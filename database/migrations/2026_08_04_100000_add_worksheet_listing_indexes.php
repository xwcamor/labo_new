<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los índices que el listado de bancada necesita para no degradarse.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ MEDIDO, NO SUPUESTO                                                      │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Con 10.000 hojas sembradas y medido contra el controlador real:
 *
 *   Ordenar por analista ....... 3.063 ms   ← la subconsulta se evaluaba fila a fila
 *   Buscar por Nº de muestra ..... 413 ms   ← LIKE '%x%' sin índice, tabla completa
 *   Página 1 sin filtros ......... 971 ms
 *   El resto ................ 30 – 130 ms
 *
 * Tres segundos para ordenar una columna es una pantalla rota. La causa NO era
 * la paginación —esa siempre trajo solo la página— sino que la tabla no tenía
 * índice en las columnas por las que el listado ordena y filtra: `run_date`
 * (que es el orden por omisión), `analyst_id`, `validated_by` y `created_by`.
 *
 * `deleted_at` entra por otro motivo: TODA consulta del módulo lleva
 * `deleted_at IS NULL` por el borrado lógico, y sin índice eso es un recorrido
 * completo en cada una.
 *
 * El índice trigram de `sample_code` es solo de Postgres: el buscador de Nº de
 * muestra usa `LIKE '%0001%'` —hay que poder buscar por el final del código, no
 * solo por el año— y un comodín al principio deja fuera de juego a un índice
 * normal. En SQLite (los tests) no se crea y no hace falta: ahí las tablas son
 * de juguete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worksheets', function (Blueprint $table) {
            $table->index('run_date', 'worksheets_run_date_index');
            $table->index('analyst_id', 'worksheets_analyst_id_index');
            $table->index('validated_by', 'worksheets_validated_by_index');
            $table->index('created_by', 'worksheets_created_by_index');
            $table->index('deleted_at', 'worksheets_deleted_at_index');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement(
            'CREATE INDEX IF NOT EXISTS worksheet_rows_sample_code_trgm
             ON worksheet_rows USING gin (sample_code gin_trgm_ops)'
        );
    }

    public function down(): void
    {
        Schema::table('worksheets', function (Blueprint $table) {
            $table->dropIndex('worksheets_run_date_index');
            $table->dropIndex('worksheets_analyst_id_index');
            $table->dropIndex('worksheets_validated_by_index');
            $table->dropIndex('worksheets_created_by_index');
            $table->dropIndex('worksheets_deleted_at_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS worksheet_rows_sample_code_trgm');
            // La extensión NO se borra: otras tablas podrían estar usándola.
        }
    }
};
