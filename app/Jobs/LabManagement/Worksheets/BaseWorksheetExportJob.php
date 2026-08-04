<?php

namespace App\Jobs\LabManagement\Worksheets;

use App\Models\Download;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Base de los cuatro trabajos de exportación del listado de bancada.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ ES EL LISTADO, NO EL CONTENIDO DE LA HOJA                                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Lo que sale es la MISMA tabla que se está mirando: qué se corrió, qué día,
 * quién, en qué estado y con cuántas muestras. Los valores medidos NO salen por
 * acá — el resultado de un ensayo se informa por su informe, que lleva firma,
 * código de verificación y límites de norma. Una planilla suelta con los
 * números crudos y sin nada de eso es exactamente lo que el laboratorio no
 * puede mandarle a un cliente.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL TRABAJADOR DE COLA NO TIENE SESIÓN                                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Por eso el workspace se captura al encolar y se vuelve a aplicar a mano en
 * `buildQuery()`: el ámbito de `BelongsToTenant` necesita un usuario en sesión
 * y en el trabajador no hay ninguno. Sin esa línea, un administrador se lleva
 * en su exportación las hojas de TODAS las empresas.
 */
abstract class BaseWorksheetExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Diez minutos: cubre el peor caso del CSV, que va por lotes. */
    public int $timeout = 600;

    public int $tries = 2;

    /** La subclase declara: 'csv' | 'excel' | 'pdf' | 'word'. */
    protected string $type;

    /** La subclase declara la extensión del archivo, sin punto. */
    protected string $extension;

    protected int $userId;
    protected array $options;
    protected string $locale;
    protected string $userTimezone;
    protected ?Download $download = null;
    protected ?int $tenantId = null;
    protected ?int $downloadId = null;

    public function __construct(int $userId, array $options = [])
    {
        $this->userId  = $userId;
        $this->options = $options;
        $this->locale  = app()->getLocale();

        $user = \App\Models\User::find($userId);
        $this->tenantId     = $user?->tenant_id;
        $this->userTimezone = \App\Support\Tz::for($user);

        // El registro de descarga se crea ACÁ, en la petición web, no en el
        // trabajador: así la campana del usuario muestra "Generando…" al
        // instante en vez de esperar a que la cola tome el trabajo.
        $this->download = Download::create([
            'slug'       => Str::random(22),
            'user_id'    => $userId,
            'type'       => $this->type,
            'filename'   => $this->generateFilename(),
            'path'       => '',
            'disk'       => 'local',
            'status'     => 'processing',
            'expires_at' => Download::computeExpiresAt(),
        ]);
        $this->downloadId = $this->download->id;
    }

    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        app()->setLocale($this->locale);

        $this->download = Download::find($this->downloadId);
        if (! $this->download) {
            return; // el usuario lo borró antes de que arrancara
        }

        if ($this->download->status !== 'processing') {
            $this->download->update(['status' => 'processing', 'error_message' => null]);
        }

        try {
            $this->executeExport($this->download);
        } catch (\Throwable $e) {
            $this->download->update(['status' => 'failed', 'error_message' => $e->getMessage()]);

            \Log::error(static::class . ' falló', [
                'download_id' => $this->downloadId,
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /** El trabajo murió del todo (reintentos agotados, tiempo, memoria). */
    public function failed(\Throwable $exception): void
    {
        if ($this->downloadId) {
            Download::where('id', $this->downloadId)
                ->whereIn('status', ['processing', 'failed'])
                ->update([
                    'status'        => 'failed',
                    'error_message' => substr($exception->getMessage(), 0, 200),
                ]);
        }
    }

    abstract protected function executeExport(Download $download): void;

    /**
     * El alcance elegido en el diálogo: lo filtrado, lo seleccionado, o todo.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Worksheet>
     */
    protected function buildQuery()
    {
        $scope   = $this->options['scope'] ?? 'filtered';
        $columns = $this->options['columns'] ?? [];

        $base = Worksheet::query()
            ->with(['definition:id,name', 'analyst:id,name', 'validator:id,name'])
            ->withCount([
                'rows',
                'rows as samples_count' => fn ($q) => $q->where('kind', WorksheetRow::KIND_SAMPLE),
            ]);

        // El ámbito de workspace, a mano: el trabajador no tiene sesión.
        if ($this->tenantId !== null) {
            $base->where('worksheets.tenant_id', $this->tenantId);
        }

        if (in_array('creator', $columns, true)) {
            $base->with('creator:id,name');
        }

        if ($scope === 'selected' && ! empty($this->options['selected_ids'])) {
            return $base->whereIn('worksheets.id', $this->options['selected_ids']);
        }

        if ($scope === 'all') {
            return $base;
        }

        // `scopeFilter` espera una petición: se rearma con los filtros que la
        // pantalla tenía puestos, para que lo exportado sea lo que se veía.
        return $base->filter(new \Illuminate\Http\Request($this->options['filters'] ?? []));
    }

    /**
     * Los filtros activos, escritos, para la portada del PDF y del Word.
     *
     * Que el archivo diga con qué recorte se generó no es adorno: una planilla
     * de 40 hojas sin decir que estaba filtrada por una prueba se lee como si
     * fueran todas las del laboratorio.
     *
     * @return array<int, array{label: string, value: string}>
     */
    protected function buildFiltersSummary(): array
    {
        $f = $this->options['filters'] ?? [];
        $out = [];

        if (! empty($f['test_definition'])) {
            $nombre = \App\Models\TestDefinition::where('slug', $f['test_definition'])->value('name');
            $out[] = ['label' => __('worksheets.test_definition'), 'value' => $nombre ?? (string) $f['test_definition']];
        }
        if (! empty($f['status'])) {
            $out[] = ['label' => __('worksheets.status'), 'value' => __('worksheets.state.' . $f['status'])];
        }
        if (! empty($f['analyst'])) {
            $out[] = ['label' => __('worksheets.analyst'), 'value' => \App\Models\User::find($f['analyst'])?->name ?? (string) $f['analyst']];
        }
        if (! empty($f['sample'])) {
            $out[] = ['label' => __('worksheets.sample_code'), 'value' => (string) $f['sample']];
        }
        if (! empty($f['from']) || ! empty($f['to'])) {
            $out[] = ['label' => __('worksheets.run_date'), 'value' => ($f['from'] ?? '…') . ' → ' . ($f['to'] ?? '…')];
        }
        if (! empty($f['only_favorites']) && filter_var($f['only_favorites'], FILTER_VALIDATE_BOOLEAN)) {
            $out[] = ['label' => __('global.only_favorites'), 'value' => '✓'];
        }

        return $out;
    }

    protected function generateFilename(): string
    {
        $base = Str::slug($this->options['title'] ?? __('worksheets.title'));

        return $base . '_' . now()->format('Y-m-d_H-i-s') . '.' . $this->extension;
    }

    /**
     * El valor de una columna para una hoja, en texto.
     *
     * Vive acá y no en cada formato para que el CSV, el Excel, el PDF y el
     * Word digan LO MISMO: cuatro copias de este `match` son cuatro lugares
     * donde el estado se puede traducir distinto.
     */
    protected function cellValue(Worksheet $hoja, string $columna): string
    {
        $tz = $this->userTimezone;

        return match ($columna) {
            'id'               => (string) $hoja->id,
            'slug'             => (string) $hoja->slug,
            'run_date'         => $hoja->run_date?->format('d-m-Y') ?? '',
            'definition'       => $hoja->definition?->name ?? '',
            'analyst'          => $hoja->analyst?->name ?? '',
            'status'           => __('worksheets.state.' . $hoja->status),
            'rows_count'       => (string) ($hoja->rows_count ?? 0),
            'samples_count'    => (string) ($hoja->samples_count ?? 0),
            'validator'        => $hoja->validator?->name ?? '',
            'validated_at'     => $hoja->validated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? '',
            'ambient_temp_c'   => $hoja->ambient_temp_c   !== null ? (string) $hoja->ambient_temp_c   : '',
            'ambient_humidity' => $hoja->ambient_humidity !== null ? (string) $hoja->ambient_humidity : '',
            'lab_pressure_hpa' => $hoja->lab_pressure_hpa !== null ? (string) $hoja->lab_pressure_hpa : '',
            'notes'            => (string) ($hoja->notes ?? ''),
            'created_at'       => $hoja->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? '',
            'creator'          => $hoja->creator?->name ?? '',
            default            => (string) ($hoja->{$columna} ?? ''),
        };
    }

    /** El encabezado de una columna, en el idioma del usuario. */
    protected function heading(string $columna): string
    {
        return match ($columna) {
            'id'               => 'ID',
            'slug'             => 'Slug',
            'run_date'         => __('worksheets.run_date'),
            'definition'       => __('worksheets.test_definition'),
            'analyst'          => __('worksheets.analyst'),
            'status'           => __('worksheets.status'),
            'rows_count'       => __('worksheets.rows_count'),
            'samples_count'    => __('worksheets.samples_count'),
            'validator'        => __('worksheets.validated_by'),
            'validated_at'     => __('worksheets.validated_at'),
            'ambient_temp_c'   => __('worksheets.ambient_temp_c'),
            'ambient_humidity' => __('worksheets.ambient_humidity'),
            'lab_pressure_hpa' => __('worksheets.lab_pressure_hpa'),
            'notes'            => __('worksheets.notes'),
            'created_at'       => __('global.created_at'),
            'creator'          => __('global.created_by'),
            default            => $columna,
        };
    }

    /** Las columnas pedidas, o el juego por omisión si no vino ninguna. */
    protected function activeColumns(): array
    {
        $pedidas = $this->options['columns'] ?? [];

        return $pedidas !== []
            ? $pedidas
            : ['run_date', 'definition', 'analyst', 'status', 'rows_count', 'samples_count', 'validator'];
    }
}
