# D — La placa del equipo: tensión, potencia y el formato con barras

> **Qué se audita.** Cómo viene la TENSIÓN (kV) y la POTENCIA (MVA) del equipo
> en el sistema Rails viejo, medido contra los DATOS REALES, para decidir cómo
> se tratan en LaboRep. El disparador es el formato con barras: `220/60/10`.
>
> **Con qué evidencia.** Un volcado real de producción de `lab_app_development`
> con 100 transformadores, 97 informes y 43 filas de importación. Los conteos de
> este documento salen de ahí, no de una impresión. Cada afirmación de código
> lleva archivo y línea.
>
> **Regla de lectura.** Donde dice "el viejo estaba mal" hay un número que lo
> respalda. Donde no hay número, se dice que no lo hay.

---

## 0. Lo que hay que saber, en una pantalla

| # | Hallazgo | Número |
|---|---|---|
| 1 | La tensión y la potencia del viejo son **texto libre** (`varchar(255)`), en las tres tablas donde viven | — |
| 2 | La mayoría de las placas **no es un solo número** | 187 de 240 valores de tensión (77.9%) traen `/` |
| 3 | **Nunca hay más de tres segmentos**, ni en tensión ni en potencia | 0 de 240 y 0 de 240 |
| 4 | Con dos columnas de tensión se perdía el terciario | 33 de 100 equipos |
| 5 | El orden de la TENSIÓN es convención (alta primero); el de la POTENCIA **no lo es** | tensión 70 desc / 3 asc · potencia 13 desc / **8 asc** |
| 6 | En 3 equipos la `/` **no separa devanados: divide** (`500/1.73` = 500/√3, reactores de 500 kV) | 3 de 100 |
| 7 | El "sin dato" se escribía como carácter (`-`, `0`) y el viejo lo convertía en **0 kV → banda ≤69 kV**, la más permisiva | 5 de 100 equipos · 9 de 97 informes |
| 8 | Ese 0 ya viajó a TrafoDex y sigue ahí | **100 de 2562** transformadores con `voltage_kv = 0` |
| 9 | El viejo re-parseaba el texto en **15 lugares** (8 para tensión, 7 para potencia) | — |
| 10 | En LaboRep, `voltage_class` y `power_rating` **no tienen ningún consumidor**: `SpecSetResolver` usa `voltage_kv_hv` crudo | 2 de 100 equipos caen en banda equivocada |
| 11 | El contrato con TrafoDex acepta las tres tensiones y toma el máximo, pero de potencia acepta **una sola** | 8 de 21 placas multi-potencia mandarían el escalón **más bajo** |

---

## 1. De dónde salen los datos: el viejo tiene DOS bases

Esto hay que decirlo antes de cualquier tabla de campos, porque explica por qué
el mismo dato tiene dos tipos distintos.

`config/database.yml:36-49` declara dos conexiones:

- **`primary` = `lab_app_development`** — la base del laboratorio (`labo_old`).
- **`primary2` = `tr_app_development`** — la base de TrafoDex, a la que el
  laboratorio escribía directo con cinco modelos puente
  (`app/models/transformer_trapp.rb:2-3`, `chromatographical_trapp.rb`,
  `physical_trapp.rb`, `furano_trapp.rb`, `customer_trapp.rb`).

**`db/schema.rb` de `labo_old` NO tiene la tabla `transformers`.** Ese archivo
(versión `2023_10_26_051386`) declara 17 tablas: accesos, auditoría, países,
clientes, los `lab_*`, perfiles, supervisores y usuarios. No hay `transformers`,
no hay `rem_reports`, y `db/migrate/` tampoco las crea (30 migraciones, ninguna
las menciona). El esquema versionado quedó atrás de la base real.

La estructura real de producción está en
`docs/migracion/esquema/lab_app_development-estructura.sql` (47 tablas), y es la
que manda:

| Tabla | Archivo:línea | Tensión | Potencia |
|---|---|---|---|
| `lab_app.transformers` | `esquema/lab_app_development-estructura.sql:1041-1062` | `num_ten varchar(255)` | `num_pot varchar(255)` |
| `lab_app.rem_reports` | `:566-610` | `num_ten varchar(255)` | `num_pot varchar(255)` |
| `lab_app.import_transformers` | `:130-152` | `num_ten varchar(255)` | `num_pot varchar(255)` |
| `tr_app.transformers` | `trafodex/docs/origen-ruby/fuentes-originales/tr_app_development_schema.sql` | **`num_vol decimal(10,5)`** | **`num_pot decimal(10,5)`** |
| `tr_app.import_transformers` | idem | `num_vol varchar(255)` | `num_pot varchar(255)` |

O sea: **el laboratorio guardaba texto y TrafoDex guardaba número.** El puente
entre los dos es una línea que colapsa el texto al máximo
(`app/controllers/trapp_management/import_transformers_controller.rb:154-155`):

```ruby
num_vol: array.num_ten.split('/').map(&:to_f).max,
num_pot: array.num_pot.split('/').map(&:to_f).max,
```

Consecuencia para la migración: **en TrafoDex las barras ya se perdieron.** Sus
2562 transformadores históricos tienen un decimal, no la placa. La placa completa
solo existe en la base del laboratorio. Si LaboRep va a ser la fuente de verdad
del equipo, la placa se recupera de `lab_app`, no de TrafoDex.

Nota de contexto sobre el mismo campo: en esa tabla, `age` y `oil_qty` **también**
son `varchar`, y en el volcado real traen `'-'` y `'0'` como valores. El texto
libre no fue una decisión sobre la tensión: fue el default de toda la tabla.

---

## 2. Tabla de campos: viejo → LaboRep → TrafoDex

| Concepto | `lab_app` (viejo) | `tr_app` (viejo) | LaboRep (`equipment`) | TrafoDex hoy (`transformers`) |
|---|---|---|---|---|
| Tensión, placa completa | `num_ten varchar(255)` `"220/60/10"` | — (se perdía) | `voltage_kv_hv`, `voltage_kv_lv`, `voltage_kv_tv` — `decimal(10,2)` nullable | — |
| Tensión, un número | — (se derivaba con `split`) | `num_vol decimal(10,5)` | accessor `voltage_class` = `max(hv,lv,tv)` | `voltage_kv decimal(10,2)` |
| Tensión, para imprimir | el texto crudo | — | accessor `voltage_label` = `"500 / 220 / 33"` (en `$appends`) | — |
| Potencia, placa completa | `num_pot varchar(255)` `"120/160/200"` | — | `power_mva`, `power_mva_2`, `power_mva_3` — `decimal(10,2)` nullable | — |
| Potencia, un número | — (se derivaba con `split`) | `num_pot decimal(10,5)` | accessor `power_rating` = `max(...)` | `power_mva decimal(10,2)` |
| Potencia, para imprimir | el texto crudo | — | accessor `power_label` | — |
| Fases | `num_fas int` (solo `tr_app`) | `num_fas int` | `phases int` (1..3) | `phases` texto de lista cerrada |
| Puente entre sistemas | — | — | `external_ref` (slug del transformer) | `serial` + `tag` |

Referencias de LaboRep: migración base
`database/migrations/2026_07_28_061051_create_equipment_table.php:78-80`;
terciario y escalones de potencia
`database/migrations/2026_07_29_170000_add_tertiary_ratings_to_equipment.php:31-34`;
accessors `app/Models/Equipment.php:96-171`; validación
`app/Http/Requests/BusinessManagement/Equipment/Concerns/EquipmentFieldRules.php:68-73`.

---

## 3. La evidencia numérica

### 3.1 Fuente y método

El volcado real de producción de `lab_app_development` (100 filas por tabla,
generado con phpMyAdmin). Se extrajeron todos los valores de `num_ten` y
`num_pot` de las tres tablas y se contaron los segmentos separados por `/`, los
caracteres que no son dígito ni punto, y el orden de los números.

Cobertura: **100 transformadores · 97 informes (`rem_reports`) · 43 filas de
importación**. En las tres tablas los dos campos están al 100% poblados: **cero
`NULL`, cero cadena vacía**. Cuando no hay dato, hay un carácter — que es
exactamente el problema.

### 3.2 Cuántos segmentos trae cada placa

**Tensión (`num_ten`)**

| Tabla | 1 segmento | 2 segmentos | 3 segmentos | más de 3 | n |
|---|---|---|---|---|---|
| `transformers` | 27 | 40 | 33 | **0** | 100 |
| `rem_reports` | 26 | 36 | 35 | **0** | 97 |
| `import_transformers` | 0 | 41 | 2 | **0** | 43 |
| **Total** | **53** | **117** | **70** | **0** | **240** |

**Potencia (`num_pot`)**

| Tabla | 1 segmento | 2 segmentos | 3 segmentos | más de 3 | n |
|---|---|---|---|---|---|
| `transformers` | 79 | 5 | 16 | **0** | 100 |
| `rem_reports` | 76 | 5 | 16 | **0** | 97 |
| `import_transformers` | 31 | 12 | 0 | **0** | 43 |
| **Total** | **186** | **22** | **32** | **0** | **240** |

Lectura: la tensión es multi-valor en **77.9%** de los casos (187/240) y la
potencia en **22.5%** (54/240). La tensión de tres devanados es frecuente (70 de
240, 29.2%); la potencia de tres escalones es menos común pero nada excepcional
(32 de 240, 13.3%).

### 3.3 Respuesta directa: ¿hay casos reales de más de tres segmentos?

**No. Cero, en 480 valores medidos (240 de tensión + 240 de potencia), en las
tres tablas.** El máximo observado es 3 en tensión y 3 en potencia.

Un solo valor en todo el volcado esconde un cuarto número, y **no es un cuarto
devanado**: `'22.9/0.46-0.23'` (transformador de distribución id 2263, cliente
HACIS, y su informe `REP-LAB-2026-0479`). Es un secundario de doble tensión —
460-230 V — escrito con guion. Son tres devanados con la baja conmutable, y los
tres números caben en los tres campos que ya existen.

### 3.4 Los separadores y las anomalías, con nombre y apellido

| Anomalía | `transformers.num_ten` | `transformers.num_pot` | `rem_reports.num_ten` | `rem_reports.num_pot` | `import_transformers` |
|---|---|---|---|---|---|
| barra `/` (ocurrencias) | 106 | 37 | 106 | 37 | 45 ten / 12 pot |
| guion `-` | 5 | 6 | 9 | 8 | 0 |
| espacios alrededor de la barra | 1 | 5 | 1 | 6 | 2 (pot) |
| coma decimal | 0 | 0 | 0 | 0 | 0 |
| unidad pegada (`kV`, `MVA`) | **0** | **0** | **0** | **0** | **0** |
| valor `'0'` | 1 | 3 | 1 | 2 | 0 |
| decimal con cero final (`0.380`, `0.460`) | 4 | 2 | 1 | 2 | 1 |

Detalle de lo que un parser estricto rechazaría hoy:

- **`transformers`**: 5/100 en tensión (`'-'` ×4, `'22.9/0.46-0.23'` ×1) y 6/100
  en potencia (`'-'` ×6).
- **`rem_reports`**: 9/97 en tensión (`'-'` ×8, `'22.9/0.46-0.23'` ×1) y 8/97 en
  potencia (`'-'` ×8).
- **`import_transformers`**: 0. La planilla que llena el cliente viene limpia.

Espacios: `'30 / 0.65'`, `'200 /160 /120'` (×3), `'200 /160/120'`, `'12.5 / 16'`,
`'40 / 20'`, `'36 / 45'`, `'48 / 60'`.

**Buena noticia medida, no supuesta: nadie escribió la unidad dentro del campo.**
Cero valores con letras en los 480 medidos. La etiqueta del formulario ya decía
"Tensión (Kv)" y "Potencia (MVA)"
(`app/views/im_management/transformers/partials/_form_new.html.erb:53,64`) y eso
alcanzó. Contrasta con `oil_qty`, donde la unidad sí se metió adentro y por eso
LaboRep la separó en `oil_volume` + `oil_volume_unit`
(`app/Models/Equipment.php:47-53`).

### 3.5 El orden NO es el mismo en tensión y en potencia

| Campo | Descendente (mayor primero) | Ascendente | Desordenado |
|---|---|---|---|
| `transformers.num_ten` | 70 | **3** | 0 |
| `rem_reports.num_ten` | 68 | **3** | 0 |
| `import_transformers.num_ten` | 43 | 0 | 0 |
| `transformers.num_pot` | 13 | **8** | 0 |
| `rem_reports.num_pot` | 14 | **7** | 0 |
| `import_transformers.num_pot` | **0** | **12** | 0 |

- **La tensión sí tiene convención**: 181 de 187 placas multi-valor van de mayor
  a menor (96.8%). Las excepciones son `'23/138'` (×2, elevador) y `'138/145'`
  (×1, probablemente una toma de regulación).
- **La potencia no tiene ninguna.** En `transformers` van 13 descendentes contra
  8 ascendentes, y en la planilla que llena el cliente
  (`import_transformers.num_pot`) son **12 de 12 ascendentes**. Es esperable: los
  escalones de refrigeración se escriben en el orden ONAN/ONAF/OFAF, que sube:
  `'120/160/200'`, `'90/120/150'`, `'10/12.5'`, `'3/3.75'` (×5), `'18/24'`,
  `'19/24'`, `'36 / 45'`, `'48 / 60'`. Y también hay descendentes reales
  (`'200 /160 /120'` ×3, `'360/360/200'`, `'65/50/15'`, `'40/40/12'`,
  `'40 / 20'`), o sea que ni siquiera es la convención inversa: es que no hay
  convención.

Consecuencia práctica: **el primer segmento de la potencia puede ser el escalón
más chico, y en la fuente que más importa —el archivo del cliente— lo es
siempre.** Cualquier código que asuma "campo 1 = valor nominal más alto" en
potencia está mal contra los datos.

### 3.6 La barra que no separa: `500/1.73`

Tres de los 100 transformadores (y sus tres informes) traen
`num_ten = '500/1.73'`. Son **reactores** (`transformer_type_id = 11`) de Red de
Energía del Perú, subestación La Niña, año 2012, 50 MVA
(ids 77, 97, 98 · informes `REP-LAB-2026-0542/0547/0548`).

`1.73` es √3. `500/1.73` es la tensión de fase de un sistema de 500 kV
(≈288.7 kV), no un devanado de 1.73 kV. **Un splitter ciego escribiría
`voltage_kv_lv = 1.73`, que es ficción**, y el informe imprimiría
"500 / 1.73 kV" como si fuera la placa.

Para la banda IEEE da igual (500 y 288.7 caen en ≥230 kV), pero para lo que se
imprime en un papel firmado, no.

### 3.7 El "sin dato" que se convertía en la banda más permisiva

En el viejo, `'-'.split('/').map(&:to_f).max` da **0.0**, y `'0'` da 0.0 igual.
Después `@num_ten = @num_ten_info.to_i` (`app/models/rem_report.rb:191`) y el
primer `if` es `@num_ten <= 69` (`:249`, `:287`).

**Un equipo sin tensión registrada se juzgaba con los límites de ≤69 kV**, que
son los más laxos del cuadro (rigidez mínima 40 kV contra 50 y 60 en las bandas
altas, ver `app/models/rem_report.rb:287-333`). No es un dato faltante: es un
resultado que aprueba cuando debería reprobar.

| Tabla | Filas con tensión efectiva 0 | % |
|---|---|---|
| `transformers` | 5 (`'-'` ×4, `'0'` ×1) | 5.0% |
| `rem_reports` | 9 (`'-'` ×8, `'0'` ×1) | 9.3% |

**Y ese 0 ya viajó.** En el volcado histórico de TrafoDex
(`trafodex/database/seeders/data/transformers_legacy.sql`, 2562 filas, 117
tensiones distintas) hay **100 filas con `num_vol = 0` y ninguna con `NULL`**.
En TrafoDex, `FiquisDiagnosisService::voltageClass()`
(`app/Services/Diagnostics/FiquisDiagnosisService.php:483-499`) manda `0` a la
clase `low` — y `null` también. Es decir: el carácter `-` de una planilla de 2024
sigue hoy poniendo 100 transformadores en la banda fisicoquímica más permisiva de
TrafoDex.

Dato adicional del puente: `build_transformer_trapp`
(`trapp_management/import_transformers_controller.rb:149-167`) creaba cada
transformador con `num_health: 0, state_health: "Muy Malo", color_health: "red"`.
Es el origen documentado de los "580 nunca diagnosticados por el viejo (HI 0
default)" que registra el CLAUDE.md de TrafoDex.

### 3.8 El truncamiento a entero

`@num_ten = @num_ten_info.to_i` (`rem_report.rb:191`,
`rem_report_detail.rb:192`, `rem_report.rb:447`, `rem_report_detail.rb:395`)
tira los decimales antes de comparar. Y hay comparaciones contra 72.5
(`rem_report.rb:353,368` — ésteres vegetal y sintético). Un equipo de 72.5 kV
entra como 72 y cae en la banda "≤72.5"; uno de 69.5 kV entra como 69 y cae en
"≤69". En el volcado real hay 3 equipos de 72.5 kV en TrafoDex
(`transformers_legacy.sql`) — el caso existe. No es hipotético.

---

## 4. Los caminos de entrada y qué hacen hoy

### 4.1 En el viejo

| # | Camino | Archivo:línea | Qué hace |
|---|---|---|---|
| 1 | Alta de equipo, a mano | `views/im_management/transformers/partials/_form_new.html.erb:55,65` | `f.text_field :num_ten` — **campo de texto**, con `data-parsley-required="true"` y nada más |
| 2 | Edición de equipo | `_form_edit.html.erb:55,65` | idem |
| 3 | Ficha (solo lectura) | `_form_show.html.erb:55,65` | idem, `readonly` |
| 4 | Datos del informe, alta | `views/im_management/rem_reports/partials/_form_new_data_transformer.html.erb:49,59` | `text_field_tag`, copia el texto del equipo al informe |
| 5 | Datos del informe, edición | `_form_edit_data_transformer.html.erb:49,59` | idem |
| 6 | Importación Excel al staging | `app/models/import_transformer.rb:43-44` | `column4.to_s`, `column5.to_s` — el texto de la celda, tal cual |
| 7 | Importación Excel directa | `app/models/transformer.rb` (`self.import`) | `:num_ten => column4` — sin `to_s` siquiera |
| 8 | Puente a TrafoDex | `trapp_management/import_transformers_controller.rb:154-155` | `split('/').map(&:to_f).max` |

**Validación: prácticamente ninguna.**

- Servidor: `app/models/transformer.rb` valida **solo** unicidad de
  `num_tag` con scope `num_serie` (`:24`). El `validates_presence_of` que incluía
  `:num_ten, :num_pot` **está comentado** (`transformer.rb:27`).
- Controlador: `params.require(:transformer).permit!`
  (`app/controllers/im_management/transformers_controller.rb:146`) — pasa todo.
- Cliente: `data-parsley-required` sin `data-parsley-type="number"` ni patrón.
  Solo exige que no esté vacío. Por eso `'-'` es un valor legal: satisface
  "requerido".

Comparación interna que lo prueba: en el mismo repositorio, cuando querían un
número, lo pedían — `data-parsley-type="number"` en
`views/pr_management/templates/lab_details/partials/_form_edit_nested_poli.html.erb:12`.
En la placa no lo pusieron.

### 4.2 Cómo se imprimía y cómo se calculaba

**Se imprimía crudo.** En la cabecera del informe
(`views/im_management/rem_reports/partials/_report_main_info.erb:60,68`):

```erb
<td class="border border-dark"><%= @main_model.num_ten rescue "-" %></td>
<td class="border border-dark"><%= @main_model.num_pot rescue "-" %></td>
```

El papel salía con `"220/60/10"` tal cual, incluido el `'-'` de los que no tenían
dato. Bien para fidelidad de placa; mal porque no había nada que garantizara que
ese texto fuera interpretable.

**Se colapsaba al máximo para mostrar un número** en tres vistas:

- `views/im_management/rem_reports/partials/_form_add_details.html.erb:26,30` —
  `<h3><%= @main_model.num_ten.split('/').map(&:to_f).max %> Kv</h3>`
- `views/im_management/rems/partials/_table_rem_reports.html.erb:60,61`
- `views/trapp_management/import_transformers/partials/_form_step3.html.erb:73,74`
  (la vista previa de lo que se iba a mandar a TrafoDex)

**Se calculaba la clase de tensión para el criterio IEEE C57.106.** Cuatro
métodos, dos modelos:

| Método | Archivo:línea | Qué deriva |
|---|---|---|
| `RemReport#refresh_orientation_fiqui_values` | `app/models/rem_report.rb:180-196` | `@num_ten` y `@num_pot` desde el texto |
| `RemReport#refresh_orientation_croma_values` | `:435-450` | idem |
| `RemReportDetail#set_orientation_fiqui_values` | `app/models/rem_report_detail.rb:181-193` | idem |
| `RemReportDetail#set_orientation_croma_values` | `:383-399` | idem |

Y con `@num_ten` se elegía el cuadro de límites (`aci_ori`, `f25_ori`, `rig_ori`,
`ten_ori`, `agu_ori`, …), con estos cortes:

- **Conmutador** (`transformer_type_id == 10`): `<= 69` / resto
  (`rem_report.rb:249`, `rem_report_detail.rb:198`).
- **Mineral**: `<= 69` / `> 69 && < 230` / `>= 230`
  (`rem_report.rb:287,302,317`; `rem_report_detail.rb:235,250,265`).
- **Éster vegetal y sintético** (`oil_type_id` 5 o 7): `<= 72.5` /
  `> 72.5 && < 170` / `>= 170` (`rem_report.rb:353,368,383`;
  `rem_report_detail.rb:301,316,331`).

**Total: 15 ocurrencias de `split('/')` sobre la placa** — 8 para la tensión
(4 métodos de modelo + el puente a TrafoDex + 3 vistas) y 7 para la potencia.
Los documentos internos de LaboRep dicen "cinco lugares"
(`docs/migracion/02-MODELO-DE-DATOS.md:76`, `app/Models/Equipment.php:92`,
`.../add_tertiary_ratings_to_equipment.php:11`): **son quince.** Conviene
corregirlo, porque el argumento es más fuerte con el número real.

### 4.3 En LaboRep (estado actual)

| Camino | Archivo | Comportamiento |
|---|---|---|
| Formulario de equipo | `resources/js/Pages/Equipment/Form.vue:322-362` | Seis `InputNumber` (`:min="0"`). **No acepta la cadena pegada**: pegar `220/60/10` en un `InputNumber` de Ant Design no deja nada |
| Validación | `.../Concerns/EquipmentFieldRules.php:68-73` | `nullable numeric min:0 max:2000` (tensión) y `max:10000` (potencia). Cotas correctas |
| Ficha | `resources/js/Pages/Equipment/Show.vue:121-126` | Imprime `voltage_label` / `power_label` |
| Índice | `resources/js/Pages/Equipment/config/columns.js:22-23` | Dos columnas, `voltage_label` y `power_label` |
| Informe de ensayo | `resources/views/lab_management/reports/test_report.blade.php:305-320` | Usa `voltage`/`power` del snapshot, y si el snapshot es viejo rearma la placa con `voltage_hv` + `voltage_lv` (sin terciario) |
| Payload del informe | `app/Services/Lab/TestReportPayload.php:193-198` | Manda `voltage`, `power`, `voltage_hv/lv/tv` y `power_mva` |
| Importación de equipos | `app/Imports/BusinessManagement/Equipment/EquipmentImport.php:16-20` | Cuatro columnas: `name`, `customer`, `serial`, `tag`. **No mapea tensión ni potencia** |
| Plantilla de importación | `.../EquipmentImportTemplate.php:35` | `['name','customer','serial','tag']` — tampoco las ofrece |
| Exportación de equipos | `.../EquipmentExport.php:50-62` | 11 columnas, **ninguna de placa**. Incluye `sort_order`, que no existe en la tabla |
| Parseo de barras | — | **No existe en ningún camino.** Lo único que hace `explode('/')` es el código ISO 4406 de partículas (`app/Services/Lab/DiagnosisTextService.php:414`), que es otro concepto |

### 4.4 Tres hallazgos concretos en LaboRep y TrafoDex

**(a) `voltage_class` y `power_rating` no tienen ningún consumidor.**

`grep` de `voltage_class|power_rating` sobre `app/` y `resources/js/`: **cero
resultados fuera del test** (`tests/Unit/Models/EquipmentNameplateTest.php`). El
accessor está documentado como "la banda que elige el cuadro de límites"
(`app/Models/Equipment.php:88-96`) y el test verifica que incluye el terciario,
pero el único lugar que resuelve el cuadro usa la columna cruda
(`app/Services/Lab/SpecSetResolver.php:137-142`):

```php
if (! $this->withinRange($set->voltage_from, $set->voltage_to, $equipment?->voltage_kv_hv)) {
    return false;
}
if (! $this->withinRange($set->power_from, $set->power_to, $equipment?->power_mva)) {
    return false;
}
```

Impacto medido sobre los 100 equipos reales: si se tipean en el orden de la placa,
la banda por máximo y la banda por primer campo discrepan en **2 de 100** (las dos
filas `'23/138'`, que por máximo caen en 69-230 kV y por primer campo en ≤69 kV).
Distribución por máximo: 39 en ≤69 · 26 en 69-230 · 31 en ≥230 · 4 sin dato. Por
primer campo: 41 · 24 · 31 · 4.

Dos de cien es poco, pero es la misma clase de error que el viejo cometía, y en
potencia es mucho peor: **8 de 21 placas multi-potencia tienen el escalón más bajo
en el primer campo**, y en la planilla del cliente son 12 de 12.

**(b) El contrato con TrafoDex acepta tres tensiones y una sola potencia.**

`StoreLabTransformerRequest` (`trafodex/app/Http/Requests/Api/V1/StoreLabTransformerRequest.php:61-67`)
declara `voltage_kv`, `voltage_kv_hv`, `voltage_kv_lv`, `voltage_kv_tv` — y
`power_mva`, solo. `LabTransformerService::resolveVoltage()`
(`:280-288`) toma el máximo de las cuatro claves de tensión, con la justificación
correcta en `docs/API-LABORATORIO.md:321-331`. Pero
`LabTransformerService::create()` (`:167`) hace:

```php
'power_mva' => $data['power_mva'] ?? null,
```

Sin máximo y sin las claves 2 y 3. Si LaboRep manda la placa tal como está
tipeada, TrafoDex recibe el primer escalón — el **más bajo** en 8 de 21 casos
reales. El doc de la API no trata el caso de la potencia (solo el de la tensión,
§5.2). Hoy no rompe nada porque **LaboRep todavía no tiene cliente de
sincronización** (solo la columna puente `external_ref`), pero es una decisión que
hay que tomar antes de escribirlo, no después.

**(c) `EquipmentExport` ofrece `sort_order`, columna que la migración excluye a
propósito.** `.../EquipmentExport.php:55`. Sale vacía por el `?? ''`, así que no
falla; es residuo del scaffold. Se menciona porque, si se agregan las columnas de
placa al export, es el momento de sacarla.

---

## 5. Propuesta

Cinco decisiones, cada una con el número que la sostiene.

### 5.1 Qué se acepta al tipear: los seis números, MÁS un campo de pegado

**Se mantienen los seis `InputNumber`** como único destino del dato. Y se agrega,
arriba de ellos, **un campo de texto de un solo uso, "Pegar placa"**, que parte la
cadena y llena los seis. No se guarda: rellena y se vacía.

Por qué hace falta:

- El operador tiene la chapa (o la planilla del cliente) escrita como
  `"220/60/10"` y `"120/160/200"`. Es el formato en que el dato existe en el mundo:
  **77.9% de las tensiones reales vienen así**.
- Hoy pegar eso en el formulario **no deja nada**: `InputNumber` descarta la
  cadena. La alternativa actual es que el operador la parta a mano, seis veces por
  equipo, y ese es precisamente el trabajo manual que produce los `'-'` y los
  `'0'` del volcado viejo.
- No es un campo nuevo en la base. Es una ayuda de tipeo, del lado del cliente,
  con la misma función expuesta al importador del lado del servidor.

Qué acepta el parser, derivado de lo que aparece en los datos reales:

| Entrada | Se acepta | Por qué |
|---|---|---|
| `220/60/10` | sí | el caso normal, 187 de 240 |
| `30 / 0.65`, `200 /160 /120` | sí, se limpian espacios | 6 casos reales |
| `220` | sí, un solo valor | 53 de 240 |
| `35.5/0.380`, `4.16/0.460` | sí, cero final se normaliza a `0.38` / `0.46` | 6 casos reales; `voltage_label` ya recorta ceros |
| `22.9/0.46-0.23` | sí — el guion entre números es otro segmento | 1 caso real; da 3 valores, entran en hv/lv/tv |
| `220 kV`, `50 MVA` | sí, se descarta el sufijo de unidad | **0 casos reales**, pero cuesta una línea y evita el rechazo de un pegado obvio |
| `220,5` | sí, coma decimal → punto | 0 casos reales; es el separador decimal del teclado local |
| `500/1.73` | **no automático**: se llena solo `500` y se avisa | 3 casos reales; `1.73` es √3, no un devanado |
| `-`, `0`, vacío | **no**: los seis campos quedan en `null` | 14 casos reales; ver 5.3 |
| más de 3 segmentos | **no**: se avisa y no se llena nada | **0 casos reales**; si algún día aparece, tiene que verse, no adivinarse |

La regla del `1.73`: si un segmento es `1.73`, `1.732` o `√3`, **no es un
devanado**. Se llena el primer valor y se muestra un aviso con las dos lecturas
posibles (tensión de sistema 500 kV, tensión de fase 288.7 kV) para que el
operador elija. Nunca se escribe `1.73` en `voltage_kv_lv`.

### 5.2 Cómo se parte: la tensión se ordena, la potencia NO

Ésta es la decisión que los datos definen sin ambigüedad.

- **Tensión → se ordena descendente** en `voltage_kv_hv`, `voltage_kv_lv`,
  `voltage_kv_tv`. Los tres campos son ROLES ("alta", "baja", "terciario", ver
  `resources/lang/es/equipment.php:26-28`), y 181 de 187 placas reales (96.8%) ya
  vienen de mayor a menor. Para los 3 elevadores (`'23/138'`, `'138/145'`)
  ordenar es lo CORRECTO, no una licencia: en un elevador de 23 a 138 kV, la alta
  es 138. El campo dice "alta" y tiene que tener la alta.
- **Potencia → se guarda en el orden tipeado**, sin ordenar. Los tres campos son
  POSICIONALES ("Potencia", "Potencia 2", "Potencia 3", `equipment.php:29-31`), y
  eso es correcto porque el orden de la placa es la secuencia de refrigeración
  ONAN/ONAF/OFAF. En los datos reales la potencia sube tanto como baja
  (13 desc / 8 asc), y en la planilla del cliente **sube en 12 de 12**. Ordenarla
  destruiría información que el papel sí tiene.

Esta asimetría no es un caprichoso: es la razón de que los nombres de los campos
ya sean distintos (roles para tensión, posiciones para potencia). Los nombres
actuales están bien; lo que falta es que el parseo los respete.

### 5.3 Qué se guarda: seis columnas numéricas, `null` cuando no hay dato

- **Solo los seis decimales.** No se agrega una columna de texto con la placa
  cruda. Guardar el texto es volver al problema: 15 lugares tuvieron que
  re-parsearlo, y mientras exista alguien lo va a leer.
- **`null`, nunca `0`.** Es la corrección más importante de toda la auditoría:
  5% de los equipos y 9.3% de los informes del viejo tenían tensión efectiva 0, y
  eso los mandaba a la banda ≤69 kV, la más permisiva. `SpecSetResolver::withinRange()`
  (`app/Services/Lab/SpecSetResolver.php:148-157`) ya lo hace bien —
  `$value === null` devuelve `false`, o sea "el cuadro no aplica", y el resultado
  sale sin criterio en vez de aprobar con el criterio equivocado. Lo que hay que
  garantizar es que **el parser jamás escriba 0**: `'-'` y `'0'` producen `null`,
  no cero.
- **El texto original se conserva en la migración del histórico, no en una
  columna viva.** Cuando se corra el ETL de `lab_app.transformers`, el `num_ten` /
  `num_pot` original va al registro de auditoría de la fila creada (o a un informe
  de migración), para que cualquier discrepancia futura se pueda rastrear al
  carácter exacto que la produjo. Las ~14 filas que el parser rechace se listan
  para carga manual: son 14, no 1400.

### 5.4 Cómo se muestra

Lo que ya hay está bien y no se toca:

- `voltage_label` / `power_label` en el MODELO (`app/Models/Equipment.php:118-171`),
  en `$appends`, de modo que índice, ficha, informe y exports imprimen lo mismo.
  Ésa es la respuesta al problema del viejo, donde tres vistas colapsaban al
  máximo cada una por su cuenta.
- El recorte de ceros (`500` y no `500.00`, pero `4.16` intacto,
  `Equipment::placa()`) reproduce cómo lo lee el papel.

Lo que falta:

1. **Las dos columnas de placa en `EquipmentExport`** (`voltage_label`,
   `power_label`, y opcionalmente los seis números para que el export sea
   re-importable). Hoy el export no las tiene: un laboratorio que exporta su
   catálogo de equipos pierde la placa. De paso, quitar `sort_order`.
2. **Las seis columnas en la plantilla y el importador de equipos**
   (`EquipmentImportTemplate`, `EquipmentImport`), aceptando además una columna
   `voltage` / `power` con la cadena pegada, parseada con las mismas reglas del
   5.1 y con las filas no interpretables reportadas como error en la vista previa
   (el importador ya tiene ese canal). Sin esto, cargar 500 equipos obliga a
   tipear 3000 números a mano — y el volcado viejo muestra qué pasa cuando se
   obliga a eso.
3. **El rearmado del snapshot viejo en el informe debe incluir el terciario**
   (`resources/views/lab_management/reports/test_report.blade.php:318` pasa solo
   `voltage_hv` y `voltage_lv`; y `:320` solo `power_mva`). Con 33% de las placas
   de tres devanados, un informe congelado se reimprime incompleto.

### 5.5 ¿Tres campos alcanzan?

**Sí, y está medido: 0 de 240 valores de tensión y 0 de 240 de potencia superan
tres segmentos.** El único cuarto número del volcado (`22.9/0.46-0.23`) es un
secundario de doble tensión, y sus tres números entran en los tres campos.

No hace falta una tabla hija de devanados, y no conviene:

- Serviría al 0% de los equipos reales y le agregaría una pantalla, un
  `hasMany`, un orden y una validación de unicidad al 100% de ellos.
- La banda de tensión, que es lo que decide el cuadro de límites, se resuelve con
  un `max()` sobre tres columnas. Con una tabla hija es una subconsulta.
- Si algún día aparece un cuarto devanado real, agregar una columna es **una**
  migración de tres líneas — exactamente la que ya se hizo para el terciario
  (`2026_07_29_170000_add_tertiary_ratings_to_equipment.php`). Es más barato que
  mantener una tabla hija durante los años en que nadie la necesita.

Lo que sí hay que arreglar para que tres alcancen de verdad:

| Cambio | Dónde | Justificación numérica |
|---|---|---|
| `SpecSetResolver::applies()` debe usar `voltage_class` (máximo) y `power_rating` (máximo), no `voltage_kv_hv` / `power_mva` | `app/Services/Lab/SpecSetResolver.php:137-142` | 2 de 100 equipos caen en banda de tensión equivocada; 8 de 21 placas multi-potencia tienen el escalón más bajo en el primer campo |
| El contrato con TrafoDex tiene que aceptar `power_mva_2` y `power_mva_3` y tomar el máximo, **o** LaboRep tiene que mandar `power_rating` | `trafodex/app/Http/Requests/Api/V1/StoreLabTransformerRequest.php:67` y `LabTransformerService::create():167` | La tensión ya toma el máximo y está documentado (`API-LABORATORIO.md:321-331`); la potencia no. Es la misma decisión, a medias |
| Corregir "cinco lugares" por "quince" | `docs/migracion/02-MODELO-DE-DATOS.md:76`, `app/Models/Equipment.php:92`, `.../add_tertiary_ratings_to_equipment.php:11` | Son 8 sitios para tensión y 7 para potencia |

Y una advertencia que no es de LaboRep pero conviene dejar escrita: **TrafoDex
tiene 100 de 2562 transformadores con `voltage_kv = 0`**, heredados del `'-'` del
viejo, y su `FiquisDiagnosisService::voltageClass()`
(`app/Services/Diagnostics/FiquisDiagnosisService.php:483-499`) manda el 0 a la
clase `low`, igual que el `null`. Mientras esas filas no se corrijan, esos 100
equipos se siguen juzgando con los umbrales fisicoquímicos más permisivos del
IEEE C57.106 — y ponerlas en `null` no alcanza, porque `null` va a `low`
también. Es un pendiente del lado de TrafoDex: "sin tensión" debería ser "sin
criterio", no "banda baja".

Del lado del contrato, la API de TrafoDex sí exige que llegue alguna tensión
(`required_without_all:voltage_kv_hv,voltage_kv_lv,voltage_kv_tv` en
`StoreLabTransformerRequest.php:61`), así que un alta por API no puede crear otro
transformador con tensión vacía. El problema es del histórico ya cargado, no de
lo que entre de ahora en adelante.

---

## Anexo: cómo reproducir los conteos

Fuente: volcado de producción de `lab_app_development` con 100 filas por tabla
(phpMyAdmin, `SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"`). Tablas usadas:
`transformers` (100 filas), `rem_reports` (97), `import_transformers` (43).

Procedimiento: por cada `INSERT INTO`, tokenizar la tupla respetando las comillas
escapadas, tomar los valores de las columnas `num_ten` y `num_pot`, y contar
(a) `value.count('/') + 1` para los segmentos, (b) los caracteres que no son
dígito ni punto para los separadores, y (c) el orden de los números extraídos con
`\d+(?:\.\d+)?` para la dirección.

Volcado histórico de TrafoDex:
`trafodex/database/seeders/data/transformers_legacy.sql`, 2562 filas, columnas
`num_vol` / `num_pot` `decimal(10,5)`; 117 tensiones distintas; 100 filas con
`num_vol = 0`; 0 con `NULL`.
