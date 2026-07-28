#!/usr/bin/env bash
# Variante global del tablero de flota (todos los inquilinos a la vez).
# Uso: ./global.sh <escala>
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/measure.sh"
cd "$BENCH_DIR"
SCALE=${1:?}; OUT="$RESULTS_DIR/$SCALE"; mkdir -p "$OUT"
pg_up
V="-v an=${AN:-10} -v umbral=${UMBRAL:-100}"
{
  echo "consulta,mediana_ms,min_ms,max_ms,sentencias"
  REPS=3 timeq "q3_global_vertical" queries/q3g_vertical.sql $V
  REPS=3 timeq "q3_global_ancho"    queries/q3g_ancho.sql    $V
} > "$OUT/tiempos_global.csv"
cat "$OUT/tiempos_global.csv"
{ explainq q3g_vertical queries/q3g_vertical.sql $V
  explainq q3g_ancho    queries/q3g_ancho.sql    $V; } > "$OUT/planes_global.txt" 2>&1
grep -E 'Scan|Execution Time|Buffers' "$OUT/planes_global.txt" | head -20
