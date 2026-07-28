# TR LAB — sistema de laboratorio de análisis de aceite dieléctrico

> Migración del sistema Rails de 2019 (`xwcamor/labo_old`) a Laravel, sobre el
> núcleo de TrafoDex (`xwcamor/trafodex`).
>
> **El plan vive en [`docs/migracion/`](docs/migracion/00-PLAN-MAESTRO.md)** —
> 13 fases, decisiones cerradas, riesgos. Empezar por ahí.
> El análisis del sistema viejo queda en `labo_old/docs/migracion/01-*` porque
> documenta ese repo, no éste.

---

## Estado actual: fase 0 CERRADA

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
`RecordLockTest`, `TransformerTypeCrudTest`, `SearchTest`.

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
