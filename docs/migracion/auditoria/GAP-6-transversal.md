# GAP-6 — El bloque transversal: menú, usuarios, permisos, configuración y auditoría

> **Qué se audita.** Todo lo que en el sistema Rails viejo no era ni recepción,
> ni bancada, ni informe: el **menú lateral completo** (el inventario real de
> módulos), el modelo de permisos (`accesses` + `profiles` + `grants`), la
> pantalla de Configuración, el registro de auditoría, el almacén y los filtros
> globales del `ApplicationController`.
>
> **Archivos del viejo revisados: 47.** 25 controladores (los 14 del alcance
> leídos completos, más los 11 de `pr_management/**`: 3 completos y 8 por sus
> rutas y sus comprobaciones de permiso), 6 plantillas de `app/views/layouts/`,
> 7 vistas y parciales de `configurations`, `users` y `authentications`, 8
> modelos (`user`, `country`, `access`, `profile`, `profile_access`, `audit`,
> `stock`, `stock_detail`) y `config/routes.rb`. Se cotejó además contra los
> volcados de `docs/migracion/esquema/` (los **66** accesos y los **4** perfiles
> reales de producción).
>
> **El sistema viejo no se modificó.** Este archivo es lo único que se escribió.
>
> **Lo ya documentado se marca DECIDIDO y no se vuelve a explicar.** Las
> referencias van a [`12-CHECKLIST.md`](../12-CHECKLIST.md),
> [`E-cobertura-tablas.md`](E-cobertura-tablas.md),
> [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md) y
> [`00-PLAN-MAESTRO.md`](../00-PLAN-MAESTRO.md).

---

## 0. Los números, en una pantalla

| | |
|---|---|
| Entradas hoja del menú viejo | **43** |
| Con equivalente completo en el nuevo | **18** |
| Con equivalente parcial | **8** |
| **Sin equivalente** | **17** |

De los 17 sin equivalente, **13 ya están documentados** como fase futura o como
punto abierto del checklist (reportes gerenciales, envío a TR APP, almacén,
etiquetas, bitácora de temperaturas). Los **4 restantes** no figuraban en ningún
documento previo y son el aporte de esta auditoría: el catálogo de sistemas de
expansión sin pantalla, el detector de valores vacíos, la exportación del
registro de auditoría y la búsqueda dentro de los cambios auditados.

---

## 1. El menú lateral, entrada por entrada

Fuente del viejo: `labo_old/app/views/layouts/_app_sidebar_left_menus.html.erb`
(el único que se renderiza —
`labo_old/app/views/layouts/application.html.erb:167` →
`labo_old/app/views/layouts/_app_sidebar_left.html.erb:35`). Fuente del nuevo:
`resources/js/Layouts/AppLayout.vue`.

> **Tres plantillas de menú del viejo son código muerto y no se cotejan**:
> `_app_sidebar_left_menus_pending.erb` (nadie la renderiza; es la versión
> anterior del mismo menú), `_sidebar_tests.html.erb` (la lista de pruebas
> clavada a mano, que solo se renderiza desde la anterior — `:228`) y
> `_app_sidebar_right.html.erb` (contenido de demostración de la plantilla
> comprada: "Stephen Tran", "Billing & Reports"; su render está comentado en
> `application.html.erb:182`). Igual que `_app_settings.html.erb`, comentado en
> `application.html.erb:442`.

### 1.1 Sección «Dashboard de Alertas» (`:4`)

| Entrada del viejo | Línea | ¿Existe en el nuevo? | Dónde |
|---|---|---|---|
| Alerta de Pendientes | `:7-9` | **PARCIAL** | `AppLayout.vue:635` («Dashboard») existe, pero el tablero del laboratorio devuelve vacío: `app/Http/Controllers/DashboardManagement/DashboardController.php:146-149` |

### 1.2 Sección «Ingreso de Muestras» (`:17`)

| Entrada del viejo | Línea | ¿Existe en el nuevo? | Dónde |
|---|---|---|---|
| Registros (`im_management/rems`) | `:20-22` | **SÍ** | «Recepción de muestras», `AppLayout.vue:698` |
| — insignia «Urgente: N» del propio menú | `:25-29` | **NO** | El único `badge` del menú nuevo es el de Aprobaciones (`AppLayout.vue:645`) |
| Control de Temperaturas › Fisicoquímicos › Listado | `:50` | **NO** | — (C1) |
| Control de Temperaturas › Cromatografías › Listado | `:58` | **NO** | — (C1) |
| Control de Stickers › Listado de Stickers | `:79` | **NO** | — (C6) |
| Ajustes Adicionales › Clientes › Listado | `:101` | **SÍ** | «Clientes», `AppLayout.vue:667` |
| Ajustes › Transformadores › Listado | `:119` | **SÍ** | «Equipos», `AppLayout.vue:672` |
| Ajustes › Transformadores › Importar Transformadores | `:126` | **PARCIAL** | `App\Imports\BusinessManagement\Equipment\EquipmentImport` — 6 columnas contra 15 |
| Ajustes › Transformadores › Listado de Fabricantes | `:130` | **SÍ** | «Marcas», `AppLayout.vue:823` |
| Ajustes › Transformadores › Listado de Tipos de Equipo | `:133` | **SÍ** | «Tipos de transformador», `AppLayout.vue:818` (solo super) |
| Ajustes › Transformadores › Listado de Tipos de Aceite | `:137` | **SÍ** | «Tipos de aceite», `AppLayout.vue:813` (solo super) |
| Ajustes › Transformadores › Listado de Marcas de Aceite | `:141` | **SÍ** | «Listas del informe», `AppLayout.vue:757` |
| Ajustes › Transformadores › Listado de Conmutadores | `:144` | **SÍ** | «Tipos de conmutador», `AppLayout.vue:833` (solo super) |
| Ajustes › Transformadores › **Listado de Sistemas de Expansión** | `:147` | **NO** | Hay tabla y sembrador; no hay ruta, controlador ni pantalla |
| Ajustes › Transformadores › Listado de Unidades de Medida para aceite | `:150` | **SÍ** | «Listas del informe», `AppLayout.vue:757` |
| Ajustes › Transformadores › Listado de Puntos de Muestreo | `:153` | **SÍ** | «Listas del informe», `AppLayout.vue:757` |
| Ajustes › Personal de Laboratorio que firma | `:163` | **SÍ** | «Firmas», `AppLayout.vue:792` |
| Ajustes › Muestreadores › Personal de Muestreo | `:172` | **SÍ** | «Muestreadores», `AppLayout.vue:787` |
| Ajustes › Firmas para Reporte › Firmas | `:181` | **SÍ** | «Firmas», `AppLayout.vue:792` |

### 1.3 Sección «Pruebas de Muestras» (`:200`)

El menú viejo se generaba con un bucle sobre los grupos y sus pruebas
(`:204-279`), así que **crecía con el catálogo**: cada prueba nueva agregaba
cuatro entradas. El nuevo tiene una entrada por concepto y filtra.

| Entrada del viejo | Línea | ¿Existe en el nuevo? | Dónde |
|---|---|---|---|
| {grupo} › {prueba} › Muestras | `:239` | **SÍ** | «Hojas de trabajo», `AppLayout.vue:704` (una entrada, no N) |
| {grupo} › {prueba} › Valores Constantes | `:250` | **PARCIAL** | Editable en la ficha de la columna; se perdió su rastro de auditoría (H-1 de [`A-columnas-y-constantes.md`](A-columnas-y-constantes.md)) |
| {grupo} › {prueba} › Límite de Tendencias | `:260` | **PARCIAL** | «Cartas de control», `AppLayout.vue:719`; los 45 valores reales no están migrados (B3) |
| {grupo} › {prueba} › Tendencias | `:267` | **SÍ** | «Cartas de control», `AppLayout.vue:719` |
| Ajustes Adicionales › Categorías de Módulos › Registros | `:303` | **SÍ** | «Grupos de pruebas», `AppLayout.vue:767` |
| Ajustes Adicionales › Módulos › Registros | `:312` | **SÍ** | «Pruebas», `AppLayout.vue:772` |
| Ajustes Adicionales › Columnas de los Módulos › Registros | `:322` | **SÍ** | Sub-pantalla de la prueba: `routes/lab_management.php:191` |
| Ajustes Adicionales › Tipos de Columnas › Registros | `:331` | **NO** | Los cuatro tipos son un enumerado del código; dado por PORTADA en [`E-cobertura-tablas.md`](E-cobertura-tablas.md) §2.3 fila 10 |

### 1.4 Sección «Reportes» (`:347`)

| Entrada del viejo | Línea | ¿Existe en el nuevo? | Dónde |
|---|---|---|---|
| Reporte OTD (On Time Deliver) | `:368` | **NO** | — (C8 / fase 11) |
| Análisis de Laboratorio | `:377` | **NO** | — (C8 / fase 11) |
| Registro de Muestras Detallado | `:386` | **NO** | — (C8 / fase 11) |
| Formato de Registro de Ingreso de Muestras | `:395` | **NO** | — (C8 / fase 11) |
| Registro de Muestras | `:404` | **NO** | — (C8 / fase 11) |
| Reportes Entregados | `:413` | **NO** | — (C8 / fase 11) |
| Listado de Reportes | `:422` | **PARCIAL** | «Informes de ensayo», `AppLayout.vue:713` — es el listado, no el Excel gerencial |

### 1.5 Sección «Gestión en TR APP» (`:437`)

| Entrada del viejo | Línea | ¿Existe en el nuevo? | Dónde |
|---|---|---|---|
| Importar Transformadores al TR APP | `:449` | **NO** | — (fase 7) |
| Importar Cromatografías al TR APP | `:458` | **NO** | — (fase 7) |
| Importar FQ al TR APP | `:467` | **NO** | — (fase 7) |
| Importar Furanos al TR APP | `:476` | **NO** | — (fase 7) |

### 1.6 Sección «Inventario de Laborario» (`:493`, con la errata del viejo)

| Entrada del viejo | Línea | ¿Existe en el nuevo? | Dónde |
|---|---|---|---|
| Listado de Stock | `:497` | **NO** | — (C7 / fase 10) |
| Seguimiento de Stock | `:506` | **NO** | — (C7 / fase 10) |

### 1.7 Sección «Auditoría del Sistema» (`:519`)

| Entrada del viejo | Línea | ¿Existe en el nuevo? | Dónde |
|---|---|---|---|
| Auditoría | `:522` | **PARCIAL** | «Logs del sistema», `AppLayout.vue:926` — sin exportación, sin búsqueda por texto en los cambios y con el registro identificado por id numérico |

### 1.8 Sección «Ajustes del Sistema» (`:536`)

| Entrada del viejo | Línea | ¿Existe en el nuevo? | Dónde |
|---|---|---|---|
| Configuración (portada de cuatro tarjetas) | `:539` | **PARCIAL** | La portada no existe; sus cuatro destinos sí: «Ajustes» `AppLayout.vue:987`, «Países» `:977`, «Usuarios» `:908`, «Perfiles» `:913` |
| — Países (tarjeta del hub) | `configurations/index.html.erb:15` | **SÍ** | «Países», `AppLayout.vue:977` (solo super) |

### 1.9 Lo que el menú nuevo agrega y el viejo no tenía

No es alcance de esta auditoría, pero conviene dejarlo para que la comparación
no se lea como una pérdida neta: Aprobaciones, Mis solicitudes, Plantillas de
análisis, Parámetros, Instrumentos, Laboratorios, las cuatro entradas de
conmutador, Automatizaciones, Mensajes, Bandeja, Mi workspace, Workspaces,
Planes, Módulos, Regiones, Idiomas, Locales y **Ajustes** (el viejo no tenía
ningún parámetro configurable, ver §4).

---

## 2. Tabla resumen de huecos

| # | Qué falta | Clasificación | Consecuencia |
|---|---|---|---|
| 1 | La portada de alertas: ocho contadores accionables | **DECIDIDO** (fase 11) | Al entrar no se ve qué está atrasado; hay que abrir listado por listado. Cinco de los ocho contadores no están enumerados en la fase |
| 2 | Detector de resultados vacíos o `NaN` | **AUSENTE** | Un valor que el instrumento escribió como "NaN" no lo denuncia nadie hasta que sale impreso |
| 3 | Contador «pendiente de bloquear» por grupo de pruebas | **PARCIAL** | Nadie ve cuántas hojas quedaron sin validar sin entrar a filtrarlas |
| 4 | Insignia de urgencia en el propio menú (≤2 días) | **AUSENTE** | La urgencia solo se ve entrando al listado y aplicando el filtro |
| 5 | Pantalla de Sistemas de Expansión (`transformer_preservations`) | **AUSENTE** | El catálogo alimenta un desplegable de la ficha del equipo y solo se puede cambiar por sembrador o SQL |
| 6 | Exportación del registro de auditoría | **AUSENTE** | No se le puede entregar el rastro a un auditor externo sin darle acceso a la base |
| 7 | Búsqueda por texto dentro de los cambios auditados | **AUSENTE** | No se puede responder "¿quién tocó este valor?" sin saber de antemano módulo e id |
| 8 | Descripción legible del registro auditado | **PARCIAL** | El listado dice `1287` donde el viejo decía «Cliente: ACME S.A.» |
| 9 | Auditoría de `TestField` (la definición de columna) | **DECIDIDO** (H-1 de `A-columnas-y-constantes.md`) | Cambiar una fórmula o un límite no deja rastro |
| 10 | Auditoría de `Standard` y de `WorksheetRow` | **AUSENTE** | Dos eslabones que el viejo sí auditaba (`norm`, `lab_detail`) dejaron de dejar rastro |
| 11 | Cambio de contraseña de otro usuario por el administrador | **AUSENTE** | Un usuario que perdió el acceso depende de que el correo de recuperación llegue |
| 12 | Ingreso por nombre de usuario (`username`) | **DECIDIDO** (`E-cobertura-tablas.md` §2.1 fila 4) | Se entra por correo; el `username` histórico no viaja |
| 13 | Documento, teléfono y apellidos partidos del usuario | **DECIDIDO** (ídem) | El analista que firma se identifica por nombre, no por documento |
| 14 | Alta de un permiso nuevo desde la pantalla (`accesses` + `grants`) | **PARCIAL** | Las acciones son una constante del código; un verbo nuevo exige despliegue |
| 15 | Los siete reportes gerenciales | **DECIDIDO** (C8 / fase 11) | — |
| 16 | Los cuatro asistentes de envío a TR APP | **DECIDIDO** (fase 7) | — |
| 17 | Almacén (2 pantallas, 5 tablas) | **DECIDIDO** (C7 / fase 10) | — |
| 18 | Etiquetas con QR | **DECIDIDO** (C6 / fase 10) | — |
| 19 | Bitácora de temperaturas (2 pantallas) | **DECIDIDO** (C1) | — |
| 20 | CRUD de «Tipos de Columnas» | **DECIDIDO** (`E-cobertura-tablas.md` §2.3 fila 10) | — |

---

## 3. Los huecos, uno por uno

### H1 — La portada de alertas: ocho contadores accionables · DECIDIDO (fase 11)

**El viejo.** La página a la que caía todo usuario al iniciar sesión
(`labo_old/config/routes.rb:226` y `:44` del controlador de sesión) era un
tablero de alertas, no un saludo. Ocho tarjetas, **cada una enlazada a su
listado ya filtrado**:

| Contador | Qué cuenta | Evidencia |
|---|---|---|
| Órdenes de Servicio | recepciones sin número de OS | `authentications_controller.rb:13` + `_dashboard_content_first.html.erb:3-10` |
| Confirmar Nº de Correlativos | recepciones con `correlative_confirmed = 0` | `:13` + `:14-21` |
| Nº Serie de Transformadores | correlativos con `pending_tr = 1` | `:15` + `:25-32` |
| Pruebas a Realizar | correlativos con `pending_tk = 1` | `:15` + `:36-43` |
| Resultado de Muestras | correlativos con `pending_va = 1` | `:15` + `:49-56` |
| Alta Prioridad de Muestreo | recepciones a ≤ 2 días de vencer | `application_controller.rb:115-117` + `:61-77` |
| Pendiente de Reporte | correlativos sin ningún informe | `:17` + `:81-88` |
| Reportes sin Bloquear | informes en estado 1 | `:19` + `:91-99` |

**El nuevo.** El elemento de menú existe
(`resources/js/Layouts/AppLayout.vue:635-639`), pero el bloque del laboratorio
está vacío por declaración explícita:
`app/Http/Controllers/DashboardManagement/DashboardController.php:146-149`
(`return [];`, con el comentario "Fase 11: acá van los indicadores del
laboratorio").

**Estado.** La fase 11 del plan maestro
([`00-PLAN-MAESTRO.md:304-313`](../00-PLAN-MAESTRO.md)) enumera "Trabajos
pendientes, informes sin emitir, muestras vencidas": cubre tres de los ocho.
**Los otros cinco** (OS sin número, correlativos sin confirmar, series de
transformador pendientes, pruebas por asignar, informes sin bloquear) no están
escritos en ningún lado. Se listan aquí para que la fase 11 no se cierre creyendo
que eran tres.

**Consecuencia.** Hasta que la fase 11 exista, el trabajo atrasado solo se ve
abriendo cada listado y filtrando a mano.

### H2 — Nadie denuncia un resultado vacío o `NaN` · AUSENTE

**El viejo.** La misma portada corría una consulta que buscaba mediciones
guardadas como cadena vacía o literalmente `"NaN"`, y pintaba **una tarjeta por
prueba afectada, enlazada a su listado**:

```
AND ( TRIM(lab_sub_details.name) = '' OR lab_sub_details.name = 'NaN' )
```

`labo_old/app/controllers/user_management/authentications_controller.rb:21`, y
la tarjeta «Revisar Campos Vacios» en
`labo_old/app/views/user_management/authentications/partials/_dashboard_content_second.html.erb:27-41`.

**El nuevo.** No hay equivalente. Ni consulta, ni comando, ni panel. Buscado en
`app/Http/Controllers/DashboardManagement/DashboardController.php` (el tablero
del laboratorio devuelve vacío) y en `routes/console.php`.

**Por qué importaba.** El `"NaN"` no es un caso de laboratorio: es lo que dejaba
el JavaScript de cálculo del viejo cuando una división quedaba sin operandos. El
nuevo calcula en el servidor y guarda `value_num` como número, así que la forma
exacta de la falla ya no puede repetirse — pero la **familia** de la falla sí (un
resultado que quedó nulo y nadie lo mira). El nuevo tiene dónde detectarla:
`results.value_num IS NULL` sobre pruebas ya publicadas.

**Consecuencia.** Un ensayo con un valor faltante no lo denuncia nadie; se
descubre cuando sale impreso en el informe del cliente.

### H3 — Cuántas hojas quedaron sin validar, por grupo · PARCIAL

**El viejo.** Una tarjeta por grupo de pruebas con la cuenta de hojas en estado
1 (cargadas y sin bloquear):
`labo_old/app/views/user_management/authentications/partials/_dashboard_content_second.html.erb:10-17`.

**El nuevo.** El estado existe y es mejor (`worksheets.status` +
`worksheets.validate` como permiso propio,
`database/seeders/RolesAndPermissionsSeeder.php:169`), y el listado se puede
filtrar. Lo que no hay es el **número a la vista sin entrar**.

**Consecuencia.** Menor: el dato es alcanzable, cuesta tres clics más.

### H4 — La insignia de urgencia salió del menú · AUSENTE

**El viejo.** El propio elemento «Registros» del menú llevaba pegada la cuenta
de recepciones a dos días o menos de vencer:
`labo_old/app/views/layouts/_app_sidebar_left_menus.html.erb:25-29`, alimentada
por un `before_action` global —
`labo_old/app/controllers/application_controller.rb:11,115-117`.

**El nuevo.** El menú tiene mecanismo de insignia y lo usa para Aprobaciones
(`resources/js/Layouts/AppLayout.vue:645`) y para Mis solicitudes (`:657`), pero
no para recepciones. El dato existe del lado del servidor: el listado acepta el
filtro `urgent`
(`app/Http/Controllers/LabManagement/ReceptionController.php:120`) y hay
`due_at` (`:491`) e `is_urgent` (`:496`).

**Consecuencia.** La urgencia dejó de ser pasiva. Hay que acordarse de ir a
mirarla, que es exactamente lo que una insignia evita.

### H5 — «Sistemas de Expansión» perdió su pantalla · AUSENTE

**El viejo.** CRUD completo con entrada de menú propia:
`labo_old/app/views/layouts/_app_sidebar_left_menus.html.erb:147-148`, ruta
declarada en `labo_old/config/routes.rb:64-67`, controlador
`labo_old/app/controllers/im_management/transformer_preservations_controller.rb`,
modelo auditado (`labo_old/app/models/transformer_preservation.rb`).

**El nuevo.** La tabla está (`database/migrations/2026_05_30_100050_create_transformer_preservations_table.php`),
el modelo está y **es auditable** (`app/Models/TransformerPreservation.php`), el
sembrador está (`database/seeders/TransformerPreservationsSeeder.php`) y el
valor se usa como desplegable de la ficha del equipo
(`app/Http/Controllers/BusinessManagement/EquipmentController.php:200`). **No
hay ruta, ni controlador, ni página, ni entrada de menú**: verificado con una
búsqueda de `preservation` sobre `routes/*.php`,
`resources/js/Layouts/AppLayout.vue` y `resources/lang/es/sidebar.php` — cero
resultados; y `find app/Http/Controllers -iname '*Preservation*'` no devuelve
nada.

Es el único de los diez catálogos del submenú «Transformadores» del viejo que
quedó sin destino. Los otros nueve están: seis con módulo propio y tres dentro
de «Listas del informe».

[`E-cobertura-tablas.md`](E-cobertura-tablas.md) §2.7 fila 32 marca la tabla
como **PORTADA** — y como tabla lo es. El hueco es de **pantalla**, no de
esquema, y por eso no aparecía en esa auditoría.

**Consecuencia.** Agregar o corregir un sistema de expansión (el laboratorio
tiene cuatro: sellado, conservador, respiración libre…) exige un sembrador o un
`UPDATE` a mano. Es el mismo camino por el que un catálogo termina desactualizado
y alguien escribe el valor en otro campo.

### H6 — El registro de auditoría no se puede exportar · AUSENTE

**El viejo.** La pantalla de auditoría respondía a `.xls` con **todos** los
resultados del filtro, no solo la página visible:
`labo_old/app/controllers/audit_management/audits_controller.rb:36-47`
(`@export_results = @query.result(distinct: true)` y el bloque
`format.xls { send_data ... :filename => "Reporte_de_auditoría_..." }`).

**El nuevo.** Una sola ruta, de lectura:
`routes/system_management.php:26`
(`Route::get('audit_logs', [AuditLogController::class, 'index'])`). No hay
`export_csv` / `export_excel` / `export_pdf`, que sí existen para prácticamente
todos los demás módulos (comparar con `routes/user_management.php:49-52`). La
página tampoco los ofrece: `resources/js/Pages/AuditLogs/Index.vue` no menciona
exportación.

**Consecuencia.** Para entregarle el rastro a un auditor externo —que es el uso
del registro— hay que darle acceso al sistema o consultar la base a mano. Para
ISO/IEC 17025 el rastro tiene que poder salir.

### H7 — No se puede buscar dentro de los cambios · AUSENTE

**El viejo.** Además de usuario y rango de fechas, el buscador aceptaba **texto
libre contra el contenido del cambio**:
`labo_old/app/controllers/audit_management/audits_controller.rb:28`
(`@query.audited_changes_i_cont_all = @search_parameters.to_s`). Con eso se
respondía "¿quién escribió este número?" sin saber en qué módulo estaba.

**El nuevo.** Los filtros son módulo, evento, usuario, **id del registro** y
rango de fechas:
`app/Http/Controllers/SystemManagement/AuditLogController.php:63-79`. Ninguno
mira dentro de `old_values` / `new_values`.

**Consecuencia.** Para investigar hay que saber de antemano el módulo y el
identificador. Si lo que se tiene es el valor sospechoso —que es el caso normal
cuando un cliente reclama—, el buscador no ayuda.

### H8 — El registro auditado se identifica por un número · PARCIAL

**El viejo.** El listado de auditoría traducía la clase a un nombre de negocio
**y resolvía el registro**: «Cliente: ACME S.A.», «Transformador: 12345»,
«Reporte de Correlativo: REP-LAB-2023-649», «Sistema de Expansion: Sellado».
Dos métodos de 60 y 90 líneas en `labo_old/app/models/audit.rb:38-108`
(`str_auditable_type`) y `:110-215` (`str_auditable_type_details`).

**El nuevo.** La columna del listado es el id crudo:
`resources/js/Pages/AuditLogs/Index.vue:124`
(`dataIndex: 'auditable_id'`), y el cajón de detalle muestra el nombre de la
clase PHP y el número: `:316-318`.

**Lo que sí mejoró.** El **diff** de cada cambio sí está humanizado, con
etiquetas traducidas y claves foráneas resueltas a su nombre:
`app/Http/Resources/AuditLogResource.php:59-80`. Es más de lo que el viejo hacía
con los valores. Lo que falta es el mismo tratamiento para el **sujeto** de la
fila.

**Consecuencia.** Un listado de auditoría que dice `1287` obliga a irse a otra
pantalla para saber de qué registro habla. El viejo, con todo lo demás roto,
esto lo hacía bien.

### H9 — Auditoría de la definición de columna · DECIDIDO

Cubierto por **H-1** de
[`A-columnas-y-constantes.md`](A-columnas-y-constantes.md) (§793-823 y la fila 1
de su tabla de prioridades, §1209). No se repite aquí.

Un matiz de esta auditoría: H-1 propone agregar el rastro también a
`TestFieldOption`. El viejo **no** auditaba su equivalente
(`labo_old/app/models/lab_category_sub_detail_option.rb` no declara `audited`),
así que eso es una mejora, no una recuperación. Lo que sí es recuperación es
`TestField`: `labo_old/app/models/lab_category_sub_detail.rb:18-19` lo declara
auditado dos veces.

### H10 — Dos eslabones más perdieron su rastro · AUSENTE

Del barrido de los 37 modelos con `audited` del viejo contra los 44 con el trait
`Auditable` del nuevo, la cobertura mejoró en general. **Dos casos van al
revés**:

| Modelo viejo | Auditado allá | Modelo nuevo | Auditado acá |
|---|---|---|---|
| `norm` | sí (`labo_old/app/models/norm.rb`) | `app/Models/Standard.php` | **no** |
| `lab_detail` | sí (`labo_old/app/models/lab_detail.rb`) | `app/Models/WorksheetRow.php` | **no** |

Verificación en el nuevo: ninguno de los dos archivos contiene `Auditable`.

**Lo que NO es regresión** (el viejo tampoco los auditaba, así que la paridad se
mantiene): `WorksheetValue` / `Result` (el viejo `lab_sub_detail` no está
auditado), `SpecLimit` (el viejo `rem_report_detail` tampoco) y `TestFieldOption`
(ver H9).

**Caso aparte, resuelto: el informe.** `rem_report` estaba auditado allá y
`app/Models/SampleReport.php` no usa el trait — pero los dos actos que importan
sí quedan escritos a mano, con más contexto que el genérico:
`app/Services/Lab/SampleReportService.php:153-164` (`report_issued`) y `:220-230`
(`report_unissued`). No cuenta como hueco; sí queda sin rastro la edición de la
cabecera de un borrador.

**Consecuencia.** Cambiar la norma con la que se evalúa una prueba
(`Standard`) no deja constancia de quién ni cuándo. Para un laboratorio
acreditado eso es exactamente lo que un auditor pide ver.

### H11 — El administrador ya no puede cambiar la contraseña de un usuario · AUSENTE

**El viejo.** Pantalla dedicada, con su propio permiso (el acceso 7, «Usuarios
Cambiar Password»):
`labo_old/app/controllers/user_management/users_controller.rb:142-150`,
vista `labo_old/app/views/user_management/users/change_password.html.erb` y
formulario `.../partials/_form_password.html.erb:17-29`. Al guardar se disparaba
un correo al afectado
(`labo_old/app/controllers/user_management/users_controller.rb:85-87`).

**El nuevo.** Solo autoservicio:
`app/Http/Controllers/ProfileController.php:386-405` (`updatePassword`, que
exige la contraseña actual), más el circuito de recuperación por correo
(`routes/auth_management.php:33-38`). El controlador de usuarios no toca
contraseñas: la única mención de correo en
`app/Http/Controllers/AuthManagement/UserController.php` es la bienvenida al
crear (`:305`).

**Matiz honesto.** Esto es en buena medida **una mejora**: el formulario viejo
guardaba la contraseña también en claro, en la columna `real_password` —
`_form_password.html.erb:20`, y ya señalado en
[`11-AUDITORIA-VIEJO-VS-NUEVO.md:562`](../11-AUDITORIA-VIEJO-VS-NUEVO.md). No
hay que reponer eso.

**Consecuencia.** Queda un caso operativo sin respuesta: el usuario que no
recibe el correo de recuperación (buzón corporativo caído, dirección vieja) hoy
no tiene salida por pantalla. La forma correcta de reponerlo es un "forzar
cambio en el próximo ingreso" o un enlace de recuperación generado por el
administrador, nunca una contraseña tipeada por un tercero.

### H12 y H13 — Ingreso por `username`, documento y teléfono · DECIDIDO

Ambos cubiertos por [`E-cobertura-tablas.md`](E-cobertura-tablas.md) §2.1 fila
4. No se repiten.

Se agrega una consecuencia de pantalla que esa fila (de esquema) no menciona: el
formulario de alta del viejo pedía **N° Documento con mínimo 8 caracteres y
obligatorio**
(`labo_old/app/views/user_management/users/partials/_form_new.html.erb:49-51`) y
**Nombre de Ingreso** (`:116-118`). El formulario nuevo no tiene ninguno de los
dos. Para un laboratorio acreditado, el analista que firma un ensayo se
identifica por documento.

### H14 — Crear un permiso nuevo desde la pantalla · PARCIAL

**El viejo.** Tres CRUD encadenados: `accesses` (el módulo),
`grants` (sus acciones, que son filas hijas del mismo árbol) y `profiles` (la
asignación con casillas). El árbol tiene exactamente dos niveles:
`labo_old/app/controllers/user_management/accesses_controller.rb:8`
(`Access.where(parent_id: 0)`) y
`labo_old/app/controllers/user_management/grants_controller.rb:6-13`. Un
administrador podía inventar un acceso y colgarle acciones sin tocar código.

**El nuevo.** Los permisos se **generan** a partir de los módulos registrados y
un juego fijo de acciones:
`database/seeders/RolesAndPermissionsSeeder.php:19` (`$actions =
\App\Observers\SystemModuleObserver::CANONICAL_ACTIONS`) y `:25-31`. Hay
pantalla para dar de alta un **módulo** («Módulos»,
`resources/js/Layouts/AppLayout.vue:962`), y el observador le crea sus permisos;
lo que no hay es forma de agregar un **verbo** nuevo (los transversales
`comments.view`, `worksheets.validate`, etc. están escritos a mano en el
sembrador, `:52-58`).

**Matiz.** El modelo nuevo es más granular donde importa: allá los cuatro CRUD
de plantillas de ensayo vivían detrás de **un solo** permiso, el 14 —
verificado: `User.authentication(session[:user_id],14)` aparece en las cinco
acciones de los cuatro controladores de
`labo_old/app/controllers/pr_management/configurations/`. Eso ya está anotado en
[`E-cobertura-tablas.md`](E-cobertura-tablas.md) §2.1 fila 1 y en los
comentarios del propio sembrador.

**Consecuencia.** Acotada, y probablemente correcta: un verbo de permiso nuevo
implica una ruta nueva, así que ya exigía despliegue. Vale dejarlo escrito para
que nadie lo descubra buscando la pantalla.

### H15 a H20 — Bloques ya documentados · DECIDIDO

Verificados contra el código del viejo y confirmados como cubiertos por
documentación previa. No se repiten aquí.

| Bloque | Evidencia del viejo | Dónde está documentado |
|---|---|---|
| Siete reportes gerenciales | `labo_old/config/routes.rb:43-47`; menú `:347-431` | C8 de [`12-CHECKLIST.md`](../12-CHECKLIST.md) + fase 11 |
| Cuatro asistentes de envío a TR APP | `labo_old/config/routes.rb:173-181`; menú `:437-489` | Fase 7 + [`06-TRAFODEX-LO-QUE-DEBE-CONSTRUIR.md`](../06-TRAFODEX-LO-QUE-DEBE-CONSTRUIR.md) |
| **Almacén** | `stocks_controller.rb` y `stock_details_controller.rb` completos; `labo_old/config/routes.rb:55-60`; menú `:493-515` | C7 de [`12-CHECKLIST.md`](../12-CHECKLIST.md) + fase 10 + [`E-cobertura-tablas.md`](E-cobertura-tablas.md) §2.9 filas 40-44 |
| Etiquetas con QR | menú `:69-87` | C6 + fase 10 + `E` fila 39 |
| Bitácora de temperaturas | menú `:40-65` | C1 + `E` §2.8 filas 37-38 |
| CRUD de «Tipos de Columnas» | menú `:326-334` | `E` §2.3 fila 10 |

**Verificación pedida del almacén.** Confirmado como fase futura y **confirmado
también el defecto de fondo que el checklist le atribuye**: `StocksController`
solo hace CRUD de la ficha (`stocks_controller.rb:59-103`) y `StockDetail` deriva
lo entregado y lo devuelto sumando movimientos
(`labo_old/app/models/stock_detail.rb:30,36`), pero **`stocks.qty` no se escribe
en ningún lado**: no aparece en ninguna de las dos clases. Y `stock_units`
efectivamente tiene ruta (`labo_old/config/routes.rb:56`) y **no tiene
controlador** (`app/controllers/stock_management/` contiene solo dos archivos).
Ambas afirmaciones de C7 quedan verificadas. **DECIDIDO, sin objeciones.**

---

## 4. Los permisos: los 66 accesos del viejo contra los del nuevo

Los 66 accesos reales salen del volcado de producción
(`docs/migracion/esquema/catalogos-definiciones.sql:16-...`, un `INSERT` por
fila). Los cuatro perfiles reales, de `:1306-1313`: Administrador Principal,
Hitachi Master, Hitachi Operadores y Hitachi Operadores - Admin.

### 4.1 El mapa

| Id | Nombre del acceso viejo | Equivalente en el nuevo | Estado |
|---|---|---|---|
| 1 | Config - Módulo Usuarios *(padre)* | módulo `users` | ✔ |
| 2 | Usuarios Buscador | `users.view` | ✔ |
| 3 | Usuarios Nuevo | `users.create` | ✔ |
| 4 | Usuarios Ver | `users.show` | ✔ |
| 5 | Usuarios Editar | `users.edit` | ✔ |
| 6 | Usuarios Eliminar | `users.delete` | ✔ |
| 7 | **Usuarios Cambiar Password** | — | **AUSENTE** (H11) |
| 8 | Config - Módulo Perfiles *(padre)* | módulo `roles` | ✔ |
| 9 | Perfiles - Buscador | `roles.view` | ✔ |
| 10 | Perfiles - Nuevo | `roles.create` | ✔ |
| 11 | Perfiles - Ver | `roles.show` | ✔ |
| 12 | Perfiles - Editar | `roles.edit` | ✔ |
| 13 | Perfiles - Eliminar | `roles.delete` | ✔ |
| 14 | Config - Módulo Accesos *(padre)* | módulo `system_modules` (solo super) | parcial (H14) |
| 15-19 | Accesos: Buscador / Nuevo / Ver / Editar / Eliminar | `system_modules.*` | parcial (H14) |
| 20-22 | Accesses - Grant: Nuevo / Editar / Eliminar | — (`CANONICAL_ACTIONS` es constante) | **PARCIAL** (H14) |
| 23 | Config - Módulo Configuración *(padre)* | grupo «Configuración del sistema» | ✔ |
| 24 | Configuración - Vista Principal | `settings` (solo super) | ✔ |
| 25 | Auditoría *(padre)* | grupo «Logs del sistema» | ✔ |
| 26 | Auditoria - Principal | `audit_logs` por rol super/admin | ✔ |
| 27 | **Auditoria - Admin** | — | **no aplica**: no se comprueba en ninguna línea del viejo |
| 28 | Pruebas de Muestras *(padre)* | grupos «Pruebas de Muestras» + «Configuración del laboratorio» | ✔ |
| 29 | PM - Configurar Limites de Grafica Tendencia | `qc_charts.edit` | ✔ |
| 30 | PM - Bloquear | `worksheets.validate` | ✔ (mejor: es permiso propio) |
| 31 | PM - Valores Constantes | `test_definitions.edit` (ficha de la columna) | parcial (H9) |
| 32 | PM - Grafica de Tendencia | `qc_charts.view` | ✔ |
| 33 | PM - Busqueda | `worksheets.view` | ✔ |
| 34 | PM - Nuevo | `worksheets.create` | ✔ |
| 35 | PM - Ver | `worksheets.show` | ✔ |
| 36 | PM- Editar | `worksheets.edit` | ✔ |
| 37 | PM - Eliminar | `worksheets.delete` | ✔ |
| 38 | PM - Ajustes Adicionaales | `test_groups.*` + `test_definitions.*` | ✔ (mejor: allá era un permiso indistinto) |
| 39 | Registro de Ingreso de Muestras *(padre)* | módulo `receptions` | ✔ |
| 40-44 | RIM: Busqueda / Nuevo / Ver / Editar / Eliminar | `receptions.view/create/show/edit/delete` | ✔ |
| 45 | RIM - Ajustes Adicionales | `customers.*`, `equipment.*`, catálogos | ✔ |
| 46 | RIM - Bloquear | `receptions.lock` (`routes/lab_management.php:364-365`) | ✔ |
| 47 | RIM - Reportes | `sample_reports` (bajo `receptions.view`) | ✔ |
| 48 | RIM - Admin Correlativo | `receptions.confirm` (`routes/lab_management.php:299`) | ✔ |
| 49 | Reportes *(padre)* | — | **AUSENTE** (C8 / fase 11) |
| 50-55 | Análisis de Lab. / Registro Detallado / Formato / Registro / Entregados / Listado | — | **AUSENTE** (C8 / fase 11) |
| 56 | Inventario *(padre)* | — | **AUSENTE** (C7 / fase 10) |
| 57-58 | Listado de Stock / Seguimiento de Stock | — | **AUSENTE** (C7 / fase 10) |
| 59 | TR APP *(padre)* | — | **AUSENTE** (fase 7) |
| 60-63 | Transformadores / Cromas / Fiquis / Furanos | — | **AUSENTE** (fase 7) |
| 64 | RIM - Control de Temperaturas | — | **AUSENTE** (C1) |
| 65 | RIM - Stickers | — | **AUSENTE** (C6) |
| 66 | Listado de Reportes OTD | — | **AUSENTE** (C8 / fase 11) |

**Recuento.** De los 66: **48 tienen equivalente**, **3 son parciales** (20-22 y
14-19 cuentan como un bloque), **14 están ausentes por fase futura** (49-63, 64,
65, 66), **1 está ausente de verdad** (el 7, cambio de contraseña por el
administrador) y **1 nunca se usó** (el 27).

### 4.2 Tres cosas que el mapa deja a la vista

**a) Los identificadores de acceso se reutilizaron entre módulos que no tienen
nada que ver.** El CRUD de Países se gatea con permisos de *Pruebas de Muestras*:
`labo_old/app/controllers/configuration_management/countries_controller.rb:12,25`
usa el 35 («PM - Ver»), `:36` el 37 («PM - Eliminar»), `:46` el 36 («PM-
Editar»), `:56` el 38 («PM - Ajustes Adicionaales») y `:95,106` el 39
(«Registro de Ingreso de Muestras», que es un **padre**). El mismo patrón en
`labo_old/app/controllers/supervisors_controller.rb:12,25`. Es decir: en el
viejo, quien podía borrar una hoja de trabajo podía borrar un país. En el nuevo
Países es solo-super (`resources/js/Layouts/AppLayout.vue:977`), lo que cierra el
agujero y a la vez **quita el catálogo de manos del administrador del
laboratorio** — vale saberlo antes de que alguien pida agregar un país.

**b) La comprobación se hacía por dos caminos distintos, con semánticas
distintas.** `User.authentication(session[:user_id], N)`
(`labo_old/app/models/user.rb:34-43`) consulta la base en cada llamada;
`user_permission.include?(N)`
(`labo_old/app/controllers/application_controller.rb:56-60`) usa el arreglo
precargado. Los ids 1, 3, 14, 16-22 y 39 solo se usan por el primero; 2, 26,
29-30, 46-66 solo por el segundo. En el nuevo hay una sola vía (Spatie +
`Gate::before` para super).

**c) El acceso 27 («Auditoria - Admin») está sembrado en producción y no se
comprueba en ninguna línea.** Es el mismo tipo de residuo que
[`12-CHECKLIST.md`](../12-CHECKLIST.md) ya anota para el nuevo con
`worksheets.validate` — con la diferencia de que ese sí tiene a quién
asignárselo.

---

## 5. La pantalla de «Configuración»: qué era y qué no era

**Hallazgo principal: el sistema viejo no tenía ningún parámetro configurable.**

`labo_old/app/controllers/configuration_management/configurations_controller.rb`
tiene **quince líneas** y una sola acción, `index`, que no carga nada
(`:6-13`). Su vista,
`labo_old/app/views/configuration_management/configurations/index.html.erb`, es
una portada de **cuatro tarjetas** que enlazan a otros módulos:

| Tarjeta | Línea | Destino |
|---|---|---|
| Países | `:13-15` | `configuration_management/countries` |
| Usuarios | `:26-28` | `user_management/users` |
| Perfiles | `:40-42` | `user_management/profiles` |
| Accesos | `:54-56` | `user_management/accesses` |

No hay tabla de ajustes en el esquema: las 47 tablas del volcado
(`docs/migracion/esquema/lab_app_development-estructura.sql`) no incluyen
ninguna con forma de `settings`.

**Consecuencia para esta auditoría: no hay ningún ajuste del viejo que le falte
al `Setting` del nuevo.** El nuevo tiene 30 claves sembradas
(`database/seeders/SettingsSeeder.php:19-67`), agrupadas en app, features, bulk,
exports, downloads, notifications, lab, security, uploads, audit y diagnostics.
El viejo tenía cero. La comparación es de suma, no de resta.

**Lo que sí falta no está en `configurations`, sino clavado en los modelos.** El
checklist ya lo tiene anotado: los umbrales de 5 / 2 / 3 días del cálculo de
entrega son constantes del código del viejo y tienen que pasar a `Setting`
(C8 de [`12-CHECKLIST.md`](../12-CHECKLIST.md)). Un ejemplo del mismo defecto
dentro de mi alcance: el corte de "urgente" está escrito a mano —
`days_remaining <= 2` en
`labo_old/app/controllers/application_controller.rb:116` — y en el nuevo, si
vuelve la insignia de H4, debería nacer como clave de `Setting`.

---

## 6. Restos del `ApplicationController` que no hay que buscar después

Cinco piezas del controlador base del viejo son código muerto. Se dejan
anotadas para que nadie las reponga creyendo que se perdió algo.

| Pieza | Evidencia | Por qué está muerta |
|---|---|---|
| `record_activity` (bitácora de IP y navegador) | `labo_old/app/controllers/application_controller.rb:33-43` | Ningún controlador la llama (búsqueda de `record_activity` en todo `app/`: una sola línea, su definición) y **no existe la tabla `activity_logs`** ni el modelo `ActivityLog` |
| `main_notifications` | `:26-28` | Consulta `UserNotification`, cuya tabla no existe. Ya declarado muerto en [`E-cobertura-tablas.md`](E-cobertura-tablas.md) §1 |
| `count_unconfirmed_m1` | `:46-49` | El cuerpo está entero comentado |
| `amchart_license` | `:102-105` | Licencia de la biblioteca de gráficos del viejo; el nuevo usa ECharts |
| `authorize` | `:65-71` | Nunca se usa (el filtro real es `authenticate_user`, `:73-78`); su mensaje dice literalmente "Please login2" |

También queda confirmado lo que
[`12-CHECKLIST.md`](../12-CHECKLIST.md) («Lo que NO hay que portar») ya decía de
dos controladores de mi alcance: `SupervisorsController` (154 líneas) y
`TicketsController` (147 líneas, y declarado bajo el espacio de nombres
`ImManagement::` en un archivo que vive en la raíz) **no tienen ruta**: la
búsqueda de `supervisor` y `ticket` en `labo_old/config/routes.rb` no devuelve
nada.

Y una pieza que **sí** se portó y conviene registrar: `static#terms` y
`static#privacy` (`labo_old/app/controllers/static_controller.rb:4-8`, rutas en
`labo_old/config/routes.rb:228-229`) tienen equivalente en
`resources/js/Layouts/AppLayout.vue:1687,1689`
(`legal_management.terms` / `legal_management.privacy`).

---

## 7. Lo que el nuevo hace mejor en este bloque

Para que el balance sea honesto, y porque tres de estos puntos son correcciones
de defectos reales del viejo:

- **La contraseña ya no se guarda en claro.** El viejo la duplicaba en
  `users.real_password` desde el propio formulario
  (`labo_old/app/views/user_management/users/partials/_form_password.html.erb:20`).
- **Validar una hoja es un permiso propio** (`worksheets.validate`,
  `database/seeders/RolesAndPermissionsSeeder.php:169`). En el viejo la pantalla
  escondía el enlace pero la acción verificaba el permiso de editar.
- **El menú no crece con el catálogo.** Cada prueba nueva agregaba cuatro
  entradas al menú viejo (`_app_sidebar_left_menus.html.erb:204-279`); acá la
  prueba es un registro y sus hojas se filtran.
- **El registro de auditoría cubre 44 modelos** contra 37, humaniza el diff de
  cada cambio y agrega `module`, `url`, `user_agent` y `note`.
- **Hay 30 parámetros configurables** donde el viejo tenía cero.
- **Sesión y bloqueo por intentos son datos**, no constantes:
  `security.session_lifetime_minutes`, `security.max_login_attempts` y
  `security.lockout_minutes` (`database/seeders/SettingsSeeder.php:54-56`),
  leídos en `app/Http/Controllers/AuthManagement/Auth/LoginController.php:49`.

---

## 8. Lo que hay que decidir

| # | Decisión | Por qué no se puede tomar desde el código |
|---|---|---|
| T1 | ¿«Sistemas de Expansión» va como módulo propio o como quinta solapa de «Listas del informe»? | Son cuatro filas que se tocan una vez por año; un módulo del andamio serían papelera, exportación en cuatro formatos e importación sobre cuatro registros. El criterio ya se fijó una vez, para los otros cuatro catálogos (C3 de [`12-CHECKLIST.md`](../12-CHECKLIST.md)); esto entra por la misma puerta |
| T2 | ¿Cómo se repone el desbloqueo de un usuario sin reponer la contraseña tipeada por un tercero? | «Forzar cambio en el próximo ingreso» y «enlace de recuperación generado por el administrador» son políticas distintas, con consecuencias distintas para la trazabilidad de quién firmó qué |
| T3 | ¿El registro de auditoría se exporta completo o filtrado, y con qué tope? | Es el mismo dilema que los topes por formato de exportación que ya están en `Setting`; con la diferencia de que el rastro de auditoría no se puede truncar en silencio |
