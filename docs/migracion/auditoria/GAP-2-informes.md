# GAP-2 — Informes y firmas: lo que el sistema viejo hace y el nuevo no

> **Alcance.** El bloque de INFORMES Y FIRMAS del sistema Rails de 2019
> (`labo_old`): los cinco controladores (`rem_reports`, `rem_report_details`,
> `rem_signatures`, `rem_user_signatures`, `reports`), sus cuatro modelos
> (`rem_report.rb`, `rem_report_detail.rb`, `rem_signature.rb`,
> `rem_user_signature.rb`) y sus vistas — 84 parciales de `rem_reports/partials`
> más 9 vistas de `rem_reports`, 23 vistas de los otros tres módulos, el layout
> del PDF (`layouts/pdf.html.erb`) y su pie (`layouts/_pdf_footer.erb`) — contra
> `SampleReportController`, `TestReportController`, los servicios de
> `app/Services/Lab/`, los dos PDF de `resources/views/lab_management/reports/`,
> `config/legacy_report.php` y `database/seeders/data/diagnosis_templates.json`.
>
> **Archivos del viejo revisados: 126** (5 controladores + 4 modelos + 93 vistas
> de `rem_reports` + 23 vistas de `rem_signatures`, `rem_user_signatures` y
> `reports` + `config/routes.rb`).
>
> **Qué NO se repite acá.** Todo lo que
> [`../12-CHECKLIST.md`](../12-CHECKLIST.md) ya cerró o ya declaró pendiente con
> su porqué: A1 (límites de detección), A4 (bandas), A5 (`{value}`), A7
> (descargo legal en todas las hojas), A8 (azufres en una hoja), A9 (los cinco
> furanos), A10 (idioma congelado del autodiagnóstico), C1 (bitácora de
> condiciones), C4 (relaciones de cromatografía), C6 (etiquetas), C8 (reportes
> gerenciales) y C10 (las dos plantillas de exportación). Cuando un hueco de
> abajo toca uno de esos puntos, se dice explícitamente y se marca **DECIDIDO**.
>
> **Un aviso sobre A3.** El checklist da por hecho (`[x]`) que las quince
> familias tienen su plantilla «con los textos copiados tal cual» del sistema
> anterior. Contra los ERB, eso es cierto en once familias y **falso en tres**:
> cromatografía, fisicoquímico (parcialmente) y PCB. Los huecos 1, 2 y 3 son
> exactamente eso.

---

## Tabla resumen

| # | Qué falta | Clasificación | Consecuencia |
|---|---|---|---|
| 1 | El texto de cromatografía del ANÁLISIS DE RESULTADOS no es el del viejo: frases nuevas que el laboratorio nunca firmó | **AUSENTE** | El informe afirma «no se evidencia actividad de falla incipiente» y recomienda repetir el ensayo, dos opiniones que ninguna persona del laboratorio redactó ni aprobó |
| 2 | Cromatografía y fisicoquímico: la norma citada y las variantes por tipo de aceite y tipo de equipo | **AUSENTE** | Una muestra de silicona o de éster sale citando la norma del aceite mineral, y un conmutador recibe un diagnóstico de valores típicos que su aceite no tiene |
| 3 | PCB: la banda de 2 ppm, la cita del valor medido, la norma IEC 60422-2024 y el párrafo del D.S. N°018-2025-SA | **AUSENTE** | Se pierde la única afirmación con efecto legal del informe (si el aceite está o no dentro de la concentración peligrosa del decreto peruano) |
| 4 | Las tres columnas AROCLOR 1242 / 1254 / 1260 de la hoja de PCB | **AUSENTE** | El cliente recibe el total de PCB sin el desglose por congénere que el papel viejo imprimía |
| 5 | Las filas «ASTM D130» de azufre 1275B y 62535 48 h | **AUSENTE** | La clasificación de la lámina de cobre, que es la mitad del ensayo, no sale impresa |
| 6 | La hoja de METALES no se imprime nunca en el PDF clásico: la clave de `config/legacy_report.php` dice `metales` y la familia real es `metales_en_aceite` | **AUSENTE** | El día que el laboratorio declare los ocho metales, el PDF clásico seguirá saliendo sin esa hoja y sin ningún error visible |
| 7 | El valor de orientación editable por informe (los 33 campos `*_ori`) | **AUSENTE** | Cuando el cuadro automático no acierta, el analista ya no puede corregir el límite impreso: el papel sale con un valor de orientación equivocado o con raya |
| 8 | La norma de referencia por familia, derivada del aceite y elegible por informe | **PARCIAL** | En el PDF clásico está clavada en `IEEE C57.106-2015` y `IEC 60599-2022`: toda muestra que no sea mineral cita una norma que no le corresponde |
| 9 | La «Fecha de Análisis» del PDF clásico | **PARCIAL** | Imprime la fecha en que se generó el PDF, no la fecha en que se corrió el ensayo: reimprimir un informe de marzo en agosto dice que se analizó en agosto |
| 10 | La «Fecha de Emisión» impresa, en los dos PDF | **AUSENTE** | El formulario deja editar `issued_at` y ningún papel lo imprime: los dos muestran la fecha de generación, así que la reimpresión de un informe emitido contradice al que el cliente tiene en la mano |
| 11 | El firmante elegido POR INFORME | **PARCIAL** | El nuevo estampa a todos los firmantes activos del workspace en todos los informes: no queda registro de quién firmó ese papel en concreto |
| 12 | La evidencia de envío al cliente (`customer_evidence`, texto rico por informe) | **AUSENTE** | Se pierde el lugar donde el laboratorio dejaba constancia de la entrega (correo, cargo, acuse) |
| 13 | Los sub-títulos por método de la hoja de azufre | **PARCIAL** | Las tres sub-tablas rotuladas se aplanan en una sola lista: se pierde la separación entre el ensayo de 48 h y el de 72 h |
| 14 | El rótulo «LIMITE REFERENCIAL» de la hoja de DBDS | **PARCIAL** | La columna sale rotulada «VALOR DE ORIENTACIÓN (*)» con un asterisco que no lleva a ninguna nota |
| 15 | El inhibidor solo se diagnostica en aceite mineral | **AUSENTE** | El viejo lo diagnosticaba también en éster sintético y éster natural; hoy esas muestras salen con el párrafo en blanco |
| 16 | La exportación a Excel del listado de informes | **AUSENTE** | Ver C8: la pantalla existe y se puede filtrar, pero no hay forma de bajarla |
| 17 | El formato `.doc` del informe | **DECIDIDO** | Era HTML con otra extensión, el mismo caso que los `.xls` que el checklist declaró no portar |
| 18 | El catálogo «Personal de Laboratorio que firma» (`rem_user_signatures`) y la firma en el acta de recepción | **AUSENTE** | No queda quién autorizó el ingreso de la muestra, que es una firma distinta de la del informe |
| 19 | La redacción del caso «varios fuera de norma» en fisicoquímico | **PARCIAL** | Deriva de la frase que el laboratorio venía firmando |

**Recuento: 11 AUSENTE · 7 PARCIAL · 1 DECIDIDO.**

---

## 1. El texto de cromatografía no es el del sistema anterior — AUSENTE

**Qué hace el viejo.** El párrafo de cromatografía es una de tres frases, y no
dice más que eso:

- `_form_add_details_cromas_default_values.html.erb:42` — todo dentro de norma:
  «Las cantidades de gases detectados se encuentran dentro de los valores
  típicos dados por la Norma IEC 60599-2022.»
- `:48` — un gas señalado: «La cantidad de {gas} detectado se encuentra por
  encima del valor típico dado por la Norma IEC 60599-2022.»
- `:54` — varios: «Las cantidades de … se encuentran por encima de los valores
  típicos dados por la Norma IEC 60599-2022.»

**Qué hay en el nuevo.** `database/seeders/data/diagnosis_templates.json:95-114`
declara tres plantillas con otra redacción y **dos frases que no existen en
ningún ERB del sistema anterior** (verificado: `incipiente` y `repetir el
ensayo` no aparecen en `labo_old/app`):

- `:99` — «Las concentraciones de {ok} se encuentran dentro de los valores
  típicos indicados por la Norma {norm}. • **No se evidencia actividad de falla
  incipiente en la muestra analizada.**»
- `:106` — «… La concentración de {failed} excede el valor típico … **Se
  recomienda repetir el ensayo para confirmar la tendencia.**»
- `:113` — «… **Se recomienda repetir el ensayo y evaluar la tendencia respecto
  de muestras anteriores.**»

**Consecuencia.** El informe firmado emite dos juicios técnicos —descartar una
falla incipiente y recomendar un reensayo— que nadie del laboratorio redactó, y
lo hace por omisión, sin que el analista tenga que pedirlo.

---

## 2. Se perdió la norma por aceite y la variante por tipo de equipo — AUSENTE

**Qué hace el viejo.** Los dos ERB grandes ramifican por tipo de aceite y por
tipo de equipo, y cada rama cita SU norma:

| Familia | Aceite | Norma citada | Evidencia |
|---|---|---|---|
| Cromatografía | Mineral | IEC 60599-2022 | `_form_add_details_cromas_default_values.html.erb:37-72` |
| Cromatografía | Silicona | IEEE Std C57.146-2005 | `:90-126` |
| Cromatografía | Midel | IEEE Std C57.155-2014 | `:131-167` |
| Cromatografía | Vegetal | IEEE Std C57.155-2014 | `:172-208` |
| Fisicoquímico | Mineral | IEEE C57.106-2015 | `_form_add_details_physicals_default_values.html.erb:39-74` |
| Fisicoquímico | Silicona | IEEE C57.111-1989(R2009) | `:91-127` |
| Fisicoquímico | Vegetal / sintético | IEC 610203-2025 | `:132-167` |
| Fisicoquímico | Midel | IEC 61203:1992 | `:172-207` |

Y por tipo de equipo hay frases propias que sustituyen al diagnóstico entero:

- Conmutador (`@tipo == 10`), `_form_add_details_cromas_default_values.html.erb:62`:
  «(*) Norma de diagnóstico: IEC 60599-2022, para el aceite del conmutador no se
  tiene referencia de valores típicos.»
- Interruptor y los tipos por encima del 11 (`@tipo > 11 or @tipo == 9`), `:68-69`:
  «(*) Norma de diagnóstico: IEC 60599-2022. / No se tiene referencia de valores
  típicos.» Lo mismo en fisicoquímico, `_form_add_details_physicals_default_values.html.erb:69-70`.
- Éster sintético, éster natural y el aceite centinela: `_form_add_details_cromas_default_values.html.erb:78`, `:84`, `:216`
  y `_form_add_details_physicals_default_values.html.erb:79`, `:85`, `:214` —
  «No se tiene referencia de valores típicos.»

La misma dependencia del aceite está en el modelo, que fijaba la norma al crear
el informe: `app/models/rem_report.rb:204-243` (fisicoquímico) y `:459-469`
(cromatografía).

**Qué hay en el nuevo.** Las seis plantillas de esas dos familias declaran
`"oil_types": []` y `"equipment_types": []`
(`diagnosis_templates.json:74-114`), es decir «aplica a todo». La norma se
resuelve por el marcador `{norm}`, que sale del criterio aplicado al resultado
(`app/Services/Lab/TestReportPayload.php:414`), no de la tabla de arriba, y las
frases de «no se tiene referencia» no existen en ninguna plantilla.

**El motor sí sabe hacerlo.** `DiagnosisTextService::candidatas()`
(`app/Services/Lab/DiagnosisTextService.php:191-200`) filtra por `oil_types` y
`equipment_types` contra los códigos del catálogo, y esos códigos existen:
`mineral`, `silicona`, `ester_vegetal`, `ester_sintetico`
(`database/seeders/LabCatalogsSeeder.php:70-73`) y los 21 tipos de equipo,
`conmutador` e `interruptor` incluidos (`:87-107`). Es una omisión de datos, no
una limitación del motor.

**Consecuencia.** Una muestra de silicona o de éster sale con el párrafo de la
norma del mineral, y un conmutador —cuyo aceite el laboratorio declara
explícitamente sin valores típicos de referencia— recibe un diagnóstico que
afirma que sus gases están dentro de valores típicos.

---

## 3. PCB: se perdió la banda de 2 ppm y la cita del decreto — AUSENTE

**Qué hace el viejo.** `_form_add_details_pcbs_default_values.html.erb:27-51`.
No es un caso «ninguno / uno / varios»: es una **banda sobre el valor medido**,
con dos párrafos cada una.

- `:32-35`, con el total `>= 2` ppm: «Los resultados obtenidos de las pruebas
  detectaron **{valor} ppm** de PCB's en la muestra de aceite aislante, se
  considera contaminado con PCB's según Norma **IEC 60422-2024**.» seguido de
  «* De acuerdo al **D.S. N°018-2025-SA**, el producto de aceite dieléctrico
  analizado se encuentra DENTRO de la concentración peligrosa en el parámetro de
  PCB (>50mg/kg).»
- `:40-43`, con el total `< 2` ppm: «Según Norma IEC 60422:2024 el aceite se
  considera libre de PCB's cuando lo detectado es menor a 2 ppm.» más la línea
  del decreto con «DENTRO de la concentración no peligrosa (<50mg/kg)».

**Qué hay en el nuevo.** `diagnosis_templates.json:144-164`: tres plantillas
`none` / `one` / `many` sin bandas, sin `{value}` y sin la cita del decreto. La
redacción («La muestra NO se clasifica como contaminada», «su manejo y
disposición quedan sujetos a la reglamentación vigente») no aparece en ningún
ERB — verificado: `clasifica como contaminad` y `manejo y disposición` no
existen en `labo_old/app`. Ni `018-2025-SA` ni `60422-2024` aparecen en el
código del sistema nuevo: solo en
[`../11-AUDITORIA-VIEJO-VS-NUEVO.md:367`](../11-AUDITORIA-VIEJO-VS-NUEVO.md),
donde el hueco quedó anotado y después no se implementó.

**Consecuencia.** Se pierde la única afirmación del informe con efecto legal
directo —si el aceite queda dentro o fuera de la concentración peligrosa del
decreto peruano— y se la reemplaza por una remisión genérica a «la
reglamentación vigente». Además, el corte que decide el veredicto pasó de ser
2 ppm sobre el valor medido a depender de que exista un cuadro de límites de
PCB, que es otra cosa.

---

## 4. Las tres columnas AROCLOR de la hoja de PCB no se imprimen — AUSENTE

**Qué hace el viejo.** `_report_pcbs.erb:18-27` declara una cabecera de **diez**
columnas, con cuatro de resultado: `AROCLOR 1242`, `AROCLOR 1254`,
`AROCLOR 1260` y `TOTAL DE PCB`. La fila las imprime las cuatro:
`_report_pcbs.erb:38-41` (`pcb_val`, `pcb2_val`, `pcb3_val`, `pcb4_val`).

**Qué hay en el nuevo.** `config/legacy_report.php:88-91` declara la hoja de PCB
con una sola columna de resultado. Y el origen del dato tampoco está: las tres
columnas existen tipadas
(`database/seeders/data/test_field_types.json:49-54`) pero **no están
declaradas en `analyte_map.json`** — el único mapeo de la prueba es
`pcb.contenido_total_de_pcbs -> pcb`. Sin parámetro no hay resultado, y sin
resultado no hay fila: es el mismo mecanismo que dejaba los cinco furanos fuera
del papel antes de A9.

**Consecuencia.** El cliente recibe el total de PCB sin el desglose por
congénere, en los dos PDF, y no hay ningún aviso de que falte.

---

## 5. Las filas «ASTM D130» de azufre no se imprimen — AUSENTE

**Qué hace el viejo.** Cada una de las dos primeras sub-tablas de azufre tiene
**dos** filas, no una: el resultado del método y la clasificación de la lámina
de cobre por ASTM D130.

- `_report_azufres.erb:24-32`: fila 1 `ASTM 1275 - Cu` (`azu_val`), fila 2
  `ASTM D130` (`azu2_val`).
- `_report_azufres.erb:49-59`: fila 1 `IEC 62535` (`azu48_val`), fila 2
  `ASTM D130` (`azu482_val`).

Las dos columnas están activas en el volcado del catálogo:
`docs/migracion/esquema/catalogos-definiciones.sql:803` y `:813` («ASTM D130»
para las pruebas 13 y 14; la de la prueba 15, `:825`, sí está dada de baja).

**Qué hay en el nuevo.** `analyte_map.json` mapea un solo parámetro por ensayo
de azufre (`azufre_1275b.resultado -> s1275b`,
`azufre_62535_48_horas.resultado -> s62535_48`,
`azufre_62535_72_horas.resultado -> s62535_72`). Las columnas D130 quedan sin
parámetro y por lo tanto sin fila impresa. La sección `pendientes` de
`test_field_types.json:250-255` discute la lámina de cobre **solo para el ensayo
de 72 horas**, que es justamente el único donde el viejo no la tenía.

**Consecuencia.** La mitad del ensayo de azufre corrosivo —la clasificación de
la lámina— desaparece del papel.

---

## 6. La hoja de METALES nunca sale en el PDF clásico — AUSENTE

**Qué hace el viejo.** `show.erb:76-81` imprime la hoja de metales cuando
`met_display = 1`; la maqueta es `_report_metales.erb:18-23`.

**Qué hay en el nuevo.** `LegacyReportRenderer::render()` recorre
`array_keys(config('legacy_report.sheets'))`
(`app/Services/Lab/LegacyReportRenderer.php:150`) y busca cada clave entre las
familias del payload. La clave declarada es **`metales`**
(`config/legacy_report.php:116`), pero la familia real de la prueba es
**`metales_en_aceite`** — así se llama en `analyte_map.json:91`, en
`diagnosis_templates.json:255`, en `test_field_types.json:77-88` y en el propio
mapa de títulos del renderizador (`LegacyReportRenderer.php:620`).

**Consecuencia.** La hoja no coincide nunca y no se imprime. Hoy no se nota
porque la prueba no declara parámetros (es uno de los dos `pendientes` de
`analyte_map.json`); el día que el laboratorio los declare, el PDF clásico
seguirá saliendo sin la hoja de metales y sin ningún error.

---

## 7. El valor de orientación ya no se puede corregir por informe — AUSENTE

**Qué hace el viejo.** Los 33 campos `*_ori` de `rem_report_details` son
**campos de texto editables en la pantalla de carga del informe**, uno por
parámetro: `_form_add_details_physicals.html.erb:52` (`aci_ori`), `:104`
(`f25_ori`), `:148` (`f90_ori`), `:192` (`f100_ori`), `:236` (`rig_ori`), y así
para los trece. El propio sistema lo dice en su pantalla de ayuda
(`_form_add_details.html.erb:178`): «Los valores de orientación se detectan de
acuerdo al Tipo de aceite y Tipo de Transformador, **en caso no encuentre los
valores de orientación correctos por favor cambie los valores**». El automático
los precarga (`app/models/rem_report.rb:246-431`) y la persona los corrige.

**Qué hay en el nuevo.** El valor impreso sale de `results.spec_display` /
`spec_min` / `spec_max` congelados al validar
(`app/Services/Lab/LegacyReportRenderer.php:661-676` y
`app/Services/Lab/TestReportPayload.php:739`), escritos por `SpecEvaluator`
desde el cuadro de límites (`app/Services/Lab/SpecEvaluator.php:105`). No hay
ninguna vía —ni pantalla ni endpoint— que permita sobrescribir ese texto para un
informe en concreto. El punto B2 del checklist habla de la falta de editor de
los cuadros GLOBALES, que es otro problema: aun con ese editor, corregir un
informe suelto obligaría a mover el cuadro de todos.

**Consecuencia.** Cuando el cuadro automático no acierta —el caso que el propio
sistema anterior anticipaba en su pantalla—, el informe sale con un valor de
orientación equivocado o con raya, y la única salida es cambiar el cuadro para
todo el laboratorio.

---

## 8. La norma de referencia del PDF clásico está clavada — PARCIAL

**Qué hace el viejo.** Cada hoja imprime al pie «(*) Norma de referencia» con
la norma que el informe tiene guardada para esa familia:
`_report_physicals.erb:347-350` (`fiq_norm.name`), `_report_cromas.erb:463-466`
(`cro_norm.name`), `_report_pcbs.erb:49-52` (`pcb_norm.name`),
`_report_polis.erb:39-42` (`pol_norm.name`). Esas normas son un campo del
informe (`rem_report_detail.rb:44-49`), se precargan según el tipo de aceite
(`rem_report.rb:204-243`, `:459-469`) y el analista las cambia desde un
desplegable del catálogo `norms` (`_form_add_details_cromas.html.erb:515`).

**Qué hay en el nuevo.** `config/legacy_report.php:76` y `:82` escriben
`'standard_note' => 'IEEE C57.106-2015'` y `'IEC 60599-2022'` como texto fijo, y
`LegacyReportRenderer::hoja()` los antepone a las condiciones
(`app/Services/Lab/LegacyReportRenderer.php:476-479`). El informe moderno **sí**
lo resuelve bien: toma el criterio congelado de los propios resultados
(`TestReportPayload.php:414`, `'standard' => $criterios->implode(' · ')`).

**Consecuencia.** En el PDF clásico —que es una exportación que el laboratorio
ofrece, no solo una comparación— toda muestra que no sea de aceite mineral cita
una norma de referencia que no le corresponde.

---

## 9. La «Fecha de Análisis» del PDF clásico es la fecha de impresión — PARCIAL

**Qué hace el viejo.** Cada hoja imprime la fecha en que se corrió ESE ensayo,
guardada por familia: `_report_physicals.erb:352-353` (`str_fiq_date`),
`_report_cromas.erb:467-468` (`str_cro_date`), `_report_pcbs.erb:53-55`,
`_report_furanos.erb:125-126`, `_report_azufres.erb:97-98`, y así en las quince.
El valor se precarga de la fecha de ensayo del instrumento
(`_form_add_details_cromas.html.erb:508`).

**Qué hay en el nuevo.** `LegacyReportRenderer::paginaFiquis()` y
`paginaCromas()` escriben `'Fecha de Análisis' => now()->format('d-m-Y')`
(`app/Services/Lab/LegacyReportRenderer.php:366` y `:414`). El informe moderno
sí usa el dato correcto: `run_date` de la hoja de bancada que corrió esa prueba
(`app/Services/Lab/TestReportPayload.php:420`, impreso en
`resources/views/lab_management/reports/test_report.blade.php:785`).

**Consecuencia.** Reimprimir en agosto el informe clásico de un ensayo de marzo
produce un papel que declara haberse analizado en agosto.

---

## 10. La «Fecha de Emisión» impresa es la fecha de generación — AUSENTE

**Qué hace el viejo.** `date_emi` es un campo del informe, precargado con hoy
pero editable (`_form_new_data_customer.html.erb:70`), y el papel imprime ese
campo: `_report_main_info.erb:36-37` (`str_date_emi`).

**Qué hay en el nuevo.** El campo existe y el formulario lo edita
(`sample_reports.issued_at`,
`resources/js/Components/Receptions/ReportFormModal.vue:204-211`), pero **ningún
PDF lo imprime**:

- Informe moderno: `resources/views/lab_management/reports/test_report.blade.php:621-622`
  imprime `$generatedAt`, que es `now()` fijado en
  `app/Http/Controllers/LabManagement/TestReportController.php:111`. La tarjeta
  de cabecera intenta `$sample['issued_at'] ?? $generatedAt`
  (`test_report.blade.php:517`), pero `issued_at` **no está en el payload**:
  `TestReportPayload::cabeceraMuestra()`
  (`app/Services/Lab/TestReportPayload.php:103-136`) no lo devuelve, así que
  siempre cae al respaldo.
- Informe clásico: `app/Services/Lab/LegacyReportRenderer.php:204`,
  `'emision' => now()->format('d-m-Y')`.

**Consecuencia.** Como el informe emitido se reimprime desde su snapshot
congelado (`app/Models/SampleReport.php:127-134`) pero la fecha se recalcula
fuera de él, una reimpresión de un informe emitido en marzo sale fechada hoy y
**contradice al papel que el cliente tiene en la mano** — que es exactamente lo
que el congelado del snapshot existe para impedir.

---

## 11. El firmante ya no se elige por informe — PARCIAL

**Qué hace el viejo.** El informe guarda UN firmante
(`rem_report.rem_signature_id`, `app/models/rem_report.rb:17`), elegido a mano
desde un desplegable del catálogo (`_form_add_details_signatures_tab.html.erb:8`
y `_form_add_signatures.html.erb:14`), y el papel imprime esa única firma con su
imagen, su nombre y su cargo: `_report_main_signature.erb:4-12`
(«Reportado por:»). Asignarla **bloquea** el informe automáticamente
(`rem_report.rb:167-177`), y desbloquearlo la borra
(`_form_validate.html.erb:7`), de modo que hay que volver a elegir quién firma.

**Qué hay en el nuevo.** Los dos PDF estampan **todos** los firmantes activos
del workspace, sin elección:
`app/Services/Lab/LegacyReportRenderer.php:746-763` y
`app/Http/Controllers/LabManagement/TestReportController.php:263-281`
(`->where('tenant_id', …)->where('is_active', true)`). El checklist ya declaró
el cambio de «una firma» a «lista con relación y cargo»
(`resources/views/lab_management/reports/legacy/report.blade.php:397-399`), y
eso es una mejora; lo que no se reemplazó es la **elección**.

**Consecuencia.** Un laboratorio con tres firmantes imprime los tres en todos
los informes, y no queda registrado en el informe quién firmó ese papel — solo
en el registro de auditoría de la generación
(`TestReportController.php:143-148`), que no es lo mismo que el documento.

---

## 12. La evidencia de envío al cliente — AUSENTE

**Qué hace el viejo.** Cada informe tiene un campo de texto rico
`customer_evidence` con su propia pantalla y su propia acción de controlador:
`app/controllers/im_management/rem_reports_controller.rb:236-246`,
`app/views/im_management/rem_reports/customer_evidence.html.erb:1-17` y
`_form_evidence.html.erb:8` (un `text_area` con CKEditor de 500 px de alto). Es
donde el laboratorio pega el correo de entrega, el cargo o el acuse del cliente.
La ruta está declarada en `config/routes.rb:115`.

**Qué hay en el nuevo.** `SampleReport` tiene `notes`
(`app/Services/Lab/SampleReportService.php:69`,
`ReportFormModal.vue:370-378`), que es un texto corto de la muestra y se
describe como notas del informe, no como constancia de entrega. No hay campo de
texto rico ni pantalla equivalente.

**Consecuencia.** Se pierde el lugar donde quedaba la prueba de que el informe
se entregó y a quién. `delivered_at` guarda la fecha, no la evidencia.

---

## 13. Los sub-títulos por método de la hoja de azufre — PARCIAL

**Qué hace el viejo.** La hoja de azufre tiene **tres tablas rotuladas**, cada
una con su propio encabezado en banda gris: `AZUFRE 1275B`
(`_report_azufres.erb:15`), `AZUFRE 62535 (48 Horas)` (`:42`) y
`AZUFRE 62535 (72 Horas)` (`:69`).

**Qué hay en el nuevo.** `config/legacy_report.php:107-110` declara una sola
hoja `azufre_corrosivo` con columnas `item / norma / ensayo / resultado`, y
`report.blade.php:248-273` dibuja **una** tabla con un título. Es la
consecuencia esperada de A8 (unificar los tres azufres en una hoja, que era el
objetivo), pero la separación visual por método se perdió en el camino.

**Consecuencia.** El lector no distingue de un vistazo qué resultado
corresponde al ensayo de 48 h y cuál al de 72 h; queda solo la columna NORMA.

---

## 14. El rótulo «LIMITE REFERENCIAL» del DBDS — PARCIAL

**Qué hace el viejo.** `_report_dbds.erb:23` rotula esa columna `LIMITE
REFERENCIAL`, sin asterisco, porque la hoja de DBDS no tiene nota de norma de
referencia al pie.

**Qué hay en el nuevo.** `config/legacy_report.php:129` usa la columna genérica
`orientacion`, y `report.blade.php:242` la rotula siempre
`VALOR DE ORIENTACIÓN (*)`.

**Consecuencia.** La hoja imprime un asterisco de llamada que sí tiene destino
(el `standard_note` `IEC 62697-1` de `config/legacy_report.php:130`), pero con
un rótulo distinto del que el laboratorio venía firmando.

---

## 15. El inhibidor solo se diagnostica en aceite mineral — AUSENTE

**Qué hace el viejo.** `_form_add_details_inhibidores_default_values.html.erb:27`:
`<% if @aceite == 1 or @aceite == 8 or @aceite == 2 or @aceite == 3 %>` — o sea
mineral (1), el centinela «-» (8), éster sintético (2) y éster natural (3).

**Qué hay en el nuevo.** `diagnosis_templates.json:338-341` declara
`"oil_types": ["mineral"]`. La nota que acompaña a la plantilla
(`:337`) dice literalmente «Solo para los aceites 1, 2, 3 y 8 del sistema
anterior, que es la condición literal de su ERB» — y después declara uno solo.
Los códigos de los otros existen (`ester_sintetico`, `ester_vegetal` en
`database/seeders/LabCatalogsSeeder.php:72-73`); el centinela «-» no se siembra
a propósito y ahí la ausencia de tipo es `NULL`, que tampoco casa con
`["mineral"]`.

**Consecuencia.** Una muestra de éster, o una de un equipo sin tipo de aceite
declarado, sale con el párrafo del inhibidor **en blanco**, y como el motor no
inventa texto, el informe no se puede confirmar hasta que alguien lo escriba a
mano.

---

## 16. No hay exportación a Excel del listado de informes — AUSENTE

**Qué hace el viejo.** `app/controllers/im_management/reports_controller.rb:36-43`
responde `format.xls` y baja
`Listado_de_reportes_{fecha}.xls` con ocho columnas:
`app/views/im_management/reports/partials/_xls_partial_report.erb:12-19` (Nº de
Reporte, Correlativo, OS, Cliente, F.Recepción, F.Entrega, Razón de Análisis,
Estado). El botón está en `reports/index.html.erb:27`.

**Qué hay en el nuevo.** La pantalla equivalente existe y está mejor resuelta
—paginación, orden y búsqueda por columna en SQL,
`app/Http/Controllers/LabManagement/SampleReportController.php:94-158`, ruta
`sample_reports.index` en `routes/lab_management.php:319`— pero **no tiene ruta
de exportación**: la única del bloque es `receptions.export`
(`routes/lab_management.php:357`).

**Nota.** Está dentro del alcance de C8 del checklist («un listado de informes»
entre las siete pantallas gerenciales), que sigue `[ ]`. Se lista acá porque la
pantalla ya se construyó y la exportación quedó afuera, así que el hueco es más
chico de lo que C8 sugiere.

**Consecuencia.** Quien hoy necesita el listado en una planilla lo copia a mano
del navegador.

---

## 17. El formato `.doc` del informe — DECIDIDO

**Qué hace el viejo.** `rem_reports_controller.rb:30-31` declara `format.html` y
`format.doc` junto al `format.pdf`. Como la vista es `show.erb` sin extensión de
formato, el `.doc` servía **el mismo HTML** con otro tipo de contenido: Word lo
abría, pero no era un `.docx`.

**Qué hay en el nuevo.** Solo PDF (moderno y clásico).

**Consecuencia.** Ninguna que valga la pena resolver: es el mismo caso que los
`.xls` que `12-CHECKLIST.md` (sección «Lo que NO hay que portar») ya declaró
fuera de alcance por ser HTML con otra extensión. Se anota para que no vuelva a
aparecer como hallazgo.

---

## 18. El catálogo de firmas de recepción — AUSENTE

**Qué hace el viejo.** Hay **dos** catálogos de firmas, no uno:

- `rem_signatures` — quien firma el INFORME (`app/models/rem_signature.rb`,
  formulario `rem_signatures/partials/_form_new.html.erb:12,21,30`: nombre
  completo, cargo e imagen).
- `rem_user_signatures` — «Personal de Laboratorio que firma»
  (`app/models/rem_user_signature.rb`, menú en
  `app/views/layouts/_app_sidebar_left_menus.html.erb:160`), que es **quien
  autoriza el ingreso de la muestra** y se estampa en el acta de recepción:
  `app/views/im_management/rems/partial_report.html.erb:45-49`, bajo la columna
  «PERSONAL QUE AUTORIZA EL INGRESO DE MUESTRA / Nombre / Firma».

**Qué hay en el nuevo.** Un solo módulo, `Signature`
(`app/Models/Signature.php:34-46`), que cubre el primero: nombre, cargo
(`title`), imagen, usuario enlazado y relación. El segundo no tiene equivalente:
`Reception` solo guarda el muestreador (`app/Models/Reception.php:87-89`,
`:145-148`), y la exportación de la recepción es una planilla de muestras
(`ReceptionController::export`, `app/Http/Controllers/LabManagement/ReceptionController.php:424-432`),
no el acta con firma.

**Consecuencia.** No queda constancia firmada de quién autorizó el ingreso de la
muestra al laboratorio, que para ISO 17025 es un registro distinto del del
informe.

---

## 19. La redacción del caso «varios fuera de norma» en fisicoquímico — PARCIAL

**Qué hace el viejo.** `_form_add_details_physicals_default_values.html.erb:60`:
«**La cantidad de** número ácido (acidez), rigidez dieléctrica, … **están fuera
del valor sugerido** por la Norma IEEE C57.106-2015.»

**Qué hay en el nuevo.** `diagnosis_templates.json:91`: «**Las cantidades de**
{failed} **están fuera de los valores sugeridos** por la Norma {norm}.»

El caso de uno solo (`:84`) sí coincide palabra por palabra con `:52` del ERB, y
la frase de los que están dentro de norma coincide con `:44`.

**Diferencia menor relacionada.** El viejo arma la lista de «los que están bien»
nombrando solo nueve parámetros (`:44`): deja fuera color, condición visual,
densidad y las dos resistividades. El nuevo lista **todos** los parámetros
medidos de la familia. Es defendible como corrección —el ERB también dejaba una
coma colgando, cosa que el nuevo resuelve—, pero cambia el texto del papel.

**Consecuencia.** El párrafo firmado no dice exactamente lo mismo que el que el
laboratorio viene firmando desde 2019. Es la única de las trece familias
transcritas donde la redacción derivó; corregirlo es editar dos cadenas del
JSON.

---

## Lo que se revisó y está bien (para no volver a auditarlo)

- **La cabecera impresa está completa.** Los 28 campos de
  `_report_main_info.erb:1-117` (cliente, dirección, contacto, usuario final,
  orden de servicio, fecha de recepción, muestreador, descripción; serie, TAG,
  locación, tipo de equipo, fabricante, año, conmutador, tensión, potencia,
  sistema de expansión, tipo y marca de aceite, cantidad con unidad, en
  operación, fecha de muestreo, punto y razón de muestreo, y las cuatro
  condiciones de campo) están los 28 en el clásico
  (`resources/views/lab_management/reports/legacy/report.blade.php:150-210`) y
  los 28 en el moderno
  (`resources/views/lab_management/reports/test_report.blade.php:598-691`). La
  única excepción es la fecha de emisión, que es el hueco 10.
- **Las once familias restantes del ANÁLISIS DE RESULTADOS** —furanos,
  partículas, sedimentos, metales, viscosidad, DBDS, inflamación, fluidez,
  pasivador, grado de polimerización y azufre— están transcritas palabra por
  palabra, con sus bandas y sus erratas: cotejadas una a una contra
  `_form_add_details_{furanos,particles,sedimentos,metales,viscos,dbds,inflamas,fluids,pasivadores,polis,azufres}_default_values.html.erb`.
- **Las quince hojas del PDF** y su orden (`show.erb:13-135`) están declaradas en
  `config/legacy_report.php:68-155`, incluidas las columnas propias de cada una
  (MÉTODO en furanos, sin orientación en partículas, tres columnas en azufre) y
  la nota de Chendong (`:100`, contra `_report_furanos.erb:120`).
- **El pie legal, el membrete y el sello** salen del workspace en vez de estar
  clavados con los datos de Hitachi (`_pdf_footer.erb:24,31,42` →
  `report.blade.php:103-118`), y el descargo va en todas las hojas (A7).
- **El ciclo de trabajo** del viejo se conserva: precarga automática del texto,
  edición por el analista, regeneración a pedido
  (`_form_add_details.html.erb:196-212` →
  `app/Services/Lab/DiagnosisTextService.php:95-121`), y la puerta que impedía
  firmar con familias sin texto (`_form_add_details.html.erb:161-168` →
  `SampleReportController::confirmAnalysis()`, `:448-470`, apoyado en
  `familiasSinTexto()`, `:482-495`).
- **El bloqueo y desbloqueo** (`rem_report.rb:167-177` y
  `_form_validate.html.erb:17`) tienen equivalente con motivo obligatorio y
  auditoría (`app/Services/Lab/SampleReportService.php:200-234`).
- **El correlativo** `REP-LAB-{año}-{4 dígitos}` (`rem_report.rb:752-767`) se
  conserva en `App\Models\SampleReport::formatCode()`
  (`app/Models/SampleReport.php:106-109`), ahora reservado por workspace.
