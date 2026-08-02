<?php

namespace Database\Seeders;

use App\Models\Signature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Quiénes firman los informes del laboratorio.
 *
 * Va en el seed como las pruebas, los grupos y los instrumentos: son datos de
 * fábrica del laboratorio, no algo que alguien tenga que cargar a mano después
 * de cada instalación.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA IMAGEN SE SIEMBRA SI ESTÁ, Y NO SE INVENTA SI NO                      │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Las firmas escaneadas viven en `database/seeders/data/signatures/` con el
 * nombre del archivo declarado acá abajo. Si el archivo está, se copia al disco
 * público y el informe la estampa. Si no está, el firmante se crea igual y el
 * informe deja la línea para firmar a mano.
 *
 * No se sustituye por un placeholder ni se deja el registro afuera: un informe
 * sin la imagen es correcto —se firma a mano— y un informe con una firma
 * inventada no lo es.
 *
 * Idempotente por nombre: volver a correr el seed no duplica ni pisa el cargo
 * que el laboratorio haya corregido.
 */
class SignaturesSeeder extends Seeder
{
    /**
     * @var array<int,array{name:string,title:string,relation:string,file:string}>
     */
    private const FIRMANTES = [
        [
            'name'     => 'RAMOS AGAPITO, JESSICA DEL ROSARIO',
            'title'    => 'Testing & Oil Laboratory Specialist',
            'relation' => 'prepared',
            'file'     => 'ramos-agapito.png',
            // Puede AUTORIZAR el ingreso de muestras (el papel del
            // `rem_user_signatures` del sistema anterior). Sin al menos una
            // persona con este papel, el alta de recepciones no se puede
            // completar: el autorizador es obligatorio.
            'authorizes' => true,
        ],
        [
            'name'     => 'HIGA YAGI, OSCAR MIGUEL',
            'title'    => 'Engineering Manager I',
            'relation' => 'approved',
            'file'     => 'higa-yagi.png',
            // INACTIVO por pedido del laboratorio (2026-08-02): ya no firma los
            // informes. La fila NO se borra —los informes que ya salieron con
            // su firma tienen que poder explicar de dónde salió ese nombre— y
            // por eso se da de baja en vez de eliminarse, que es el mismo
            // criterio que las listas del informe.
            'active'   => false,
        ],
    ];

    public function run(): void
    {
        $tenantId = (int) config('lab.seed_tenant_id', 1);
        $origen   = database_path('seeders/data/signatures');
        $conImagen = 0;

        foreach (self::FIRMANTES as $orden => $firmante) {
            $ruta = $this->copiarImagen($origen, $firmante['file']);

            if ($ruta !== null) {
                $conImagen++;
            }

            $registro = Signature::firstOrNew([
                'name'      => $firmante['name'],
                'tenant_id' => $tenantId,
            ]);

            if (! $registro->exists) {
                $registro->fill([
                    'slug'       => Str::random(22),
                    'title'      => $firmante['title'],
                    'relation'   => $firmante['relation'],
                    'sort_order' => $orden + 1,
                    'is_active'  => $firmante['active'] ?? true,
                    'authorizes_entry' => $firmante['authorizes'] ?? false,
                ]);
            }

            // Bootstrap del papel de autorizador sobre registros EXISTENTES:
            // solo si el workspace no tiene a NADIE habilitado — sin al menos
            // uno, el alta de recepciones no se puede completar. Con alguno ya
            // elegido (aunque sea otro), no se toca nada: esa lista es del
            // laboratorio, no del seed.
            if (($firmante['authorizes'] ?? false)
                && ! $registro->authorizes_entry
                && ! Signature::where('tenant_id', $tenantId)->where('authorizes_entry', true)->exists()) {
                $registro->authorizes_entry = true;
            }

            // La imagen SÍ se refresca en cada corrida, aunque el registro ya
            // exista: es el archivo del repositorio, y si el laboratorio lo
            // reemplazó por uno mejor escaneado quiere ver el nuevo. El cargo,
            // en cambio, no se pisa — ése lo corrige el laboratorio.
            if ($ruta !== null) {
                $registro->image = $ruta;
            }

            $registro->save();
        }

        $total = count(self::FIRMANTES);
        $this->command?->info("Firmas: {$total} firmantes ({$conImagen} con imagen).");

        if ($conImagen < $total) {
            $this->command?->warn(
                'Faltan firmas escaneadas en database/seeders/data/signatures/. '
                . 'El informe dejará la línea para firmar a mano hasta que estén.'
            );
        }
    }

    /** Copia la firma al disco público y devuelve su ruta, o null si no está. */
    private function copiarImagen(string $origen, string $archivo): ?string
    {
        $completa = $origen . DIRECTORY_SEPARATOR . $archivo;

        if (! is_file($completa)) {
            return null;
        }

        $destino = 'signatures/' . $archivo;
        Storage::disk('public')->put($destino, (string) file_get_contents($completa));

        return $destino;
    }
}
