# E — Cobertura completa del esquema, tabla por tabla

> **Qué se audita.** Las **47 tablas** de la base real del sistema Rails viejo
> (`lab_app_development`), una por una, contra el sistema nuevo. Ninguna
> excepción: catálogos de tres filas, tablas de infraestructura, almacén,
> auditoría y adjuntos incluidos.
>
> **Fuente del esquema viejo.**
> `docs/migracion/esquema/lab_app_development-estructura.sql` (47 tablas, 723
> columnas). `labo_old/db/schema.rb` **no se usó como fuente**: declara 18
> tablas y contradice a la base real; el detalle de esa discrepancia ya está en
> [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md) §1.
>
> **Fuente de los conteos de filas.**
> `docs/migracion/esquema/catalogos-definiciones.sql` (volcado real de los
> catálogos y las definiciones de producción). Donde no hay volcado se dice "sin
> volcado", no se estima.
>
> **Fuente del esquema nuevo.** Las 83 tablas creadas en
> `database/migrations/` más las 5 de Spatie, y los 70 modelos de `app/Models/`.
>
> **El sistema viejo no se modificó.** Este archivo es el único que se escribió.

---

## 0. Los números, en una pantalla

| | Tablas | |
|---|---|---|
| Tablas del viejo | **47** | 723 columnas |
| **PORTADA** | **16** | equivalente completo, sin pérdida con consecuencia |
| **PARCIAL** | **15** | equivalente con algo que falta, nombrado en la matriz |
| **NO PORTADA** | **13** | sin equivalente |
| **DESCARTADA** con documentación previa | **1** | `rem_conditions` (0 filas) |
| **INFRA** del framework | **2** | `ar_internal_metadata`, `schema_migrations` |

Las 13 no portadas no son 13 problemas: se agrupan en **cinco bloques**.

| Bloque | Tablas | Consecuencia |
|---|---|---|
| Almacén / préstamo de equipos | 5 (`stocks`, `stock_details`, `stock_detail_moves`, `stock_detail_returns`, `stock_units`) | Fase 10 del plan maestro. No bloquea informes |
| Catálogos degradados a texto libre | 3 (`transformer_oil_marks` 52 filas, `transformer_oil_units` 6, `transformer_points` 4) | **Sí tiene consecuencia**: el punto de muestreo cambia el resultado esperado |
| Bitácora de condiciones del laboratorio | 2 (`cro_temperatures` 100 filas, `fiq_temperatures` 100) | **Sí tiene consecuencia**: la presión atmosférica no tiene columna en ninguna parte del nuevo |
| Etiquetas con QR | 1 (`stickers`) | Paso físico de la recepción. Fase 10 |
| Procedencia y aviso interno | 2 (`db_systems`, `rem_report_detail_issues`) | Decisión D2 y pérdida del aviso de dato faltante |

Ningún bloque impide emitir un informe. Dos tienen consecuencia normativa; están
en el §"LO QUE FALTA" con su número de evidencia.

---

## 1. Advertencia de método: el viejo tenía DOS bases

`labo_old/config/database.yml:36-49` declara dos conexiones:

- **`primary` = `lab_app_development`** — la base del laboratorio. **Es el
  alcance de esta auditoría**: las 47 tablas de la matriz.
- **`primary2` = `tr_app_development`** — la base de TrafoDex, a la que el
  laboratorio **escribía directo**. Sus tablas no son del sistema viejo: son de
  otro sistema, ya migrado. Se listan aparte en el §4 para que la cobertura sea
  completa y para que nadie las busque en la matriz principal.

La pista de que son dos bases está escondida: cinco modelos con
`establish_connection` explícito
(`labo_old/app/models/transformer_trapp.rb:2-3`) y diez más que heredan de
`labo_old/app/models/primary2.rb:1-4`, donde la única señal es que la línea
`class X < ActiveRecord::Base` quedó comentada
(`labo_old/app/models/customer.rb:1-2`).

Además hay **dos modelos sin tabla** en la base real: `rem_task.rb` y
`user_notification.rb`. Están escritos con sus relaciones
(`labo_old/app/models/rem_task.rb:3-5`,
`labo_old/app/models/user_notification.rb:3-4`) y **ninguna tabla `rem_tasks` ni
`user_notifications` existe** en el volcado de estructura. Es código muerto, se
suma a la lista de §5.4 de [`../11-AUDITORIA-VIEJO-VS-NUEVO.md`](../11-AUDITORIA-VIEJO-VS-NUEVO.md).

---

## 2. La matriz

Leyenda del estado:

- **PORTADA** — hay tabla equivalente y no se perdió nada con consecuencia.
- **PARCIAL** — hay tabla equivalente y falta algo que se nombra.
- **NO PORTADA** — no existe equivalente.
- **DESCARTADA** — no existe equivalente **y hay documentación previa que lo
  justifica**; se cita dónde.
- **INFRA** — tabla de infraestructura del framework; su equivalente es del
  framework nuevo, no del dominio.

### 2.1 Usuarios, perfiles y permisos

| # | Tabla vieja | Cols | Filas | Tabla(s) del nuevo | Estado | Qué se pierde |
|---|---|---|---|---|---|---|
| 1 | `accesses` (`esquema/lab_app_development-estructura.sql:30-36`) | 5 | 66 | `permissions` (`database/migrations/2025_09_18_093509_create_permission_tables.php:24`) | PORTADA | Nada. El árbol de dos niveles (`parent_id`) se volvió la convención `modulo.accion`; los 66 accesos se cubren con permisos por módulo y acción, que es **más** granular que el viejo (allá los cuatro CRUD de plantillas vivían detrás de un único permiso, ver `../11-AUDITORIA-VIEJO-VS-NUEVO.md` §5.5) |
| 2 | `profiles` (`:429-435`) | 5 | 4 | `roles` (`...create_permission_tables.php:34`) | PORTADA | Nada. Los cuatro perfiles reales (Administrador Principal, Hitachi Master, Hitachi Operadores, Hitachi Operadores - Admin) son roles |
| 3 | `profile_accesses` (`:443-449`) | 5 | 170 | `role_has_permissions` (`...create_permission_tables.php:135`) | PORTADA | Nada |
| 4 | `users` (`:1142-1168`) | 25 | sin volcado | `users` (`database/migrations/2025_09_18_093438_create_users_table.php:12`) | **PARCIAL** | **`num_doc`** (documento de identidad), **`cellphone`** (teléfono), **`username`** y el apellido partido en dos (`lastname1`, `lastname2`). El nuevo tiene un solo `name` y no tiene teléfono ni documento. En un laboratorio acreditado el analista que firma se identifica por documento, no solo por nombre. Lo que **no** es pérdida: `real_password` (la contraseña en claro), `salt`, `hashed_password` propio y `authentication_token`, reemplazados por hash de Laravel y Sanctum |

### 2.2 Auditoría

| # | Tabla vieja | Cols | Filas | Tabla(s) del nuevo | Estado | Qué se pierde |
|---|---|---|---|---|---|---|
| 5 | `audits` (`:57-73`) | 15 | sin volcado | `audit_logs` (`database/migrations/2026_05_05_100000_create_audit_logs_table.php:11`) | **PARCIAL** | **`associated_id` / `associated_type`** (el registro PADRE del cambio: permitía ver "todo lo que se tocó de esta remisión" aunque el cambio fuera en un detalle), **`version`** (número de revisión por registro) y **`request_uuid`** (agrupa todos los cambios de una misma petición HTTP: sin él, un guardado que escribe seis tablas son seis filas sueltas). El nuevo suma `module`, `url`, `user_agent` y `note`. Nota relacionada: [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md) §0, punto 4, documenta que **el cambio del valor constante dejó de auditarse** |

### 2.3 Plantillas de ensayo (el corazón de la configuración)

| # | Tabla vieja | Cols | Filas | Tabla(s) del nuevo | Estado | Qué se pierde |
|---|---|---|---|---|---|---|
| 6 | `lab_category_detail_types` (`:200-207`) | 6 | 3 | `test_groups` (`database/migrations/2026_07_28_090000_create_test_definitions_tables.php:61`) | **PARCIAL** | **`icon_label`** — el icono Font Awesome del menú (`bong`, `syringe`, `flask-vial`), editable y obligatorio en el viejo (`labo_old/app/views/layouts/_app_sidebar_left_menus.html.erb:211`). El nuevo no tiene columna de icono: los tres grupos salen iguales en el menú. Cosmético, ya listado en [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md) §10.1 |
| 7 | `lab_category_details` (`:178-192`) | 13 | 29 | `test_definitions` (`...create_test_definitions_tables.php:113`) | **PARCIAL** | `has_reuse` está **mapeado al concepto equivocado** (`has_control`), documentado en [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md) §10.2. `blur_calculation` (el JavaScript guardado en la base) se reemplazó por fórmula POR CAMPO evaluada en el servidor — mejora, no pérdida, detalle en [`B-formulas.md`](B-formulas.md) |
| 8 | `lab_category_sub_details` (`:215-233`) | 17 | 208 | `test_fields` (`...create_test_definitions_tables.php:170`) | **PARCIAL** | **`report_use`**, **`is_imported`**, **`imported_value`**, **`imported_remove_value`**: las cuatro tienen valor de negocio y **nadie las lee en el nuevo**. La estructura destino existe (`report_visible`, `instrument_formats.column_map`) pero el dato no se migró. Ya cuantificado en [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md) §6.6-6.7 y §10.3 |
| 9 | `lab_category_sub_detail_options` (`:241-251`) | 9 | 93 | `test_field_options` (`...create_test_definitions_tables.php:263`) | PORTADA | Nada. `applicability_flag` → `accreditation_flag` + `is_accredited` (`database/migrations/2026_07_29_120000_add_is_accredited_to_test_field_options.php`), que separa el hecho de la acreditación de su rótulo |
| 10 | `lab_category_sub_detail_types` (`:259-265`) | 4 | 4 | `test_fields.type` (columna, no tabla) | PORTADA | Nada. Los cuatro tipos reales (Texto, Número, Selección, Fecha) son un enumerado en el campo; el importador los traduce (`app/Console/Commands/ImportLegacyTestsCommand.php:52-57`) |
| 11 | `lab_detail_types` (`:291-297`) | 5 | 3 | `worksheet_rows.kind` (columna, no tabla) | PORTADA | Nada. Las tres filas reales (Patrón Control, Muestra, Duplicado) son `control` / `sample` / `duplicate` en `app/Models/WorksheetRow.php:32-48`, que además agrega `blank` |
| 12 | `norms` (`:355-361`) | 5 | 12 | `standards` (`database/migrations/2026_07_28_140000_create_specs_tables.php:60`) | PORTADA | Nada, y se corrige un defecto: el viejo mezclaba norma de MÉTODO con norma de ACEPTACIÓN en la misma lista de 12; el nuevo las declara con `kind` (`database/seeders/data/standards.json`). Ojo: la fila 12 del viejo dice `IEC 610203-2025`, que **no es un número IEC válido** — anomalía ya registrada en `database/seeders/data/spec_limits_legacy.json:2138` |

### 2.4 Bancada (la corrida de laboratorio)

| # | Tabla vieja | Cols | Filas | Tabla(s) del nuevo | Estado | Qué se pierde |
|---|---|---|---|---|---|---|
| 13 | `labs` (`:160-170`) | 9 | sin volcado | `worksheets` (`database/migrations/2026_07_28_100000_create_worksheets_tables.php:70`) | PORTADA | Nada. `date_rehearsal`→`run_date`, `user_id`→`analyst_id`, `validate_user_id`→`validated_by`, `state`→`status`. El nuevo suma `ambient_temp_c`, `ambient_humidity`, `sample_temp_c`, `void_reason` y candado por antigüedad |
| 14 | `lab_details` (`:273-283`) | 9 | sin volcado | `worksheet_rows` (`...create_worksheets_tables.php:115`) | PORTADA | Nada. `num_test` (varchar copiado por jQuery) pasó a ser ETIQUETA (`sample_code`) con la relación real en `sample_test_id`; el porqué está escrito en `database/migrations/2026_07_28_130000_create_receptions_tables.php:29-38` |
| 15 | `lab_sub_details` (`:339-347`) | 7 | sin volcado | `worksheet_values` (`...create_worksheets_tables.php:164`) + `results` (`database/migrations/2026_07_28_120000_create_results_table.php:79`) | PORTADA | Nada. El viejo guardaba TODO en un `name varchar(255)` —número, observación y las N réplicas concatenadas con `/` (`labo_old/app/models/lab_sub_detail.rb:16-17`)—; el nuevo separa `value_num` / `value_text` / `qualifier` / `replicate_no` |
| 16 | `lab_files` (`:305-315`) | 9 | sin volcado | `instrument_files` (`...create_worksheets_tables.php:240`) | PORTADA | Nada. El nuevo suma `sha256`, `mime`, `size`, `status`, `parse_error` y `rows_parsed` |
| 17 | `lab_file_details` (`:323-331`) | 7 | sin volcado | `worksheet_values` vía `app/Services/Lab/InstrumentFileParser.php` + `instrument_formats` (`...create_worksheets_tables.php:269`) | PORTADA | Nada estructural. El parche SQL de furanos con `substring_index` que el viejo tenía en el modelo (`labo_old/app/models/lab_file_detail.rb:24-26`) desaparece porque el mapeo es dato (`column_map`) |
| 18 | `patron_tendences` (`:369-421`) | 51 | 27 | `qc_charts` (`database/migrations/2026_07_28_110000_create_qc_tables.php:65`) + `qc_points` (`:132`) + `qc_duplicates` (`:163`) | **PARCIAL** | **Los 27 registros (45 valores de límite reales) NO están migrados.** La estructura existe y es mejor (una fila por carta en vez de 51 columnas con prefijo por gas), pero hay que cargarla a mano. Y las cartas del viejo traen los cinco límites **sin desviación estándar**: cargadas tal cual, Westgard queda apagado. Es el pendiente B3 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md) |

### 2.5 Recepción, muestras y trabajos

| # | Tabla vieja | Cols | Filas | Tabla(s) del nuevo | Estado | Qué se pierde |
|---|---|---|---|---|---|---|
| 19 | `rems` (`:457-499`) | 41 | sin volcado | `receptions` (`database/migrations/2026_07_28_130000_create_receptions_tables.php:93`) + `sample_counters` (`:79`) | **PARCIAL** | **Las 15 columnas `num_fiq`…`num_pas`** (cuántos ensayos de cada familia se PACTARON al recibir) y **`qty_num_test`**: el viejo tipeaba la cantidad comprometida y comparaba contra lo emitido (`labo_old/app/models/rem.rb:124-131`). En el nuevo la cantidad se DERIVA de `sample_tests`, así que **no se puede expresar una diferencia entre lo vendido y lo cargado**. `ea_val`/`va_val`/`dc_val` → `container_ok`/`volume_ok`/`label_ok` (mejor nombrados); `qty_num_pack` → `packages`; `date_deliver` → `due_at`; `validity` + `correlative_confirmed` + `state` → `status` + `confirmed_at`/`confirmed_by`; los cuatro `*_done` cacheados pasaron a derivarse en la misma consulta del listado (C9 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md), resuelto) |
| 20 | `rem_correlatives` (`:524-540`) | 15 | sin volcado | `samples` (`...create_receptions_tables.php:154`) | **PARCIAL** | `pending_tr` / `pending_tk` / `pending_va` (banderas de "falta equipo / falta tarea / falta valor" que alimentaban seis filtros de los reportes gerenciales, `labo_old/app/controllers/report_management/ents_controller.rb:55-65`): en el nuevo se derivan, pero **los reportes gerenciales que las consumían no existen** (§2.9). `date_urgent` (hasta cuándo dura la urgencia, que un evento nocturno de MySQL apagaba — `labo_old/README_EVENTS.md`) no tiene equivalente: `is_urgent` es permanente hasta que alguien lo apague a mano. `qr_code` está descartado a propósito (columna que nadie escribía ni leía, `../12-CHECKLIST.md` "Lo que NO hay que portar") |
| 21 | `rem_jobs` (`:548-558`) | 9 | sin volcado | `sample_tests` (`...create_receptions_tables.php:221`) | PORTADA | Nada, y se corrige el defecto de origen: el viejo insertaba una fila por CADA prueba del catálogo y después se marcaba a mano cuáles iban (`...create_receptions_tables.php:47-50`); el nuevo solo crea las pedidas. El nuevo suma `started_at`/`validated_at`/`reported_at` |
| 22 | `rem_conditions` (`:507-516`) | 8 | **0** | — | **DESCARTADA** | Tabla vacía y sin referencias: un intento previo de sacar los límites a datos, abandonado. Ya declarada muerta en [`../11-AUDITORIA-VIEJO-VS-NUEVO.md`](../11-AUDITORIA-VIEJO-VS-NUEVO.md) §5.4. Su intención sí se cumplió, por otro camino: `spec_sets` + `spec_limits` |

### 2.6 El informe

| # | Tabla vieja | Cols | Filas | Tabla(s) del nuevo | Estado | Qué se pierde |
|---|---|---|---|---|---|---|
| 23 | `rem_reports` (`:566-610`) | 43 | sin volcado | `sample_reports` (`database/migrations/2026_07_29_130000_create_sample_reports_tables.php:72`) + `report_counters` (`:58`) + cabecera en `receptions`/`samples` (`database/migrations/2026_07_28_170000_add_report_header_fields.php`) | **PARCIAL** | **`customer_evidence`** — la evidencia que entregó el cliente, con su pantalla: no existe columna en el nuevo (ya listado en `../11-AUDITORIA-VIEJO-VS-NUEVO.md` §5.2). Las 14 columnas de placa congelada en el informe (`mark_id`, `num_ten`, `num_pot`, `transformer_type_id`, `oil_type_id`, `transformer_oil_mark_id`, `age`, `conmutation_type_id`, `transformer_preservation_id`, `oil_qty`, `transformer_oil_unit_id`, `transformer_point_id`, `location`, `num_tag`) se reemplazaron por `sample_reports.snapshot` al emitir — equivalente y más honesto, pero **el snapshot es JSON: no se puede filtrar un listado por la marca que decía el informe**. `date_pue` es columna muerta (tiene dos métodos de formato en `labo_old/app/models/rem_report.rb:105-111` y **cero usos en vistas**). `operation` y `was_updated` son banderas internas del flujo viejo, sin destino ni necesidad |
| 24 | `rem_report_details` (`:618-840`) | **221** | sin volcado | `results` (`database/migrations/2026_07_28_120000_create_results_table.php:79`) + `sample_report_tests` (`...create_sample_reports_tables.php:135`) + `spec_limits` (`database/migrations/2026_07_28_140000_create_specs_tables.php:212`) + `sample_diagnoses` (`database/migrations/2026_07_28_180000_create_sample_diagnoses_table.php`) | **PARCIAL** | La tabla de 221 columnas se descompuso bien: 29 `*_display` → `sample_report_tests.is_visible`; 29 `*_lab_detail_id` → `results.worksheet_row_id`; 66 `*_val` → `results.value_num`/`value_text`; 33 `*_ori` → `spec_limits` (243 límites extraídos, **sin validar por el laboratorio** — B2 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md)); 15 `*_comment` → `sample_diagnoses.body`; 15 `*_date` → `worksheets.run_date`; 6 `*_norm_id` → `test_methods`/`spec_sets.standard_id`; 13 `fiq_item*` son el número de fila impreso, hoy derivado del orden. **Lo que falta de verdad son 2 de las 6 columnas de condición de ensayo**: `fiq_lab_tem`/`fiq_lab_hum`/`cro_lab_tem`/`cro_lab_hum` tienen destino (`worksheets.ambient_temp_c`, `ambient_humidity`), pero **`fiq_lab_pre` y `cro_lab_pre` — la presión atmosférica — no tienen columna en ninguna parte del sistema nuevo** |
| 25 | `rem_report_detail_issues` (`:848-856`) | 7 | sin volcado | — | **NO PORTADA** | El aviso interno de "falta un valor": cuando el formulario del informe no encontraba una medición, insertaba una fila con el enlace a la pantalla y el nombre del parámetro, y mandaba un correo ("No se encuentra el valor: Metales Cobre") — `labo_old/app/views/im_management/rem_reports/partials/_form_add_details_metales.html.erb:24`, `labo_old/app/mailers/user_management/user_mailers.rb:34-38`. **Se pierde el aviso proactivo de dato faltante al armar el informe**: hoy nadie avisa, el parámetro sale en blanco. Ojo: la implementación vieja creaba filas **desde la vista, al renderizar**, así que un informe abierto tres veces generaba tres avisos |
| 26 | `rem_report_reasons` (`:864-870`) | 5 | 6 | `samples.sampling_reason` (varchar, `database/migrations/2026_07_28_170000_add_report_header_fields.php`) | **PARCIAL** (degradación) | Era catálogo con CRUD y 6 filas reales (Rutina, Evento, Tratamiento Termo Vacío, Tratamiento Regeneración, Cambio de aceite, Otros); hoy es **texto libre**. Se pierde poder agrupar por motivo sin normalizar a mano. Es uno de los cuatro catálogos degradados de C3 en [`../12-CHECKLIST.md`](../12-CHECKLIST.md) |
| 27 | `rem_signatures` (`:878-886`) | 7 | sin volcado | `signatures` (`database/migrations/2026_07_29_043439_create_signatures_table.php:15`) | PORTADA | Nada. El nuevo suma `relation` (lista cerrada traducible), `user_id`, `title` y orden |
| 28 | `rem_user_signatures` (`:894-901`) | 6 | sin volcado | `signatures` + `users.signature` (`database/migrations/2025_09_18_093438_create_users_table.php:12`) | PORTADA | Nada. Las dos tablas del viejo (firma del informe y firma del usuario) se unificaron en una, con la imagen atada al firmante |
| 29 | `samplers` (`:909-917`) | 7 | 12 (sembrados en `database/seeders/SamplersSeeder.php:33-34`) | `samplers` (`database/migrations/2026_07_29_041836_create_samplers_table.php:15`) | **PARCIAL** | **`num_doc`** — el documento del muestreador. Como los 12 registros reales no son personas sino áreas y terceros (LABORATORIO, CLIENTE, ABB, SUBCONTRATISTA — ver el comentario del seeder), la pérdida es teórica hoy; deja de serlo el día que el laboratorio quiera registrar a la persona física que extrajo la muestra, que es lo que ISO 17025 pide trazar |

### 2.7 Equipos y sus catálogos

| # | Tabla vieja | Cols | Filas | Tabla(s) del nuevo | Estado | Qué se pierde |
|---|---|---|---|---|---|---|
| 30 | `transformers` (`:1041-1062`) | 20 | 100 (volcado citado en [`D-placa-equipos.md`](D-placa-equipos.md) §1) | `equipment` (`database/migrations/2026_07_28_061051_create_equipment_table.php:37`) | **PARCIAL** | El grueso está y mejorado: `num_ten`/`num_pot` (texto libre con barras) → `voltage_kv_hv`/`lv`/`tv` + `power_mva`/`_2`/`_3`, `location` → jerarquía real de cliente. Lo que se degradó a texto: **`transformer_oil_mark_id` → `equipment.oil_brand` varchar(120)**, **`transformer_oil_unit_id` → `oil_volume_unit`**, y **`transformer_point_id` se movió a `samples.sampling_point` varchar(80)** — los tres eran FK a catálogo. `age` (texto) → `manufacture_year` (entero), que es una mejora pero exige convertir el histórico |
| 31 | `transformer_types` (`:1127-1134`) | 6 | **21** | `equipment_types` (`database/migrations/2026_05_30_100000_create_lab_catalogs_tables.php:40`) | **PARCIAL** | **El seeder siembra 20 de los 21 tipos reales**: falta **`Regulador de Voltaje`** (id 21, creado 2024-08-09 en la base real). El seeder dice "Son 20." (`database/seeders/LabCatalogsSeeder.php:57`) porque la lista salió de un comentario de una vista, no del volcado. Y se pierde la columna **`comment`**, que valía `TRAPP` exactamente en los tres tipos que TrafoDex conoce (potencia, distribución, horno) y era la marca de qué se podía diagnosticar allá — hoy esa decisión no tiene dónde escribirse (D1 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md)) |
| 32 | `transformer_preservations` (`:1112-1119`) | 6 | 4 | `transformer_preservations` (`database/migrations/2026_05_30_100050_create_transformer_preservations_table.php:17`) | PORTADA | Nada relevante (`comment` no se usa) |
| 33 | `transformer_oil_marks` (`:1070-1076`) | 5 | **52** | `equipment.oil_brand` (varchar 120, `database/migrations/2026_07_29_130000_create_sample_reports_tables.php:158`) | **NO PORTADA** como tabla | Catálogo con CRUD y **52 marcas de aceite reales** convertido en texto libre. Es la degradación más grande de las cuatro de C3: 52 valores normalizados quedan a merced de quien tipee |
| 34 | `transformer_oil_units` (`:1084-1090`) | 5 | 6 | `equipment.oil_volume_unit` (varchar) | **NO PORTADA** como tabla | Las 6 unidades reales (Kg, Lb, L, Gl, Cil, `-`) son texto libre. Es exactamente el camino por el que la base vieja terminó con "2500 gal", "2500 galones" y "2500Gal" |
| 35 | `transformer_points` (`:1098-1104`) | 5 | 4 | `samples.sampling_point` (varchar 80) | **NO PORTADA** como tabla | Los 4 puntos de muestreo (`-`, Inferior, Medio, Superior) son texto libre. **Es el más sensible de los tres**: el punto de extracción cambia el resultado esperado de un ensayo de aceite, y sin catálogo no se puede comparar históricos "mismo punto contra mismo punto" |
| 36 | `import_transformers` (`:130-152`) | 21 | 43 (volcado citado en [`D-placa-equipos.md`](D-placa-equipos.md)) | reemplazada por `App\Imports\BusinessManagement\Equipment\EquipmentImport` (vista previa con `dryRun`) | **PARCIAL** (reemplazada por otro diseño) | La tabla de escalonamiento por usuario no hace falta: el nuevo previsualiza en transacción y revierte. Pero **el importador nuevo acepta 6 columnas (`name`, `customer`, `serial`, `tag`, `voltage_kv`, `power_mva`) y el viejo escalonaba 15**: quedan afuera tipo de equipo, marca, conmutador, preservación, tipo de aceite, marca de aceite, unidad de volumen, volumen y antigüedad. Importar el padrón deja esos nueve campos vacíos, equipo por equipo |

### 2.8 Bitácora de condiciones del laboratorio

| # | Tabla vieja | Cols | Filas | Tabla(s) del nuevo | Estado | Qué se pierde |
|---|---|---|---|---|---|---|
| 37 | `cro_temperatures` (`:81-90`) | 8 | **100** | — (parcialmente `worksheets.ambient_temp_c` / `ambient_humidity`) | **NO PORTADA** | Bitácora diaria por fecha, con CRUD, que **precargaba** el formulario del día. Hoy los dos valores se tipean en cada hoja de bancada y no hay registro por fecha consultable. Y la **presión atmosférica (`cro_lab_pre`) no existe en ninguna columna del sistema nuevo** — para cromatografía es dato de ensayo. C1 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md) |
| 38 | `fiq_temperatures` (`:113-122`) | 8 | **100** | — (ídem) | **NO PORTADA** | Ídem para fisicoquímico (`fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`) |

### 2.9 Etiquetas, almacén y procedencia

| # | Tabla vieja | Cols | Filas | Tabla(s) del nuevo | Estado | Qué se pierde |
|---|---|---|---|---|---|---|
| 39 | `stickers` (`:935-946`) | 10 | sin volcado | — | **NO PORTADA** | Las etiquetas del envase. En el viejo eran dos mecanismos que no se hablaban (uno con el responsable clavado en el HTML, otro con un QR que codificaba una URL de producción escrita en el modelo). Se pierde **imprimir la etiqueta que se pega al frasco**, que es un paso físico del proceso de recepción, no una comodidad. C6 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md); fase 10 del plan maestro |
| 40 | `stocks` (`:954-963`) | 8 | sin volcado | — | **NO PORTADA** | Almacén. En el viejo era **préstamo de equipos**, no insumos, y `stocks.qty` nunca se tocaba. C7 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md) |
| 41 | `stock_details` (`:971-979`) | 7 | sin volcado | — | **NO PORTADA** | La cabecera del préstamo (fecha, si es préstamo, descripción). No registra quién pide ni quién autoriza |
| 42 | `stock_detail_moves` (`:987-1002`) | 14 | sin volcado | — | **NO PORTADA** | El movimiento con sus tres tríos (entregado / devuelto / pendiente) |
| 43 | `stock_detail_returns` (`:1010-1019`) | 8 | sin volcado | — | **NO PORTADA** | Las devoluciones parciales |
| 44 | `stock_units` (`:1027-1033`) | 5 | 3 | — | **NO PORTADA** | Las 3 unidades (unidad, juego, paquete). En el viejo ni tenía controlador: la ruta `stock_management/stock_units` estaba declarada sin destino |
| 45 | `db_systems` (`:98-105`) | 6 | sin volcado | — | **NO PORTADA** | La procedencia del registro. El viejo marcaba `db_system_id = 2` en cada cliente creado desde el laboratorio; **nunca se leyó y se perdió también en TrafoDex**. Sin ella no se puede responder "¿este cliente lo cargó alguien acá o entró por el laboratorio?". Es la decisión D2 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md) |

### 2.10 Infraestructura del framework

| # | Tabla vieja | Cols | Tabla(s) del nuevo | Estado |
|---|---|---|---|---|
| 46 | `ar_internal_metadata` (`:44-49`) | 4 | — | **INFRA**. Metadato interno de ActiveRecord (guarda el entorno). Laravel no lo tiene ni lo necesita |
| 47 | `schema_migrations` (`:925-927`) | 1 | `migrations` (la crea Laravel) | **INFRA**. Equivalente exacto |

---

## 3. Resumen numérico

Una fila de la matriz = una tabla del viejo. Los cinco estados suman 47.

| Estado | Tablas | Cuáles |
|---|---|---|
| **PORTADA** | **16** | `accesses`, `profiles`, `profile_accesses`, `lab_category_sub_detail_options`, `lab_category_sub_detail_types`, `lab_detail_types`, `norms`, `labs`, `lab_details`, `lab_sub_details`, `lab_files`, `lab_file_details`, `rem_jobs`, `rem_signatures`, `rem_user_signatures`, `transformer_preservations` |
| **PARCIAL** | **15** | `users`, `audits`, `lab_category_detail_types`, `lab_category_details`, `lab_category_sub_details`, `patron_tendences`, `rems`, `rem_correlatives`, `rem_reports`, `rem_report_details`, `rem_report_reasons`, `samplers`, `transformers`, `transformer_types`, `import_transformers` |
| **NO PORTADA** | **13** | `rem_report_detail_issues`, `transformer_oil_marks`, `transformer_oil_units`, `transformer_points`, `cro_temperatures`, `fiq_temperatures`, `stickers`, `stocks`, `stock_details`, `stock_detail_moves`, `stock_detail_returns`, `stock_units`, `db_systems` |
| **DESCARTADA** con documentación | **1** | `rem_conditions` (0 filas y 0 referencias) |
| **INFRA** | **2** | `ar_internal_metadata`, `schema_migrations` |
| | **47** | |

Tres de las PARCIAL lo son por **degradación a texto libre**, no por columna
faltante: `rem_report_reasons`, y dentro de `transformers` la marca de aceite y
la unidad de volumen. Se cuentan una sola vez, en la fila del catálogo que
desapareció.

Además hay **dos columnas** descartadas a propósito, con justificación escrita
en [`../12-CHECKLIST.md`](../12-CHECKLIST.md) ("Lo que NO hay que portar"):
`rem_correlatives.qr_code` (nadie la escribía ni la leía) y `rem_reports.date_pue`
(dos métodos de formato en el modelo, cero usos en vistas).

**Columnas.** De las 723 del viejo, las que no tienen destino **y** tienen
consecuencia son **17**: las 2 de presión atmosférica (`fiq_lab_pre`,
`cro_lab_pre`) y las 15 de cantidad pactada por familia en `rems`
(`num_fiq`…`num_pas`). Todo el resto de las ausencias son tablas enteras, no
columnas sueltas dentro de una tabla portada.

---

## 4. Apéndice: las 14 tablas de la OTRA base

El sistema viejo leía y escribía 14 tablas de `tr_app_development` (TrafoDex).
No son tablas del sistema viejo, pero si no se listan la cobertura queda
incompleta.

| Tabla en `tr_app` | Cómo la usaba el viejo | En el nuevo |
|---|---|---|
| `customers` | lectura y escritura (`labo_old/app/models/customer.rb:2`, `customer_trapp.rb:2-3`) | `customers` propio (`database/migrations/2026_05_13_223304_create_customers_table.php`) |
| `customer_locations` | lectura y escritura | `customer_locations` propio |
| `customer_areas` | lectura y escritura | `customer_areas` propio |
| `customer_substations` | lectura y escritura | `customer_substations` propio |
| `countries` | lectura | `countries` propio |
| `oil_types` | lectura y escritura | `oil_types` propio |
| `marks` | lectura y escritura | `brands` propio |
| `conmutation_types` | lectura y escritura | `tap_changer_types` propio |
| `transformers` | escritura (`transformer_trapp.rb:2-3`) | `equipment` propio; el envío pasa a ser API, no escritura directa |
| `chromatographicals` | escritura (`chromatographical_trapp.rb:2-3`) | destino de la API de laboratorio de TrafoDex |
| `physicals` | escritura (`physical_trapp.rb:2-3`) | ídem |
| `furanos` | escritura (`furano_trapp.rb:2-3`) | ídem |
| `chromatographical_dga_diags` | lectura (`chromatographical_dga_diag.rb:1`) | del dominio de TrafoDex |
| `chromatographical_duvals` | lectura y **escritura con `after_update`** (`chromatographical_duval.rb:1-4`) | del dominio de TrafoDex |

El desacople ya está hecho: LaboRep tiene sus catálogos propios y no abre una
segunda conexión. El detalle de qué viajaba y qué no está en
[`../11-AUDITORIA-VIEJO-VS-NUEVO.md`](../11-AUDITORIA-VIEJO-VS-NUEVO.md) §4.

---

## LO QUE FALTA

Ordenado por consecuencia real para el laboratorio. Lo que solo es incomodidad
no está en esta lista.

### 1. La presión atmosférica no tiene columna en ninguna parte

`fiq_lab_pre` y `cro_lab_pre` existían en la bitácora
(`esquema/lab_app_development-estructura.sql:84,116`) **y** congeladas en cada
informe (`:813,817`). En el sistema nuevo no hay ninguna columna de presión:
`worksheets` tiene `ambient_temp_c` y `ambient_humidity`, nada más
(`database/migrations/2026_07_28_100000_create_worksheets_tables.php:70`).
**Consecuencia:** para cromatografía de gases la presión es condición de
ensayo; un informe acreditado que la omitía antes y la omite ahora es una
regresión que ya venía, pero el viejo al menos la GUARDABA. Hoy el dato no se
puede registrar ni a mano.

### 2. Las 100 filas de bitácora diaria y su precarga

`cro_temperatures` y `fiq_temperatures` tienen **100 filas reales cada una** en
el volcado (`esquema/catalogos-definiciones.sql`), o sea que se usaban todos los
días. **Consecuencia:** el analista tipea las condiciones en cada hoja en vez de
cargarlas una vez por jornada, y no queda registro consultable "qué presión,
temperatura y humedad hubo el 14 de marzo" — que es justo lo que un auditor de
ISO 17025 pide ver.

### 3. Las 45 mediciones de control de calidad quedan sin migrar

`patron_tendences` tiene **27 registros** con los cinco límites de cada carta
(`esquema/lab_app_development-estructura.sql:369-421`), y la tabla destino
`qc_charts` está vacía hasta que alguien los tipee
(`database/migrations/2026_07_28_110000_create_qc_tables.php:65`).
**Consecuencia:** el control de calidad —la función que el nuevo sí construyó y
el viejo no tenía— arranca apagado. Y sin la desviación estándar, que el viejo
no guardaba, las reglas de Westgard no se disparan aunque se carguen los
límites.

### 4. El punto de muestreo dejó de ser catálogo

`transformer_points` (4 filas) era FK **en el equipo y en el informe**
(`:1055`, `:595`); hoy es `samples.sampling_point varchar(80)`.
**Consecuencia:** el punto de extracción cambia el resultado esperado de un
ensayo de aceite. Sin catálogo, comparar la tendencia de un transformador
"mismo punto contra mismo punto" depende de que nadie haya escrito "Superior",
"superior" y "SUP" en tres recepciones distintas.

### 5. Las 52 marcas de aceite y las 6 unidades de volumen, también

`transformer_oil_marks` tenía **52 filas normalizadas**;
`transformer_oil_units`, 6. Las dos son texto libre hoy.
**Consecuencia:** ya no se puede responder "cuántos equipos usan aceite Nynas"
sin normalizar a mano, y el volumen de aceite pierde la unidad comparable.

### 6. Importar el padrón de equipos llena 6 campos de 15

El escalonamiento viejo (`import_transformers`, `:130-152`) traía tipo de
equipo, marca, conmutador, preservación, tipo de aceite, marca de aceite,
unidad, volumen y antigüedad. `EquipmentImport` acepta `name`, `customer`,
`serial`, `tag`, `voltage_kv` y `power_mva`
(`app/Imports/BusinessManagement/Equipment/EquipmentImport.php:107-150`).
**Consecuencia:** una carga inicial de padrón deja nueve campos vacíos por
equipo, y completarlos a mano en cientos de equipos es exactamente lo que hace
que no se completen.

### 7. Falta un tipo de equipo del catálogo real

La base tiene **21** tipos; el seeder siembra **20**
(`database/seeders/LabCatalogsSeeder.php:57` el comentario, `:60-80` la lista). Falta `Regulador de Voltaje`.
**Consecuencia:** un equipo de ese tipo no se puede clasificar; y como la lista
se tomó de un comentario de una vista en vez del volcado, cualquier tipo que el
laboratorio haya agregado después de esa vista tampoco está.

### 8. Nadie avisa que falta un valor al armar el informe

`rem_report_detail_issues` registraba el parámetro faltante y mandaba correo con
el enlace (`labo_old/app/mailers/user_management/user_mailers.rb:34-38`).
**Consecuencia:** hoy un parámetro sin cargar sale en blanco en el informe y el
único control es que alguien lo note leyendo el PDF. La implementación vieja era
mala (escribía desde la vista, al renderizar), pero el control existía.

### 9. No se puede expresar la diferencia entre lo pactado y lo cargado

Las 15 columnas `num_fiq`…`num_pas` de `rems` (`:469-483`) eran la cantidad
comprometida por familia, y `qty_num_test` la cantidad de correlativos; el viejo
comparaba contra lo emitido (`labo_old/app/models/rem.rb:124-131`). En el nuevo
todo se deriva de `sample_tests`.
**Consecuencia:** si el cliente pactó 40 cromatografías y se cargaron 38, el
sistema nuevo no lo sabe: para él se pidieron 38. No es un problema de datos,
es que falta el dato del compromiso.

### 10. La auditoría perdió el agrupamiento del cambio

`audits` tenía `associated_id`/`associated_type` (el registro padre) y
`request_uuid` (todos los cambios de una misma petición); `audit_logs` no tiene
ninguno de los dos
(`database/migrations/2026_05_05_100000_create_audit_logs_table.php:11`).
**Consecuencia:** un guardado de informe que escribe seis tablas deja seis filas
sin relación entre sí, y no se puede reconstruir "todo lo que se tocó de esta
recepción" ni "esto lo hizo un solo clic".

### Fuera de esta lista, a propósito

Almacén (5 tablas), etiquetas con QR y los reportes gerenciales **no están acá**
porque ya tienen su lugar en [`../12-CHECKLIST.md`](../12-CHECKLIST.md) (C6, C7,
C8) y en el plan maestro como fases 10 y 11. La procedencia (`db_systems`) es la
decisión D2, no una ausencia por descuido. Y `rem_conditions`,
`rem_correlatives.qr_code` y `rem_reports.date_pue` están descartadas con
justificación escrita.

---

## Procedencia de este documento

| Qué se leyó | Para qué |
|---|---|
| `docs/migracion/esquema/lab_app_development-estructura.sql` (1805 líneas, 47 tablas) | El esquema viejo, columna por columna |
| `docs/migracion/esquema/catalogos-definiciones.sql` | Conteo de filas reales de 20 tablas |
| `labo_old/config/database.yml:36-49` y `labo_old/app/models/*.rb` (63 modelos) | Qué tabla vive en qué base; los dos modelos sin tabla |
| `labo_old/app/controllers/**` (11 módulos + 5 controladores sueltos) | Qué pantalla consumía cada tabla |
| `database/migrations/*.php` (83 tablas creadas explícitamente, más las 5 de Spatie) | El esquema nuevo |
| `app/Models/*.php` (70 modelos), `app/Imports/**`, `app/Services/Lab/**` | Quién lee cada columna |
| `app/Console/Commands/ImportLegacyTestsCommand.php` | Qué se migra de verdad y qué solo tiene estructura |
| `database/seeders/LabCatalogsSeeder.php`, `SamplersSeeder.php`, `SignaturesSeeder.php`, `data/standards.json` | Qué catálogos se siembran y con cuántas filas |
| `docs/migracion/11-AUDITORIA-VIEJO-VS-NUEVO.md`, `12-CHECKLIST.md`, `auditoria/A`, `B`, `D` | Para no repetir ni contradecir lo ya auditado |

**No se modificó ningún archivo de la aplicación.**
