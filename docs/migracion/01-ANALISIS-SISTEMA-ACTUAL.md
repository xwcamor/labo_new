# Análisis del sistema actual (Ruby on Rails, 2019-2023)

> Inventario de lo que existe hoy en `labo_old`, qué hace bien, qué está roto y
> por qué. Es la base sobre la que se apoya el plan de migración.
> Todo lo de este documento salió de leer el código, no de suposiciones.

---

## 1. Ficha técnica

| Aspecto | Valor |
|---|---|
| Framework | Rails 7.0, Ruby 3.1.0 |
| Base de datos | MySQL (`mysql2` 0.5.2), `utf8mb4_spanish_ci` |
| Segunda BD | `primary2` → `tr_app_development` (la base de TRAFODEX) |
| Vistas | ERB + Bootstrap 5 + jQuery, sin framework de front |
| PDF | wicked_pdf / wkhtmltopdf |
| Excel | axlsx + roo (import) |
| Búsqueda | ransack |
| Auditoría | gema `audited` |
| Autenticación | propia (`hashed_password` + `salt`), con `real_password` en texto plano |
| Autorización | `user_permission.include?(N)` — números mágicos en cada acción |
| Modelos | 59 |
| Controladores | 78 (8.726 líneas) |
| Vistas | ~46.000 líneas de ERB |

### Volumen por módulo (líneas)

```
rem_report.rb            777   ← generación de valores de orientación (mandrakeado)
rem_report_detail.rb     710   ← lo mismo, duplicado
rem.rb                   388
rems_controller.rb       386
rem_reports_controller   315
_form_add_details_*.erb  672 + 571 + 457   ← lógica de negocio dentro de la vista
```

---

## 2. Los ocho módulos funcionales

Los namespaces de `config/routes.rb` no coinciden con los módulos de negocio.
Traducción:

| Namespace | Qué es realmente |
|---|---|
| `im_management` | Recepción de muestras (REM), equipos, clientes, informes, catálogos |
| `pr_management` | Bancada: plantillas de ensayo, hojas de trabajo, patrones, tendencias |
| `conditions_management` | Edición manual de los "valores de orientación" de un informe |
| `trapp_management` | Envío de resultados a TRAFODEX (escritura directa en su BD) |
| `json_management` | Lo mismo que el anterior, en versión JSON/AJAX |
| `report_management` | Indicadores: OTD, trabajos, laboratorio, entregas, FIM |
| `stock_management` | Almacén de reactivos y materiales |
| `user_management` | Usuarios, perfiles, accesos |

### 2.1 Recepción de muestras — el flujo real

```
Rem (recepción)
  ├─ customer, sampler, date_received, date_deliver
  ├─ qty_num_pack (envases), qty_num_test (muestras)
  ├─ checks de conformidad: ea_val (envase), va_val (volumen), dc_val (tarjeta)
  ├─ is_urgent, state, validity, correlative_confirmed
  │
  └─ RemCorrelative × qty_num_test        ← una muestra física, código "2026-0744"
       ├─ year_test + num_test  (correlativo anual)
       ├─ transformer_id        (el equipo del que se tomó)
       ├─ pending_tr / pending_tk / pending_va  (semáforos de pendientes)
       │
       ├─ RemJob × N            ← una tarea por prueba solicitada
       │    ├─ lab_category_detail_id (qué prueba)
       │    ├─ task_done, state
       │    └─ lab_detail_id   (fila de la hoja de trabajo donde se ejecutó)
       │
       └─ RemReport             ← el informe emitido, "REP-LAB-2026-0001"
            ├─ 15 belongs_to con datos del equipo COPIADOS (marca, tipo, aceite, tensión…)
            ├─ date_rec / date_emi / date_ent / date_pue / date_mue
            ├─ type_report (0 principal / 1 adicional), state, rem_signature_id
            └─ RemReportDetail  ← LA TABLA DE ~250 COLUMNAS
```

**Problema estructural 1.** `RemCorrelative#generate_rem_jobs` crea un `RemJob`
por **cada** `LabCategoryDetail` existente, sin importar qué se pidió.
El filtro por prueba solicitada quedó comentado en el código. Resultado: cada
muestra arrastra 26 tareas, la mayoría vacías.

**Problema estructural 2.** El vínculo entre la hoja de trabajo y la muestra no
es una FK. Se hace comparando cadenas y con SQL interpolado a mano
(`LabDetail#update_rem_jobs`):

```ruby
@num_year = @lab_detail.first.num_test.split('-')[0].strip
@num_test = @lab_detail.first.num_test.split('-').last.strip
@rem_job = RemJob.where("... AND num_test = '#{@num_test}' AND year_test = '#{@num_year}' ")
```

Si el analista escribe `2026 - 744` en vez de `2026-0744`, el resultado nunca
se enlaza al informe y la celda sale vacía sin aviso.

### 2.2 Bancada — la parte bien diseñada

Esta es la única parte del sistema viejo que **sí** está modelada como datos y
que hay que conservar conceptualmente:

```
LabCategoryDetailType   (Físico Químico / Cromatografía / Otros)
  └─ LabCategoryDetail  (la prueba: "Número Ácido", "Rigidez Dieléctrica", …)
       ├─ num_pos, has_patron
       ├─ is_blur + blur_calculation   ← la fórmula de cálculo
       └─ LabCategorySubDetail  (las columnas de la hoja de trabajo)
            ├─ num_pos, is_required, is_blocked, is_reuse, reuse_value
            ├─ lab_category_sub_detail_type_id  (1=texto, 2=número, 3=opciones)
            └─ LabCategorySubDetailOption  (+ applicability_flag = acreditación)

Lab           (hoja de trabajo de un día: prueba + fecha + analista + estado)
  └─ LabDetail       (una fila: patrón / muestra / duplicado)
       └─ LabSubDetail  (el valor de cada columna)   ← EAV
```

#### Las 29 pruebas reales

Salen del volcado con datos
([`esquema/catalogos-definiciones.sql`](esquema/catalogos-definiciones.sql)),
no del seed. El seed decía 26 y se equivocaba en el número **y** en los nombres:

| id | Prueba | | id | Prueba |
|---|---|---|---|---|
| 1 | Número Ácido | | 16 | Grado de Polimerización |
| 2 | Factor De Potencia 25º | | 17 | Viscocidad |
| 3 | Factor De Potencia 100º | | 18 | Partículas |
| 4 | Rigidez Dieléctrica | | 19 | Metales en Aceite |
| 5 | Tensión Interfacial | | 20 | Inhibidor |
| 6 | Contenido de Agua | | 21 | DBDS |
| 7 | Color | | 22 | Sedimentos |
| 8 | Condición Visual | | 23 | Fluidez |
| 9 | Densidad Relativa | | 24 | Inflamación |
| 10 | Análisis Cromatográfico | | 25 | Pasivador |
| 11 | PCB | | 26 | Factor De Potencia 90º |
| 12 | Furanos | | **27** | **Rigidez Dieléctrica Electrodos planos** |
| 13 | Azufre 1275B | | **28** | **Resistividad Volumétrica 25º** |
| 14 | Azufre 62535 (48 horas) | | **29** | **Resistividad Volumétrica 100º** |
| 15 | Azufre 62535 (72 horas) | | | |

Las tres en negrita **no existen en el seed**. Y varios nombres cambiaron:
`Azufre 1/2/3` son en realidad `Azufre 1275B` y `Azufre 62535` a 48 y 72 horas
(el método y el tiempo de ensayo, no una numeración); `Mteales` —con el error de
tipeo— es `Metales en Aceite`; `Agua` es `Contenido de Agua`.

Con esas 29 se definen **208 columnas** de hoja de trabajo
(`lab_category_sub_details`) y **93 opciones** de selección
(`lab_category_sub_detail_options`).

> El volcado publicado está **filtrado a propósito**: solo definiciones. Las
> tablas con nombres del personal (`samplers`, `rem_user_signatures`) y con
> equipos y muestras de clientes quedaron afuera, porque el repo es público.

La estructura sí es correcta: el laboratorio agrega pruebas y columnas sin
tocar código.

**Pero** `blur_calculation` guarda **JavaScript crudo** en la base:

```js
var col5 = parseFloat(document.getElementById('col5').value);
var col8 = parseFloat(document.getElementById('col8').value);
var result = (col8-col6)*col5/col7;
document.getElementById('col9').value = result.toFixed(3);
```

Consecuencias: el cálculo solo existe en el navegador (el servidor no puede
recalcular ni validar), depende de los `id` del DOM (cambiar el orden de una
columna rompe la fórmula en silencio), y es una vía de inyección de script.

### 2.3 Control de calidad analítica

`LabDetail.lab_detail_type_id` distingue **patrón (1) / muestra (2) / duplicado (3)**.
`PatronTendence` guarda los límites de la carta de control y
`templates/tendences` dibuja las tendencias con amCharts — hay 10 parciales
casi idénticos (`_amcharts_hid`, `_amcharts_oxi`, `_amcharts_nit`, …), uno por
gas, de ~405 líneas cada uno. Y los IDs de columna están clavados en el
controlador:

```ruby
@lab_sub_detail_hid = LabSubDetail...where("lab_sub_details.lab_category_sub_detail_id = 61 ...")
@lab_sub_detail_oxi = ...id = 62...
```

Es funcionalidad valiosa (es lo que sostiene la acreditación ISO 17025), mal
implementada.

### 2.4 Importación de archivos de instrumento

`LabFile` + `TxtDataUploader` + `LabFileDetail`: se sube el `.txt` que emite el
cromatógrafo, se guarda el contenido completo en una columna `description` y
después se parsea. En `1.txt_examples/HITACHI/` están los formatos reales
(DPA 75C, DTL C, Cromas, Furanos). El post-proceso también está clavado:

```ruby
# DONT MOVE FURANOS COLUMN ORDER
# lab_category_sub_detail_id = 80,81,82,83,84 IDS FOR FURANO COLUMNS
"update lab_file_details SET name = substring_index(name,' ',1) WHERE lab_category_sub_detail_id IN (80,81,82,83,84)"
```

### 2.5 Almacén, etiquetas e indicadores

- `Stock` → `StockDetail` → `StockDetailMove` → `StockDetailReturn`: préstamo y
  devolución de reactivos/material. Simple y correcto; se porta casi tal cual.
- `Sticker`: etiquetas con QR (`rqrcode`) para los envases.
- `report_management`: OTD (`date_rec` → `date_ent`, aceptable ≤ 5 días),
  tiempo de emisión (≤ 2), tiempo de entrega (≤ 3), trabajos, entregas.
  Los umbrales están como constantes en `RemReport`:
  `ACCEPTABLE_OTD_DAYS = 5`. Exporta a XLS con parciales ERB de 250 líneas.

---

## 3. Los cuatro problemas de fondo

### 3.1 La tabla de 221 columnas

> Cifras verificadas contra el volcado de estructura de producción
> (`esquema/lab_app_development-estructura.sql`), no estimadas.

`rem_report_details` tiene exactamente **221 columnas**, repartidas así:

| Sufijo | Cantidad | Qué guarda |
|---|---|---|
| `_val` | 66 | el valor medido |
| `_ori` | 37 | el valor de orientación (texto: `"0.20 - máximo"`) |
| `_display` | 29 | si el parámetro se muestra en el informe |
| `_lab_detail_id` | 29 | FK a la fila de la hoja de trabajo |
| `_date` | 15 | fecha del ensayo |
| `_comment` | 15 | observación |
| `_norm_id` | 6 | norma de aceptación (`fiq`, `cro`, `pcb`, `dbd`, `inh`, `pol`) |
| resto | 24 | `id`, `rem_report_id`, 6 columnas de condiciones de laboratorio, `fiq_item1..13`, `deleted`, timestamps |

**El dato que más dice es la asimetría: 66 valores contra 37 orientaciones.**
Hay 29 mediciones que la tabla puede almacenar y **nunca** puede evaluar,
porque nadie agregó la columna de límite correspondiente. No es que estén
"dentro de norma": es que no hay norma contra la que compararlas, y el informe
las imprime sin veredicto. La tabla ya no da abasto consigo misma.

El patrón por parámetro es:

```
{p}_val        valor medido
{p}_ori        valor de orientación (texto: "0.20 - máximo")
{p}_display    si se muestra en el informe
{p}_lab_detail_id  FK a la fila de la hoja de trabajo
{p}_comment / {p}_date / {p}_norm_id
```

Prefijos detectados: `aci f25 f90 f100 rig rigep ten agu col con den r25 r100
cro cro2..cro11 pcb pcb2..pcb4 fur fur2..fur6 azu azu48 azu72 pol vis par
par2..par8 met met2..met8 inh dbd sed flu inf pas` más `fiq_item1..fiq_item13`.

Agregar un parámetro nuevo hoy significa: migración con 5-7 columnas nuevas,
tocar el modelo, los dos callbacks de orientación, la vista del formulario
(672 líneas), la vista del informe (377 líneas), la del PDF y los exports.

### 3.2 Las normas y los límites están clavados en el código

Dos lugares, con el mismo contenido duplicado:

- `RemReportDetail#set_orientation_fiqui_values` / `set_orientation_croma_values`
  (`after_create`)
- `RemReport#refresh_orientation_fiqui_values` / `refresh_orientation_croma_values`
  (`after_update`)

Son ~1.100 líneas de `if/elsif` anidados por `oil_type_id`, `transformer_type_id`
y tensión, que escriben cadenas de texto en la base:

```ruby
if @transformer_oil_type_id.to_i == 1
  if @num_ten <= 69
    RemReportDetail.update(self.id, aci_ori: "0.20 - máximo", rig_ori: "40.0 - mínimo", …)
  elsif @num_ten > 69 && @num_ten < 230
    RemReportDetail.update(self.id, aci_ori: "0.15 - máximo", rig_ori: "47.0 - mínimo", …)
```

Y la norma se asigna por número de aceite:

```ruby
if oil_type_id == 1 or oil_type_id == 2 or oil_type_id == 3 or oil_type_id == 8
  fiq_norm_id: 1   # IEEE C57.106-2015
  cro_norm_id: 2   # IEC 60599-2022
```

Cuatro consecuencias:

1. **Actualizar una norma exige un despliegue.** Cuando IEEE publique
   C57.106-2019, hay que editar ~40 bloques de código a mano.
2. **Los informes viejos se reescriben.** `after_update` recalcula las
   orientaciones cada vez que se guarda el informe. Un informe emitido en 2023
   bajo la edición 2015 pasa a mostrar los valores de hoy. Para un laboratorio
   acreditado, eso es un hallazgo de auditoría.
3. **El aceite fuera de la lista se cae al `else`** y todo el cuadro queda en
   `"-"`, sin aviso: parece que "no aplica" cuando en realidad "no está cargado".
   Los comentarios del propio código lo delatan:
   `# CONDITIONS FOR OTHERS CUIDADOOO COMENTAR DE NUEVO EN ERRROR`.
4. **La norma que registra el analista se ignora.** Ver 3.3.

### 3.3 La norma del ensayo y la norma de aceptación están desconectadas

Este es el punto que el usuario señaló y es real. Hay **dos** normas distintas
y el sistema solo respeta una:

- **Norma de método** — cómo se midió. La captura el analista en la hoja de
  trabajo: cada plantilla tiene un campo `Norma` en `num_pos: 2`
  (`LabCategorySubDetail`), con opciones (`ASTM D877`, `ASTM D1816`, …) y su
  bandera de acreditación (`applicability_flag`). Se lee con
  `LabDetail#norma_y_flag` y **sí** se imprime en el PDF (`_report_physicals.erb`).
- **Norma de aceptación** — contra qué se juzga. Es `rem_report_details.fiq_norm_id`,
  que se asigna **por `oil_type_id` clavado en el modelo**, sin mirar en ningún
  momento lo que el analista registró.

#### El intento que quedó a medias: `rem_conditions`

En la base hay una tabla `rem_conditions` que **no aparece en las migraciones,
no tiene modelo, no la referencia ninguna línea de código y tiene 0 filas**:

```sql
CREATE TABLE `rem_conditions` (
  `id` int NOT NULL,
  `transformer_oil_type`  int,
  `lab_category_details`  int,
  `name`                  varchar(255),
  `cond_value`            varchar(255),
  `deleted`               int, ...
```

Fue el intento correcto de sacar los límites del código a datos, y se abandonó.
Se ve por qué: le faltan las dimensiones que el problema realmente tiene.

| Dimensión que el límite necesita | ¿está en `rem_conditions`? |
|---|---|
| tipo de aceite | sí (`transformer_oil_type`) |
| prueba | sí (`lab_category_details`) |
| **tipo de equipo** | no |
| **banda de tensión** | no |
| **norma de aceptación y su edición** | no |
| **vigencia (desde / hasta)** | no |
| **parámetro** (una prueba puede medir varios) | no |
| valor numérico + operador | no: `cond_value` es `varchar` |

Con solo (aceite, prueba) es imposible expresar "mineral entre 69 y 230 kV",
que es justamente el caso más común. Como la tabla no alcanzaba, el código
siguió con los `if/elsif` y la tabla se quedó vacía. **La idea estaba bien; la
tabla era demasiado chica para el problema.** `spec_sets` + `spec_limits`
(ver `03-NORMAS-Y-LIMITES.md`) es esa misma tabla con las siete dimensiones que
le faltaban.

#### Y el mecanismo para llevar los datos del ensayo al informe existe, pero nadie lo lee

`lab_category_sub_details.report_use` es una bandera para marcar "este campo de
la prueba va al informe". Se puede editar en cinco pantallas de configuración.
**Ningún informe la consulta**: las únicas referencias en el código son los
formularios que la editan y un helper que la traduce a "Sí"/"No".

Por eso el informe imprime lo que hay en las columnas fijas de
`rem_report_details` y no lo que el analista cargó en la hoja de trabajo.

#### Los dos informes se comportan distinto

- **Fisicoquímico** (`_report_physicals.erb`): sí imprime la norma de método
  por parámetro, con `norma_y_flag` leyendo el campo `Norma` que cargó el
  analista.
- **Cromatografía** (`_report_cromas.erb`): imprime **solo**
  `rem_report_detail.cro_norm.name`, la norma de aceptación asignada por
  `oil_type_id` clavado. Nunca toca lo que registró el analista.

O sea: en cromatografía la norma de la prueba efectivamente **no sale en el
informe**. En fisicoquímico sale, pero no se usa para evaluar. Son dos
comportamientos distintos para el mismo concepto, en el mismo PDF.

Por eso hoy puede salir un informe que dice "método ASTM D877" en la columna de
norma y al lado un valor de orientación de `45.0 - mínimo` que corresponde a
D1816. Son separaciones de electrodos distintas (2.54 mm fijo en D877 contra
1.0 o 2.0 mm en D1816) y los kV no son comparables. El mismo problema existe en
factor de potencia: `f25`, `f90` y `f100` se tratan como tres parámetros
independientes cuando son **el mismo parámetro medido a tres temperaturas**.

### 3.4 Lógica de negocio dentro de las vistas

`_form_add_details_physicals.html.erb` (672 líneas) hace, dentro del ERB:

- Busca la última columna de la plantilla:
  `LabCategorySubDetail.where("lab_category_detail_id= 1").order("num_pos ASC").last`
  — el ID de la prueba está clavado en la vista.
- **Escribe en la base desde la vista**:
  `<% RemReportDetail.update(@rem_report_detail.id, aci_val: @lab_sub_detail.name) %>`
- Parsea el límite quitando palabras de un string:
  `(@rem_report_detail.aci_ori.strip.delete! "(máximo)").to_f`
- Decide si está fuera de norma y marca `@aci_error = 1`.

Después, `_form_add_details_physicals_default_values.html.erb` (214 líneas) usa
esas variables para armar el texto de "Análisis de Resultado" con ERB anidado
por aceite × tipo × cantidad de parámetros fuera, repitiendo la misma frase
cinco veces con la norma cambiada a mano:

```erb
<% if @aceite == 1 %> … "Norma IEEE C57.106-2015" …
<% elsif @aceite == 4 %> … "Norma IEEE C57.111-1989(R2009)" …
```

Efecto lateral serio: como el guardado ocurre al **renderizar**, abrir la
pantalla modifica datos. Un GET muta el informe.

---

## 4. La integración actual: 14 tablas compartidas

No hay API. `labo_old` declara **dos bases** en `config/database.yml`
(`primary` = `lab_app_development`, `primary2` = `tr_app_development`, en el
mismo servidor MySQL) y usa la segunda como si fuera propia.

Hay **dos mecanismos** distintos, y el segundo es el que se pasa por alto:

**a) Conexión explícita** — cinco modelos con `establish_connection` y
`table_name` calificado:

```ruby
class ChromatographicalTrapp < ActiveRecord::Base
  establish_connection(:primary2)
  self.table_name = 'tr_app_development.chromatographicals'
end
```

**b) Herencia de `Primary2`** — diez modelos más, sin ninguna marca visible:

```ruby
class Primary2 < ActiveRecord::Base
  self.abstract_class = true
  connects_to database: { writing: :primary2, reading: :primary2 }
end

class Customer  < Primary2   # ← parece un modelo normal del laboratorio
class OilType   < Primary2   #   y NO lo es: vive en la otra base
class Mark      < Primary2
```

### 4.1 Inventario completo

| Tabla en `tr_app_development` | Modelo(s) en el labo | Qué es |
|---|---|---|
| `customers` | `Customer`, `CustomerTrapp` | **clientes del laboratorio** |
| `customer_locations` | `CustomerLocation` | sedes |
| `customer_areas` | `CustomerArea` | áreas |
| `customer_substations` | `CustomerSubstation` | subestaciones |
| `countries` | `Country` | países |
| `oil_types` | `OilType` | tipos de aceite |
| `marks` | `Mark` | marcas de equipo |
| `conmutation_types` | `ConmutationType` | tipos de conmutador |
| `transformers` | `TransformerTrapp` | equipos (destino del envío) |
| `chromatographicals` | `ChromatographicalTrapp` | muestras de cromatografía |
| `chromatographical_duvals` | `ChromatographicalDuval` | resultados de Duval |
| `chromatographical_dga_diags` | `ChromatographicalDgaDiag` | diagnóstico DGA |
| `physicals` | `PhysicalTrapp` | muestras de fisicoquímico |
| `furanos` | `FuranoTrapp` | muestras de furanos |

Además hay consultas SQL que cruzan las dos bases en una sola sentencia
(hoy comentadas, pero el patrón existe):

```sql
SELECT id,name FROM tr_app_development.customers
WHERE id IN (SELECT DISTINCT customer_id FROM lab_app_development.rems ...)
```

**La consecuencia más fuerte: el laboratorio no tiene clientes propios.**
`Customer < Primary2` significa que el módulo de clientes del labo lee y
escribe la tabla `customers` de la otra aplicación. Lo mismo con países,
aceites, marcas y tipos de conmutador. Si esa base desaparece, el laboratorio
se queda sin catálogos y sin clientes: no arranca.

### 4.2 `tr_app_development` es el sistema Rails VIEJO, no TrafoDex

Esto es lo que convierte el asunto en bloqueante.

La base a la que apunta `primary2` es la del **TRAPP original en Ruby**
(github.com/xwcamor/trapp), no la de TrafoDex en Laravel. La evidencia es
inequívoca:

| | `tr_app_development` (a la que escribe el labo) | TrafoDex (Laravel) |
|---|---|---|
| Motor | MySQL | PostgreSQL 16 |
| Fisicoquímico | tabla `physicals` | tabla `fiquis` |
| Gases | `num_hid`, `num_oxi`, `num_nit`, `num_met`… | `h2`, `o2`, `n2`, `ch4`… |
| Fecha de muestra | `date_rehearsal` | `sample_date` |
| Borrado | `deleted` (0/1) | `deleted_at` (SoftDeletes) |
| Clientes | `num_doc`, `deleted` | `slug`, `cod`, `is_active`, `tenant_id` |

TrafoDex ya migró esos datos a su propio esquema con los `Legacy*Seeder` y sus
volcados `*_legacy.sql`.

**El día que TrafoDex reemplace al TRAPP viejo en producción, el laboratorio
deja de funcionar.** No es que la integración quede vieja: se queda sin
clientes, sin países, sin tipos de aceite y sin marcas, porque nunca los tuvo.

Por eso la migración del laboratorio no es solo una modernización: es lo que
desacopla las dos aplicaciones antes de que ese corte ocurra.

El envío (`TrappManagement::ImportCromasController#step4`) es un asistente de
4 pasos que:

1. Lista informes cuyo equipo exista en TRAFODEX, emparejando **por número de
   serie en texto**: `TransformerTrapp.find_by(deleted: 0, num_serie: ...)`.
2. En el paso 4 recorre el resultado e inserta fila por fila.

Riesgos concretos:

- **Sin idempotencia**: volver a ejecutar el paso 4 duplica las muestras.
- **Sin transacción ni control de error**: `.save` sin verificar el retorno.
- **`find_by` devuelve el primero**: si hay dos equipos con el mismo número de
  serie, el resultado se carga en el equipo equivocado.
- **Acoplamiento de esquema**: cualquier `ALTER TABLE` en TRAFODEX rompe el
  laboratorio. El nombre de base `tr_app_development` está escrito en el código.
- **Reglas de negocio salteadas**: al insertar por SQL no corre el motor de
  diagnóstico de TRAFODEX; el índice de salud queda desactualizado hasta que
  alguien recalcule a mano.
- **No se envía el PDF**. Hoy el informe firmado no llega a TRAFODEX por
  ningún canal.

Se envían cromatografía, fisicoquímico, furanos y equipos. El fisicoquímico y
furanos siguen exactamente el mismo patrón.

Y hay una pérdida de información en el envío. `TransformerType` mapea los 20
tipos de equipo del laboratorio a los 3 que entiende el sistema de destino:

```ruby
def str_transformer_type_id_trapp
  return "1" if id == 1     # Potencia
  return "2" if id == 2     # Distribución
  return "3" if id == 3     # Horno
  return "1" if id > 3      # ← TODO lo demás se manda como POTENCIA
end
```

Un bushing, un cable, un interruptor y un reactor llegan al otro sistema
etiquetados como transformadores de potencia, y ahí se los diagnostica con los
umbrales de potencia. No hay error ni aviso: hay un diagnóstico equivocado.

---

## 5. Otras deudas relevantes

| Deuda | Detalle |
|---|---|
| Contraseñas en claro | `users.real_password` guarda la contraseña sin cifrar |
| Autorización por número | `user_permission.include?(61)`, `User.authentication(session[:user_id], 42)` repetido en cada acción, sin catálogo |
| `deleted = 0/1` a mano | Sin `paranoia`; cada consulta tiene que acordarse de filtrar |
| SQL interpolado | `where("... = #{self.lab.lab_category_detail_id}")` en modelos |
| Esquema incompleto | `db/schema.rb` solo declara 18 tablas de las ~50 reales. `rems`, `rem_reports`, `rem_report_details`, `transformers`, `stocks` y todos los catálogos se crearon **fuera de las migraciones** |
| Sin pruebas | `test/` está vacío |
| **`db/seeds.rb` no corre Y ADEMÁS es obsoleto** | escribe `is_blur` y `has_patron` en `lab_category_details`, columnas que **no existen** en la tabla real → `rails db:seed` revienta con `unknown attribute`. E ignora cinco que sí existen: `container`, `is_grouped`, `description`, `unit_name_amchart`, `has_reuse`. El dueño confirmó que eran datos iniciales de prueba y que todo cambió después: **no usarlo como fuente de nada** |
| **`db/migrate/` tampoco sirve** | las tablas se crearon con SQL directo contra la base, no con migraciones. Las 30 migraciones que hay cubren 18 de 47 tablas y describen un esquema anterior |
| Multi-idioma | No existe; todo el texto está en español dentro de las vistas |
| Multi-empresa | No existe; una instalación = un laboratorio |

### 5.1 Fuentes de verdad, en orden

Como nada se creó por migraciones, hay tres candidatos a "fuente" y solo uno
sirve para cada cosa:

| Para saber… | Fuente | Por qué |
|---|---|---|
| qué tablas y columnas hay | **el volcado de estructura** (`esquema/lab_app_development-estructura.sql`, en ESTE repo) | es la base real de producción. `db/schema.rb`, `db/migrate/` y `db/seeds.rb` de `labo_old` describen un esquema anterior y NO se consultan |
| qué pruebas y plantillas existen | **la base, con datos** | el seed está desactualizado años (ver arriba); pendiente de conseguir |
| los 24 cuadros de límites | **el código Ruby** (`RemReport`, `RemReportDetail`) | nunca fueron datos: la tabla `rem_conditions` quedó vacía |
| qué límites se aplicaron de verdad | **`rem_report_details.*_ori` de los informes emitidos** | son la copia congelada de lo que salió impreso; sirven para validar lo extraído del código |

**Ni `db/seeds.rb` ni `db/migrate/` de `labo_old` se usan como fuente.** El
dueño creó las tablas con SQL directo contra la base y los seeds fueron datos
iniciales de prueba que quedaron atrás. Sirven, como mucho, para entender la
evolución del esquema (`is_blur` se mudó a `lab_category_sub_details`,
`has_patron` se convirtió en `has_reuse`) — nada más.

**El volcado de estructura vive en este repo** (`esquema/`), justamente para no
tener que abrir `labo_old` a buscar nada.

---

## 6. Qué se conserva y qué se descarta

### Se conserva (el conocimiento del negocio)

- El flujo REM → correlativo → tareas → hoja de trabajo → informe. Es correcto.
- El modelo de plantillas de ensayo (prueba → columnas → opciones).
- La distinción patrón / muestra / duplicado y las cartas de control.
- Las tablas de valores de orientación: son datos reales del laboratorio, hay
  que **extraerlos del código y cargarlos como datos**, no reinventarlos.
- Los formatos de los archivos de instrumento (`1.txt_examples/`).
- Los indicadores OTD y sus umbrales.
- La numeración `REP-LAB-{año}-{4 dígitos}` y `{año}-{4 dígitos}` de muestra.

### Se descarta

- La tabla ancha `rem_report_details`.
- Los cuatro métodos de orientación clavados (~1.100 líneas).
- La generación de narrativa con ERB anidado.
- La escritura directa en la base de TRAFODEX.
- El JavaScript de cálculo guardado en base.
- La autenticación y autorización propias.
