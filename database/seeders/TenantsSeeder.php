<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TenantsSeeder — workspaces de prueba para desarrollo.
 *
 * Cada tenant es una "empresa cliente" del SaaS:
 *   - Empresa 1     (id=1) → admin: joe@example.com
 *   - Empresa 2     (id=2) → admin: yugi@example.com
 *   - Independiente (id=3) → admin: independiente@example.com
 *
 * Cada tenant tiene un system_user invisible asociado al final del seed
 * (creado por TenantSystemUserService). Ese usuario es el dueno de los
 * tokens API de Sanctum: sin el no hay integracion externa posible.
 *
 * Idempotente: usa updateOrInsert por id (el slug se preserva si ya existia).
 */
class TenantsSeeder extends Seeder
{
    public function run(): void
    {
        // Disclaimer legal por defecto de los informes PDF (membrete del tenant).
        // Texto genérico de laboratorio; cada empresa lo ajusta a su gusto.
        $disclaimer = fn (string $empresa) => "Los resultados de este informe corresponden únicamente a las "
            . "muestras analizadas bajo las condiciones de ensayo. {$empresa} no se responsabiliza por componentes "
            . "proporcionados por el cliente ni por el uso inadecuado de este documento. No se otorga garantía "
            . "expresa o implícita sobre la condición, productividad o correcto funcionamiento del equipo. Se prohíbe "
            . "la reproducción total o parcial de este documento sin autorización previa escrita. Los análisis y "
            . "opiniones representan el mejor juicio de {$empresa} y no son refrendados por un ente acreditador.";

        // ┌──────────────────────────────────────────────────────────────────┐
        // │ LOS DOS TEXTOS DEL INFORME QUE SOLO TIENE EL WORKSPACE 1         │
        // └──────────────────────────────────────────────────────────────────┘
        // Van SOLO en el workspace de demostración, y a propósito:
        //
        //   · el párrafo de la acreditación lleva un número de certificado
        //     concreto (AT-2596). Sembrarlo en los tres haría que cualquier
        //     instalación arrancara declarando una acreditación que no tiene —
        //     el mismo motivo por el que el informe nunca dibuja el sello de
        //     otro laboratorio;
        //   · la descripción por omisión cita un procedimiento con número de
        //     versión (P-PG-TR-LA-18-20). Los procedimientos se revisan, y
        //     otro laboratorio tiene el suyo.
        //
        // Los dos se editan en Mi workspace. Una instalación real los reemplaza
        // por los suyos ANTES de emitir el primer informe.
        $acreditacion = 'Esta prueba está acreditada bajo la acreditación del laboratorio ISO/IEC 17025 '
            . 'emitida por la Junta Nacional de Acreditación ANSI-ASQ. Consulte el certificado y el '
            . 'alcance de la acreditación AT-2596.' . "\n"
            // La segunda línea es parte del texto, no un adorno: el certificado
            // exige el párrafo bilingüe y así lo imprimía el papel viejo.
            . 'This test is accredited under the laboratory\'s ISO/IEC 17025 accreditation issued by '
            . 'the ANSI-ASQ National Accreditation Board. Refer to certificate and scope of '
            . 'accreditation AT-2596.';

        $descripcionMuestra = 'Se recibió muestra según procedimiento P-PG-TR-LA-18-20.';

        $tenants = [
            [
                'id' => 1, 'name' => 'Empresa 1',
                'address' => 'Av. Industrial 1234, Urb. Las Praderas, Lima — Perú',
                'accreditation_note' => $acreditacion,
                'sample_description_default' => $descripcionMuestra,
            ],
            ['id' => 2, 'name' => 'Empresa 2',     'address' => 'Calle Los Transformadores 567, Cerro Colorado, Arequipa — Perú'],
            ['id' => 3, 'name' => 'Independiente',  'address' => 'Jr. Eléctrico 89, Wanchaq, Cusco — Perú'],
        ];

        // Timezone explícito en cada workspace seed. Sin esto, el booted()
        // del modelo intentaria derivarlo del country del creator — pero
        // como insertamos via DB::table() (raw, sin eventos del modelo) el
        // hook no corre. Mejor ser explícitos.
        foreach ($tenants as $t) {
            $existingSlug = DB::table('tenants')->where('id', $t['id'])->value('slug');
            DB::table('tenants')->updateOrInsert(
                ['id' => $t['id']],
                [
                    'slug'              => $existingSlug ?? Str::random(22),
                    'name'              => $t['name'],
                    'address'           => $t['address'],
                    'report_disclaimer' => $disclaimer($t['name']),
                    // Solo el workspace 1 los trae; ver el bloque de arriba.
                    'accreditation_note'         => $t['accreditation_note'] ?? null,
                    'sample_description_default' => $t['sample_description_default'] ?? null,
                    'is_active'         => true,
                    'timezone'          => 'America/Lima',
                    'created_by'        => 1,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]
            );
        }

        // Reset auto-increment para que el proximo INSERT continue despues del ultimo id.
        if (config('database.default') === 'pgsql') {
            DB::statement("SELECT setval('tenants_id_seq', COALESCE((SELECT MAX(id) FROM tenants), 0) + 1, false)");
        }

        $this->command?->info('Tenants seeded: Empresa 1 (id=1), Empresa 2 (id=2), Independiente (id=3).');

        // ┌──────────────────────────────────────────────────────────────────┐
        // │ LOS DOS LOGOS DEL INFORME CLÁSICO, SOLO WORKSPACE 1              │
        // └──────────────────────────────────────────────────────────────────┘
        // El papel viejo lleva el logotipo de la empresa arriba a la IZQUIERDA
        // en todas las hojas, y el sello ANAB arriba a la DERECHA solo en las
        // hojas acreditadas (fisicoquímico, cromatografía y azufre — la
        // condición vive en `config/legacy_report.php`). El renderizador ya los
        // imprime desde `tenants.logo` / `tenants.accreditation_logo`; lo que
        // faltaba era CARGARLOS: sin esto, el informe salía sin cabecera.
        //
        // Los archivos no se versionan (repositorio público, marca registrada y
        // sello de un acreditador): viven en `storage/app/legacy-assets`,
        // copiados a mano del sistema viejo. Mismo criterio que las firmas: si
        // el archivo está, se siembra; si no, no se inventa. Y NUNCA se pisa un
        // logo que el laboratorio ya haya subido desde «Mi workspace».
        $this->logosDelWorkspaceUno();

        // System users — invisibles, duenos de los tokens API. Idempotente.
        $service = app(\App\Services\SystemManagement\TenantSystemUserService::class);
        foreach (\App\Models\Tenant::all() as $tenant) {
            $service->ensureFor($tenant);
        }
        $this->command?->info('System users created/linked for all tenants.');
    }

    /** Copia los logos del sistema viejo al workspace 1, si están y si faltan. */
    private function logosDelWorkspaceUno(): void
    {
        $mapa = [
            'logo'               => 'hitachi_logo_new.png',
            'accreditation_logo' => 'anab_logo.png',
        ];

        $sembrados = 0;

        foreach ($mapa as $columna => $archivo) {
            if (DB::table('tenants')->where('id', 1)->value($columna)) {
                continue;   // el laboratorio ya subió el suyo: no se toca
            }

            $origen = storage_path('app/legacy-assets/' . $archivo);

            if (! is_file($origen)) {
                continue;   // sin archivo no se inventa nada
            }

            $contenido = (string) file_get_contents($origen);

            // El sello del sistema viejo es un GIF con extensión .png (los
            // assets de Rails venían así). El PDF lo tolera porque lee los
            // bytes, pero el navegador recibe Content-Type image/png con datos
            // GIF y hay dónde no se dibuja. Se transcodifica a PNG real.
            if (str_starts_with($contenido, 'GIF8') && function_exists('imagecreatefromstring')) {
                $img = imagecreatefromstring($contenido);
                if ($img !== false) {
                    ob_start();
                    imagepng($img);
                    $contenido = (string) ob_get_clean();
                    imagedestroy($img);
                }
            }

            $destino = 'logos/empresa-1/' . $archivo;
            \Illuminate\Support\Facades\Storage::disk('public')
                ->put($destino, $contenido);

            DB::table('tenants')->where('id', 1)->update([$columna => $destino]);
            $sembrados++;
        }

        if ($sembrados > 0) {
            $this->command?->info("Logos del informe (workspace 1): {$sembrados} cargado(s) desde legacy-assets.");
        } elseif (! is_file(storage_path('app/legacy-assets/hitachi_logo_new.png'))) {
            $this->command?->warn(
                'Sin logos en storage/app/legacy-assets — el informe clásico sale sin cabecera. '
                . 'Copiarlos del sistema viejo o subirlos en «Mi workspace».'
            );
        }
    }
}
