#!/usr/bin/env bash
# Carga una escala completa (cabeceras + forma vertical + forma ancha).
#
# Se carga por tandas de equipos en vez de una sola sentencia gigante por dos
# razones: una transaccion de 80 millones de filas desborda el WAL y dispara
# puntos de control en cadena, y si el servidor se cae a mitad se pierde todo.
# Por tandas, una caida cuesta una tanda.
#
# Uso: ./load.sh <n_equipos> <equipos_por_inquilino>

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

N_EQUIP=${1:?falta n_equipos}
PER_TENANT=${2:-500}
CHUNK=${CHUNK:-2000}

pg_up

echo "[load] escala: $N_EQUIP equipos, $PER_TENANT por inquilino, tandas de $CHUNK"
start=$(date +%s)

lo=1
while [ "$lo" -le "$N_EQUIP" ]; do
  hi=$(( lo + CHUNK - 1 )); [ "$hi" -gt "$N_EQUIP" ] && hi=$N_EQUIP
  q -q -v lo="$lo" -v hi="$hi" -v equip_per_tenant="$PER_TENANT" -f sql/10_load_samples.sql >/dev/null
  q -q -v lo="$lo" -v hi="$hi" -f sql/11_load_vertical.sql >/dev/null
  q -q -c "select bench.load_wide_all($lo, $hi);" >/dev/null
  printf '\r[load] equipos %d/%d  (%ds)' "$hi" "$N_EQUIP" "$(( $(date +%s) - start ))"
  lo=$(( hi + 1 ))
done
echo

echo "[load] indices nivel 1"
q -q -f sql/20_indexes.sql >/dev/null

echo "[load] vacuum analyze"
q -q -c "vacuum analyze bench.results_v, bench.samples, bench.w_analisis_cromatografico, bench.w_grado_de_polimerizacion, bench.w_numero_acido, bench.w_contenido_de_agua;" >/dev/null

echo "[load] listo en $(( $(date +%s) - start ))s"
qv "select 'samples' t, count(*) from bench.samples
    union all select 'results_v', count(*) from bench.results_v
    union all select 'w_cromatografico', count(*) from bench.w_analisis_cromatografico
    union all select 'w_polimerizacion', count(*) from bench.w_grado_de_polimerizacion
    union all select 'w_numero_acido', count(*) from bench.w_numero_acido
    union all select 'w_contenido_agua', count(*) from bench.w_contenido_de_agua;"
