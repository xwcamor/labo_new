# Auditoría A — Columnas de las pruebas y valores constantes

> **Alcance.** Cómo el sistema Rails de 2019 (`/home/user/labo_old`) definía las
> columnas de cada prueba y sus valores constantes, y qué de todo eso NO está en
> el sistema nuevo (`/workspace/labo_new`).
>
> **Método.** Se leyeron: el DDL real de producción, las migraciones, los cinco
> modelos, los cuatro controladores de configuración, los dos controladores de
> constantes, las quince vistas que administran o consumen esas tablas, los dos
> sembradores y los datos sembrados reales. Del lado nuevo: las migraciones de
> `test_definitions`, el modelo `TestField`, `TestFieldController`, las pantallas
> `Fields.vue` / `Constants.vue`, el importador legado y el motor de bancada.
>
> **El sistema viejo no se modificó.** Este documento es el único archivo escrito.

---

## 0. Resumen ejecutivo

El sistema viejo modelaba esto **bien**, y el nuevo lo modela **mejor**: declara
el rol de cada columna, evalúa las fórmulas en el servidor, separa el hecho de
la acreditación de su rótulo y hace que reordenar sea inofensivo. La estructura
está migrada casi entera.

Lo que falta no es estructura, es **dato migrado y dato aplicado**:

1. Cuatro columnas del viejo que SÍ tenían valor de negocio no las lee nadie en
   el nuevo (`report_use`, `is_imported` + sus dos parámetros).
2. Dos columnas del nuevo existen, se cargan con dato correcto y **nadie las
   consume** (`is_locked`, `min_exclusive` desde la interfaz).
3. Una columna del viejo está mapeada al concepto equivocado (`has_reuse` →
   `has_control`).
4. El cambio del valor constante **dejó de auditarse**. En el viejo se auditaba.

La pantalla de constantes del nuevo cubre el mecanismo del viejo **en lo
esencial y con una diferencia de comportamiento que no está documentada** (ver
§12).

---

## 1. Advertencia previa: `db/schema.rb` del viejo está desactualizado

`labo_old/db/schema.rb` (versión `2023_10_26_051386`) **no describe la base de
producción**. Se quedó atrás y contradice a las migraciones y al volcado real.

| Tabla | `schema.rb` dice | La base real tiene |
|---|---|---|
| `lab_category_details` | `is_blur`, `has_patron` (`schema.rb:74,76`) | `container`, `description`, `unit_name_amchart`, `has_reuse` — y **ni `is_blur` ni `has_patron`** |
| `lab_category_details` | sin `container` / `description` / `unit_name_amchart` | los tres existen |
| `lab_category_sub_details` | 12 columnas (`schema.rb:99-114`) | 17 columnas |
| `lab_category_sub_detail_options` | 5 columnas (`schema.rb:83-90`) | 9 columnas (`applicability_flag`, `num_pos`, `is_hidden`) |
| `lab_category_detail_types` | sin `icon_label` (`schema.rb:63-68`) | `icon_label` existe y se usa |

**La fuente de verdad usada en esta auditoría** es el volcado de estructura de
producción, ya presente en este repositorio:
`docs/migracion/esquema/lab_app_development-estructura.sql`. Se verificó que las
posiciones de sus columnas coinciden una a una con los índices que usa el
importador (`app/Console/Commands/ImportLegacyTestsCommand.php:113` y `:53`), o
sea que el importador se escribió contra esta misma estructura.

Consecuencia práctica: **cualquier auditoría que se apoye en `schema.rb` va a
concluir que faltan columnas que en realidad existen, y viceversa.**

---

## 2. Las tablas del viejo, columna por columna

### 2.1 `lab_category_detail_types` — el GRUPO de pruebas

`docs/migracion/esquema/lab_app_development-estructura.sql:200-206`

```sql
CREATE TABLE `lab_category_detail_types` (
  `id` bigint NOT NULL,
  `name` varchar(255) ... DEFAULT NULL,
  `icon_label` varchar(255) ... DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
)
```

| Columna | Qué hacía | Evidencia |
|---|---|---|
| `name` | Nombre del grupo. Único, sin distinguir mayúsculas. | `labo_old/app/models/lab_category_detail_type.rb:6` |
| `icon_label` | **El nombre del icono Font Awesome del menú.** Valores reales: `bong`, `syringe`, `flask-vial`. | `labo_old/app/views/layouts/_app_sidebar_left_menus.html.erb:211`: `<i class="fa-solid fa-<%= lab_category_detail_type.icon_label %> fs-5 me-2">` |
| `deleted` | Borrado lógico (0/1), forzado a 0 en el alta. | `lab_category_detail_type.rb:20-22` |

Datos reales: 3 filas — `(1,'Fisico Quimico','bong')`, `(2,'Cromatografias','syringe')`,
`(3,'Otros','flask-vial')`.

`icon_label` era **editable por pantalla y obligatorio**:
`labo_old/app/views/pr_management/configurations/category_detail_types/partials/_form_new.html.erb:11`.

### 2.2 `lab_category_details` — LA PRUEBA

`.../lab_app_development-estructura.sql:178-191`

| Columna | Qué hacía en la práctica | Evidencia |
|---|---|---|
| `lab_category_detail_type_id` | Grupo al que pertenece. | `labo_old/app/models/lab_category_detail.rb:3` |
| `name` | Nombre de la prueba. Único, sin distinguir mayúsculas. | `lab_category_detail.rb:12` |
| `container` | Envase requerido para la muestra. | `estructura.sql:182` |
| `num_pos` | Orden en el menú. El formulario de alta lo prellenaba con `max(num_pos)+1`. | `.../category_details/partials/_form_new.html.erb:1,19` |
| `is_grouped` | Rotulado en pantalla **"No usa Duplicados / No usa Patrón Control"**. Con 1, el selector de tipo de fila solo ofrecía "Muestra". | `.../lab_details/partials/_form_new.html.erb:23-26` |
| `blur_calculation` | **JavaScript crudo** con el cálculo de TODA la prueba, inyectado con `html_safe` y direccionando las celdas por posición del DOM (`col5`, `col9`). | `.../lab_details/partials/_calculation_script.html.erb:3-5`; texto real en `labo_old/db/seeds.rb:151` |
| `description` | "Detalles de Uso": texto libre de ayuda. | `.../category_details/partials/_form_new.html.erb:32` |
| `unit_name_amchart` | Rótulo del eje en los gráficos de tendencia (`mgKOH/g`, `ppm`, `kV/2.0mm`…). | `estructura.sql:187` + datos reales |
| `has_reuse` | **Gate del menú "Valores Constantes"**: solo aparecía en la barra lateral de la prueba si valía 1. | `labo_old/app/views/layouts/_app_sidebar_left_menus.html.erb:248-252` |
| `deleted` | Borrado lógico. | `lab_category_detail.rb:32-34` |

Nota: `has_reuse` es **redundante** con "tiene al menos una columna `is_reuse=1`".
El código lo dice: en el mismo archivo, líneas 246-247, está comentada la versión
que calculaba la condición y debajo la que lee la bandera. Se reemplazó un
`COUNT` por una bandera desnormalizada, y por eso puede quedar desincronizada.
En los datos reales **no lo está**: los 9 tests con `has_reuse=1` son exactamente
los 9 que tienen columnas constantes.

### 2.3 `lab_category_sub_detail_types` — EL TIPO DE COLUMNA

`.../estructura.sql:259-264`. Solo `name` + `deleted`.

Cuatro filas reales: `1=Texto`, `2=Número`, `3=Selección`, `4=Fecha`.

**Era un CRUD que mentía.** Los ids 1/2/3/4 están escritos a mano en las vistas
de bancada, con el `if/elsif` que decide qué control HTML se pinta:

```erb
<% if    array.lab_category_sub_detail_type_id.to_i == 1 %>    <!-- TEXO-->
<% elsif array.lab_category_sub_detail_type_id.to_i == 2 %> <!-- NUMERO-->
<% elsif array.lab_category_sub_detail_type_id.to_i == 3 %> <!-- SELECT-->
<% elsif array.lab_category_sub_detail_type_id.to_i == 4 %> <!-- DATETIME-->
```
— `labo_old/app/views/pr_management/templates/lab_details/partials/_form_new_nested.erb:10,30,51,58`
(y otra vez completo en `_form_edit_nested.html.erb:12,19,27,43`).

Agregar una quinta fila desde la pantalla "Tipos de Columnas" guardaba el
registro y no hacía nada: ninguna vista sabía dibujarlo y la celda salía vacía.

Diferencia práctica entre `1` y `2`: **los dos son `<input type="text">`**; el
`2` solo agrega `data-parsley-type="number"`, o sea validación de cliente. No
había tipado real ni en la base (`lab_sub_details.name` es `varchar`) ni en el
servidor.

### 2.4 `lab_category_sub_details` — LA COLUMNA

`.../estructura.sql:215-232`

```sql
CREATE TABLE `lab_category_sub_details` (
  `id` bigint NOT NULL,
  `lab_category_detail_id` bigint DEFAULT NULL,
  `lab_category_sub_detail_type_id` bigint DEFAULT NULL,
  `name` varchar(255) ... DEFAULT NULL,
  `num_pos` int DEFAULT NULL,
  `is_required` int DEFAULT NULL,
  `is_blocked` int DEFAULT NULL,
  `is_blur` int DEFAULT NULL,
  `is_reuse` int DEFAULT NULL,
  `reuse_value` varchar(255) ... DEFAULT NULL,
  `is_imported` int DEFAULT NULL,
  `imported_value` varchar(255) ... DEFAULT NULL,
  `imported_remove_value` varchar(255) ... DEFAULT NULL,
  `report_use` int DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
)
```

| Columna | Rótulo en pantalla | Qué hacía **de verdad** |
|---|---|---|
| `name` | "Nombre de la Columna" | Cabecera de la hoja. Era un `text_area` y **admitía saltos de línea a propósito**: `"Hidrógeno\r\nH2\r\nppm"` se renderizaba en tres líneas con `simple_format`. Ahí vivían el nombre, el símbolo y la unidad. |
| `num_pos` | "Posición" | Orden **y significado**: pos 1 = nº de muestra, pos 2 = norma, última = resultado (§4). |
| `is_required` | "¿Es Campo Obligatorio?" | Agregaba `required="required"` + `data-parsley-required` al input. **Solo cliente.** |
| `is_blocked` | "¿Bloquear Edición?" | Agregaba `readonly="true"` al input. **Solo cliente.** |
| `is_blur` | "¿Se usa en Cálculos?" | Agregaba `onblur="calculate()"`: al salir de la celda se corría el bloque JS de la prueba. |
| `is_reuse` | "¿Es Constante?" | Ver §5. |
| `reuse_value` | "Valor de la Constante" | Ver §5. |
| `is_imported` | "Importar TXT" | La columna se puede prellenar leyendo el archivo crudo del instrumento. |
| `imported_value` | "Buscar Parámetro" | La etiqueta a buscar en el TXT (`'Valor medio:'`, `'C2H2        '`). |
| `imported_remove_value` | "Quitar Parámetro" | Los caracteres a quitar del valor encontrado (`'kV'`, `'°C'`, `':Ωcm'`). |
| `report_use` | "Mostrar en Reporte" | **Declaraba que esta columna sale impresa en el informe del cliente.** |
| `deleted` | — | Borrado lógico. |

Rótulos: `.../category_details/partials/_form_new_nested.html.erb:5-17` y
`_form_edit_nested.html.erb:5-18`; render de la cabecera multilínea:
`.../lab_details/partials/_form_new.html.erb:56-58`.

### 2.5 `lab_category_sub_detail_options` — LAS OPCIONES DEL SELECT

`.../estructura.sql:241-250`

| Columna | Qué hacía |
|---|---|
| `name` | El texto de la opción: una norma (`ASTM D974`) o un código de equipo (`PP-LA-01C-065`). |
| `applicability_flag` | `varchar(5)` con el comentario del DDL **`'A, NA, etc.'`**. Es la marca de acreditación. Ver §6.4. |
| `num_pos` | Orden de la lista. |
| `is_hidden` | Opción retirada de la lista **sin borrar el histórico**. |
| `deleted` | Borrado lógico. |

Dato relevante de diseño: **la celda guardaba el `id` de la opción, no su
texto**, dentro de la misma columna `varchar` que todo lo demás
(`_form_new_nested.erb:55`: `<option value="<%= option_array.id %>">`). De ahí
el parche de la vista de edición, que reinyecta la opción seleccionada si quedó
oculta o borrada, para no corromper el registro al reeditarlo:

```erb
visibles = array.lab_category_sub_detail_options.where(deleted: 0, is_hidden: 0)
seleccionada = array.lab_category_sub_detail_options.find_by(id: @sub_detail.name)
opciones = visibles.to_a
opciones << seleccionada if seleccionada.present? && !visibles.exists?(id: seleccionada.id)
```
— `.../lab_details/partials/_form_edit_nested.html.erb:31-36`

### 2.6 `lab_categories`

**No existe.** No está en el DDL de producción, ni en las migraciones, ni hay
modelo. El nivel de "categoría" lo cumple `lab_category_detail_types`.

### 2.7 Dónde caían los datos

`labs` (la HOJA: prueba + fecha + estado) → `lab_details` (la FILA: patrón /
muestra / duplicado, con `num_test`) → `lab_sub_details` (la CELDA:
`lab_category_sub_detail_id` + `name` como texto).

`labs` tenía unicidad `(date_rehearsal, lab_category_detail_id)` entre las vivas
(`labo_old/app/models/lab.rb:12`): **una hoja por prueba por día**.

---

## 3. Los modelos: validaciones, callbacks, defaults, métodos

| Modelo | Validaciones | Callbacks | Métodos de presentación | Auditoría |
|---|---|---|---|---|
| `LabCategoryDetailType` | `name` único sin distinguir mayúsculas | `before_save :save_default_values` → `deleted = 0` | — | `audited` + `has_associated_audits` |
| `LabCategoryDetail` | `name` único | ídem | `str_grouped` (0→"No", 1→"Si") | `audited associated_with: :lab_category_detail_type` |
| `LabCategorySubDetailType` | `name` único | ídem | — | `audited` + `has_associated_audits` |
| `LabCategorySubDetail` | **ninguna — la de unicidad está comentada** (`lab_category_sub_detail.rb:12`) | ídem | `str_has_calculation`, `str_reuse`, `str_blocked`, `str_required`, `str_report` | **`audited`** (dos veces, `associated_with:` a la prueba y al tipo — `:18-19`) |
| `LabCategorySubDetailOption` | **ninguna — comentada** (`:8`) | ídem | — | **NO auditado** |

Anidamiento: `LabCategoryDetail accepts_nested_attributes_for
:lab_category_sub_details, allow_destroy: true` (`:9`) y `LabCategorySubDetail
accepts_nested_attributes_for :lab_category_sub_detail_options, allow_destroy:
true` (`:9`). Es lo que permitía editar prueba + columnas + opciones en un solo
formulario.

**No había ningún default de base ni de modelo** más allá de `deleted = 0`: una
columna nueva nacía con `is_required`, `is_blocked`, `is_blur`, `is_reuse`,
`is_imported`, `report_use` en `NULL` si el formulario no los mandaba, y todo el
código los compara con `.to_i == 1`, así que `NULL` se comportaba como 0.

Un método aparte, que es el que da sentido a `applicability_flag`:

```ruby
def norma_y_flag
  sub = lab_sub_details
          .joins(:lab_category_sub_detail)
          .find_by(lab_category_sub_details: { num_pos: 2 })
  return [nil, nil] unless sub
  csd = sub.lab_category_sub_detail
  if csd.lab_category_sub_detail_type_id == 3
    opt = LabCategorySubDetailOption.find_by(id: sub.name)
    return [nil, nil] unless opt
    [opt.name, opt.applicability_flag]
  else
    [sub.name, nil]
  end
end
```
— `labo_old/app/models/lab_detail.rb:52-69`

Tres supuestos clavados ahí: que la norma es **la columna `num_pos: 2`**, que si
es de tipo selección el valor guardado es el **id** de la opción, y que la marca
de acreditación viaja con la opción.

---

## 4. Los tres supuestos posicionales del viejo

| Supuesto | Dónde | Qué rompía al reordenar |
|---|---|---|
| `num_pos == 1` es el Nº de muestra | JS que copia `#col1` a `lab_detail[num_test]`: `.../lab_details/partials/_form_new.html.erb:73-83` | El enlace de la fila con la muestra del cliente |
| `num_pos == 2` es la Norma | `lab_detail.rb:55` | La norma y la marca de acreditación del informe |
| La ÚLTIMA columna es el Resultado | gráficos de tendencia | La tendencia y el valor informado |
| Las fórmulas direccionan `col{posición}` | `blur_calculation` | El cálculo entero, en silencio |

De ahí el aviso en mayúsculas del propio sistema viejo:

```
PASO 2
ORDENAR LAS POSICIONES DE LAS COLUMNAS
OJO: LA COLUMNA RESULTADO SIEMPRE ES LA ULTIMA
```
— `labo_old/README_ADD_COLUMNS.md:6-8`

Y el procedimiento para agregar una columna era **pegar código temporal en una
vista de producción, desplegar, comentarlo y volver a desplegar**
(`README_ADD_COLUMNS.md:11-37`).

---

## 5. El mecanismo de VALOR CONSTANTE, a fondo

Es el punto pedido explícitamente, así que va completo: qué es, quién lo
escribía, cuándo, con qué alcance y cómo llegaba a las filas.

### 5.1 Qué representa

Magnitudes que son **las mismas para toda la tanda del día** y que el analista
no debería retipear en cada muestra:

- **El factor de la solución titulante de KOH** y el **volumen del blanco** del
  Número Ácido. Se recalculan cuando se titula una solución nueva, y entran
  directo en la fórmula del resultado: `(volumen_gastado - vol_blanco) *
  factor_koh / peso_aceite`.
- **La temperatura y la humedad ambiente** de la sala, en las seis pruebas
  eléctricas.
- **La temperatura y la densidad del agua** de referencia de la Tensión
  Interfacial.
- **La temperatura de ensayo** de las dos Resistividades Volumétricas (25 y 100),
  que es la que le da el nombre a la prueba.

O sea: **no es un "valor por omisión" cosmético. Es una constante de calibración
que participa del cálculo del resultado informado.**

### 5.2 Quién lo escribía y desde dónde

Una pantalla propia, titulada literalmente **"Valores Constantes"**:

- Controlador: `labo_old/app/controllers/pr_management/templates/patrons_controller.rb`
  (y el gemelo `admin_templates/patrons_controller.rb`).
- Vista: `labo_old/app/views/pr_management/templates/patrons/edit.html.erb:1`
  (`<% title "Valores Constantes" %>`) — un modal.
- Ruta: `GET/PUT /pr_management/templates/patrons/{id}/edit`, donde **`{id}` es
  el id de la PRUEBA** (`patrons_controller.rb:34`: `@main_model =
  LabCategoryDetail.find(params[:id])`).
- Enlace: en la **barra lateral de cada prueba, al lado de "Muestras"** —
  `_app_sidebar_left_menus.html.erb:248-252`. Es decir, a un clic de la bancada.

El cuerpo del formulario es una fila con una celda por constante:

```erb
<%= f.fields_for :lab_category_sub_details,
      f.object.lab_category_sub_details.where('deleted=0 AND is_reuse=1')
        .order('lab_category_sub_details.num_pos ASC'), :wrapper => false do |nested_form| %>
  <td><%= nested_form.text_field :reuse_value, class: 'form-control text-black',
          'data-parsley-required'=>"true", placeholder: "Requerido.", :required=> true %></td>
<% end %>
```
— `labo_old/app/views/pr_management/templates/patrons/partials/_form_edit.html.erb:11-13`

Quién podía entrar: acceso **31**, que en el sembrador se llama **"SP - Patron"**
(`labo_old/db/seeds.rb:46`). Es un permiso **separado** del de editar la
plantilla de la prueba (accesos 34/35/36) y del de configurar módulos (14).

> **Agujero del viejo, para que no se copie:** el `edit` verifica el acceso 31
> (`patrons_controller.rb:8`) pero el **`update` no verifica nada**
> (`patrons_controller.rb:19-29`), y los parámetros entran con
> `params.require(:lab_category_detail).permit!` (`:44`). Cualquier usuario
> logueado podía hacer el PUT y, por anidamiento, escribir **cualquier atributo
> de cualquier columna** —incluidos `name`, `is_required`, `is_blocked`— pasando
> ids de columnas de otras pruebas.

### 5.3 Alcance: por PRUEBA, no por hoja ni por fila

El valor vive en `lab_category_sub_details.reuse_value`, o sea **en la definición
de la columna**. No hay ninguna tabla que lo ligue a una hoja (`labs`) ni a una
fecha. Consecuencias exactas:

- Es **uno solo por prueba** y vale para **todas las hojas, de todos los días**,
  hasta que alguien lo cambie a mano.
- Cambiarlo **no** modifica ninguna fila ya cargada: las filas viejas conservan
  la copia del valor que regía el día en que se cargaron.
- **No queda registro de la vigencia**: no se puede responder "qué factor de KOH
  regía el 12 de marzo" mirando la definición; solo mirando las celdas de las
  filas de ese día (o el rastro de auditoría, ver §5.6).

### 5.4 Cómo llegaba a las filas

**Se copiaba como valor inicial del `<input>` al crear una fila nueva**, y nada
más:

```erb
<% if params[:lab_file_id].present? %>
  <% @file_values = LabFileDetail.where("lab_file_id = ? AND lab_category_sub_detail_id = ?",
                                        params[:lab_file_id], array.id) %>
  <% if @file_values.size > 0 %>
    <% @file_values.each do |file_value| %>
      value="<%= file_value.name %>"
    <% end %>
  <% else %>
    value="<%= array.reuse_value %>"
  <% end %>
<% else %>
  value="<%= array.reuse_value %>"
<% end %>
```
— `labo_old/app/views/pr_management/templates/lab_details/partials/_form_new_nested.erb:13-24`
(idéntico para tipo número, `:33-44`; y diez veces en la variante
`_form_new_nested_poli.html.erb`, la del Grado de Polimerización).

Cuatro hechos que se deducen de ese fragmento y que definen el mecanismo:

1. **Solo en el alta.** La vista de edición (`_form_edit_nested.html.erb:14,21`)
   pone `value="<%= @sub_detail.name %>"`: nunca vuelve a mirar `reuse_value`.
2. **El archivo del instrumento gana.** Si la fila se está creando a partir de
   un TXT importado y ese TXT trae la columna, se usa el valor del archivo; la
   constante es el respaldo.
3. **Solo para texto y número.** Las ramas de selección (tipo 3) y fecha (tipo 4)
   no ponen `value`, así que una constante sobre un `select` no hacía nada.
4. **Se copia, no se referencia.** Lo que se guarda en `lab_sub_details.name` es
   una copia literal. Es lo correcto para un registro de laboratorio: la fila
   dice con qué factor se calculó, no "el factor vigente hoy".

**El sistema nunca escribía `reuse_value` desde la bancada.** Se buscó en todo el
código: las únicas escrituras son las de la pantalla de constantes y las de los
dos formularios de configuración. No hay "aprender del último valor cargado".

### 5.5 Cuántas eran, y con qué valores

Del volcado real (`docs/migracion/esquema/catalogos-definiciones.sql`):
**16 columnas constantes en 9 pruebas**, y `has_reuse=1` en exactamente esas 9.

| Prueba | Columna | `reuse_value` |
|---|---|---|
| Número Ácido | Factor KOH | `0.514` |
| Número Ácido | Vol Blanco | `0.181` |
| Factor De Potencia 25º | Temperatura Ambiente (ºC) | `20.2` |
| Factor De Potencia 25º | Humedad Ambiente (%) | `60` |
| Factor De Potencia 90º | Temperatura Ambiente (ºC) | `21.5` |
| Factor De Potencia 90º | Humedad Ambiente (%) | `62` |
| Factor De Potencia 100º | Temperatura Ambiente (ºC) | `20.2` |
| Factor De Potencia 100º | Humedad Ambiente (%) | `66` |
| Rigidez Dieléctrica | Temperatura Ambiente (ºC) | `20.2` |
| Rigidez Dieléctrica | Humedad Ambiente (%) | `65` |
| Rigidez Dielectrica Electrodos planos | Temperatura Ambiente (ºC) | `21.3` |
| Rigidez Dielectrica Electrodos planos | Humedad Ambiente (%) | `41` |
| Tensión Interfacial | Temp. Agua | `20.1` |
| Tensión Interfacial | Densidad Agua | `0.998` |
| Resistividad Volumétrica 25º | Temperatura (ºC) | `25` |
| Resistividad Volumétrica 100º | Temperatura (ºC) | `100` |

Las 16 son de tipo **2 (Número)**, `is_required=1`, `is_blocked=0`. Las dos del
Número Ácido llevan además `is_blur=1`, o sea que al salir de la celda se
recalculaba el resultado.

Sí, **traía constantes sembradas**: el sembrador viejo las escribía con valores
concretos (`labo_old/db/seeds.rb:191-192`: `reuse_value: "0.5531"` y `"0.512"`),
distintos de los que hay hoy en producción (`0.514` / `0.181`) — prueba de que el
supervisor efectivamente los cambia.

### 5.6 Auditoría del cambio

`LabCategorySubDetail` está declarado `audited` (`lab_category_sub_detail.rb:18-19`).
Cambiar `reuse_value` **dejaba un registro** en la tabla `audits` con usuario, IP,
`audited_changes` y fecha, asociado a la prueba. Para un laboratorio ISO/IEC
17025 esto no es un adorno: el factor del titulante entra en el cálculo de cada
número ácido informado, y hay que poder demostrar cuál regía y quién lo cambió.

---

## 6. Qué hacía cada bandera, en la práctica

### 6.1 `is_required`

Añadía `required="required"` y `data-parsley-required="true"` al `<input>`
(`_form_new_nested.erb:28`). **Solo validación de navegador.** No hay validación
de modelo (`lab_sub_detail.rb` no tiene ninguna) ni de controlador
(`lab_details_controller.rb:165`: `permit!`). Un POST directo guardaba la celda
vacía.

En los datos: **201 de 207 columnas vivas tienen `is_required=1`**. Es decir, la
bandera existe pero está puesta en casi todo: informativa más que discriminante.

### 6.2 `is_blocked`

Añadía `readonly="true"` (`_form_new_nested.erb:27`). **Solo navegador**, otra
vez: quitar el atributo desde el inspector, o hacer el POST a mano, escribía el
campo. Y como el cálculo también era de cliente, no había recálculo de servidor
que corrigiera el valor inyectado.

17 columnas la tenían. Son las calculadas: `Resultado (mgKOH/g aceite)`,
`Repetibilidad`, `Total de Gases Combustibles`, `Total`, los 8 pasos intermedios
del Grado de Polimerización, `Total de Sedimentos`, los dos `Resultado (Ωcm)`…
más una que no es un cálculo: **`Factor De Potencia 90º → Norma`**, bloqueada
porque esa prueba tiene una sola norma posible.

### 6.3 `num_pos`

Dos trabajos en una columna: **presentación** (orden de las cabeceras, siempre
`order("num_pos ASC")`) y **semántica** (§4). El segundo es el que hacía
peligroso reordenar.

### 6.4 `applicability_flag` de las opciones — la acreditación

`varchar(5)`, comentario del DDL: `'A, NA, etc.'`. Valores reales en producción:
**`A` en 10 opciones, `NA` en 27, nulo en 48** (de 85 vivas).

Se imprime como **superíndice al lado de la norma** en el informe del cliente:

```erb
<% norma, flag = @rem_report_detail.aci_lab_detail&.norma_y_flag %>
<%= norma %> <% if flag.present? %><sup>(<%= flag %>)</sup><% end %>
```
— `labo_old/app/views/im_management/rem_reports/partials/_report_physicals.erb:33-35`
(y trece veces más en el mismo archivo, una por ensayo fisicoquímico).

Y la leyenda al pie:

```
(A) Acreditado
(NA) No Acreditado
Esta prueba está acreditada bajo la acreditación del laboratorio ISO/IEC 17025
emitida por la Junta Nacional de Acreditación ANSI-ASQ. Consulte el certificado
y el alcance de la acreditación AT-2596.
```
— `_report_physicals.erb:338-341`

O sea: **es una afirmación legal por método de ensayo.** La misma prueba puede
estar acreditada con `ASTM D1816` y no con `IEC 60156`, y el papel lo dice.

Se editaba en un solo lugar: el editor anidado **de edición** de la prueba
(`.../category_details/partials/_form_edit_nested.html.erb:36`, con placeholder
`"Acreditación."`). **El formulario de ALTA no lo mostraba**
(`_form_new_nested.html.erb:32-38`), ni tampoco el orden de las opciones: una
prueba nueva se creaba sin poder declarar acreditación, y había que editarla
después. Las dos pantallas estaban desincronizadas.

### 6.5 El tipo de columna (1=texto, 2=número, 3=opciones, 4=fecha)

Ver §2.3. En resumen: decidía **qué control HTML se pintaba**, con `if/elsif` por
id repetido en cuatro vistas; `1` y `2` eran el mismo `input type="text"` con o
sin validación de cliente; `3` renderizaba un `<select>` cuyo `value` era el
**id** de la opción; `4` un `datetime-local`. Sin tipado de base ni de servidor.

### 6.6 `is_imported` / `imported_value` / `imported_remove_value`

El mecanismo de **"Lectura de Archivo TXT"**: subir el protocolo crudo del
instrumento y prellenar la hoja. El botón aparecía solo si la prueba tenía al
menos una columna con `is_imported=1`
(`.../lab_details/partials/_form_new.html.erb:41-49`).

El parser completo eran siete líneas dentro de una vista:

```erb
<% @word_searched = category_sub_detail.imported_value.to_s %>
<% @word_removed  = category_sub_detail.imported_remove_value.to_s %>
<% @data = @main_model.description.match(/#{@word_searched}*(.*)$/) %>
...
<% @value_saved = @data[1].strip.delete! @word_removed %>
```
— `labo_old/app/views/pr_management/templates/imports/partials/_form_show.html.erb:11-24`

**En producción hay 31 mapeos configurados**, en 7 pruebas:

| Prueba | Columnas mapeadas | Ejemplos de `imported_value` / `imported_remove_value` |
|---|---|---|
| Factor De Potencia 25º y 100º | 2 cada una | `'Temperatura del ensayo:'` / `'°C'`; `'a 60 Hz:'` / `'%'` |
| Rigidez Dieléctrica y Rigidez EP | 2 cada una | `'Temperatura:'` / `'°C'`; `'Valor medio:'` / `'kV'` |
| Análisis Cromatográfico | 10 | `'Sample name:'`, `'\r\nH2         '`, `'C2H2        '` … |
| Furanos | 5 | `'2-furfuraldehido '`, `'furfurilalcohol'` … |
| Resistividad Volumétrica 25º y 100º | 4 cada una | `'Rho+:'` / `':Ωcm'`, `'Número de muestra:'` |

Esos 31 renglones son **configuración de laboratorio ganada con el instrumento
en la mano**: la etiqueta exacta que imprime cada equipo, con sus espacios y sus
erratas (`'5-metl-2-furfualdehido'`).

### 6.7 `report_use`

**Declaraba qué columnas salen impresas en el informe del cliente.** Está en
**63 columnas vivas**, y la lista es exactamente el conjunto de resultados
informables, incluyendo las pruebas con varios resultados:

- Análisis Cromatográfico: 11 (los 9 gases + TDCG + Total)
- Partículas: 8 (Código ISO + los 7 rangos de tamaño)
- Metales en Aceite: 8 (Al, Cu, Fe, Pb, Ag, Sn, Zn, Si)
- Furanos: 6 · PCB: 4 · Sedimentos: 4 · Azufres: 2+2+1
- y un único `Resultado` en cada una de las demás.

Es importante subrayarlo porque cambia el diagnóstico: **el sistema viejo SÍ
declaraba qué columnas eran informables.** Lo que no declaraba era a qué
parámetro medible correspondía cada una. Son dos cosas distintas y la segunda es
la que justificaba `output_analyte_id`.

---

## 7. Las pantallas de administración del viejo

Cuatro CRUD en `pr_management/configurations`, todos con el mismo esqueleto
(`before_action`, `User.authentication(session[:user_id], 14)`, `permit!`):

| Ruta | Título en pantalla | Modelo |
|---|---|---|
| `/pr_management/configurations/category_detail_types` | **Categorías** | `LabCategoryDetailType` |
| `/pr_management/configurations/category_details` | **Módulos** | `LabCategoryDetail` + columnas anidadas + opciones anidadas |
| `/pr_management/configurations/category_subdetail_types` | **Tipos de Columnas** | `LabCategorySubDetailType` |
| `/pr_management/configurations/category_subdetails` | **Columnas de los Módulos** | `LabCategorySubDetail` + opciones anidadas |

(`category_detail_types_controller.rb:128-131`, `category_details_controller.rb:135-138`,
`category_subdetail_types_controller.rb:128-131`, `category_subdetails_controller.rb:135-138`.)

**Qué se podía editar por pantalla, y qué no:**

| Campo | "Módulos" (anidado) | "Columnas de los Módulos" (suelto) |
|---|---|---|
| `num_pos`, `name`, tipo | sí | sí |
| `is_required`, `is_blur`, `is_blocked`, `is_reuse`, `reuse_value`, `report_use` | sí | sí |
| `is_imported`, `imported_value`, `imported_remove_value` | sí | **no** |
| Opciones: texto | sí | sí |
| Opciones: `num_pos` | solo en **editar** | **no** |
| Opciones: `applicability_flag` | solo en **editar** | **no** |
| Opciones: `is_hidden` | **por ninguna pantalla** — solo por SQL | **no** |

Dos pantallas que editan lo mismo y no coinciden. Y en el formulario de alta
anidado los encabezados están cruzados respecto de las celdas: el `<th>` dice
"Mostrar en Reporte" en la posición 13 pero la celda 13 es el botón de eliminar
(`_form_new_nested.html.erb:16-17` contra `:74-80`).

Ninguna pantalla exponía `is_hidden` de las opciones, aunque el código de
bancada lo consulta (`_form_new_nested.erb:54`). En producción hay 2 opciones
ocultas: se pusieron por base de datos.

---

## 8. Lo sembrado: 29 pruebas, 207 columnas, 93 opciones

Del volcado real. Los sembradores del repositorio viejo (`db/seeds.rb`,
`db/seeds2.rb`) están **atrasados**: `seeds.rb` tiene 26 pruebas y 156 columnas y
usa `is_blur`/`has_patron` en `lab_category_details`, que ya no existen;
`seeds2.rb` es todavía más viejo y usa la convención `1=No / 2=Si`. La foto real
es esta:

| id | pos | Prueba | Columnas | Constantes | Import TXT | `report_use` | Fórmula JS |
|---:|---:|---|---:|---:|---:|---:|:--:|
| 1 | 1 | Número Ácido | 9 | 2 | 0 | 1 | sí |
| 2 | 2 | Factor De Potencia 25º | 7 | 2 | 2 | 1 | — |
| 26 | 3 | Factor De Potencia 90º | 7 | 2 | 0 | 1 | — |
| 3 | 4 | Factor De Potencia 100º | 7 | 2 | 2 | 1 | — |
| 4 | 5 | Rigidez Dieléctrica | 8 | 2 | 2 | 1 | — |
| 27 | 6 | Rigidez Dielectrica Electrodos planos | 8 | 2 | 2 | 0 | — |
| 5 | 7 | Tensión Interfacial | 11 | 2 | 0 | 1 | — |
| 6 | 8 | Contenido de Agua | 9 | 0 | 0 | 1 | sí |
| 7 | 9 | Color | 6 | 0 | 0 | 1 | — |
| 8 | 10 | Condición Visual | 3 | 0 | 0 | 1 | — |
| 9 | 11 | Densidad Relativa | 3 | 0 | 0 | 1 | — |
| 28 | 12 | Resistividad Volumétrica 25º | 7 | 1 | 4 | 1 | sí |
| 29 | 13 | Resistividad Volumétrica 100º | 7 | 1 | 4 | 0 | sí |
| 10 | 14 | Análisis Cromatográfico | 13 | 0 | 10 | 11 | sí |
| 11 | 15 | PCB | 7 | 0 | 0 | 4 | — |
| 12 | 16 | Furanos | 9 | 0 | 5 | 6 | sí |
| 13 | 17 | Azufre 1275B | 7 | 0 | 0 | 2 | — |
| 14 | 18 | Azufre 62535 (48 horas) | 7 | 0 | 0 | 2 | — |
| 15 | 19 | Azufre 62535 (72 horas) | 6 | 0 | 0 | 1 | — |
| 16 | 20 | Grado de Polimerización | 16 | 0 | 0 | 0 | sí |
| 17 | 21 | Viscocidad | 8 | 0 | 0 | 1 | — |
| 18 | 22 | Partículas | 10 | 0 | 0 | 8 | — |
| 19 | 23 | Metales en Aceite | 10 | 0 | 0 | 8 | — |
| 20 | 24 | Inhibidor | 3 | 0 | 0 | 1 | — |
| 21 | 25 | DBDS | 4 | 0 | 0 | 1 | — |
| 22 | 26 | Sedimentos | 6 | 0 | 0 | 4 | sí |
| 23 | 27 | Fluidez | 3 | 0 | 0 | 1 | — |
| 24 | 28 | Inflamación | 3 | 0 | 0 | 1 | — |
| 25 | 29 | Pasivador | 3 | 0 | 0 | 1 | — |
| | | **Total** | **207** | **16** | **31** | **63** | **8 pruebas** |

Otros números de la foto real:

- Tipos de columna: **texto 71 · número 78 · selección 52 · fecha 6** (= 207).
- `is_required=1` en **201**; `is_blocked=1` en **17**; `is_blur=1` en **31**.
- `is_grouped=0` en **las 29** pruebas: ninguna estaba marcada como "no usa
  patrón ni duplicado".
- Opciones: **93 filas, 85 vivas** (8 borradas, entre ellas la errata
  `'PP-LA-01C-100.'` con el punto al final); **2 ocultas**; flag `A` en 10, `NA`
  en 27.
- Una sola columna borrada lógicamente en toda la historia de la tabla.

---

## 9. Lo que hay en el sistema nuevo

- **Migración**: `database/migrations/2026_07_28_090000_create_test_definitions_tables.php`
  crea `test_groups`, `analytes`, `test_definitions`, `test_fields`,
  `test_field_options`.
- **Agregados posteriores**:
  `2026_07_28_150000_create_test_field_instrument_table.php` (instrumentos por
  columna), `2026_07_29_120000_add_is_accredited_to_test_field_options.php`,
  `2026_07_29_190000_add_detection_limit_to_test_fields.php`,
  `2026_07_28_180000_create_sample_diagnoses_table.php` (agrega
  `report_comment_group`).
- **Modelo**: `app/Models/TestField.php` — roles declarados
  (`none/sample_code/standard/result/temperature/observation`), tipos
  (`text/number/select/date/computed/instrument`), `porQueNoAdmite()` que aplica
  el rango de verdad.
- **Controlador**: `app/Http/Controllers/LabManagement/TestFieldController.php` —
  CRUD de columnas + `reorder` + `checkFormula` + `constants` / `updateConstants`.
- **Pantallas**: `resources/js/Pages/TestDefinitions/Fields.vue` (editor, con
  arrastre y flechas, orden confirmado con POST explícito) y
  `Constants.vue` (los valores constantes).
- **Tipos de columna**: `config/lab_field_types.php` en vez de tabla, con el
  porqué documentado en el propio archivo (líneas 8-30).
- **Importador legado**: `app/Console/Commands/ImportLegacyTestsCommand.php`.
- **Datos que completan lo que el viejo no declaraba**:
  `database/seeders/data/analyte_map.json` (41 mapeos columna→parámetro, más 4
  pruebas explícitamente pendientes), `test_formulas.json` (9 fórmulas portadas,
  1 pendiente documentada), `test_field_types.json` (34 correcciones de
  tipo/unidad/decimales/mínimo), `instruments.json`, `detection_limits.json`.

---

## 10. Tabla de equivalencias, columna por columna

### 10.1 Grupo de pruebas

| Viejo (`lab_category_detail_types`) | Nuevo (`test_groups`) | Estado |
|---|---|---|
| `id` | `id` + `slug` | ✅ |
| `name` | `name` | ✅ |
| — | `code` (derivado del nombre, `TestGroup::codeFrom()`) | ➕ nuevo |
| `icon_label` | **nada** | ❌ **falta** |
| `deleted` | `deleted_at` + `deleted_by` + `deleted_description` | ✅ mejor |
| — | `tenant_id`, `is_active`, `sort_order`, candado | ➕ nuevo |

### 10.2 La prueba

| Viejo (`lab_category_details`) | Nuevo (`test_definitions`) | Estado |
|---|---|---|
| `lab_category_detail_type_id` | `test_group_id` | ✅ renombrado |
| `name` | `name` | ✅ |
| — | `code`, `slug` | ➕ nuevo |
| `container` | `container` | ✅ |
| `num_pos` | `sort_order` | ✅ renombrado |
| `is_grouped` | `is_grouped` (conservado por trazabilidad) + `has_control` / `requires_control` / `requires_duplicate` | ✅ mejor — y ahora lo valida el servidor (`app/Models/Worksheet.php:291,295`) |
| `blur_calculation` (JS de toda la prueba) | `test_fields.formula` por columna, evaluada en el servidor | ✅ mucho mejor |
| `description` | `description` | ✅ |
| `unit_name_amchart` | `chart_unit` | ✅ renombrado |
| `has_reuse` | mapeado a **`has_control`** por el importador | ⚠️ **mal mapeado** |
| `deleted` | `deleted_at` + soft deletes | ✅ |
| — | `replicates`, `report_comment_group`, `legacy_id`, `tenant_id`, `is_active`, candado | ➕ nuevo |

### 10.3 La columna

| Viejo (`lab_category_sub_details`) | Nuevo (`test_fields`) | Estado |
|---|---|---|
| `lab_category_detail_id` | `test_definition_id` | ✅ |
| `lab_category_sub_detail_type_id` (fk a tabla) | `type` (cadena contra `config/lab_field_types.php`) | ✅ mejor — el tipo dejó de ser dato falso |
| `name` (con `\r\n` para cabecera de 3 líneas) | `label` (una línea) + `unit` aparte | ⚠️ **parcial** — ver hueco H-7 |
| `num_pos` | `sort_order` **+ `role`** | ✅ mucho mejor — el orden dejó de tener semántica |
| `is_required` | `is_required` | ✅ mejor — validado en el servidor al cerrar la hoja (`WorksheetService.php:641`) |
| `is_blocked` | `is_locked` | ⚠️ **columna existe, importa el dato, no la lee nadie** |
| `is_blur` | reemplazado por `formula` en la columna calculada | ✅ mejor |
| `is_reuse` | `is_reusable` | ✅ |
| `reuse_value` | `default_value` | ✅ renombrado |
| `is_imported` | trasladado a `instrument_formats.column_map` | ⚠️ **estructura sí, dato no migrado** |
| `imported_value` | `column_map[].match` | ⚠️ ídem |
| `imported_remove_value` | **no hace falta** (el parser descarta la unidad al leer el número) | ✅ mejor por diseño |
| `report_use` | `report_visible` | ⚠️ **columna existe, el importador no la lee** |
| `deleted` | `deleted_at` | ✅ |
| — | `code` (identificador de fórmulas), `slug` | ➕ nuevo |
| — | `role` | ➕ **la pieza clave** |
| — | `output_analyte_id` | ➕ nuevo |
| — | `formula` (validada, sin ciclos, evaluada en servidor) | ➕ nuevo |
| — | `unit`, `decimals`, `min_value`, `max_value`, `min_exclusive`, `detection_limit` | ➕ nuevo |
| — | `replicates` | ➕ nuevo |
| — | `legacy_id` | ➕ trazabilidad |

### 10.4 Las opciones

| Viejo (`lab_category_sub_detail_options`) | Nuevo (`test_field_options`) | Estado |
|---|---|---|
| `lab_category_sub_detail_id` | `test_field_id` | ✅ |
| `name` | `value` | ✅ renombrado |
| `applicability_flag` | `accreditation_flag` (rótulo) **+ `is_accredited` (hecho)** | ✅ mucho mejor |
| `num_pos` | `sort_order` | ✅ |
| `is_hidden` | `is_hidden` — y **ahora sí editable por pantalla** | ✅ mejor |
| `deleted` | **no existe**: las opciones se ocultan, no se borran | ✅ mejor por diseño |
| — | `legacy_id` | ➕ |
| (celda guarda el `id` de la opción en un `varchar`) | `worksheet_values.option_id` como clave foránea | ✅ mucho mejor |

### 10.5 El tipo de columna

| Viejo (`lab_category_sub_detail_types`) | Nuevo | Estado |
|---|---|---|
| Tabla con CRUD, 4 filas, ids clavados en 4 vistas | `config/lab_field_types.php` + 2 tipos nuevos (`computed`, `instrument`) | ✅ mejor — deja de parecer editable lo que no lo era |
| Pantalla "Tipos de Columnas" | **no existe** (el propio config la promete en su línea 27) | ❌ falta, cosmético |

---

## 11. HUECOS

### 🔴 Bloqueante

**H-1 · El cambio del valor constante ya no se audita.**

`TestField` y `TestFieldOption` **no usan el trait `Auditable`**:

```php
class TestField extends Model
{
    use HasFactory, SoftDeletes;
```
— `app/Models/TestField.php:31` (comparar con `TestDefinition.php:32` y
`TestGroup.php:29`, que sí lo usan)

Y `updateConstants()` solo hace `$field->update(['default_value' => $value])`
(`TestFieldController.php:211`), sin escribir nada en el registro de auditoría.

En el sistema viejo esto **sí quedaba registrado**: `LabCategorySubDetail` está
declarado `audited associated_with: :lab_category_detail`
(`labo_old/app/models/lab_category_sub_detail.rb:18`), así que cada cambio de
`reuse_value` dejaba usuario, fecha, IP y valor anterior en la tabla `audits`.

**Por qué de negocio.** El factor de la solución titulante entra directamente en
la fórmula de cada número ácido informado:
`(volumen_gastado - vol_blanco) * factor_koh / peso_aceite`. Si un cliente o el
organismo acreditador cuestiona un resultado de hace ocho meses, hay que poder
demostrar **qué factor regía ese día y quién lo cargó**. Hoy el sistema nuevo
solo tiene el último valor, sin historia y sin autor. Es una pérdida de
trazabilidad frente a lo que ya funcionaba, en el dato más sensible del
mecanismo, y en un laboratorio con alcance ISO/IEC 17025 declarado en el propio
informe. Lo mismo aplica, con la misma gravedad, a `is_accredited` de las
opciones: cambiar esa casilla cambia una afirmación legal del papel y no queda
rastro (aunque acá el viejo tampoco auditaba — ver H-9).

---

### 🟠 Importante

**H-2 · `report_use` se descarta: el importador adivina por nombre lo que el
viejo tenía declarado.**

El importador lee las posiciones 0..9 y 14 de cada columna del volcado y **nunca
las 10, 11, 12 ni 13** (`ImportLegacyTestsCommand.php:192-193`). La 13 es
`report_use`. En su lugar, `report_visible` se deriva de una búsqueda por nombre:

```php
private const RESULT_HINTS = '/resultado|grado de polimerizaci|total de gases/i';
...
'report_visible' => $isResult,
```
— `ImportLegacyTestsCommand.php:61,208`

y `$isResult` solo es verdadero **si la coincidencia por nombre es exactamente
una** (`:195`). En 5 pruebas hay cero o varias, y quedan sin marcar: Tensión
Interfacial, PCB, Partículas, Metales en Aceite, Sedimentos.

Los números: `report_use=1` en **63 columnas** contra ~24 que el importador marca.
Las que se pierden son precisamente las de las pruebas multi-resultado — los 11
renglones de cromatografía, los 8 rangos de Partículas, los 8 elementos de
Metales, los 4 aroclores del PCB, los 4 de Sedimentos, los 6 de Furanos.

**Por qué de negocio.** El comentario del propio código dice que el viejo "no lo
declaraba"; es cierto para el parámetro medible (`output_analyte_id`) y **falso
para la visibilidad en el informe**. Ese dato existía, está en el volcado, y es
la decisión del laboratorio sobre qué se publica. Sembrarlo desde `report_use`
resolvía las 5 pruebas ambiguas sin pedirle nada a nadie. Hoy, en el sistema
nuevo, un informe de Partículas o de Metales no tiene ninguna columna marcada
como publicable y el laboratorio tiene que reconstruir a mano una configuración
que ya había tomado.

**H-3 · Los 31 mapeos de lectura de TXT no están migrados.**

La estructura nueva es mejor: `instrument_formats.column_map`
(`2026_07_28_100000_create_worksheets_tables.php:286`) con dos modos declarados,
`label` y `lookup`, y un parser que corrige los cinco defectos del viejo
(`app/Services/Lab/InstrumentFileParser.php:9-61`).

Pero: `is_imported`, `imported_value` e `imported_remove_value` **no los lee el
importador**, y **no hay ningún sembrador de `instrument_formats`** (se buscó en
`database/seeders/` y en `app/Console/Commands/`: cero coincidencias con
`column_map` o `InstrumentFormat`).

**Por qué de negocio.** Son 31 renglones que solo se pueden escribir con el
instrumento delante: la etiqueta literal que imprime cada equipo, con sus
espacios (`'C2H2        '`) y sus erratas de tipeo
(`'5-metl-2-furfualdehido'`). Sin ellos, la cromatografía y los furanos vuelven
a la carga manual de 9 y 5 valores por muestra, que es exactamente el trabajo
que la lectura de TXT eliminaba, y donde se cuelan los errores de transcripción
que el informe firmado después arrastra.

**H-4 · `is_locked` se guarda, se importa y no lo lee nadie.**

El importador la puebla correctamente (`ImportLegacyTestsCommand.php:205`:
`'is_locked' => $blocked === '1'`), y en la grilla de bancada la única cosa que
hace una celda de solo lectura es tener fórmula:

```js
const columnKind = (field) => {
    if (isComputed(field)) return 'computed';
```
— `resources/js/Components/Worksheets/WorksheetGrid.vue:121-122`

`readonly` en esa grilla es una **prop de toda la hoja** (`:71`), no una
propiedad por columna. Y en el servidor, `writeValues()` solo saltea los campos
con fórmula (`app/Services/Lab/WorksheetService.php:517-519`).

En los datos: 17 columnas venían bloqueadas y **9 de ellas no tienen fórmula en
el sistema nuevo** — las 8 etapas intermedias del Grado de Polimerización
(`Viscosidad de muestra (T)`, `Viscosidad de Solvente(T0)`,
`Concetracion muestra`, `Viscosidad especifica (ns)`,
`Viscosidad Intrinseca (n)`, `Grado de polimerización`, `Promedio`) y
`Factor De Potencia 90º → Norma`. La fórmula del GP está declarada pendiente a
propósito y con buen criterio (`test_formulas.json`, clave `pendientes`), pero
mientras lo esté, esas 8 celdas quedan **libremente editables** cuando antes eran
de solo lectura.

**Por qué de negocio.** Un valor derivado que el analista puede sobrescribir a
mano deja de ser derivado: se pierde la garantía de que el informe muestra lo que
la fórmula calculó. Es el tipo de agujero de integridad de datos que la
acreditación mira. Y la corrección es de una línea en la grilla, porque el dato
ya está cargado.

**H-5 · El permiso de "Valores Constantes" se fusionó con el de editar la
plantilla.**

Viejo: pantalla propia con acceso **31 ("SP - Patron")**, distinto de los accesos
de módulos (34/35/36) y de configuración (14).

Nuevo: `constants.update` está en el grupo
`Route::middleware('permission:test_definitions.edit')`
(`routes/lab_management.php:194-198`), el mismo que crea, edita y borra columnas,
cambia fórmulas y roles.

**Por qué de negocio.** Cambiar el factor del KOH es una tarea **diaria del
supervisor de bancada**. Reestructurar la plantilla de una prueba es una tarea
excepcional del responsable técnico. Fusionarlos obliga a darle a quien titula la
solución la llave para reescribir fórmulas y roles, o a negarle la tarea diaria.
El propio comentario del controlador reconoce que la pantalla se mantuvo separada
"porque el supervisor la usa a diario"; el permiso no acompañó a ese razonamiento.

**H-6 · `has_reuse` está mapeado a `has_control`, que es otra cosa.**

```php
[$id, $groupId, $name, $container, $pos, $isGrouped, $formula, $desc, $chartUnit, $hasReuse, $deleted] = ...
...
'has_control'   => $hasReuse === '1',
```
— `ImportLegacyTestsCommand.php:113,137`

La variable está bien nombrada y el destino está mal. `has_reuse` significa "esta
prueba tiene columnas constantes" (gate del menú "Valores Constantes",
`_app_sidebar_left_menus.html.erb:248`). `has_control` significa "esta prueba
corre con patrón de control", que en el viejo se deducía de `is_grouped` y del
recuento de filas ya cargadas (`.../lab_details/partials/_form_new.html.erb:23-37`).

Efecto sobre los datos reales: quedan marcadas como "corre con patrón" las **9**
pruebas que tienen constantes (Número Ácido, los tres Factores de Potencia, las
dos Rigideces, Tensión Interfacial y las dos Resistividades) y **no** las otras
20 — cuando en el viejo **las 29** tenían `is_grouped=0`, es decir, todas
ofrecían patrón y duplicado.

Además, `requires_control` y `requires_duplicate` quedan en `false` para las 29
(el importador no los toca), así que la regla de control de calidad del viejo
—mientras no haya un patrón y un duplicado cargados, el selector solo ofrece esas
dos filas— **no se reproduce en ninguna prueba**. La estructura para hacerlo
existe y está validada en el servidor (`app/Models/Worksheet.php:291,295`); lo
que falta es el dato.

**Por qué de negocio.** El patrón de control y el duplicado son la evidencia de
que la corrida del día es válida. Que la exigencia quede apagada en las 29
pruebas convierte una regla de calidad en una costumbre, y una hoja sin patrón se
puede cerrar y publicar.

---

### 🟡 Menor

**H-7 · La cabecera de tres líneas se aplanó y `unit` quedó casi vacío.**

El viejo usaba saltos de línea dentro de `name` para armar cabeceras de tres
renglones: nombre · símbolo · unidad (`"Hidrógeno\r\nH2\r\nppm"`,
`"Resultado\r\n(mgKOH/g aceite)"`), renderizadas con `simple_format`
(`.../lab_details/partials/_form_new.html.erb:57`).

El importador colapsa todos los espacios en uno
(`ImportLegacyTestsCommand.php:340`: `preg_replace('/\s+/', ' ', $v)`) y **no
puebla `unit`** (el array `$row` de `:198-210` no lo incluye). La grilla nueva
dibuja dos renglones, `label` y `unit`
(`WorksheetGrid.vue:609-627`), así que hoy muestra
`"Hidrógeno H2 ppm"` en una línea y el segundo renglón vacío. `test_field_types.json`
corrige unidad y decimales en **34 columnas** de 207; el resto sigue con la
unidad metida dentro del rótulo.

**Por qué de negocio.** No es un error de dato, es legibilidad de bancada: el
analista lee cabeceras de veinte columnas en una tablet, y `"D.Carbono CO2 ppm"`
en una línea es más lento de leer que el símbolo aislado. También afecta al
informe y a los ejes de los gráficos, que ya saben usar `unit` cuando existe.
TrafoDex resolvió exactamente este problema con tres claves de idioma
(nombre corto, símbolo, unidad) y ahí quedó bien; acá conviene la misma solución.

**H-8 · `min_exclusive` no se puede tocar desde la interfaz.**

La columna existe, está casteada (`TestField.php:82`), y `porQueNoAdmite()` la
aplica de verdad (`:100-112`). Pero **no está en la validación del controlador**
(`TestFieldController.php:259-297` valida `min_value`, `max_value`,
`detection_limit` y no `min_exclusive`) **ni en el formulario**
(`TestFieldFormModal.vue:39-66`). Solo la escribe
`LabTestFieldTypesSeeder.php:115,154` desde `test_field_types.json`.

**Por qué de negocio.** El caso que justifica esa columna —el cero que no es una
medición sino el "no medido" del sistema anterior, documentado en la migración
con los 626 ensayos de rigidez y los 104 de factor de potencia que fabricó— es
justamente el que el laboratorio va a querer aplicar a columnas nuevas. Hoy puede
poner `mínimo = 0` desde la pantalla y creer que rechaza el cero, y no lo
rechaza: la semántica queda inclusiva en silencio. Un mínimo que no hace lo que
la pantalla sugiere es peor que no tenerlo.

**H-9 · Renombrar una opción crea otra y oculta la vieja; no hay forma de
corregir una errata.**

`syncOptions()` busca por `['test_field_id', 'value']`
(`TestFieldController.php:372`) e **ignora el `id`** que el editor sí manda
(`FieldOptionsEditor.vue` / `TestFieldFormModal.vue:58-65`). Todo lo que no quedó en
la lista se marca `is_hidden` (`:391-393`).

Resultado: corregir `'ASTM 1275'` → `'ASTM D1275'` (errata real del volcado,
`labo_old/db/seeds.rb:388`) **no corrige nada**: crea una opción nueva, oculta la
vieja, y todos los ensayos históricos siguen apuntando a la opción con la errata
—que además es la que el informe imprime, porque el informe resuelve la opción
por su clave foránea—. En el viejo, el `fields_for` anidado con `id` sí
actualizaba el texto en su lugar y el histórico se corregía solo.

**Por qué de negocio.** La inmutabilidad es la decisión correcta cuando cambia el
*significado* de una opción. Pero una errata ortográfica en el nombre de una
norma no cambia el significado, y hoy no hay ninguna manera de arreglarla sin ir
a la base. Falta la distinción entre "renombrar" (corrige el histórico) y
"reemplazar" (oculta y crea).

**H-10 · `icon_label` del grupo no existe en el nuevo.**

`test_groups` no tiene columna de icono (`create_test_definitions_tables.php:61-80`,
`TestGroup.php:36-39`). El viejo la tenía, era obligatoria en el formulario, y
alimentaba el icono del menú (`bong`, `syringe`, `flask-vial`).

**Por qué de negocio.** Puramente visual: tres iconos en el menú de pruebas. Se
menciona por completitud, porque es un dato que existía, se editaba por pantalla
y se perdió sin nota.

**H-11 · No existe la pantalla de referencia de "Tipos de Columnas".**

`config/lab_field_types.php:27-29` dice: *"La pantalla 'Tipos de Columnas' pasa a
ser una referencia de solo lectura que se arma leyendo este archivo"*. No existe:
el config solo se consume desde `TestFieldController` y la grilla.

**Por qué de negocio.** El viejo tenía esa pantalla y el supervisor la conoce. Su
ausencia no rompe nada, pero deja una promesa escrita sin cumplir y quita el
único lugar donde el laboratorio podía ver qué tipos de columna existen sin abrir
el modal de alta.

**H-12 · El valor constante no se valida contra el tipo de la propia columna.**

`updateConstants()` valida `['nullable', 'string', 'max:255']`
(`TestFieldController.php:193-196`), sin mirar `type`, `decimals`, `min_value` ni
`max_value` de la columna. Un `0,514` con coma, o un `0.5531` pegado con un
espacio invisible, se guarda igual.

Atenuante real: al guardar la fila, `writeValues()` sí aplica el rango
(`WorksheetService.php:540`), así que el valor malo se rechaza ahí. El viejo era
exactamente igual de laxo (un `text_field` sin validación). Por eso queda en
menor: es una oportunidad, no una regresión. Pero el nuevo **sí tiene** el tipo
declarado y podría avisar en el momento en que el supervisor tipea, en vez de
tres muestras después en la bancada.

---

## 12. ¿La pantalla de constantes del nuevo cubre el mecanismo del viejo?

**Cubre lo esencial. Le falta una cosa importante, tiene una diferencia de
comportamiento no documentada y arregla dos defectos del viejo.**

### Lo que cubre bien

| Aspecto del mecanismo viejo | Nuevo | ¿Cubierto? |
|---|---|---|
| Pantalla propia, por prueba | `constants()` recibe la `TestDefinition` (`TestFieldController.php:181`) | ✅ |
| Lista solo las columnas constantes | `->where('is_reusable', true)` (`:186`) | ✅ |
| Un solo formulario, un solo guardado | un borrador para toda la tabla (`Constants.vue:34-53`) | ✅ |
| Alcance: por prueba, no por hoja | el valor vive en `test_fields.default_value` | ✅ idéntico |
| Se copia a la fila, no se referencia | la celda guarda su propia copia en `worksheet_values` | ✅ idéntico |
| Cambiarlo no toca filas ya cargadas | ídem | ✅ idéntico |
| El archivo del instrumento gana sobre la constante | el TXT se confirma aparte y sobreescribe el borrador | ✅ equivalente |
| No aplica a selección ni a instrumento | `carriedValue()` devuelve `null` para `option_id` / `instrument_id` (`WorksheetGrid.vue:222-225`) | ✅ idéntico, y documentado |

### Lo que arregla del viejo

- **Asignación masiva.** El viejo aceptaba `permit!` sobre atributos anidados y
  se podía escribir cualquier columna de cualquier prueba. El nuevo filtra:
  `if ($field === null) { continue; }` sobre el conjunto de campos declarados
  constantes de **esa** prueba (`TestFieldController.php:198-209`), y hay un test
  que lo fija: `test_los_valores_constantes_solo_aceptan_columnas_declaradas_constantes`
  (`tests/Feature/Lab/TestFieldEditorTest.php:261`).
- **El `update` sin autorización.** En el viejo, el PUT no verificaba ningún
  acceso. El nuevo lo tiene detrás de middleware de permiso
  (`routes/lab_management.php:194-198`) — aunque sea el permiso equivocado, ver
  H-5.

### Lo que NO cubre

1. **No queda rastro del cambio** (H-1). Es la pérdida más grave y la que hace
   que la respuesta a la pregunta del encabezado sea "solo en parte": el viejo
   auditaba el cambio del factor de KOH, el nuevo no.

2. **Cambió a qué se parece el valor arrastrado, y no está dicho en la
   pantalla.** El viejo prellenaba **siempre** con la constante de la definición.
   El nuevo prefiere **el valor de la fila anterior de la misma hoja** y solo cae
   a la constante si no hay fila anterior o si esa celda vino vacía:

   ```js
   const carriedValue = (field, replicate) => {
       if (field.is_reusable) {
           const previous = rows.value[rows.value.length - 1] ?? null;
           const carried = editableValue(field, valueOf(previous, field, replicate));
           if (carried !== null && carried !== '') return carried;
       }
       ...
       return field.default_value ?? null;
   };
   ```
   — `resources/js/Components/Worksheets/WorksheetGrid.vue:212-225`

   Para la temperatura ambiente es una mejora clara: sube dos grados a media
   mañana, el analista la corrige una vez y las siguientes muestras heredan la
   corrección. Para el **factor de KOH es un riesgo nuevo**: es una constante de
   calibración, no una condición de sala. Si el analista tipea mal el factor en
   la muestra 1, el error **se propaga a las 20 filas siguientes** en vez de que
   cada fila arranque del valor correcto de la definición. En el viejo, un error
   de dedo afectaba una fila.

   No hay ninguna advertencia de esto. La ayuda de la pantalla dice *"Los valores
   que se arrastran de una muestra a la siguiente"*
   (`resources/lang/es/test_fields.php:113`), que describe el comportamiento
   nuevo pero no distingue los dos casos, y el rótulo del campo sigue siendo
   **"Valor constante"** (`:28`), que sugiere lo del viejo.

   Lo razonable es distinguir dos naturalezas —constante de calibración (siempre
   desde la definición) contra condición ambiental (se arrastra de la fila
   anterior)— o, como mínimo, decir en la pantalla cuál de las dos hace.

3. **Está enterrada.** En el viejo el enlace "Valores Constantes" estaba en la
   **barra lateral de cada prueba, al lado de "Muestras"**
   (`_app_sidebar_left_menus.html.erb:248-252`): un clic desde la bancada. En el
   nuevo el único acceso es un botón dentro de `Fields.vue`
   (`resources/js/Pages/TestDefinitions/Fields.vue:152-154`), o sea prueba →
   ficha → columnas → constantes. La hoja de trabajo **no** enlaza a la pantalla
   (se buscó `constants` en todo `resources/js`: solo aparece en esos dos
   archivos). Quien titula una solución nueva a las siete de la mañana la va a
   corregir en la celda de la primera muestra en vez de en la definición — y
   entonces, con el arrastre del punto 2, el valor viejo de la definición nunca
   se actualiza y la corrección vive solo en las filas de ese día.

4. **No muestra el contexto que el nuevo ya tiene.** La pantalla lista rótulo,
   código, unidad y valor (`Constants.vue:86-104`). No muestra decimales, rango
   admitido, ni desde cuándo rige ese valor, ni quién lo puso — cosas que el
   modelo nuevo conoce (`decimals`, `min_value`, `max_value`) o podría conocer
   (H-1). Es la diferencia entre un campo de texto y una ficha de calibración.

---

## 13. Verificación de lo que sí está bien resuelto

Para que la lista de huecos no dé una impresión equivocada, esto es lo que se
verificó **correcto y completo**:

- **Rol declarado** en vez de posición: `role` en `test_fields`, con los seis
  valores y la deducción por etiqueta —no por posición— en la importación
  (`ImportLegacyTestsCommand.php:343-388`), con el caso `"Nº de Muestra"` y su
  ordinal de dos bytes ya resuelto con el modificador `u`.
- **Fórmulas**: 9 de las 10 del viejo portadas a expresiones por código, con el
  JavaScript original guardado al lado para cotejar
  (`database/seeders/data/test_formulas.json`); la décima (Grado de
  Polimerización) declarada pendiente con el motivo técnico exacto. Se validan al
  guardar, se detectan ciclos, y no se puede borrar una columna que una fórmula
  usa (`TestFieldController.php:107-132`), con tests.
- **Reordenar es seguro**: `reorder()` solo toca `sort_order` y filtra ids de
  otras pruebas (`:147-168`), con dos tests
  (`test_reordenar_no_cambia_ninguna_formula`,
  `test_reordenar_no_toca_columnas_de_otra_prueba`).
- **Acreditación**: separada en hecho (`is_accredited`) y rótulo
  (`accreditation_flag`), con la migración explicando el error real que eso
  corrige —`"NA"` contaba como acreditado— y el traspaso del dato viejo
  (`2026_07_29_120000_add_is_accredited_to_test_field_options.php:36-39`).
  Consumido por el informe (`app/Services/Lab/TestReportPayload.php:527-528`).
- **Opciones**: se ocultan, no se borran; `is_hidden` por fin editable; el orden
  y la marca de acreditación disponibles **también en el alta**, cerrando la
  desincronización de las dos pantallas del viejo.
- **Instrumentos por columna**: `test_field_instrument` recupera un dato que el
  viejo declaraba y el importador estaba tirando (las opciones de la columna
  "Bureta" eran las tres buretas), con el porqué en
  `2026_07_28_150000_create_test_field_instrument_table.php:10-34`.
- **Tipos de columna**: pasar de tabla a config está bien argumentado y es
  correcto — era un CRUD que no hacía nada.
- **`is_required`** ahora se valida en el servidor al cerrar la hoja, con la
  excepción bien pensada del código de muestra en filas de patrón y duplicado
  (`WorksheetService.php:638-660`).
- **Rango** (`min_value` / `max_value`) por fin aplicado
  (`TestField::porQueNoAdmite()` + `WorksheetService.php:540`): en el viejo
  vivía en la definición y no lo leía nadie.
- **Lo que se deja sin declarar, se deja sin declarar**: las 4 pruebas de
  `analyte_map.json → pendientes` y la fórmula pendiente están anotadas con el
  motivo, en vez de adivinadas. Es el criterio correcto para datos que terminan
  en un informe firmado.

---

## 14. Orden sugerido de corrección

| # | Hueco | Costo estimado | Por qué en este orden |
|---|---|---|---|
| 1 | H-1 auditoría de `TestField` / `TestFieldOption` | trivial (agregar el trait + `auditModule`) | Es un `use` y recupera trazabilidad legal |
| 2 | H-4 honrar `is_locked` en la grilla y en `writeValues()` | bajo | El dato ya está cargado; hoy 9 celdas derivadas son editables |
| 3 | H-6 mapear `has_reuse` a su lugar y sembrar `has_control` / `requires_*` desde `is_grouped` | bajo | Reactiva la exigencia de patrón y duplicado |
| 4 | H-2 sembrar `report_visible` desde `report_use` | bajo | El dato está en el volcado; resuelve las 5 pruebas ambiguas |
| 5 | H-5 permiso propio para los valores constantes | bajo | Devuelve la tarea diaria al supervisor sin darle la plantilla |
| 6 | §12.2 decidir y documentar el arrastre (constante de calibración vs condición ambiental) | medio (decisión + interfaz) | Riesgo real de propagar un error de dedo en el factor de KOH |
| 7 | §12.3 enlazar los valores constantes desde la hoja de trabajo | bajo | Sin esto, el mecanismo existe y no se usa |
| 8 | H-3 sembrar `instrument_formats.column_map` desde los 31 mapeos del viejo | medio | Recupera configuración que solo se obtiene con el instrumento delante |
| 9 | H-8 `min_exclusive` editable | trivial | Hoy la pantalla promete algo que no hace |
| 10 | H-7 unidad y símbolo fuera del rótulo | medio | Legibilidad de bancada, informe y gráficos |
| 11 | H-9 renombrar vs reemplazar una opción | medio | Hoy no hay forma de corregir una errata |
| 12 | H-10, H-11, H-12 | bajo | Completitud |
