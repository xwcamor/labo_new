#!/usr/bin/env bash
# Mide una escala ya cargada y deja los resultados en results/<escala>.
#
# Uso: ./run_scale.sh <nombre_escala> <equipo_de_prueba> <inquilino_de_prueba>

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/measure.sh"

SCALE=${1:?falta nombre de escala}
EQ=${2:?falta equipo}
TN=${3:-1}
AN=${AN:-10}          # analyte 10 = hidrogeno H2, el parametro de la tendencia
UMBRAL=${UMBRAL:-100} # ppm de H2

OUT="$RESULTS_DIR/$SCALE"
mkdir -p "$OUT"
pg_up

V="-v eq=$EQ -v tn=$TN -v an=$AN -v umbral=$UMBRAL"

# --- tamanos -------------------------------------------------------------
q -q -o "$OUT/tamanos.txt" -c "
select relname,
       to_char(n_live_tup, 'FM999,999,999,999')                as filas,
       pg_size_pretty(pg_table_size(relid))                    as tabla,
       pg_size_pretty(pg_indexes_size(relid))                  as indices,
       pg_size_pretty(pg_total_relation_size(relid))           as total,
       pg_total_relation_size(relid)                           as total_bytes
from pg_stat_user_tables
where schemaname = 'bench'
order by pg_total_relation_size(relid) desc;"

# --- conteos reales ------------------------------------------------------
q -q -o "$OUT/conteos.txt" -c "
select 'samples' t, count(*) c from bench.samples
union all select 'results_v', count(*) from bench.results_v
union all select 'w_analisis_cromatografico', count(*) from bench.w_analisis_cromatografico
union all select 'w_grado_de_polimerizacion', count(*) from bench.w_grado_de_polimerizacion
union all select 'w_numero_acido', count(*) from bench.w_numero_acido
union all select 'w_contenido_de_agua', count(*) from bench.w_contenido_de_agua;"

# --- tiempos, cache caliente --------------------------------------------
run_all() {
  local sufijo="$1"
  {
    echo "consulta,mediana_ms,min_ms,max_ms,sentencias"
    timeq "q1_vertical"      queries/q1_vertical.sql      $V
    timeq "q1_ancho"         queries/q1_ancho.sql         $V
    timeq "q2_vertical"      queries/q2_vertical.sql      $V
    timeq "q2_ancho"         queries/q2_ancho.sql         $V
    timeq "q3_vertical"      queries/q3_vertical.sql      $V
    timeq "q3_ancho"         queries/q3_ancho.sql         $V
    timeq "q4_vertical"      queries/q4_vertical.sql      $V
    timeq "q4_ancho_crudo"   queries/q4_ancho_crudo.sql   $V
    timeq "q4_ancho_union"   queries/q4_ancho_union.sql   $V
  } > "$OUT/tiempos_$sufijo.csv"
  cat "$OUT/tiempos_$sufijo.csv"
}

echo "== $SCALE :: indices nivel 1, cache caliente =="
run_all "nivel1_caliente"

echo "== $SCALE :: indices nivel 1, planes =="
{
  explainq q1_vertical    queries/q1_vertical.sql    $V
  explainq q2_vertical    queries/q2_vertical.sql    $V
  explainq q3_vertical    queries/q3_vertical.sql    $V
  explainq q4_vertical    queries/q4_vertical.sql    $V
  explainq q1_ancho       queries/q1_ancho.sql       $V
  explainq q2_ancho       queries/q2_ancho.sql       $V
  explainq q3_ancho       queries/q3_ancho.sql       $V
  explainq q4_ancho_union queries/q4_ancho_union.sql $V
} > "$OUT/planes_nivel1.txt" 2>&1

echo "== $SCALE :: indices nivel 2 =="
q -q -f sql/21_indexes_nivel2.sql >/dev/null
q -q -c "vacuum analyze bench.results_v, bench.w_analisis_cromatografico;" >/dev/null
run_all "nivel2_caliente"

q -q -o "$OUT/tamanos_nivel2.txt" -c "
select relname, pg_size_pretty(pg_table_size(relid)) tabla,
       pg_size_pretty(pg_indexes_size(relid)) indices,
       pg_size_pretty(pg_total_relation_size(relid)) total,
       pg_total_relation_size(relid) total_bytes
from pg_stat_user_tables where schemaname='bench'
order by pg_total_relation_size(relid) desc;"

{
  explainq q1_vertical    queries/q1_vertical.sql    $V
  explainq q3_vertical    queries/q3_vertical.sql    $V
  explainq q4_vertical    queries/q4_vertical.sql    $V
} > "$OUT/planes_nivel2.txt" 2>&1

# --- tiempos, cache fria -------------------------------------------------
# Una pasada unica por consulta despues de vaciar shared_buffers Y la cache del
# sistema operativo. Con REPS=0 la unica medicion es la primera, que es la que
# paga la lectura desde disco.
echo "== $SCALE :: cache fria (una pasada por consulta) =="
{
  echo "consulta,ms_primera_pasada"
  for pair in q1_vertical q1_ancho q2_vertical q2_ancho q3_vertical q3_ancho q4_vertical q4_ancho_union; do
    drop_all_caches
    ms="$(REPS=0 timeq "$pair" "queries/$pair.sql" $V | cut -d, -f2)"
    echo "$pair,$ms"
  done
} > "$OUT/tiempos_fria.csv"
cat "$OUT/tiempos_fria.csv"

echo "== $SCALE :: listo, resultados en $OUT =="
