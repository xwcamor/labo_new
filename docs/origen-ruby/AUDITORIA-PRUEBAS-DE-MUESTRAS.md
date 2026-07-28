# Auditoría del módulo Pruebas de Muestras del sistema Rails (2019)

## Qué es este documento

Lectura del código del módulo `pr_management` ("Pruebas de Muestras") del
repositorio viejo en Ruby on Rails — <https://github.com/xwcamor/trapp> —, hecha
en julio de 2026 para decidir qué se porta y qué NO se reproduce en Laravel.

## Advertencia sobre la fuente de verdad

`db/schema.rb` del sistema viejo está **DESACTUALIZADO**: le faltan columnas que
sí existen en la base real, porque la base se modificó a mano. La configuración
real del sistema —fórmulas, columnas, IDs— vive **EN LA BASE**, no en el código.

Ante cualquier duda mandan el volcado de estructura en
[`docs/migracion/esquema/`](../migracion/esquema/) y el código Ruby.
**Nunca** `db/schema.rb`, `db/migrate/` ni `db/seeds.rb`.

## Para qué sirve

- La sección **6 ("Comportamientos sutiles, trucos y bugs")** es la lista de lo
  que NO hay que reproducir.
- La sección **"Lo que no se entiende"** son preguntas abiertas para el
  laboratorio.

---

He terminado la investigación. Aclaro de entrada dos cosas: **`db/schema.rb` está desactualizado** (le faltan `lab_details.num_test`, `lab_files`/`lab_file_details`, `patron_tendences`, `is_imported`/`imported_value`/`imported_remove_value`/`report_use`, `is_grouped`/`has_reuse`/`unit_name_amchart`/`description` en `lab_category_details`, `is_hidden`/`applicability_flag`/`num_pos` en las opciones) — la BD real fue modificada a mano (ver `/home/user/labo_old/comentarios.md:63-65`). Y **la configuración real vive en la base, no en el código**: fórmulas, columnas e IDs. Para migrar hay que dumpear la BD, no leer solo el repo.

---

# 1. Hoja de trabajo (`labs`) — el flujo real

## 1.1 Modelo de datos (4 niveles)

```
lab_category_detail_type  (categoría: Fisicoquímico, Cromatografía, ...)
└─ lab_category_detail    (= "Módulo"/prueba: Número Ácido, Rigidez Dieléctrica, Furanos...)   ← plantilla
   ├─ lab_category_sub_detail   (= definición de COLUMNA)                                      ← plantilla
   │  └─ lab_category_sub_detail_option (opciones del select)
   └─ lab   (= HOJA DE TRABAJO: 1 módulo + 1 fecha)
      └─ lab_detail  (= FILA: Patrón / Muestra / Duplicado)
         └─ lab_sub_detail (= CELDA: valor de una columna en esa fila)
```

Una `lab` **no** es "una muestra": es **un módulo + una fecha de ensayo**. Todas las filas de ese día para esa prueba cuelgan de ahí.

## 1.2 Crear una hoja de trabajo

`GET /pr_management/templates/lab_category_details/:lab_category_detail_id/labs/new`
→ `app/views/pr_management/templates/labs/partials/_form_new.html.erb`

Pide **un solo campo editable**: `date_rehearsal` (fecha, `date_field`, requerido, línea 35-37). Categoría y módulo se muestran como texto; `user_id` y `lab_category_detail_id` van en hidden (líneas 1-2). Acepta `?date=` en la query para pre-cargar la fecha (línea 34).

Defaults en `app/models/lab.rb:44-47`: `state = 1` (Desbloqueado), `deleted = 0`.

**Unicidad**: `app/models/lab.rb:12` — `date_rehearsal` único por `lab_category_detail_id` entre los no borrados. Si choca, `labs_controller.rb:133` muestra "Registro duplicado (Fecha duplicada)".

## 1.3 Agregar filas (`lab_details`) y `lab_detail_type_id`

`GET .../labs/:lab_id/lab_details/new` → `templates/lab_details/partials/_form_new.html.erb`.

Los 3 tipos están en `app/models/lab_detail.rb:46-48` y `db/seeds.rb:409-413`:

| id | nombre | rol |
|----|--------|-----|
| 1 | **Patrón Control** | material de referencia; **es lo único que se grafica en Tendencias** |
| 2 | **Muestra** | muestra real de cliente; **la única con `num_test` y validación de duplicados** |
| 3 | **Duplicado** | réplica de control interno |

**Regla de orden fuerte** (`_form_new.html.erb:28-38`): si NO existe todavía al menos 1 Patrón *y* 1 Duplicado en esa `lab`, el select **solo ofrece Patrón(1) y Duplicado(3)** — no se puede cargar una Muestra. Una vez que hay ambos, el select ofrece los 3 con "Muestra" preseleccionada. El `show` refuerza esto con banners rojos "Debe existir al menos 1 TIPO DUPLICADO / PATRON" (`labs/partials/_form_show.html.erb:14-24`).

Excepción: si `lab_category_detail.is_grouped == 1`, el select se fuerza a **solo "Muestra"** (`_form_new.html.erb:23-26`). La columna en el admin se rotula "No usa Duplicados / No usa Patrón Control" (`configurations/category_details/partials/_form_new.html.erb:9`).

**`num_test`**: no es una columna de la plantilla, es una columna real de `lab_details`. Se llena **solo por JavaScript** copiando el valor de `#col1` (la primera columna, "Nº de Muestra") y quitando espacios (`_form_new.html.erb:72-84`). El input `#num_test` es `readonly`. Si el usuario tiene JS desactivado o pega el valor sin disparar `keyup`, `num_test` queda vacío. Validación de duplicado en `lab_detail.rb:26-42`: solo aplica a `lab_detail_type_id == 2`, buscando el mismo `num_test` dentro del mismo `lab_category_detail_id` (no solo dentro de la misma fecha).

**Efecto lateral no obvio** — `lab_detail.rb:84-117` (`update_rem_jobs`, after_create/after_update): al crear una fila tipo Muestra, parsea `num_test` como `"AÑO-NUM"` y marca `rem_jobs.task_done = 1` + `lab_detail_id` del trabajo correspondiente. Al borrar, lo revierte. Esto acopla Pruebas de Muestras con el módulo de Reportes (`rem_*`). El propio comentario admite que "No funciona si el usuario crea antes de que registre el ingreso de la muestra" (línea 86). Además está construido con SQL interpolado a mano (inyección).

## 1.4 Carga de valores de cada columna (`lab_sub_details`)

Todo pasa por **nested attributes** de Rails: `lab_detail[lab_sub_details_attributes][<lab_category_sub_detail_id>][name]`, más un hidden con `lab_category_sub_detail_id` (`_form_new_nested.erb:1-8`). En edición se agrega un hidden con el `id` del `lab_sub_detail` existente (`_form_edit_nested.html.erb:6-7`).

El widget se elige por `lab_category_sub_detail_type_id` (`_form_new_nested.erb:10,30,51,58`):

| tipo | nombre (`db/seeds.rb:179-184`) | widget | qué se guarda en `lab_sub_details.name` |
|---|---|---|---|
| 1 | Texto | `input type=text` | el texto |
| 2 | Número | `input type=text` + `data-parsley-type="number"` | el número **como string** |
| 3 | Selección | `<select>` | **el ID de `lab_category_sub_detail_option`**, no el texto |
| 4 | Fecha | `input type=datetime-local` | el string datetime |

Punto crítico: **`lab_sub_details.name` es un `string` para todo**. Los números se guardan como texto (con lo que "1.5E-03", "<0.5", "NaN" conviven), y los selects guardan el ID de la opción. Todos los renders hacen ese switch a mano (`lab_details/partials/_table.html.erb:36-46`, `labs/partials/_table.html.erb:61-74`, `_display_missing_values.html.erb:21-34`, `_xls_records.erb:34-47`).

Los `id="colN"` de los inputs se generan por **índice de posición** (`index + 1` sobre `@lab_category_sub_details` ordenado por `num_pos`), y las fórmulas de cálculo referencian esos IDs. Por eso el `README_ADD_COLUMNS.md:8` grita "**LA COLUMNA RESULTADO SIEMPRE ES LA ÚLTIMA**": reordenar columnas rompe todas las fórmulas silenciosamente.

## 1.5 Cálculos (`is_blur` + `blur_calculation`)

- `lab_category_details.blur_calculation` es un **bloque de JavaScript crudo guardado en la BD**, inyectado con `html_safe` dentro de `function calculate(){...}` (`lab_details/partials/_calculation_script.html.erb:1-10`).
- Se renderiza solo si hay alguna columna con `is_blur=1` (`_form_has_calculation.html.erb:3-6`).
- Cada input con `is_blur=1` recibe `onblur="calculate()"`; además corre en `window.onload`.

Ejemplos reales en `db/seeds.rb:151` (Número Ácido: `(col8-col6)*col5/col7` → `col9`, 3 decimales) y `db/seeds.rb:162` (Furanos: fórmula de Shen con `log10`).

**Todo el cálculo es del lado cliente.** No hay recálculo ni verificación en servidor. El campo de resultado suele tener `is_blocked=1` (readonly), pero readonly es solo HTML — un POST directo puede escribir cualquier cosa. **Esto conviene NO reproducir**: en Laravel la fórmula debería evaluarse en el servidor (o al menos revalidarse).

## 1.6 `state`, bloqueo y validación

`labs.state`:
- **0 = "Bloqueado"**, **1 = "Desbloqueado"** (`app/models/lab.rb:32-37`). Las líneas comentadas 33-34 muestran que antes significaba "Validado / Pendiente Validar" — el concepto mutó a un candado.

Efecto de `state = 0` (bloqueado), **puramente visual**:
- `labs/partials/_table.html.erb:93,100,108,114`: se ocultan los botones Agregar/Editar/Eliminar de la fecha.
- `lab_details/partials/_table.html.erb:54`: se ocultan Editar/Eliminar de cada fila.
- `_display_missing_values.html.erb:41`: idem.

**No hay ninguna comprobación de `state` en los controladores.** `LabsController#update/#destroy` (líneas 140-158) y `LabDetailsController#update/#destroy` (100-119) no lo miran. Un POST directo a una hoja bloqueada la modifica sin problema. **Bug a no reproducir: el bloqueo debe validarse en servidor.**

**Quién valida / bloquea** (`labs_controller.rb:116-123` + `labs/validate.html.erb`):
- Pantalla `GET .../labs/:lab_id/validate`. El link solo se muestra a usuarios con `access_id = 30` — cuyo nombre en `db/seeds.rb:45` es **"SP- Validar"** (perfil Supervisor). El comentario `_table.html.erb:99` dice explícitamente "No cambiar Permiso Validar".
- **Sin embargo, la acción `validate` chequea el permiso 36 (Editar), no el 30** (`labs_controller.rb:117`). El botón está escondido para no-supervisores pero la URL es accesible para cualquiera con permiso de edición. **Bug de autorización a no reproducir.**
- El formulario (`partials/_form_validate.html.erb:2,25`) manda `validate_user_id = current_user.id` y un select de `state` [Desbloqueado=1, Bloqueado=0]. `validate_user_id` es **solo un sello de quién tocó el candado por última vez** — se sobrescribe cada vez, no hay historial ni timestamp propio (la trazabilidad real viene del gem `audited`, `lab.rb:18-19`).
- El texto oficial (`_form_validate.html.erb:6-9`): "Cuando se usa el Estado Bloqueado no se puede editar o eliminar los registros de la Fecha Principal, sólo puede ser cambiado por el Supervisor."

**Bug en el filtro de búsqueda**: `labs/partials/_search_filters.html.erb:11` ofrece `[["Desbloqueado", 0], ["Bloqueado", 1]]` — **invertido** respecto al modelo. Filtrar por "Bloqueado" devuelve los desbloqueados.

## 1.7 Borrado

Todo es soft-delete (`deleted = 0/1`). En cascada manual: `lab.rb:49-58` (después de update, si `deleted=1`, marca `lab_details` y `lab_sub_details`) y `lab_detail.rb:77-82`. **Es `after_update`, no `after_save`** — funciona porque `destroy` hace `update_attribute(:deleted, 1)` (`labs_controller.rb:154`).

## 1.8 Índice, filtros y export

`labs_controller.rb:22-73`, permiso 33:
- Ransack sobre `Lab`. Filtros: `num_test` (select con distinct de la prueba, "Todos" = sin filtro), estado, rango `date_rehearsal`, y "Creado Por" (`lab_details.user_id`).
- **Default oculto**: si no mandás `search_date_ini`, se fuerza a `3.months.ago.beginning_of_month` (línea 29). Los datos más viejos son invisibles salvo que se cambie la fecha.
- Paginación **de 5 en 5** (`lab.rb:40`).
- **"Pruebas con Valores Pendientes"** (línea 55): `lab_details` que tienen alguna celda con `TRIM(name) = ''` **o `name = 'NaN'`**. El `'NaN'` es la huella de las fórmulas JS fallando (`parseFloat` de un campo vacío) y quedando persistido en la BD. Se muestra en un panel amarillo (`index.html.erb:19-35`).
- Export `.xls`: es un **HTML con `<table>`** servido con extensión `.xls` (`partials/_xls_records.erb`), no un XLSX real.
- Interpolación de `params[:lab_category_detail_id]` directo en SQL en las líneas 35 y 55 → **inyección SQL**.

---

# 2. Carga de archivos de instrumento

## 2.1 Flujo

1. En el form de nueva muestra, si el módulo tiene ≥1 columna con `is_imported = 1`, aparece el botón "Lectura de Archivo TXT" (`lab_details/partials/_form_new.html.erb:41-49`) → `/pr_management/templates/imports/new?lab_category_detail_id=X&lab_id=Y`.
2. `ImportsController#create` (`templates/imports_controller.rb:38-49`) crea un `LabFile` con el archivo. `accept=".txt"` solo en el HTML (`imports/partials/_form_new.html.erb:7`).
3. `LabFile` after_create `save_strings` (`app/models/lab_file.rb:19-25`): **lee el archivo entero con `File.read` y lo vuelca en la columna `description`**. Todo el parseo posterior trabaja sobre ese string, no sobre el archivo.
4. Redirige a `imports#show` → pantalla "Parámetros Encontrados", donde se hace el parseo y se muestran los valores extraídos.
5. Al confirmar ("Usar Datos"), `#update` guarda los `lab_file_details` (nested) y redirige a `.../lab_details/new?lab_file_id=<id>` (`imports_controller.rb:56`).
6. El form de nueva muestra, si viene `lab_file_id`, precarga cada input buscando un `LabFileDetail` con ese `lab_file_id` y ese `lab_category_sub_detail_id` (`_form_new_nested.erb:13-24` y 33-44). Si no hay match, cae al `reuse_value`.

**El usuario siempre puede editar los valores importados antes de guardar** — la importación solo precarga el formulario.

## 2.2 El parser (`imports/partials/_form_show.html.erb:6-32`)

Todo el parser son **7 líneas de ERB**:

```erb
@word_searched = category_sub_detail.imported_value.to_s
@word_removed  = category_sub_detail.imported_remove_value.to_s
@data = @main_model.description.match(/#{@word_searched}*(.*)$/)
...
@value_saved = @data[1].strip                       # si no hay word_removed
@value_saved = @data[1].strip.delete! @word_removed # si lo hay
```

Es decir: por cada columna con `is_imported=1`, se busca la **primera ocurrencia** del literal `imported_value` en el texto del archivo y se captura **el resto de esa línea** (`$` en Ruby = fin de línea). Luego se hace `strip` y opcionalmente se borran todas las apariciones de los caracteres de `imported_remove_value` (unidades como `kV`, `%`, `ppm`).

Problemas concretos de esta implementación:

- **`/#{word}*(.*)$/`** — el `*` cuantifica **el último carácter** de la palabra buscada. Buscar `"Valor medio"` genera `/Valor medi o*(.*)$/`, que también matchea `"Valor medi"`. Casi siempre funciona por casualidad. No es una búsqueda literal.
- **`imported_value` se interpola crudo en un Regexp**: cualquier `(`, `.`, `[`, `+` en la config actúa como metacarácter o revienta.
- **`delete!` devuelve `nil` si no borró nada** → `@value_saved.strip` explota con `NoMethodError` (línea 23-24). Ocurre cuando la unidad configurada no está en ese archivo en particular.
- **`delete` borra caracteres, no substrings**: `delete!("kV")` borra *todas* las `k` y `V` del valor, no la cadena "kV".
- **Solo la primera ocurrencia**. Los TXT de DTL C tienen "Llenado 1 / Llenado 2 / Llenado 3" con las mismas etiquetas repetidas; solo se captura el primero.
- **`@data` es una variable de instancia reutilizada en el loop**, y el footer decide entre "Usar Otro Archivo" y "Usar Datos" mirando `@data` **de la última columna** (`_form_show.html.erb:39`). Si la última matchea y las otras no, ofrece guardar igual (guardando vacíos); si la última falla y las demás matchean, no deja guardar.
- **`TxtDataUploader` no valida nada**: `extension_white_list` (`app/uploaders/txt_data_uploader.rb:50-52`) es la API de CarrierWave 0.x y devuelve `jpg jpeg gif png`; el proyecto usa CarrierWave **2.2.2** (`Gemfile.lock:103`), donde el método se llama `extension_allowlist`. **El método es código muerto: se acepta cualquier extensión.** Además incluye `CarrierWave::MiniMagick` (línea 7) sin sentido para texto.
- `LabFile#save_default_values` pone **`deleted = 1`** al crear (línea 43) — el archivo nace "borrado"; el hidden `lab_file[deleted]=0` del paso de confirmación (`_form_show.html.erb:3`) lo activa. Es un flag de "confirmado", no de borrado.
- `LabFile#remove_rows_unsaved` (línea 47) hace `DELETE FROM lab_file_details where deleted=1` **global, sin scope**, en cada create/update. Como `LabFileDetail` siempre nace con `deleted=0` (`lab_file_detail.rb:20`), en la práctica no borra nada — es código muerto peligroso.
- `LabFileDetail#update_values_for_furanos` (`lab_file_detail.rb:23-27`): after_create ejecuta un **UPDATE global sin WHERE por id** sobre `lab_category_sub_detail_id IN (80,81,82,83,84)` haciendo `substring_index(name,' ',1)`. Es un hack para quedarse con el primer token de los valores de Furanos ("0.000 BDL" → "0.000"). El comentario dice literal: `#DONT MOVE FURANOS COLUMN ORDER`. **IDs hardcodeados.**

## 2.3 Formatos soportados (`/home/user/labo_old/1.txt_examples/HITACHI/`)

No hay "formatos" declarados: **hay un solo parser genérico línea-por-línea**, y cada formato se soporta configurando `imported_value` por columna en la BD. Los 4 formatos reales presentes:

| Equipo / carpeta | Encoding | Estructura | Ejemplo |
|---|---|---|---|
| **DPA 75C** (Rigidez dieléctrica) | UTF-8 | `Etiqueta:<TAB><TAB>valor unidad` | `Medición 1:\t\t\t67.0  kV`, `Valor medio:\t\t\t\t62.6  kV`, `Desviación estándar: \t\t\t3.8  kV` |
| **DTL C** (Factor de disipación) | UTF-8 | igual, pero con **secciones repetidas** `Llenado 1/2/3` | `Ípsilon:\t\t2.28`, `en 50 Hz:\t\t1.0040 %`, `Temperatura del ensayo:\t100 °C` |
| **Furanos** (HPLC LaChrom) | ASCII | **tabla de ancho fijo**, no clave:valor | `2-furaldehyde   11.658   26946   4.427`; hay `0.000 BDL` y nombres que se parten en 2 líneas (`5-hidroxymethyl-2-furalde` / `hyde`) |
| **Cromas** (DGA/ASTM 3612) | UTF-8 **con BOM** | tabla `Nombre  Cantidad` | `CO2         140`, `H2          2` |

Detalles a tener en cuenta al reescribir:
- Separadores son **TABs**, y en cantidad variable (2, 3 o 4 tabs).
- Hay acentos y `°C`; un archivo tiene **BOM UTF-8** (`Cromas/23-0178.TXT`) — el BOM entra a `description` y afecta el match de la primera línea.
- Extensión mayúscula `.TXT` existe.
- El caso Furanos es donde el parser genérico se queda corto y por eso está el parche SQL de `substring_index`.
- Los ejemplos también incluyen `Plantilla Analista 1.xlsm`, `Plantilla Supervisor.xlsm`, `VALORES DE ORIENTACION.xlsx` y `MODELO DE INFORME.pdf` — el sistema Excel que este módulo reemplazó. Vale la pena mirarlos para entender las reglas de negocio originales.

---

# 3. Tendencias

## 3.1 Qué se grafica

`app/controllers/pr_management/templates/tendences_controller.rb`, permiso 33.

Se grafica **solo el Patrón Control** (`lab_detail_type_id = 1`), nunca Muestras ni Duplicados — las líneas para tipos 2 y 3 están escritas y **comentadas** (líneas 22-24). Es una carta de control de calidad del laboratorio, no una tendencia del aceite del cliente.

**Dos modos**, decididos por el ID del módulo (`tendences/index.html.erb:6,12`):

**a) Modo simple (`lab_category_detail_id < 10`)** → `_single_content` + `_amcharts`.
La serie de datos es `@lab_sub_details1` (`tendences_controller.rb:20`): los `lab_sub_details` de filas Patrón Control **de la ÚLTIMA columna del módulo** — literalmente `.order("num_pos ASC").last` (línea 17). Es decir, la columna "Resultado". Eje X = `labs.date_rehearsal`, eje Y = `lab_sub_details.name` volcado crudo en el JS (`_amcharts.html.erb:86-87`).

**b) Modo cromatografía (`lab_category_detail_id == 10`, Análisis Cromatográfico)** → `_multi_content` + 9 partials `_amcharts_<gas>`. Nueve gráficos, uno por gas, con **`lab_category_sub_detail_id` hardcodeados** (`tendences_controller.rb:26-34`):

| id | var | gas |
|---|---|---|
| 61 | `@lab_sub_detail_hid` | Hidrógeno (H2) |
| 62 | `_oxi` | Oxígeno (O2) |
| 63 | `_nit` | Nitrógeno (N2) |
| 64 | `_met` | Metano (CH4) |
| 65 | `_mon` | Monóxido de Carbono (CO) |
| 66 | `_dio` | Dióxido de Carbono (CO2) |
| 67 | `_eti` | Etileno (C2H4) |
| 68 | `_eta` | Etano (C2H6) |
| 69 | `_ace` | Acetileno (C2H2) |

Los módulos 7, 8 y >10 no tienen tendencia (el menú los excluye: `layouts/_app_sidebar_left_menus.html.erb:257`, condición `@lcd < 7 or (@lcd > 8 && @lcd < 11)`).

## 3.2 Los límites LCI/LAI/LC/LAS/LCS

Vienen de la tabla **`patron_tendences`**, y hay una relación **implícita 1:1 por ID**: `PatronTendence.find(params[:lab_category_detail_id])` (`_single_content.html.erb:4`, `_amcharts.html.erb:106`). **No hay foreign key ni `belongs_to`** — se asume que `patron_tendences.id == lab_category_details.id`. Cualquier desalineación de IDs cruza los límites entre pruebas silenciosamente. Es lo primero que hay que arreglar en la migración.

Columnas: base `las, lai, lcs, lci, lc` + prefijadas por gas `oxi_*, nit_*, met_*, mon_*, dio_*, eti_*, eta_*, ace_*`. El gráfico de H2 **reusa las columnas base sin prefijo** (`_amcharts_hid.html.erb:128,168,207,246,286`), no hay `hid_*`.

Significado (textos literales de `_single_content.html.erb:17-56`) — es una **carta de control estadística tipo Shewhart**:

| Sigla | Significado | Color en las tarjetas |
|---|---|---|
| **LCI** | Límite de Control Inferior | `bg-info` (azul) |
| **LAI** | Límite de Advertencia Inferior | `bg-success` (verde) |
| **LC** | Límite Central (valor esperado del patrón) | `bg-danger` (rojo) |
| **LAS** | Límite de Advertencia Superior | `bg-success` (verde) |
| **LCS** | Límite de Control Superior | `bg-info` (azul) |

Semántica de laboratorio (típicamente LC = media histórica, LA = ±2σ, LC límites = ±3σ): dentro de LAI–LAS todo bien; entre LAI–LCI o LAS–LCS, advertencia; fuera de LCI/LCS, fuera de control → el lote de ensayos del día es sospechoso.

Cómo se dibujan (`_amcharts.html.erb`): cada límite es una **serie de línea propia** (`series2`..`series6`), construida repitiendo la constante en **cada fecha en la que hay un punto de patrón** (ej. líneas 124-131). No hay bandas ni áreas ni alertas automáticas — **el sistema no marca ni notifica cuando un patrón se sale de control; es puramente visual**. Estilos: patrón = línea punteada `[10,2]` violeta con bullets; LAS/LAI = sólidas verde-oscuro grosor 3; LC = sólida roja grosor 4; LCS/LCI = punteadas `[10,5]` azules. Unidad de eje/tooltip = `lab_category_details.unit_name_amchart`.

Edición de límites: `PatronTendencesController` (permiso 31 = "SP - Patron"), `patron_tendences/edit.html.erb` — usa `_form_edit_cromas` (tabla de 9 gases × 5 límites) si `params[:id] == 10`, si no `_form_edit` (5 campos).

**Riesgo de XSS/JS roto**: los valores se interpolan sin escapar dentro del `<script>` (`value: <%= a.name %>`). Un `lab_sub_details.name` vacío, `"NaN"` o `"<0.5"` genera JavaScript inválido y **rompe el gráfico entero**, no solo un punto.

---

# 4. Admin de plantillas (`pr_management/configurations/`)

Cuatro CRUDs, todos protegidos por `access_id = 14` (`category_details_controller.rb:19` etc.) — el mismo permiso para ver, crear, editar y borrar. El menú lo esconde bajo permiso 38 (`_app_sidebar_left_menus.html.erb:285`), "Ajustes Adicionales".

| Controlador | Modelo | Qué configura |
|---|---|---|
| `category_detail_types` | `LabCategoryDetailType` | Categorías. Campos: `name`, `icon_label` (clase FontAwesome del menú, `category_detail_types/partials/_form_new.html.erb:10-11`) |
| `category_details` | `LabCategoryDetail` | **La prueba/módulo + todas sus columnas anidadas** |
| `category_subdetails` | `LabCategorySubDetail` | Editar **una** columna suelta (ruta alternativa) |
| `category_subdetail_types` | `LabCategorySubDetailType` | Los 4 tipos de columna. Solo campo `name` |

## 4.1 Configuración de la prueba (`category_details/partials/_form_new.html.erb`)

- `lab_category_detail_type_id` — categoría
- `name`
- `num_pos` — orden en el menú; se auto-sugiere `MAX(num_pos)+1` (línea 1)
- `is_grouped` (checkbox rotulado "No usa Duplicados / No usa Patrón Control")
- `blur_calculation` — **textarea con JavaScript crudo** ("Fórmula de los Campos")
- `description` — "Detalles de Uso"

En BD existen además `is_blur`, `has_patron`, `has_reuse`, `unit_name_amchart` que **no aparecen en estos formularios** — se editan a mano en la BD. `has_reuse` controla si aparece el link "Valores Constantes" en el menú (`_app_sidebar_left_menus.html.erb:248`) y `unit_name_amchart` es la unidad del gráfico.

## 4.2 Configuración de cada columna (nested, `_form_new_nested.html.erb` / `_form_edit_nested.html.erb`)

Columnas del editor: Posición | Nombre | Tipo | Opciones | Obligatorio | Cálculos | Bloquear | Constante | Valor Constante | Importar TXT | Buscar Parámetro | Quitar Parámetro | Mostrar en Reporte.

Las **opciones del select** (`lab_category_sub_detail_options`) se editan anidadas dos niveles (`_form_edit_nested.html.erb:33-39`) con campos `num_pos`, `name`, `applicability_flag` ("Acreditación") y checkbox `deleted`. Nótese que la vista `new` (`_form_new_nested.html.erb:32-35`) **solo muestra `name`** — falta `num_pos` y `applicability_flag`, que sí están en `edit`. Inconsistencia real.

`applicability_flag` se usa en `LabDetail#norma_y_flag` (`app/models/lab_detail.rb:52-69`): asume que **la columna con `num_pos == 2` es siempre "Norma"** y devuelve `[nombre_de_la_norma, applicability_flag]` para el módulo de reportes. Otro supuesto posicional frágil.

## 4.3 Los "tipos de columna" (`lab_category_sub_detail_types`)

Tabla de 4 filas (`db/seeds.rb:179-184`): **Texto (1), Número (2), Selección (3), Fecha (4)**. Es configurable por CRUD, **pero los IDs 1/2/3/4 están hardcodeados en ~10 vistas** (`_form_new_nested.erb:10,30,51,58`; `_form_edit_nested.html.erb:12,19,27,43`; `lab_details/_table.html.erb:37-46`; `labs/_table.html.erb:61-74`; `_display_missing_values.html.erb:21-34`; `_xls_records.erb:34-47`; `_table_poli.html.erb:29-38`). El comentario `<!-- HTML TAGS: INPUT =1,2 SELECT: 3 DATE: 4 -->` está repetido en todas. **Agregar un 5º tipo desde la UI no hace nada**: se guarda pero ninguna vista lo renderiza (la celda queda en blanco). En Laravel esto debería ser un enum, no una tabla.

## 4.4 Las banderas, una por una

| Bandera | Dónde se lee | Efecto real |
|---|---|---|
| **`is_blur`** | `_form_new_nested.erb:26,46`; `_form_has_calculation.html.erb:3` | Mal nombrada: **no difumina nada**. Pone `onblur="calculate()"` en el input. Marca "esta columna participa en la fórmula". Si ninguna columna la tiene, el script de cálculo ni se carga. Getter engañoso: `str_has_calculation` (`lab_category_sub_detail.rb:22-25`) devuelve "Si/No" leyendo `is_blur` |
| **`is_reuse`** | `patrons_controller.rb:39`; `patrons/_form_edit.html.erb:11` | "Es Constante": el valor persiste entre muestras. Habilita la columna en la pantalla **"Valores Constantes"** (`/pr_management/templates/patrons/:id/edit`, permiso 31) donde el supervisor edita `reuse_value` de todas las columnas constantes del módulo en una tabla |
| **`reuse_value`** | `_form_new_nested.erb:20,23,40,43` | El valor por defecto que se precarga en cada nueva fila. Ej: `Factor KOH = "0.5531"`, `Temperatura Ambiente = "21.5"` (`db/seeds.rb:191,193`). **Se aplica solo en `new`, nunca en `edit`** |
| **`is_blocked`** | `_form_new_nested.erb:27,47`; `_form_edit_nested.html.erb:16,23` | Agrega `readonly="true"` al input. Se usa en la columna Resultado (calculada). **Solo HTML — no hay protección en servidor** |
| **`is_required`** | mismos archivos, líneas 28,48 / 17,24,46 | Agrega `data-parsley-required` + `required` + placeholder "Requerido.". **Validación 100% cliente (Parsley); no hay `validates` en `LabSubDetail`** (`app/models/lab_sub_detail.rb:8` la tiene comentada). Un POST directo guarda vacíos |
| **`is_imported`** | `lab_details/_form_new.html.erb:41`; `imports/_form_show.html.erb:6` | Doble función: (a) si el módulo tiene ≥1, aparece el botón "Lectura de Archivo TXT"; (b) marca qué columnas intenta extraer el parser |
| **`imported_value`** | `imports/_form_show.html.erb:11,14` | La cadena a buscar en el TXT. Se interpola cruda en un `Regexp`. En `edit` es un `text_area` y en `new` un `text_field` (`_form_edit_nested.html.erb:76` vs `_form_new_nested.html.erb:70`) — un salto de línea pegado en el textarea rompe el regex |
| **`imported_remove_value`** | `imports/_form_show.html.erb:12,23` | Caracteres a quitar del valor extraído (unidades). Usa `String#delete!`, que borra **caracteres individuales**, no la subcadena, y devuelve `nil` si no borra nada → excepción |
| **`report_use`** | **en ningún lado funcional** | "Mostrar en Reporte". Solo existe el getter `str_report` (`lab_category_sub_detail.rb:46-49`) y los checkboxes. Grepeando todo el repo, **ninguna vista de reporte lo consulta**: `im_management/rem_reports/*` levanta valores por `lab_category_sub_detail_id` hardcodeado (121-128, 152, ...). **Es una feature muerta o a medio hacer** — hay que preguntarle al cliente si debía funcionar |

**Bug de layout** en `_form_new_nested.html.erb`: el `<thead>` lista "Mostrar en Reporte" en la posición 13 y un `<th>` vacío en la 14 (líneas 16-17), pero el `<tbody>` pone el botón de eliminar en la 13 y `report_use` en la 14 (líneas 74-80). Las columnas están cruzadas respecto a los encabezados. En `_form_edit_nested.html.erb` está bien.

**Riesgo de datos**: reordenar `num_pos` o insertar una columna en medio **rompe todas las fórmulas** (que referencian `colN` por posición) sin ningún aviso. `README_ADD_COLUMNS.md` describe el procedimiento manual: crear la columna, luego **pegar código ERB temporal en `_table.html.erb`** que hace `LabSubDetail.create(...)` con IDs literales para backfillear los registros existentes, desplegar, y después comentar esas líneas y volver a desplegar. Ese hack quedó fosilizado en el código (`labs/partials/_table.html.erb:44-51`, comentado). **En Laravel esto debe ser una migración/comando, no ERB en producción.**

---

# 5. `admin_templates/` vs `templates/`

## 5.1 Qué es

`admin_templates` es una **"vista supervisor"** de las mismas hojas: en vez de modales sobre el índice de fechas, presenta pantallas full-page tipo dashboard con contadores, DataTables con filtros por columna y botones "Registros Con Validación / Sin Validación" (`admin_templates/labs/index.html.erb:17-21`) y "Validar" (`admin_templates/labs/show.html.erb:22`). El `templates/labs_controller.rb:202` guarda `@admin_url` apuntando ahí.

## 5.2 Por qué hay dos: es una copia fosilizada, no una capa

**No hay separación de responsabilidades** — es un fork por copy-paste que se quedó atrás. Evidencia:

1. **Tres de los cuatro controladores de `admin_templates` declaran la clase con el namespace equivocado**:
   - `admin_templates/lab_details_controller.rb:1` → `class PrManagement::Templates::LabDetailsController`
   - `admin_templates/imports_controller.rb:1` → `class PrManagement::Templates::ImportsController`
   - `admin_templates/patrons_controller.rb:1` → `class PrManagement::Templates::PatronsController`
   
   Solo `admin_templates/labs_controller.rb:1` está bien (`PrManagement::AdminTemplates::LabsController`).
   
   Con Zeitwerk esto es un error de carga: el archivo debe definir `PrManagement::AdminTemplates::LabDetailsController` y define otra cosa → `Zeitwerk::NameError`. Y `config.eager_load = true` en producción (`config/environments/production.rb:13`) significa que **esto reventaría al bootear**. Que la app aparentemente ande sugiere que estos archivos son código muerto que ya no se carga, o que la app no corre en modo producción. **Vale la pena verificar en el servidor real antes de asumir que `admin_templates` funciona.** En cualquier caso, las rutas `namespace :admin_templates` para `lab_details`, `imports` y `patrons` (`config/routes.rb:155-165`) apuntan a controladores que no existen.

2. **`admin_templates/labs_controller.rb` apunta sus URLs a `templates/`**: `set_url` (línea 173) arma `@main_url = "/pr_management/templates/..."`, y `set_nested_url` (línea 184) idem. Los botones de la vista admin te devuelven al flujo normal.

3. **Nada enlaza a `admin_templates`**: el único grep fuera de su propia carpeta es la asignación de `@admin_url`, que ninguna vista usa. **No hay entrada en el menú.**

4. **Las vistas de `admin_templates` son la versión vieja de las de `templates`** (diff confirmado):
   - No tienen el campo/JS de `num_test` ni la validación de muestra (`admin/_form_new.html.erb` vs `templates/_form_new.html.erb:5-18`)
   - Permiten elegir cualquier `lab_detail_type_id` sin la regla Patrón+Duplicado
   - Los selects **no filtran `is_hidden`** en las opciones (`admin/_form_new_nested.erb:54` usa `where("deleted=0")` vs `where("deleted=0 AND is_hidden=0")` en templates)
   - `admin/_form_edit_nested.html.erb:27-33` **no preselecciona la opción actual** del select — al editar cualquier fila, todos los selects se resetean a la primera opción y se guarda mal
   - No incluyen `layouts/remove_spaces`

**Recomendación para la migración**: no portar `admin_templates`. Portar `templates/` (que es lo vivo) y, si el supervisor necesita la vista de tablero, reconstruirla como una vista/rol sobre el mismo controlador.

---

# 6. Comportamientos sutiles, trucos y bugs — lista para NO reproducir

### Seguridad
1. **`params.require(...).permit!`** en todos los controladores del módulo (`labs_controller.rb:222`, `lab_details_controller.rb:165`, `imports_controller.rb:83`, etc.) — mass assignment total. Se puede setear `state`, `deleted`, `user_id`, `validate_user_id` desde el navegador.
2. **Inyección SQL** por interpolación de params: `labs_controller.rb:35,55`, `tendences_controller.rb` (varias), `lab_detail.rb:87,95,103,109`.
3. **`blur_calculation` es JS de la BD inyectado con `html_safe`** (`_calculation_script.html.erb:5`) — quien edite la config ejecuta código arbitrario en el navegador de todos los analistas.
4. **`validate` chequea el permiso 36 en vez del 30** (`labs_controller.rb:117`): el botón está oculto pero la URL no está protegida.
5. **Ninguna comprobación de `state` en servidor**: bloquear una fecha no impide modificarla vía POST.
6. **Ninguna validación en servidor de `is_required`, `is_blocked` ni de tipos**: todo es Parsley/HTML.
7. Datos en `<script>` sin escapar (`_amcharts.html.erb:87`).

### Corrección de datos
8. **`'NaN'` se persiste en la BD** cuando la fórmula JS opera sobre campos vacíos. El panel "Pruebas con Valores Pendientes" (`labs_controller.rb:55`) existe precisamente para cazarlos. En Laravel: calcular en servidor y rechazar no-números.
9. **`lab_sub_details.name` es string para todo**, incluidos números y IDs de opciones. Migrar con cuidado: hay notación científica, `<0.5`, vacíos y `NaN` mezclados.
10. **Las opciones de select se guardan por ID** — si alguien borra una opción, el histórico queda huérfano. `templates/_form_edit_nested.html.erb:31-36` tiene un parche que reinyecta la opción seleccionada aunque esté oculta/borrada, para no corromper el registro al editar. **Ese parche es funcionalidad requerida, no un adorno.**
11. **`_form_edit_nested.html.erb:43-47` (tipo Fecha): el `value` solo se emite si `is_required == 1`.** Editar una fila con una columna Fecha no obligatoria **borra la fecha guardada** silenciosamente. Bug real.
12. `_form_edit_nested.html.erb` **no envuelve las celdas en `<tr>`** (emite `<td>` sueltos dentro del `<tbody>`); funciona por tolerancia del navegador.
13. **`reuse_value` no se aplica en `edit`**, solo en `new`.
14. `LabSubDetail#str_two_values` (`lab_sub_detail.rb:16-18`) hace `name.gsub('/', '<br><br>')` — el módulo 16 guarda **múltiples mediciones concatenadas con `/` en un solo campo string**. Ver §"módulo 16" abajo.

### Acoplamientos e IDs mágicos
15. **`lab_category_detail_id == 16`** dispara las vistas `_poli` (`labs/_form_show.html.erb:31`, `lab_details/_form_new.html.erb:62`, `_form_edit.html.erb:40`, `_table.html.erb:64`, `_xls_records.erb:37`, `_display_missing_values.html.erb:24`). Según el orden de `db/seeds.rb:166`, id 16 = **Grado de Polimerización**. Las vistas `_form_new_nested_poli.html.erb` reparten los índices de columna en rangos duros (`index > 1 && index < 4` → 2 inputs; `> 3 && < 8` → 4 inputs; `> 7 && < 15` → 2 inputs) y los combinan en un único campo oculto `colN`. **La lógica de concatenación con `/` no está en el repo** — debe estar en el `blur_calculation` de ese módulo en la BD. Hay que dumpearlo.
16. **`lab_category_detail_id == 10`** (Análisis Cromatográfico) dispara `_multi_content` en Tendencias.
17. **`lab_category_sub_detail_id` 61-69** hardcodeados para los 9 gases (`tendences_controller.rb:26-34`); **80-84** para Furanos (`lab_file_detail.rb:26`); **121-128, 152** en los reportes (`im_management/rem_reports/partials/*`); **207, 208** en código comentado (`labs/_table.html.erb:48-49`).
18. **`patron_tendences.id == lab_category_details.id`** asumido sin FK.
19. **Se asume que `num_pos == 1` es "Nº de Muestra"** (`#col1` → `num_test`), **`num_pos == 2` es "Norma"** (`lab_detail.rb:55`) y **la última columna es "Resultado"** (`tendences_controller.rb:17`).
20. El menú decide qué módulos tienen Tendencias con `@lcd < 7 or (@lcd > 8 && @lcd < 11)` (`_app_sidebar_left_menus.html.erb:257`).
21. **Efecto lateral cruzado con `rem_jobs`**: crear/borrar una fila tipo Muestra marca tareas como hechas en otro módulo (`lab_detail.rb:84-117`), parseando `num_test` como `"AÑO-NUM"`.

### Rendimiento
22. **Queries N+1 masivas dentro de las vistas**: `labs/_table.html.erb:18-20` (una query por fila de la tabla) y `_form_edit_nested.html.erb:4` (`LabSubDetail.find_by` **por cada columna**). `labs/_form_show.html.erb:5-6` cuenta patrones y duplicados con dos queries más.
23. Todo el caching de fragmentos está **comentado** (`labs/_table.html.erb:12,39,57`), presumiblemente porque daba datos rancios.
24. `Lab.per_page = 5` (`lab.rb:40`) — paginación absurdamente chica, probablemente para tapar el N+1.
25. El export "xls" renderiza **todos** los resultados sin paginar (`labs_controller.rb:52,60`).

### Restos y código muerto
26. `templates/labs_controller_old.rb` — 204 líneas de controlador viejo que **sigue en `app/controllers`** y por tanto se carga.
27. `labs/partials/_form_show.html.erb:45-54` — un bloque `<div style="display: none">` con el botón bueno duplicado ("SE HA COMENTADO PARA VALIDAR MUESTRAS"), y luego el botón real en la línea 56 **sin la condición de `state`**: desde el `show`, "Agregar Muestra" aparece incluso si la fecha está bloqueada.
28. `labs/_table.html.erb:136` — auto-bloqueo comentado: `Lab.where("date_rehearsal < NOW() - INTERVAL 3 DAY").update_all(state: 0)`. **Era un requisito que se intentó y se abandonó** — vale preguntar si lo quieren de vuelta (como job, no como ERB).
29. `LabFile#remove_rows_unsaved` y `extension_white_list` — código muerto.
30. `labs/partials/_nested_data.html.erb` (4 líneas que imprimen IDs) y `add_details.html.erb` (renderiza un partial de `im_management`) — archivos huérfanos en la carpeta.
31. `lab_details/partials/__form_new_nested_old.erb`, `_form_new_issue.html.erb` — más restos.
32. Filtro de estado invertido (`_search_filters.html.erb:11`).
33. Encabezados cruzados en `configurations/category_details/partials/_form_new_nested.html.erb:16-17` vs `74-80`.

---

## Lo que no se entiende del código (a preguntar antes de reescribir)

- **`report_use`**: se configura en la UI pero ningún código lo lee. ¿Debía filtrar las columnas del informe PDF? Hoy los reportes usan IDs fijos.
- **La lógica de concatenación con `/` del módulo 16** (Grado de Polimerización): las vistas `_poli` generan N inputs por columna y `str_two_values` los separa al mostrar, pero **el JS que los une está en `blur_calculation` en la BD**, no en el repo.
- **`is_grouped` vs `has_patron`**: ambos parecen decir "esta prueba no usa patrón/duplicado", pero `has_patron` no se lee en ninguna parte del código y `is_grouped` sí. Probablemente `has_patron` quedó obsoleto.
- **`labs.validate_user_id`**: se sobrescribe en cada cambio de candado. ¿Se espera un historial de quién validó qué? Hoy eso solo está en las tablas de `audited`.
- El significado exacto de `state` (¿"validado" o "bloqueado"?) cambió a mitad de la vida del sistema (líneas comentadas en `lab.rb:33-34`) y el filtro de búsqueda quedó con la semántica vieja. Conviene definir con el cliente si se quieren **dos conceptos separados** (validado ≠ bloqueado) en Laravel.

---

## Archivos clave para quien reescriba

**Modelos**: `/home/user/labo_old/app/models/lab.rb`, `lab_detail.rb`, `lab_sub_detail.rb`, `lab_category_detail.rb`, `lab_category_sub_detail.rb`, `lab_file.rb`, `lab_file_detail.rb`, `patron_tendence.rb`

**Núcleo del flujo**: `/home/user/labo_old/app/controllers/pr_management/templates/labs_controller.rb`, `lab_details_controller.rb`, `imports_controller.rb`, `tendences_controller.rb`

**Vistas que contienen la lógica real** (más que los controladores): `/home/user/labo_old/app/views/pr_management/templates/lab_details/partials/_form_new_nested.erb`, `_form_edit_nested.html.erb`, `_calculation_script.html.erb`, y `/home/user/labo_old/app/views/pr_management/templates/imports/partials/_form_show.html.erb` (el parser TXT completo)

**Config**: `/home/user/labo_old/app/views/pr_management/configurations/category_details/partials/_form_edit_nested.html.erb` (la versión correcta y completa del editor de columnas)

**Datos de referencia**: `/home/user/labo_old/db/seeds.rb` (fórmulas y columnas reales de los primeros módulos), `/home/user/labo_old/README_ADD_COLUMNS.md`, `/home/user/labo_old/1.txt_examples/` (formatos de instrumento + las planillas Excel originales que el sistema reemplazó)
