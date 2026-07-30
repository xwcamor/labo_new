# Auditoría M — Qué campos eran obligatorios en cada formulario

> **Alcance.** Qué exigía cada formulario del sistema Rails de 2019
> (`/home/user/labo_old`), con qué mecanismo, y qué exige hoy el equivalente del
> sistema nuevo (`/workspace/labo_new`). El disparador es concreto: el dueño
> reporta que el informe del sistema nuevo sale con catorce campos de cabecera
> impresos como raya (`—`), y la hipótesis a verificar era que en el sistema
> anterior esos campos eran obligatorios.
>
> **Método.** Se revisaron las **cinco** fuentes de obligatoriedad del sistema
> viejo (la vista, el modelo, el controlador, la base y —la que no estaba en el
> encargo pero es la que más pesaba— el comportamiento de `belongs_to` en
> producción). Del lado nuevo: los `FormRequest` de Equipos, Clientes y
> Muestreadores, las reglas en línea de `ReceptionController`,
> `SampleReportController` y `WorksheetController`, el servicio que reparte la
> cabecera del informe, y la plantilla del informe.
>
> **El sistema viejo no se modificó.** Este documento es el único archivo escrito.
>
> **Sobre los conteos.** Los porcentajes salen del volcado de datos reales que el
> dueño facilitó (`lab_app_development_100.sql`), que trae **una muestra de hasta
> 100 filas por tabla**, no la base completa: 97 informes (`rem_reports`), 100
> equipos (`transformers`), 100 recepciones (`rems`), 100 correlativos,
> 12 muestreadores y 208 columnas de bancada. Son órdenes de magnitud, no censos.
> No se reproduce ningún nombre de cliente, de equipo ni de personal.

---

## 0. Resumen ejecutivo

**La hipótesis es correcta a medias, y la mitad que falla es la que importa.**

Sí: en el sistema viejo los catorce campos que hoy salen en raya estaban marcados
como obligatorios en la vista del formulario de informe
(`rem_reports/partials/_form_new_data_customer.html.erb` y
`_form_new_data_transformer.html.erb`), y en el nuevo son todos `nullable`
(`SampleReportController.php:374-390`). Eso se aflojó y está documentado abajo.

Pero el sistema viejo **no obtenía el dato por obligarlo**. Obtenía el dato por
dos vías distintas, y conviene separarlas porque llevan a decisiones opuestas:

1. **Los campos que llegaban llenos, llegaban PRECARGADOS.** El formulario viejo
   traía el contacto y el usuario final copiados del cliente, la descripción con
   un texto plantilla, y los trece datos de placa copiados del transformador
   (`_form_new_data_customer.html.erb:26,48,61`;
   `_form_new_data_transformer.html.erb:6-208`). El analista confirmaba, no
   tipeaba. `end_user` está cargado en el 100 % de los informes de la muestra
   porque venía escrito.

2. **Los campos que NO estaban precargados y sí eran obligatorios se llenaron
   con basura.** Las cuatro temperaturas y humedad salían en blanco y con
   `required`: hoy el 26 % de los informes tiene la temperatura del aceite en
   campo guardada como `0`, el 18 % la del transformador, el 7 % la humedad. Y en
   los desplegables obligatorios alguien agregó al catálogo una opción llamada
   literalmente `'-'`: **el 70 % de los informes tiene "Marca de aceite = -"**,
   el 30 % "Sistema de expansión = -", el 26 % "Punto de muestreo = -".

3. **Y el informe viejo imprimía raya igual.** El PDF viejo convertía el cero en
   raya a mano: `<% if @main_model.oil_temp.to_i == 0 %>-<% else %>…`
   (`_report_main_info.erb:86,94,100,102,110`). O sea: **el informe con huecos no
   es un defecto nuevo, es el mismo informe con huecos de siempre**, producido
   por ceros forzados en vez de por nulos honestos.

Conclusión operativa: **endurecer la validación no llenaría el informe**. Lo que
llenaba el informe viejo era la precarga, y eso es lo que el nuevo no tiene. Hay
además **dos defectos reales del nuevo** que sí explican rayas donde el dato
existe (§8): la marca de aceite se tipea y se descarta en silencio, y el contacto
y el usuario final no se pueden cargar desde la recepción.

---

## 1. La base de datos del viejo no obligaba nada

Antes de las tablas, hay que sacar del medio la cuarta fuente del encargo. En la
estructura real de producción
(`docs/migracion/esquema/lab_app_development-estructura.sql`), en las cinco
tablas de estos formularios, **todas las columnas de negocio son
`DEFAULT NULL`**. Las únicas `NOT NULL` son `id`, `created_at` y `updated_at`.

| Tabla | Líneas | Columnas de negocio `NOT NULL` |
|---|---|---|
| `rems` | 457-500 | ninguna |
| `rem_correlatives` | 524-542 | ninguna |
| `rem_reports` | 566-611 | ninguna |
| `transformers` | 1041-1062 | ninguna |
| `samplers` | 909-918 | ninguna |

No hay una sola restricción `NOT NULL`, `CHECK` ni `FOREIGN KEY` de negocio. La
base aceptaba cualquier cosa, incluida una recepción sin cliente. Esa columna de
las tablas de abajo dice "no" en todos los casos y no se repite por campo.

`customers` no está en este volcado: el modelo `Customer` hereda de `Primary2`
(`app/models/primary2.rb:1-4`), que apunta a una segunda base
(`config/database.yml:43-48`). La estructura de esa base no está en el
repositorio; los campos del formulario de cliente se leyeron de la vista y del
modelo.

---

## 2. Las cinco fuentes de obligatoriedad, y cuál pesaba de verdad

El encargo pedía revisar cuatro. Hay una quinta, y es la única que efectivamente
bloqueaba un guardado.

### 2.1 La vista: `data-parsley-required` — y no siempre estaba enchufada

El marcador que usan todas las vistas es `data-parsley-required="true"` más el
`required` de HTML5. Parsley solo se auto-vincula a los formularios que declaran
`data-parsley-validate`. Comparando:

| Formulario | `data-parsley-validate` | `novalidate` | ¿bloqueaba? |
|---|---|---|---|
| Recepción nueva (`rems/_form_new.html.erb:5`) | sí | no | sí |
| Informe nuevo (`rem_reports/_form_new.html.erb:5`) | sí | no | sí |
| Equipo nuevo (`transformers/_form_new.html.erb:5`) | sí | no | sí |
| Cliente nuevo (`customers/_form_new.html.erb:5`) | **no** | **sí** | **no** |
| Muestreador nuevo (`samplers/_form_new.html.erb:5`) | **no** | **sí** | **no** |
| Correlativo (`rem_correlatives/_form_edit.html.erb:5`) | **no** | **sí** | **no** |

En los tres últimos el `required` de HTML5 queda anulado por el `novalidate` del
propio formulario, y Parsley no se auto-vincula. Lo único que corría era el
enganche global de `layouts/_form_validation.html.erb:20-29`, que llama a
`form.parsley().validate()` **con el `preventDefault()` comentado**
(`:21`): muestra el error y deja pasar el envío. En esos tres formularios el
asterisco era decorativo.

### 2.2 El modelo: casi nada, y lo que había estaba comentado

Un solo `validates_presence_of` en todo el proyecto, y comentado:

```
app/models/transformer.rb:27
 # validates_presence_of  :location,:num_tag,:num_ten,:num_pot,:mark_id,:age,
 #   :oil_type_id,:transformer_type_id,:transformer_oil_mark_id,:oil_qty,
 #   :transformer_oil_unit_id,:conmutation_type_id,:transformer_preservation_id
```

Doce campos del equipo que en algún momento fueron obligatorios en el modelo y
dejaron de serlo. También está comentado `validates :type_report, presence: true`
(`app/models/rem_report.rb:37`). Lo que sí quedó vivo son unicidades
(`transformer.rb:24`, `customer.rb:14`, `sampler.rb:10`, `rem_report.rb:36`) y una
validación de coherencia en la recepción (`rem.rb:28-37`): el número de pruebas
no puede exceder el total de envases.

### 2.3 El controlador: `permit!`, o sea nada

Los seis controladores usan el mismo patrón:

| Controlador | Línea |
|---|---|
| `rems_controller.rb` | `383-384`: `params.require(:rem).permit!` |
| `rem_reports_controller.rb` | `313-314` |
| `rem_correlatives_controller.rb` | `124-125` |
| `transformers_controller.rb` | `145-146` |
| `customers_controller.rb` | `137-138` |
| `samplers_controller.rb` | `141-142` |

`params.require(:modelo)` exige que exista la clave raíz del formulario, nada
más. `permit!` acepta cualquier atributo sin validarlo. **Cero verificación
manual antes de guardar en los seis.**

Dos agravantes que conviene registrar:

- Cuando el guardado falla, el mensaje al usuario está clavado y no describe el
  error real: `rems_controller.rb:196-198` responde siempre
  *"El Nº de Muestras para analizar no debe ser mayor al Total de Envases"*, sea
  ése el problema o no.
- `rems_controller.rb:217-226` (`update_data`) y `:228-237` (`update_state`) no
  tienen rama `else`: si la actualización falla, no hay mensaje ni recarga. El
  usuario ve la pantalla como si hubiera guardado.

### 2.4 La base: nada (§1).

### 2.5 La quinta fuente: `belongs_to` obligatorio en producción

`config/application.rb:12` fija `config.load_defaults 7.0`, que activa
`belongs_to_required_by_default = true`. Ese valor se apaga **solo en
desarrollo** (`config/environments/development.rb:36`) y **no se apaga en
`production.rb`**. Es decir: en producción, cada `belongs_to` de estos modelos era
una validación de presencia real, evaluada en el servidor, imposible de saltear
desde el navegador.

| Modelo | `belongs_to` obligatorios en producción | Líneas |
|---|---|---|
| `Rem` | firma que autoriza, cliente, muestreador | `rem.rb:4-6` |
| `RemCorrelative` | recepción, equipo | `rem_correlative.rb:4-5` |
| `RemReport` | correlativo, equipo, muestreador, razón de análisis, unidad de aceite, fabricante, tipo de equipo, marca de aceite, tipo de aceite, conmutador, sistema de expansión, punto de muestreo, firma | `rem_report.rb:5-17` |
| `Transformer` | cliente, tipo, fabricante, conmutador, sistema de expansión, punto de muestreo, tipo de aceite, marca de aceite, unidad de aceite | `transformer.rb:3-11` |
| `Customer` | país | `customer.rb:4` |
| `Sampler` | país | `sampler.rb:3` |

**Esto explica los datos.** En el volcado, todas las columnas `*_id` de
`rem_reports` y `transformers` están llenas al 100 %, mientras que las de texto y
número tienen entre 4 % y 26 % de ceros o rayas. No es que la gente completara
mejor los desplegables: es que los desplegables no se podían dejar vacíos, y para
eso se creó la opción `'-'` en los catálogos (§6.1).

---

## 3. Formulario por formulario — el sistema viejo

Convención de las tablas: **V** = obligatorio en la vista (y si el bloqueo
efectivamente corría, §2.1) · **M** = en el modelo · **C** = en el controlador ·
**BD** = `NOT NULL` en la base. La columna BD es "no" en todos los casos (§1) y
se omite.

### 3.1 Recepción de muestras — `rems`

Vista: `app/views/im_management/rems/partials/_form_new.html.erb` (la de edición
es equivalente). Bloqueo de Parsley: **activo**.

| Campo | Etiqueta | V | M | C | Si se dejaba vacío |
|---|---|---|---|---|---|
| `date_received` | Fecha de Recepción (*) | sí `:11` | no | no | Parsley bloqueaba |
| `date_deliver` | Fecha de Entrega (*) | sí `:16` | no | no | Parsley bloqueaba |
| `customer_id` | Cliente (*) | sí `:21` | sí (`belongs_to`) | no | bloqueaba en cliente y servidor |
| `sampler_id` | Muestra extraída por (*) | sí `:26` | sí (`belongs_to`) | no | bloqueaba en cliente y servidor |
| `rem_user_signature_id` | Autoriza el ingreso (*) | sí `:70` | sí (`belongs_to`) | no | bloqueaba en cliente y servidor |
| `qty_num_test` | Cantidad de correlativos (*) | sí `:78`, `min=1` | sí (`rem.rb:28`) | no | bloqueaba |
| `qty_num_pack` | Total de envases | sí `:191`, `readonly` | sí (`rem.rb:28`) | no | lo calcula un script (`:214-222`) |
| `num_os` | Nº Orden de Servicio | **no** `:31` | no | no | queda vacío (5 % del volcado) |
| `observation` | Observaciones | **no** `:66` | no | no | vacío (87 % del volcado) |
| `ea_val` / `va_val` / `dc_val` | Envase / volumen / datos OK | no, precargados en sí `:40,46,52` | no | no | queda "sí" por omisión |
| `is_urgent` | Prioridad | no `:58` | no | no | queda "no" |
| `num_fiq` … `num_pas` (15 contadores) | Botellas por prueba | **no** `:92-188` | no | no | nulo: entre 82 % y 100 % nulos según la prueba |

### 3.2 Correlativo — asignación de equipo (`rem_correlatives`)

Vista: `rem_correlatives/partials/_form_edit.html.erb`. Bloqueo de Parsley:
**inactivo** (`:5` lleva `novalidate` y no lleva `data-parsley-validate`).

| Campo | Etiqueta | V | M | C | Si se dejaba vacío |
|---|---|---|---|---|---|
| `transformer_id` | Nº Serie del Transformador | marcado `:32`, sin bloqueo | sí (`belongs_to`) | no | el `belongs_to` lo rechazaba en el servidor, y el mensaje que se mostraba era *"Registro duplicado"* (`rem_correlatives_controller.rb:88`) |

Todo lo demás en esa pantalla es de solo lectura. El correlativo, el año y el
número los genera el sistema (`rem.rb:398-424`).

### 3.3 Informe de la muestra — `rem_reports`

**Éste es el formulario de los catorce campos.** Vista:
`rem_reports/partials/_form_new.html.erb:5`, con `data-parsley-validate` →
bloqueo **activo**. Cuatro bloques. Las vistas de edición
(`_form_edit_data_*`) llevan exactamente los mismos marcadores.

#### Bloque "Referencia" (`_form_new_data_customer.html.erb`)

| Campo | Etiqueta | V | M | Precargado con | Si se dejaba vacío |
|---|---|---|---|---|---|
| `num_report` | Nº de Reporte | sí `:13,20,28`, `readonly` | unicidad `rem_report.rb:36` | correlativo generado | no se podía |
| `rem_report_reason_id` | Razón de Análisis | sí `:16` | sí (`belongs_to`) | primera opción del catálogo | bloqueaba; en la práctica quedaba "Rutina" (87 de 97) |
| `contact_info` | Contacto del Cliente | sí `:26` | no | `customer.contact_info` | bloqueaba, pero venía escrito |
| `date_rec` | Fecha de Recepción | sí `:36` | no | `rem.date_received` | venía escrito |
| `description` | Descripción Muestra | sí `:48` | no | texto plantilla del procedimiento | venía escrito |
| `end_user` | Usuario Final | sí `:61` | no | `customer.name` | venía escrito |
| `date_emi` | Fecha de Emisión | sí `:70` | no | hoy | venía escrito |
| `sampler_id` | Muestra extraída por | sí `:79` | sí (`belongs_to`) | `rem.sampler_id` | venía escrito |
| `date_ent` | Fecha de Entrega | sí `:88` | no | hoy | venía escrito |
| `num_os` | Órden de Servicio | no, `readonly` `:6` | no | `rem.num_os` | se mostraba, no se editaba |

#### Bloque "Datos del Equipo" (`_form_new_data_transformer.html.erb`)

Los trece primeros venían **copiados del equipo**; los cuatro últimos salían
**en blanco**. Esa es toda la diferencia entre un dato cargado y un cero.

| Campo | Etiqueta | V | M | Precargado con | Si se dejaba vacío |
|---|---|---|---|---|---|
| `location` | Locación | sí `:27` | sí | `transformer.location` | venía escrito |
| `mark_id` | Fabricante | sí `:37` | sí (`belongs_to`) | del equipo | del equipo |
| `num_ten` | Tensión (kV) | sí `:49` | sí | del equipo | del equipo |
| `num_pot` | Potencia (MVA) | sí `:59` | sí | del equipo | del equipo |
| `transformer_type_id` | Tipo de Equipo | sí `:69` | sí (`belongs_to`) | del equipo | del equipo |
| `oil_type_id` | Tipo de Aceite | sí `:79` | sí (`belongs_to`) | del equipo | del equipo |
| `transformer_oil_mark_id` | Marca de Aceite | sí `:91` | sí (`belongs_to`) | del equipo | del equipo, y el equipo traía `'-'` |
| `age` | Año de Fabricación | sí `:101` | sí | del equipo | del equipo |
| `conmutation_type_id` | Conmutador | sí `:111` | sí (`belongs_to`) | del equipo | del equipo |
| `transformer_preservation_id` | Sistema de Expansión | sí `:121` | sí (`belongs_to`) | del equipo | del equipo, y el equipo traía `'-'` |
| `oil_qty` | Cantidad de Aceite | sí `:135` | sí | del equipo | del equipo |
| `transformer_oil_unit_id` | Unidad | sí `:138` | sí (`belongs_to`) | del equipo | del equipo |
| `num_tag` | Código del Cliente / TAG | sí `:194` | sí | del equipo | del equipo |
| `transformer_point_id` | Punto de Muestreo | sí `:151` | sí (`belongs_to`) | **nada** — el campo no existe en el formulario de equipo | había que elegir; existía la opción `'-'` |
| `oil_temp` | Temp. Aceite Transf. (°C) | sí `:162` | no | **nada** | había que tipear; se tipeaba `0` |
| `amb_temp` | Temp. ambiente campo (°C) | sí `:172` | no | **nada** | ídem |
| `hum_rel` | Humedad relativa campo (%) | sí `:184` | no | **nada** | ídem |
| `tra_temp` | Temp. aceite campo (°C) | sí `:204` | no | **nada** | ídem |

#### Bloque "Datos de la Muestra" (`_form_new_data_correlative.html.erb`)

| Campo | Etiqueta | V | M | Precargado con |
|---|---|---|---|---|
| `date_mue` | Fecha de Muestra | sí `:53` | no | `rem.date_received` |
| `operation` | En operación | sí `:63` | no | primera opción, con `'-'` como tercera (`:63`) |

#### Bloque "Análisis" (`_form_new_data_tests.html.erb`)

Solo casillas de qué pruebas se publican, todas precargadas en verdadero
(`:18-160`). Ninguna obligatoria.

### 3.4 Equipos / transformadores — `transformers`

Vista: `transformers/partials/_form_new.html.erb:5`, bloqueo **activo**.

| Campo | Etiqueta | V | M | Si se dejaba vacío |
|---|---|---|---|---|
| `customer_id` | Cliente | sí `:12` | sí (`belongs_to`) | bloqueaba en cliente y servidor |
| `num_serie` | Nº Serie | sí `:22` | unicidad con `num_tag` (`:24`) | bloqueaba |
| `location` | Locación | sí `:32` | comentado `:27` | bloqueaba en el navegador |
| `mark_id` | Fabricante | sí `:42` | sí (`belongs_to`) | bloqueaba |
| `num_ten` | Tensión (kV) | sí `:55` | comentado | bloqueaba en el navegador |
| `num_pot` | Potencia (MVA) | sí `:65` | comentado | ídem |
| `transformer_type_id` | Tipo de Equipo | sí `:75` | sí (`belongs_to`) | bloqueaba; existía `'-'` |
| `oil_type_id` | Tipo de Aceite | sí `:85` | sí (`belongs_to`) | bloqueaba |
| `transformer_oil_mark_id` | Marca de Aceite | sí `:98` | sí (`belongs_to`) | bloqueaba; existía `'-'` |
| `age` | Año de Fabricación | sí `:108` | comentado | bloqueaba en el navegador |
| `conmutation_type_id` | Conmutador | sí `:118` | sí (`belongs_to`) | bloqueaba |
| `transformer_preservation_id` | Sistema de Expansión | sí `:128` | sí (`belongs_to`) | bloqueaba; existía `'-'` |
| `oil_qty` | Cantidad de Aceite | sí `:143` | comentado | bloqueaba en el navegador |
| `transformer_oil_unit_id` | Unidad | sí `:146` | sí (`belongs_to`) | bloqueaba; existía `'-'` |
| `num_tag` | Código del Cliente / TAG | sí `:170` | unicidad con `num_serie` | bloqueaba |
| `transformer_point_id` | Punto de Muestreo | **comentado** `:156-164` | sí (`belongs_to`) | **el campo no existía en el formulario**: 100 de 100 equipos del volcado lo tienen nulo, pese al `belongs_to` obligatorio |

El caso de `transformer_point_id` es el más ilustrativo del sistema viejo: el
modelo lo declaraba obligatorio, la vista lo tenía comentado, y el dato terminaba
pidiéndose por informe en vez de por equipo. Se volvía a tipear en cada muestra.

La carga masiva (`transformer.rb:41-98`) no valida nada y hace `mark.id`,
`oil_type.id`, `transformer_type.id`… sobre el resultado de un `find_by_name`: si
el nombre del catálogo no coincide exactamente, revienta con `NoMethodError` en
vez de dar un error de línea.

### 3.5 Clientes — `customers`

Vista: `customers/partials/_form_new.html.erb:5`. Bloqueo **inactivo**
(`novalidate`, sin `data-parsley-validate`).

| Campo | Etiqueta | V | M | Si se dejaba vacío |
|---|---|---|---|---|
| `country_id` | País | marcado `:11`, sin bloqueo | sí (`belongs_to`) | el servidor lo rechazaba |
| `num_doc` | Nº Documento | marcado `:19`, sin bloqueo | unicidad `customer.rb:14` | **se guardaba vacío** |
| `name` | Cliente | marcado `:27`, sin bloqueo | no | **se guardaba vacío** |
| `address` | Dirección | marcado `:35`, sin bloqueo | no | **se guardaba vacío** |
| `contact_info` | Contacto | marcado `:43`, sin bloqueo | no | **se guardaba vacío** |

Nota de arrastre: `customer.rb:30-34` creaba automáticamente, por cada cliente
nuevo, una locación y un área llamadas `'-'`. La jerarquía del sistema viejo
nacía con un nodo vacío por diseño.

### 3.6 Muestreadores — `samplers`

Vista: `samplers/partials/_form_new.html.erb:5`. Bloqueo **inactivo**.

| Campo | Etiqueta | V | M | Si se dejaba vacío |
|---|---|---|---|---|
| `country_id` | País | marcado `:11`, sin bloqueo | sí (`belongs_to`) | el servidor lo rechazaba |
| `num_doc` | Código | marcado `:19`, sin bloqueo | unicidad `sampler.rb:10` | se guardaba vacío |
| `name` | Muestreador | marcado `:27`, sin bloqueo | no | se guardaba vacío |

En el volcado los 12 muestreadores tienen los tres campos cargados.

### 3.7 Hoja de trabajo / bancada — `labs` + `lab_details` + `lab_sub_details`

Éste es el único lugar donde el sistema viejo hacía las cosas bien: **la
obligatoriedad de cada columna de resultado era un dato, no código.**

| Campo | Etiqueta | V | M | Si se dejaba vacío |
|---|---|---|---|---|
| `labs.date_rehearsal` | Fecha del ensayo | sí (`labs/partials/_form_new.html.erb:35,37`) | no | Parsley bloqueaba |
| `lab_details.lab_detail_type_id` | Tipo de muestra (muestra / patrón / duplicado) | sí (`lab_details/partials/_form_new.html.erb:31,33`) | no | bloqueaba |
| `lab_details.num_test` | Nº de muestra | `required` + `readonly` (`:9`), copiado por script de `col1` (`:73-83`) | no | bloqueaba |
| Cada celda de resultado | según la columna configurada | **según `lab_category_sub_details.is_required`** (`lab_details/partials/_form_new_nested.erb:28,48,63`) | no | bloqueaba si la columna estaba marcada |

En el volcado, **202 de 208 columnas de bancada tienen `is_required = 1`**. La
obligatoriedad de un resultado de laboratorio se administraba por configuración,
sin tocar el código. Ese criterio se conservó en el sistema nuevo (§4.5).

### 3.8 Valores de orientación del informe (`rem_reports/add_details`)

Vale aclararlo porque el nombre engaña: en esta pantalla **el resultado medido no
se tipea** — se lee de la bancada
(`_form_add_details_cromas.html.erb:33-40`, que busca el `LabSubDetail` y lo
escribe con `RemReportDetail.update`). Los 19 campos `required` de esa vista son
los **límites de orientación** (`cro_ori`, `f25_ori`, …), no mediciones. Y el
propio código contempla `cro_ori == "-"` (`:52`): también ahí el campo
obligatorio tenía su escape.

---

## 4. El sistema nuevo — la validación real

### 4.1 Recepción — `ReceptionController::validated()` (`:374-390`)

| Campo | Regla | Viejo equivalente | Veredicto |
|---|---|---|---|
| `customer_id` | `required` | obligatorio | igual |
| `received_at` | `required, date` | obligatorio | igual |
| `code` | `nullable` | generado, no editable | más flexible |
| `service_order` | `nullable` | no obligatorio | igual |
| `sampler_id` | **`nullable`** | **obligatorio** (`belongs_to` + vista) | **se aflojó** |
| `due_at` | `nullable`, `after_or_equal:received_at` | obligatorio, sin coherencia | se aflojó la presencia, se endureció la coherencia |
| `packages` | `nullable, 0..9999` | obligatorio, calculado | se aflojó |
| `container_ok` / `volume_ok` / `label_ok` | `nullable, boolean` | precargados en sí | equivalente |
| `is_urgent`, `notes`, `status` | `nullable` | no obligatorios | igual |
| firma que autoriza el ingreso | **no existe** | **obligatorio** (`rem.rb:4`) | **se perdió el campo** |
| cantidad de correlativos | en `confirm()`: `required, 1..500` (`:259-261`) | obligatorio | igual, mejor acotado |
| coherencia pruebas ≤ envases | **no existe** | `rem.rb:28-37` | **se perdió la regla** |
| `contact_info`, `end_user` | **no están en este formulario** | se cargaban aguas abajo | ver §8.2 |

### 4.2 Informe de la muestra — `SampleReportController::validated()` (`:374-390`)

**Los 20 campos de cabecera son `nullable`, sin excepción.** Es el
contrapunto exacto del §3.3: `service_order`, `contact_info`, `end_user`,
`report_number`, `description`, `sampling_reason`, `sampling_point`, `sampled_at`,
`oil_temp_c`, `equipment_temp_c`, `ambient_temp_c`, `relative_humidity`,
`oil_brand`, `manufacture_year`, `oil_volume`, `issued_at`, `delivered_at`,
`notes`, `tests`.

Lo que el nuevo agrega y el viejo no tenía: **cotas físicas**. Temperaturas
`between:-50,250`, ambiente `-50,80`, humedad `0,100`, año `1900..hoy+1`. En el
viejo las cuatro temperaturas eran `varchar` sin ninguna cota
(`estructura.sql:597-599,603`).

Y la diferencia de fondo, que no es de validación: el nuevo **no precarga nada**.
`ReportFormModal.vue:78` hace `form.value = { ...json.header }` y `json.header`
sale de lo ya guardado (`SampleReportController.php:301-339`). Para una muestra
nueva eso es todo nulo. Donde el viejo mostraba el contacto del cliente, el
nombre del cliente como usuario final, la plantilla de descripción y trece datos
de placa, el nuevo muestra campos en blanco.

### 4.3 Equipos — `EquipmentFieldRules::equipmentRules()`

| Campo | Nuevo | Viejo | Veredicto |
|---|---|---|---|
| `customer_id` | `required` | obligatorio | igual |
| `name` | `required` | no existía | se endureció (campo nuevo) |
| `serial` | **`nullable`** | **obligatorio** (`:22`) | **se aflojó** |
| `tag` | **`nullable`** | **obligatorio** (`:170`) | **se aflojó** |
| `equipment_type_id` | **`nullable`** | **obligatorio** | **se aflojó** |
| `oil_type_id` | **`nullable`** | **obligatorio** | **se aflojó** |
| `brand_id` | **`nullable`** | **obligatorio** | **se aflojó** |
| `tap_changer_type_id` (conmutador) | **`nullable`** | **obligatorio** | **se aflojó** |
| `transformer_preservation_id` | **`nullable`** | **obligatorio** | **se aflojó** |
| `voltage_kv_hv/lv/tv` | `nullable, 0..2000` | obligatorio, `varchar` libre | presencia aflojada, tipo endurecido |
| `power_mva`, `_2`, `_3` | `nullable, 0..10000` | obligatorio, `varchar` libre | ídem |
| `manufacture_year` | `nullable, 1900..hoy+1` | obligatorio, `varchar` | ídem |
| `oil_volume` + `oil_volume_unit` | `nullable`, unidad de lista cerrada | obligatorio, unidad por catálogo con `'-'` | presencia aflojada, unidad endurecida |
| `service_state` (en operación) | `nullable`, lista cerrada | no existía en el equipo | campo nuevo |
| `customer_location_id` (locación) | **`nullable`** | **obligatorio** como texto libre | **se aflojó** |
| `phases`, `external_ref` | `nullable` | no existían | campos nuevos |
| punto de muestreo | **no existe en el equipo** | `belongs_to` obligatorio, vista comentada | ver §7 |
| `oil_brand` (marca de aceite) | **no existe en el formulario del equipo** | obligatorio | ver §8.1 |

Lo que el nuevo hizo mejor y no debe deshacerse: la unicidad. El viejo validaba
`num_tag` con alcance `num_serie` (`transformer.rb:24`); el nuevo hace lo mismo
con `EquipmentIdentityIsFree`, y sacó a propósito el nombre único por workspace y
el código derivado del nombre, con el porqué escrito en
`StoreEquipmentRequest.php:12-33`.

### 4.4 Clientes y muestreadores

| Campo | Nuevo | Viejo | Veredicto |
|---|---|---|---|
| Cliente `name` | `required` + único sin acentos (`StoreCustomerRequest.php:38-55`) | marcado sin bloqueo | se endureció |
| Cliente `cod` | `required` + único por país (`:56-64`) | unicidad global | se endureció |
| Cliente `country_id` | `required` (`:65`) | `belongs_to` | igual |
| Cliente `address` | **`nullable`** (`:66`) | marcado sin bloqueo | equivalente en efecto |
| Cliente **contacto** | **no existe la columna** | campo del cliente | **se perdió el campo** (§5, punto 1) |
| Muestreador `name` | `required` + único (`StoreSamplerRequest.php:33-50`) | marcado sin bloqueo | se endureció |
| Muestreador `code` | `nullable`, derivado del nombre (`:17-20,51-64`) | marcado sin bloqueo | equivalente |
| Muestreador `country_id` | **no existe** | `belongs_to` obligatorio | se perdió, sin impacto en el informe |

### 4.5 Hoja de trabajo / bancada

Aquí el nuevo **es más estricto que el viejo**, y con el mismo criterio de datos:

- La obligatoriedad sigue siendo por columna (`test_fields.is_required`,
  `create_test_definitions_tables.php:233`), administrable desde
  `TestFieldFormModal.vue:263`, y el importador legado trajo la marca del viejo
  (`ImportLegacyTestsCommand.php:204`).
- El bloqueo dejó de ser del navegador: `WorksheetService::missingRequiredValues()`
  (`:637-670`) verifica **en el servidor**, por réplica, y no deja cerrar la hoja
  con celdas obligatorias vacías. El viejo dependía de Parsley, salteable
  desactivando JavaScript.
- Y sabe exceptuar lo que no corresponde: a un patrón, un duplicado o un blanco
  no se les reclama código de muestra (`:651-658`).
- `run_date` es `required` (`WorksheetController.php:122,207`), igual que
  `date_rehearsal` del viejo.

**Este bloque no se aflojó.** No hay nada que corregir en esta pantalla.

---

## 5. LO QUE SE AFLOJÓ

Ordenado por impacto en el informe que recibe el cliente. La columna "¿lo llena
endurecer?" es la pregunta que importa: si el viejo lo obtenía por precarga,
volverlo obligatorio no lo llena, solo molesta.

### 1. Contacto del cliente — impacto alto

En el viejo era un **campo del cliente** (`customers/_form_new.html.erb:43`) que
el informe precargaba (`_form_new_data_customer.html.erb:26`). En el nuevo la
columna del cliente **no existe** (`Customer.php:33-36`) y el dato solo se puede
tipear por informe (`SampleReportController.php:383`, `nullable`).
Cargado en 91 de 97 informes del volcado; los 6 restantes valen `'-'`.
**¿Lo llena endurecer? No** — lo llena devolverle el campo al cliente y
precargarlo.

### 2. Usuario final — impacto alto

Obligatorio en el viejo (`:61`) y precargado con el nombre del cliente. Cargado
en **97 de 97**. En el nuevo, `nullable` y en blanco. La migración
(`add_report_header_fields.php:22-25`) explica bien por qué el usuario final no
siempre es el cliente —una contratista manda muestras de la minera— y por eso el
valor por omisión del viejo era razonable como punto de partida, no como verdad.
**¿Lo llena endurecer? No** — lo llena precargar con el cliente y dejar
cambiarlo.

### 3. Descripción de la muestra — impacto alto

Obligatoria en el viejo (`:48`) y **precargada con el texto del procedimiento**.
Presente en 97 de 97; 93 contienen la plantilla y 34 son exactamente la
plantilla sin agregado. En el nuevo, `nullable` y en blanco.
**¿Lo llena endurecer? No** — lo llena un texto por omisión configurable.

### 4. Marca de aceite — impacto alto, y hay un defecto detrás

Obligatoria en el viejo por `belongs_to` (`rem_report.rb:12`). En el nuevo el
campo **no está en la ficha del equipo** y, cuando se tipea en el informe, **se
descarta en silencio**: `oil_brand` no figura en `Equipment::$fillable`
(`Equipment.php:56-66`) y `SampleReportService.php:154-161` la escribe con
`update()` sobre el equipo. Ver §8.1. Ojo con el conteo antes de pedir el dato:
**68 de 97 informes del viejo apuntan a la marca `'-'`**.
**¿Lo llena endurecer? No** — hay que arreglar el defecto; endurecer sobre un
campo que no persiste solo bloquea el guardado.

### 5. Sistema de preservación / expansión — impacto medio-alto

Obligatorio en el viejo (`transformers/_form_new.html.erb:128`,
`rem_report.rb:15`). En el nuevo, `nullable` en la ficha del equipo
(`EquipmentFieldRules.php:57`). Dato real en 68 de 97 informes; los otros 29
apuntan a `'-'`.
**¿Lo llena endurecer? Parcialmente** — es un dato de placa, se carga una vez por
equipo y sirve para siempre. Candidato legítimo a obligatorio **en la ficha del
equipo**, con la salvedad del §6.2.

### 6. Conmutador — impacto medio-alto

Obligatorio en el viejo. En el nuevo, `nullable` (`:56`). Es el mejor dato del
lote: 97 de 97 informes lo tienen, con solo 3 valores distintos y **sin ninguna
raya** — el catálogo de conmutadores es el único de los cinco que nunca necesitó
una opción `'-'`. Es un dato que el cliente sí conoce.
**¿Lo llena endurecer? Sí** — es el caso más claro a favor.

### 7. Cantidad de aceite — impacto medio

Obligatoria en el viejo. En el nuevo, `nullable` (`:75-76`). Cargada con número
en 75 de 97 informes; **22 valen `0`** y 21 tienen la unidad en `'-'`.
El nuevo ya arregló la mitad del problema: la unidad es lista cerrada `['L','gal']`
(`Equipment.php:51`), con el porqué escrito en `:42-50`.
**¿Lo llena endurecer? No del todo** — el 23 % de ceros del viejo dice que el dato
a veces no se conoce. Es dato de placa: pedirlo en la ficha del equipo, no por
informe.

### 8. Locación — impacto medio

Obligatoria en el viejo como **texto libre** (`transformers/_form_new.html.erb:32`,
copiada al informe en `_form_new_data_transformer.html.erb:27`). Cargada en 96 de
97 informes. En el nuevo es una jerarquía normalizada
(`customer_location_id` → `customer_area_id` → `customer_substation_id`), toda
`nullable` (`EquipmentFieldRules.php:33-44`), y el informe cae de locación a
subestación (`test_report.blade.php:510`).
**¿Lo llena endurecer? Sí, con cuidado** — la jerarquía es mejor modelo, pero el
viejo creaba automáticamente el nodo `'-'` por cliente (`customer.rb:30-34`), así
que exigir la locación con esa jerarquía sembrada empuja a elegir el
nodo vacío. Ver §6.3.

### 9. Razón de muestreo — impacto medio

Obligatoria en el viejo por `belongs_to` (`rem_report.rb:8`), con catálogo de 6
opciones. En el nuevo es **texto libre de 80 caracteres**, `nullable`
(`SampleReportController.php:387`). Presente en 97 de 97, pero **87 dicen
"Rutina"**, que era la primera opción preseleccionada del desplegable: es el valor
por omisión de un `select`, no una decisión.
**¿Lo llena endurecer? No** — lo llena volver a un catálogo con opción por
omisión. El texto libre además rompe la comparabilidad: la migración misma
(`add_report_header_fields.php:28-29`) dice que la razón cambia cómo se lee el
resultado, y un campo que a veces dice "Rutina", a veces "rutina" y a veces
"RUTINA " no se puede filtrar.

### 10. Cuatro condiciones de campo: temperatura del aceite, del equipo, ambiente, humedad — impacto medio

Las cuatro obligatorias en el viejo, las cuatro **en blanco**, las cuatro
`nullable` en el nuevo (`:378-381` del bloque de temperaturas). Ceros guardados:
temperatura del aceite en campo 25 de 97, del transformador 17, humedad 7,
ambiente 4.
**¿Lo llena endurecer? No, y es el caso donde endurecer hace daño.** Ver §6.1.

### 11. En operación — impacto bajo-medio

En el viejo era un campo **de la muestra** (`rem_reports.operation`) obligatorio,
con tres opciones: Sí, No y `'-'`. Reparto real: 63 Sí, 14 No, **20 `'-'`**. En el
nuevo es `equipment.service_state`, `nullable`, lista cerrada de tres valores
(`Equipment.php:53`).
**¿Lo llena endurecer? No, y además cambió de significado.** Ver §6.4.

### 12. Punto de muestreo — impacto bajo en el informe, alto en la comparabilidad

En el viejo: `belongs_to` obligatorio en el equipo (`transformer.rb:8`) pero con
**la vista comentada** (`transformers/_form_new.html.erb:156-164`), así que los
100 equipos del volcado lo tienen nulo y se pedía por informe, donde 25 de 97
dicen `'-'`. En el nuevo es texto libre de 80 caracteres en el informe
(`samples.sampling_point`, `create_receptions_tables.php:179`).
**¿Lo llena endurecer? Sí, si se hace en el lugar correcto** — es una decisión de
muestreo (grifo, tapa, conmutador), no de placa: va con la muestra, y con
catálogo, no texto libre.

### 13. Firma que autoriza el ingreso — se perdió el campo

`rem.rb:4` la exigía por `belongs_to` y la vista la pedía
(`rems/_form_new.html.erb:70`). Cargada en 100 de 100 recepciones. En el nuevo no
existe columna equivalente en `receptions`. No sale en el informe, pero es
trazabilidad de quién aceptó la muestra.

### 14. Muestreador en la recepción — se aflojó

`required` en el viejo por vista y `belongs_to`; `nullable` en el nuevo
(`ReceptionController.php:380`), con `sampler_name` como alternativa de texto
libre. Cargado en 100 de 100 recepciones del viejo, con solo 2 muestreadores
distintos. Sale en el informe ("Muestra extraída por").
**¿Lo llena endurecer? Sí** — pedir uno de los dos, el catálogo o el nombre, es
razonable: quien recibe la muestra sabe quién la trajo.

### 15. Serie y TAG del equipo — se aflojaron

Los dos `required` en el viejo (`:22`, `:170`), los dos `nullable` en el nuevo
(`EquipmentFieldRules.php:60-61`). Serie cargada en 100 de 100 equipos; **TAG en
83, con 17 en `'-'`**. Son los dos campos con los que el nuevo identifica al
equipo (`EquipmentIdentityIsFree`) y los dos que el informe imprime primero.
**¿Lo llena endurecer? La serie sí; el TAG no** — el 17 % de rayas del viejo dice
que hay equipos sin código de cliente. Lo correcto es exigir **al menos uno de
los dos**, que es además lo que la unicidad del nuevo ya asume.

### 16. La regla de coherencia de la recepción — se perdió

`rem.rb:28-37` impedía declarar más pruebas que envases recibidos. No tiene
equivalente en `ReceptionController`. Es la única validación de negocio real que
tenía el modelo viejo y no es una obligatoriedad de campo, es una coherencia
entre dos campos: no llena ninguna raya, pero evitaba una entrega imposible.

---

## 6. LO QUE NO HAY QUE ENDURECER, Y POR QUÉ

**La tesis, dicha sin vueltas: obligar mal es exactamente lo que llenó la base
vieja de ceros y de rayas.** Un campo obligatorio que el operador no puede saber
no produce dato: produce el valor que deja pasar el formulario. Y ese valor
después se imprime, o peor, se calcula.

### 6.1 Las cuatro condiciones de campo — el caso testigo

Temperatura del aceite del transformador, temperatura del aceite en campo,
temperatura ambiente y humedad relativa. Las cuatro eran obligatorias en el
viejo, las cuatro salían en blanco, y el resultado está medido:

| Campo | `0` guardados | de |
|---|---|---|
| Temp. aceite en campo (`tra_temp`) | 25 | 97 |
| Temp. aceite del transformador (`oil_temp`) | 17 | 97 |
| Humedad relativa (`hum_rel`) | 7 | 97 |
| Temp. ambiente (`amb_temp`) | 4 | 97 |

Un cero no es una lectura. Una humedad relativa de 0 % no existe en un patio de
subestación, y una temperatura de aceite de 0 °C en un transformador en servicio
tampoco. Son el "no lo sé" de un formulario que no aceptaba "no lo sé".

Y el propio informe viejo lo sabía: imprimía raya cuando el valor era cero
(`_report_main_info.erb:86,94,102,110`). O sea que **el viejo mostraba
exactamente las mismas rayas que el nuevo**, con el agravante de que el dato
guardado era un cero falso en vez de un nulo. El nuevo ya tomó la decisión
correcta y la dejó escrita:

```
add_report_header_fields.php:30-33
  En el sistema anterior se guardaban como texto y se imprimían con
  `to_f.round(2)`, así que un campo vacío salía "0.00" y quedaba
  indistinguible de una medición real de cero. Acá son decimales anulables:
  vacío es vacío.
```

Ese criterio se reafirma en `Sample.php:57-68` y en la plantilla
(`test_report.blade.php:296-298`: *"si no se midió, RAYA. Nunca 0.00"*).

**No endurecer.** Estas cuatro las mide el muestreador en el patio y las anota en
la tarjeta de la muestra. Si la tarjeta viene sin ellas, el laboratorio no las
puede inventar. Lo correcto es que el informe diga que no se midieron.

Sí conviene, en cambio, **advertir sin bloquear**: marcar la muestra como
"cabecera incompleta" en la pantalla de la entrega, y que el aviso viaje al
muestreador. Eso genera dato; obligar genera ceros.

### 6.2 Los cinco desplegables con opción `'-'` en el catálogo

La evidencia más directa de que obligar mal no funciona no está en el código
viejo: está en sus catálogos. Alguien tuvo que **crear una opción llamada `'-'`**
para poder guardar un formulario que exigía elegir:

| Catálogo | Fila `'-'` | Informes que la usan | Equipos que la usan |
|---|---|---|---|
| `transformer_oil_marks` (marca de aceite) | `id 1` | **68 / 97** | 68 / 100 |
| `transformer_preservations` (sistema de expansión) | `id 4` | 29 / 97 | 23 / 100 |
| `transformer_points` (punto de muestreo) | `id 1`, creado el 2023-06-20, **cinco meses después de los otros tres** | 25 / 97 | 0 / 100 |
| `transformer_oil_units` (unidad de aceite) | `id 6` | 21 / 97 | 16 / 100 |
| `transformer_types` (tipo de equipo) | `id 16`, creado el 2023-09-04 | 9 / 97 | 5 / 100 |

Las fechas cuentan la historia sola: los catálogos se sembraron el 2023-01-25, y
las opciones `'-'` de punto de muestreo y de tipo de equipo aparecieron en junio y
septiembre. No estaban en el diseño: se agregaron cuando la obligatoriedad chocó
con la realidad.

**Y hay un caso peor que una raya en el papel.** El `'-'` del tipo de equipo
(`id 16`) no es cosmético: el tipo de equipo es una de las tres dimensiones con
las que se elige el cuadro de límites, como está escrito en
`EquipmentFieldRules.php:46-52`. Un equipo con tipo `'-'` se compara contra
ninguna norma y sale "sin criterio", que —cita del mismo comentario— *"es peor
que un dato faltante, porque parece que cumple"*. Obligar a elegir un tipo
produjo nueve informes cuyo tipo de equipo es una raya. Eso no es un hueco en la
cabecera: es un diagnóstico sin norma.

**No endurecer ninguno de los cinco sin resolver primero el destino del "no
aplica".** Un transformador sellado no tiene sistema de expansión; un aceite
reprocesado no tiene marca comercial. La respuesta correcta no es un catálogo con
`'-'`: es un campo anulable, y que el informe imprima raya, que es lo que ya hace.

### 6.3 Locación, y el nodo `'-'` que el viejo creaba solo

`customer.rb:30-34` creaba, por cada cliente nuevo, una `CustomerLocation` y un
`CustomerArea` llamadas `'-'`. La jerarquía del viejo nacía con un nodo vacío. Si
en el nuevo se hace obligatoria la locación sobre una jerarquía migrada que
arrastra esos nodos, el operador va a elegir el `'-'` —es el único que existe para
muchos clientes— y el informe va a imprimir una raya con más pasos intermedios.

El nuevo ya tiene la pieza que hace esto correcto: `HierarchyBelongsToCustomer`
(`EquipmentFieldRules.php:35,39,43`) impide colgar un equipo de la locación de
otro cliente. **Obligar la locación tiene sentido después de limpiar los nodos
`'-'` de la jerarquía migrada, no antes.**

### 6.4 "En operación" — cambió de significado, no solo de obligatoriedad

En el viejo era `rem_reports.operation`: **¿estaba el transformador en operación
cuando se tomó esta muestra?** Es un hecho del muestreo, y por eso vivía en el
informe. En el nuevo es `equipment.service_state`: **¿en qué estado está este
equipo?** Es una propiedad del equipo, que cambia con el tiempo.

No son el mismo dato. Volverlo obligatorio en la ficha del equipo congela en la
ficha un hecho que era de la muestra, y hace que un informe de hace dos años
imprima el estado de hoy. Además, el reparto del viejo (63 Sí, 14 No, **20 `'-'`**)
dice que en uno de cada cinco muestreos ni el muestreador lo sabía.

**No endurecer.** Si se quiere el dato del viejo, es un campo de la muestra, no
del equipo, y anulable.

### 6.5 Descripción de la muestra — obligarla produce la plantilla, no información

93 de 97 descripciones del viejo contienen el texto que el formulario precargaba
(`_form_new_data_customer.html.erb:48`), y 34 son exactamente ese texto sin una
palabra agregada. La obligatoriedad no generó descripción: generó 34 copias de la
misma frase. Las 59 restantes sí agregan algo (la mediana está en 80 caracteres
contra 48 de la plantilla pelada), y eso es lo valioso.

**No endurecer; precargar.** Un texto por omisión editable produce el mismo
resultado sin bloquear a nadie, y deja distinguir quién agregó algo.

### 6.6 Razón de muestreo — obligar un `select` produce su primera opción

87 de 97 dicen "Rutina", que es la opción 1 del catálogo y venía preseleccionada
(`_form_new_data_customer.html.erb:16`, sin `prompt`). No es que el 90 % de las
muestras sean de rutina: es que nadie tocó el desplegable. Un campo obligatorio
con valor por omisión no es obligatorio, es un valor por omisión con asterisco.

**No endurecer la presencia; recuperar el catálogo.** Volver de texto libre a las
seis opciones del viejo (Rutina, Evento, Tratamiento termo vacío, Tratamiento
regeneración, Cambio de aceite, Otros) devuelve comparabilidad, que es lo que se
perdió de verdad.

### 6.7 Los quince contadores de envases de la recepción

`num_fiq`, `num_cro`, `num_pcb`, `num_fur`, `num_azu`, `num_pol`, `num_vis`,
`num_par`, `num_met`, `num_inh`, `num_dbd`, `num_sed`, `num_flu`, `num_inf`,
`num_pas`. **En el viejo no eran obligatorios** (`rems/_form_new.html.erb:92-188`,
sin un solo `data-parsley-required`), y con razón: entre el 82 % y el 100 % están
nulos según la prueba. Se llena el que corresponde a lo que llegó.

Se anota para dejar constancia de que **el viejo también sabía dejar campos
opcionales cuando el campo era condicional.** El criterio no es "el viejo obligaba
todo": es que obligaba lo que estaba precargado y lo que era un desplegable.

### 6.8 Nº de orden de servicio y observaciones

Tampoco eran obligatorios en el viejo (`:31`, `:66`): el número de orden llega
después en el 5 % de los casos, y las observaciones solo si hay algo que observar
(87 % vacías). Mantener `nullable`.

### 6.9 El TAG del equipo

17 de 100 equipos del viejo tienen el TAG en `'-'`. Hay equipos sin código de
cliente, y es legítimo. **Lo correcto no es obligar el TAG, es obligar "serie o
TAG".**

---

## 7. Los catorce campos del informe: quién los carga y en qué pantalla

Columnas: **Dónde vive hoy** en el nuevo · **Quién lo carga** · **En qué
pantalla** · **Cuánto dato real tenía el viejo** (sobre 97 informes o 100 equipos
del volcado, descontando ceros y rayas).

| Campo del informe | Dónde vive hoy | Quién lo carga | Pantalla | Dato real en el viejo |
|---|---|---|---|---|
| Contacto | `receptions.contact_info` | Recepción del laboratorio, una vez por entrega; debería venir del cliente | **Ficha del cliente** (campo a recrear) + **Recepción** para el caso puntual. Hoy solo se puede desde el informe | 91 / 97 (6 con `'-'`) |
| Usuario final | `receptions.end_user` | Recepción, una vez por entrega | **Recepción**, precargado con el nombre del cliente. Hoy solo desde el informe | 97 / 97, todos precargados |
| Descripción de la muestra | `samples.description` | Recepción, con texto por omisión del procedimiento | **Recepción** (o el informe, con la plantilla precargada) | 97 / 97, 34 son solo la plantilla |
| Locación | `equipment.customer_location_id` → `customer_substation_id` | Quien da de alta el equipo | **Ficha del equipo** | 96 / 97 en el informe; en el equipo, texto libre |
| Sistema de preservación | `equipment.transformer_preservation_id` | Quien da de alta el equipo, con la placa a la vista | **Ficha del equipo** | 68 / 97 (29 con `'-'`) |
| Razón de muestreo | `samples.sampling_reason` | El muestreador decide; el laboratorio transcribe de la tarjeta | **Recepción**, por muestra (hoy: informe) | 97 / 97, pero 87 son el valor por omisión |
| Marca de aceite | `equipment.oil_brand` | Quien da de alta el equipo | **Ficha del equipo** — hoy el campo no está en ese formulario, y desde el informe se pierde (§8.1) | **29 / 97** (68 con `'-'`) |
| Cantidad de aceite | `equipment.oil_volume` + `oil_volume_unit` | Quien da de alta el equipo, de la placa | **Ficha del equipo** | 75 / 97 (22 en `0`); unidad 76 / 97 |
| Conmutador | `equipment.tap_changer_type_id` | Quien da de alta el equipo | **Ficha del equipo** | **97 / 97**, sin rayas |
| En operación | `equipment.service_state` | El muestreador lo observa en el patio | **Recepción**, por muestra (era un dato de la muestra, §6.4) | 77 / 97 (20 con `'-'`) |
| Temperatura del aceite del transformador | `samples.oil_temp_c` | El muestreador, en el patio | **Recepción**, transcribiendo la tarjeta (hoy: informe) | 80 / 97 (17 en `0`) |
| Temperatura del aceite en campo | `samples.equipment_temp_c` | El muestreador, en el patio | ídem | **72 / 97** (25 en `0`) |
| Temperatura ambiente | `samples.ambient_temp_c` | El muestreador, en el patio | ídem | 93 / 97 (4 en `0`) |
| Humedad relativa | `samples.relative_humidity` | El muestreador, en el patio | ídem | 90 / 97 (7 en `0`) |
| *(bonus)* Punto de muestreo | `samples.sampling_point` | El muestreador decide | **Recepción**, por muestra, con catálogo | 72 / 97 (25 con `'-'`); en el equipo, 0 / 100 |

Lectura de la última columna, que es la que decide qué pedir:

- **Datos de placa que el cliente conoce y no cambian** (conmutador 100 %,
  locación 99 %, preservación 70 %, cantidad de aceite 77 %): se cargan **una vez
  en la ficha del equipo** y sirven para todas las muestras futuras. Es
  exactamente lo que el nuevo ya hace y el viejo no
  (`SampleReportService.php:148-153`: *"la próxima muestra del mismo
  transformador tiene que llegar con el dato ya cargado"*). En estos campos
  endurecer tiene
  sentido, porque se paga una vez.
- **Datos del muestreo que solo existen si el muestreador los anotó**
  (las cuatro temperaturas, humedad, en operación, punto de muestreo, razón):
  entre 21 % y 26 % de ausencia real en el viejo. En estos campos, endurecer
  produce ceros.
- **La marca de aceite es el peor caso** (29 %): además de tener el defecto del
  §8.1, es el dato que el cliente menos conoce. Es el candidato natural a quedar
  anulable e imprimirse en raya.

**El desbalance de pantallas es el diagnóstico completo.** Hoy, los catorce campos
se cargan en **un solo lugar**: `ReportFormModal.vue`, el formulario de emisión
del informe, una muestra a la vez
(`SampleReportController.php:374-390`, `ReportFormModal.vue:150-320`). Se pide
todo al final, al que emite, y no al que sabe: nueve de los catorce los sabe quien
da de alta el equipo o quien recibe la entrega. En el viejo, los trece datos de
placa llegaban precargados desde el equipo y solo cuatro se tipeaban. Eso es lo
que hay que reponer: **precarga y reparto por pantalla, no asteriscos.**

---

## 8. Defectos del sistema nuevo encontrados en el camino

Se listan porque explican rayas donde el dato existe. No se corrigió nada: este
documento es el único archivo escrito.

### 8.1 La marca de aceite se tipea y se descarta en silencio

`SampleReportService::aplicarCabecera()` (`:154-161`) escribe tres campos en el
equipo:

```php
$deEquipo = array_filter([
    'oil_brand'        => $datos['oil_brand'] ?? null,
    'manufacture_year' => $datos['manufacture_year'] ?? null,
    'oil_volume'       => $datos['oil_volume'] ?? null,
], fn ($v) => $v !== null);

if ($deEquipo !== [] && $sample->equipment) {
    $sample->equipment->update($deEquipo);
}
```

`oil_brand` **no está en `Equipment::$fillable`** (`Equipment.php:56-66`), que
lista `oil_volume`, `oil_volume_unit`, `service_state` pero no `oil_brand`. El
modelo no declara `$guarded = []` y el proyecto no activa
`Model::preventSilentlyDiscardingAttributes()` en ningún proveedor, así que
Eloquent **descarta el atributo sin avisar**: el guardado responde "guardado", el
campo vuelve vacío y el informe imprime raya. Los otros dos campos del mismo
array sí persisten.

Además el campo **no existe en la ficha del equipo**
(`resources/js/Pages/Equipment/Form.vue` no lo tiene) y no está en
`EquipmentFieldRules`, aunque la columna sí existe
(`create_sample_reports_tables.php:158`) y la plantilla del informe la imprime con
un comentario que dice que el dato está cargado
(`test_report.blade.php:529-534`). Ningún test lo cubre.

**Éste es el único de los catorce campos donde la raya es un defecto y no una
decisión.**

### 8.2 Contacto y usuario final no se pueden cargar en la recepción

Las dos columnas viven en `receptions`
(`add_report_header_fields.php:44-45`) y el modelo las acepta
(`Reception.php:57-59`). Pero `ReceptionController::validated()` (`:374-390`) **no
las incluye** y `Receptions/Form.vue` no las muestra. La única vía es el
formulario del informe, que las escribe en la recepción
(`SampleReportService.php:120-128`). Consecuencia práctica: en una entrega de
cuarenta muestras, el contacto de la empresa se carga desde el informe de una de
ellas y afecta a las cuarenta, sin que la pantalla de la entrega lo muestre.

### 8.3 La coherencia "pruebas ≤ envases" no se migró

`rem.rb:28-37` la validaba. No hay equivalente en `ReceptionController::confirm()`
(`:256-268`), que solo acota `samples` a 1..500. `packages` es `nullable`.

### 8.4 La firma que autoriza el ingreso no tiene columna

`rems.rem_user_signature_id` era obligatoria (`rem.rb:4`) y está cargada en 100 de
100 recepciones del volcado. `receptions` no tiene equivalente.

---

## 9. Qué se recomienda hacer, en orden

Sin escribir código; es la lista que se desprende de lo anterior.

1. **Arreglar el defecto del §8.1**: agregar `oil_brand` a `Equipment::$fillable`,
   ponerlo en la ficha del equipo con su regla `nullable`, y cubrirlo con un test.
   Es una raya que hoy tapa un dato cargado.
2. **Reponer la precarga, que es lo que el viejo hacía bien.** El formulario del
   informe debería llegar con el contacto y el usuario final del cliente, la
   descripción con el texto del procedimiento, y las condiciones de campo de la
   muestra anterior del mismo equipo como sugerencia visible pero no aceptada por
   omisión. Nueve de los catorce huecos se cierran por esa vía, sin un solo
   `required`.
3. **Recrear el contacto como campo del cliente** y usarlo como valor por omisión.
4. **Repartir por pantalla**: contacto, usuario final, descripción, razón, punto
   de muestreo, en operación y las cuatro condiciones de campo pertenecen a la
   **recepción** (§8.2); preservación, conmutador, cantidad y marca de aceite y
   locación pertenecen a la **ficha del equipo**.
5. **Endurecer solo estos cuatro, y solo en la ficha del equipo**: conmutador
   (100 % de dato real en el viejo), serie **o** TAG (al menos uno), tipo de
   equipo y tipo de aceite —los dos últimos porque eligen el cuadro de límites y
   su ausencia produce un diagnóstico sin norma (`EquipmentFieldRules.php:46-52`).
   Antes de endurecer el tipo de equipo, verificar que la migración no haya traído
   equipos apuntando al tipo `'-'` del viejo.
6. **Volver la razón de muestreo a un catálogo** de seis opciones y el punto de
   muestreo a catálogo, los dos con el "no aplica" resuelto como nulo y no como
   una fila `'-'`.
7. **Advertir sin bloquear** en las cuatro condiciones de campo: un indicador de
   "cabecera incompleta" en la entrega, que le llegue al muestreador. Es lo que
   genera dato.
8. **Recuperar las dos reglas perdidas** que no son obligatoriedad de campo:
   pruebas ≤ envases (§8.3) y la firma que autoriza el ingreso (§8.4).
9. **No tocar la bancada.** La hoja de trabajo del nuevo ya es más estricta que la
   del viejo, con la obligatoriedad por dato y verificada en el servidor (§4.5).
