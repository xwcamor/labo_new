#!/usr/bin/env bash
# Costo de sacar de la tabla un ano entero de historia.
#
# Es el argumento real a favor de particionar en un sistema con historico, asi
# que se mide, no se supone. Se comparan las dos operaciones sobre la MISMA
# tabla particionada:
#
#   - separar y borrar la particion de un ano (lo que permite la particion);
#   - borrar las mismas filas con DELETE ... WHERE (lo que hay que hacer sin ella).
#
# El DELETE se mide sobre la tabla particionada a proposito, y hay que leer el
# numero sabiendo que ESO FAVORECE AL DELETE: al filtrar por la columna de
# particion, PostgreSQL lo dirige a una sola particion y mantiene solo los
# indices de esa particion. Sobre la tabla sin particionar seria peor, porque
# alli no hay ningun indice que empiece por measured_at y habria que recorrer
# el monton entero. O sea: el numero del DELETE es una cota INFERIOR.
#
# Destruye datos. Corre al final de todo.
#
# Uso: ./archivo.sh <escala>

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"
cd "$BENCH_DIR"

SCALE=${1:?}
OUT="$RESULTS_DIR/$SCALE"; mkdir -p "$OUT"
pg_up

ms() { # ms <etiqueta> <sql>
  local t0 t1
  t0=$(date +%s%N)
  q -q -c "$2" >/dev/null
  t1=$(date +%s%N)
  echo "$1,$(( (t1 - t0) / 1000000 ))"
}

{
  echo "operacion,ms"
  echo "# filas por ano"
  q -tA -c "select 'filas_' || anio || ',' || filas from (
              select extract(year from measured_at)::int as anio, count(*) as filas
              from bench.results_vp group by 1) t order by anio;"

  # Separar la particion la deja como tabla independiente: es lo que se hace
  # para archivar un ano sin perderlo.
  ms "detach_particion_2016" "alter table bench.results_vp detach partition bench.results_vp_2016;"
  ms "drop_tabla_2016"       "drop table bench.results_vp_2016;"

  # La misma cantidad de filas, borradas de la manera clasica.
  ms "delete_ano_2017"       "delete from bench.results_vp where measured_at >= date '2017-01-01' and measured_at < date '2018-01-01';"
  # El DELETE no devuelve el espacio ni limpia los indices hasta que pasa VACUUM.
  ms "vacuum_despues_delete"  "vacuum bench.results_vp_2017;"
} > "$OUT/archivo.csv"
cat "$OUT/archivo.csv"
