<?php

namespace Database\Seeders;

use App\Models\EntryAuthorizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * El personal del laboratorio que autoriza el ingreso de muestras.
 *
 * Es el catálogo «Personal de Laboratorio» del sistema anterior
 * (`rem_user_signatures`): una lista PROPIA, separada de los firmantes de
 * informes. El elegido es obligatorio al registrar una recepción, así que sin
 * al menos una persona acá el alta no se puede completar — por eso va en el
 * seed y en el resumen de `setup:project`.
 *
 * Mismo criterio que `SignaturesSeeder` con la imagen: se siembra si el
 * archivo está en `database/seeders/data/entry-authorizers/`, y no se inventa
 * si no. Los datos reales del catálogo del laboratorio (la lista completa con
 * sus firmas escaneadas) entran por el volcado privado — este repositorio es
 * público y no los versiona.
 *
 * Idempotente por nombre: volver a correr el seed no duplica ni pisa.
 */
class EntryAuthorizersSeeder extends Seeder
{
    /**
     * @var array<int,array{name:string,file:string}>
     */
    private const PERSONAL = [
        [
            'name' => 'RAMOS AGAPITO, JESSICA DEL ROSARIO',
            'file' => 'ramos-agapito.png',
        ],
    ];

    public function run(): void
    {
        $tenantId = (int) config('lab.seed_tenant_id', 1);
        $origen   = database_path('seeders/data/entry-authorizers');

        foreach (self::PERSONAL as $orden => $persona) {
            $registro = EntryAuthorizer::withoutGlobalScopes()->firstOrNew([
                'name'      => $persona['name'],
                'tenant_id' => $tenantId,
            ]);

            if (! $registro->exists) {
                $registro->fill([
                    'slug'       => Str::random(22),
                    'sort_order' => $orden + 1,
                    'is_active'  => true,
                ]);
            }

            // La imagen SÍ se refresca en cada corrida (es el archivo del
            // repositorio); el resto no se pisa.
            $ruta = $this->copiarImagen($origen, $persona['file']);
            if ($ruta !== null) {
                $registro->image = $ruta;
            }

            $registro->save();
        }

        $this->command?->info('Personal que autoriza ingresos: ' . count(self::PERSONAL) . ' persona(s).');
    }

    /** Copia la firma al disco público y devuelve su ruta, o null si no está. */
    private function copiarImagen(string $origen, string $archivo): ?string
    {
        $completa = $origen . DIRECTORY_SEPARATOR . $archivo;

        if (! is_file($completa)) {
            return null;
        }

        $destino = 'entry-authorizers/' . $archivo;
        Storage::disk('public')->put($destino, (string) file_get_contents($completa));

        return $destino;
    }
}
