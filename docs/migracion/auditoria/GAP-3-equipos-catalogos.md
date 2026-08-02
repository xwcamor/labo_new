# GAP-3 — Equipos, clientes y catálogos: lo que el viejo hace y el nuevo no

> **Alcance.** El bloque `im_management` del sistema Rails viejo dedicado a
> EQUIPOS, CLIENTES Y CATÁLOGOS: `transformers`, `customers`, los ocho catálogos
> (`transformer_types`, `transformer_preservations`, `transformer_points`,
> `transformer_oil_marks`, `transformer_oil_units`, `conmutation_types`,
> `marks`, `oil_types`), los dos importadores (`import_transformers`,
> `transformer_uploads`) y la bitácora de condiciones del laboratorio
> (`cro_temperatures`, `fiq_temperatures`), con sus vistas y sus modelos.
>
> **Contra qué se compara.** `app/Http/Controllers/BusinessManagement/`
> (`EquipmentController`, `CustomerController`, `CustomerHierarchyController`),
> los modelos `Equipment` / `Customer` / catálogos, las páginas Vue de
> `resources/js/Pages/{Equipment,Customers,…}`, `EquipmentImport` /
> `EquipmentImportTemplate` / `EquipmentExport` y las migraciones de `equipment`
> y `customers`.
>
> **Cuántos archivos del viejo se revisaron: 70.** 15 controladores (los 14 del
> alcance más `json_management/transformers_controller.rb`, que sirve el mismo
> modelo), 19 modelos, 34 vistas y parciales (incluidos los 4 parciales de
> `rem_reports` que consumen la bitácora), `config/routes.rb` y el volcado de
> estructura `docs/migracion/esquema/lab_app_development-estructura.sql`.
>
> **Regla.** Cada afirmación lleva `archivo:línea` de los dos lados. Donde no hay
> evidencia, se dice que no la hay. **El sistema viejo no se modificó.**

---

## 0. Antes de la lista: dos premisas que hay que corregir

Las dos aparecen en el encargo de esta auditoría y las dos son falsas contra el
código. Conviene despejarlas primero, porque si no se buscan huecos donde no los
hay.

### 0.1 `transformer_uploads` NO es carga de archivos ni de fotos del equipo

Es un **importador de Excel**, el segundo de los dos que tiene el viejo. El
controlador no guarda un archivo: lo pasa a `Transformer.import` y lo descarta
(`app/controllers/im_management/transformer_uploads_controller.rb:48`), y la
vista pide un `.xlsx` con la plantilla de importación
(`app/views/im_management/transformer_uploads/partials/_form_new.html.erb:32`).

**En el sistema viejo NO existe ningún adjunto de equipo.** Las cuatro únicas
subidas de archivo del repositorio son la firma del usuario
(`app/models/rem_user_signature.rb:5`), la firma del informe
(`app/models/rem_signature.rb:5`), el avatar (`app/models/user.rb:9`) y el
volcado del instrumento (`app/models/lab_file.rb:10`). No hay foto de placa, ni
plano, ni protocolo de fábrica colgando del transformador. Que el nuevo tampoco
los tenga **no es un hueco de migración**.

### 0.2 `fiq_temperatures.fiq_lab_pre` NO es presión atmosférica

El nombre de la columna engaña —`_pre` sugiere presión— pero la etiqueta del
formulario y la del informe dicen otra cosa:

| Columna | Etiqueta en el formulario | Cómo lo imprime el informe |
|---|---|---|
| `cro_temperatures.cro_lab_pre` | «Presión Atmosférica Lab (hPa)» (`cro_temperatures/partials/_form_new.html.erb:17`) | `… %> hPa` (`rem_reports/partials/_report_cromas.erb:474`) |
| `fiq_temperatures.fiq_lab_pre` | **«Temp. de Muestra en Lab (°C)»** (`fiq_temperatures/partials/_form_new.html.erb:17`) | `… %> °C` (`rem_reports/partials/_report_physicals.erb:357`) |

O sea: **la presión atmosférica existe solo en cromatografía**, y del lado
fisicoquímico ese campo es la temperatura de la muestra. Esto corrige
[`E-cobertura-tablas.md`](E-cobertura-tablas.md) §2.8 y su «LO QUE FALTA» #1, que
tratan a las dos como presión. Ver §11 de este documento.

---

## 1. Tabla resumen

| # | Qué falta | Clasificación | Consecuencia |
|---|---|---|---|
| 1 | El índice de Equipos perdió las 8 columnas de negocio del viejo (Cliente, Locación, Fabricante, Tipo de equipo, Tipo de aceite, Marca de aceite, Conmutador, Sistema de expansión) | **AUSENTE** | El listado de equipos no dice de qué cliente es cada uno: hay que abrir la ficha uno por uno |
| 2 | La ficha (Show) del equipo no muestra cliente, jerarquía ni ninguno de los cinco catálogos, aunque el backend ya los manda en el payload | **AUSENTE** | Se ve la placa de un equipo sin saber a quién pertenece ni con qué aceite trabaja |
| 3 | El panel de filtros del índice de Equipos sigue siendo el del scaffold: ofrece «Código» (columna inexistente, sin traducción) y no ofrece cliente / tipo / aceite / serie / TAG | **PARCIAL** | Un filtro visible que no filtra nada, y los filtros útiles escondidos en el constructor avanzado |
| 4 | El selector de columnas del export de Equipos ofrece `code` y `sort_order` por omisión y **no ofrece serie, TAG, cliente ni placa** | **PARCIAL** | El export por omisión sale con una columna vacía, dos encabezados con la clave de traducción cruda y sin la chapa del equipo |
| 5 | `TransformerPreservation` (Sistema de expansión) no tiene módulo CRUD: sin ruta, sin controlador, sin página, sin entrada de menú | **AUSENTE** | El laboratorio no puede agregar ni corregir un sistema de preservación sin un `seeder` y un despliegue |
| 6 | `TransformerPreservation::transformers()` apunta a `App\Models\Transformer`, clase que no existe en el nuevo | **AUSENTE** | Cualquier llamada a esa relación es un error fatal en tiempo de ejecución |
| 7 | El importador de equipos escribe la placa SOLO al crear: en una fila existente solo actualiza nombre, serie y TAG | **PARCIAL** | Reimportar el padrón con las tensiones corregidas no corrige nada y no avisa |
| 8 | La plantilla descargable de importación sigue con 4 columnas y no ofrece `voltage_kv` / `power_mva`, que el importador sí acepta | **PARCIAL** | Quien usa la plantilla oficial nunca importa la placa, aunque el motor la soporta |
| 9 | El importador exige el nombre del cliente repetido en cada fila; el viejo lo elegía una vez en el asistente | **PARCIAL** | Un padrón de 500 equipos de un solo cliente se rechaza entero por una diferencia de grafía en el nombre |
| 10 | El índice de Clientes perdió las columnas Nº Documento, Dirección y Contacto | **PARCIAL** | Buscar un cliente por RUC en el listado exige abrir el panel de filtros |
| 11 | La bitácora diaria de condiciones del laboratorio y su **precarga** | **DECIDIDO** (C1) | Corrección de alcance: la presión **ya tiene columna**; lo que falta es el registro por fecha y la precarga |
| 12 | El importador de equipos llena 6 campos de 15 | **DECIDIDO** (E §6) | Ya documentado; aquí solo se agrega qué validaba la vista previa vieja que la nueva no puede validar |
| 13 | `contact_info` del cliente | **DECIDIDO** (M §5.1) | Ya documentado |
| 14 | `oil_brand` no está en el formulario del equipo | **DECIDIDO** (M §8.1) | Ya documentado; se registra que la mitad del defecto (descarte silencioso) ya se corrigió |
| 15 | Todos los campos del equipo pasaron de obligatorios a `nullable` | **DECIDIDO** (M §3.4 y §5) | Ya documentado campo por campo |
| 16 | `db_systems` (procedencia), el nodo `-` automático de la jerarquía y el mapeo «tipo > 3 → Potencia» | **DECIDIDO** (D1, D2, D3) | Ya son decisiones del dueño |
| 17 | Placa, export sin placa y `sort_order` fantasma | **DECIDIDO** (D §4.3 y §5.4) | Ya documentado; el #4 de esta tabla agrega lo que D no vio |

Recuento: **3 AUSENTE · 6 PARCIAL · 8 DECIDIDO.** (El #6 se cuenta como AUSENTE
aparte del #5 porque es un defecto distinto: uno es una pantalla que falta, el
otro es código roto.)

---

## 2. Hueco 1 — El índice de Equipos perdió las ocho columnas de negocio

**Clasificación: AUSENTE.**

### Qué hace el viejo

El listado de transformadores tiene **doce columnas de datos** más la de
acciones (`app/views/im_management/transformers/partials/_table.html.erb:8-20`):

```
Cliente · Nº Serie · Locación · Fabricante · Tensión (Kv) · Potencia (MVA) ·
Tipo de Equipo · Tipo de Aceite · Marca de Aceite · Año de Fabricación ·
Conmutador · Sistema de Expansión
```

El JSON que las alimenta resuelve cada relación por su nombre
(`transformers/partials/_data_json.json.jbuilder:1-14`), y el controlador las
precarga con `includes` para no caer en N+1
(`app/controllers/im_management/transformers_controller.rb:103-105`).

### Qué hay en el nuevo

`resources/js/Pages/Equipment/config/columns.js:13-33` declara:

```
★ · Nombre · Serie · TAG · Tensión · Potencia · Fases · Año · Volumen de aceite ·
Ref. externa · (Workspace, solo super) · Activo · Creado
```

**No está el cliente, ni la ubicación, ni ninguno de los cinco catálogos.** Y no
es solo la vista: `EquipmentController::index()` ni siquiera los carga —
`$with = ['creator:id,name,email']` y, para super, `tenant:id,name`
(`app/Http/Controllers/BusinessManagement/EquipmentController.php:63-67`).

La celda principal usa el TAG como subtítulo del nombre
(`resources/js/Pages/Equipment/Index.vue:529`), que es lo más cerca que llega.

### Consecuencia

Un laboratorio que abre «Equipos» ve una lista de nombres y chapas sin saber de
qué empresa es cada uno, que es justamente el eje por el que se busca un equipo.

---

## 3. Hueco 2 — La ficha del equipo no muestra ni el cliente ni los catálogos

**Clasificación: AUSENTE.**

### Qué hace el viejo

La ficha (`transformers/partials/_form_show.html.erb`) es el mismo formulario en
solo lectura: los quince campos, con el cliente arriba de todo
(`transformers/partials/_form_new.html.erb:12`) y los cinco catálogos en su
lugar (`:75`, `:85`, `:98`, `:118`, `:128`).

### Qué hay en el nuevo

El backend hace bien su parte: `EquipmentController::payload()`
(`.../EquipmentController.php:499-548`) carga y serializa `customer`,
`location`, `area`, `substation`, `equipment_type`, `oil_type`, `brand`,
`tap_changer_type`, `preservation`, `oil_volume_unit` y `service_state`.

La pantalla no los pinta. `resources/js/Pages/Equipment/Show.vue:105-152` tiene
exactamente once celdas: ID, Slug (solo super), Nombre, Serie, TAG, Tensión,
Potencia, Fases, Año, Volumen de aceite, Ref. externa y Estado.

El formulario de edición sí los tiene todos
(`resources/js/Pages/Equipment/Form.vue:237-383`), así que el dato se carga y se
guarda: solo no se puede **leer** sin entrar a editar.

### Consecuencia

Para responder «¿de quién es este transformador y con qué aceite trabaja?» hay
que abrir el formulario de edición de un registro que quizá no se quiere tocar.

---

## 4. Hueco 3 — El panel de filtros del índice de Equipos es el del scaffold

**Clasificación: PARCIAL.**

### Qué hace el viejo

DataTables con **una casilla de búsqueda por cada una de las doce columnas**,
inyectadas al cargar (`transformers/partials/_table.html.erb:23-37` declara la
fila de filtros y `:47-50` la convierte en `input`), y el filtrado se aplica
columna por columna (`:121-127`). Se pueden mostrar u ocultar desde el índice
(`transformers/index.html.erb:40-41`).

### Qué hay en el nuevo

`resources/js/Pages/Equipment/config/filters.js:7-16` sigue siendo la copia del
scaffold de catálogos —su propio comentario lo delata: «catálogo global de
marcas»— y ofrece:

```js
{ key: 'name', … }, { key: 'code', label: t('equipment.code'), type: 'text' },
{ key: 'is_active', … }, { key: 'created_at', … }, { key: 'only_favorites', … }
```

Dos problemas concretos:

1. **`code` no existe.** La migración lo excluye a propósito
   (`database/migrations/2026_07_28_061051_create_equipment_table.php:21`) y
   `Equipment::scopeFilter()` lo sacó explícitamente
   (`app/Models/Equipment.php:250-253`). El backend **ignora** el parámetro, así
   que el campo se puede llenar y no pasa nada.
2. **No tiene traducción.** No hay clave `equipment.code` ni en
   `resources/lang/es/equipment.php` ni en `resources/lang/en/equipment.php`, o
   sea que el filtro se rotula literalmente `equipment.code` en pantalla.

Lo que el backend **sí** sabe filtrar y el panel no ofrece: `serial`, `tag`
(`app/Models/Equipment.php:254-262`), `customer_id`, `equipment_type_id`,
`oil_type_id` (`:266-268`).

**Atenuante honesto:** esos cinco sí están en el constructor de filtros
avanzados (`Equipment::filterSchema()`, `app/Models/Equipment.php:326-336`, con
sus opciones cargadas en `EquipmentController.php:110-119`). O sea que filtrar
por cliente es posible, pero hay que abrir otro cajón y armar una cláusula.

### Consecuencia

El filtro que se ve por omisión tiene un campo que no hace nada y le faltan los
tres ejes por los que un laboratorio busca un equipo.

---

## 5. Hueco 4 — El export de Equipos ofrece dos columnas fantasma y ninguna de la chapa

**Clasificación: PARCIAL.** (Amplía lo que
[`D-placa-equipos.md`](D-placa-equipos.md) §4.3(c) y §5.4 ya señalaron: D vio el
`sort_order` residual y la falta de la placa, pero no revisó el selector de
columnas.)

### Qué hace el viejo

El índice exporta **las doce columnas visibles** en cinco formatos —copiar, CSV,
Excel, PDF e imprimir— con `exportOptions: { columns: ':not(:last-child)' }`
(`transformers/partials/_table.html.erb:93-105`), o sea todo menos la de
acciones. Lo que se ve es lo que se baja.

### Qué hay en el nuevo

`resources/js/Pages/Equipment/config/exports.js:9-18` ofrece, con `default: true`
en las cuatro primeras:

```
name · code · sort_order · is_active · (tenant, solo super) · created_at ·
updated_at · creator
```

Contra el generador `EquipmentExport`
(`app/Exports/BusinessManagement/Equipment/EquipmentExport.php:52-63`), cuyo
`columnDefs` conoce `id, name, serial, tag, sort_order, is_active, slug,
created_at, updated_at, creator, tenant`:

- **`code`** no está en `columnDefs`, así que el filtro de `activeColumns`
  (`:66-69`) lo descarta en silencio: se ofrece marcada por omisión y no produce
  nada.
- **`sort_order`** sí está en `columnDefs` (`:56`) pero no es columna de la
  tabla: sale vacía por el `?? ''`, y su encabezado es
  `__('equipment.sort_order')`, clave que **no existe en ningún archivo de
  idioma** → el Excel sale con el literal `equipment.sort_order` como título de
  una columna vacía.
- **`serial` y `tag` existen en el generador y no se ofrecen en el selector**, y
  no hay ninguna columna de cliente, jerarquía, placa ni catálogos.

### Consecuencia

El export por omisión del padrón de equipos entrega nombre, una columna vacía con
un encabezado sin traducir, el estado y las fechas. Ni la serie con la que el
laboratorio identifica el equipo.

---

## 6. Hueco 5 — El catálogo «Sistema de expansión» no tiene pantalla

**Clasificación: AUSENTE.**

### Qué hace el viejo

`app/controllers/im_management/transformer_preservations_controller.rb` es un
CRUD completo: `index` (`:17`), `show` (`:28`), `new` (`:38`), `edit` (`:48`),
`create` (`:59`), `update` (`:75`), `delete` (`:89`) y `destroy` (`:101`), con
sus diez vistas (`app/views/im_management/transformer_preservations/`) y su ruta
declarada junto a los demás catálogos (`config/routes.rb:62-68`).

### Qué hay en el nuevo

La tabla existe
(`database/migrations/2026_05_30_100050_create_transformer_preservations_table.php:17`),
el modelo existe y el desplegable del formulario del equipo la consume
(`app/Http/Controllers/BusinessManagement/EquipmentController.php:200`). Pero:

- `grep -rn "preservation" routes/` → **cero resultados**.
- No hay `TransformerPreservationController` en
  `app/Http/Controllers/BusinessManagement/`.
- No hay `resources/js/Pages/TransformerPreservations/`.
- El propio modelo lo declara: «Catálogo GLOBAL (sin tenant). **Sin módulo CRUD
  por ahora**» (`app/Models/TransformerPreservation.php:16`).

Las cuatro filas de fábrica solo entran por
`database/seeders/TransformerPreservationsSeeder.php:17-22`.

Es el único de los ocho catálogos del alcance sin pantalla: los otros siete
tienen módulo propio (`EquipmentTypes`, `OilTypes`, `Brands`, `TapChangerTypes`)
o se administran en «Listas del informe» (`ReportCatalogs`, para marca de aceite,
unidad de volumen y punto de muestreo — ver [`E`](E-cobertura-tablas.md) §3).

### Consecuencia

Agregar o corregir un sistema de preservación exige editar un `seeder` y
desplegar, que es exactamente lo que este proyecto vino a eliminar.

---

## 7. Hueco 6 — `TransformerPreservation::transformers()` apunta a una clase inexistente

**Clasificación: AUSENTE** (código roto, no una funcionalidad faltante).

`app/Models/TransformerPreservation.php:53-56`:

```php
public function transformers(): HasMany
{
    return $this->hasMany(Transformer::class, 'transformer_preservation_id');
}
```

`App\Models\Transformer` **no existe**: en `app/Models/` el equipo se llama
`Equipment.php`, y la migración explica por qué se renombró
(`database/migrations/2026_07_28_061051_create_equipment_table.php:11-15`).

En el viejo la relación equivalente es `has_many :transformers`
(`app/models/transformer_preservation.rb:3`) y sí resuelve.

### Consecuencia

Cualquier código que quiera contar «cuántos equipos usan este sistema de
preservación» —lo primero que va a necesitar la pantalla del hueco 5— revienta
con «Class not found» en vez de devolver un número.

---

## 8. Hueco 7 — Reimportar el padrón no corrige la placa

**Clasificación: PARCIAL.**

### Qué hace el viejo

El asistente **no actualiza**: crea. `import_transformers_controller.rb:98-103`
excluye del lote las series que ya existen (`num_serie_not_in`), y el paso 4
inserta y reporta «La Serie X con el Tag Y ya existe» si choca
(`:161-167`). O sea que el viejo era honesto: no pretendía actualizar.

### Qué hay en el nuevo

`EquipmentImport` sí tiene modo `update_or_create`
(`app/Imports/BusinessManagement/Equipment/EquipmentImport.php:78`), pero cuando
la fila ya existe el parche es de tres campos
(`.../EquipmentImport.php:220-226`):

```php
if ($existing->name !== $name)                        $patch['name']   = $name;
if ($serial !== null && $existing->serial !== $serial) $patch['serial'] = $serial;
if ($tag !== null && $existing->tag !== $tag)          $patch['tag']    = $tag;
```

La placa se escribe **solo en la rama de alta** (`:254-259`,
`voltage_kv_hv/lv/tv` y `power_mva/_2/_3`). No hay aviso: la fila se cuenta como
`updated` en el resumen (`:228-234`) aunque la tensión de la planilla se haya
descartado.

### Consecuencia

El caso más natural del importador —«ya cargué el padrón, ahora subo la planilla
con las placas»— no hace nada y el resumen dice que actualizó todo.

---

## 9. Hueco 8 — La plantilla oficial de importación no ofrece la placa

**Clasificación: PARCIAL.**

`EquipmentImport` acepta seis columnas y parte la placa con `PlateValue`
(`.../EquipmentImport.php:145-150`), pero
`app/Exports/BusinessManagement/Equipment/EquipmentImportTemplate.php:35`
sigue devolviendo cuatro:

```php
['name', 'customer', 'serial', 'tag'],
```

Su propio bloque de documentación (`:15-19`) también las lista solo cuatro, y el
estilo del encabezado está cableado al rango `A1:D1` (`:48`) y a las columnas
`A..D` (`:56`), así que agregar dos columnas exige tocar tres lugares.

El viejo, en cambio, distribuía **una sola plantilla** con las catorce columnas
(un enlace de Google Drive en
`transformer_uploads/partials/_form_new.html.erb:3` y en
`import_transformers/partials/_form_step2.html.erb:40`), y la vista previa la
validaba entera.

### Consecuencia

Quien descarga la plantilla del sistema nunca importa tensión ni potencia, aunque
el importador las acepta desde hace tiempo. Es una función construida y no
alcanzable desde la pantalla.

---

## 10. Hueco 9 — El cliente se elige una vez en el viejo y se repite por fila en el nuevo

**Clasificación: PARCIAL.**

### Qué hace el viejo

Los **dos** importadores piden el cliente ANTES del archivo:

- Asistente: paso 1 = desplegable de clientes
  (`import_transformers/partials/_form_step1.html.erb:28`), paso 2 = el archivo
  (`_form_step2.html.erb:33`), y el `customer_id` viaja por la URL hasta la
  inserción (`import_transformers_controller.rb:52-54`, `:144`).
- Importación directa: se elige el cliente
  (`transformer_uploads/partials/_form_index.html.erb:6`) y se sube el archivo
  contra ese cliente (`transformer_uploads_controller.rb:45-48`;
  `app/models/transformer.rb:53` hace `Customer.find(customer_id)`).

La planilla, por eso, **no tiene columna de cliente**
(`app/models/import_transformer.rb:22-53`: catorce columnas, ninguna es el
cliente).

### Qué hay en el nuevo

`customer` es una **columna obligatoria de cada fila**, resuelta por nombre
exacto —insensible a mayúsculas y acentos, pero exacto—
(`.../EquipmentImport.php:128-136` y `:324-342`), y la fila se rechaza si no
coincide (`:130-135`). No hay forma de decir «todo este archivo es de Abengoa».

El porqué está escrito y es razonable (`:318-323`: no crear clientes al vuelo).
Lo que no está resuelto es el caso normal del laboratorio, que carga el padrón de
un cliente por vez.

### Consecuencia

Un padrón de 500 equipos con «Abengoa Perú S.A.» donde el catálogo dice «ABENGOA
PERU SA» se rechaza fila por fila, 500 veces, por un dato que el operador ya
sabía antes de abrir el archivo.

---

## 11. Hueco 10 — El índice de Clientes perdió tres columnas

**Clasificación: PARCIAL.**

### Qué hace el viejo

Cinco columnas de datos, todas con casilla de búsqueda propia
(`customers/partials/_table.html.erb:8-13` y `:15-22`):

```
País · NºDocumento · Cliente · Dirección · Contacto
```

### Qué hay en el nuevo

`resources/js/Pages/Customers/config/columns.js:15-30`:

```
★ · Nombre · País · (Workspace) · Ubicaciones · Áreas · Subestaciones ·
Equipos · Activo · Creado
```

Los cuatro contadores de la jerarquía son una mejora clara sobre el viejo. Pero
**`cod` (el Nº Documento / RUC) y `address` no son columnas**, y `contact_info`
directamente no existe como campo (hueco DECIDIDO, ver §13).

`cod` sí es filtro (`resources/js/Pages/Customers/config/filters.js:10`) y sale
en la ficha (`resources/js/Pages/Customers/Show.vue:180-181`), así que el dato no
se perdió: solo dejó de verse en el listado.

### Consecuencia

Identificar un cliente por su RUC desde el listado —el caso de una recepción que
llega con la factura en la mano— exige abrir el cajón de filtros o entrar a la
ficha.

---

## 12. Hueco 11 — La bitácora diaria de condiciones y su precarga

**Clasificación: DECIDIDO** (es C1 de [`12-CHECKLIST.md`](../12-CHECKLIST.md) y
la tabla 37-38 de [`E`](E-cobertura-tablas.md)). **Se registra aquí solo porque
el alcance del pendiente cambió y porque el mecanismo de precarga no estaba
descrito.**

### Corrección 1: la presión atmosférica YA tiene columna

`E-cobertura-tablas.md` §«LO QUE FALTA» #1 dice que «no hay ninguna columna de
presión» en el sistema nuevo. **Ya no es cierto**, y está completamente cableada:

| Pieza | Archivo:línea |
|---|---|
| Columna | `database/migrations/2026_07_30_120000_add_lab_pressure_to_worksheets.php:43` (`decimal(6,1)`) |
| Asignable | `app/Models/Worksheet.php:94` |
| Validada | `app/Http/Controllers/LabManagement/WorksheetController.php:129` y `:215` (`numeric min:500 max:1100`) |
| En el formulario | `resources/js/Pages/Worksheets/Form.vue:233-238` |
| En el informe moderno | `app/Services/Lab/TestReportPayload.php:411, 424-428` |
| En el informe clásico | `app/Services/Lab/LegacyReportRenderer.php:308, 321, 415-418` |

Y la temperatura de la muestra —el `fiq_lab_pre` del viejo, ver §0.2— es
`worksheets.sample_temp_c`
(`database/migrations/2026_07_28_170000_add_report_header_fields.php:61`),
resuelta en C2 del checklist.

### Corrección 2: lo que queda es el registro por fecha y la PRECARGA

Lo que el viejo tiene y el nuevo no:

1. **Dos tablas con una fila por día**, con unicidad por fecha
   (`app/models/cro_temperature.rb:11`, `app/models/fiq_temperature.rb:11`) y
   CRUD propio (`cro_temperatures_controller.rb:17-101`,
   `fiq_temperatures_controller.rb:17-101`), listadas por fecha descendente
   (`cro_temperatures_controller.rb:105`). Estructura en
   `docs/migracion/esquema/lab_app_development-estructura.sql:81-90` y `:113-122`;
   **100 filas reales en cada una**.
2. **La precarga del formulario del informe**, con **dos reglas de emparejamiento
   distintas** que no estaban documentadas:
   - Cromatografía: busca la fila cuya fecha es **la fecha de corrida de la
     bancada** de ese ensayo
     (`rem_reports/partials/_form_add_details_cromas.html.erb:520`:
     `date_temperature = @rem_report_detail.cro_lab_detail.lab.date_rehearsal`).
   - Fisicoquímicos: acepta **la fecha de corrida O la fecha de recepción**
     (`rem_reports/partials/_form_add_details_physicals.html.erb:601-607`), y la
     fecha de corrida sale del acidez o, si no hay, del factor de potencia a
     25 °C.
   - En los dos casos la precarga solo se aplica si el valor todavía está vacío
     (`_form_add_details_cromas.html.erb:519`,
     `_form_add_details_physicals.html.erb:606`), así que lo tipeado gana.

En el nuevo, los tres valores se tipean en cada hoja de bancada
(`resources/js/Pages/Worksheets/Form.vue`) y no existe ninguna tabla de bitácora:
`ls database/migrations/ | grep -i "condition\|ambient\|temperature"` no devuelve
nada.

### Consecuencia

El analista repite tres números en cada hoja del día en lugar de cargarlos una
vez, y a un auditor de ISO 17025 no se le puede mostrar «qué presión, temperatura
y humedad hubo el 14 de marzo» sin recorrer las hojas de ese día.

---

## 13. Lo ya documentado — DECIDIDO, sin repetir

Cada línea remite a dónde está la evidencia completa. Se listan para que la
cobertura de este bloque quede cerrada, no para volver a discutirlas.

| Tema | Dónde está documentado | Estado |
|---|---|---|
| El importador nuevo llena 6 campos de 15 (faltan tipo de equipo, marca, conmutador, preservación, tipo de aceite, marca de aceite, unidad, volumen y antigüedad) | [`E`](E-cobertura-tablas.md) §2.7 fila 36 y «LO QUE FALTA» #6 | DECIDIDO |
| `contact_info` del cliente no tiene columna en el nuevo | [`M`](M-campos-obligatorios.md) §5.1 y §7 | DECIDIDO |
| `oil_brand` no está en el formulario del equipo | [`M`](M-campos-obligatorios.md) §5.4 y §8.1 | DECIDIDO. **Media corrección:** el descarte silencioso por asignación masiva ya se arregló (`app/Models/Equipment.php:69` la incluye en `$fillable`), pero sigue sin estar en `Equipment/Form.vue` ni en `EquipmentFieldRules.php:24-92`; solo se puede cargar desde el formulario del informe (`SampleReportController.php:780`) |
| Los quince campos del formulario del equipo pasaron de obligatorios a `nullable` | [`M`](M-campos-obligatorios.md) §3.4 (tabla campo por campo) y §5 (los quince aflojes con su número real) | DECIDIDO |
| Placa `num_ten`/`num_pot` como texto libre con barras, el `500/1.73`, el `'-'` que valía 0 kV y el truncamiento a entero | [`D`](D-placa-equipos.md) completo | DECIDIDO |
| `EquipmentExport` sin columnas de placa y con `sort_order` residual | [`D`](D-placa-equipos.md) §4.3(c) y §5.4(1) | DECIDIDO — ampliado en §5 de este documento |
| `EquipmentImportTemplate` sin las columnas de placa | [`D`](D-placa-equipos.md) §5.4(2) | DECIDIDO — ampliado en §9: el importador ya cambió y la plantilla no |
| Punto de muestreo, marca de aceite y unidad de volumen degradados a texto libre | [`E`](E-cobertura-tablas.md) §3 y C3 del [`checklist`](../12-CHECKLIST.md) | RESUELTO 2026-07-31 (`report_catalogs`) |
| Falta el 21º tipo de equipo (`Regulador de Voltaje`) | [`E`](E-cobertura-tablas.md) «LO QUE FALTA» #7 | RESUELTO — está en `database/seeders/LabCatalogsSeeder.php` con la nota de por qué faltaba |
| `db_systems` — la procedencia del registro (`app/models/customer.rb:26`, `db_system_id = 2`) | D2 del [`checklist`](../12-CHECKLIST.md) | DECIDIDO (del dueño) |
| El nodo `-` de ubicación y área que el viejo fabricaba al crear un cliente (`app/models/customer.rb:30-33`) y al empujar a TrafoDex (`trapp_management/import_transformers/partials/_form_step3.html.erb:53-68`) | D3 del [`checklist`](../12-CHECKLIST.md) y `docs/API-LABORATORIO.md` | DECIDIDO — se rechaza a propósito |
| «Tipo de equipo mayor a 3 → Potencia» (`app/models/transformer_type.rb:15-28`) y la columna `comment = TRAPP` | D1 del [`checklist`](../12-CHECKLIST.md), [`E`](E-cobertura-tablas.md) §2.7 fila 31 | DECIDIDO (del dueño) |

---

## 14. Lo que NO hay que portar (defectos del viejo hallados en este barrido)

Se anotan para que nadie los reconstruya creyendo que son funcionalidad perdida.

1. **La columna «Sistema de Expansión» del índice viejo muestra la marca de
   aceite.** `transformers/partials/_table.html.erb:71` pinta
   `transformer_oil_mark_name` en la duodécima columna, mientras el JSON sí
   calcula `transformer_preservation_name`
   (`_data_json.json.jbuilder:14`) y **nadie lo consume**. O sea que la marca de
   aceite se ve dos veces y el sistema de expansión nunca.
2. **`json_management/transformers_controller.rb:8`** hace `includes` de
   `:connection_type` y `:customer_substation`, dos relaciones que
   `app/models/transformer.rb:3-11` **no declara**. Es un `500` esperando a que
   alguien llame a ese endpoint.
3. **El campo «Punto de Muestreo» del formulario del equipo está comentado**
   (`transformers/partials/_form_new.html.erb:156-164`) pese al `belongs_to`
   obligatorio del modelo (`app/models/transformer.rb:8`). Ya está en
   [`M`](M-campos-obligatorios.md) §12 con el número: 100 de 100 equipos lo
   tienen nulo.
4. **La creación del equipo pierde el motivo del rechazo.** El controlador
   informa siempre «Registro duplicado (Nº Serie)» ante cualquier fallo de
   guardado (`transformers_controller.rb:65`, `:76`), aunque la única validación
   del modelo es sobre `num_tag` (`app/models/transformer.rb:24`).
5. **Los dos importadores atrapan cualquier excepción y muestran un solo texto
   genérico** listando los siete catálogos
   (`transformer_uploads_controller.rb:56-61`,
   `import_transformers_controller.rb:247-252`), sin decir qué fila ni qué valor
   falló. El importador nuevo, con su vista previa fila por fila
   (`EquipmentImport::summary()`), es estrictamente mejor.
6. **El paso 3 del asistente CREA datos desde la vista**: el parcial del puente a
   TrafoDex inserta ubicaciones, áreas y subestaciones mientras renderiza
   (`trapp_management/import_transformers/partials/_form_step3.html.erb:62-68`),
   así que abrir la pantalla dos veces escribía dos veces. Es el mismo antipatrón
   de `rem_report_detail_issues` ya registrado en [`E`](E-cobertura-tablas.md).

---

## 15. Procedencia

| Qué se leyó del viejo | Para qué |
|---|---|
| `app/controllers/im_management/{transformers,customers,transformer_types,transformer_preservations,transformer_points,transformer_oil_marks,transformer_oil_units,conmutation_types,marks,oil_types,import_transformers,transformer_uploads,cro_temperatures,fiq_temperatures}_controller.rb` | Acciones, permisos, catálogos que carga cada formulario |
| `app/controllers/json_management/transformers_controller.rb` | El otro consumidor del mismo modelo |
| `app/models/{transformer,customer,customer_location,customer_area,customer_substation,import_transformer,cro_temperature,fiq_temperature,transformer_type,transformer_preservation,mark,oil_type,conmutation_type,transformer_oil_mark,transformer_oil_unit,transformer_point,country,db_system,lab_file}.rb` | Validaciones, relaciones, `import`, ganchos de creación y adjuntos |
| `app/views/im_management/transformers/**` (5 archivos) | Columnas, filtros, botones, exportaciones, campos del formulario |
| `app/views/im_management/customers/**` (4 archivos) | Ídem |
| `app/views/im_management/{cro,fiq}_temperatures/**` (5 archivos) | Etiquetas reales de la bitácora y sus unidades |
| `app/views/im_management/import_transformers/**` (3 archivos) | Las 14 columnas del asistente y su validación celda por celda |
| `app/views/im_management/transformer_uploads/**` (2 archivos) | El segundo importador y su plantilla |
| Los 8 `partials/_table.html.erb` de los catálogos y 3 de sus `_form_new` | Confirmar que todos son «solo nombre» |
| `app/views/im_management/rem_reports/partials/{_form_add_details_cromas,_form_add_details_physicals,_report_cromas,_report_physicals}` | Cómo se precarga y cómo se imprime la bitácora |
| `config/routes.rb:62-84` | Qué acciones expone cada recurso |
| `docs/migracion/esquema/lab_app_development-estructura.sql` | Tipos y columnas reales de `transformers`, `customers`, `cro_temperatures`, `fiq_temperatures` y los catálogos |

Del sistema nuevo: `EquipmentController`, `CustomerController`,
`CustomerHierarchyController`, `Equipment`, `Customer`,
`TransformerPreservation`, `EquipmentFieldRules`, `StoreCustomerRequest`,
`EquipmentImport`, `EquipmentImportTemplate`, `EquipmentExport`, las páginas Vue
de `Equipment` (Index, Form, Show, `config/{columns,filters,exports}.js`) y
`Customers` (columns, filters, Show), las migraciones de `equipment`,
`customers`, la jerarquía, `report_catalogs` y `worksheets`, los seeders
`LabCatalogsSeeder` y `TransformerPreservationsSeeder`, y `routes/business_management.php`.

**No se modificó ningún archivo del sistema viejo.**
