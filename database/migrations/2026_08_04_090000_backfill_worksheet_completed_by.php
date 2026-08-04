<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quién completó cada hoja ya terminada.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ LA COLUMNA ESTABA VACÍA EN TODAS                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `validated_by` solo lo llenaba `WorksheetService::validate()`, el paso manual
 * de supervisión que este flujo ya no usa: la hoja publica sola en cuanto no le
 * falta ningún dato. Así que la columna quedó nula en TODAS las hojas y la
 * pantalla mostraba un guion donde tenía que haber un nombre.
 *
 * El código nuevo ya la llena hacia adelante. Esta migración cierra lo de atrás
 * con el dato REAL, no con una suposición: `worksheet_values.entered_by` guarda
 * quién escribió cada valor, así que el ÚLTIMO valor cargado de la hoja dice
 * quién la dejó completa — que es exactamente el criterio que aplica el código
 * nuevo.
 *
 * Solo toca las que están en nulo: una hoja que sí pasó por la validación
 * manual conserva a su validador.
 */
return new class extends Migration
{
    public function up(): void
    {
        // La subconsulta ordena por el valor más nuevo de la hoja (por fecha y,
        // a igual fecha, por id: dos valores guardados en el mismo segundo son
        // frecuentes cuando la fila entera se guarda de una vez).
        $ultimo = DB::table('worksheet_values as wv')
            ->join('worksheet_rows as wr', 'wr.id', '=', 'wv.worksheet_row_id')
            ->select('wv.entered_by')
            ->whereColumn('wr.worksheet_id', 'worksheets.id')
            ->whereNotNull('wv.entered_by')
            ->orderByDesc('wv.created_at')
            ->orderByDesc('wv.id')
            ->limit(1);

        DB::table('worksheets')
            ->whereNull('validated_by')
            ->whereNotNull('validated_at')
            ->update(['validated_by' => DB::raw('(' . $this->sql($ultimo) . ')')]);
    }

    /**
     * Revertir no borra el dato: la hoja SÍ la completó esa persona, y
     * vaciarlo devolvería la columna al guion que esta migración vino a
     * corregir. La migración es de relleno, no de estructura.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }

    /** La subconsulta con sus ataduras ya interpoladas. */
    private function sql($query): string
    {
        $sql = $query->toSql();

        foreach ($query->getBindings() as $binding) {
            $sql = preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sql, 1);
        }

        return $sql;
    }
};
