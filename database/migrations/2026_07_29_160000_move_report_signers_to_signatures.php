<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Los firmantes pasan al módulo FIRMAS.
 *
 * Estaban en `report_signers`, y se administraban desde una tarjeta escondida
 * dentro de "Mi workspace". Eso es un módulo metido a la fuerza en la pantalla
 * de otro: el laboratorio mantiene sus firmantes como mantiene sus
 * instrumentos, sus muestreadores y sus pruebas — con su listado, su búsqueda,
 * su papelera y su auditoría.
 *
 * Se traspasa lo que hubiera cargado, sin perder nada: nombre, cargo, relación,
 * orden y el enlace al usuario. La tabla vieja NO se borra acá — sigue
 * respaldando el flujo de aprobaciones heredado (`report_approvals` la
 * referencia), y tirarla en la misma migración que mueve el dato sería quedarse
 * sin de dónde recuperarlo si algo salió mal.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('report_signers')) {
            return;
        }

        foreach (DB::table('report_signers')->orderBy('id')->get() as $viejo) {
            $existe = DB::table('signatures')
                ->where('tenant_id', $viejo->tenant_id)
                ->where('name', $viejo->name)
                ->where('user_id', $viejo->user_id)
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('signatures')->insert([
                'slug'       => Str::random(22),
                'name'       => $viejo->name,
                'title'      => $viejo->title,
                'user_id'    => $viejo->user_id,
                'relation'   => $viejo->relation ?? 'approved',
                'sort_order' => $viejo->sort_order ?? 1,
                'is_active'  => true,
                'tenant_id'  => $viejo->tenant_id,
                'created_at' => $viejo->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // El dato original sigue en `report_signers`: no hay nada que revertir.
    }
};
