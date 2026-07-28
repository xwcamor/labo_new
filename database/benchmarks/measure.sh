#!/usr/bin/env bash
# Motor de medicion.
#
# Cada consulta se ejecuta REPS+1 veces dentro de UNA sola sesion de psql. La
# primera pasada se descarta: es la que paga el analisis de la sentencia y la
# que sube las paginas a memoria. De las REPS restantes se reporta la MEDIANA,
# no el promedio, porque en un contenedor compartido basta una pausa del
# planificador del sistema operativo para que el promedio deje de describir nada.
#
# Los archivos de consulta pueden tener varias sentencias (la forma ancha resuelve
# el informe con cuatro). El tiempo de una pasada es la SUMA de sus sentencias,
# que es lo que espera el usuario del sistema.

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

REPS=${REPS:-7}

# timeq <etiqueta> <archivo.sql> [-v var=valor ...]
# Escribe una linea CSV: etiqueta,mediana_ms,min_ms,max_ms,sentencias
timeq() {
  local label="$1" file="$2"; shift 2
  local tmp; tmp="$(mktemp)"
  {
    echo '\timing on'
    echo '\o /dev/null'
    for _ in $(seq 0 "$REPS"); do echo "\\i $file"; done
  } > "$tmp"

  local raw times n_stmt
  raw="$(q "$@" -q -f "$tmp" | grep -oP '(?<=^Time: )[0-9.]+')"
  rm -f "$tmp"

  local total; total="$(printf '%s\n' "$raw" | grep -c .)"
  n_stmt=$(( total / (REPS + 1) ))
  [ "$n_stmt" -lt 1 ] && { echo "$label,ERROR,,,"; return 1; }

  # Suma por pasada, descartando la primera. Con REPS=0 (medicion en frio) la
  # unica pasada que hay es justamente la que interesa: la que lee de disco.
  local keep=$REPS; [ "$keep" -lt 1 ] && keep=1
  times="$(printf '%s\n' "$raw" | awk -v k="$n_stmt" '
    { s += $1; if (NR % k == 0) { print s; s = 0 } }' | tail -n "$keep" | sort -g)"

  local med min max
  med="$(printf '%s\n' "$times" | awk '{a[NR]=$1} END{ if(NR%2) print a[(NR+1)/2]; else printf "%.3f", (a[NR/2]+a[NR/2+1])/2 }')"
  min="$(printf '%s\n' "$times" | head -1)"
  max="$(printf '%s\n' "$times" | tail -1)"
  echo "$label,$med,$min,$max,$n_stmt"
}

# explainq <etiqueta> <archivo.sql> [-v ...] -> guarda el plan completo
explainq() {
  local label="$1" file="$2"; shift 2
  local tmp; tmp="$(mktemp)"
  # EXPLAIN no se puede anteponer a un archivo con varias sentencias desde psql,
  # asi que se envuelve cada sentencia con \gexec no sirve; se usa auto_explain
  # via una copia del archivo con EXPLAIN delante de cada sentencia.
  awk 'BEGIN{p=1} /^--/ && p {print; next} { if (p && $0 ~ /[^ \t]/) { print "explain (analyze, buffers, costs off, timing off, summary on)"; p=0 } print; if ($0 ~ /;[ \t]*$/) p=1 }' "$file" > "$tmp"
  {
    echo "\\echo '===== $label ====='"
    cat "$tmp"
  } > "${tmp}.run"
  q "$@" -q -f "${tmp}.run"
  rm -f "$tmp" "${tmp}.run"
}
