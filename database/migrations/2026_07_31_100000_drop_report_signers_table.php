<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira `report_signers`: los firmantes viven en el módulo Firmas.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ HABÍA DOS TABLAS PARA LO MISMO                                   │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `report_signers` viene de la base SaaS sobre la que se montó el laboratorio: un
 * "slot de firma" por workspace, con su editor dentro de "Mi workspace". Después
 * se construyó el módulo FIRMAS (`signatures`), que es un catálogo completo
 * —pantalla propia, papelera, auditoría, candado, favoritos— y es el que el
 * informe IMPRIME.
 *
 * Quedaron las dos, y no en paralelo inofensivo: el PAPEL se firmaba con
 * `signatures` y el FLUJO DE APROBACIÓN se armaba con `report_signers`, incluido
 * el gate del menú "Aprobaciones". Un laboratorio que cargaba sus firmas en el
 * módulo —lo natural, es el que aparece en el sidebar— nunca veía la bandeja de
 * aprobaciones, y no había nada en pantalla que explicara por qué.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ PASA CON LOS DATOS                                                   │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Se COPIAN al catálogo antes de borrar la tabla. En esta instalación está vacía,
 * pero una que no lo esté no puede perder sus firmantes por una migración: sería
 * un informe que sale sin la línea de firma que venía saliendo.
 *
 * Se copia solo lo que el catálogo no tenga ya con el mismo nombre: si alguien
 * cargó al mismo firmante en los dos lugares —que es exactamente lo que pasaría
 * al descubrir que el papel no lo mostraba— no se lo duplica.
 *
 * `report_approvals.report_signer_id` NO se renombra: renombrar una columna con
 * clave foránea en Postgres arrastra su índice y su restricción, y el nombre de
 * la columna no es lo que decide a qué apunta — eso lo dice el modelo, que ya
 * apunta a `Signature`. Queda anotado ahí.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_signers')) {
            return;
        }

        $this->rescatarFirmantes();

        // `report_approvals.report_signer_id` se declaró como entero con índice,
        // SIN clave foránea (ver la migración que creó la tabla), así que no hay
        // nada que soltar antes de borrar. Se pide igual el DROP CONSTRAINT IF
        // EXISTS por si alguna instalación sí la tiene: sin el `IF EXISTS`,
        // Postgres corta la migración con "constraint does not exist" — y hacerlo
        // con un try/catch dentro del closure de `Schema::table` NO alcanza,
        // porque Laravel ejecuta esas sentencias DESPUÉS de correr el closure y
        // la excepción se levanta fuera del try.
        if (Schema::hasTable('report_approvals') && DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE report_approvals DROP CONSTRAINT IF EXISTS '
                . 'report_approvals_report_signer_id_foreign'
            );
        }

        Schema::dropIfExists('report_signers');
    }

    /**
     * Los firmantes que estuvieran cargados en la tabla vieja pasan al catálogo.
     */
    private function rescatarFirmantes(): void
    {
        $viejos = DB::table('report_signers')->orderBy('tenant_id')->orderBy('sort_order')->get();

        foreach ($viejos as $viejo) {
            $nombre = $viejo->name;

            // El nombre impreso salía del usuario cuando había uno.
            if (! $nombre && $viejo->user_id) {
                $nombre = DB::table('users')->where('id', $viejo->user_id)->value('name');
            }

            $nombre = $nombre ?: ('Firmante '.$viejo->id);

            $yaEsta = DB::table('signatures')
                ->where('tenant_id', $viejo->tenant_id)
                ->where('name', $nombre)
                ->whereNull('deleted_at')
                ->exists();

            if ($yaEsta) {
                continue;
            }

            DB::table('signatures')->insert([
                'slug'       => \Illuminate\Support\Str::random(22),
                'tenant_id'  => $viejo->tenant_id,
                'name'       => $nombre,
                'title'      => $viejo->title,
                'relation'   => $viejo->relation ?: 'approved',
                'user_id'    => $viejo->user_id,
                'sort_order' => $viejo->sort_order ?: 0,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No se recrea. La tabla no tenía nada que el catálogo no tenga, y
        // devolverla vacía dejaría otra vez dos lugares donde configurar lo mismo
        // —que es el problema que esta migración cierra—.
    }
};
