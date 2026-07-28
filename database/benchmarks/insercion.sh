#!/usr/bin/env bash
# Costo de dar de alta una hoja de trabajo en cada forma, a la escala cargada.
#
# Se mide con los indices ya presentes, que es donde esta el costo real: el
# monton cuesta poco, lo que cuesta es mantener los btree. Por eso este numero
# solo tiene sentido a escala grande.
#
# Uso: ./insercion.sh <escala>

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"
cd "$BENCH_DIR"

SCALE=${1:?}
OUT="$RESULTS_DIR/$SCALE"; mkdir -p "$OUT"
VECES=${VECES:-500}
pg_up

q -q -f sql/40_insercion.sql >/dev/null

{
  echo "escenario,forma,ms_por_hoja_de_trabajo,filas_escritas"
  # Primero una tanda de calentamiento que se descarta.
  q -q -c "select bench.insert_vertical(50); select bench.limpiar_insercion();" >/dev/null
  q -q -c "select bench.insert_ancho(50);    select bench.limpiar_insercion();" >/dev/null

  v="$(qv "select round(bench.insert_vertical($VECES)::numeric, 4);")"
  q -q -c "select bench.limpiar_insercion();" >/dev/null
  a="$(qv "select round(bench.insert_ancho($VECES)::numeric, 4);")"
  q -q -c "select bench.limpiar_insercion();" >/dev/null
  echo "las 4 pruebas,vertical,$v,35"
  echo "las 4 pruebas,ancho,$a,5"

  v="$(qv "select round(bench.insert_vertical($VECES, true)::numeric, 4);")"
  q -q -c "select bench.limpiar_insercion();" >/dev/null
  a="$(qv "select round(bench.insert_ancho($VECES, true)::numeric, 4);")"
  q -q -c "select bench.limpiar_insercion();" >/dev/null
  echo "solo cromatografia,vertical,$v,12"
  echo "solo cromatografia,ancho,$a,2"
} > "$OUT/insercion.csv"
cat "$OUT/insercion.csv"
