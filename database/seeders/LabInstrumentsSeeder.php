<?php

namespace Database\Seeders;

use App\Models\Instrument;
use App\Models\TestDefinition;
use App\Models\TestField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Los instrumentos de bancada del laboratorio, derivados de sus propios datos.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ DE QUÉ SE PARTE                                                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema Rails viejo el instrumento no era una entidad. Era el TEXTO de
 * una opción de un campo de selección:
 *
 *     Prueba "Número Ácido" → columna "Bureta PP-LA-01C"
 *         opciones: PP-LA-01C-023 · PP-LA-01C-065 · PP-LA-01C-100 · PP-LA-01C-100.
 *
 * Consecuencias, todas reales y todas visibles en el volcado:
 *
 *   · El código de calibración vivía adentro del nombre, como texto. No había
 *     fecha de calibración ni de vencimiento, así que el sistema no podía decir
 *     si el equipo estaba vigente el día del ensayo — que es literalmente lo
 *     que exige ISO 17025 para que un resultado sea trazable.
 *   · El mismo equipo estaba repetido en cada prueba que lo usara. La balanza
 *     PP-LA-01C-056 aparece como opción del Número Ácido y otra vez del
 *     Contenido de Agua: dos filas, sin relación entre sí. Cambiar el código
 *     obligaba a editar las dos.
 *   · Y no había nada que impidiera las erratas: ahí está 'PP-LA-01C-100.', con
 *     el punto al final, conviviendo con 'PP-LA-01C-100'.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ HACE ESTE SEMBRADOR                                                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 *   1. Lee las opciones importadas, saca los códigos y da de alta un equipo por
 *      código —uno solo, aunque aparezca en cinco pruebas—.
 *   2. Cambia el tipo de esas columnas de `select` a `instrument`, así la hoja
 *      de trabajo guarda una clave foránea al equipo en vez de un texto.
 *
 * El nombre de cada equipo NO se puede deducir del código: sale del archivo
 * `database/seeders/data/instruments.json`, donde está declarado por columna.
 * Lo que no esté declarado ahí se deja como estaba, y se avisa.
 *
 * Las opciones viejas NO se borran: quedan como constancia de qué ofrecía la
 * plantilla original. Con la columna en tipo `instrument` ya no se leen.
 *
 * Idempotente: se ancla al código del equipo.
 */
class LabInstrumentsSeeder extends Seeder
{
    /** El workspace de demostración, el mismo donde entran los clientes. */
    private const TENANT_ID = 1;

    /** Un código de calibración del laboratorio: PP-LA-01C-023, o el pelado. */
    private const CODIGO = '/\b(PP-LA-\d+[A-Z](?:-\d+)?)\b/';

    public function run(): void
    {
        $ruta = database_path('seeders/data/instruments.json');

        if (! is_file($ruta)) {
            $this->command?->warn('No se encontró instruments.json. Nada que sembrar.');

            return;
        }

        $json = json_decode((string) file_get_contents($ruta), true) ?: [];
        $nombres = $json['nombres'] ?? [];
        $detalle = $json['detalle'] ?? [];

        $creados = 0;
        $columnas = 0;
        $sinNombre = [];

        foreach ($this->columnasConCodigos() as $clave => $columna) {
            $codigos = $this->codigosDe($columna);

            if ($codigos === []) {
                continue;
            }

            if (! isset($nombres[$clave])) {
                // Sin nombre declarado no se da de alta nada. Un instrumento
                // llamado "PP-LA-01C-013" no le dice a nadie qué equipo es, y
                // el nombre no se puede adivinar del código.
                $sinNombre[$clave] = $codigos;
                continue;
            }

            foreach ($codigos as $codigo) {
                $creados += $this->registrar($codigo, $nombres[$clave], $detalle[$codigo] ?? []);
            }

            if ($columna->type !== 'instrument') {
                $columna->update(['type' => 'instrument']);
                $columnas++;
            }
        }

        $total = Instrument::withoutGlobalScopes()->count();
        $this->command?->info(
            "Instrumentos: {$creados} dados de alta ({$total} en total), "
            . "{$columnas} columnas pasadas a selección de equipo."
        );

        foreach ($sinNombre as $clave => $codigos) {
            $this->command?->warn(
                "  · '{$clave}' ofrece " . count($codigos) . ' códigos y no está en instruments.json: se deja como estaba.'
            );
        }
    }

    /**
     * Las columnas de selección cuyas opciones son códigos de calibración.
     *
     * Se decide POR LOS DATOS y no por una lista escrita a mano: una columna
     * cuyas opciones son "Mineral / Vegetal / Silicona" no es un instrumento, y
     * una cuyas opciones son todas PP-LA-… no puede ser otra cosa.
     *
     * @return array<string,TestField>
     */
    private function columnasConCodigos(): array
    {
        $pruebas = TestDefinition::pluck('code', 'id');
        $salida = [];

        $columnas = TestField::with('options')
            ->whereIn('type', ['select', 'instrument'])
            ->get();

        foreach ($columnas as $columna) {
            $opciones = $columna->options;

            if ($opciones->isEmpty()) {
                continue;
            }

            // TODAS las opciones tienen que ser códigos. Con que una no lo sea,
            // la columna está ofreciendo otra cosa además del equipo y
            // convertirla perdería esa opción.
            $todas = $opciones->every(fn ($o) => (bool) preg_match(self::CODIGO, (string) $o->value));

            if (! $todas) {
                continue;
            }

            $prueba = $pruebas[$columna->test_definition_id] ?? null;

            if ($prueba === null) {
                continue;
            }

            $salida["{$prueba}.{$columna->code}"] = $columna;
        }

        return $salida;
    }

    /**
     * Los códigos distintos que ofrece una columna.
     *
     * La expresión saca el código de adentro del texto, que es lo que resuelve
     * de una sola vez los dos formatos del viejo ('PP-LA-01C-007' pelado y
     * 'R1 PP-LA-01C-007' con el prefijo del reactivo) y de paso limpia la
     * errata 'PP-LA-01C-100.', que queda unificada con 'PP-LA-01C-100'.
     *
     * @return array<int,string>
     */
    private function codigosDe(TestField $columna): array
    {
        $codigos = [];

        foreach ($columna->options as $opcion) {
            if (preg_match(self::CODIGO, (string) $opcion->value, $m)) {
                $codigos[$m[1]] = true;
            }
        }

        return array_keys($codigos);
    }

    /**
     * Da de alta el equipo si no está. Devuelve 1 si lo creó.
     *
     * NO refresca el nombre de uno que ya exista: el laboratorio puede haberlo
     * precisado desde la pantalla ("Balanza analítica" → "Balanza Mettler
     * XPE205"), y ese dato vale más que el nuestro.
     *
     * @param array<string,mixed> $detalle
     */
    private function registrar(string $codigo, string $nombre, array $detalle): int
    {
        $existente = Instrument::withoutGlobalScopes()
            ->where('tenant_id', self::TENANT_ID)
            ->where('code', $codigo)
            ->first();

        if ($existente) {
            return 0;
        }

        Instrument::create([
            'slug'      => Str::random(22),
            'code'      => $codigo,
            'name'      => $nombre,
            'tenant_id' => self::TENANT_ID,
            'is_active' => true,
            // La calibración se siembra VACÍA a propósito. El sistema viejo no
            // la guardaba, así que no hay de dónde sacarla, y una fecha
            // inventada es trazabilidad inventada: el sistema diría que el
            // equipo estaba vigente sin que nadie lo haya verificado.
            'brand'                   => $detalle['brand'] ?? null,
            'model'                   => $detalle['model'] ?? null,
            'serial'                  => $detalle['serial'] ?? null,
            'location'                => $detalle['location'] ?? null,
            'calibration_certificate' => $detalle['calibration_certificate'] ?? null,
            'calibrated_at'           => $detalle['calibrated_at'] ?? null,
            'calibration_due_at'      => $detalle['calibration_due_at'] ?? null,
            'notes'                   => 'Dado de alta desde las opciones de la plantilla del sistema anterior. '
                                       . 'Falta cargar marca, modelo, serie y calibración.',
        ]);

        return 1;
    }
}
