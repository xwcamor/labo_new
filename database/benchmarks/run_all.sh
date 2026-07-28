#!/usr/bin/env bash
# Corrida completa de una escala: recrea el esquema, carga, mide.
#
# Las escalas se corren de a una y se borra la anterior. No es una comodidad:
# la escala grande de la forma vertical mas su copia particionada no entran a la
# vez en el disco disponible, asi que acumular escalas hace fracasar la corrida.
#
# Uso: ./run_all.sh <nombre> <n_equipos> <equipos_por_inquilino> <equipo_prueba> <inquilino_prueba>

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"
cd "$BENCH_DIR"

NOMBRE=${1:?}; N_EQUIP=${2:?}; PER_TENANT=${3:?}; EQ=${4:?}; TN=${5:?}

pg_up
q -q -f sql/00_schema.sql      >/dev/null
q -q -f sql/12_wide_loader.sql >/dev/null
./load.sh "$N_EQUIP" "$PER_TENANT"
./run_scale.sh "$NOMBRE" "$EQ" "$TN"
