#!/usr/bin/env bash
# Mide el informe consolidado (consulta 4) a medida que crece la cantidad de
# pruebas dadas de alta: 4, 10, 20 y 30 tablas.
#
# Uso: ./prueba30.sh <escala> <equipo> <lo_equipos> <hi_equipos>

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/measure.sh"
cd "$BENCH_DIR"

SCALE=${1:?}; EQ=${2:?}; LO=${3:?}; HI=${4:?}
OUT="$RESULTS_DIR/$SCALE"; mkdir -p "$OUT"
pg_up

q -q -f sql/50_prueba30.sql >/dev/null
echo "[p30] creando 26 tablas de prueba extra para equipos $LO-$HI"
q -q -c "select bench.crear_pruebas_extra(26, $LO, $HI);" >/dev/null

# El texto base son las 4 ramas reales sin su ORDER BY final.
BASE="$(sed 's/^order by 3, 1, 2;$//' queries/q4_ancho_union.sql)"

{
  echo "pruebas_dadas_de_alta,ramas_union,mediana_ms,min_ms,max_ms,longitud_sql_caracteres"
  for n in 0 6 16 26; do
    extra="$(qv "select bench.union_sql($n);")"
    f="queries/_gen_union_$(( n + 4 )).sql"
    { printf '%s\n' "$BASE"; printf '%s\n' "$extra"; echo "order by 3, 1, 2;"; } > "$f"
    len=$(wc -c < "$f")
    line="$(timeq "union_$(( n + 4 ))" "$f" -v eq="$EQ")"
    echo "$(( n + 4 )),$(( n + 4 )),$(echo "$line" | cut -d, -f2,3,4),$len"
  done
} > "$OUT/prueba30.csv"
cat "$OUT/prueba30.csv"

# El costo de PLANIFICACION es la mitad interesante: crece con la cantidad de
# ramas aunque el equipo no tenga datos en ellas.
{
  for n in 4 10 20 30; do
    echo "===== union de $n ramas ====="
    explainq "union_$n" "queries/_gen_union_$n.sql" -v eq="$EQ" | grep -E 'Planning Time|Execution Time'
  done
  echo "===== consulta 4 vertical (no cambia con la cantidad de pruebas) ====="
  explainq q4_vertical queries/q4_vertical.sql -v eq="$EQ" | grep -E 'Planning Time|Execution Time'
} > "$OUT/prueba30_planes.txt" 2>&1
cat "$OUT/prueba30_planes.txt"

# La consulta vertical equivalente no cambia de texto ni de plan al agregar
# pruebas: se mide como referencia.
timeq "q4_vertical_referencia" queries/q4_vertical.sql -v eq="$EQ" | tee -a "$OUT/prueba30.csv"
