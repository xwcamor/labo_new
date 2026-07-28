#!/usr/bin/env bash
# Reemplaza la forma vertical por su version particionada por ano y vuelve a
# medir las consultas 1, 2 y 3.
#
# Borra bench.results_v antes de generar la particionada. No es prolijidad: a la
# escala mayor las dos no entran a la vez en el disco de este contenedor. Como el
# generador es determinista, la particionada queda con el dato identico.
#
# Uso: ./particion.sh <escala> <n_equipos> <equipo> <inquilino>

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/measure.sh"
cd "$BENCH_DIR"

SCALE=${1:?}; N_EQUIP=${2:?}; EQ=${3:?}; TN=${4:-1}
AN=${AN:-10}; UMBRAL=${UMBRAL:-100}
CHUNK=${CHUNK:-5000}
OUT="$RESULTS_DIR/$SCALE"; mkdir -p "$OUT"
pg_up

echo "[part] tamano de la forma vertical NO particionada (para el informe)"
q -q -o "$OUT/tamanos_pre_particion.txt" -c "
select relname, pg_size_pretty(pg_table_size(relid)) tabla,
       pg_size_pretty(pg_indexes_size(relid)) indices,
       pg_size_pretty(pg_total_relation_size(relid)) total
from pg_stat_user_tables where schemaname='bench' order by pg_total_relation_size(relid) desc;"

echo "[part] borrando bench.results_v para liberar disco"
q -q -c "drop table bench.results_v;" >/dev/null

echo "[part] creando particiones"
q -q -f sql/30_particion.sql >/dev/null

start=$(date +%s); lo=1
while [ "$lo" -le "$N_EQUIP" ]; do
  hi=$(( lo + CHUNK - 1 )); [ "$hi" -gt "$N_EQUIP" ] && hi=$N_EQUIP
  q -q -c "select bench.load_vp($lo, $hi);" >/dev/null
  printf '\r[part] equipos %d/%d (%ds)' "$hi" "$N_EQUIP" "$(( $(date +%s) - start ))"
  lo=$(( hi + 1 ))
done
echo

echo "[part] indices"
# Se crean los tres del nivel util. Se OMITE a proposito el equivalente de
# ix_v_an_date (analyte_id, measured_at): ya quedo demostrado a la escala media
# que ese indice es el que hunde el tablero de flota, y repetir la demostracion
# aqui cuesta media hora de construccion y tres gigabytes que este contenedor
# no tiene de sobra.
q -q -c "set maintenance_work_mem='2GB';
  create index ix_vp_eq_an_date on bench.results_vp (equipment_id, analyte_id, measured_at desc);
  create index ix_vp_eq_date    on bench.results_vp (equipment_id, measured_at desc);
  create index ix_vp_tn_an_eq_date on bench.results_vp (tenant_id, analyte_id, equipment_id, measured_at desc) include (value_num);" >/dev/null

q -q -c "vacuum analyze bench.results_vp;" >/dev/null

V="-v eq=$EQ -v tn=$TN -v an=$AN -v umbral=$UMBRAL"
{
  echo "consulta,mediana_ms,min_ms,max_ms,sentencias"
  timeq "q1_particionado" queries/q1_particionado.sql $V
  timeq "q2_particionado" queries/q2_particionado.sql $V
  timeq "q3_particionado" queries/q3_particionado.sql $V
} > "$OUT/tiempos_particion_caliente.csv"
cat "$OUT/tiempos_particion_caliente.csv"

{
  explainq q1_particionado queries/q1_particionado.sql $V
  explainq q2_particionado queries/q2_particionado.sql $V
  explainq q3_particionado queries/q3_particionado.sql $V
} > "$OUT/planes_particion.txt" 2>&1

q -q -o "$OUT/tamanos_particion.txt" -c "
select 'results_vp (todas las particiones)' as objeto,
       pg_size_pretty(sum(pg_table_size(c.oid)))   as tabla,
       pg_size_pretty(sum(pg_indexes_size(c.oid))) as indices,
       pg_size_pretty(sum(pg_total_relation_size(c.oid))) as total,
       sum(pg_total_relation_size(c.oid)) as total_bytes,
       count(*) as particiones
from pg_class c join pg_namespace n on n.oid=c.relnamespace
where n.nspname='bench' and c.relname like 'results_vp_%' and c.relkind='r';"
cat "$OUT/tamanos_particion.txt"

{
  echo "consulta,ms_primera_pasada"
  for p in q1_particionado q2_particionado q3_particionado; do
    drop_all_caches
    echo "$p,$(REPS=0 timeq "$p" "queries/$p.sql" $V | cut -d, -f2)"
  done
} > "$OUT/tiempos_particion_fria.csv"
cat "$OUT/tiempos_particion_fria.csv"
