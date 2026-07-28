# Banco de pruebas: una fila por celda medida contra una tabla por ensayo

> Esta discusión no se resuelve opinando. Se midió.
>
> Los guiones que producen estos números están en
> [`database/benchmarks/`](../../database/benchmarks/README.md) y se vuelven a
> correr con un comando. Las tablas del banco de pruebas viven en un esquema
> aparte (`bench`) y no tocan el esquema de la aplicación.

---

## 0. La pregunta

El sistema anterior del laboratorio se volvió lento. El dueño del proyecto
sostiene que la causa fue guardar los resultados **en vertical** —una fila por
celda medida— y que esa tabla creció más de lo que aguantaba; propone en cambio
una **tabla ancha por ensayo**, 29 tablas tipadas, una por prueba.

La posición contraria es que la causa no fue la verticalidad sino que los
valores eran texto sin índice utilizable, que el vínculo entre la hoja de
trabajo y la muestra se hacía por texto, y que las vistas tenían N+1.

Las dos son hipótesis razonables sobre el mismo síntoma. Lo único que las
separa son números.

---

## 1. Qué se midió y cómo

### 1.1 Las dos formas

| | Forma A — vertical | Forma B — ancha |
|---|---|---|
| Tablas | `bench.results_v` | `bench.w_analisis_cromatografico`, `w_grado_de_polimerizacion`, `w_numero_acido`, `w_contenido_de_agua` |
| Una fila es | una celda medida | una hoja de trabajo completa |
| Columnas de datos | 3 (`value_num`, `qualifier`, `unit`) | 13 / 16 / 9 / 9 |

Las cuatro pruebas de la forma ancha son las cuatro plantillas **reales** de la
aplicación con más columnas. Sus nombres, su cantidad de columnas y el tipo de
cada una salen de `public.test_definitions` y `public.test_fields`, no de una
invención: el generador lee ese catálogo.

**Las dos formas contienen exactamente el mismo dato**, celda por celda. Se
verifica con una comparación cruzada antes de medir (4.000 celdas comparadas,
0 discrepancias). El generador es determinista (no usa `random()`), lo que
además permite regenerar el mismo juego de datos más tarde.

### 1.2 El universo simulado

Cada equipo se muestrea dos veces por año durante diez años (20 muestras). No
todas las muestras corren las cuatro pruebas: cromatografía 100 %, contenido de
agua 95 %, número ácido 90 %, grado de polimerización 12 % (exige papel del
equipo, casi nunca se hace). Eso da **20,98 filas verticales por muestra**,
coherente con la estimación de partida de unas 7 filas por muestra y por prueba.

| Escala | Equipos | Espacios de trabajo | Muestras | Filas verticales **reales** | Filas anchas **reales** |
|---|---|---|---|---|---|
| S1 | 2.000 | 4 | 40.000 | **839.251** | 118.809 |
| S2 | 20.000 | 40 | 400.000 | **8.392.024** | 1.188.001 |
| S3 | 200.000 | 400 | 4.000.000 | **83.920.006** | 11.879.997 |

Se llegó a las tres escalas, incluida la de 100×. Los equipos por espacio de
trabajo se mantienen constantes (500), que es como crece de verdad un SaaS: no
crece un cliente, crecen los clientes. El tablero de flota mide entonces lo que
importa —si el tablero de un laboratorio se degrada porque crecen los datos de
los **otros** laboratorios— y aparte se mide una variante global sobre todo el
universo.

### 1.3 Las cuatro consultas

1. **Informe de un equipo** — todos los parámetros de su última muestra.
2. **Tendencia** — la serie de un parámetro (hidrógeno) de un equipo, 5 años.
3. **Tablero de flota** — último valor de un parámetro por equipo del espacio de
   trabajo, y cuántos superan 100 ppm.
4. **Consulta transversal** — todos los resultados de un equipo, de todas las
   pruebas, 5 años. Es el informe consolidado.

En la forma ancha la consulta 1 son cuatro sentencias (una por modelo, que es lo
que hace la aplicación) y el tiempo reportado es su suma. La consulta 4 se mide
de dos maneras: **cruda** (cuatro lecturas y que PHP transponga) y **UNION**
(devolviendo la misma forma que la vertical, una fila por parámetro).

### 1.4 Los índices

Este es el punto donde una medición se amaña sin querer, así que se hizo en dos
pasadas y se reportan las dos.

**Nivel 1** — sobre la vertical, únicamente los dos índices que se pidió probar:

| Índice | Para qué |
|---|---|
| `(equipment_id, analyte_id, measured_at desc)` | tendencia de un parámetro de un equipo |
| `(analyte_id, measured_at)` | intento de índice para el tablero de flota |

**Nivel 2** — los que agregaría cualquiera que mire los planes:

| Índice | Para qué |
|---|---|
| `(equipment_id, measured_at desc)` | consultas 1 y 4: todo lo de un equipo, sin filtrar por parámetro |
| `(tenant_id, analyte_id, equipment_id, measured_at desc) INCLUDE (value_num)` | consulta 3: recorrido solo por índice, sin ir al montón |

**La forma ancha recibió el equivalente exacto en las dos pasadas**, incluido su
propio índice cubridor `(tenant_id, equipment_id, measured_at desc) INCLUDE
(hidrogeno_h2_ppm)`. Sin eso el planificador elegía mapa de bits más
ordenamiento y la forma ancha perdía por una razón que no tiene nada que ver con
la forma de la tabla.

### 1.5 Método

- **Caliente**: mediana de 7 pasadas en una misma sesión, descartando la
  primera. Se reporta la mediana, no el promedio: en un contenedor compartido
  una pausa del planificador del sistema operativo arruina un promedio.
- **Fría**: una sola pasada después de detener PostgreSQL **y vaciar la caché
  del sistema operativo** (`drop_caches`). Es una única muestra por consulta:
  ruidosa por construcción, léase como orden de magnitud.
- `ANALYZE` corrido sobre todas las tablas antes de medir. Todos los planes que
  se citan salen de `EXPLAIN (ANALYZE, BUFFERS)`.
- Servidor con la configuración por omisión de Ubuntu: `shared_buffers` 128 MB,
  `work_mem` 4 MB, `max_wal_size` 1 GB. Es poco para una base de 15 GB, y hay que
  tenerlo en cuenta al leer los números en caliente: buena parte del trabajo lo
  sostiene la caché del sistema operativo (15 GB libres en la máquina). Con un
  `shared_buffers` de producción **las dos formas mejoran, y mejora más la
  vertical**, que es la que tiene el conjunto de trabajo más grande. O sea: el
  entorno de medición está sesgado en contra de la forma vertical, no a favor.

---

## 2. Resultados en caliente

### 2.1 Con los índices de nivel 1 (los dos que se pidió probar)

Tiempos en milisegundos, mediana de 7 pasadas.

| Consulta | S1 vert. | S1 ancho | S2 vert. | S2 ancho | S3 vert. | S3 ancho |
|---|---|---|---|---|---|---|
| 1 · informe de un equipo | 0,49 | 0,86 | 0,42 | 1,37 | 0,57 | 0,86 |
| 2 · tendencia | 0,35 | 0,33 | 0,33 | 0,25 | 0,41 | 0,31 |
| 3 · tablero de flota | **9,36** | 5,59 | **356,21** | 5,90 | **1.894,56** | 3,73 |
| 4 · transversal (UNION) | 0,92 | 0,86 | 0,51 | 0,93 | 0,58 | 1,01 |

La fila 3 es el argumento del dueño del proyecto en su forma más fuerte, y hay
que decirlo con todas las letras: **con esos índices la forma vertical se cae**.
1,9 segundos contra 3,7 milisegundos es un factor de **508×**. A 8 millones de
filas ya rompe el presupuesto de 200 ms, y a 84 millones no es utilizable.

El plan explica exactamente por qué:

```
Parallel Bitmap Heap Scan on results_v
  Recheck Cond: (analyte_id = 10)
  Filter: (tenant_id = 36)          <-- el filtro que importa se aplica DESPUÉS
  Rows Removed by Filter: 130000
  Buffers: shared read=71981         <-- 562 MB leídos para devolver 500 filas
```

El índice `(analyte_id, measured_at)` no lleva el espacio de trabajo. Para
responder por un laboratorio hay que leer el hidrógeno de **todos** los
laboratorios y descartar el 97,5 % después. Eso no escala, y crece con los datos
de los demás clientes: es exactamente la patología que hundió al sistema viejo.

### 2.2 Con los índices de nivel 2 (las dos formas correctamente indexadas)

| Consulta | S1 vert. | S1 ancho | S2 vert. | S2 ancho | S3 vert. | S3 ancho |
|---|---|---|---|---|---|---|
| 1 · informe de un equipo | 0,44 | 0,86 | 0,45 | 0,96 | **0,44** | 0,98 |
| 2 · tendencia | 0,36 | 0,38 | 0,26 | 0,25 | **0,32** | 0,33 |
| 3 · tablero de flota | 1,77 | 1,89 | 1,79 | 1,39 | **1,72** | 1,57 |
| 4 · transversal (UNION) | 0,68 | 0,93 | 0,53 | 0,90 | **0,57** | 1,10 |
| 4 · transversal (cruda, 4 lecturas) | — | 1,57 | — | 1,38 | — | 1,07 |

**Un solo índice —el `(tenant_id, analyte_id, equipment_id, measured_at)
INCLUDE (value_num)`— llevó la consulta 3 de 1.894 ms a 1,72 ms. Mil cien veces más rápida. Sin cambiar una sola fila de datos ni una línea del
esquema.**

Y el plan pasa a ser el mismo que el de la forma ancha:

```
Index Only Scan using ix_v_tn_an_eq_date on results_v
  Heap Fetches: 0
  Buffers: shared hit=54            <-- 54 páginas, contra 71.981 antes
Execution Time: 1.991 ms
```

Lo notable de esta tabla es lo que **no** pasa: entre S1 y S3, con la tabla
vertical multiplicándose por cien —de 839 mil a 84 millones de filas— ninguna de
las cuatro consultas se movió. 0,44 / 0,32 / 1,72 / 0,57 ms. Un árbol B crece de
manera logarítmica; multiplicar las filas por cien agrega un nivel al árbol, y
un nivel son microsegundos.

### 2.3 Cuánto más rápida es cada forma (escala S3, 84 millones de filas)

| Consulta | Vertical | Ancha | Quién gana | Cuántas veces |
|---|---|---|---|---|
| 1 · informe de un equipo | 0,44 ms | 0,98 ms | **vertical** | 2,25× |
| 2 · tendencia | 0,32 ms | 0,33 ms | empate | 1,03× |
| 3 · tablero de flota | 1,72 ms | 1,57 ms | **ancha** | 1,10× |
| 4 · transversal (UNION) | 0,57 ms | 1,10 ms | **vertical** | 1,94× |
| 4 · transversal (cruda) | 0,57 ms | 1,07 ms | **vertical** | 1,89× |

La forma ancha no es más rápida. En dos de las cuatro consultas es la vertical
la que gana, y por casi el doble.

La razón de la consulta 1 es concreta: en la forma ancha el informe de un equipo
son cuatro sentencias contra cuatro tablas, cuatro planificaciones y cuatro
viajes; en la vertical es un recorrido de índice que devuelve 20 filas leyendo
**8 páginas**. Cuanto más pruebas tenga el laboratorio, peor le va a la ancha en
esta consulta: hoy son 4 sentencias, con las 29 pruebas del laboratorio son 29.

### 2.4 Un equipo distinto en cada pasada

Repetir siete veces la misma consulta sobre el mismo equipo deja sus páginas
calientes y mide un caso que en producción casi no ocurre. Se repitió la
medición sobre **40 equipos distintos** en cada pasada (S3):

| Consulta | Vertical | Ancha |
|---|---|---|
| 1 · informe | 0,42 ms | 0,93 ms |
| 2 · tendencia | 0,29 ms | 0,24 ms |
| 4 · transversal | 0,62 ms | 1,00 ms |

Idénticos a los de la tabla anterior. Los números en caliente no son un
artefacto de haber calentado un solo equipo.

---

## 3. Resultados en frío

Una sola pasada por consulta, con PostgreSQL reiniciado y la caché del sistema
operativo vaciada. Es una muestra única y es ruidosa: léanse como orden de
magnitud, no como medición fina.

| Consulta | S1 vert. | S1 ancho | S2 vert. | S2 ancho | S3 vert. | S3 ancho |
|---|---|---|---|---|---|---|
| 1 · informe | 33,7 | 21,5 | 16,7 | 28,7 | 12,6 | 50,0 |
| 2 · tendencia | 13,2 | 10,9 | 19,7 | 24,1 | 48,9 | 21,8 |
| 3 · tablero | 12,3 | 10,9 | 13,6 | 16,8 | 51,5 | 41,3 |
| 4 · transversal | 23,5 | 15,8 | 13,7 | 24,5 | 164,9 | 128,8 |

En frío la ventaja se invierte y pasa a la forma ancha en tres de las cuatro
consultas de la escala mayor: es el costo de tener un índice más grande que
recorrer desde disco. La diferencia es de decenas de milisegundos y solo la paga
la primera consulta después de un reinicio. Ninguna supera los 200 ms salvo la
transversal a 84 millones de filas, y la paga también la forma ancha (129 ms).

---

## 4. Tamaño en disco

Escala S3 — 4 millones de muestras, con los índices de nivel 2 en las dos formas.

| Forma | Tabla | Índices | Total |
|---|---|---|---|
| **Vertical** `results_v` | 6.186 MB | 8.809 MB | **15,0 GB** |
| + `samples` (cabecera de muestra) | 230 MB | 119 MB | 349 MB |
| **Vertical, total** | | | **15,4 GB** |
| `w_analisis_cromatografico` | 568 MB | 446 MB | 1.015 MB |
| `w_contenido_de_agua` | 396 MB | 163 MB | 559 MB |
| `w_numero_acido` | 375 MB | 154 MB | 529 MB |
| `w_grado_de_polimerizacion` | 77 MB | 21 MB | 97 MB |
| **Ancha, total** | | | **2,15 GB** |

**La forma vertical ocupa 7,0 veces más.** Y la proporción es estable: 6,9× en
S1, 7,0× en S2, 7,0× en S3, así que se puede proyectar sin sorpresas.

De dónde sale: la forma vertical repite en cada fila el equipo, la fecha, el
inquilino, la prueba y el identificador del parámetro —20 bytes de claves más
24 de cabecera de fila para 8 bytes de dato útil— y ese sobrecosto lo pagan
también los índices, que son 8,8 GB contra 6,2 GB de tabla. Es el precio real de
la flexibilidad, y no hay forma de negociarlo.

Dicho eso: **15 GB de datos son 15 GB.** A este laboratorio le costarían unos
pocos dólares por mes de disco. Y la escala S3 (200.000 equipos) es cien veces
el laboratorio real; a su escala verdadera la forma vertical ocupa **150 MB**.

---

## 5. Costo de dar de alta una hoja de trabajo

Medido en la escala S3, con todos los índices puestos, promediando 500 altas.

| Escenario | Vertical | Ancha | Diferencia |
|---|---|---|---|
| Muestra con las 4 pruebas (35 filas vert. / 5 anchas) | 1,01 ms | 0,20 ms | ancha 5,0× |
| Solo cromatografía (12 filas vert. / 2 anchas) | 0,28 ms | 0,05 ms | ancha 5,0× |

La forma ancha escribe cinco veces más rápido, de manera consistente. Es un
resultado a favor de la propuesta del dueño y hay que anotarlo como tal.

También hay que ponerlo en escala: **1 milisegundo por hoja de trabajo.** Con
las 4.000 muestras al año que maneja el laboratorio, el sobrecosto anual de la
forma vertical en escritura es de **cuatro segundos**. La medición se hizo
dentro del servidor, en un bucle, así que ni siquiera incluye el viaje desde
PHP —que la forma ancha paga cuatro veces, una por tabla, y la vertical una.

---

## 6. La consulta transversal y la prueba número 30

Esta es la pregunta que más importa, porque no es sobre el rendimiento de hoy
sino sobre lo que pasa cuando el laboratorio da de alta una prueba.

En la forma vertical, la consulta 4 no cambia: una prueba nueva son filas
nuevas. En la forma ancha hay que agregar una rama al UNION y escribir a mano
un par (columna, parámetro) por cada columna del ensayo nuevo.

Se midió de verdad: se crearon **26 tablas de ensayo adicionales** con el ancho
promedio real de una prueba del laboratorio y se corrió el informe consolidado
con 4, 10, 20 y 30 pruebas dadas de alta.

| Pruebas dadas de alta | Ramas del UNION | Tiempo (ms) | Planificación | Ejecución | Largo del SQL |
|---|---|---|---|---|---|
| 4 | 4 | 1,11 | 1,59 ms | 0,57 ms | 2.233 car. |
| 10 | 10 | 2,14 | 2,82 ms | 1,39 ms | 3.913 car. |
| 20 | 20 | 4,62 | 5,76 ms | 2,95 ms | 6.727 car. |
| 30 | 30 | 6,82 | 8,14 ms | 4,20 ms | 9.547 car. |
| — | **La misma consulta en vertical** | **0,62** | 0,70 ms | 1,02 ms | 480 car. |

El crecimiento es **lineal y limpio**: cada prueba nueva agrega unos 0,22 ms al
informe consolidado de **cada** equipo, para siempre. Con las 29 pruebas que ya
tiene el laboratorio, la consulta transversal en forma ancha cuesta **6,8 ms
contra 0,62 ms de la vertical: 11 veces más**.

Y hay un detalle del que conviene tomar nota: más de la mitad del costo es
**planificación**, no ejecución. Se paga aunque el equipo no tenga ni una
muestra en 26 de esas 30 tablas —como es el caso aquí—. Es costo estructural: no
lo baja tener menos datos, ni un índice, ni una caché.

El largo del SQL es la otra mitad del problema, y no se mide en milisegundos:
9.547 caracteres de UNION escritos a mano contra 480. Ese texto hay que
generarlo o mantenerlo, y se rompe cada vez que alguien agrega una columna a un
ensayo.

---

## 7. El tablero de flota global

La consulta 3 mide un espacio de trabajo (500 equipos). Aparte se midió la
variante que agrega sobre **todo el universo** (200.000 equipos, 4 millones de
muestras de cromatografía), que es lo que ve un administrador del sistema.

Vale la pena mostrar las tres etapas, porque ilustran de qué depende de verdad
este resultado:

| Etapa | Vertical | Ancha |
|---|---|---|
| Índices tal como quedaron del nivel 2 | 3.372 ms (recorrido secuencial) | 1.807 ms |
| + índice cubridor en la vertical | **565 ms** | 1.807 ms |
| + índice cubridor también en la ancha | 566 ms | **411 ms** |

Con las dos formas igualmente indexadas, **la ancha gana esta consulta por
1,38×** y lee cinco veces menos páginas (23.000 contra 112.000), porque su
entrada de índice es más angosta: no lleva el identificador de parámetro. Es una
victoria real de la forma ancha y es la única del banco de pruebas fuera de la
escritura.

Nótese además el salto intermedio: la misma consulta, sobre los mismos datos,
pasó de 3.372 ms a 565 ms —**seis veces**— agregando un índice. Es el mismo
fenómeno de la sección 2.1, en la otra dirección.

---

## 8. Partición por año

### 8.1 No hace falta para consultar — cuesta entre 2 y 4 veces

Se rehízo la forma vertical de la escala S3 como tabla particionada por rango de
`measured_at`, una partición por año (2016–2026, once particiones), con los
mismos índices de nivel 2. Los datos son idénticos: el generador es determinista
y se regeneró el mismo juego.

Caché caliente, mediana de 7 pasadas, repetido en **cuatro corridas
independientes**. Hizo falta repetir: con once particiones la varianza sube
mucho —la primera medición de la consulta 4 dio 5,26 ms con un máximo de
19,8 ms, y las tres siguientes dieron entre 0,82 y 1,44 ms—, así que una sola
corrida no describe nada. Se reporta la mediana de las cuatro; las dieciséis
mediciones crudas están en
[`results/s3_4m/tiempos_particion_caliente.csv`](../../database/benchmarks/results/s3_4m/tiempos_particion_caliente.csv).

| Consulta | Sin partición | Con partición | Diferencia |
|---|---|---|---|
| 1 · informe de un equipo | **0,44 ms** | 1,60 ms | 3,7× más lenta |
| 2 · tendencia | **0,32 ms** | 0,67 ms | 2,1× más lenta |
| 3 · tablero de flota | **1,72 ms** | 3,82 ms | 2,2× más lenta |
| 4 · transversal | **0,57 ms** | 1,36 ms | 2,4× más lenta |

La partición **empeora las cuatro consultas**, incluida la tendencia, que es la
que en teoría más debería aprovecharla porque filtra por fecha.

### 8.2 Dónde se va el tiempo: planificación, no ejecución

Separar los dos costos explica el resultado entero.

| Consulta | Planif. sin part. | Ejec. sin part. | Planif. con part. | Ejec. con part. | Particiones tocadas |
|---|---|---|---|---|---|
| 1 · informe | 0,84 ms | 0,19 ms | **5,32 ms** | 0,42 ms | 11, dos veces (la subconsulta y la principal) |
| 2 · tendencia | 0,63 ms | 0,10 ms | **2,68 ms** | 0,41 ms | 6 (descarta 2016–2020) |
| 3 · tablero | 0,89 ms | 1,99 ms | **4,02 ms** | 4,60 ms | 11 (no hay filtro de fecha que podar) |
| 4 · transversal | 1,25 ms | 0,46 ms | **3,21 ms** | 0,71 ms | 6 |

La ejecución casi no se mueve: los índices locales son más chicos y compensan.
Lo que se dispara es la **planificación**, entre 2,6 y 6,3 veces, porque el
planificador tiene que considerar once tablas y once juegos de índices en vez de
uno, y después combinar sus resultados con un `Append` o un `Merge Append`.

En la consulta 1 el desbalance es grotesco: **5,32 ms de planificación para
0,42 ms de ejecución.** Se paga trece veces más por decidir cómo leer que por
leer. Y es costo fijo: no baja con menos datos, ni con un índice mejor, ni con
caché.

Las consultas 1 y 3 son además las que peor se llevan con la partición por una
razón de fondo: **no saben de antemano en qué año está la respuesta.** "La
última muestra de este equipo" y "el último valor de este parámetro por equipo"
no traen filtro de fecha, así que no hay nada que podar y hay que abrir las once
particiones. La consulta 2, que sí filtra cinco años, poda cinco particiones y
aun así pierde: lo que ahorra en ejecución no alcanza para pagar la
planificación.

### 8.3 En frío

Una sola pasada por consulta, con PostgreSQL reiniciado y la caché del sistema
operativo vaciada. Muestra única, ruidosa.

| Consulta | Sin partición | Con partición |
|---|---|---|
| 1 · informe | **12,6 ms** | 58,2 ms |
| 2 · tendencia | 48,9 ms | **22,3 ms** |
| 3 · tablero | **51,5 ms** | 97,5 ms |
| 4 · transversal | **164,9 ms** | 487,4 ms |

Aquí sí aparece la única ventaja de consulta que tiene la partición: la
tendencia en frío mejora más del doble (22 contra 49 ms), porque los índices
locales de los cinco años pedidos son mucho más chicos que un índice global.
Las otras tres empeoran, y la transversal empeora casi 3×: once particiones en
frío son once árboles distintos que subir desde disco.

### 8.4 Tamaño

| | Total | Índices | Nota |
|---|---|---|---|
| Sin partición | 15,0 GB | 8.809 MB (5 índices) | |
| Con partición | 15,9 GB | 8.975 MB (4 índices) | un índice **menos** y aun así ocupa más |

La partición sale más cara en disco, y hay una razón concreta que conviene
anotar porque sorprende: **una tabla particionada no admite una clave primaria
que no incluya la columna de partición.** La clave pasa de `(id)` a
`(id, measured_at)` y crece de 1.798 MB a 2.521 MB, un 40 % más. A eso se suma
que once árboles B chicos desperdician más espacio en páginas parcialmente
llenas que uno grande.

### 8.5 Archivar un año: aquí la partición gana, y por mucho

Este es el argumento de verdad a favor de particionar, y los números lo
respaldan sin discusión. Un año son 8.392.000 filas.

| Operación | Tiempo |
|---|---|
| `ALTER TABLE ... DETACH PARTITION` (separa el año, no lo pierde) | 79 ms |
| `DROP TABLE` de esa partición | 75 ms |
| **Total con partición** | **154 ms** |
| `DELETE ... WHERE measured_at >= ... AND < ...` | 22.841 ms |
| `VACUUM` posterior (sin él no se recupera el espacio ni se limpian los índices) | 23.305 ms |
| **Total sin partición** | **46.146 ms** |

**Trescientas veces más rápido**, y el `DELETE` está medido en su mejor caso:
se ejecutó sobre la propia tabla particionada, donde PostgreSQL lo dirige a una
sola partición y mantiene solo los índices de esa partición. Sobre la tabla sin
particionar sería peor todavía, porque allí no hay ningún índice que empiece por
`measured_at` y habría que recorrer los 6,2 GB del montón. El número de
46 segundos es una **cota inferior**.

Además el `DETACH` conserva el año como tabla independiente: se puede volcar a
un archivo y guardarlo, que es exactamente lo que quiere hacer el laboratorio
con el histórico viejo. El `DELETE` no deja nada.

### 8.6 Veredicto: no hace falta a este volumen

La partición por año **no cambia el veredicto y hoy no conviene**:

- cuesta entre 2 y 4 veces en las cuatro consultas, en caché caliente;
- el sobrecosto es de **planificación**, o sea fijo e imposible de optimizar;
- ocupa 900 MB más con un índice menos;
- y las consultas que resuelve no eran un problema: la tendencia da **0,32 ms**
  sin partición. Particionar para acelerar algo que ya tarda tres décimas de
  milisegundo es empeorarlo.

Con una salvedad importante, y es la que le interesa al dueño del proyecto:
**para importar y para archivar el histórico por año, la partición es 300 veces
mejor.** Pero eso no obliga a particionar la tabla de producción. Importar por
año se resuelve cargando por lotes con un `WHERE` sobre el año, que es lo que
hace este mismo banco de pruebas. Y purgar historia es una operación que el
laboratorio hará, si acaso, una vez al año: 46 segundos una vez al año contra
2 a 4 veces peor en cada consulta de cada día es un mal negocio.

**Cuándo sí particionar**: cuando la purga o el archivado de años pase a ser
rutina —normativa de retención, por ejemplo—, o cuando la tabla crezca lo
suficiente como para que reindexar o hacer `VACUUM` sobre ella entera deje de
entrar en una ventana de mantenimiento. Ninguna de las dos condiciones se cumple
hoy, y las dos se pueden reconocer a tiempo: particionar más adelante es un
trabajo acotado, y hacerlo ahora es pagar el costo todos los días por un
beneficio anual.

---

## 9. Qué muestra el esquema del sistema viejo

Los números de arriba dicen qué pasa con cada forma bien indexada. Queda la
pregunta de qué le pasó al sistema anterior. El volcado del esquema está en
[`esquema/lab_app_development-estructura.sql`](esquema/lab_app_development-estructura.sql)
y responde solo:

| Tabla | Qué es | Índices que tenía |
|---|---|---|
| `lab_sub_details` | la forma **vertical** del sistema viejo | clave primaria, `lab_detail_id`, `lab_category_sub_detail_id` |
| `rem_report_details` | la forma **ancha**: **221 columnas** | **clave primaria, y nada más** |
| `labs` | la hoja, con `date_rehearsal` | clave primaria, `lab_category_detail_id`, `user_id` — **la fecha no está indexada** |
| `rem_correlatives` | el vínculo muestra ↔ transformador | **clave primaria, y nada más** — ni `transformer_id` ni `num_test` |
| `rems` | la remisión | **clave primaria, y nada más** |

Tres hechos, todos verificables en ese archivo:

1. **El valor medido se guardaba en `lab_sub_details.name varchar(255)`.** Texto.
   Ordenar o promediar exigía convertir en cada consulta, y en la base convivían
   `1.5E-03`, `<0.5`, vacíos y `NaN`.
2. **El vínculo era por texto**: `lab_details.num_test varchar(255)` contra
   `rem_correlatives.num_test int`. Una comparación `varchar = int` anula
   cualquier índice, y aquí ni siquiera había uno que anular.
3. **No existía ningún índice compuesto en toda la cadena.** Para armar la
   tendencia de un transformador había que recorrer entera `rem_correlatives`,
   cruzar por texto contra `lab_details` y recién ahí bajar a `lab_sub_details`.

Y el hecho decisivo: **la tabla ancha del sistema viejo, `rem_report_details`
con sus 221 columnas, tenía exactamente un índice: su clave primaria.** El
sistema viejo tenía las dos formas conviviendo y las dos estaban igual de mal
indexadas. Si la verticalidad hubiera sido la causa de la lentitud, la tabla
ancha tendría que haber andado bien. No hay ninguna razón para creer que anduvo
bien: con 221 columnas y un solo índice, cualquier consulta que no fuera por
`id` era un recorrido secuencial.

Esto no demuestra por sí solo la tesis del índice —para eso están las
mediciones—, pero cierra la puerta a la otra: **la forma ancha no es lo que
faltaba, porque ya estaba, y no alcanzó.**

---

## 10. Respuestas

### ¿A qué volumen deja de responder la forma vertical?

**Con los índices correctos, a ninguno de los medidos.** A 84 millones de filas
las consultas 1 y 2 tardan 0,44 y 0,32 ms, unas 450 veces por debajo del
presupuesto de 200 ms, y no se movieron entre 839 mil y 84 millones de filas.

**Con un índice equivocado, a los 8 millones de filas** (356 ms en la consulta
3, escala S2), y a los 84 millones es inutilizable (1.895 ms). Ese es el volumen
que hay que anotar, porque es el que se alcanza sin darse cuenta: 8 millones de
filas verticales son unas 400.000 muestras, que este laboratorio junta en cien
años pero un cliente grande junta en pocos.

El límite no lo pone el volumen. Lo pone el índice.

### ¿Cuánto más rápida es la forma ancha en cada consulta?

En la escala mayor, con las dos formas correctamente indexadas:

| | Ancha respecto de la vertical |
|---|---|
| 1 · informe de un equipo | **0,44×** (la ancha es 2,25 veces más *lenta*) |
| 2 · tendencia | 0,97× (empate) |
| 3 · tablero de flota | **1,10×** más rápida |
| 4 · transversal | **0,52×** (la ancha es 1,94 veces más *lenta*) |
| 3 global (todo el universo) | **1,38×** más rápida |
| Alta de una hoja de trabajo | **5,0×** más rápida |
| Espacio en disco | **7,0×** más chica |

La forma ancha gana en escritura, en disco y en los agregados de una sola
columna sobre todo el universo. Pierde en las dos consultas que arman informes,
que son las que corre el laboratorio todo el día.

### ¿Cuánto cuesta la consulta transversal en la forma ancha y cómo escala a la prueba 30?

6,8 ms con 30 pruebas dadas de alta, contra 0,62 ms de la vertical: **11 veces
más**. Escala de manera **lineal**: +0,22 ms por prueba nueva, más de la mitad
en planificación, y se paga aunque el equipo no tenga datos en la prueba nueva.
El texto SQL pasa de 2.233 a 9.547 caracteres.

### ¿La partición por año cambia el veredicto?

**No, y además hoy no conviene.** Con once particiones anuales sobre las mismas
84 millones de filas, las cuatro consultas empeoran: la 1 pasa de 0,44 a 1,60 ms
(3,7×), la 2 de 0,32 a 0,67 ms (2,1×), la 3 de 1,72 a 3,82 ms (2,2×) y la 4 de
0,57 a 1,36 ms (2,4×). El sobrecosto es de **planificación** —de 0,84 a 5,32 ms
en la consulta 1, trece veces más que su propia ejecución—, así que es fijo: no
lo baja ningún índice ni ninguna caché. Y ocupa 900 MB más teniendo un índice
menos, porque la clave primaria se ve obligada a incluir la fecha.

La excepción está en el mantenimiento, y es enorme: **archivar un año cuesta
154 ms con partición y 46 segundos sin ella** (300×), y el `DETACH` además
conserva el año como tabla aparte para poder guardarlo. Pero eso es una
operación anual, y la partición se paga en cada consulta de cada día. Importar
el histórico por año no necesita particiones: se hace por lotes con un `WHERE`
sobre el año, igual que este mismo banco de pruebas.

Conviene particionar el día que la purga o el archivado de años sea rutina, o
que `VACUUM`/`REINDEX` sobre la tabla entera deje de entrar en la ventana de
mantenimiento. Hoy no pasa ninguna de las dos, y particionar más adelante es un
trabajo acotado.

### Recomendación

**Vertical, con los índices compuestos que incluyen `tenant_id` y
`equipment_id`; lo que hundió al sistema viejo fue la falta de índice utilizable
y el vínculo por texto, no la verticalidad —y la propia tabla ancha de 221
columnas del sistema viejo, con un único índice, lo demuestra.**

Cambiaría de opinión si:

- **el disco pasara a ser el cuello de botella.** 15 GB contra 2 GB es 7×, y es
  el único número donde la forma ancha gana por un margen que no se discute. Si
  el laboratorio creciera cien veces y el disco fuera caro, la conversación
  cambia. Hoy, a escala real (S1), son 154 MB contra 22 MB;
- **la carga pasara a ser dominada por la escritura**, no por la consulta. La
  forma ancha escribe 5× más rápido. Con miles de altas por segundo eso importa;
  con 4.000 muestras al año, la diferencia anual son cuatro segundos;
- **apareciera una consulta analítica pesada sobre un solo parámetro de todo el
  universo** como caso de uso central y frecuente. Ahí la forma ancha gana 1,38×
  leyendo cinco veces menos páginas, y la ventaja crece con la cantidad de
  parámetros que la vertical mete en el mismo índice;
- **el laboratorio congelara su catálogo de ensayos.** El argumento más fuerte
  contra la forma ancha no es de rendimiento: es que cada prueba nueva agrega
  una rama al UNION del informe consolidado, y ese costo es permanente y lineal.
  Si las 29 pruebas fueran definitivas, ese argumento se cae.

**Sin particionar**, además. La partición por año cuesta entre 2 y 4 veces en
las cuatro consultas y no resuelve ningún problema que hoy exista. Se reabre el
día que archivar o purgar años sea rutina —ahí gana 300×— y no antes.

Lo que **no** haría en ningún caso es tomar la decisión sobre índices por la vía
de la forma de la tabla. Las dos formas responden en menos de dos milisegundos a
84 millones de filas cuando están bien indexadas, y las dos se caen cuando no lo
están: la vertical llegó a 1.895 ms y la ancha global a 1.807 ms, en el mismo
banco de pruebas y con los mismos datos, por la misma razón.

---

## 11. Tabla comparativa final

Escala S3 — 4.000.000 de muestras · 83.920.006 filas verticales · 11.879.997
filas anchas · caché caliente · las dos formas correctamente indexadas.

| Qué se mide | Vertical | Ancha | Gana |
|---|---|---|---|
| 1 · Informe de un equipo | **0,44 ms** | 0,98 ms | vertical, 2,25× |
| 2 · Tendencia de un parámetro | **0,32 ms** | 0,33 ms | empate |
| 3 · Tablero de flota (un espacio de trabajo) | 1,72 ms | **1,57 ms** | ancha, 1,10× |
| 3 · Tablero de flota (todo el universo) | 566 ms | **411 ms** | ancha, 1,38× |
| 4 · Informe consolidado, 4 pruebas | **0,57 ms** | 1,10 ms | vertical, 1,94× |
| 4 · Informe consolidado, 30 pruebas | **0,62 ms** | 6,82 ms | vertical, 11× |
| Alta de una hoja de trabajo | 1,01 ms | **0,20 ms** | ancha, 5,0× |
| Espacio en disco | 15,4 GB | **2,15 GB** | ancha, 7,0× |
| Consultas que rompen los 200 ms | ninguna | ninguna | — |
| Vertical particionada por año, consulta 1 | 1,60 ms | — | sin partición, 3,7× |
| Vertical particionada por año, consulta 2 | 0,67 ms | — | sin partición, 2,1× |
| Archivar un año (8,4 M de filas) | 154 ms con partición · 46.146 ms sin ella | — | con partición, 300× |
| Costo de dar de alta la prueba 30 | una fila en un catálogo | migración, modelo, formulario, +0,22 ms en cada informe | vertical |
| Peor caso medido con un índice equivocado | 1.895 ms | 1.807 ms | ninguna |
