# Modelo de datos propuesto (TR LAB sobre Laravel)

> Reemplaza la tabla de ~250 columnas y las normas clavadas por un esquema
> normalizado. Cada tabla dice de qué tabla del sistema viejo viene.
>
> Regla de oro, la misma que rige TRAFODEX: **el código solo tiene fórmulas y
> flujo; todo lo que puede cambiar (normas, límites, parámetros, métodos,
> plantillas, textos) vive en datos.**

---

## 0. Convenciones heredadas del core

Todas las tablas de negocio llevan, sin excepción:

```
id, slug (22 chars, único, es el route key)
tenant_id (nullable → null = global/de fábrica)
created_by, deleted_by, deleted_description
timestamps + softDeletes
locked_at, locked_by  (donde aplique el trait Lockable)
```

Nunca más `deleted integer`. Nunca más `id` en la URL.

---

## 1. Maestros del laboratorio

### 1.1 Clientes y ubicaciones

`customers` — el laboratorio pasa a tener los suyos. En el laboratorio el
cliente es quien contrata el servicio y factura; puede no ser el dueño del
equipo (una contratista que muestrea para una distribuidora).

> **Ojo, no es un rediseño: es una repatriación.** Hoy el laboratorio **no
> tiene** tabla `customers`. `Customer < Primary2` lee y escribe la tabla de la
> otra base (ver `01` §4). Lo mismo pasa con `countries`, `oil_types`, `marks` y
> `conmutation_types`. Esas cinco tablas hay que **crearlas y migrarles los
> datos** desde `tr_app_development`, no solo remodelarlas. Es trabajo extra de
> la fase 1 y de la fase 12 que no estaba contemplado.

```
customers          id, slug, tenant_id, code, name, tax_id, country_id,
                   address, email, phone, is_active, external_ref
customer_contacts  customer_id, name, email, phone, role, receives_reports
customer_sites     customer_id, name, code            (sede / central)
customer_areas     customer_site_id, name             (subestación / área)
```

`external_ref` = slug del cliente equivalente en TRAFODEX. Nullable: hay
clientes de laboratorio que no existen allá.

### 1.2 Equipos

`equipment` — **no** se llama `transformers`. El laboratorio recibe muestras de
transformadores, conmutadores, reactores, bushings, cables, interruptores,
electrobombas e intercambiadores (los 20 tipos de `transformer_types` del
sistema viejo). Llamarlo "transformador" es lo que llevó al `if tipo == 10`.

```
equipment   id, slug, tenant_id
            customer_id, customer_site_id, customer_area_id
            serial, tag, name
            equipment_type_id        → equipment_types (catálogo)
            oil_type_id              → oil_types
            brand_id, oil_brand_id
            voltage_kv_hv, voltage_kv_lv, voltage_class   ← numérico, no "220/60/10"
            power_mva, phases, manufacture_year
            oil_volume, oil_volume_unit_id
            tap_changer_type_id, preservation_id
            external_ref             ← slug del transformer en TRAFODEX
            is_active
```

> **Cambio de fondo**: hoy `rem_reports.num_ten` es un `string` `"220/60/10"`
> que el código parsea con `split('/').map(&:to_f).max` en cinco lugares
> distintos. En el modelo nuevo la tensión es numérica y la banda de tensión se
> resuelve una sola vez, en el servicio de límites.

### 1.3 Catálogos

Uno por tabla, todos generados con `php artisan make:module` (clon de `Brand`):

`equipment_types`, `oil_types`, `brands`, `oil_brands`, `oil_volume_units`,
`tap_changer_types`, `preservations`, `sampling_points`, `samplers`,
`countries`, `containers`, `report_reasons`, `instruments`.

`instruments` es nuevo y necesario para ISO 17025: qué equipo de medición se
usó, su fecha de calibración y su vencimiento. Su **nombre es el código de
calibración** (`PP-LA-01C-100`) —así lo llama el laboratorio, y es único por
workspace— y la `description` es el tipo de equipo ("Bureta"), que se repite
entre equipos distintos y por eso no puede identificarlos.

---

## 2. Normas, métodos y parámetros

Esta sección es el corazón del rediseño. Detalle completo en
[`03-NORMAS-Y-LIMITES.md`](03-NORMAS-Y-LIMITES.md).

```
standards        id, slug, code ("IEEE C57.106"), edition ("2015"),
                 full_name, kind (method|acceptance|diagnostic),
                 issuing_body, published_on, superseded_by_id, is_active

analytes         id, slug, code ("acid","rig","ten","wat","pot","h2","ch4",…)
                 name, unit, decimals, group (fiqui|dga|furanos|pcb|azufre|otros)
                 direction (lower_better|higher_better|range|qualitative)
                 sort_order

test_methods     id, slug, analyte_id, standard_id
                 conditions (json: {gap_mm:2.0} | {temp_c:100})
                 label ("ASTM D1816 · 2.0 mm"), accredited (bool), is_active

spec_sets        id, slug, tenant_id, standard_id (acceptance), label
                 oil_type_id, equipment_type_id, service_state (new|in_service)
                 voltage_from, voltage_to, power_from, power_to
                 effective_from, effective_to, is_active, source_note

spec_limits      id, spec_set_id, analyte_id, test_method_id (nullable)
                 operator (<=|>=|between|=|text)
                 value_min, value_max, text_value
                 display_override, warn_ratio, sort_order
```

`spec_sets` + `spec_limits` reemplazan las ~1.100 líneas de `if/elsif` de
`RemReport` y `RemReportDetail`.

---

## 3. Recepción de muestras

```
receptions            id, slug, tenant_id, number ("REM-2026-0031")
  (ex rems)           customer_id, sampler_id, received_at, deliver_due_at
                      containers_count, samples_count
                      container_ok, volume_ok, tag_ok   ← los checks ea/va/dc
                      priority (normal|alta), status, notes
                      received_by, confirmed_at, confirmed_by

samples               id, slug, tenant_id, reception_id
  (ex rem_correlatives) code ("2026-0744"), year, seq
                      equipment_id           ← FK real, no texto
                      sampled_at, sampling_point_id, oil_temp_c, ambient_temp_c
                      status (recibida|en_proceso|completa|anulada)
                      priority, due_at

sample_tests          id, sample_id, test_definition_id
  (ex rem_jobs)       status (solicitada|asignada|en_proceso|hecha|validada|anulada)
                      assigned_to, worksheet_row_id (nullable)
                      requested_at, completed_at, validated_at, validated_by
```

Dos cambios respecto del viejo:

1. `sample_tests` se crea **solo para las pruebas solicitadas**, no una por cada
   una de las 26 definidas.
2. `sample_tests.worksheet_row_id` es una **FK**. Se acabó el emparejamiento
   por `split('-')` sobre el código escrito a mano.

---

## 4. Bancada (hojas de trabajo)

```
test_groups           id, slug, name          (Físico Químico / Cromatografía / Otros)
  (ex lab_category_detail_types)

test_definitions      id, slug, tenant_id, test_group_id
  (ex lab_category_details)  code, name, sort_order, has_control (patrón)
                      default_method_id, is_active

test_fields           id, slug, test_definition_id
  (ex lab_category_sub_details)
                      code ("peso_aceite"), label, sort_order
                      type (text|number|select|date|computed|standard|instrument)
                      unit, decimals
                      is_required, is_locked, is_reusable, default_value
                      formula                ← expresión declarativa, ver abajo
                      output_analyte_id      ← si este campo ES el resultado
                      report_visible

test_field_options    id, test_field_id, value, label, sort_order
  (ex lab_category_sub_detail_options)
                      standard_id (nullable), accredited (bool)

worksheets            id, slug, tenant_id, test_definition_id
  (ex labs)           run_date, analyst_id, status (abierta|cerrada|validada)
                      validated_by, validated_at, ambient_temp_c, ambient_humidity

worksheet_rows        id, worksheet_id, kind (control|sample|duplicate|blank)
  (ex lab_details)    sample_id (nullable — los patrones no tienen muestra)
                      sample_test_id (nullable)
                      instrument_id, instrument_file_id, position

worksheet_values      id, worksheet_row_id, test_field_id
  (ex lab_sub_details)  value_num  (decimal 18,6, nullable)
                      value_text (nullable)
                      option_id  (nullable, si el campo es select)
```

### 4.1 Las fórmulas dejan de ser JavaScript

`test_fields.formula` guarda una **expresión sobre códigos de campo**, evaluada
en el servidor:

```
# Número Ácido, hoy:
#   var result = (col8-col6)*col5/col7;  document.getElementById('col9').value = …
# Nuevo, en test_fields[resultado].formula:
(volumen_gastado - volumen_blanco) * factor_koh / peso_aceite

# Agua, hoy: promedio de col4 y col5 + repetibilidad en col7
promedio:        (lectura_1 + lectura_2) / 2
repetibilidad:   abs(lectura_1 - lectura_2)

# Furanos (DP de Chendong), hoy 6 líneas de JS:
(1.51 - log10(fal_ppb / 1000)) / 0.0035
```

Se evalúa con una librería de expresiones matemáticas acotada
(`symfony/expression-language` o similar) sobre un contexto que solo contiene
los códigos de campo de esa prueba. Beneficios: el servidor recalcula y valida,
reordenar columnas no rompe nada, y no hay ejecución de código arbitrario.

> El campo `formula` se muestra en el editor con la lista de códigos
> disponibles, igual que hoy se edita `blur_calculation`.

---

## 5. Resultados consolidados — la tabla que mata a `rem_report_details`

```
results     id, slug, tenant_id
            sample_id
            analyte_id
            test_method_id            ← la norma de MÉTODO efectivamente usada
            worksheet_row_id, test_field_id   ← trazabilidad al dato crudo
            value_num, value_text
            measured_at
            analyst_id, instrument_id
            lab_temp_c, lab_humidity, sample_temp_c
            status (preliminar|validado|anulado)
            replaced_by_id            ← repetición de ensayo
```

Una fila por parámetro medido. Agregar un parámetro nuevo = insertar una fila
en `analytes` y una en `test_methods`. Cero migraciones, cero cambios de vista.

Índices: `(sample_id, analyte_id)`, `(analyte_id, measured_at)` para las
tendencias.

---

## 6. Informes

```
reports            id, slug, tenant_id
  (ex rem_reports) number ("REP-LAB-2026-0001"), year, seq
                   sample_id, report_type (principal|adicional|correccion)
                   parent_report_id
                   received_at, issued_at, delivered_at, commissioned_at
                   status (borrador|en_revision|emitido|entregado|anulado)
                   spec_set_id                ← el cuadro que se aplicó
                   equipment_snapshot  json   ← datos del equipo congelados
                   language, template_id

report_findings    id, report_id, analyte_id, test_method_id
                   value_num, value_text
                   limit_operator, limit_min, limit_max, limit_display
                   verdict (dentro|fuera|sin_criterio|no_medido)
                   severity, sort_order
                   standard_code_snapshot     ← "IEEE C57.106-2015" congelado

report_narratives  id, report_id, block (fiqui|dga|furanos|general)
                   generated_text, final_text, edited_by, edited_at
```

**El punto clave**: `report_findings` guarda el límite **copiado**, no una FK
viva a `spec_limits`. Un informe emitido en 2023 seguirá mostrando el criterio
de 2023 aunque la norma se actualice en 2027. Es el requisito de auditoría que
hoy se incumple.

`report_narratives` separa el texto generado del texto final, para que el
supervisor pueda editar sin perder el original (hoy el botón
"Reemplazar Análisis de Resultado" pisa el dato).

### 6.1 Plantillas de narrativa

```
narrative_templates  id, slug, tenant_id, block, locale
                     test_group_id, oil_type_id, equipment_type_id (todos nullable)
                     condition (all_ok|one_out|many_out|no_criteria)
                     body, sort_order, is_active
```

El cuerpo usa marcadores:

```
• Los resultados obtenidos de las pruebas de {{parametros_dentro}} están dentro
  de los valores sugeridos por la Norma {{norma_aceptacion}}.
• La cantidad de {{parametros_fuera}} está fuera del valor sugerido por la
  Norma {{norma_aceptacion}}.
```

Una plantilla por bloque × condición × idioma reemplaza los cinco bloques ERB
duplicados de `_form_add_details_physicals_default_values.html.erb`. Cambiar la
redacción o agregar inglés no toca código.

### 6.2 Firmas y aprobación

Se reutiliza **tal cual** el mecanismo de TRAFODEX: `report_signers`,
`report_requests`, `report_instances`, `report_approvals`, con la misma regla
de firma (la imagen se estampa solo con auto-firma consentida y auditada) y la
verificación HMAC + QR contra el registro de auditoría.

---

## 7. Control de calidad analítica

```
qc_charts     id, slug, tenant_id, test_definition_id, analyte_id
              target, sd, limit_type (levey_jennings|range)
              warn_sigma (2), action_sigma (3)
              effective_from, effective_to

qc_results    id, qc_chart_id, worksheet_row_id, measured_at
              value, z_score, flag (ok|warn|out)
              westgard_rule (nullable: 1_3s, 2_2s, R_4s, 4_1s, 10x)
```

Se alimenta automáticamente de las filas `worksheet_rows.kind = control`. Una
sola vista de carta de control parametrizada por analito reemplaza los 10
parciales `_amcharts_*.html.erb` de ~405 líneas cada uno.

Los duplicados (`kind = duplicate`) alimentan el cálculo de repetibilidad
contra el criterio de la prueba.

---

## 8. Almacén y etiquetas

Porte directo, con los nombres normalizados:

```
stock_items      id, slug, tenant_id, code, name, unit, min_qty, is_active
stock_lots       stock_item_id, lot_code, expires_on, qty_initial, qty_available
stock_movements  stock_lot_id, kind (in|out|loan|return|adjust), qty,
                 user_id, worksheet_id (nullable), moved_at, notes
labels           id, slug, sample_id, printed_at, printed_by, qr_payload
  (ex stickers)
```

---

## 9. Integración

```
integration_targets  id, slug, name ("TRAFODEX prod"), base_url,
                     token (cifrado), is_active, tenant_id

outbound_messages    id, slug, tenant_id, target_id
                     resource_type (sample_result|report_pdf|equipment)
                     resource_id, idempotency_key (uuid, único por target)
                     payload json, status (pendiente|enviado|error|descartado)
                     attempts, last_error, http_status, response json
                     sent_at, acknowledged_at, remote_id
```

Detalle del contrato en [`04-INTEGRACION-TRAFODEX.md`](04-INTEGRACION-TRAFODEX.md).

---

## 10. Resumen del cambio

| Sistema viejo | Sistema nuevo |
|---|---|
| `rem_report_details` (~250 columnas) | `results` (1 fila por parámetro) + `report_findings` |
| 4 métodos con ~1.100 líneas de `if` | `spec_sets` + `spec_limits` (datos) |
| Norma asignada por `oil_type_id` clavado | `standards` + resolución por `spec_sets` con vigencia |
| Norma de método ignorada | `test_methods` + `results.test_method_id` |
| Narrativa en ERB anidado × 5 aceites | `narrative_templates` con marcadores |
| `blur_calculation` = JavaScript en BD | `test_fields.formula` = expresión evaluada en servidor |
| Vínculo hoja↔muestra por `split('-')` | FK `worksheet_rows.sample_id` |
| `num_ten` string `"220/60/10"` | columnas numéricas + banda resuelta una vez |
| 26 `rem_jobs` por muestra siempre | `sample_tests` solo de lo solicitado |
| Escritura directa en la BD de TRAFODEX | API con idempotencia y cola de reintento |
| `deleted = 0/1` | SoftDeletes + papelera + restore |
| `user_permission.include?(61)` | Spatie Permission (`permission:samples.create`) |
| Una instalación = un laboratorio | Multi-empresa con `tenant_id` |
