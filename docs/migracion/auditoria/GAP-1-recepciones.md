# GAP-1 — Recepción de muestras y correlativos

> **Alcance.** El bloque de RECEPCIÓN DE MUESTRAS Y CORRELATIVOS del sistema
> Ruby de 2019 (`labo_old`) contra su equivalente en Laravel (`labo_new`).
> Cubre `rems`, `rem_correlatives`, `rem_fulls`, `samplers`, `stickers` y
> `automates`: sus controladores, sus modelos y **todas** sus vistas —incluidas
> las parciales de listado, filtros, exportación y formularios—, más las rutas
> y el volcado de estructura.
>
> **Archivos del viejo revisados: 85.** Seis controladores
> (`app/controllers/im_management/{rems,rem_correlatives,rem_fulls,samplers,stickers,automates}_controller.rb`),
> cinco modelos (`rem.rb`, `rem_correlative.rb`, `rem_job.rb`, `sampler.rb`,
> `sticker.rb`), 73 vistas ERB bajo `app/views/im_management/{rems,rem_correlatives,rem_fulls,samplers,stickers,automates}/**`
> y `config/routes.rb`.
>
> **Fuera de alcance** (se nombran solo cuando explican un hueco):
> `rem_reports`, `rem_report_details`, `rem_signatures`, `rem_user_signatures`,
> `transformers` y los reportes gerenciales de `report_management`.
>
> **Qué NO se repite aquí.** Lo que ya está cerrado como decisión en
> [`../12-CHECKLIST.md`](../12-CHECKLIST.md) ("Lo que NO hay que portar", C6, C8)
> y en [`E-cobertura-tablas.md`](E-cobertura-tablas.md) aparece marcado
> **DECIDIDO** y sin volver a discutirse: `rem_correlatives.qr_code`, los seis
> filtros de reportes que ninguna pantalla exponía, y los `.xls` que en realidad
> eran HTML con otra extensión.
>
> Verificado contra el código el 2026-08-02. Nada de lo que sigue se afirma sin
> `archivo:línea` de los dos lados.

---

## Tabla resumen

| # | Qué falta | Clasificación | Consecuencia |
|---|-----------|---------------|--------------|
| 1 | Los 15 contadores de envases por familia de ensayo (`num_fiq`…`num_pas`) y el total pactado | **AUSENTE** | El laboratorio no puede registrar cuántos frascos de cada tipo entraron ni contrastar lo pactado con lo emitido |
| 2 | La persona que autoriza el ingreso de la muestra (`rem_user_signature_id`) y su firma en el acta | **PARCIAL** (era AUSENTE; el campo se RESOLVIÓ el 2026-08-02) | Catálogo PROPIO `entry_authorizers` (módulo «Personal que autoriza», con nombre y firma escaneada, como el `rem_user_signatures` del viejo) + `receptions.authorized_by_id`, obligatorio en el alta y visible en la ficha. QUEDA el acta imprimible con la imagen de su firma (`_xls_partial_report.erb:88-99`) |
| 3 | La validación "muestras a analizar ≤ envases recibidos" | **AUSENTE** | Se pueden emitir 40 correlativos para una entrega de 3 frascos sin que nada lo advierta |
| 4 | Prioridad y fecha estimada **por muestra** (`is_urgent` + `date_urgent` del correlativo) | **PARCIAL** | No se puede adelantar una muestra suelta dentro de una entrega ni comprometerle una fecha propia |
| 5 | Los filtros y el ordenamiento del listado de recepciones | **PARCIAL** | Quedan sin filtro el muestreador, la orden de servicio, la fecha comprometida, la serie del transformador, el correlativo y los cuatro chequeos de avance |
| 6 | La exportación a Excel del **listado** de recepciones (con sus filtros) | **AUSENTE** | No hay forma de sacar el tablero de entregas del período a una planilla |
| 7 | El "Descargar Todo" de una recepción, completo (cabecera + muestras + informes) | **PARCIAL** | La descarga trae las muestras pero no la cabecera de la entrega ni el listado de informes emitidos |
| 8 | La baja de una recepción no arrastra sus muestras, pruebas ni informes | **CORREGIDO** (2026-08-02) | Ahora arrastra en transacción y una entrega con informe emitido no se borra (`ReceptionController::destroy`) |
| 9 | "Aplicar a todas" reemplaza el pedido en vez de agregarse a él | **PARCIAL** | No hay manera de sumar una prueba a las veinte muestras sin borrar lo que cada una tenía pedido |
| 10 | La pantalla ancha de recepciones (`rem_fulls`): envases por familia y verificación física por fila | **AUSENTE** | No existe ninguna pantalla ni descarga donde se vea, entrega por entrega, qué envases entraron y si estaban conformes |
| 11 | La fecha de entrega comprometida era obligatoria | **CORREGIDO** (2026-08-02) | `due_at` es obligatoria, no puede ser anterior a la recepción y el calendario ni ofrece esos días. También son obligatorios el muestreador (catálogo o externo), el autorizador y los envases (> 0, que prellenan el confirmar muestras) |
| 12 | Etiquetas del envase con QR e impresión del sticker por correlativo | **DECIDIDO** (C6) | Documentado: hoy no se puede imprimir la etiqueta que se pega al frasco |
| 13 | Asignar a la muestra un equipo de otro cliente (la escapatoria `?transformer_id=0`) | **DECIDIDO** | Documentado en el código: si el equipo está cargado bajo otro cliente hay que corregir su ficha primero |

**Recuento** (actualizado 2026-08-02): 13 huecos — **4 AUSENTE** (#1, #3, #6,
#10) · **5 PARCIAL** (#2, #4, #5, #7, #9) · **2 CORREGIDO** (#8, #11) ·
**2 DECIDIDO** (#12, #13). Además, fuera de la lista del viejo: el «N° de
recepción», que acá era texto libre, ahora se **genera** (`REC-año-número`,
contador propio por workspace y año, emitido en la transacción del alta).

---

## 1. Los 15 contadores de envases por familia de ensayo — AUSENTE

**Qué hace el viejo.** El formulario de alta pide, uno por uno, cuántos envases
de cada familia entran: botella de físico químico, jeringa de cromatografía,
frasco de PCB, de furanos, de azufre, grado de polimerización, viscosidad,
partículas, metales, inhibidor, DBDS, sedimentos, fluidez, inflamación y
pasivador. Un script suma los quince y escribe el total en un campo de solo
lectura.

- `labo_old/app/views/im_management/rems/partials/_form_new.html.erb:89-195` —
  los quince campos numéricos más `qty_num_pack` (total, `readonly`).
- `labo_old/app/views/im_management/rems/partials/_form_new.html.erb:213-222` —
  la suma automática de `.quantity` hacia `.total`.
- `labo_old/app/views/im_management/rems/partials/_form_show_secondary_info.html.erb:175-339` —
  la ficha los muestra, y solo los que son mayores que cero.
- `labo_old/app/views/im_management/rems/partials/_form_approve.html.erb:109-311` —
  la pantalla de confirmación los repite para revisarlos antes de emitir.
- `labo_old/app/views/im_management/rems/partials/_xls_partial_report.erb:27-50`
  y `:68-83` — el Excel los imprime bajo el encabezado "Nº ENVASES QUE INGRESAN".
- `labo_old/app/models/rem.rb:124-131` — `str_type_report_icon` compara los
  informes emitidos contra `qty_num_test`, la cantidad comprometida.

**Qué hay en el nuevo.** Una sola columna con el total de envases.

- `labo_new/database/migrations/2026_07_28_130000_create_receptions_tables.php:119` —
  `packages`, `nullable`.
- `labo_new/resources/js/Pages/Receptions/Form.vue:255-267` — el único campo.
- `labo_new/app/Http/Controllers/LabManagement/ReceptionController.php:492` —
  su regla de validación.

**Ya documentado** en [`E-cobertura-tablas.md:138`](E-cobertura-tablas.md) y en
su §9 (`:339-346`), sin decisión tomada.

**Consecuencia.** El laboratorio no puede registrar qué recibió físicamente por
familia de ensayo, y por lo tanto no puede responder "el cliente pactó 40
cromatografías y entraron 38".

---

## 2. La persona que autoriza el ingreso de la muestra — AUSENTE

**Qué hace el viejo.** Toda recepción exige elegir al personal de laboratorio
que autoriza el ingreso. Es una relación obligatoria del modelo, se muestra en
la ficha, en la pantalla de confirmación y en el listado, y el Excel de la
entrega imprime **la imagen de su firma**.

- `labo_old/app/models/rem.rb:4` — `belongs_to :rem_user_signature`
  (obligatorio en Rails 5+).
- `labo_old/app/views/im_management/rems/partials/_form_new.html.erb:68-71` —
  el selector, marcado `(*)` y con `data-parsley-required`.
- `labo_old/app/views/im_management/rems/partials/_form_show_secondary_info.html.erb:156-163`
  y `_form_approve.html.erb:71-76` — se muestra en la ficha y al confirmar.
- `labo_old/app/views/im_management/rems/partials/_table.html.erb:14` y
  `:62` — es columna del listado.
- `labo_old/app/views/im_management/rems/partials/_xls_partial_report.erb:88-99` —
  "PERSONAL QUE AUTORIZA EL INGRESO DE MUESTRA: Nombre / Firma", con
  `image_tag(...signature_url)`.
- `labo_old/app/controllers/im_management/rems_controller.rb:27` y `:69` — se
  puede filtrar el listado por esa persona.

**Qué hay en el nuevo.** Nada. `receptions` no tiene la columna
(`labo_new/database/migrations/2026_07_28_130000_create_receptions_tables.php:93-149`),
`ReceptionController::validated()` no la acepta
(`labo_new/app/Http/Controllers/LabManagement/ReceptionController.php:473-500`)
y `resources/lang/es/receptions.php` no tiene su etiqueta. Existen `confirmed_by`
y `created_by`, que son otra cosa: quién apretó el botón, no quién firma como
responsable de aceptar la muestra.

**Ya documentado** en [`M-campos-obligatorios.md:920-923`](M-campos-obligatorios.md)
(§8.4), con la recomendación explícita de recuperarlo (`:962`).

**Consecuencia.** La recepción no deja constancia de quién autorizó el ingreso, y
el acta de recepción firmada que el laboratorio entregaba no se puede emitir.

**RESUELTO el campo (2026-08-02).** Primero se intentó como bandera en Firmas
(`signatures.authorizes_entry`) y el laboratorio lo corrigió: el autorizador NO
tiene que ver con los firmantes de informes — en el viejo es un catálogo propio
con su propia pantalla. Quedó como en el viejo: módulo **«Personal que
autoriza»** (`entry_authorizers`: nombre completo + firma escaneada o dibujada,
per-tenant, con su entrada de menú junto a Muestreadores) y
`receptions.authorized_by_id` apuntando ahí, obligatorio en el alta. La
migración `2026_08_02_203000` trasladó lo que hubiera quedado en la bandera y
la eliminó.
**QUEDA** el acta imprimible con la imagen de la firma del autorizador.

---

## 3. La validación "muestras a analizar ≤ envases recibidos" — AUSENTE

**Qué hace el viejo.** El modelo rechaza guardar una recepción cuya cantidad de
correlativos supere el total de envases, y el controlador devuelve el mensaje
concreto.

- `labo_old/app/models/rem.rb:28` y `:31-37` — `validate :non_zero`:
  `if self.qty_num_test > self.qty_num_pack` agrega error.
- `labo_old/app/controllers/im_management/rems_controller.rb:196` y `:210` —
  "El Nº de Muestras para analizar no debe ser mayor al Total de Envases
  ingresados."

**Qué hay en el nuevo.** Solo un rango fijo, sin relación con los envases.

- `labo_new/app/Http/Controllers/LabManagement/ReceptionController.php:322-324` —
  `'samples' => ['required','integer','min:1','max:500']`.
- `labo_new/app/Services/Lab/ReceptionService.php:47-51` — solo verifica `< 1`.
- `packages` sigue siendo `nullable`
  (`...create_receptions_tables.php:119`), así que ni siquiera hay contra qué
  comparar.

**Ya documentado** en [`M-campos-obligatorios.md:916-918`](M-campos-obligatorios.md)
(§8.3).

**Consecuencia.** Se pueden emitir correlativos de más —números quemados que
nunca van a tener muestra— sin que nada lo advierta al confirmar.

---

## 4. Prioridad y fecha estimada por muestra — PARCIAL

**Qué hace el viejo.** Además de la urgencia de la entrega, cada correlativo
tiene la suya y una fecha estimada propia. Hay una acción dedicada
(`add_details`), la ficha muestra una columna "Prioridad / F. Estimada" y el
Excel las imprime.

- `labo_old/app/controllers/im_management/rem_correlatives_controller.rb:95-103` —
  la acción `add_details`.
- `labo_old/app/views/im_management/rem_correlatives/partials/_form_add_details.html.erb:72-85` —
  la casilla `is_urgent` y el campo `date_urgent`.
- `labo_old/app/views/im_management/rems/partials/_form_show_list_tests.html.erb:132-143` —
  la columna, con "Máxima Prioridad" y la fecha en rojo.
- `labo_old/app/views/im_management/rems/partials/_xls_partial_report.erb:208-222` —
  columnas "IMPORTANCIA" y "FECHA ESTIMADA DE REALIZACIÓN".
- `labo_old/app/models/rem_correlative.rb:38-40` y `:47` — `str_date_urgent`,
  `is_urgent` por defecto en 0.

**Qué hay en el nuevo.** La columna `is_urgent` existe en la muestra y se hereda
de la recepción al emitir, pero **no hay pantalla que la cambie**, y `date_urgent`
no tiene equivalente.

- `labo_new/database/migrations/2026_07_28_130000_create_receptions_tables.php:181` —
  `samples.is_urgent`.
- `labo_new/app/Services/Lab/ReceptionService.php:76` — se copia de la recepción
  al crear la muestra y nadie más la escribe.
- `labo_new/resources/js/Pages/Receptions/Show.vue:151-183` — las columnas de la
  tabla de muestras: código, equipo, pruebas, avance y acciones. No hay prioridad.

**Ya documentado** en [`E-cobertura-tablas.md:139`](E-cobertura-tablas.md).

**Consecuencia.** Dentro de una entrega de cuarenta muestras no se puede señalar
cuál va primero ni comprometerle una fecha, que es exactamente el caso que el
campo resolvía.

---

## 5. Los filtros y el ordenamiento del listado — PARCIAL

**Qué hace el viejo.** El listado tiene quince filtros en un panel desplegable,
ocho accesos directos desde el tablero, una fila de búsqueda por columna y
enlaces de ordenamiento en las quince columnas.

- `labo_old/app/views/im_management/rems/partials/_search_filters.html.erb:1-126` —
  muestreador, Nº de orden de servicio (con la opción "Pendiente"), rango de
  fecha de recepción, cliente, autorizado por, rango de cantidad de muestras,
  series/pruebas/valores/informes asignados (los cuatro chequeos), estado
  bloqueado/desbloqueado, prioridad, año y número de correlativo, rango de fecha
  de entrega y serie del transformador.
- `labo_old/app/controllers/im_management/rems_controller.rb:30-117` — su
  traducción a la consulta, incluidos los ocho accesos del tablero (`num_os`,
  `num_correlative`, `num_serie`, `num_task`, `num_test`, `num_priority`,
  `num_report_pending`, `num_report_state`).
- `labo_old/app/views/im_management/rems/partials/_search_ordering.html.erb:1-18` —
  `sort_link` en las quince columnas.
- `labo_old/app/views/im_management/rems/partials/_table.html.erb:24-40` — la
  fila "Buscar" por columna.

**Qué hay en el nuevo.** Cinco filtros y un solo criterio de orden.

- `labo_new/app/Http/Controllers/LabManagement/ReceptionController.php:113-129` —
  estado, cliente, rango de fecha de recepción, urgente y número de muestra.
- `labo_new/app/Http/Controllers/LabManagement/ReceptionController.php:133` —
  `orderBy('received_at', $direction)`: la única columna por la que se ordena.
- `labo_new/resources/js/Pages/Receptions/config/columns.js:24` y `:35` — las
  columnas de fecha de recepción **y de fecha comprometida** están marcadas
  `sorter: true`, pero el servidor ignora la segunda: la flecha se dibuja y no
  cambia nada.
- `labo_new/resources/js/Pages/Receptions/Index.vue:219-270` — los controles.

**Consecuencia.** Preguntas que el laboratorio hacía todos los días —"qué
entregas trajo tal cuadrilla", "cuáles siguen sin orden de servicio", "en qué
entrega vino el transformador de la serie TR-99887", "qué vence esta semana"— hoy
no se pueden responder desde el listado.

---

## 6. La exportación a Excel del listado de recepciones — AUSENTE

**Qué hace el viejo.** El mismo listado, con los filtros aplicados, se baja en
Excel con catorce columnas (incluida "Días Restantes").

- `labo_old/app/controllers/im_management/rems_controller.rb:122-137` —
  `@export_results` (sin paginar) y los formatos `xlsx` y `xls`.
- `labo_old/app/views/im_management/rems/export.xlsx.axlsx:1-47` — el archivo,
  con estilos y encabezados.
- `labo_old/app/views/im_management/rems/partials/_search_results_options.html.erb:143-165` —
  el botón "Exportar Registros", que arrastra los dieciocho parámetros de
  búsqueda a la descarga.

**Qué hay en el nuevo.** Solo la descarga de **una** recepción.

- `labo_new/routes/lab_management.php:356` — `receptions/{reception}/export` es
  la única ruta de exportación del módulo.
- `labo_new/resources/js/Pages/Receptions/Index.vue:207-216` — la barra del
  listado solo ofrece "Registrar recepción".

**Consecuencia.** No hay manera de sacar a una planilla el tablero de entregas de
un período o de un cliente, que es lo que se adjunta a un informe de gestión.

---

## 7. El "Descargar Todo" de la recepción, incompleto — PARCIAL

**Qué hace el viejo.** Una sola descarga con **tres bloques**: la cabecera de la
entrega (fechas, días restantes, muestreador, orden de servicio, cliente, los
quince contadores de envases, el total, las tres verificaciones físicas, las
observaciones y el nombre y la firma de quien autoriza), el listado de muestras
(serie del transformador, correlativo, fecha de ingreso, pruebas asignadas,
**fecha de muestreo por prueba**, y los cuatro chequeos en SÍ/NO más importancia y
fecha estimada) y el listado de informes (nueve columnas).

- `labo_old/app/controllers/im_management/rems_controller.rb:302-325` — la acción
  `partial_report`.
- `labo_old/app/views/im_management/rems/partials/_xls_partial_report.erb:17-108`
  (cabecera), `:110-224` (muestras), `:226-256` (informes).
- `labo_old/app/views/im_management/rems/admin.html.erb:37-39` — el botón
  "Descargar Todo".

**Qué hay en el nuevo.** Una hoja con diez columnas, solo de muestras.

- `labo_new/app/Exports/LabManagement/Receptions/ReceptionSamplesExport.php:47-58` —
  código, equipo, TAG, fluido, fecha de toma, punto de muestreo, pruebas pedidas,
  validadas, informadas y etapa.
- `labo_new/app/Http/Controllers/LabManagement/ReceptionController.php:424-432` —
  la acción.

**Consecuencia.** La planilla ya no sirve como acta de la entrega: no dice qué
envases entraron, si estaban conformes, quién autorizó el ingreso ni qué informes
salieron.

---

## 8. La baja de una recepción no arrastra su contenido — AUSENTE

**Qué hace el viejo.** Al eliminar una remisión se marcan como eliminados, en la
misma operación, sus correlativos, sus trabajos y sus informes.

- `labo_old/app/controllers/im_management/rems_controller.rb:328-334` —
  `destroy` graba el motivo y llama a `remove_rem_correlatives`.
- `labo_old/app/models/rem.rb:327-339` — marca `deleted: 1` en `rem_jobs`,
  `rem_correlatives` y `rem_reports`.

**Qué hay en el nuevo.** Solo se da de baja la recepción.

- `labo_new/app/Http/Controllers/LabManagement/ReceptionController.php:451-468` —
  `destroy` escribe motivo y `delete()` sobre la recepción, nada más.
- `labo_new/app/Models/Reception.php:36-53` y `labo_new/app/Models/Sample.php:35-40` —
  ninguno declara un evento `deleting` que propague la baja.
- `labo_new/app/Http/Controllers/LabManagement/WorksheetController.php:336-350` —
  el selector de la bancada ofrece las `SampleTest` pendientes **sin** excluir
  las de recepciones dadas de baja.
- `labo_new/app/Http/Controllers/LabManagement/SampleReportController.php:101-116` —
  el listado global de informes une con `receptions` pero solo filtra
  `whereNull('samples.deleted_at')`; no filtra `receptions.deleted_at`.

**Consecuencia.** Después de dar de baja una entrega, sus muestras se siguen
pudiendo cargar en la bancada y sus informes se siguen listando: el laboratorio
puede emitir un papel de una recepción que ya no existe.

---

## 9. "Aplicar a todas" reemplaza el pedido en vez de agregarse — PARCIAL

**Qué hace el viejo.** El botón "Forzar Pruebas" marca las pruebas elegidas en
todas las muestras de la entrega **sin tocar** las que ya estaban marcadas: es
aditivo.

- `labo_old/app/controllers/im_management/automates_controller.rb:157-166` —
  `rem_jobs...update_all(state: 1)` sobre las seleccionadas, y solo sobre ellas.
- `labo_old/app/views/im_management/rems/partials/_form_show_list_tests.html.erb:9` —
  el botón que lleva ahí.

**Qué hay en el nuevo.** La operación manda la lista completa y da de baja lo que
no viene, para todas las muestras a la vez.

- `labo_new/app/Services/Lab/ReceptionService.php:149-163` — las pruebas
  pendientes que no están en la lista pasan a `cancelled` (las que ya tienen
  trabajo hecho sí se conservan, `:154-159`).
- `labo_new/app/Services/Lab/ReceptionService.php:182-192` — `requestTestsForMany`
  aplica esa misma lógica muestra por muestra.
- `labo_new/resources/js/Components/Receptions/AssignTestsModal.vue:69-73` — al
  abrirlo para "todas", la selección arranca **vacía**.
- `labo_new/resources/lang/es/receptions.php:144` — la advertencia lo dice:
  "Lo que se marque REEMPLAZA el pedido de todas las muestras".

**Consecuencia.** Si las veinte muestras de una entrega tienen pedidos distintos y
hay que sumarles una prueba a todas, no hay forma de hacerlo en un paso: aplicar a
todas da de baja lo pendiente de cada una.

---

## 10. La pantalla ancha de recepciones (`rem_fulls`) — AUSENTE

**Qué hace el viejo.** Una segunda pantalla del mismo registro con treinta y una
columnas: los quince contadores de envases, las tres verificaciones físicas, las
observaciones, quien autoriza, el total de envases, el total de muestras y los
cuatro chequeos de avance, todo en una fila por entrega.

- `labo_old/app/controllers/im_management/rem_fulls_controller.rb:138-147` — el
  controlador (comparte el modelo `Rem`).
- `labo_old/app/views/im_management/rem_fulls/partials/_table.html.erb:5-40` —
  los encabezados; `:77-137` — las celdas.
- `labo_old/config/routes.rb:89` — `resources :rems, :rem_fulls`.

**Qué hay en el nuevo.** Nada equivalente. Las tres verificaciones físicas
existen en la base y se ven **solo** en la ficha de una recepción:

- `labo_new/resources/js/Components/Receptions/ReceptionHeader.vue:42` — los
  chips de `container_ok` / `volume_ok` / `label_ok`.
- `labo_new/resources/js/Pages/Receptions/config/columns.js:18-119` — las once
  columnas del listado, ninguna de ellas de verificación física.
- `labo_new/app/Exports/LabManagement/Receptions/ReceptionSamplesExport.php:47-58` —
  tampoco están en la descarga.

**Consecuencia.** Para saber si las entregas de la semana llegaron con el envase y
el volumen conformes hay que abrir una por una: no hay listado ni descarga que lo
muestre junto.

---

## 11. La fecha de entrega comprometida era obligatoria — PARCIAL

**Qué hace el viejo.** El formulario la exige, y de ella salen los días
restantes y el color de urgencia del listado.

- `labo_old/app/views/im_management/rems/partials/_form_new.html.erb:14-18` —
  "Fecha de Entrega (*)" con `data-parsley-required`.
- `labo_old/app/models/rem.rb:99-112` — `days_remaining` y `urgency_class`
  (rojo ≤ 2 días, ámbar 3-4, verde > 4).

**Qué hay en el nuevo.** La columna es opcional.

- `labo_new/app/Http/Controllers/LabManagement/ReceptionController.php:491` —
  `'due_at' => ['nullable','date','after_or_equal:received_at']`.
- `labo_new/resources/js/Pages/Receptions/Index.vue:59-78` — sin `due_at` no se
  calculan los días restantes y la celda queda en raya.

**Consecuencia.** Una entrega registrada sin fecha comprometida desaparece de la
columna por la que se decide qué se trabaja hoy, y quedaría fuera del indicador
de entregas a tiempo previsto en C8 del checklist.

---

## 12. Etiquetas del envase con QR — DECIDIDO

Cerrado en [`../12-CHECKLIST.md`](../12-CHECKLIST.md) §C6 y en
[`E-cobertura-tablas.md:178`](E-cobertura-tablas.md): la tabla `stickers` figura
como **NO PORTADA**, con el reemplazo ya definido (QR derivado del código de la
muestra, impresión por lote, tamaño de etiqueta como dato) y ubicado en la fase
10 del plan maestro.

Evidencia del viejo, para quien tenga que implementarlo:

- `labo_old/app/controllers/im_management/rem_correlatives_controller.rb:44-55` —
  `print_correlative`, una etiqueta por ventana emergente.
- `labo_old/app/views/im_management/rem_correlatives/print_correlative.html.erb:42-62` —
  la etiqueta, con el responsable **escrito en el HTML** ("Flor Palacios").
- `labo_old/app/models/sticker.rb:215-227` — `generate_qr_link`, que arma
  `"https://lab.softwarebu.com/im_management/rems/" + id` (URL de producción
  clavada en el modelo, apuntando a la remisión padre detrás de un login).
- `labo_old/app/controllers/im_management/stickers_controller.rb:168-183` — el
  CRUD paralelo, con su propio QR.

En el nuevo no existe ninguna ruta de impresión de etiquetas
(`labo_new/routes/lab_management.php:272-390`).

---

## 13. Asignar un equipo de otro cliente — DECIDIDO

**Qué hacía el viejo.** El desplegable de series filtraba por el cliente de la
remisión, pero un enlace al pie abría la misma pantalla con **todos** los
transformadores del sistema, y el guardado no verificaba nada.

- `labo_old/app/views/im_management/rem_correlatives/partials/_form_edit.html.erb:38-40` —
  "Si desea asignar cualquier Serie de Transformador, por favor click aquí"
  (`?transformer_id=0`).
- `labo_old/app/views/im_management/rem_correlatives/partials/_form_edit_all_transformers.html.erb:128` —
  el selector con `@all_transformers`.
- `labo_old/app/controllers/im_management/rem_correlatives_controller.rb:117-121` —
  `@transformers` (del cliente) y `@all_transformers` (todos) se cargan juntos.
- `labo_old/app/controllers/im_management/rem_correlatives_controller.rb:82-92` —
  `update` con `permit!`, sin verificar a quién pertenece el equipo.

**Qué hay en el nuevo.** Se rechaza del lado del servidor, con la decisión
escrita.

- `labo_new/app/Services/Lab/ReceptionService.php:194-222` — `assignEquipment`
  verifica que el equipo sea del cliente de la recepción y, si no, rebota con
  `receptions.errors.equipment_not_of_customer`.
- `labo_new/app/Http/Controllers/LabManagement/ReceptionController.php:268-274` —
  sin cliente no se ofrece ningún equipo.

**Consecuencia.** Si el transformador está cargado bajo otro cliente, primero hay
que corregir su ficha. Es el comportamiento buscado: la escapatoria del viejo era
la vía por la que una muestra terminaba colgada del equipo de otra empresa.

---

## Lo que se verificó y **no** es hueco

Se deja constancia para no volver a levantarlo:

- **Catálogo de muestreadores.** Portado como módulo completo, con país, código
  único cuando está presente, papelera y exportaciones
  (`labo_new/database/migrations/2026_07_29_041836_create_samplers_table.php`,
  `app/Http/Controllers/BusinessManagement/SamplerController.php`,
  `app/Http/Requests/BusinessManagement/Sampler/StoreSamplerRequest.php:51-64`).
  El viejo era `samplers_controller.rb` + `sampler.rb:162`.
- **Bloqueo del registro.** El `state` 0/1 del viejo
  (`rems_controller.rb:228-236`, `_form_validate.html.erb:9-25`) equivale al
  candado `Lockable` (`ReceptionController.php:56-65`), con alcance por rol.
- **Confirmación irreversible de los correlativos.** La casilla "Comprendo que el
  Número de Correlativos no se pueden modificar"
  (`_form_approve.html.erb:317-323`) equivale al `Popconfirm` de
  `ConfirmSamplesCard.vue:65-71`, con el rango de números anunciado antes.
- **Las dos pestañas Muestras / Informes** de la ficha
  (`rems/admin.html.erb:56-67`) están en `Receptions/Show.vue:499-520`.
- **Los cuatro iconos de avance** del listado ya están resueltos y documentados
  en C9 del checklist (`ReceptionController.php:96-111`).
- **Baja de una muestra suelta con motivo** (`rem_correlatives_controller.rb:30-41`)
  está en `ReceptionController::destroySample` (`:394-415`), y además protegida
  cuando ya salió un informe.
- **La vista `rem_correlatives/index5.html.erb`** (13 líneas) y las cuatro
  parciales `_notification*.html.erb` son avisos emergentes y código muerto: no
  aportan función.

---

## Procedencia de este documento

Lectura directa del código del sistema anterior en `/home/user/labo_old`
(solo lectura, no se modificó nada) y del sistema nuevo en `/workspace/labo_new`,
el 2026-08-02. Cada afirmación tiene su `archivo:línea` en los dos lados. Las
decisiones ya cerradas se citan desde [`../12-CHECKLIST.md`](../12-CHECKLIST.md),
[`E-cobertura-tablas.md`](E-cobertura-tablas.md) y
[`M-campos-obligatorios.md`](M-campos-obligatorios.md) en lugar de repetirse.
