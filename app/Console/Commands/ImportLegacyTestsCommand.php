<?php

namespace App\Console\Commands;

use App\Models\Analyte;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\TestFieldOption;
use App\Models\TestGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa las plantillas de ensayo del sistema Rails viejo.
 *
 * Lee un volcado SQL de `lab_category_detail_types`, `lab_category_details`,
 * `lab_category_sub_details` y `lab_category_sub_detail_options`, y crea las
 * pruebas, sus campos y sus opciones en el sistema nuevo.
 *
 *   php artisan import:legacy-tests {archivo.sql} --dry-run
 *
 * IDEMPOTENTE: se ancla al `legacy_id` de cada fila, así que correrlo diez
 * veces deja el mismo resultado. Si el laboratorio agrega una prueba en el
 * sistema viejo, se vuelve a correr y se sincroniza.
 *
 * POR QUÉ UN COMANDO Y NO UN SEEDER A MANO: son 29 pruebas, 208 columnas y 93
 * opciones. Transcribirlas garantiza erratas —el propio seed del sistema viejo
 * tenía "Mteales" por "Metales"— y deja el trabajo sin repetir cuando el
 * volcado se actualice.
 *
 * LO QUE NO PUEDE DECIDIR SOLO: cuál columna es el RESULTADO. Lo detecta por
 * nombre en 24 de las 29; las otras 5 se marcan y se listan al final para que
 * el laboratorio las confirme. No inventa.
 */
class ImportLegacyTestsCommand extends Command
{
    protected $signature = 'import:legacy-tests
                            {file : Ruta al volcado SQL del sistema viejo}
                            {--dry-run : Muestra qué haría, sin escribir nada}';

    protected $description = 'Importa las plantillas de ensayo (pruebas, campos y opciones) desde el volcado del sistema Rails viejo';

    /** Tipo de campo del sistema viejo → tipo del nuevo. */
    private const TYPE_MAP = [
        '1' => 'text',
        '2' => 'number',
        '3' => 'select',
    ];

    /** Columnas cuyo nombre delata que son el resultado de la prueba. */
    private const RESULT_HINTS = '/resultado|grado de polimerizaci|total de gases/i';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_readable($file)) {
            $this->error("No se puede leer: {$file}");
            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');

        $sql = file_get_contents($file);
        $groups  = $this->parse($sql, 'lab_category_detail_types');
        $tests   = $this->parse($sql, 'lab_category_details');
        $fields  = $this->parse($sql, 'lab_category_sub_details');
        $options = $this->parse($sql, 'lab_category_sub_detail_options');

        $this->line('');
        $this->info('Encontrado en el volcado:');
        $this->line(sprintf('  grupos %d · pruebas %d · campos %d · opciones %d',
            count($groups), count($tests), count($fields), count($options)));

        if (! $tests) {
            $this->error('No hay pruebas en el archivo. ¿Es el volcado correcto?');
            return self::FAILURE;
        }

        $ambiguous = [];
        $stats = ['grupos' => 0, 'pruebas' => 0, 'campos' => 0, 'opciones' => 0, 'resultados' => 0];

        $run = function () use ($groups, $tests, $fields, $options, &$ambiguous, &$stats, $dry) {
            // ── Grupos ───────────────────────────────────────────────────
            $groupByLegacy = [];
            foreach ($groups as $g) {
                if (($g[2] ?? '1') === '1') continue;     // deleted
                $name = $this->str($g[1]);
                $row = ['code' => Str::slug($name, '_'), 'name' => $name, 'sort_order' => (int) $g[0]];
                $groupByLegacy[(int) $g[0]] = $dry ? null
                    : TestGroup::updateOrCreate(['code' => $row['code']], $row + ['slug' => Str::random(22)])->id;
                $stats['grupos']++;
            }

            // ── Pruebas ──────────────────────────────────────────────────
            $testByLegacy = [];
            foreach ($tests as $t) {
                [$id, $groupId, $name, $container, $pos, $isGrouped, $formula, $desc, $chartUnit, $hasReuse, $deleted] =
                    array_pad(array_slice($t, 0, 11), 11, null);
                if ($deleted === '1') continue;

                $name = $this->str($name);
                $row = [
                    'code'          => Str::slug($name, '_'),
                    'name'          => $name,
                    'test_group_id' => $groupByLegacy[(int) $groupId] ?? null,
                    'description'   => $this->str($desc) ?: null,
                    'container'     => $this->str($container) ?: null,
                    'chart_unit'    => $this->str($chartUnit) ?: null,
                    'is_grouped'    => $isGrouped === '1',
                    'has_control'   => $hasReuse === '1',
                    'sort_order'    => (int) $pos,
                ];
                $testByLegacy[(int) $id] = $dry ? (int) $id
                    : TestDefinition::updateOrCreate(['legacy_id' => (int) $id], $row + ['slug' => Str::random(22)])->id;
                $stats['pruebas']++;
            }

            // ── Campos, agrupados por prueba para resolver el resultado ──
            $byTest = [];
            foreach ($fields as $f) {
                if (($f[14] ?? '0') === '1') continue;    // deleted
                if ($f[1] === 'NULL') continue;
                $byTest[(int) $f[1]][] = $f;
            }

            $fieldByLegacy = [];
            foreach ($byTest as $legacyTestId => $cols) {
                if (! isset($testByLegacy[$legacyTestId])) continue;
                usort($cols, fn ($a, $b) => (int) $a[4] <=> (int) $b[4]);
                $usedCodes = [];   // dos columnas pueden dar el mismo código

                // ¿Cuáles son resultados? Por nombre. Si hay exactamente uno,
                // se marca solo. Si hay cero o varios, se anota para revisión
                // humana y NO se adivina.
                $hits = array_values(array_filter($cols, fn ($c) => preg_match(self::RESULT_HINTS, $this->str($c[3]))));
                $testName = $this->str($tests[array_search((string) $legacyTestId, array_column($tests, 0))][2] ?? '?');
                if (count($hits) !== 1) {
                    $ambiguous[] = [
                        'prueba'     => $testName,
                        'candidatos' => array_map(fn ($c) => $this->str($c[3]), $hits),
                        'ultima'     => $this->str(end($cols)[3]),
                    ];
                }

                foreach ($cols as $c) {
                    [$fid, , $typeId, $label, $pos, $req, $blocked, , $reuse, $default] =
                        array_pad(array_slice($c, 0, 10), 10, null);
                    $label = $this->str($label);
                    $isResult = count($hits) === 1 && $hits[0][0] === $fid;
                    if ($isResult) $stats['resultados']++;

                    $row = [
                        'test_definition_id' => $dry ? 0 : $testByLegacy[$legacyTestId],
                        'code'           => $this->uniqueCode($label, $usedCodes),
                        'label'          => $label,
                        'type'           => self::TYPE_MAP[$typeId] ?? 'text',
                        'sort_order'     => (int) $pos,
                        'is_required'    => $req === '1',
                        'is_locked'      => $blocked === '1',
                        'is_reusable'    => $reuse === '1',
                        'default_value'  => $this->str($default) ?: null,
                        'report_visible' => $isResult,
                    ];
                    $fieldByLegacy[(int) $fid] = $dry ? null
                        : TestField::updateOrCreate(['legacy_id' => (int) $fid], $row + ['slug' => Str::random(22)])->id;
                    $stats['campos']++;
                }
            }

            // ── Opciones ─────────────────────────────────────────────────
            foreach ($options as $o) {
                [$oid, $fieldId, $value] = array_pad(array_slice($o, 0, 3), 3, null);
                if (! isset($fieldByLegacy[(int) $fieldId]) && ! $dry) continue;
                $stats['opciones']++;
                if ($dry) continue;
                TestFieldOption::updateOrCreate(['legacy_id' => (int) $oid], [
                    'test_field_id' => $fieldByLegacy[(int) $fieldId],
                    'value'         => $this->str($value),
                ]);
            }
        };

        if ($dry) {
            $run();
        } else {
            DB::transaction($run);
        }

        $this->line('');
        $this->info($dry ? 'SIMULACIÓN (no se escribió nada):' : 'Importado:');
        foreach ($stats as $k => $v) {
            $this->line(sprintf('  %-12s %d', $k, $v));
        }

        if ($ambiguous) {
            $this->line('');
            $this->warn('Pruebas donde NO se puede saber sola cuál columna es el resultado.');
            $this->line('Se dejan SIN marcar a propósito: adivinar acá manda el dato equivocado al informe.');
            $this->line('');
            $this->table(
                ['Prueba', 'Candidatos', 'Última columna'],
                array_map(fn ($a) => [
                    $a['prueba'],
                    $a['candidatos'] ? implode(' · ', $a['candidatos']) : '(ninguno)',
                    $a['ultima'],
                ], $ambiguous)
            );
            $this->line('Confirmarlas desde el editor de pruebas, o con analyte:map.');
        }

        return self::SUCCESS;
    }

    /**
     * Extrae las filas de un INSERT INTO `tabla` VALUES (...),(...);
     * Devuelve cada fila como array de campos crudos (con las comillas puestas).
     */
    private function parse(string $sql, string $table): array
    {
        $rows = [];
        $re = '/INSERT INTO `' . preg_quote($table, '/') . '`[^\n]*VALUES\s*\n?(.*?);\s*\n/s';
        if (! preg_match_all($re, $sql, $m)) return $rows;

        foreach ($m[1] as $chunk) {
            foreach ($this->splitTuples($chunk) as $tuple) {
                $rows[] = $this->splitFields($tuple);
            }
        }
        return $rows;
    }

    /** Separa `(...),(...)` respetando comillas y escapes. */
    private function splitTuples(string $s): array
    {
        $out = []; $cur = ''; $depth = 0; $q = false; $esc = false;
        foreach (str_split($s) as $ch) {
            if ($esc) { $cur .= $ch; $esc = false; continue; }
            if ($ch === '\\') { $cur .= $ch; $esc = true; continue; }
            if ($ch === "'") { $q = ! $q; $cur .= $ch; continue; }
            if (! $q && $ch === '(') { if ($depth++ === 0) { $cur = ''; continue; } }
            if (! $q && $ch === ')') { if (--$depth === 0) { $out[] = $cur; continue; } }
            if ($depth > 0) $cur .= $ch;
        }
        return $out;
    }

    /** Separa los campos de una tupla respetando comillas. */
    private function splitFields(string $s): array
    {
        $out = []; $cur = ''; $q = false; $esc = false;
        foreach (str_split($s) as $ch) {
            if ($esc) { $cur .= $ch; $esc = false; continue; }
            if ($ch === '\\') { $cur .= $ch; $esc = true; continue; }
            if ($ch === "'") { $q = ! $q; $cur .= $ch; continue; }
            if ($ch === ',' && ! $q) { $out[] = trim($cur); $cur = ''; continue; }
            $cur .= $ch;
        }
        $out[] = trim($cur);
        return $out;
    }

    /** Limpia un valor SQL: quita comillas, resuelve escapes y saltos de línea. */
    private function str(?string $v): string
    {
        if ($v === null || $v === 'NULL') return '';
        $v = trim($v, "'");
        $v = str_replace(['\\r\\n', '\\n', '\\r', '\\t', "\\'", '\\"', '\\\\'], [' ', ' ', ' ', ' ', "'", '"', '\\'], $v);
        return trim(preg_replace('/\s+/', ' ', $v));
    }

    /**
     * Igual que code(), pero garantiza unicidad dentro de la prueba. Hay
     * columnas distintas cuya etiqueta produce el mismo código —el caso real es
     * Azufre 62535, con "Resultado" y "Resultado Lámina de Cobre" recortados al
     * mismo largo—. Se desempata con un sufijo en vez de fallar.
     *
     * @param array<string,bool> $used
     */
    private function uniqueCode(string $label, array &$used): string
    {
        $base = $this->code($label);
        $code = $base;
        $n = 2;
        while (isset($used[$code])) {
            $code = $base . '_' . $n++;
        }
        $used[$code] = true;
        return $code;
    }

    /** Etiqueta → código estable para usar en las fórmulas. */
    private function code(string $label): string
    {
        $c = Str::slug($label, '_');
        $c = preg_replace('/_+/', '_', trim($c, '_'));
        return $c !== '' ? Str::limit($c, 58, '') : 'campo';
    }
}
