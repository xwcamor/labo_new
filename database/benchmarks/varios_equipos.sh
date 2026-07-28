#!/usr/bin/env bash
# Variante de la medicion en caliente con un equipo DISTINTO en cada pasada.
#
# Por que hace falta: repetir siete veces la misma consulta sobre el mismo equipo
# deja sus paginas en memoria y mide un caso que en produccion casi no ocurre.
# Con muchos usuarios mirando equipos distintos, la parte alta del indice sigue
# caliente pero las hojas y el monton no. Este numero es el mas parecido a lo
# que siente el usuario en un sistema con trafico.
#
# Uso: ./varios_equipos.sh <escala> <n_equipos>

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/measure.sh"
cd "$BENCH_DIR"

SCALE=${1:?}; N_EQUIP=${2:?}
OUT="$RESULTS_DIR/$SCALE"; mkdir -p "$OUT"
N=${N:-40}
pg_up

# Genera un archivo con N copias de la consulta, cada una con otro equipo.
gen() {
  local src="$1" dst="$2" i eq
  : > "$dst"
  for i in $(seq 1 "$N"); do
    eq=$(( (i * 7919) % N_EQUIP + 1 ))
    sed "s/:eq/$eq/g; s/:an/10/g" "$src" >> "$dst"
  done
}

{
  echo "consulta,mediana_ms_por_pasada,min_ms,max_ms,sentencias_por_pasada"
  for pair in q1_vertical q1_ancho q2_vertical q2_ancho q4_vertical q4_ancho_union; do
    gen "queries/$pair.sql" "queries/_gen_multi_$pair.sql"
    # REPS=1: la primera pasada calienta, la segunda mide. Cada pasada son N
    # consultas sobre N equipos distintos; el tiempo se divide por N.
    line="$(REPS=1 timeq "$pair" "queries/_gen_multi_$pair.sql")"
    med="$(echo "$line" | cut -d, -f2)"
    mn="$(echo "$line" | cut -d, -f3)"
    mx="$(echo "$line" | cut -d, -f4)"
    st="$(echo "$line" | cut -d, -f5)"
    echo "$pair,$(awk -v v="$med" -v n="$N" 'BEGIN{printf "%.3f", v/n}'),$(awk -v v="$mn" -v n="$N" 'BEGIN{printf "%.3f", v/n}'),$(awk -v v="$mx" -v n="$N" 'BEGIN{printf "%.3f", v/n}'),$(( st / N ))"
  done
} > "$OUT/tiempos_equipos_variados.csv"
cat "$OUT/tiempos_equipos_variados.csv"
