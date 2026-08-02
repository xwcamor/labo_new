# GAP-4 — Bancada y cuadros de condiciones

> **Qué se audita.** Dos bloques del sistema Rails de 2019 (`/home/user/labo_old`,
> revisado en SOLO LECTURA, sin modificar ningún archivo):
>
> 1. **La bancada**: la pantalla donde el analista carga los ensayos
>    (`pr_management/templates/{labs, lab_details, imports, patrons,
>    patron_tendences, tendences}`), sus modelos y sus vistas.
> 2. **Los cuadros de condiciones**: `conditions_management/{cromas, fiquis,
>    pcbs}`, la pantalla desde la que el analista fijaba el **valor de
>    orientación** de cada parámetro según aceite, tipo de equipo, estado de
>    servicio y clase de tensión.
>
> **Aviso sobre el encargo.** El archivo
> `app/controllers/sample_management/samples_controller.rb` **tiene 0 bytes** y
> `app/views/sample_management/**` no lo referencia ninguna ruta
> (`config/routes.rb` no declara el espacio `sample_management`). Ya está
> anotado como código muerto en
> [`../12-CHECKLIST.md`](../12-CHECKLIST.md) → "Lo que NO hay que portar". La
> bancada real vive en `pr_management/templates`, y es lo que se auditó.
>
> **Archivos del viejo revisados: 120.**
> 11 controladores · 13 modelos · 60 vistas de `pr_management/templates` ·
> 29 vistas de `conditions_management` · más `config/routes.rb`, `db/schema.rb`,
> `app/models/rem_report_detail.rb`, `app/models/rem_report.rb`,
> `app/views/im_management/rem_reports/partials/_form_add_details_{physicals,cromas,pcbs}.html.erb`
> y `app/views/layouts/_app_sidebar_left_menus.html.erb` (los consumidores de los
> cuadros y el menú que da acceso a la bancada).
>
> **Lo ya documentado no se repite.** Los huecos que ya tienen lugar en
> [`../12-CHECKLIST.md`](../12-CHECKLIST.md), en
> [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md),
> [`B-formulas.md`](B-formulas.md) o [`E-cobertura-tablas.md`](E-cobertura-tablas.md)
> aparecen marcados **DECIDIDO**, con la referencia y sin volver a argumentarlos.
>
> **El sistema viejo no se modificó.** Este archivo es el único que se escribió.

---

## 0. Tabla resumen

| # | Qué falta | Clasificación | Consecuencia |
|---:|---|---|---|
| 1 | El analista ya no puede fijar el valor de orientación de un parámetro (ni eligiéndolo de la tabla ni escribiéndolo) | **AUSENTE** | El límite que se imprime es el que deduce el motor; si el caso no encaja, no hay forma de corregirlo sin tocar la base |
| 2 | El **estado de servicio** no participa de la elección del cuadro, y el catálogo nuevo tiene 3 de los 5 estados del viejo | **PARCIAL** | Un aceite nuevo, uno antes de energizar y uno tratado se juzgan con el límite del aceite EN SERVICIO, que es más permisivo |
| 3 | De los 295 renglones de los cuadros de condiciones solo se migraron 25 cuadros, y salieron de OTRO archivo del viejo | **PARCIAL** | Todas las combinaciones que solo existían en la pantalla de condiciones quedan sin criterio y el informe imprime raya |
| 4 | **PCB se quedó sin cuadro de límites**, y su plantilla de diagnóstico dice siempre "NO se clasifica como contaminada" | **AUSENTE** | Un aceite contaminado con PCB puede salir informado como limpio |
| 5 | La banda de tensión ≥230–<345 kV desapareció | **PARCIAL** | Un equipo de 345 kV se juzga con el límite de ≥230 kV, que en agua y color es distinto |
| 6 | El límite que depende del **método** (MIDEL usado: ASTM D924 contra IEC 60247) no está cargado | **PARCIAL** | La estructura lo admite (`spec_limits.test_method_id`); el dato falta, y el éster sintético usado queda con un solo criterio |
| 7 | La lectura del archivo TXT del instrumento **no tiene interfaz ni catálogo de formatos** | **AUSENTE** | La cromatografía y los furanos vuelven a cargarse a mano: 9 y 5 valores por muestra |
| 8 | Borrar una fila de bancada no retira su resultado, ni su punto de control, ni devuelve la prueba a la cola | **AUSENTE** | El informe sigue imprimiendo un resultado que ya no tiene fila detrás |
| 9 | No se controla que la misma muestra se cargue dos veces en la misma prueba | **AUSENTE** | Dos filas producen dos resultados del mismo parámetro para la misma muestra, y el informe no sabe cuál es |
| 10 | La fila no registra quién la cargó, y el listado no filtra por analista | **PARCIAL** | En una hoja compartida por dos turnos no se puede responder quién midió cada muestra |
| 11 | El listado de hojas no se puede filtrar por Nº de muestra | **AUSENTE** | Para saber en qué hoja quedó una muestra hay que abrirlas de a una |
| 12 | El listado de hojas no se exporta | **AUSENTE** | El supervisor pierde la planilla con la que revisaba la corrida fuera del sistema |
| 13 | El panel "Pruebas con Valores Pendientes" del índice | **PARCIAL** | El filtro por estado se le acerca, pero no dice QUÉ celda falta ni en qué fila |
| 14 | Bloquear y desbloquear la hoja pasó de permiso propio a `role:super\|admin` | **PARCIAL** | Un supervisor de bancada que no sea administrador del workspace no puede bloquear una fecha |
| 15 | No hay pantalla para editar los cuadros de límites | **DECIDIDO** | [`../12-CHECKLIST.md`](../12-CHECKLIST.md) B2 |
| 16 | Los 31 mapeos de lectura de TXT no están migrados | **DECIDIDO** | [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md) H-3 |
| 17 | `is_locked` por columna se importa y no lo lee nadie | **DECIDIDO** | [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md) H-4 |
| 18 | Las cartas de control no traen desviación estándar: Westgard queda apagado | **DECIDIDO** | [`../12-CHECKLIST.md`](../12-CHECKLIST.md) B3 · [`E-cobertura-tablas.md`](E-cobertura-tablas.md) §"LO QUE FALTA" 3 |
| 19 | Un patrón fuera de control no avisa a nadie | **DECIDIDO** | [`../12-CHECKLIST.md`](../12-CHECKLIST.md) B4 |
| 20 | La bitácora diaria de condiciones del laboratorio y su precarga | **DECIDIDO** | [`../12-CHECKLIST.md`](../12-CHECKLIST.md) C1 · [`E-cobertura-tablas.md`](E-cobertura-tablas.md) §"LO QUE FALTA" 1 y 2 |
| 21 | El cambio de un valor constante no se audita, y su permiso se fusionó con el de la plantilla | **DECIDIDO** | [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md) H-1, H-5 y §12 |
| 22 | La unicidad (prueba, fecha) del viejo no se reproduce | **DECIDIDO** | Renuncia deliberada y escrita en `database/migrations/2026_07_28_100000_create_worksheets_tables.php:105-108` |
| 23 | Las 13 fórmulas del Grado de Polimerización | **DECIDIDO** | [`B-formulas.md`](B-formulas.md) H1 |
| 24 | Nadie avisa que falta un valor al armar el informe | **DECIDIDO** | [`E-cobertura-tablas.md`](E-cobertura-tablas.md) §"LO QUE FALTA" 8 |

**Recuento:** 6 **AUSENTE** · 8 **PARCIAL** · 10 **DECIDIDO**.

---

## BLOQUE 1 — Cuadros de condiciones

### Cómo funcionaba el viejo, en una pantalla

El valor de orientación de cada parámetro (`rem_report_details.{aci,f25,f90,f100,rig,rigep,ten,agu,col,con,den}_ori`
para fisicoquímicos, `cro..cro9_ori` para los nueve gases, `pcb_ori` para PCB) se
llenaba por **dos caminos independientes**:

1. **Automático**, al crear el detalle del informe:
   `RemReportDetail.set_orientation_fiqui_values` y
   `set_orientation_croma_values` (`labo_old/app/models/rem_report_detail.rb:181`
   y `:390`), un árbol de `if` sobre tipo de equipo, tipo de aceite y la tensión
   máxima (`rem_report_detail.rb:191-193`:
   `num_ten.split('/').map(&:to_f).max`).
2. **Manual**, desde el informe: cada parámetro tenía a su derecha un campo de
   texto editable **y** una lupa que abría la pantalla de condiciones en una
   ventana aparte, pasándole el aceite y el tipo de equipo:

```erb
<input class="form-control" data-parsley-required="true" required="required"
       name="rem_report[rem_report_details_attributes][0][aci_ori]"
       value="<%= @rem_report_detail.aci_ori %>" placeholder="Nº Ácido">
...
<a onclick="window.open('/conditions_management/fiquis/<%= rem_report_detail.id %>/edit
     ?transformer_oil_type_id=<%= @main_model.oil_type_id %>
     &transformer_type_id=<%= @main_model.transformer_type_id %>
     &aci_display=1', ...)"><i class="fa-solid fa-magnifying-glass"></i></a>
```
— `labo_old/app/views/im_management/rem_reports/partials/_form_add_details_physicals.html.erb:52,55`

La ventana dibujaba una tabla de candidatos filtrada por aceite y tipo de equipo,
con una fila por combinación, y al marcar el radio guardaba y cerraba sola:

```erb
<script>
  $('.radiogroup').on('change', function() { $('#save_value').val( this.value ); });
  $('input[type=radio]').on('change', function() { $(this).closest("form").submit(); });
</script>
```
— `labo_old/app/views/conditions_management/fiquis/partials/_all_conditions.html.erb:52-55`
(idéntico en `cromas/partials/_all_conditions.html.erb:44-47` y
`pcbs/partials/_all_conditions.html.erb:12-15`)

**Los 295 renglones**, contados sobre los `input type="radio"` de los 23
parciales: 182 en fisicoquímicos, 112 en cromatografía, 1 en PCB.

| Parcial | Renglones | Parcial | Renglones |
|---|---:|---|---:|
| `fiquis/_agu_conditions` | 28 | `cromas/_hid_conditions` | 15 |
| `fiquis/_rig_conditions` | 25 | `cromas/_met_conditions` | 15 |
| `fiquis/_aci_conditions` | 20 | `cromas/_mon_conditions` | 15 |
| `fiquis/_col_conditions` | 18 | `cromas/_dio_conditions` | 15 |
| `fiquis/_ten_conditions` | 16 | `cromas/_eti_conditions` | 15 |
| `fiquis/_con_conditions` | 15 | `cromas/_eta_conditions` | 15 |
| `fiquis/_f25_conditions` | 15 | `cromas/_ace_conditions` | 15 |
| `fiquis/_den_conditions` | 14 | `cromas/_nit_conditions` | 1 |
| `fiquis/_f100_conditions` | 13 | `cromas/_oxi_conditions` | 1 |
| `fiquis/_f90_conditions` | 12 | `pcbs/_pcb_conditions` | 1 |
| `fiquis/_rigep_conditions` | 11 | | |

Y **los ejes de la selección eran cinco**, no dos:

| Eje | Dónde | Ejemplo |
|---|---|---|
| Tipo de equipo | `params[:transformer_type_id]` | `== 10` conmutador (`fiquis/_rig_conditions.html.erb:14`) |
| Tipo de aceite | `params[:transformer_oil_type_id]` | `1` mineral, `4` silicona, `5`/`6` vegetal, `7` MIDEL (`:48,111,120,129`) |
| **Estado de servicio** | rótulo de la fila, elegido por el analista | NUEVO · ANTES DE ENERGIZAR · EN SERVICIO · TRATADO · NUEVO EN TRAFO |
| **Clase de tensión** | rótulo de la fila, elegido por el analista | ≤69 · >69–<230 · ≥230–<345 · ≥230 · ≤72.5 · 72.5–170 · ≥170 |
| **Método** | rótulo de la fila | ASTM 924 contra IEC60247 en MIDEL usado (`fiquis/_f25_conditions.html.erb:82,87`) |

---

### Hueco 1 · El analista ya no puede fijar el valor de orientación · **AUSENTE**

**Qué hace el viejo.** Los dos mecanismos de arriba: el campo de texto libre
(`_form_add_details_physicals.html.erb:52`) y el selector de la lupa
(`:55` → `conditions_management/fiquis/partials/_all_conditions.html.erb:3`,
que hace `form_for @main_model, method: :patch` contra
`ConditionsManagement::FiquisController#update`, `fiquis_controller.rb:30-40`).
Los dos escriben la misma columna `{param}_ori`
(`fiquis/partials/_rig_conditions.html.erb:190`).

**Qué hay en el nuevo.** Solo la deducción automática. La pantalla equivalente
—"Análisis de Resultado de Resultados"— imprime el límite como TEXTO:

```vue
<td>{{ row.limit ?? '—' }}</td>
```
— `/workspace/labo_new/resources/js/Components/Receptions/ReportAnalysisModal.vue:243`

y el valor sale congelado del resultado, resuelto por
`SpecEvaluator::verdictFor()`
(`/workspace/labo_new/app/Services/Lab/SpecEvaluator.php:105-111`) sobre el cuadro
que eligió `SpecSetResolver::resolve()`
(`/workspace/labo_new/app/Services/Lab/SpecSetResolver.php:75-112`). No existe
ninguna ruta que permita fijarlo a mano: se buscó `spec` en `routes/` y no hay
ninguna que escriba `SpecSet`, `SpecLimit` ni `results.spec_display`.

**Consecuencia.** Cuando la combinación real no encaja en los 25 cuadros —y el
resto de este bloque muestra que hay muchas—, el informe imprime raya y nadie
puede corregirlo desde la aplicación.

**Nota.** El campo de texto libre del viejo es la vía por la que se llenaron los
`{param}_ori` de las combinaciones que el automático nunca cubría. No se propone
reponerlo tal cual: sin catálogo detrás es el mismo camino por el que la base
vieja terminó con variantes tipeadas del mismo criterio. Lo que hace falta es la
elección **desde una lista**, que es lo que hacía la lupa.

---

### Hueco 2 · El estado de servicio no elige cuadro, y faltan dos de los cinco estados · **PARCIAL**

**Qué hace el viejo.** La rigidez dieléctrica del aceite mineral tiene **diez**
renglones, y la diferencia entre ellos es el estado de servicio, no la tensión:

```
MINERAL NUEVO                          35.0 - mínimo
MINERAL ANTES DE ENERGIZAR ≤69 KV      45.0 - mínimo
MINERAL ANTES DE ENERGIZAR >69-<230KV  55.0 - mínimo
MINERAL ANTES DE ENERGIZAR ≥230-<345KV 60.0 - mínimo
MINERAL EN SERVICIO ≤69 KV             40.0 - mínimo
MINERAL EN SERVICIO >69-<230KV         47.0 - mínimo
MINERAL EN SERVICIO ≥230               50.0 - mínimo
MINERAL TRATADO ≤69 KV                 45.0 - mínimo
MINERAL TRATADO >69-<230KV             55.0 - mínimo
MINERAL TRATADO ≥230                   60.0 - mínimo
```
— `labo_old/app/views/conditions_management/fiquis/partials/_rig_conditions.html.erb:49-107`

La asignación automática **solo produce la rama EN SERVICIO** (40 / 47 / 50):
`labo_old/app/models/rem_report_detail.rb:235-275`. Los otros seis renglones solo
se alcanzaban por la lupa. En silicona hay un tercer estado, "NUEVO EN TRAFO"
(`fiquis/_aci_conditions.html.erb:82`, `_agu_conditions.html.erb:118`).

**Qué hay en el nuevo.** El eje existe en las dos tablas y no lo usa nadie:

- La columna está: `spec_sets.service_state`
  (`/workspace/labo_new/database/migrations/2026_07_28_140000_create_specs_tables.php:176`)
  y `equipment.service_state`
  (`.../2026_07_28_061051_create_equipment_table.php:79`).
- El resolvedor la aplica: `SpecSetResolver::applies()`
  (`/workspace/labo_new/app/Services/Lab/SpecSetResolver.php:133`).
- Pero **ningún cuadro la declara**: el sembrador nunca la escribe
  (`/workspace/labo_new/database/seeders/LabSpecSetsSeeder.php:158-172` —
  escribe `oil_type_id`, `equipment_type_id`, `voltage_from`, `voltage_to`, y
  nada más), porque el archivo de origen tampoco la trae
  (`database/seeders/data/spec_limits_legacy.json`: ninguna de las 25
  `condicion` tiene la clave).
- Y el catálogo de estados es más corto que el del viejo:
  `public const SERVICE_STATES = ['new', 'in_service', 'out_of_service'];`
  — `/workspace/labo_new/app/Models/Equipment.php:54`.
  No hay "antes de energizar", ni "tratado", ni "nuevo en trafo".

**Consecuencia.** Un aceite recién tratado en un equipo de 220 kV se juzga con el
mínimo de 47 kV (en servicio) en vez de 55 kV (tratado): pasa como conforme un
aceite que la norma da por no aceptado.

---

### Hueco 3 · Los 295 renglones no se migraron; los 25 cuadros salieron de otro archivo · **PARCIAL**

**Qué hace el viejo.** Los 295 renglones de `conditions_management` son un
catálogo de criterios más ancho que el árbol de `if` del modelo. Los dos
convivían y no coincidían.

**Qué hay en el nuevo.** 25 cuadros, y su procedencia lo dice sin rodeos:

```json
"extraido_de": "labo_old app/models/rem_report_detail.rb (métodos set_orientation_*_values)",
"metodo": "extracción programática de los bloques RemReportDetail.update()"
```
— `/workspace/labo_new/database/seeders/data/spec_limits_legacy.json` → `_meta`

Es decir: **se migró el camino automático y no el catálogo de la pantalla**. Los
25 cuadros se reparten en 16 de cromatografía y 9 de fisicoquímicos, con estas
condiciones y ninguna más (aceite, tipo de equipo, tensión):

```
cromas  Conmutador · Reactor·Mineral · Reactor·otros · Distribución·Mineral ·
        Potencia·Mineral · Horno·Mineral · De corriente·Mineral ·
        De voltaje·Mineral · Instrumento·Mineral · Bushing·Mineral ·
        Cables·Mineral · Interruptor·Mineral · Silicona · Midel ·
        Éster vegetal · Vegetal girasol
fiquis  Conmutador ≤69 · Conmutador >69 · Mineral ≤69 · Mineral 69-230 ·
        Mineral ≥230 · Silicona · Éster ≤72.5 · Éster 72.5-170 · Éster ≥170
```

**Lo que solo existía en la pantalla y no tiene destino:** los estados de
servicio (hueco 2), la banda ≥230–<345 kV (hueco 5), el criterio por método
(hueco 6), el cuadro de PCB (hueco 4), y los renglones de conmutador
"NUEVO ≤69 KV" / "NUEVO >69 KV" / "EN SERVICIO NEUTRAL"
(`fiquis/_agu_conditions.html.erb:16,22,28`), que en el nuevo colapsan en dos
cuadros por tensión.

**Consecuencia.** Toda combinación fuera de esas 25 devuelve `null` en
`SpecSetResolver` y el informe imprime raya. Está bien que diga raya y no
"cumple" —es la decisión escrita en
`/workspace/labo_new/app/Services/Lab/SpecSetResolver.php:40-50`—, pero el
laboratorio tenía el criterio y ahora no lo tiene.

---

### Hueco 4 · PCB se quedó sin cuadro, y el párrafo dice siempre "no contaminada" · **AUSENTE**

**Qué hace el viejo.** El valor de orientación de PCB **solo** venía de la
pantalla de condiciones. El método automático está entero comentado:

```ruby
# Set Values for PCB
def set_orientation_pcb_values
  # @transformer_type_id = self.rem_report.transformer_type_id
  ...
```
— `labo_old/app/models/rem_report_detail.rb:685-700`

y el único renglón de la pantalla es el que el analista marcaba:

```erb
<input type="radio" name="radiogroup" class="radiogroup" value="Libre de PCB <2" />
...
<input type="hidden" name="rem_report_detail[pcb_ori]" id="save_value" />
```
— `labo_old/app/views/conditions_management/pcbs/partials/_pcb_conditions.html.erb:13,22`

**Qué hay en el nuevo.** Ningún cuadro de PCB. Los 25 cuadros son solo de dos
grupos —`{'cromas', 'fiquis'}`— y no hay ningún otro sembrador que cargue uno
(se buscó `pcb` en `database/seeders/`: solo aparece en el sembrador de la
demostración, en `analyte_map.json` y en las plantillas de texto).

Sin cuadro, `SpecEvaluator::verdictFor()` devuelve el arreglo vacío
(`/workspace/labo_new/app/Services/Lab/SpecEvaluator.php:69-71`) y **todos** los
resultados de PCB quedan con `spec_status = null`. Y de ahí:

```php
return $resultados->where('spec_status', Result::SPEC_OUT);
```
— `/workspace/labo_new/app/Services/Lab/DiagnosisTextService.php:292`

devuelve siempre cero, o sea que la familia entra siempre por el caso `none` de
la plantilla:

```json
{"family": "pcb", "case": "none",
 "body": "• El contenido de PCB se encuentra por debajo del límite establecido. La muestra NO se clasifica como contaminada."}
```
— `/workspace/labo_new/database/seeders/data/diagnosis_templates.json`

**Consecuencia.** Es la más grave de este documento: un aceite contaminado con
PCB sale del sistema con un párrafo firmado que afirma lo contrario, y la
afirmación tiene efectos regulatorios sobre el manejo y la disposición del
aceite. Los casos `one` y `many` de la plantilla existen y **no se pueden
disparar** mientras PCB no tenga cuadro.

---

### Hueco 5 · La banda ≥230–<345 kV desapareció · **PARCIAL**

**Qué hace el viejo.** Cuatro parámetros distinguen una cuarta banda de tensión
por encima de 230 kV:

```
MINERAL ANTES DE ENERGIZAR ≥230-<345KV   agua 10.0 - máximo   (_agu_conditions.html.erb:67)
MINERAL ANTES DE ENERGIZAR ≥230-<345KV   rigidez 60.0 - mín.  (_rig_conditions.html.erb:69)
MINERAL ANTES DE ENERGIZAR ≥230-<345KV   FP 90º               (_f90_conditions.html.erb:45)
MINERAL ANTES DE ENERGIZAR ≥230-<345KV   FP 100º              (_f100_conditions.html.erb:45)
MINERAL ANTES DE ENERGIZAR ≥230-<345KV   color                (_col_conditions.html.erb:55)
```

**Qué hay en el nuevo.** Tres bandas de mineral y ninguna más:
`Mineral · ≤69 kV`, `Mineral · 69-230 kV`, `Mineral · ≥230 kV`
(`spec_limits_legacy.json` → `cuadros`), sembradas con `voltage_from` /
`voltage_to` (`LabSpecSetsSeeder.php:166-167`). El corte superior es abierto.

**Consecuencia.** Un equipo de 345 kV o más recibe el criterio de ≥230 kV. En la
rama que el sistema nuevo sí tiene (EN SERVICIO) las dos coinciden, así que hoy
la pérdida no se manifiesta; se manifiesta en cuanto se reponga el estado
"antes de energizar" del hueco 2, donde ≥230 y ≥230–<345 tienen valores
distintos de agua y color.

---

### Hueco 6 · El límite por método no está cargado · **PARCIAL**

**Qué hace el viejo.** El factor de potencia a 25 °C del MIDEL usado tiene dos
renglones, uno por norma de ensayo:

```erb
<td>ASTM 924 - MIDEL USADO</td>
...
<td>IEC60247 - MIDEL USADO</td>
```
— `labo_old/app/views/conditions_management/fiquis/partials/_f25_conditions.html.erb:82,87`

**Qué hay en el nuevo.** La estructura está y es mejor que la del viejo: el
límite se indexa por método, con índice único
`(spec_set_id, analyte_id, test_method_id)`
(`/workspace/labo_new/database/migrations/2026_07_28_140000_create_specs_tables.php:221,249`),
y el sembrador ya sabe resolverlo
(`/workspace/labo_new/database/seeders/LabSpecSetsSeeder.php:220-232`).

Lo que falta es el **dato**: los cuadros de `spec_limits_legacy.json` salieron del
modelo, que no distingue métodos, así que ningún límite trae `test_method`.

**Consecuencia.** El éster sintético usado se juzga con un solo criterio de
factor de potencia, sin registrar con cuál de las dos normas se midió. Es el
mismo problema de fondo que el **RIG-GAP** ya anotado en
`spec_limits_legacy.json` → `pendientes`, pero en un parámetro distinto.

---

## BLOQUE 2 — Bancada

### Hueco 7 · La lectura del archivo del instrumento no tiene interfaz ni catálogo de formatos · **AUSENTE**

**Qué hace el viejo.** Un flujo de tres pasos, accesible desde el propio
formulario de carga:

1. El botón aparece si la prueba tiene alguna columna importable:
   ```erb
   <% @display_import_button = @lab_category_sub_details.where("is_imported = 1 AND lab_category_detail_id = ?", params[:lab_category_detail_id]) %>
   <% if @display_import_button.size > 0 %>
     <a href="/pr_management/templates/imports/new?lab_category_detail_id=...&lab_id=...">
       <i class="fa-solid fa-upload"></i> Lectura de Archivo TXT</a>
   ```
   — `labo_old/app/views/pr_management/templates/lab_details/partials/_form_new.html.erb:41-49`
2. Se sube el archivo (`imports/partials/_form_new.html.erb:7`), se lee entero a
   una columna de texto (`labo_old/app/models/lab_file.rb:19-25`) y se muestra
   "Parámetros Encontrados" para confirmar
   (`imports/partials/_form_show.html.erb:6-32`).
3. Al confirmar, se vuelve al formulario de carga con `?lab_file_id=`, y cada
   celda se precarga con lo interpretado
   (`imports_controller.rb:56` → `lab_details/partials/_form_new_nested.erb:13-24`).

**Qué hay en el nuevo.** El motor está y es mucho mejor —
`/workspace/labo_new/app/Services/Lab/InstrumentFileParser.php:63-107` documenta y
corrige los cinco defectos del parser viejo — y el punto de entrada existe:

```php
Route::post('worksheets/{worksheet}/instrument_file', [InstrumentFileController::class, 'store'])
```
— `/workspace/labo_new/routes/lab_management.php:259`

Pero **no se puede llegar a él**:

- Ningún archivo de `resources/js/` menciona `instrument_file`,
  `InstrumentFormat` ni `instrument_formats` (búsqueda en todo el árbol: cero
  coincidencias). La grilla de bancada no tiene botón de importar.
- El endpoint exige `instrument_format_id` existente y activo
  (`/workspace/labo_new/app/Http/Controllers/LabManagement/InstrumentFileController.php:56-60`),
  y la tabla `instrument_formats`
  (`/workspace/labo_new/database/migrations/2026_07_28_100000_create_worksheets_tables.php:269-292`)
  **no tiene sembrador ni CRUD**: `InstrumentFormat` solo se nombra en su propio
  modelo y en ese controlador.
- El propio parser promete la pantalla que falta: *"`column_map` es un JSON
  editable desde la pantalla de formatos"* (`InstrumentFileParser.php:40`).

**Consecuencia.** La función está construida y es inalcanzable de punta a punta.
Se distingue de [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md) H-3,
que audita la falta del **dato** (los 31 mapeos): acá falta además el **camino**
—la pantalla de formatos y el botón en la hoja—, así que sembrar los 31 mapeos no
alcanzaría para que el analista pudiera usarlos.

---

### Hueco 8 · Borrar una fila no retira nada de lo que produjo · **AUSENTE**

**Qué hace el viejo.** Al borrar (lógicamente) una fila, un callback devolvía la
prueba pedida a la cola y le soltaba el enlace:

```ruby
@rem_job = RemJob.where("deleted=0 AND lab_category_detail_id = ... AND rem_correlative_id IN (...)")
if @rem_job.present?
  @query = RemJob.where("id = ?", @rem_job.first.id).update_all(task_done: 0, lab_detail_id: nil)
end
```
— `labo_old/app/models/lab_detail.rb:102-115` (rama `else`, la del borrado)

y las celdas de esa fila quedaban marcadas borradas
(`lab_detail.rb:77-82`).

**Qué hay en el nuevo.** El borrado no hace ninguna de las tres cosas:

```php
public function destroyRow(Worksheet $worksheet, WorksheetRow $row): RedirectResponse
{
    abort_unless($row->worksheet_id === $worksheet->id, 404);
    if (! $worksheet->isEditable()) { ... }
    $row->delete();
    return back()->with('success', __('worksheets.row_deleted'));
}
```
— `/workspace/labo_new/app/Http/Controllers/LabManagement/WorksheetController.php:518-529`

- **El resultado sobrevive.** `results.worksheet_row_id` está declarado
  `cascadeOnDelete`
  (`/workspace/labo_new/database/migrations/2026_07_28_120000_create_results_table.php:98-99`),
  pero `WorksheetRow` usa `SoftDeletes`
  (`/workspace/labo_new/app/Models/WorksheetRow.php:27` y el `softDeletes()` de
  la migración): `$row->delete()` es un `UPDATE`, así que la cascada nunca se
  dispara. Y `ResultMaterializer::forWorksheet()` solo se llama desde
  `saveRow()` (`/workspace/labo_new/app/Services/Lab/WorksheetService.php:161`),
  nunca desde el borrado.
- **El punto de control sobrevive.** `qc_points.worksheet_row_id` es
  `nullOnDelete` (`.../2026_07_28_110000_create_qc_tables.php:136`) y, por la
  misma razón, tampoco se toca.
- **La prueba pedida no vuelve a la cola.** `SampleProgressService` se invoca
  desde `saveRow`, `validate` y `void`
  (`WorksheetService.php:116,162,271,327`) y desde ningún borrado de fila.
- No hay evento de modelo (`WorksheetRow` no declara `booted()` ni `deleting`) ni
  ninguna prueba automatizada que lo cubra (`destroyRow` y `rows.destroy` no
  aparecen en `tests/`).

**Consecuencia.** Es exactamente lo que `ResultMaterializer` declara que no puede
pasar: *"`results` es DERIVADA de `worksheet_values` y se tiene que poder
reconstruir entera desde allá. Un resultado que no se puede reconstruir es un
dato inventado en un papel firmado"*
(`/workspace/labo_new/app/Services/Lab/ResultMaterializer.php:76-80`). Hoy, borrar
una fila mal cargada deja su resultado publicado, su punto en la carta de control
y la muestra marcada como "en proceso" para siempre.

---

### Hueco 9 · La misma muestra se puede cargar dos veces · **AUSENTE**

**Qué hace el viejo.** Dos controles, uno detrás del otro:

1. Una **pantalla previa** que preguntaba el número antes de dejar crear la fila:
   ```erb
   <% if @list_array.size > 0 && params[:search_num].present? %>
     <h2 class="text-danger text-center">Ya existe el Nº de Muestra</h2>
   <% else %>
     <a href=".../lab_details/new">Usar el Nº de Muestra</a>
   ```
   — `labo_old/app/views/pr_management/templates/lab_details/index.html.erb:25-33`,
   alimentada por `lab_details_controller.rb:24-34`.
2. Una **validación de modelo**, en alta y en edición:
   ```ruby
   def duplicated_values_custom_condition_on_create
     if self.lab_detail_type_id == 2
       @find = LabDetail.joins(:lab).where("... lab_detail_type_id = 2 AND lab_details.num_test = ? AND labs.lab_category_detail_id = ?", self.num_test, self.lab.lab_category_detail_id)
       if @find.size > 0 then errors.add(:num_test, "Ya existe") end
   ```
   — `labo_old/app/models/lab_detail.rb:26-42`; el mensaje al usuario está en
   `lab_details_controller.rb:92`: *"El Nº de Muestra ya existe (Tipo Muestra)"*.
   El alcance es la PRUEBA entera, no la hoja: la misma muestra no se podía
   cargar dos veces ni en dos fechas distintas.

**Qué hay en el nuevo.** Nada equivalente:

- No hay índice único: `worksheet_rows.sample_test_id` es solo `->index()`
  (`/workspace/labo_new/database/migrations/2026_07_28_100000_create_worksheets_tables.php:144`).
- `WorksheetService::inheritFromSampleTest()` verifica que la prueba pedida
  exista y que sea de la misma definición que la hoja
  (`/workspace/labo_new/app/Services/Lab/WorksheetService.php:430-449`), y **no**
  verifica que no esté ya cargada en otra fila.
- El selector la sigue ofreciendo: `pendingTests()` filtra por estado
  `pending` o `in_progress`
  (`/workspace/labo_new/app/Http/Controllers/LabManagement/WorksheetController.php:338-343`),
  y una muestra recién cargada queda justamente en `in_progress`
  (`WorksheetService.php:116`).
- El índice único de `results` es
  `(worksheet_row_id, analyte_id, replicate_no)`
  (`.../2026_07_28_120000_create_results_table.php:162`), o sea **por fila**: dos
  filas distintas producen dos resultados del mismo parámetro para la misma
  muestra.

**Consecuencia.** El informe de esa muestra recibe dos valores del mismo
parámetro sin ninguna regla que diga cuál gana.

---

### Hueco 10 · La fila no dice quién la cargó, y el listado no filtra por analista · **PARCIAL**

**Qué hace el viejo.** `lab_details.user_id` guarda el autor de CADA fila
(`labo_old/db/schema.rb:126`, escrito desde
`lab_details/partials/_form_new.html.erb:2`), se muestra como columna en las tres
tablas de la bancada (`labs/partials/_table.html.erb:34,81`,
`lab_details/partials/_table.html.erb:13,49`,
`labs/partials/_display_missing_values.html.erb:9`), viaja en la exportación
(`labs/partials/_xls_records.erb:16,52`) y es un **filtro del listado**:

```erb
<%= select_tag "search_user_id", "<option value=\"Todos\">Todos</option>" + options_for_select(@users.map{ |e| [e.name, e.id] }, ...) %>
```
— `labo_old/app/views/pr_management/templates/labs/partials/_search_filters.html.erb:37`,
aplicado en `labs_controller.rb:45`
(`@query.lab_details_user_id_eq = @search_user_id if @search_user_id.to_i > 0`)
y usado además para acotar las filas que se despliegan
(`labs/partials/_table.html.erb:17-21`).

**Qué hay en el nuevo.** El dato existe una capa más abajo y no se ve:

- `worksheet_rows` no tiene columna de autor: el `$fillable` es
  `worksheet_id, kind, sample_code, sample_id, sample_test_id, equipment_id,
  position, instrument_id, instrument_file_id, notes, legacy_id`
  (`/workspace/labo_new/app/Models/WorksheetRow.php:51-55`).
- `worksheet_values.entered_by` sí se escribe
  (`/workspace/labo_new/app/Services/Lab/WorksheetService.php:556`) y **no se
  dibuja en ninguna parte**: `entered_by` no aparece en
  `resources/js/Components/Worksheets/WorksheetGrid.vue`.
- El listado de hojas filtra por prueba, estado y rango de fechas, y por nada más
  (`/workspace/labo_new/app/Http/Controllers/LabManagement/WorksheetController.php:68-81`;
  `resources/js/Pages/Worksheets/Index.vue:59-101`). La hoja tiene un único
  `analyst_id` de cabecera.

**Consecuencia.** Una hoja de una fecha con dos turnos tiene un solo analista
declarado. Para un laboratorio con alcance ISO/IEC 17025 la pregunta "quién midió
esta muestra" se responde hoy leyendo el registro de auditoría, no la hoja.

---

### Hueco 11 · No se puede filtrar el listado por Nº de muestra · **AUSENTE**

**Qué hace el viejo.** El filtro principal del listado es el número de muestra, y
se ofrece como desplegable con los números realmente cargados en esa prueba:

```ruby
@num_tests = LabDetail.select("DISTINCT (num_test) as num_test")
  .where("deleted=0 AND lab_id IN (select id from labs where deleted=0 AND lab_category_detail_id = #{@search_lab_category_detail_id})")
```
— `labo_old/app/controllers/pr_management/templates/labs_controller.rb:35`,
dibujado en `labs/partials/_search_filters.html.erb:5` y aplicado en `:41`
(`@query.lab_details_num_test_eq`).

Además, al filtrar, la fila de esa hoja se despliega sola y la celda coincidente
se pinta:

```erb
<td class="<% if lab_sub_detail.name == params[:search_num_test] %>bg-info<% end %>">
```
— `labs/partials/_table.html.erb:58`, con la apertura automática en `:25`.

**Qué hay en el nuevo.** El controlador acepta `test_definition`, `status`,
`from`, `to`, `sort`, `direction` y `per_page`
(`/workspace/labo_new/app/Http/Controllers/LabManagement/WorksheetController.php:98`)
y ninguno más. `worksheet_rows.sample_code` está indexado
(`.../create_worksheets_tables.php:138`) y no lo consulta ninguna pantalla.

**Consecuencia.** Para responder "¿en qué hoja quedó la muestra 2026-0744?" hay
que abrir las hojas de esa prueba de a una. Es la consulta más frecuente del
supervisor de bancada.

---

### Hueco 12 · El listado no se exporta · **AUSENTE**

**Qué hace el viejo.** El índice ofrece "Exportar Registros", que respeta los
filtros puestos:

```ruby
format.xls {
  send_data render_to_string(:partial => "pr_management/templates/labs/partials/xls_records"),
  :filename => "Reporte_de_#{@lab_category_detail.name}_#{Time.now.strftime("%d_%m_%Y")}.xls"
}
```
— `labo_old/app/controllers/pr_management/templates/labs_controller.rb:59-63`,
botón en `labs/partials/_search_results_options.html.erb:8-17`.

La planilla trae fecha, tipo de fila, **todas** las columnas de la prueba
resueltas (texto, opción y fecha) y el autor
(`labs/partials/_xls_records.erb:11-53`).

**Qué hay en el nuevo.** Nada: `WorksheetController` solo devuelve páginas
Inertia y `Index.vue` no tiene ningún botón de exportación.

**Nota honesta.** El archivo del viejo es HTML con extensión `.xls` —está
anotado en [`../12-CHECKLIST.md`](../12-CHECKLIST.md) "Lo que NO hay que portar"
como formato—, pero **la capacidad** de bajar la corrida no está en ninguna
lista: lo descartado es el formato, no la función.

**Consecuencia.** El supervisor pierde la planilla con la que revisaba la corrida
del mes fuera del sistema.

---

### Hueco 13 · El panel "Pruebas con Valores Pendientes" · **PARCIAL**

**Qué hace el viejo.** El índice muestra, arriba de todo y en amarillo, las filas
de esa prueba con alguna celda vacía o con el texto `NaN`:

```ruby
@missing_data = LabDetail.distinct(:num_test).joins(:lab_sub_details)
  .where("... AND ( TRIM(lab_sub_details.name) = '' OR lab_sub_details.name = 'NaN' )")
```
— `labo_old/app/controllers/pr_management/templates/labs_controller.rb:55`

y la tarjeta solo aparece si hay algo que mostrar
(`labs/index.html.erb:19-35`), con la fila completa y los botones de editar y
borrar (`labs/partials/_display_missing_values.html.erb:39-47`). Del otro lado, el
`NaN` bloqueaba armar el informe
(`im_management/rem_reports/partials/_form_add_details_physicals.html.erb:43-46`:
`@aci_block_save = "bloquear"`).

**Qué hay en el nuevo.** El equivalente parcial es el estado de la hoja: una hoja
con obligatorios vacíos no publica y se queda en `draft`
(`/workspace/labo_new/app/Services/Lab/WorksheetService.php:147-163`), y el
listado se puede filtrar por estado. `missingRequiredValues()` sabe exactamente
qué fila y qué columna faltan (`:638-673`) pero es **privado** y su salida no
llega a ninguna pantalla: `show()` solo manda `missing`, que son los
prerrequisitos de patrón y duplicado, no las celdas
(`/workspace/labo_new/app/Http/Controllers/LabManagement/WorksheetController.php:313`).

**Consecuencia.** Se sabe que la hoja está incompleta y no qué le falta. El `NaN`
en sí ya no puede ocurrir —las fórmulas escriben `null`, no texto
(`WorksheetService.php:198-214`)—, así que esa mitad del problema está resuelta
por diseño.

---

### Hueco 14 · Bloquear la hoja pasó de permiso propio a rol · **PARCIAL**

**Qué hace el viejo.** El bloqueo tiene **acceso propio (30)**, distinto del de
editar (36), y el comentario del código pide no cambiarlo:

```erb
<% if @user_permission.include?(30) %> <!-- No cambiar Permiso Validar-->
  <a href="<%= @main_url %>/<%= array.id %>/validate" ...><i class="fa fa-lock"></i></a>
```
— `labo_old/app/views/pr_management/templates/labs/partials/_table.html.erb:99-104`

El aviso de la pantalla dice para qué sirve: *"Cuando se usa el Estado Bloqueado
no se puede editar o eliminar los registros de la Fecha Principal, sólo puede ser
cambiado por el Supervisor"*
(`labs/partials/_form_validate.html.erb:5-11`). Y el bloqueo automático por
antigüedad estaba **comentado**:
`<%# @auto_block = Lab.where("date_rehearsal < NOW() - INTERVAL 3 DAY").update_all(state: 0) %>`
(`labs/partials/_table.html.erb:136`).

**Qué hay en el nuevo.** El candado es de administrador del workspace:

```php
Route::middleware('role:super|admin')->group(function () {
    Route::post('worksheets/{worksheet}/lock',   ...)->name('worksheets.lock');
    Route::post('worksheets/{worksheet}/unlock', ...)->name('worksheets.unlock');
});
```
— `/workspace/labo_new/routes/lab_management.php:238-241`

**Consecuencia.** El supervisor de bancada, que en el viejo tenía el acceso 30
sin ser administrador, hoy o recibe el rol `admin` completo —con todo lo que eso
abre— o no puede bloquear ni desbloquear una fecha. Se relaciona con el pendiente
ya anotado en [`../12-CHECKLIST.md`](../12-CHECKLIST.md) "Lo que hay que limpiar"
(*el permiso `worksheets.validate` está sembrado y ninguna ruta lo usa*): ese
permiso huérfano es justamente el que corresponde a esta acción.

**Lo que sí mejoró y conviene no perder de vista:** el bloqueo por antigüedad
está vivo y es configurable
(`/workspace/labo_new/app/Services/Lab/WorksheetService.php:358-379`,
ajuste `worksheets.auto_lock_months`), donde el viejo lo tenía comentado; y el
candado se verifica en el servidor (`Worksheet::isEditable()`,
`/workspace/labo_new/app/Models/Worksheet.php:236-245`) donde el viejo solo
escondía botones.

---

## Anexo — Lo verificado como CORRECTO en este alcance

Para que la lista de huecos no dé una impresión equivocada, esto se revisó y está
resuelto, en varios casos mejor que en el viejo:

- **La regla de patrón y duplicado vive en el servidor y está encendida.**
  `Worksheet::missingPrerequisites()`
  (`/workspace/labo_new/app/Models/Worksheet.php:280-301`) más
  `WorksheetService::assertKindAllowed()` (`:478-503`), con el dato sembrado para
  las 29 pruebas por `LabTestQcPolicySeeder` (`:59-71`). En el viejo la regla
  estaba en el HTML del selector
  (`lab_details/partials/_form_new.html.erb:28-37`), **y terminó desactivada**:
  el botón quedó envuelto en `display: none` con el comentario
  `<!-- SE HA COMENTADO PARA VALIDAR MUESTRAS -->`
  (`labs/partials/_form_show.html.erb:45-54`). Esto cierra en la práctica el
  hueco H-6 de [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md).
- **El duplicado por fin se compara.** El viejo obligaba a cargarlo y no lo
  comparaba nunca; el nuevo lo aparea con su original y guarda la diferencia
  (`WorksheetService::materializeDuplicates()`, `:799-843`).
- **Las reglas de Westgard no existían en el viejo.** Están construidas
  (`app/Services/Lab/WestgardEvaluator.php`) y esperando la desviación estándar
  (pendiente B3 del checklist).
- **Los puntos de la carta se congelan contra los límites vigentes ese día**
  (`QcChart::scopeVigenteAl()`, `/workspace/labo_new/app/Models/QcChart.php:165-178`),
  donde el viejo pisaba los cinco límites en la misma fila
  (`patron_tendences`, editada por `patron_tendences_controller.rb:20`) y
  redibujaba la historia contra el criterio de hoy.
- **La carta tiene clave foránea a su prueba.** El viejo hacía
  `PatronTendence.find(params[:lab_category_detail_id])`
  (`tendences/partials/_single_content.html.erb:4`), es decir, confiaba en que el
  id de la tabla de límites coincidiera con el id de la prueba.
- **La fila se ata a la prueba pedida por clave foránea**, no partiendo el texto
  del código e interpolándolo en SQL
  (`lab_detail.rb:87` contra `WorksheetService::inheritFromSampleTest()`,
  `:418-457`). Es el punto A6 del checklist, ya cerrado.
- **El cálculo corre en el servidor.** El viejo guardaba JavaScript en una columna
  de la base y lo inyectaba con `html_safe`
  (`lab_details/partials/_calculation_script.html.erb:3-5`), con el campo destino
  en `readonly` —salteable con un envío directo—; el nuevo calcula en
  `FormulaResolver` y descarta lo que venga del formulario para un campo con
  fórmula (`WorksheetService::writeValues()`, `:516-519`).
- **Los valores constantes tienen su pantalla y además un acceso desde la hoja.**
  `WorksheetConstantsModal.vue` se abre desde `Worksheets/Show.vue:23,257`, lo
  que cierra el punto §12.3 de
  [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md) (los otros tres
  puntos de esa sección siguen abiertos).
- **La celda dejó de guardar todo en un `varchar`.** El viejo metía número,
  fecha, observación, el id de la opción elegida y hasta N réplicas concatenadas
  con `/` en `lab_sub_details.name`
  (`labo_old/app/models/lab_sub_detail.rb:16-17`,
  `lab_details/partials/_form_new_nested_poli.html.erb:10-30`); el nuevo separa
  `value_num` / `value_text` / `option_id` / `replicate_no`.

---

## Procedencia de este documento

- Sistema viejo: `/home/user/labo_old`, revisado en solo lectura. **No se
  modificó ningún archivo.**
- Sistema nuevo: `/workspace/labo_new`, en el estado de la fecha de escritura.
- Recuentos: `grep -c 'name="radiogroup"'` sobre los 23 parciales de
  `app/views/conditions_management/**` (295 renglones); lectura del JSON
  `database/seeders/data/spec_limits_legacy.json` (25 cuadros) y
  `qc_charts.json` (16 cartas).
- Documentos previos consultados para no repetir: `../12-CHECKLIST.md`,
  `A-columnas-y-constantes.md`, `B-formulas.md`, `D-placa-equipos.md`,
  `E-cobertura-tablas.md`, `M-campos-obligatorios.md`. El archivo
  `docs/origen-ruby/AUDITORIA-PRUEBAS-DE-MUESTRAS.md` también se revisó.
