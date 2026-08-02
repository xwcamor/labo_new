<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * El autorizador del ingreso deja de ser una bandera en Firmas y pasa a su
 * catálogo propio (`entry_authorizers`).
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ SE MUEVE (la corrección de una corrección)                       │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La primera reposición de este dato lo colgó de `signatures` con una bandera
 * `authorizes_entry`. Estaba mal, y el laboratorio lo señaló: en el sistema
 * anterior el «Personal de Laboratorio» que autoriza ingresos
 * (`rem_user_signatures`) es un catálogo PROPIO, separado de los firmantes de
 * informes (`rem_signatures`) — con su propia pantalla, su propio menú y su
 * propia firma escaneada. Mezclarlos en una tabla obligaba a dar de alta como
 * «firmante de informes» a gente que jamás firma un informe, solo para que
 * pudiera autorizar un ingreso.
 *
 * Esta migración corre DESPUÉS de crear `entry_authorizers`: traslada al
 * catálogo nuevo las firmas que hubieran quedado marcadas con la bandera,
 * re-apunta `receptions.authorized_by_id` conservando lo elegido, y elimina la
 * bandera. En una instalación que nunca corrió la bandera, las dos primeras
 * partes no encuentran nada y solo queda el esquema correcto.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 0. Copia de lo elegido en las recepciones, ANTES de tirar la
        //    columna: reception_id => signature_id.
        $previas = Schema::hasColumn('receptions', 'authorized_by_id')
            ? DB::table('receptions')->whereNotNull('authorized_by_id')
                ->pluck('authorized_by_id', 'id')->all()
            : [];

        // 1. Trasladar al catálogo nuevo las firmas marcadas (si las hay),
        //    recordando el mapeo signature_id => entry_authorizer_id.
        $mapa = [];

        if (Schema::hasColumn('signatures', 'authorizes_entry')) {
            $marcadas = DB::table('signatures')
                ->where('authorizes_entry', true)
                ->whereNull('deleted_at')
                ->get(['id', 'name', 'image', 'is_active', 'sort_order', 'tenant_id']);

            foreach ($marcadas as $firma) {
                $existente = DB::table('entry_authorizers')
                    ->where('tenant_id', $firma->tenant_id)
                    ->whereRaw('LOWER(name) = LOWER(?)', [$firma->name])
                    ->value('id');

                $mapa[$firma->id] = $existente ?: DB::table('entry_authorizers')->insertGetId([
                    'slug'       => Str::random(22),
                    'name'       => $firma->name,
                    // La imagen se COMPARTE en disco, no se duplica: las dos
                    // filas apuntan al mismo archivo. No hay riesgo de borrado
                    // cruzado: el archivo solo se elimina al REEMPLAZAR la
                    // imagen desde el módulo respectivo.
                    'image'      => $firma->image,
                    'is_active'  => $firma->is_active,
                    'sort_order' => $firma->sort_order,
                    'tenant_id'  => $firma->tenant_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Re-apuntar receptions.authorized_by_id al catálogo nuevo.
        if (Schema::hasColumn('receptions', 'authorized_by_id')) {
            Schema::table('receptions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('authorized_by_id');
            });
        }

        Schema::table('receptions', function (Blueprint $table) {
            $table->foreignId('authorized_by_id')->nullable()->after('sampler_name')
                ->constrained('entry_authorizers')->nullOnDelete();
        });

        // 3. Reponer lo elegido, ya traducido al catálogo nuevo.
        foreach ($previas as $receptionId => $firmaId) {
            if (isset($mapa[$firmaId])) {
                DB::table('receptions')->where('id', $receptionId)
                    ->update(['authorized_by_id' => $mapa[$firmaId]]);
            }
        }

        // 4. La bandera se va de Firmas.
        if (Schema::hasColumn('signatures', 'authorizes_entry')) {
            Schema::table('signatures', function (Blueprint $table) {
                $table->dropColumn('authorizes_entry');
            });
        }
    }

    public function down(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('authorized_by_id');
        });

        Schema::table('signatures', function (Blueprint $table) {
            $table->boolean('authorizes_entry')->default(false)->after('relation');
        });

        Schema::table('receptions', function (Blueprint $table) {
            $table->foreignId('authorized_by_id')->nullable()->after('sampler_name')
                ->constrained('signatures')->nullOnDelete();
        });
    }
};
