# GAP 5 — Reportes gerenciales, exportaciones e integración con TrafoDex

> Auditoría del bloque de **reportes gerenciales** y de la **integración con
> TrafoDex** del sistema anterior (Ruby on Rails, 2019) contra el sistema nuevo
> (Laravel 13 + Inertia + Vue 3).
>
> El sistema viejo se leyó en **solo lectura**. No se modificó ningún archivo de
> `labo_old`.

## Alcance

| Bloque del viejo | Archivos |
|---|---|
| `app/controllers/report_management/` | 7 controladores (`rlabs`, `ents`, `fims`, `jobs`, `otds`, `rems`, `reports`) |
| `app/controllers/trapp_management/` | 4 controladores (asistentes de envío a TrafoDex) |
| `app/controllers/json_management/` | 5 controladores (API JSON interna del puente) |
| `app/views/report_management/` | 20 vistas (6 índices, 6 tablas, 6 parciales `.xls`, 1 `.xlsx.axlsx`, 1 PDF) |
| `app/views/json_management/` | 4 plantillas `.jbuilder` de carga útil |
| `app/views/trapp_management/` | 2 vistas del asistente (paso 1 y paso 2 de cromatografía) |
| `app/models/` | 15 modelos (`rem_report`, `rem`, `rem_job`, `rem_correlative`, `db_system`, `primary2`, `*_trapp`, `chromatographical_duval`, `chromatographical_dga_diag`, `transformer_type`, `transformer_preservation`) |
| `config/routes.rb` | 1 |

**Total: 58 archivos del sistema viejo abiertos.**

Del sistema nuevo se revisaron `app/Http/Controllers/DashboardManagement/`,
`app/Http/Controllers/LabManagement/SampleReportController.php`,
`ReceptionController.php`, `app/Exports/**` (78 archivos), `routes/api.php`,
`app/Http/Controllers/Api/**`, `resources/js/Pages/Dashboard/`,
`resources/js/Pages/SampleReports/` y `resources/js/Pages/Receptions/`.

## Criterio de clasificación

- **AUSENTE** — la función no existe en el sistema nuevo ni en ninguna forma
  parcial. Cuando además ya hay una decisión escrita sobre qué hacer, se cita el
  documento, pero la clasificación sigue siendo AUSENTE porque hoy no se puede
  responder la pregunta.
- **PARCIAL** — hay algo equivalente en el nuevo, pero no cubre lo que el viejo
  cubría.
- **DECIDIDO** — el asunto ya está resuelto por escrito en
  [`../12-CHECKLIST.md`](../12-CHECKLIST.md),
  [`E-cobertura-tablas.md`](E-cobertura-tablas.md),
  [`M-campos-obligatorios.md`](M-campos-obligatorios.md) o
  [`../04-INTEGRACION-TRAFODEX.md`](../04-INTEGRACION-TRAFODEX.md). Se nombra y
  se enlaza, sin repetir el contenido.

---

## Tabla resumen

| # | Qué falta | Clasificación | Consecuencia |
|---|---|---|---|
| 1 | Indicador On Time Delivery (pantalla y cálculo) | **AUSENTE** | No se puede responder "¿cuántos informes salieron dentro del plazo este trimestre?" con ningún dato del sistema nuevo |
| 2 | Exportación `.xlsx` del OTD con semáforo por fila | **AUSENTE** | El indicador que el laboratorio adjunta a su reporte de gestión hay que rehacerlo a mano en Excel |
| 3 | Dos definiciones distintas de OTD en la misma pantalla | **DECIDIDO** | — (C8 de `12-CHECKLIST.md`) |
| 4 | Matriz de resultados de laboratorio (30 columnas de analitos) | **AUSENTE** | No hay forma de sacar los valores medidos de varias muestras en una sola planilla |
| 5 | Filtro por rango de fechas en el listado de informes | **AUSENTE** | El listado global de informes no se puede acotar a un período; la única forma es ordenar y paginar |
| 6 | Listado de informes ENTREGADOS (reporte `ents`) y su Excel | **AUSENTE** | No se puede listar qué informes se entregaron al cliente en un período |
| 7 | Estado "entregado" del informe | **PARCIAL** | El informe tiene `delivered_at` pero no un estado; no se puede filtrar "emitidos sin entregar" |
| 8 | Formato de registro de ingreso de muestras (reporte `fims`, Excel de 27 columnas) | **AUSENTE** | El formato de registro que el laboratorio archiva por período no se puede emitir |
| 9 | Listado de muestras cruzando recepciones (reporte `jobs`, Excel de 14 columnas) | **AUSENTE** | No hay ninguna pantalla que liste muestras de varias recepciones a la vez |
| 10 | Exportación del listado de recepciones por rango de fechas (reporte `rems`) | **PARCIAL** | Solo se exporta una recepción por vez; para el trimestre son 60 descargas |
| 11 | Los umbrales de días restantes siguen clavados, ahora en el navegador | **PARCIAL** | El color del plazo no se puede configurar, filtrar ni exportar |
| 12 | Permiso propio de los reportes gerenciales | **AUSENTE** | Cuando se reconstruyan no hay permiso sembrado que decida quién los ve |
| 13 | Las cuatro pantallas gerenciales repetidas y sus seis filtros no expuestos | **DECIDIDO** | — (C8 y "Lo que NO hay que portar") |
| 14 | `report_management/reports` (CRUD roto + PDF de maqueta) | **DECIDIDO** | — ("Lo que NO hay que portar") |
| 15 | **El envío completo de resultados a TrafoDex** | **AUSENTE** | TrafoDex deja de recibir cromatografía, fisicoquímico y furanos: el diagnóstico se congela con los datos que ya tiene |
| 16 | Alta de transformadores en TrafoDex desde el laboratorio | **AUSENTE** | Un equipo nuevo que el laboratorio recibe nunca aparece en TrafoDex |
| 17 | Listas JSON de "qué falta enviar", con deduplicación por serie y fecha | **AUSENTE** | No hay forma de saber qué resultados ya viajaron y cuáles no |
| 18 | Mapeo de preservación y de tipo de conexión hacia TrafoDex | **AUSENTE** | Aunque se construya el envío, faltan dos traducciones que el viejo hacía |
| 19 | El grado de polimerización nunca se envió (línea comentada) | **PARCIAL** | Dato de contexto: el DP de TrafoDex nunca vino del laboratorio |
| 20 | Búsqueda de equipo por número de serie expuesta como JSON | **AUSENTE** | La API nueva solo expone Clientes; no hay endpoint de equipos |
| 21 | Sin idempotencia y selección por número de serie en el asistente | **DECIDIDO** | — (`04-INTEGRACION-TRAFODEX.md` §1 y §3.2) |
| 22 | Mapeo de los 20 tipos de equipo a los 3 de TrafoDex | **DECIDIDO** | — (D1 de `12-CHECKLIST.md`) |
| 23 | Jerarquía `"-"` fabricada para el equipo creado por el puente | **DECIDIDO** | — (D3 de `12-CHECKLIST.md`) |
| 24 | Procedencia del registro (`db_systems`, `CustomerTrapp`) | **DECIDIDO** | — (D2 de `12-CHECKLIST.md`) |
| 25 | Fecha estimada de entrega de resultados por muestra (`date_urgent`) | **DECIDIDO** | — (`E-cobertura-tablas.md` fila 20) |
| 26 | Los quince contadores de envases y la cantidad pactada | **DECIDIDO** | — (`E-cobertura-tablas.md` fila 19, `M-campos-obligatorios.md` §6.7) |
| 27 | La firma que autoriza el ingreso de la muestra | **DECIDIDO** | — (`M-campos-obligatorios.md` §8.4) |

Recuento: **13 AUSENTE**, **4 PARCIAL**, **10 DECIDIDO**.

---

# Parte A — Reportes gerenciales

Antes de los huecos, el inventario de lo que había, porque la decisión escrita
(C8) dice **qué hacer** pero no deja registrado **qué preguntaba cada pantalla,
con qué filtros y con qué columnas**. Ese inventario es lo que sigue.

## Las siete pantallas del viejo

| Ruta | Título en pantalla | Permiso | Qué pregunta responde | Formato de salida |
|---|---|---|---|---|
| `report_management/otds` | "Registro de Ingreso de Muestras" (rótulo equivocado) | 66 | ¿Se entregó dentro del plazo? | HTML + `.xls` + `.xlsx` |
| `report_management/rlabs` | "Registro de Ingreso de Muestras" | 50 | ¿Qué se midió en cada muestra? | HTML + `.xls` |
| `report_management/rems` | "Registro de Ingreso de Muestras" | 51 | ¿Qué entró y en qué estado? | HTML + `.xls` |
| `report_management/fims` | "Registro de Ingreso de Muestras" | 52 | Ídem, formato de archivo | HTML + `.xls` |
| `report_management/jobs` | "Registro de Muestras" | 53 | ¿Qué falta hacerle a cada muestra? | HTML + `.xls` |
| `report_management/ents` | "Reportes Entregados" | 54 | ¿Qué informes se entregaron? | HTML + `.xls` |
| `report_management/reports` | — | 55 / 42 | (código muerto) | HTML + PDF |

Los cuatro listados `rems`, `fims`, `jobs` y `ents` comparten **el mismo cuerpo
de controlador, palabra por palabra** (`ents_controller.rb:33-76`,
`fims_controller.rb:33-74`, `jobs_controller.rb:33-74`,
`rems_controller.rb:33-75`) y **la misma tabla HTML de siete columnas**: las
cuatro plantillas `partials/_table.html.erb` solo difieren en espacios en
blanco. Lo único distinto entre las cuatro pantallas es el archivo Excel que
descargan.

---

## Hueco 1 — Indicador On Time Delivery

**Clasificación: AUSENTE.**

### Qué hace el viejo

`app/controllers/report_management/otds_controller.rb:35-56` arma un listado de
`RemReport` con:

- Filtro: `date_rec` desde / hasta, con **valor por omisión** "inicio del mes de
  hace tres meses" (`:37`).
- Filtro fijo `state = 0` (`:43`) — solo informes ya entregados.
- Orden por `date_rec` descendente, 10 por página (`:49`).

La tabla HTML (`app/views/report_management/otds/partials/_table.html.erb:8-15`)
tiene ocho columnas: Fecha de Recepción · Fecha de Emisión · Fecha de Entrega ·
**Días Proyectados** (`date_ent - date_rec`, `:31`) · **Días Reales**
(`date_emi - date_rec`, `:39`) · **O.T.D.** (reales menos proyectados, `:47`) ·
Nº Orden de Servicio · Cliente.

Los tres umbrales viven como constantes en el modelo
(`app/models/rem_report.rb:124-126`):

```ruby
ACCEPTABLE_OTD_DAYS      = 5
ACCEPTABLE_ISSUE_DAYS    = 2
ACCEPTABLE_DELIVERY_DAYS = 3
```

y se aplican en `rem_report.rb:128-150`: `otd = date_ent - date_rec`,
`time_to_issue = date_emi - date_rec`, `time_to_delivery = date_ent - date_emi`.

### Qué hay en el nuevo

Nada. `app/Http/Controllers/DashboardManagement/DashboardController.php:141-149`
declara el método y devuelve el vacío:

```php
/**
 * Fase 11: acá van los indicadores del laboratorio (OTD, tiempo de emisión,
 * carga por analista, muestras vencidas). El tablero de flota de TrafoDex se
 * eliminó: el laboratorio no diagnostica equipos.
 */
protected function labDashboard(?User $user, Request $request): array
{
    return [];
}
```

Una búsqueda de `otd` en `app/`, `resources/js/`, `resources/lang/` y `config/`
no devuelve ninguna otra aparición.

**Los tres datos que el cálculo necesita SÍ existen**: `receptions.received_at`
(`database/migrations/2026_07_28_130000_create_receptions_tables.php:109`),
`sample_reports.issued_at` y `sample_reports.delivered_at`
(`app/Http/Controllers/LabManagement/SampleReportController.php:123-124`, con
`delivered_at` cargable desde el formulario en
`resources/js/Components/Receptions/ReportFormModal.vue:224-228`). Falta el
indicador, no el dato.

### Consecuencia

El laboratorio pierde su único indicador de gestión: hoy no hay forma de
responder "¿cuántos informes salieron dentro del plazo este trimestre?" sin
exportar tabla por tabla y calcularlo a mano.

> La decisión de reconstruirlo con umbral configurable y contra `due_at` está en
> C8 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md) y en la Fase 11 del plan
> maestro. Lo que este hueco aporta es el detalle de columnas y fórmulas de
> arriba, que ahí no está escrito.

---

## Hueco 2 — La exportación `.xlsx` del OTD (única con formato real)

**Clasificación: AUSENTE.**

### Qué hace el viejo

`app/views/report_management/otds/export.xlsx.axlsx` es **el único archivo Excel
verdadero de todo el sistema viejo** (los demás son HTML con extensión `.xls`).
Usa la gema `axlsx` y define cinco estilos (`:3-32`): bordes, encabezado gris
`D8D8D8` en negrita de 14 puntos, título de 16, relleno verde `00FF00` y relleno
rojo `FF0000`.

Una sola hoja, "Reporte On Time Delivery" (`:34`), con diez columnas (`:36-41`):

| # | Columna |
|---|---|
| 1 | Fecha de Ingreso |
| 2 | Fecha de Emisión |
| 3 | Fecha de Entrega |
| 4 | **OTD OBSERVACIÓN** (celda pintada verde o roja) |
| 5 | OTD (Días) |
| 6 | Tiempo para emitir (Días) |
| 7 | Tiempo de entrega (Días) |
| 8 | Nº Orden de Servicio |
| 9 | Cliente |
| 10 | Nº Muestra |

El semáforo de la columna 4 se calcula en `:45-53` y se rotula en `:59`.

El `.xls` alternativo del mismo reporte
(`otds/partials/_xls_partial_report.erb:13-24`) tiene **doce** columnas, tres de
ellas de sí/no contra los umbrales: "OTD Correcto (MAX 5 días)", "Emisión
Correcta (MAX 2 días)", "Entrega Correcta (MAX 3 días)".

### Qué hay en el nuevo

No hay ninguna exportación de indicadores. El motor de exportación existe y está
maduro (78 clases bajo `app/Exports/`, con `EquipmentExport`,
`CustomersExport`, etc.), pero ninguna es de gestión.

### Consecuencia

El archivo que el laboratorio adjunta a su reporte de gestión mensual hay que
rehacerlo a mano en Excel.

---

## Hueco 3 — Dos definiciones de OTD conviviendo

**Clasificación: DECIDIDO** — C8 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md)
("el Excel del viejo y su propio modelo usan dos definiciones distintas de OTD
en la misma pantalla. Hay que elegir una").

Se agrega solo la evidencia exacta, que no estaba anotada:

- **Definición del modelo**: `otd = date_ent - date_rec`, correcto si `<= 5`
  (`app/models/rem_report.rb:128-142`). Es la que usa el `.xls`
  (`otds/partials/_xls_partial_report.erb:36-43`).
- **Definición del `.xlsx`**: correcto si `date_emi <= date_ent`, incorrecto si
  `date_emi > date_ent` (`otds/export.xlsx.axlsx:45-53`, rótulo en `:59`). No
  mira `date_rec` ni el umbral de 5 días.

Los dos archivos se bajan de la misma pantalla, con el mismo filtro, y dan
números distintos.

---

## Hueco 4 — La matriz de resultados de laboratorio (reporte `rlabs`)

**Clasificación: AUSENTE.**

### Qué hace el viejo

`app/controllers/report_management/rlabs_controller.rb:19-26` toma **el último
informe de cada muestra** con una subconsulta (`MAX(id) GROUP BY
rem_correlative_id`) y lo filtra por rango de `date_rec`, con el mismo valor por
omisión de tres meses (`:15`).

La tabla HTML (`rlabs/partials/_table.html.erb:8-15`) tiene ocho columnas: Nº de
Muestra · OS Laboratorio · Fecha Recepción · **Fecha de entrega de resultados
estimada** · Fecha entrega de informe al cliente · Cliente · Serie · Tipo de
fluido dieléctrico.

El Excel (`rlabs/partials/_xls_partial_report.erb:26-151`) agrega a esas ocho
columnas **treinta columnas de valores medidos**, agrupadas por familia con
encabezado de dos filas y un color por bloque:

| Bloque | Columnas |
|---|---|
| **FÍSICOQUÍMICO** (11) | Número Ácido · Factor Potencia 25 °C · Factor Potencia 90 °C · Factor Potencia 100 °C · Rigidez Dieléctrica · Rigidez Dieléctrica Electrodos Planos · Tensión Interfacial · Agua · Color · Condición Visual · Densidad Relativa |
| **CROMATOGRAFÍA** (9) | Hidrógeno · Oxígeno · Nitrógeno · Metano · Monóxido de Carbono · Dióxido de Carbono · Etileno · Etano · Acetileno |
| **Otros** (15, una columna cada uno) | PCB · Furano · Azufre 1275B · Azufre 62535 (48 h) · Azufre 62535 (72 h) · Grado de Polimerización · Viscosidad · Partículas · Metales · Inhibidor · DBD · Sedimentos · Fluidez · Inflamación · Pasivador |

Los valores salen de `rem_report_details.first.*_val` (`:103-149`), con `"-"`
cuando no hay dato.

### Qué hay en el nuevo

`app/Http/Controllers/LabManagement/SampleReportController.php:96-159` produce el
listado global de informes, y sus columnas
(`resources/js/Pages/SampleReports/Index.vue:128-144`) cubren **la cabecera** del
reporte viejo y la mejoran (agrega tipo de equipo, tensión, potencia, motivo de
muestreo, tipo de informe y estado).

Lo que **no** tiene: **ni una sola columna de valor medido**, y **ninguna
exportación**. En `routes/lab_management.php` la única ruta del listado es
`:319` (`sample_reports.index`); no hay ruta de export, y una búsqueda de
`export` en `resources/js/Pages/SampleReports/Index.vue` no devuelve nada.

### Consecuencia

No hay forma de obtener los valores medidos de varias muestras en una sola
planilla: ni para hacer tendencias propias, ni para mandarle al cliente el
histórico de su flota, ni para revisar un lote de resultados fuera del sistema.
La única salida de valores es el PDF de **un** informe.

---

## Hueco 5 — El listado de informes no se puede acotar por fecha

**Clasificación: AUSENTE.**

### Qué hace el viejo

Las siete pantallas gerenciales arrancan con un filtro de fechas ya puesto:
`@search_date_ini = params[:search_date_ini].presence || 3.months.ago.beginning_of_month.to_date`
(`rlabs_controller.rb:15`, `otds_controller.rb:37`, `ents_controller.rb:41`,
`fims_controller.rb:41`, `jobs_controller.rb:41`, `rems_controller.rb:41`), con
los dos campos de fecha en el formulario
(`rlabs/index.html.erb:13-19`) y un contador de registros del resultado
(`:40`).

### Qué hay en el nuevo

`SampleReportController.php:188-197` define las ocho columnas buscables, todas
por texto (`samples.code`, `sample_reports.code`, `receptions.service_order`,
`customers.name`, `equipment.serial`, `equipment_types.name`, `oil_types.name`,
`samples.sampling_reason`), y `:200-206` define las cinco **ordenables** —
`status`, `kind`, `issued_at`, `delivered_at`, `received_at`—, que se ordenan
pero **no se filtran**. Los únicos filtros de valor son `status` y `kind`
(`:271-272`).

En la pantalla, los controles son tres: buscador global, estado y tipo
(`resources/js/Pages/SampleReports/Index.vue:65-67`). No hay selector de rango de
fechas.

El listado de **recepciones** sí lo tiene
(`ReceptionController.php:118-119`, filtros `from` / `to`), así que el hueco es
específico del listado de informes.

### Consecuencia

Preguntas tan simples como "los informes emitidos en julio" o "lo entregado en
el último trimestre" no se pueden hacer: hay que ordenar por fecha y paginar
hasta encontrar el corte.

---

## Hueco 6 — El reporte de informes entregados (`ents`)

**Clasificación: AUSENTE.**

### Qué hace el viejo

`app/controllers/report_management/ents_controller.rb:50` agrega al listado de
recepciones la condición `rem_correlatives_rem_reports_state_eq = 0`, es decir
**solo recepciones con al menos un informe ya entregado**.

El Excel (`ents/partials/_xls_partial_report.erb`) se titula "LISTADO DE ENTREGA
DE REPORTES" (`:21`), vuelve a filtrar en la vista con
`state=0 AND deleted=0 AND type_report=0` (`:38`) —o sea entregados, no
eliminados y principales— y saca nueve columnas (`:25-33`):

Nº Reporte · Nº de Muestra · Nº Orden de Servicio · Cliente · Nº Serie del
Transformador · Fecha de Recepción · Fecha de Entrega · **Razón de Análisis** ·
**Estado**.

### Qué hay en el nuevo

`SampleReports/Index.vue:128-144` tiene las siete primeras columnas equivalentes
(incluida `sampling_reason`, la razón de análisis) pero:

- no filtra por "entregado" (ver hueco 7),
- no filtra por período (hueco 5),
- no exporta (hueco 4).

### Consecuencia

"¿Qué informes le entregamos a este cliente entre marzo y junio?" no tiene
respuesta en el sistema nuevo.

---

## Hueco 7 — El informe no tiene estado "entregado"

**Clasificación: PARCIAL.**

### Qué hace el viejo

`app/models/rem_report.rb:53-56` define dos estados del informe:

```ruby
def str_state
  return "Entregado" if state == 0
  return "Generado"  if state == 1
end
```

`state` se pone en `1` al crear (`:163`) y pasa a `0` automáticamente cuando el
informe recibe firma (`set_auto_lock_registry`, `:167-177`). Sobre ese estado se
construyen el reporte `ents` (`ents_controller.rb:50`) y el filtro fijo del OTD
(`otds_controller.rb:43`).

### Qué hay en el nuevo

`app/Models/SampleReport.php:32-35` tiene dos estados, pero otros:

```php
public const STATUS_DRAFT  = 'draft';
public const STATUS_ISSUED = 'issued';
public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ISSUED];
```

La entrega al cliente se registra como **fecha suelta**, `delivered_at`
(`SampleReportController.php:758`, `'nullable', 'date', 'after_or_equal:issued_at'`),
sin estado asociado y sin filtro que la use.

### Consecuencia

No se puede listar "emitidos que todavía no se entregaron", que es la cola de
trabajo del que despacha informes; y el reporte de entregas del viejo pierde su
criterio de selección.

---

## Hueco 8 — El formato de registro de ingreso de muestras (`fims`)

**Clasificación: AUSENTE.**

### Qué hace el viejo

`fims/partials/_xls_partial_report.erb` se titula "FORMATO DE REGISTRO DE
INGRESO DE MUESTRAS" (`:19`) y es el archivo que el laboratorio conserva como
constancia del período. Encabezado de dos filas, veintisiete columnas
(`:22-58`):

| Grupo | Columnas |
|---|---|
| Cabecera (6) | Fecha de Recepción · Fecha de Entrega · **Días Restantes** · Extraído Por · Nº Orden de Servicio · Cliente |
| **Nº ENVASES QUE INGRESAN** (16) | Botella para FQ · Jeringa · Frascos PCB · Frascos Furanos · Frascos Azufre · Grado de Polimerización · Frascos Viscosidad · Frascos Partículas · Frascos Metales · Frascos Inhibidor · Frascos DBDS · Frascos Sedimentos · Frascos Fluidez · Frascos Inflamante · Frascos Pasivador · **Total de Envases** |
| **ESTADO DE MUESTRAS** (3) | Envases Adecuados · Volumen Adecuados · Datos Completos |
| Cierre (3) | Observaciones · Nombre de quien autoriza · **Firma** (imagen embebida, `:96-100`) |

La columna "Días Restantes" se pinta con la clase de urgencia
(`:65-67`), que sale de `app/models/rem.rb:99-112`: rojo si faltan 2 días o
menos, ámbar entre 3 y 4, verde por encima de 4.

### Qué hay en el nuevo

Repartido, y sin forma de emitirlo como un solo documento:

- Las tres marcas de verificación física existen y mejor nombradas:
  `container_ok` / `volume_ok` / `label_ok`
  (`database/migrations/2026_07_28_130000_create_receptions_tables.php:116-118`).
- El total de envases existe: `packages` (`:119`).
- Los días restantes se calculan, pero **en el navegador**
  (`resources/js/Pages/Receptions/Index.vue:59-78`), así que no viajan a ninguna
  exportación.
- Los quince contadores por familia y la firma de quien autoriza **no existen**
  (ver huecos 26 y 27, ya decididos).
- No hay exportación del listado de recepciones por período (hueco 10).

### Consecuencia

El formato de registro que el laboratorio archiva —y que un auditor de ISO 17025
pide como evidencia de la recepción— no se puede emitir desde el sistema nuevo.

---

## Hueco 9 — El listado de muestras cruzando recepciones (`jobs`)

**Clasificación: AUSENTE.**

### Qué hace el viejo

`jobs/partials/_xls_partial_report.erb` se titula "LISTADO DE MUESTRAS" (`:21`)
y recorre **todas las recepciones del período y todos sus correlativos**
(`:42-43`), una fila por muestra, catorce columnas (`:25-38`):

| # | Columna | Cómo se calcula |
|---|---|---|
| 1 | F. Recepción | `:45` |
| 2 | Orden de Servicio | `:47` |
| 3 | Cliente | `:49` |
| 4 | Nº Serie del Transformador | `:51-57`, imprime **"PENDIENTE DE ASIGNAR"** en rojo si no hay equipo |
| 5 | Nº de Muestra | `:59` |
| 6 | Fecha de Ingreso | `:61` |
| 7 | **Pruebas Asignadas** (los nombres, uno por línea) | `:63-71` |
| 8 | **Fecha de Muestreo** (la del ensayo, por prueba) | `:73-90`, cruzando `lab_sub_details` con `labs` |
| 9 | Nº Serie Asignado (sí/no) | `:92-98` |
| 10 | Pruebas Asignadas (sí/no) | `:100-106` |
| 11 | **Valores Asignados** (sí/no) | `:108-118`, "NO" si alguna prueba tiene `task_done = 0` |
| 12 | Reporte Creado (sí/no) | `:120-126` |
| 13 | Importancia | `:128-134`, "MÁXIMA PRIORIDAD" en rojo si `is_urgent` |
| 14 | Fecha Estimada de Realización | `:136-141`, `date_urgent` |

Es el reporte operativo: la lista de trabajo de todo lo que hay en la casa, con
lo que le falta a cada muestra.

### Qué hay en el nuevo

Los cuatro chequeos existen y están **mejor resueltos** —se derivan en la misma
consulta del listado, sin caché
(`app/Http/Controllers/LabManagement/ReceptionController.php:100-110`:
`unlinked_count`, `untested_count`, `outstanding_count`, `reported_count`)—
pero se muestran **agregados por recepción**, como cuatro chips
(`resources/js/Pages/Receptions/Index.vue:188-191`), no por muestra.

Y **no existe ningún listado global de muestras**: en
`routes/lab_management.php` las únicas rutas con `samples/` son
`samples/{sample}/report` (`:323`), `samples/{sample}/reports/new` (`:326`) y
`samples/{sample}/reports` (`:338`). No hay `Pages/Samples/`. Una muestra solo se
ve dentro de la ficha de su recepción.

### Consecuencia

No se puede preguntar "de todas las muestras que hay en el laboratorio, ¿cuáles
siguen sin equipo asignado?" ni "¿cuáles urgentes tienen valores sin cargar?"
sin abrir recepción por recepción.

---

## Hueco 10 — Exportación del listado de recepciones por período (`rems`)

**Clasificación: PARCIAL.**

### Qué hace el viejo

`rems/partials/_xls_partial_report.erb` recorre **todas las recepciones del
rango de fechas** (`:17`) y por cada una imprime **tres tablas** en el mismo
archivo:

1. `N. DATOS DE INGRESO DE LA MUESTRA` (`:19-97`) — las mismas 26 columnas del
   reporte `fims`, sin la de días restantes.
2. `LISTADO DE MUESTRAS` (`:99-213`) — once columnas por correlativo, las
   mismas 11 últimas del reporte `jobs`.
3. `LISTADO DE REPORTES` (`:216-249`) — nueve columnas, las mismas del reporte
   `ents`, pero **sin** el filtro `state=0`: incluye los informes generados y no
   entregados.

Es decir: un solo archivo con la recepción, sus muestras y sus informes,
repetido tantas veces como recepciones tenga el período.

### Qué hay en el nuevo

`app/Exports/LabManagement/Receptions/ReceptionSamplesExport.php` cubre **el
segundo bloque, para una sola recepción**, con diez columnas (`:47-58`): código
de muestra · equipo · TAG · tipo de aceite · fecha de muestreo · punto de
muestreo · pruebas pedidas · validadas · informadas · avance. La ruta es
`receptions/{reception}/export` (`routes/lab_management.php:356`,
`ReceptionController.php:424-432`), o sea **una recepción por descarga**.

No hay equivalente del bloque de cabecera (envases, estado de las muestras,
observaciones, quien autoriza) ni del bloque de informes.

### Consecuencia

Para armar el legajo de un trimestre hay que bajar una planilla por recepción
—sesenta descargas para sesenta recepciones— y ninguna de las sesenta trae la
cabecera ni los informes.

---

## Hueco 11 — Los umbrales de plazo siguen clavados, ahora en el navegador

**Clasificación: PARCIAL.**

### Qué hace el viejo

`app/models/rem.rb:99-117`: `days_remaining = date_deliver - Date.today` y tres
umbrales clavados en `urgency_class` (`:107-112`): rojo `<= 2`, ámbar `3..4`,
verde `> 4`. Además expone un `ransacker` (`:115-117`) que traduce el cálculo a
SQL (`DATEDIFF(date_deliver, date_received)`), o sea que en el viejo **el plazo
se podía filtrar y ordenar en la base**.

### Qué hay en el nuevo

`resources/js/Pages/Receptions/Index.vue:59-78`: el mismo cálculo, con umbrales
distintos —rojo si es negativo, ámbar `<= 3`— y **resuelto en el cliente**.
`ReceptionController::index` (`:74-142`) no lo calcula ni lo filtra.

### Consecuencia

Los umbrales siguen sin ser configurables (mismo defecto que C8 señala para el
OTD) y, por estar en el navegador, el plazo no se puede filtrar en el listado,
no se puede ordenar por él, y no puede aparecer en ninguna exportación.

---

## Hueco 12 — Los reportes gerenciales no tienen permiso propio

**Clasificación: AUSENTE.**

### Qué hace el viejo

Cada pantalla se gatea con un identificador numérico distinto:
`rlabs` = 50 (`rlabs_controller.rb:13`), `rems` = 51 (`rems_controller.rb:15`),
`fims` = 52 (`fims_controller.rb:15`), `jobs` = 53 (`jobs_controller.rb:15`),
`ents` = 54 (`ents_controller.rb:15`), `reports` = 55 y 42
(`reports_controller.rb:18,29`), `otds` = 66 (`otds_controller.rb:14`). Y los
cuatro asistentes de envío: 60, 61, 62 y 63
(`import_transformers_controller.rb:78`, `import_cromas_controller.rb:75`,
`import_fiquis_controller.rb:75`, `import_furanos_controller.rb:75`).

### Qué hay en el nuevo

`database/seeders/RolesAndPermissionsSeeder.php:52-58` siembra los permisos
transversales y ninguno es de reportes de gestión ni de integración. El único
permiso del laboratorio fuera del CRUD es `worksheets.validate`.

### Consecuencia

Cuando se construyan los indicadores no hay permiso sembrado que decida quién
los ve: el reporte de gestión de un laboratorio no es información que deba ver
todo el que carga una muestra.

---

## Huecos 13 y 14 — Ya decididos

**Hueco 13. Cuatro pantallas repetidas y seis filtros que ninguna pantalla
expone. Clasificación: DECIDIDO** — C8 y "Lo que NO hay que portar" de
[`../12-CHECKLIST.md`](../12-CHECKLIST.md). Evidencia de los filtros muertos:
`ents_controller.rb:52-66` arma seis condiciones (`num_os` en blanco,
correlativo sin confirmar, `pending_tr`, `pending_tk`, `pending_va`,
`is_urgent`) a partir de parámetros que el formulario
(`ents/index.html.erb:11-31`) nunca envía.

**Hueco 14. `report_management/reports`. Clasificación: DECIDIDO** — "Lo que NO
hay que portar". Evidencia: `reports_controller.rb:97,151,156,161,166` usan el
modelo `Report`, que no existe en `app/models/`, y `create`/`update`/`destroy`
redirigen a `im_management_transformer_types_path`, que es otro módulo. La vista
`reports/show.erb` es una **maqueta con datos escritos a mano** ("RED DE ENERGIA
DEL PERU S.A", `:23`; "PGTR-LA-23-0234", `:12`) y termina con sesenta y cuatro
párrafos de relleno que dicen "INFORMACIÓN DEL CLIENTE" (`:142`).

---

# Parte B — Integración con TrafoDex

## Cómo funcionaba el puente

No era una API: era **una segunda conexión a la base de TrafoDex**.
`app/models/primary2.rb:1-4` declara la conexión y cinco modelos escriben
directo sobre las tablas de la otra base, nombrándolas con el esquema completo:

| Modelo del viejo | Tabla que escribe | Archivo |
|---|---|---|
| `TransformerTrapp` | `tr_app_development.transformers` | `transformer_trapp.rb:2-3` |
| `ChromatographicalTrapp` | `tr_app_development.chromatographicals` | `chromatographical_trapp.rb:2-3` |
| `PhysicalTrapp` | `tr_app_development.physicals` | `physical_trapp.rb:2-3` |
| `FuranoTrapp` | `tr_app_development.furanos` | `furano_trapp.rb:2-3` |
| `CustomerTrapp` | `tr_app_development.customers` | `customer_trapp.rb:1-3` |

El disparo era **manual**: un asistente de cuatro pasos por cada tipo de dato
(`config/routes.rb:173-181`). Nada era automático al emitir un informe.

---

## Hueco 15 — El envío de resultados a TrafoDex no existe

**Clasificación: AUSENTE.** Es el hueco más grave del bloque.

### Qué hace el viejo

Tres asistentes, uno por familia de ensayo. Los tres tienen la misma forma: paso
1 elige cliente, paso 2 muestra la grilla con las muestras candidatas y sus
casillas, paso 3 confirma, **paso 4 escribe en la base de TrafoDex**.

**Cromatografía** — `trapp_management/import_cromas_controller.rb:122-140`:

```ruby
transformer_trapp = TransformerTrapp.find_by(deleted: 0, num_serie: array.transformer.num_serie)
chromatographical_trapp = ChromatographicalTrapp.new
chromatographical_trapp.transformer_id = transformer_trapp.id
chromatographical_trapp.date_rehearsal = array.date_mue
chromatographical_trapp.num_hid = array.rem_report_details.first.cro_val
...
```

Mapa completo (`:129-137`):

| Origen (laboratorio) | Destino (TrafoDex) | Gas |
|---|---|---|
| `cro_val` | `num_hid` | Hidrógeno |
| `cro2_val` | `num_oxi` | Oxígeno |
| `cro3_val` | `num_nit` | Nitrógeno |
| `cro4_val` | `num_met` | Metano |
| `cro5_val` | `num_mon` | Monóxido de carbono |
| `cro6_val` | `num_dio` | Dióxido de carbono |
| `cro7_val` | `num_eti` | Etileno |
| `cro8_val` | `num_eta` | Etano |
| `cro9_val` | `num_ace` | Acetileno |

Candidatas: informes no eliminados, de tipo principal, con hidrógeno declarado y
cuyo transformador ya exista en TrafoDex (`:79-83`).

**Fisicoquímico** — `import_fiquis_controller.rb:87-103`:

| Origen | Destino | Parámetro |
|---|---|---|
| `aci_val` | `num_acid` | Número ácido |
| `f25_val` | `num_pot` | Factor de potencia a 25 °C |
| `f100_val` | `num_pot2` | Factor de potencia a 100 °C |
| `rig_val` | `num_rig` | Rigidez dieléctrica |
| `rigep_val` | `num_rig2` | Rigidez con electrodos planos |
| `ten_val` | `num_ten` | Tensión interfacial |
| `agu_val` | `num_wat` | Agua |

Nótese que **el factor de potencia a 90 °C (`f90_val`) no se envía**, aunque el
laboratorio lo mide y lo imprime en su matriz de resultados
(`rlabs/partials/_xls_partial_report.erb:105`).

**Furanos** — `import_furanos_controller.rb:87-102`:

| Origen | Destino | Compuesto |
|---|---|---|
| `fur_val` | `num_fal` | 2-FAL |
| `fur2_val` | `num_hme` | 5-HMF |
| `fur3_val` | `num_ace` | 2-ACF |
| `fur4_val` | `num_mfu` | 5-MEF |
| `fur5_val` | `num_fua` | 2-FOL |
| `fur6_val` | — | **comentado** (ver hueco 19) |

En los tres casos la fecha del ensayo es `date_mue` y el enlace con el equipo se
hace por **número de serie en texto**, con `find_by` (toma el primero si hay
repetidos).

### Qué hay en el nuevo

**Nada.** Una búsqueda de `outbound_message`, `integration_target`,
`SendLabResult` y `trafodex` sobre `app/`, `database/`, `routes/`,
`resources/js/` y `config/` devuelve solo comentarios explicativos: ninguna
tabla, ningún trabajo en cola, ninguna pantalla, ningún cliente HTTP.

Lo único construido es **la columna del puente**, y sin ninguna lógica detrás:

- `database/migrations/2026_07_28_061051_create_equipment_table.php:81-87`
  declara `equipment.external_ref` con el comentario de que es "el slug del
  transformer equivalente".
- `app/Http/Requests/BusinessManagement/Equipment/Concerns/EquipmentFieldRules.php:90`
  la valida como `nullable|string|max:255`.
- `resources/js/Pages/Equipment/Form.vue:489-493` la ofrece como una **caja de
  texto libre** que el operador tipea a mano.

No hay búsqueda de transformador, ni conciliación, ni envío.

> El contrato de reemplazo está diseñado en
> [`../04-INTEGRACION-TRAFODEX.md`](../04-INTEGRACION-TRAFODEX.md) y planificado
> como Fase 7 del plan maestro (`00-PLAN-MAESTRO.md:251-264`). El diseño existe;
> el código no.

### Consecuencia

Desde el corte, TrafoDex deja de recibir cromatografía, fisicoquímico y furanos:
el índice de salud, las tendencias, el Duval y el tablero de flota se congelan
con los datos que ya tenían, y el laboratorio no tiene ninguna vía —ni siquiera
manual— de mandar un resultado.

---

## Hueco 16 — El alta de transformadores en TrafoDex

**Clasificación: AUSENTE.**

### Qué hace el viejo

`trapp_management/import_transformers_controller.rb:87-108` crea el
transformador en TrafoDex y, si el guardado sale bien, **crea también sus dos
filas de diagnóstico** (`:99-100`):

```ruby
if transformer_trapp.save!
  ChromatographicalDuval.create!(transformer_id: transformer_trapp.id)
  ChromatographicalDgaDiag.create!(transformer_id: transformer_trapp.id)
end
```

El cuerpo del transformador se arma en `build_transformer_trapp`
(`:149-167`), y trae tres valores clavados que conviene tener presentes:

```ruby
connection_type_id: 16,        # "conexión otros"
num_health: 0,
state_health: "Muy Malo",
color_health: "red",
```

Es decir: **el equipo nacía en TrafoDex marcado en rojo**, con índice de salud 0,
hasta que el motor lo recalculara.

### Qué hay en el nuevo

Nada: no hay ninguna llamada de alta hacia TrafoDex (mismo resultado de búsqueda
del hueco 15).

### Consecuencia

Un transformador que el laboratorio recibe por primera vez nunca aparece en
TrafoDex, así que sus resultados no tendrían dónde cargarse ni aunque el envío
existiera.

---

## Hueco 17 — Las listas JSON de "qué falta enviar", con deduplicación

**Clasificación: AUSENTE.**

### Qué hace el viejo

Los cuatro controladores de `json_management` alimentaban las grillas del
asistente, y sus plantillas `.jbuilder` hacían **la deduplicación contra la base
de TrafoDex antes de mostrar la fila**.

`json_management/trapp_import_cromas/partials/_data_index.json.jbuilder:2-5`:

```ruby
date_croma = ChromatographicalTrapp.select(...)
  .joins("INNER JOIN tr_app_development.transformers ON ...")
  .where("... transformers.num_serie = ? AND chromatographicals.date_rehearsal = ?",
         array.transformer.num_serie, array.date_mue)

if date_croma.size == 0
  # solo entonces se emite la fila
```

O sea: la fila aparece **solo si esa combinación de número de serie y fecha de
ensayo todavía no existe del otro lado**. Lo mismo en
`trapp_import_fiquis/partials/_data_index.json.jbuilder:2-6` (contra
`physicals`) y `trapp_import_furanos/partials/_data_index.json.jbuilder:2-5`
(contra `furanos`).

Para los transformadores la lógica es la inversa y vive en el controlador:
`json_management/trapp_import_transformers_controller.rb:9` lista los equipos
del laboratorio **cuyo número de serie NO está** en TrafoDex.

Las cargas útiles emitidas eran:

| Recurso | Campos |
|---|---|
| Cromatografía (`_data_index.json.jbuilder:6-22`) | id · transformer_id · customer_id · customer_name · transformer_type · num_serie · num_tag · date_mue · hid · oxi · nit · met · mon · dio · eti · eta · ace |
| Fisicoquímico (`:6-20`) | exist_physical · id · transformer_id · customer_id · customer_name · transformer_type · num_serie · date_mue · aci_val · f25_val · f100_val · rig_val · rigep_val · ten_val · agu_val |
| Furanos (`:6-18`) | id · transformer_id · customer_id · customer_name · transformer_type · num_serie · date_mue · fur_val … fur6_val |
| Transformadores (`:2-14`) | id · customer_name · num_serie · num_tag · substation · num_vol · num_pot · age · mark · transformer_type · oil_type · conmutation_type · preservation_type |

### Qué hay en el nuevo

No hay ninguna pantalla ni endpoint que responda "qué resultados todavía no
viajaron". `routes/api.php:35-63` expone únicamente Clientes.

### Consecuencia

Aunque se construya el envío, no hay hoy ninguna manera de saber qué quedó
pendiente ni de detectar un reenvío: el estado del puente sería invisible.

---

## Hueco 18 — Las dos traducciones de catálogo hacia TrafoDex

**Clasificación: AUSENTE.**

### Qué hace el viejo

Además del mapeo de tipos de equipo (hueco 22, ya decidido), el puente traducía:

- **Preservación** — `app/models/transformer_preservation.rb:15-20` convierte
  los cuatro identificadores del laboratorio en el **texto** que TrafoDex espera
  ("Conservador con membrana", "Tanque sellado con nitrogeno.", "Conservador con
  respiración libre.", "-"). Se usa en
  `json_management/trapp_import_transformers/partials/_data_index.json.jbuilder:14`.
- **Tipo de conexión** — no se traduce: se manda siempre `16`, "conexión otros"
  (`import_transformers_controller.rb:162`), o sea que el dato real del
  laboratorio se descartaba.

### Qué hay en el nuevo

`transformer_preservations` está portada como catálogo
(`E-cobertura-tablas.md` fila 32), pero **no hay ninguna traducción de salida**:
el nuevo no tiene dónde declarar "esta fila mía es aquella fila de TrafoDex".

### Consecuencia

Cuando se construya el envío hay que rehacer estas dos correspondencias desde
cero; el mapeo del viejo (con sus textos exactos, punto final incluido) es la
única fuente de qué esperaba el otro lado.

---

## Hueco 19 — El grado de polimerización nunca se envió

**Clasificación: PARCIAL** (dato de contexto, no una regresión).

### Qué hace el viejo

`import_furanos_controller.rb:99`:

```ruby
#furano_trapp.num_pol = array.rem_report_details.first.fur6_val.to_f
```

La línea está **comentada**. El laboratorio mide el grado de polimerización
—lo imprime en su matriz de resultados
(`rlabs/partials/_xls_partial_report.erb:133`) y su plantilla JSON lo publica
(`trapp_import_furanos/partials/_data_index.json.jbuilder:18`)— pero al escribir
en TrafoDex se descartaba.

### Consecuencia

El grado de polimerización que TrafoDex muestra **nunca vino del laboratorio**:
lo calcula él, por Chendong, a partir del 2-FAL. Al diseñar el envío nuevo hay
que decidir explícitamente si el DP medido se manda —y qué gana frente al
calculado—, porque no hay precedente que copiar.

---

## Hueco 20 — La búsqueda de equipo por número de serie

**Clasificación: AUSENTE.**

### Qué hace el viejo

`json_management/transformers_controller.rb:22-29` expone una búsqueda por
número de serie que devuelve `[{id, num_serie}]`, y `:7-11` vuelca el padrón
completo con cliente, subestación, área, sede y país anidados.

### Qué hay en el nuevo

`routes/api.php:48-63` expone solo Clientes; no hay controlador de equipos bajo
`app/Http/Controllers/Api/`. El único punto de contacto con un equipo externo es
la caja de texto `external_ref` del hueco 15.

### Consecuencia

No hay ningún camino programático para resolver "a qué equipo corresponde esta
muestra", que es el primer paso de cualquier envío
(`04-INTEGRACION-TRAFODEX.md` §3.1 lo llama "resolución del equipo").

---

## Huecos 21 a 27 — Ya decididos

Se listan con su evidencia para que el rastro quede completo; el detalle está en
los documentos citados.

| # | Asunto | Evidencia del viejo | Dónde está decidido |
|---|---|---|---|
| 21 | Sin idempotencia; y el asistente selecciona **por número de serie**, no por informe: la casilla del paso 2 lleva `value=num_serie` (`trapp_management/import_cromas/partials/_form_step2.html.erb:104-107`) y el paso 4 vuelve a consultar por `transformer_num_serie_in` (`import_cromas_controller.rb:83`), así que elegir una muestra reenvía **todos** los informes de ese transformador. La deduplicación del hueco 17 solo actúa en la grilla, nunca en la escritura | `import_cromas_controller.rb:122-140` | [`../04-INTEGRACION-TRAFODEX.md`](../04-INTEGRACION-TRAFODEX.md) §1 y §3.2 |
| 22 | 20 tipos de equipo colapsados a 3 con "si es mayor a 3, Potencia" (`transformer_type.rb:15-28`) | `import_transformers_controller.rb:158` | D1 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md) |
| 23 | Sede y área `"-"` fabricadas para colgar la subestación (`import_transformers_controller.rb:137-147`) | ídem | D3 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md) |
| 24 | Procedencia del registro: `customer.rb:26` marca `db_system_id = 2` en cada cliente y nadie lo lee; `CustomerTrapp` (`customer_trapp.rb:1-3`) está declarado y **no se usa en ninguna parte del código** | `db_system.rb:1-8` | D2 de [`../12-CHECKLIST.md`](../12-CHECKLIST.md) |
| 25 | Fecha estimada de entrega de resultados por muestra, columna del reporte `rlabs` (`rlabs/partials/_table.html.erb:11,35`) y del reporte `jobs` (`jobs/partials/_xls_partial_report.erb:38,136-141`) | `rem_correlative.rb:38-40` | [`E-cobertura-tablas.md`](E-cobertura-tablas.md) fila 20 |
| 26 | Los quince contadores de envases por familia y la cantidad de informes pactada, columnas del Excel de `fims` y `rems` | `fims/partials/_xls_partial_report.erb:71-85` | [`E-cobertura-tablas.md`](E-cobertura-tablas.md) fila 19 y [`M-campos-obligatorios.md`](M-campos-obligatorios.md) §6.7 |
| 27 | La firma de quien autoriza el ingreso, **estampada como imagen** en el Excel | `fims/partials/_xls_partial_report.erb:96-100`, `rems/partials/_xls_partial_report.erb:89-93` | [`M-campos-obligatorios.md`](M-campos-obligatorios.md) §8.4 |

---

## Lo que no hace falta portar (hallado en esta revisión)

- **`.xls` que no son Excel.** Los seis parciales `_xls_partial_report.erb` son
  HTML con extensión `.xls`; llevan atributos `bgcolor`, `rowspan` y CSS de
  rotación vertical que Excel interpreta a medias. El único archivo Excel real
  es `otds/export.xlsx.axlsx`. Ya está anotado en "Lo que NO hay que portar" del
  checklist.
- **Dos etiquetas `<img>` rotas** en los Excel de `fims` y `rems`
  (`fims/partials/_xls_partial_report.erb:93`,
  `rems/partials/_xls_partial_report.erb:86`): apuntan a `img_girl.jpg` e
  `img_tree.gif`, archivos de ejemplo de un tutorial que no existen en el
  proyecto. No reproducirlas.
- **Los títulos de pantalla equivocados.** Cinco de las siete pantallas se
  rotulan "Registro de Ingreso de Muestras" aunque tres de ellas no listen
  ingresos (`otds/index.html.erb:1`, `rlabs/index.html.erb:1`,
  `rems/index.html.erb:1`, `fims/index.html.erb:1`). Al reconstruir, nombrarlas
  por lo que responden.

---

## Procedencia de este documento

| Qué se leyó | Para qué |
|---|---|
| `labo_old/app/controllers/report_management/*.rb` (7) | Filtros, permisos y formatos de salida de cada reporte |
| `labo_old/app/views/report_management/**` (20) | Columnas de cada tabla y de cada exportación |
| `labo_old/app/controllers/trapp_management/*.rb` (4) | Qué se escribía en TrafoDex y cuándo |
| `labo_old/app/controllers/json_management/*.rb` (5) + 4 `.jbuilder` | Carga útil y deduplicación del puente |
| `labo_old/app/models/*.rb` (15) | Fórmulas del OTD, días restantes, mapeos de catálogo, conexión secundaria |
| `labo_old/config/routes.rb` | Qué rutas existían y cuáles quedaron sin destino |
| `labo_new/app/Http/Controllers/{DashboardManagement,LabManagement}/**` | Qué de todo eso está construido |
| `labo_new/app/Exports/**` (78) | Si existe alguna exportación de gestión |
| `labo_new/routes/{api,lab_management,dashboard_management}.php` | Si hay endpoint de integración o de exportación |
| `labo_new/resources/js/Pages/{Dashboard,SampleReports,Receptions}/**` | Filtros y columnas que la pantalla ofrece hoy |
| `labo_new/docs/migracion/12-CHECKLIST.md` y `auditoria/**` | Qué ya estaba decidido, para no repetirlo |
