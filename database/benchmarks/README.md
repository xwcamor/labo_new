# Banco de pruebas: forma vertical contra forma ancha

Mide, sobre PostgreSQL 16 y con datos sinteticos a tres escalas, las dos maneras
de guardar los resultados de los ensayos:

- **vertical** — `bench.results_v`, una fila por celda medida;
- **ancha** — `bench.w_<prueba>`, una tabla tipada por ensayo.

El informe con los numeros y la conclusion esta en
[`docs/migracion/08-BENCHMARK-VERTICAL-VS-ANCHO.md`](../../docs/migracion/08-BENCHMARK-VERTICAL-VS-ANCHO.md).

Todo vive en el esquema `bench` de la misma base. No toca `public`.
Para borrarlo: `drop schema bench cascade;`

## Como se repite

```bash
cd database/benchmarks

# escala, equipos, equipos por inquilino, equipo de prueba, inquilino de prueba
./run_all.sh s1_40k    2000   500 1777   4
./run_all.sh s2_400k  20000   500 17777  36
./run_all.sh s3_4m   200000   500 177777 356

# mediciones adicionales sobre la escala ya cargada
./insercion.sh      s3_4m
./varios_equipos.sh s3_4m 200000
./prueba30.sh       s3_4m 177777 177501 178000

# reemplaza la vertical por su version particionada por ano y vuelve a medir
./particion.sh      s3_4m 200000 177777 356

# costo de archivar un ano entero (DETACH+DROP contra DELETE+VACUUM).
# DESTRUYE datos: va al final de todo
./archivo.sh        s3_4m
```

`run_all.sh` **recrea el esquema desde cero**: las escalas no se acumulan. A la
escala mayor la forma vertical no particionada y su copia particionada no entran
a la vez en el disco de este contenedor, y por eso `particion.sh` borra la
primera antes de generar la segunda. Como el generador es determinista
(`bench.rnd`, sin `random()`), la copia sale con el dato identico.

## Que hay en cada archivo

| Archivo | Que hace |
|---|---|
| `lib.sh` | conexion (lee el `.env`), reintento si el servidor se cae, vaciado de caches |
| `measure.sh` | `timeq` (mediana de N pasadas) y `explainq` (plan con buffers) |
| `sql/00_schema.sql` | esquema `bench`, catalogo de parametros derivado de `test_fields` reales, las dos formas |
| `sql/10_load_samples.sql` | cabeceras de muestra |
| `sql/11_load_vertical.sql` | carga de la forma vertical |
| `sql/12_wide_loader.sql` | carga de la forma ancha (arma la sentencia desde el mismo catalogo) |
| `sql/20_indexes.sql` | indices nivel 1 |
| `sql/21_indexes_nivel2.sql` | indices nivel 2 (los que faltan para las consultas 1, 3 y 4) |
| `sql/30_particion.sql` | version particionada por ano |
| `sql/40_insercion.sql` | costo de alta de una hoja de trabajo |
| `sql/50_prueba30.sql` | 26 tablas de ensayo extra para medir el informe consolidado con 30 pruebas |
| `archivo.sh` | costo de sacar un ano entero: separar la particion contra `DELETE`+`VACUUM` |
| `queries/` | las cuatro consultas reales, en las dos formas |
| `results/` | salida: CSV de tiempos, planes, tamanos |

## Advertencias sobre las mediciones

- Los tiempos en caliente son la **mediana** de 7 pasadas, descartando la
  primera. Los tiempos en frio son **una sola pasada** despues de detener
  PostgreSQL y vaciar la cache del sistema operativo.
- El servidor corre con la configuracion por omision de Debian/Ubuntu
  (`shared_buffers` 128 MB). Es poco para una base de 15 GB. Los tiempos en
  caliente estan sostenidos en buena parte por la cache del sistema operativo,
  que en esta maquina tiene 15 GB libres. Con un `shared_buffers` de produccion
  las dos formas mejoran, y mejora mas la vertical, que es la que tiene el
  conjunto de trabajo mas grande.
- Las cuatro consultas se miden sobre el **mismo** equipo y el **mismo**
  inquilino en las dos formas, y las dos formas contienen **el mismo dato**
  celda por celda (se verifica con una comparacion cruzada).
