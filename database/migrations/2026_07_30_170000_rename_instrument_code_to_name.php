<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El instrumento se llama por su código: `code` pasa a ser `name`, y el nombre
 * viejo pasa a ser `description`.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ                                                                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el laboratorio el instrumento se identifica por su código de calibración:
 * "PP-LA-01C-100". Eso es su NOMBRE — es lo que el analista dice, lo que va en
 * la hoja de bancada y lo que el informe imprime para que el resultado sea
 * trazable. Lo que la tabla llamaba `name` era en realidad el TIPO de equipo
 * ("Bureta", "Balanza analítica", "Espinterómetro"): tres buretas comparten esa
 * palabra y son tres equipos distintos.
 *
 * El esquema decía lo contrario de lo que hace el sistema, y eso se notaba en
 * cada pantalla: el listado ordenaba por "Nombre" y mostraba doce filas que
 * decían "Bureta"; la clave única —y todas las reglas de los FormRequests, y el
 * importador— tenían que aclarar en un comentario que la clave natural NO era el
 * nombre. Cuando el comentario de una columna tiene que desmentir su propio
 * nombre, lo que hay que cambiar es el nombre.
 *
 *   code (PP-LA-01C-100)  →  name         obligatorio, único por workspace
 *   name (Bureta)         →  description  opcional, texto libre
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ ORDEN DE LAS OPERACIONES                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Los dos renombres se cruzan, así que el orden importa: primero se libera el
 * nombre `name` (name → description) y solo después se ocupa (code → name). Los
 * índices se bajan ANTES: Postgres los conserva al renombrar la columna, y
 * quedarían dos índices con nombre de `name` apuntando a columnas distintas.
 *
 * El `name` nuevo queda NOT NULL, y por eso hay un relleno antes: un equipo sin
 * código no puede quedarse sin nombre, así que hereda el tipo (con su id
 * pegado si eso choca con otro) y el laboratorio lo corrige desde la pantalla.
 * Es lo contrario de lo que hacía el sistema viejo, que llenaba el hueco con un
 * texto vacío y lo dejaba pasar.
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        // ── 0. Las vistas anchas por prueba ──────────────────────────────
        //
        // `lab:build-views` genera una vista por prueba, y las que tienen una
        // columna de instrumento leen `i.code`. Postgres se niega a cambiar el
        // tipo de una columna de la que depende una vista, así que se bajan
        // primero y se vuelven a generar al final. Es seguro por la misma razón
        // por la que son vistas y no tablas: no guardan nada.
        $this->bajarVistas($driver);

        // ── 1. Los índices que nombran las dos columnas ──────────────────
        DB::statement('DROP INDEX IF EXISTS instruments_code_unique_active');
        DB::statement('DROP INDEX IF EXISTS instruments_name_index');

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_instruments_name_pattern');
        }

        // ── 2. El relleno, ANTES de que `code` sea obligatorio ───────────
        //
        // Un equipo sin código hereda el tipo como nombre. Si dos equipos del
        // mismo workspace comparten ese tipo (tres buretas sin código), se le
        // pega el id: el índice único que se crea al final los rechazaría, y
        // fallar la migración por datos viejos deja la instalación a medias.
        DB::statement("UPDATE instruments SET code = name WHERE code IS NULL OR TRIM(code) = ''");

        $duplicados = DB::table('instruments')
            ->selectRaw('id, tenant_id, code')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($fila) => $fila->tenant_id . '|' . mb_strtolower((string) $fila->code))
            ->filter(fn ($grupo) => $grupo->count() > 1);

        foreach ($duplicados as $grupo) {
            // El primero conserva el código; los demás lo llevan sufijado.
            foreach ($grupo->slice(1) as $fila) {
                DB::table('instruments')
                    ->where('id', $fila->id)
                    ->update(['code' => $fila->code . ' #' . $fila->id]);
            }
        }

        // ── 3. Los dos renombres, en orden ───────────────────────────────
        Schema::table('instruments', function (Blueprint $table) {
            $table->renameColumn('name', 'description');
        });

        Schema::table('instruments', function (Blueprint $table) {
            $table->renameColumn('code', 'name');
        });

        // ── 4. Obligatoriedad al revés que antes ─────────────────────────
        //
        // El nombre pasa a ser obligatorio (era `code`, opcional) y la
        // descripción opcional (era `name`, obligatoria). Va ANTES de crear los
        // índices: en SQLite `change()` recrea la tabla y se los llevaría.
        Schema::table('instruments', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            // Texto libre y puede ser largo ("Bureta digital de 10 mL, clase
            // A"): deja de ser varchar(255).
            $table->text('description')->nullable()->change();
        });

        // ── 5. Los índices, ahora sobre el nombre ────────────────────────
        DB::statement('CREATE INDEX instruments_name_index ON instruments (name)');

        DB::statement(
            'CREATE UNIQUE INDEX instruments_name_unique_active ' .
            'ON instruments (tenant_id, LOWER(name)) ' .
            'WHERE deleted_at IS NULL'
        );

        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX idx_instruments_name_pattern ON instruments (name varchar_pattern_ops)');
        }

        $this->rehacerVistas($driver);
    }

    public function down(): void
    {
        $this->bajarVistas(DB::getDriverName());

        $driver = DB::getDriverName();

        DB::statement('DROP INDEX IF EXISTS instruments_name_unique_active');
        DB::statement('DROP INDEX IF EXISTS instruments_name_index');

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_instruments_name_pattern');
        }

        Schema::table('instruments', function (Blueprint $table) {
            $table->renameColumn('name', 'code');
        });

        Schema::table('instruments', function (Blueprint $table) {
            $table->renameColumn('description', 'name');
        });

        // El nombre viejo era obligatorio; si quedó alguno en nulo por el camino
        // de vuelta se rellena con el código, que sí lo era.
        DB::statement('UPDATE instruments SET name = code WHERE name IS NULL');

        Schema::table('instruments', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('code')->nullable()->change();
        });

        DB::statement('CREATE INDEX instruments_name_index ON instruments (name)');
        DB::statement(
            'CREATE UNIQUE INDEX instruments_code_unique_active ' .
            'ON instruments (tenant_id, LOWER(code)) ' .
            'WHERE deleted_at IS NULL AND code IS NOT NULL'
        );

        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX idx_instruments_name_pattern ON instruments (name varchar_pattern_ops)');
        }

        $this->rehacerVistas($driver);
    }

    /**
     * Baja las vistas anchas por prueba (`v_*`).
     *
     * Solo Postgres: el SQL que las genera usa `to_char`, así que en SQLite —la
     * base de los tests— no existen.
     */
    private function bajarVistas(string $driver): void
    {
        if ($driver !== 'pgsql') {
            return;
        }

        $vistas = DB::select(
            "SELECT table_name FROM information_schema.views " .
            "WHERE table_schema = 'public' AND table_name LIKE 'v\\_%'"
        );

        foreach ($vistas as $vista) {
            DB::statement('DROP VIEW IF EXISTS ' . '"' . $vista->table_name . '" CASCADE');
        }
    }

    /**
     * Vuelve a generarlas con el nombre de columna nuevo.
     *
     * Se hace con el comando y no con SQL escrito acá: el SQL de cada vista se
     * deriva de la plantilla de su prueba, y copiarlo a la migración lo dejaría
     * congelado en la forma que tenía hoy. Si todavía no hay pruebas sembradas
     * —instalación nueva— el comando no tiene nada que generar y no pasa nada:
     * las vistas tampoco existían.
     */
    private function rehacerVistas(string $driver): void
    {
        if ($driver !== 'pgsql') {
            return;
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('lab:build-views');
        } catch (\Throwable $e) {
            // Una vista no guarda datos: si acá falla, `php artisan
            // lab:build-views` la rehace sin consecuencias. No se aborta la
            // migración por esto — los datos ya están renombrados.
        }
    }
};
