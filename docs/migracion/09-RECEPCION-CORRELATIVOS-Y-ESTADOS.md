# Recepción de muestras, correlativos y estados

> Este documento responde cuatro preguntas del laboratorio, en este orden:
>
> 1. ¿Está mal crear una tabla por prueba?
> 2. ¿Por qué usar el sistema nuevo y no el anterior?
> 3. ¿Cómo debió modelarse la recepción de muestras (REM)?
> 4. ¿Está bien elegir el equipo en la fila de la bancada?
>
> Todo lo que se afirma acá sale del código y del esquema del sistema anterior,
> con la referencia al lado. Donde no se pudo verificar, se dice.

---

## 1. ¿Está mal crear una tabla por prueba?

No hace falta discutirlo: **el sistema anterior ya hizo las dos cosas**, y las
dos dejaron rastro.

### Una columna por prueba — ya existe, y ya se rompió

La tabla `rems` (la remisión) lleva quince contadores, uno por ensayo:

```
num_fiq  num_cro  num_pcb  num_fur  num_par  num_azu  num_sed  num_met
num_vis  num_dbd  num_inf  num_flu  num_inh  num_pol  num_pas
```

El laboratorio corre **29** pruebas. Rigidez Dieléctrica Electrodos Planos
(2023), Resistividad Volumétrica 25 °C y 100 °C (2024) y Factor de Potencia 90 °C
**no tienen columna**. Una remisión de hoy no puede registrar cuántos envases de
Resistividad recibió, porque agregar la prueba exigía un `ALTER TABLE` que nadie
corrió.

Ese es el costo de "una columna por prueba", medido en la propia base: **el
esquema se queda atrás del laboratorio**.

### Una tabla ancha por prueba — también existe

`rem_report_details` aplana unos treinta ensayos en columnas paralelas
(`aci_display`, `aci_lab_detail_id`, `aci_ori`, `aci_val`, `aci_date`,
`aci_comment`, y lo mismo para los otros veintiocho). Son **221 columnas**, y
tiene **un solo índice: la clave primaria**.

La forma horizontal ya estaba, y no hizo rápido a nada.

### Entonces, ¿qué lo hacía lento?

Tres cosas, y ninguna se arregla eligiendo la forma de la tabla:

1. **El valor era texto.** `lab_sub_details.name varchar(255) NOT NULL` recibía
   números, fechas y hasta el id de la opción elegida en un desplegable. Sobre
   una columna así ningún índice sirve para comparar, ordenar ni promediar.
2. **La fila de bancada no sabía de qué muestra era.** Ver §4.
3. **No había índices.** `lab_details` no tiene índice por número de muestra;
   `transformers`, con más de 2400 equipos, tiene solo la clave primaria.

### Lo que sí corresponde: la tabla ancha para LEER

El laboratorio exporta a Excel por módulo, y ese Excel **es** una tabla ancha:

| Fecha | Tipo | Nº de Muestra | Norma | H2 | O2 | N2 | … | Total |
|---|---|---|---|---|---|---|---|---|

Eso no obliga a guardar ancho. Se guarda **una vez**, tipado e indexado
(`results`), y se expone **una vista por prueba generada desde su propia
definición**:

```sql
SELECT * FROM v_analisis_cromatografico;
```

Devuelve exactamente esas columnas, para el export, para Excel y para cualquier
herramienta de tablero. Si el volumen lo pide, la misma generación produce una
vista materializada que se refresca al validar la hoja.

Ventaja sobre la tabla física: agregar una prueba **no toca el esquema**. Una
vista no guarda datos — se borra y se vuelve a crear sin perder nada, cosa que
un `ALTER TABLE` no permite. Y la aplicación nunca necesita permiso para
modificar sus propias tablas, que es el permiso que convierte un error en una
tabla perdida.

**Resumen honesto**: la tabla por prueba es correcta para LEER y equivocada para
GUARDAR. Se puede tener la primera sin pagar la segunda.

---

## 2. ¿Por qué el sistema nuevo y no el anterior?

Lista corta, todo verificable en el código anterior.

### La vista escribía en la base

Abrir un REM no era una lectura. El ERB ejecutaba escrituras:

```erb
<%# _form_show_main_info.html.erb:73 %>
<% Rem.update(@main_model.id, :series_done => 1 ) %>
```

Nueve `Rem.update` posibles en ese partial, dieciséis `update_all` más en los
tres partials de la lista de muestras, y unas cuarenta escrituras más en los
partials del informe.

Consecuencias, todas reales:

- Un `GET` no es idempotente: recargar cambia la base.
- **El estado depende de que alguien abra la pantalla.** Si nadie abre el REM,
  `series_done` y `jobs_done` quedan viejos y los filtros del índice mienten.
- Dos usuarios mirando el mismo REM se pisan.
- La bitácora de auditoría se llena de versiones que nadie provocó.
- En el informe, cada render que no encuentra un valor **inserta** una fila en
  `rem_report_detail_issues` y **manda un correo síncrono**. Recargar tres veces
  = tres filas y tres correos.

### El cálculo era JavaScript inyectado en la página

La fórmula vivía en `lab_category_details.blur_calculation` y direccionaba las
celdas por POSICIÓN (`document.getElementById('col9')`). El servidor no
recalculaba ni verificaba nada:

- Un envío directo escribía cualquier número en el resultado.
- Con un campo vacío quedaba el texto `NaN` guardado en la base. El sistema tenía
  un panel dedicado a cazar esos registros.
- La ayuda del propio sistema avisaba: *"si se cambia la posición de las columnas
  que se usen en cálculos se tiene que cambiar la fórmula"*, y
  `README_ADD_COLUMNS.md` lo formalizaba: *"OJO: LA COLUMNA RESULTADO SIEMPRE ES
  LA ULTIMA"*.

### El instrumento era el texto de una opción

"Bureta PP-LA-01C" con el código de calibración adentro del nombre, repetido en
cada prueba que lo usara, sin fecha de vencimiento. Sin esa fecha **no se puede
demostrar que el equipo estaba vigente el día del ensayo**, que es literalmente
lo que pide ISO 17025 para que un resultado sea trazable.

### Las reglas de control vivían en el HTML

El desplegable de tipo de fila solo ofrecía "Patrón" y "Duplicado" hasta que
hubiera uno de cada. Como la regla estaba en la vista, un envío directo cargaba
muestras sin ningún control. Y los duplicados, obligatorios, **no se comparaban
nunca**: el analista hacía el trabajo doble y el dato se perdía.

### Los límites de norma estaban duplicados y ya divergieron

El árbol de `if/elsif` que asigna la norma según tipo de transformador y tipo de
aceite está escrito **dos veces**, en `rem_report.rb` y en
`rem_report_detail.rb`. Y ya no coinciden: uno trata como vegetal los aceites
`5, 6, 9`; el otro solo el `5`. **Un informe de girasol nace sin norma
asignada.** Ése es el resultado inevitable de tener la regla en el código en vez
de en los datos.

### Y lo que el sistema anterior hacía bien

No todo estaba mal, y conviene decirlo porque el modelo correcto sale de ahí:
**la cadena recepción → muestra → prueba pedida ya existía y estaba bien
pensada.** Ver §3. Lo que falló fue la implementación, no la idea.

---

## 3. Cómo debió modelarse la recepción

### Lo que había

```
rems                  la remisión: cliente, fecha, muestreador, envases
 └─ rem_correlatives  LA MUESTRA: year_test + num_test → "2026-0695"
                      y acá vive transformer_id
     ├─ rem_jobs      qué prueba le toca a esa muestra, con su estado
     │   └─ lab_details        la fila de la hoja de trabajo
     └─ rem_reports            el informe emitido
```

La estructura es correcta. Los cuatro problemas están en el detalle.

### Problema 1 — el correlativo se generaba con "leer el último y sumar 1"

```ruby
# rem.rb:371-381
@num_correlatives.times do |i|
  @last_correlative = RemCorrelative.where("deleted = 0 AND year_test = ?", Date.today.year)
                                    .order(:num_test).last
  @max_value = @last_correlative.nil? ? 1 : @last_correlative.num_test.to_i + 1
  RemCorrelative.create(rem_id: self.id, year_test: Date.today.year, num_test: @max_value)
end
```

- Sin bloqueo ni transacción: dos recepciones confirmadas a la vez leen el mismo
  último número y emiten el mismo correlativo.
- La validación de unicidad **está comentada** (`rem_correlative.rb:22`), y
  aunque estuviera activa su alcance es `rem_id`, no `year_test`.
- El filtro `deleted = 0` **reutiliza el número de un correlativo dado de baja**:
  el siguiente lote vuelve a emitir un número ya usado, ahora para otra muestra.
- Vuelve a consultar la tabla **dentro del bucle**, una vez por muestra.

Que esto ocurrió en producción no es una hipótesis: el repositorio tiene dos
archivos dedicados a buscar duplicados —`README_FIND_DUPLICATED_NUM_TESTS.md` y
`README_FIND_DUPLICATED_REPORTS.md`—. El número de informe además se calcula con
**dos algoritmos distintos**: uno al mostrarlo en el formulario y otro al
grabarlo, y su unicidad tiene alcance por transformador, así que el mismo
`REP-LAB-2026-0123` puede quedar en dos equipos.

**Lo correcto**: una fila contadora por (workspace, año) que se bloquea dentro de
la transacción y entrega el bloque completo de una sola vez.

```sql
UPDATE sample_counters
   SET last_number = last_number + :cuantas
 WHERE tenant_id = :t AND year = :y
RETURNING last_number;
```

Eso da exactamente lo que el laboratorio pide —"registro lo que entra y digo
cuántos correlativos quiero"— sin recorrer nada y sin condición de carrera. Más
un índice único sobre `(tenant_id, year, number)` como última línea de defensa,
y **numeración sin reutilización**: un correlativo dado de baja queda anulado,
no vuelve a emitirse. En un laboratorio, un número de muestra reutilizado es un
resultado atribuido a la muestra equivocada.

### Problema 2 — los estados se recalculaban al leer

El estado de la remisión (`series_done`, `jobs_done`, `datas_done`,
`reports_done`) y el de cada muestra (`pending_tr`, `pending_tk`, `pending_va`)
se guardaban en columnas, pero **se recalculaban y reescribían desde la vista en
cada apertura**, con N+1 anidado y sin paginación.

Presupuesto medido para una remisión de 40 muestras:

| Origen | Consultas |
|---|---|
| Controlador (`show`) | ~8 |
| Cabecera (8 métodos de estado, invocados dos veces, + 4 `Rem.update` con sus callbacks) | ~20–30, **con escrituras** |
| Lista de muestras: 8 consultas + 1 `UPDATE` por muestra | **~320 + 40 escrituras** |

La pantalla de administración es peor: itera además por cada prueba pedida y por
cada una ejecuta un `JOIN` de tres tablas más un `update_all` → 40 muestras × 10
pruebas ≈ **400 `JOIN` + 400 `UPDATE`**, en un `GET`.

**Lo correcto**: el estado se escribe **cuando pasa lo que lo cambia**, no cuando
alguien mira.

| Evento | Qué cambia |
|---|---|
| Se confirma la recepción | las muestras se crean con sus pruebas pedidas, en `pendiente` |
| El analista carga la fila | esa prueba pasa a `en proceso` |
| El supervisor valida la hoja | sus pruebas pasan a `validado` y se materializan los resultados |
| Se emite el informe | pasan a `informado` |

Abrir el REM es entonces **una** consulta con `GROUP BY`, sin escribir nada.
La regla, en una frase: **derivar una vez al escribir y leer barato**, en vez de
recalcular en cada lectura.

### Problema 3 — la muestra y la hoja se unían por texto

Tres mecanismos distintos, ninguno con clave foránea:

1. **jQuery copiaba la primera celda** de la fila al campo `num_test`
   (`_form_new.html.erb:73-83`). Si el usuario pegaba el valor sin generar el
   evento de teclado, la copia quedaba vacía y el vínculo no existía.
2. El modelo **parseaba el string** para encontrar la muestra:
   ```ruby
   @num_year = ...num_test.split('-')[0]   # "2026"
   @num_test = ...num_test.split('-').last # "0695"
   RemJob.where("... num_test = '#{@num_test}' AND year_test = '#{@num_year}' ...")
   ```
   Un guion de más rompe el parseo; `'0695'` casa con `695` solo por la coerción
   implícita del motor; y el fragmento **se interpola crudo en SQL** — eso es una
   inyección, no un detalle de estilo.
3. Una vista buscaba el número de muestra **en el valor de cualquier celda** de
   la hoja, sin restringir a la columna que lo contiene, y acto seguido marcaba
   la prueba como hecha. Cualquier coincidencia produce un falso positivo.

El propio autor lo dejó anotado en `lab_detail.rb:86`:
*"No funciona si el usuario crea antes de que registre el ingreso de la muestra"*.

**Lo correcto**: la fila de bancada referencia la prueba pedida por clave
foránea. El número de muestra es una ETIQUETA que se muestra, no la forma de
encontrarla.

### Problema 4 — a cada muestra se le creaban las 29 pruebas

```ruby
# rem_correlative.rb:70-77
@list_tests = LabCategoryDetail.where("deleted=0").order("num_pos ASC")
@list_tests.each { |t| RemJob.create!(rem_correlative_id: self.id, lab_category_detail_id: t.id) }
```

Se creaba una fila por **cada prueba del catálogo** para cada muestra, y después
se marcaba a mano cuáles se pedían de verdad. Una remisión de 40 muestras
insertaba ~1160 filas de las que la mayoría no significaban nada — dentro de un
`PUT`, sin transacción envolvente.

Debajo quedaron **70 líneas comentadas** de la versión anterior, que derivaba las
pruebas de los contadores `num_fiq`, `num_cro`… con los ids de prueba clavados a
mano. Es decir: la declaración "a esta muestra le tocan estas pruebas" existió,
estaba clavada en el código, y se abandonó.

**Lo correcto**: se crean solo las pruebas que se piden. Y se piden por dato —
por perfil de análisis del cliente, o marcándolas en la recepción— no clavando
ids en el código ni creándolas todas por las dudas.

---

## 4. ¿Está bien elegir el equipo en la fila de la bancada?

**No. Es un parche, y hay que quitarlo antes de cargar datos reales.**

Hoy `worksheet_rows.equipment_id` se elige por fila. Eso sirvió para la
demostración, pero es una oportunidad de pegarle el ensayo al transformador
equivocado, y el analista de bancada no es quien tiene ese dato: lo tiene la
recepción.

El equipo cuelga de la **muestra**:

```
reception (REM)
 └─ sample            code "2026-0695" · equipment_id · sampled_at · envase
     └─ sample_test   qué prueba se pide · estado
         └─ worksheet_row     ← acá va sample_test_id, y NADA MÁS
```

La fila de bancada guarda `sample_test_id`. El equipo, el cliente y la fecha de
muestreo se leen a través de la muestra. Se elige **una vez**, en la recepción,
por quien recibió el envase.

El esquema nuevo ya tiene reservados `worksheet_rows.sample_id` y
`sample_test_id`; están en nulo porque la recepción todavía no está construida.

### Y sobre el patrón y el duplicado

En el sistema anterior el patrón y el duplicado son filas ordinarias de la hoja,
distinguidas por un entero (`lab_detail_type_id` 1/2/3). El "Nº de Muestra"
del patrón (`HITA-012`) es un **valor por defecto de texto libre** cargado
columna por columna en la pantalla de patrones. El lote del patrón no es una
entidad: es una cadena.

Eso importa porque el lote del patrón es lo que define los límites de la carta de
control. Si es texto libre, dos escrituras distintas del mismo lote son dos
lotes, y la carta se parte sin que nadie lo note. En el sistema nuevo el lote es
un campo de la carta (`qc_charts.control_lot`) con su período de vigencia, y el
patrón no lleva número de muestra porque no es la muestra de ningún cliente.

---

## 5. Los tipos: por qué todo era texto

El laboratorio lo señaló y tenía razón. La causa es la misma columna de siempre:

```sql
lab_sub_details.name  varchar(255) NOT NULL
```

Ahí caían números, fechas y el id de la opción elegida. Con todo guardado en un
`varchar`, declarar una columna como "texto" no costaba nada, y así quedaron
declaradas casi todas. El importador copió ese criterio en vez de preguntarse
qué **mide** cada columna.

Auditadas las 207 columnas contra el volcado y contra el código anterior:
**161 estaban bien, 46 mal**, en cuatro grupos.

| Grupo | Columnas | Causa |
|---|---|---|
| Fecha guardada como texto | 6 | el importador no mapeaba el tipo 4 |
| Clasificación de vocabulario cerrado como texto libre | 6 | el sistema anterior no tenía cómo declararlas |
| Resultado numérico como texto | 29 | ídem |
| Instrumento sin convertir | 5 | solo se convertían las que ya eran lista con códigos `PP-LA-…` |

### Las fechas — error propio, no heredado

El sistema anterior **sí** las declaraba fecha (tipo 4). El importador solo
mapeaba 1, 2 y 3, y las caía al `?? 'text'` en silencio. Importa: el ensayo
IEC 62535 es a 48 y a 72 horas, y sin fechas comparables no se puede demostrar
que la exposición duró lo que la norma exige.

### Las clasificaciones — texto libre donde hay vocabulario cerrado

El resultado de los tres ensayos de Azufre y la clasificación de la lámina de
cobre (ASTM D130) son clasificaciones. Con texto libre conviven "Corrosivo",
"corrosivo" y "CORROSIVO" en la misma columna y ningún filtro las agrupa.

Las listas salen de la hoja de diagnóstico del propio laboratorio. Nota fina que
el sistema anterior no distinguía: **a 72 horas el vocabulario es distinto**,
porque además del cobre se evalúa el depósito de sulfuro en el papel
("Negativo sin depósitos" / "Positivo sin depósitos" / "Positivo con depósitos").

### El cero que no es una medición

Veintidós columnas donde el 0 no es un valor sino el "no medido" del sistema
anterior, que obligaba a llenar la celda. Una rigidez de 0 kV no existe; un
factor de potencia de exactamente 0.000 % no es medible; y en Furanos la fórmula
del grado de polimerización hace `log10(2FAL/1000)`, que con cero da infinito.

No es una hipótesis: en TrafoDex, al anular esos ceros, aparecieron **626**
ensayos con solo la rigidez por el método alterno y **104** con solo el factor de
potencia a 100 °C, que hasta entonces contaban como mediciones de valor cero.

Y la lista **no es uniforme**: en los nueve gases de la cromatografía el cero sí
es real —un gas no detectado es 0 ppm—, y en sedimentos, metales y PCB también
puede serlo. Por eso va columna por columna y no como regla general.

### El rango existía y no lo leía nadie

`min_value` y `max_value` estaban en la definición de cada columna desde el
principio, y **ninguna parte del sistema los consultaba**. Se podía declarar que
la rigidez va de 0 a 80 kV y guardar 800. Ahora se aplican al guardar la fila, y
se agregó la cota inferior abierta (`min_exclusive`) que es lo que expresa "el
cero no cuenta".

### Lo que se perdía en cada importación

Tres cosas que el volcado declaraba de cada opción y el importador dejaba caer:

- **`applicability_flag`** — el indicador "A" de ensayo **acreditado**. Es lo que
  imprime el "(A) Acreditado" y la nota de la acreditación ISO/IEC 17025 en el
  informe. Son 10 opciones. Perderlo es perder una afirmación legal.
- **`is_hidden`** — dos opciones del tensiómetro que el laboratorio había
  retirado de la lista volvían a ofrecerse.
- **`deleted`** — ocho opciones dadas de baja se revivían en cada importación,
  entre ellas la errata `PP-LA-01C-100.` con el punto al final.

### Dos erratas de etiqueta que conviene mirar

`Znic (Zn)` por **Zinc**, y `Silicio (Sn)` por **Silicio (Si)**. La segunda es
peor de lo que parece: `Sn` es el estaño, que es la fila inmediatamente anterior
de la misma tabla — dos metales distintos con el mismo símbolo.

### Lo que queda esperando decisión del laboratorio

Anotado en `database/seeders/data/test_field_types.json`, sin tocar:

- **Condición Visual** — su vocabulario trae `Brillante y Claro` y `B Y C`, que
  son el mismo resultado escrito de dos formas, y `-`, que es "no aplica" y no un
  resultado. Unificarlos cambia lo que dicen los informes históricos.
- **Código ISO de partículas** — no es una lista cerrada sino tres escalas
  concatenadas (`20/19/17`). Lo correcto sería derivarlo de los conteos, pero eso
  necesita la tabla de escala de la norma, que el motor de fórmulas —aritmética
  pura— todavía no expresa.
- **Viscosidad** — es calculable (`tiempo × constante`, ASTM D445) y la hoja de
  diagnóstico del laboratorio lo tiene escrito así. Se deja como número hasta
  que confirmen, porque volverla calculada la vuelve de solo lectura.
- **PCB total** y **Color resultado** — parecen la suma y el promedio, pero no
  hay fórmula en el sistema anterior que lo respalde.
- **Azufre a 72 horas** — informa papel y lámina de cobre, y tiene una sola
  columna de resultado. Si informan las dos, falta una columna.
- **"Menor al límite de detección"** — en PCB y Metales el sistema anterior
  recibía `< 2` y lo limpiaba antes de convertir. La bancada nueva ya sabe
  guardarlo con su signo aparte; falta decidir cómo se informa.

---

## 6. Lo que no se pudo verificar

Se deja anotado para no dar por cierto lo que no lo está:

- **El DDL de las tablas `rem_*` no está en el repositorio del sistema anterior.**
  Se crearon a mano en la base. Los datos de esta nota sobre columnas e índices
  salen del volcado de estructura
  (`docs/migracion/esquema/lab_app_development-estructura.sql`), no del código.
- Si existen índices o restricciones de unicidad en
  `rem_correlatives (year_test, num_test)` no se puede afirmar desde el código;
  en el volcado de estructura esa tabla tiene **solo la clave primaria**.
- El literal `HITA-012` no aparece en el repositorio. Está documentado el
  mecanismo, no el valor.
