# TR LAB — sistema de laboratorio de análisis de aceite dieléctrico

> Migración del sistema Rails de 2019 (`xwcamor/labo_old`) a Laravel, sobre el
> núcleo de TrafoDex (`xwcamor/trafodex`).
>
> **El plan vive en [`docs/migracion/`](docs/migracion/00-PLAN-MAESTRO.md)** —
> 13 fases, decisiones cerradas, riesgos. Empezar por ahí.
> El análisis del sistema viejo queda en `labo_old/docs/migracion/01-*` porque
> documenta ese repo, no éste.

---

## Estado actual: fase 0 en curso

| | |
|---|---|
| Commit base | copia de TrafoDex @ `9a3b2f6`, sin modificar |
| Poda | hecha: 192 archivos del dominio de diagnóstico |
| Recableado | **pendiente** — la aplicación todavía no arranca |
| Dependencias | `composer install` / `npm install` sin correr todavía |

**La puerta de salida de la fase 0** es:
`php artisan make:module Prueba --group=X` genera un módulo que compila y pasa
sus pruebas. Hasta entonces la fase no está cerrada.

### Los 14 archivos a recablear

No se borraron porque son núcleo, no dominio. Referencian clases eliminadas:

| Archivo | Qué hay que hacer | Fase |
|---|---|---|
| `Models/ReportShare.php` | tipado a `Transformer` → pasa a `Sample` | 6 |
| `Services/Reports/ReportApprovalService.php` | ídem | 6 |
| `Services/Sharing/ReportShareService.php` | ídem | 6 |
| `Controllers/.../ReportShareController.php` | ídem | 6 |
| `Controllers/.../ReportShareLogController.php` | ídem | 6 |
| `Controllers/ReportVerifyController.php` | ídem | 6 |
| `Controllers/SystemManagement/DiagnosticRulesController.php` | **es el molde del editor de normas y cuadros de límites** — se reescribe, no se tira | 2 |
| `Controllers/.../OilTypeController.php` | quitar el conteo de transformadores | 1 |
| `Controllers/.../CommentController.php` | quitar `Transformer` de los comentables | 1 |
| `Controllers/SearchController.php` | quitar transformadores del buscador global | 1 |
| `Middleware/HandleInertiaRequests.php` | quitar la inyección de colores de diagnóstico | 1 |
| `Console/Commands/SetupProjectCommand.php` | quitar los seeders eliminados | 1 |
| `config/polymorphic.php` | quitar las entradas de muestras | 1 |
| `config/purge.php` | ídem | 1 |

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
