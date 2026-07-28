# TR LAB — sistema de laboratorio de análisis de aceite dieléctrico

> Migración del sistema Rails de 2019 (`xwcamor/labo_old`) a Laravel, sobre el
> núcleo de TrafoDex (`xwcamor/trafodex`).
>
> **Todo el plan vive en [`docs/migracion/`](docs/migracion/00-PLAN-MAESTRO.md)** —
> 13 fases, decisiones cerradas, riesgos, el análisis del sistema viejo y el
> volcado de su esquema. Empezar por ahí.
>
> **`labo_old` y `trafodex` no se tocan.** Decisión del dueño: este repo es el
> único que se modifica. El análisis de `labo_old` y lo que TrafoDex tiene que
> construir para la integración están acá, en `docs/migracion/01-*` y `06-*`.

---

## Fuente de verdad del sistema viejo — LEER ANTES DE BUSCAR NADA

El esquema real de la base del laboratorio **está en este repo**:
[`docs/migracion/esquema/lab_app_development-estructura.sql`](docs/migracion/esquema/lab_app_development-estructura.sql)
(47 tablas, `--no-data`). **No hay que abrir `labo_old` para saber qué columnas
hay.**

Y de `labo_old`, estos tres archivos **NO son fuente de nada**:

| Archivo | Por qué no sirve |
|---|---|
| `db/migrate/` | las tablas se crearon con **SQL directo** contra la base, no con migraciones. Las 30 que hay cubren 18 de 47 tablas |
| `db/schema.rb` | se generó de esas migraciones: describe un esquema anterior |
| `db/seeds.rb` | eran **datos iniciales de prueba**; cambió todo después. Además ni siquiera corre: escribe columnas que ya no existen |

Confirmado por el dueño (2026-07-28). Ya costó una vez: se dio por buena la
cifra de "26 pruebas" porque estaba en el seed, y no hay forma de saber cuántas
son sin el volcado CON DATOS.

**Lo único de `labo_old` que sí es fuente es el CÓDIGO** (`app/models/*.rb`,
`app/views/**`), porque es lo que corre en producción. De ahí salieron los 25
cuadros de límites.

**Lo que falta**: el volcado con datos de `lab_category_detail_types`,
`lab_category_details`, `lab_category_sub_detail_types`,
`lab_category_sub_details`, `lab_category_sub_detail_options`,
`lab_detail_types`, `norms` y `patron_tendences`. Sin eso no se puede cerrar la
fase 4.

---

## Git: una sola rama

**Todo se commitea y se pushea a `main`, directo. No se crean ramas.**

Pedido explícito del dueño (2026-07-28). Ya pasó una vez que el trabajo quedó
repartido entre una rama y `main`, y que ramas de documentación aparecieran en
los otros dos repos — costó tiempo y desconfianza. Si una sesión arranca
posicionada en una rama con otro nombre, lo primero es volver a `main`:

```bash
git checkout main && git pull
```

Nada de `feature/*`, nada de `claude/*`. Si algo necesita aislarse, se habla
antes.

---

## Estado actual: fase 1 EN CURSO — Pruebas de Muestras

> El diseño completo del módulo, y la respuesta a "¿cada prueba no debería
> tener su tabla?", están en
> [`docs/migracion/07-PRUEBAS-DE-MUESTRAS.md`](docs/migracion/07-PRUEBAS-DE-MUESTRAS.md).
> La auditoría del módulo equivalente del sistema Rails, con archivo y línea de
> cada hallazgo, está en
> [`docs/origen-ruby/AUDITORIA-PRUEBAS-DE-MUESTRAS.md`](docs/origen-ruby/AUDITORIA-PRUEBAS-DE-MUESTRAS.md).

**OJO al actualizar: las migraciones de la fase 1 se editaron EN SU LUGAR.**
Todavía no hay ningún despliegue, así que agregar una columna a una migración
sin publicar es más limpio que arrastrar una migración de alteración. Quien ya
haya corrido `php artisan migrate` con una versión anterior tiene que correr
**`php artisan migrate:fresh --seed`**. A partir del primer despliegue real
esto deja de valer: de ahí en adelante, migración nueva siempre.

### Qué se construyó

| Pieza | Dónde |
|---|---|
| Plantillas de ensayo | `test_groups` → `test_definitions` → `test_fields` → `test_field_options` |
| Instrumentos con calibración | `instruments` (ISO 17025) |
| Bancada | `worksheets` → `worksheet_rows` → `worksheet_values` |
| Control de calidad | `qc_charts` → `qc_points` + `qc_duplicates` |
| Archivos de instrumento | `instrument_files` + `instrument_formats` |
| Motor de fórmulas | `app/Services/Lab/Formula*` |
| Westgard y repetibilidad | `app/Services/Lab/WestgardEvaluator`, `RepeatabilityEvaluator` |
| Lector de archivos de instrumento | `app/Services/Lab/InstrumentFileParser` |
| Flujo de la bancada | `app/Services/Lab/WorksheetService` |
| Tipos de columna | `config/lab_field_types.php` (era una tabla; ver el doc) |
| **Capa consultable** | `results` + `ResultMaterializer` + `lab:rebuild-results` |
| Parámetros medibles | `analytes` (la pieza que faltaba) + `analyte_map.json` + `lab:map-analytes` |
| Cálculo en vivo | `worksheets.preview` (lo calcula el SERVIDOR, no el navegador) |
| Conversión de valores | `app/Services/Lab/ValueCoercer` (una sola vez, para guardado y vista previa) |

Rutas en `routes/lab_management.php`, grupo propio `LabManagement`, prefijo
`/lab_management`. Los módulos de catálogo se generaron con `make:module`; las
hojas de trabajo y las cartas de control NO, porque no son catálogos.

### Las cuatro reglas que el sistema viejo tenía en el HTML

Todas se verifican ahora del lado del servidor, y cada una tiene su prueba:

1. **El cálculo.** Era JavaScript guardado en la base e inyectado en la página;
   el campo resultado tenía `readonly`, que un envío directo saltea.
2. **Los campos obligatorios.** Se validaban en el navegador; la validación del
   modelo estaba escrita y comentada.
3. **El bloqueo de la hoja.** Solo escondía botones: ningún controlador lo miraba.
4. **"Primero patrón y duplicado".** Vivía en las opciones de un select.

Y una quinta, de autorización: la pantalla de validar escondía su enlace a los
no supervisores pero la acción verificaba el permiso de **editar**. Por eso
`worksheets.validate` es un permiso aparte de `worksheets.edit`.

### Las dos capas, y por qué NO una tabla por prueba

La pregunta "¿cada prueba no debería tener su tabla?" vuelve sola, así que está
respondida con números en
[`docs/migracion/08-BENCHMARK-VERTICAL-VS-ANCHO.md`](docs/migracion/08-BENCHMARK-VERTICAL-VS-ANCHO.md):
se midieron las dos formas sobre **84 millones de filas**, con índices y planes
de ejecución.

Lo que hay que saber sin abrir el documento:

- Con los índices correctos, **ninguna de las dos formas rompe los 200 ms** a esa
  escala. La consulta de tendencia da 0,32 ms en vertical.
- Con el índice equivocado, la vertical llega a **1.895 ms** — y la ancha, a
  1.807 ms. **El límite no lo pone la forma de la tabla, lo pone el índice.**
- El sistema viejo TENÍA la tabla ancha (`rem_report_details`, 221 columnas) y
  tenía **un solo índice: su clave primaria**. La forma ancha no era lo que
  faltaba, porque ya estaba y no alcanzó.
- La forma ancha gana en disco (7×) y en escritura (5×). La vertical gana en el
  informe consolidado (11× con 29 pruebas) y en el costo de dar de alta la
  prueba 30.

**Los índices de `results` salen de esa medición. No los cambie sin volver a
correr el banco de pruebas** (`database/benchmarks/`). El caso peligroso es
`(analyte_id, measured_at)`, que parece la elección natural para el tablero de
flota y es mil veces peor que `(tenant_id, analyte_id, equipment_id, measured_at)`.

### La capa `results`

`worksheet_values` es la constancia de lo que hizo el analista. `results` es su
lectura tipada, con `equipment_id`, `analyte_id` y `measured_at` en la fila, y es
lo que consultan el informe, las tendencias, el tablero y la API hacia TrafoDex.

Se materializa **al validar la hoja** (no antes: hasta que el supervisor no
firma, un valor no debe aparecer en el informe de un cliente) y se puede
reconstruir entera con `php artisan lab:rebuild-results`. Si eso alguna vez deja
de ser cierto, la capa dejó de ser derivada y hay un problema.

Solo las filas de tipo **muestra** producen resultados: el patrón, el duplicado y
el blanco son control de calidad del método, no del aceite del cliente. Y solo
las columnas que declaran `output_analyte_id`; lo que no está declarado no
informa nada, a propósito.

---

## Estado anterior: fase 0 CERRADA

Verificado en este entorno (PHP 8.4, Composer 2.8, Node 22, PostgreSQL 16):

| Comprobación | Resultado |
|---|---|
| `composer install` | OK |
| `npm install` | OK |
| `php artisan --version` | Laravel Framework 13.9.0 |
| `php artisan route:list` | **629 rutas** cargan sin error |
| `php artisan migrate` | 56 migraciones OK |
| `php artisan db:seed` | OK (175 clientes, 843 ubicaciones, 1940 áreas, 1368 subestaciones) |
| `php artisan make:module Analyte --group=BusinessManagement` | genera el módulo y se auto-registra en `system_modules`, `polymorphic.php` y `purge.php` |
| `npm run build` | verde (828 módulos, 2.4 MB) |
| `php artisan test` | **546 pasan · 50 fallan · 19 se saltean** |

### Las 50 pruebas que fallan

Se reducen a **dos** causas, las dos del dominio eliminado:

- 20 llaman a `DiagnosticCatalogSeeder`
- 19 usan `App\Models\Transformer`
- el resto son cascada de esas dos

**No se borran.** Son pruebas de comportamiento del núcleo (orden, filtros,
exportación, bloqueo de registros, permisos, comentarios, buscador) que apenas
arman su escenario con `Transformer`. Se recablean a `Equipment` en la fase 1.
Borrarlas sería tirar cobertura de núcleo para dejar la suite en verde.

Los archivos afectados: `CustomerCrudTest`, `CustomerCountSortTest`,
`CustomerCountrySortTest`, `CustomerExportTest`, `CustomerFlatFilterTest`,
`CustomerScopedAccessTest`, `CommentTest`, `ExportPdfTemplatesTest`,
`ImportExportPermissionTest`, `OilTypeCrudTest`, `PresetFilterTest`,
`RecordLockTest`, `EquipmentTypeCrudTest`, `SearchTest`.

> El scaffold generó `Analyte` sin pruebas, porque su master `Brand` tampoco
> las tiene. Vale agregarle una suite a `Brand` antes de generar los módulos
> de la fase 1: se hereda a todos.

### Lo que queda tipado a `Transformer` (fase 6)

La pila de compartir y aprobar informes se conserva entera y sin tocar:
`Models/ReportShare`, `Services/Reports/ReportApprovalService`,
`Services/Sharing/ReportShareService`, `ReportShareController`,
`ReportShareLogController`, `ReportVerifyController`. Se recablean a `Sample`.

En `report_instances` la FK a `transformers` se degradó a
`unsignedBigInteger` con índice, para que la migración corra; la fase 6 la
convierte en `sample_id` con su constraint.

### Recuperable del commit base

El editor de reglas de TrafoDex se eliminó para que el build pase, pero **es el
molde del editor de normas y cuadros de límites de la fase 2**:

```bash
git checkout 7b9c489 -- app/Http/Controllers/SystemManagement/DiagnosticRulesController.php \
                        resources/js/Pages/SystemManagement/DiagnosticRules
```

---

## Principio rector (NO romper)

Heredado de TrafoDex, y es la razón de ser de esta migración:

**El código solo tiene fórmulas y flujo; todo lo que puede cambiar (normas,
límites, métodos, parámetros, plantillas de ensayo, textos del informe) vive en
datos.**

El sistema viejo tenía los valores de orientación clavados en ~1.100 líneas de
`if/elsif` repartidas en cuatro métodos. Si piden agregar una condición y el
instinto es escribir un `if` en el motor, casi siempre es una fila de datos.

Ya hubo un intento de arreglarlo: la tabla `rem_conditions` de la base vieja
quedó con 0 filas porque tenía 2 de las 7 dimensiones que un límite necesita.
`spec_sets` + `spec_limits` es esa misma tabla, completa.

---

## Qué NO se trae de TrafoDex

Los motores de diagnóstico y sus datos. **El laboratorio no diagnostica el
equipo**: emite un informe de ensayo contra un criterio de aceptación. El
índice de salud, Duval, la condición IEEE y el tablero de flota son de
TrafoDex, y duplicarlos garantiza que las dos aplicaciones den números
distintos para la misma muestra.

Si hace falta mostrar el índice de salud en un informe del laboratorio, se le
pide a TrafoDex por la API (ver `docs/migracion/04-INTEGRACION-TRAFODEX.md`).

---

## Diferencias con el dominio de TrafoDex

- **`equipment`, no `transformers`.** El laboratorio recibe muestras de 20
  tipos de equipo (conmutadores, reactores, bushings, cables, interruptores,
  electrobombas, intercambiadores…). Llamarlo "transformador" es lo que llevó
  al `if tipo == 10` del sistema viejo.
- **`results`: una fila por parámetro medido.** Reemplaza la tabla de 221
  columnas del sistema viejo. Agregar un parámetro es insertar en `analytes`,
  no una migración.
- **Dos normas distintas**: la de *método* (ASTM D1816 · 2.0 mm, la registra el
  analista al ejecutar el ensayo) y la de *aceptación* (IEEE C57.106-2015, el
  criterio contra el que se juzga). El sistema viejo las mezclaba; acá no se
  sustituyen nunca.
- **El criterio se congela en el informe emitido.** Un informe de 2023 tiene
  que reimprimirse con el límite de 2023 aunque la norma se actualice en 2027.

---

## Convenciones

Las mismas de TrafoDex, sin cambios: convenciones SAP de UI (`.sap-index`,
`.sap-form`, `.show-page sap-show`, `.bulk-bar` al pie), 8 esquemas de color,
tema oscuro, i18n es/en obligatorio, `DerivesAttributesFromLang` en los
FormRequest, `Edit` en vez de reescribir archivos completos.

Y las de trato: español neutro estricto (sin argentinismos), sin emojis,
honestidad brutal — si algo no está al 100 %, se dice.

### Cómo correr cosas

```bash
php artisan test --filter={Modulo}
npm run build
php artisan migrate
php artisan make:module {Nombre} --group={Grupo}
```

Nada se da por cerrado sin construir y correr las pruebas.
