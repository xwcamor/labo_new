<?php

namespace App\Console\Commands;

use App\Models\TestDefinition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Una tabla ancha por prueba, generada desde su propia definición.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ PREGUNTA CONTESTA ESTE COMANDO                                       │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El laboratorio pregunta —con razón, y más de una vez— por qué cada prueba no
 * tiene su propia tabla con sus propias columnas. Su exportación a Excel es
 * exactamente eso:
 *
 *   Fecha · Tipo · Nº de Muestra · Norma · H2 · O2 · N2 · CH4 · … · Total
 *
 * Este comando la produce. `SELECT * FROM v_analisis_cromatografico` devuelve
 * esas columnas, con esos nombres, una fila por muestra. Sirve para el export,
 * para Excel, para Power BI y para cualquier herramienta que hable SQL.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ UNA VISTA Y NO UNA TABLA                                         │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La diferencia NO es el rendimiento —eso ya se midió sobre 84 millones de
 * filas y está en docs/migracion/08-BENCHMARK-VERTICAL-VS-ANCHO.md—. Es qué
 * pasa el día que el laboratorio agrega una prueba o una columna.
 *
 *   Con TABLA física:  hay que ejecutar CREATE TABLE / ALTER TABLE. O lo hace un
 *                      programador cada vez, o la aplicación necesita permiso
 *                      para modificar su propio esquema — y una aplicación que
 *                      puede alterar sus tablas es una aplicación donde un error
 *                      borra una. Además el cambio deja de estar versionado: no
 *                      se puede revisar ni deshacer.
 *
 *   Con VISTA:         se borra y se vuelve a crear. Una vista no guarda datos,
 *                      así que rehacerla no puede perder nada. Correr este
 *                      comando después de cambiar una plantilla es todo.
 *
 * O sea: la tabla ancha SÍ, para leer y exportar. Guardar sigue siendo por
 * columna tipada, que es lo que permite que el laboratorio configure sus propias
 * pruebas sin un programador.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ SI ALGÚN DÍA UNA VISTA SE QUEDA CORTA                                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Con `--materializar` la misma generación produce vistas MATERIALIZADAS, que
 * guardan el resultado en disco y se refrescan cuando se valida una hoja. Es el
 * mismo SQL: cambia dónde vive el resultado, no cómo se consulta.
 */
class BuildTestViewsCommand extends Command
{
    protected $signature = 'lab:build-views
        {--test= : Solo la prueba con este código}
        {--materializar : Vistas materializadas en vez de vistas comunes}
        {--mostrar : Imprime el SQL en vez de ejecutarlo}';

    protected $description = 'Genera una vista ancha por prueba, con una columna por campo';

    public function handle(): int
    {
        $pruebas = TestDefinition::with(['fields' => fn ($q) => $q->orderBy('sort_order')])
            ->when($this->option('test'), fn ($q, $code) => $q->where('code', $code))
            ->get();

        if ($pruebas->isEmpty()) {
            $this->error('No hay pruebas. ¿Corrió el seed?');

            return self::FAILURE;
        }

        $hechas = 0;

        foreach ($pruebas as $prueba) {
            $sql = $this->sqlDe($prueba);

            if ($sql === null) {
                $this->warn("  · {$prueba->code}: sin columnas, se omite.");
                continue;
            }

            if ($this->option('mostrar')) {
                $this->line($sql);
                $this->newLine();
                continue;
            }

            // Generar el SQL se puede en cualquier motor —y así se verifica en
            // las pruebas—; ejecutarlo, solo en Postgres.
            if (DB::getDriverName() !== 'pgsql') {
                continue;
            }

            DB::statement($sql);
            $hechas++;
        }

        if ($this->option('mostrar')) {
            return self::SUCCESS;
        }

        if (DB::getDriverName() !== 'pgsql') {
            $this->warn('Las vistas solo se crean en Postgres. En SQLite se generó el SQL y no se ejecutó.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("{$hechas} vistas generadas.");
        $this->line('');
        $this->line('  Cada prueba es ahora una tabla ancha para consultar:');
        $this->line('');

        foreach ($pruebas->take(4) as $p) {
            $this->line('    <fg=green>SELECT * FROM ' . $this->nombreVista($p) . ';</>');
        }

        $this->line('');
        $this->line('  Se guardan por columna tipada y se LEEN anchas. Agregar una');
        $this->line('  prueba o una columna no toca el esquema: se vuelve a correr');
        $this->line('  <fg=green>php artisan lab:build-views</> y listo.');

        return self::SUCCESS;
    }

    /**
     * El SQL de la vista de una prueba.
     *
     * Una columna por campo de la plantilla, con el MISMO nombre que el
     * laboratorio le puso, más la fecha, el tipo de fila, el correlativo de la
     * muestra, el equipo, el cliente y quién la cargó — que es lo que trae su
     * exportación a Excel.
     */
    private function sqlDe(TestDefinition $prueba): ?string
    {
        $campos = $prueba->fields;

        if ($campos->isEmpty()) {
            return null;
        }

        $columnas = [];
        $usados = [];

        foreach ($campos as $campo) {
            $nombre = $this->nombreColumna($campo->code, $usados);

            // De dónde sale el valor según el tipo. Es la contracara de
            // ValueCoercer: allá se decide en qué columna cae, acá de cuál se
            // lee. El número primero, después el texto de la opción elegida,
            // después el texto suelto — el mismo orden que `resolved`.
            $expresion = match ($campo->type) {
                'number', 'computed' => 'v.value_num',
                'select'             => 'o.value',
                // El NOMBRE del instrumento es su código de calibración
                // (PP-LA-01C-100): es lo que hace trazable el resultado y lo
                // que la exportación del laboratorio trae en esa columna.
                'instrument'         => 'i.name',
                default              => 'v.value_text',
            };

            // El signo de censura viaja con el número: ">75" no es 75, y la
            // exportación tiene que decirlo igual que la pantalla.
            if (in_array($campo->type, ['number', 'computed'], true)) {
                $expresion = "CASE WHEN v.qualifier = 'gt' THEN '>' WHEN v.qualifier = 'lt' THEN '<' ELSE '' END "
                    . "|| trim(to_char(v.value_num, 'FM9999999999990.99999999'))";
            }

            $columnas[] = sprintf(
                "MAX(CASE WHEN f.id = %d THEN %s END) AS %s",
                $campo->id,
                $expresion,
                $this->citar($nombre),
            );
        }

        $vista = $this->nombreVista($prueba);

        // Una vista materializada no admite CREATE OR REPLACE: se borra y se
        // vuelve a crear. Es seguro justamente porque no guarda nada propio.
        $preludio = $this->option('materializar')
            ? "DROP MATERIALIZED VIEW IF EXISTS {$vista};\nCREATE MATERIALIZED VIEW {$vista} AS\n"
            : "CREATE OR REPLACE VIEW {$vista} AS\n";

        return $preludio . sprintf(
            <<<'SQL'
            SELECT
                r.id                        AS fila_id,
                w.tenant_id                 AS workspace_id,
                w.run_date                  AS fecha,
                r.kind                      AS tipo,
                COALESCE(s.code, r.sample_code) AS nro_muestra,
                e.name                      AS equipo,
                e.tag                       AS equipo_etiqueta,
                c.name                      AS cliente,
                w.status                    AS estado_hoja,
                a.name                      AS analista,
            %s
            FROM worksheet_rows r
                JOIN worksheets w        ON w.id = r.worksheet_id AND w.deleted_at IS NULL
                LEFT JOIN worksheet_values v ON v.worksheet_row_id = r.id
                LEFT JOIN test_fields f  ON f.id = v.test_field_id
                LEFT JOIN test_field_options o ON o.id = v.option_id
                LEFT JOIN instruments i  ON i.id = v.instrument_id
                LEFT JOIN samples s      ON s.id = r.sample_id
                LEFT JOIN equipment e    ON e.id = COALESCE(s.equipment_id, r.equipment_id)
                LEFT JOIN customers c    ON c.id = e.customer_id
                LEFT JOIN users a        ON a.id = w.analyst_id
            WHERE w.test_definition_id = %d
              AND r.deleted_at IS NULL
            GROUP BY r.id, w.tenant_id, w.run_date, r.kind, s.code, r.sample_code,
                     e.name, e.tag, c.name, w.status, a.name
            SQL,
            '                ' . implode(",\n                ", $columnas),
            $prueba->id,
        );
    }

    /** `v_analisis_cromatografico`. */
    private function nombreVista(TestDefinition $prueba): string
    {
        return 'v_' . Str::limit(Str::slug($prueba->code, '_'), 60, '');
    }

    /**
     * El nombre de la columna en la vista.
     *
     * Se usa el código que el laboratorio le puso a la columna, no un `col12`.
     * Si dos columnas dieran el mismo nombre se desempata con un sufijo, porque
     * una vista con dos columnas iguales no se crea.
     *
     * @param array<string,bool> $usados
     */
    private function nombreColumna(string $code, array &$usados): string
    {
        $base = Str::limit(preg_replace('/[^a-z0-9_]/', '_', mb_strtolower($code)), 58, '');
        $nombre = $base;
        $n = 2;

        while (isset($usados[$nombre])) {
            $nombre = $base . '_' . $n++;
        }

        $usados[$nombre] = true;

        return $nombre;
    }

    private function citar(string $identificador): string
    {
        return '"' . str_replace('"', '""', $identificador) . '"';
    }
}
