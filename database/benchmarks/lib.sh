#!/usr/bin/env bash
# Funciones compartidas del banco de pruebas.
#
# Por que existe la funcion de reintento: en el contenedor de desarrollo el
# servidor de Postgres se detiene solo cada tanto. Una corrida completa dura
# decenas de minutos, asi que una caida a mitad de camino tiraria a la basura
# toda la generacion de datos. Cada llamada a psql pasa por aqui y, si la
# conexion fue rechazada, levanta el servicio y reintenta en vez de abortar.

set -uo pipefail

BENCH_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RESULTS_DIR="$BENCH_DIR/results"
mkdir -p "$RESULTS_DIR"

# Credenciales tomadas del .env de la aplicacion, para no duplicarlas aqui.
ENV_FILE="$(cd "$BENCH_DIR/../.." && pwd)/.env"
db_env() { grep -E "^$1=" "$ENV_FILE" | tail -1 | cut -d= -f2- | tr -d '"'; }

export PGHOST="$(db_env DB_HOST)"
export PGPORT="$(db_env DB_PORT)"
export PGDATABASE="$(db_env DB_DATABASE)"
export PGUSER="$(db_env DB_USERNAME)"
export PGPASSWORD="$(db_env DB_PASSWORD)"

pg_up() {
  pg_isready -q -h "$PGHOST" -p "$PGPORT" && return 0
  pg_ctlcluster 16 main start >/dev/null 2>&1 || service postgresql start >/dev/null 2>&1
  for _ in $(seq 1 20); do
    sleep 1
    pg_isready -q -h "$PGHOST" -p "$PGPORT" && return 0
  done
  return 1
}

# psql con reintento. Todo el banco de pruebas pasa por aqui.
q() {
  local try out rc
  for try in 1 2 3 4 5; do
    out="$(psql -v ON_ERROR_STOP=1 -X "$@" 2>&1)"; rc=$?
    if [ $rc -eq 0 ]; then printf '%s' "$out"; return 0; fi
    if printf '%s' "$out" | grep -qiE 'could not connect|connection refused|server closed|terminating connection'; then
      echo "[lib] conexion perdida, reintento $try" >&2
      pg_up; sleep 2; continue
    fi
    printf '%s' "$out" >&2; return $rc
  done
  return 1
}

# Igual que q() pero devolviendo una sola celda sin adornos.
qv() { q -tA -c "$1"; }

# Vacia la cache del sistema operativo ademas de la de Postgres. Sirve para las
# mediciones en frio: reiniciar Postgres solo limpia shared_buffers, y con 128 MB
# de shared_buffers casi todo el trabajo lo estaba absorbiendo la cache del SO.
drop_all_caches() {
  q -c "checkpoint;" >/dev/null
  pg_ctlcluster 16 main stop >/dev/null 2>&1 || service postgresql stop >/dev/null 2>&1
  sleep 2
  sync; echo 3 > /proc/sys/vm/drop_caches 2>/dev/null
  pg_up
}
