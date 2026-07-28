# Qué se reutiliza de TRAFODEX y qué no

> TR LAB es una **aplicación Laravel aparte**, con su propia base de datos, que
> arranca desde una copia del núcleo de TRAFODEX. No es un módulo dentro de
> TRAFODEX ni un repositorio compartido por paquete.

---

## 1. Por qué aplicación aparte

Se evaluaron tres opciones:

| Opción | A favor | En contra |
|---|---|---|
| **A. Módulo dentro de TRAFODEX** | una sola base, cero integración | mezcla dos dominios (LIMS vs diagnóstico de activos), dos públicos y dos ritmos de despliegue; el laboratorio pasa a poder tumbar la app de flota; los clientes de TRAFODEX que no son laboratorio cargan con 30 tablas que no usan |
| **B. Paquete Composer compartido** | núcleo único, sin duplicar | el núcleo de TRAFODEX no está extraído como paquete; hacerlo ahora es un refactor grande de un sistema en producción, con riesgo alto y valor diferido |
| **C. App aparte, núcleo copiado** | cada dominio evoluciona a su ritmo; la integración queda explícita y auditable; TR LAB nace limpio | el núcleo se duplica: un arreglo en tenancy o auditoría hay que aplicarlo dos veces |

**Elegida: C.** El costo de la duplicación es real pero acotado (el núcleo está
estable), y es mucho menor que el riesgo de A o el refactor de B. Si más
adelante la divergencia molesta, extraer el núcleo a un paquete es un paso que
se puede dar **después**, con las dos aplicaciones ya funcionando.

Además, es la forma en que el negocio ya funciona: hoy son dos sistemas
separados con dos bases separadas.

---

## 2. Se reutiliza tal cual

Todo esto se copia de TRAFODEX y se usa sin rediseñar. Es el "core" del que
habla el pedido.

### Infraestructura

- **Multi-empresa**: traits `BelongsToTenant` y `BelongsToTenantOrGlobal`,
  `HideSuperScope`, `tenant_id` nullable, bypass de super.
- **Permisos**: Spatie Permission con los traits propios; rutas con
  `permission:{modulo}.{accion}`; roles `super | admin | user`.
- **Auditoría**: trait `Auditable`, log polimórfico, `BuildsRecordAudit`.
- **Papelera**: SoftDeletes + trash + restore + force-delete + `deleted_by` +
  `deleted_description`.
- **Bloqueo de registros**: trait `Lockable`.
- **Dependencias**: trait `HasDependents` (impedir borrar lo que está en uso).
- **Restricción por cliente asignado**: `RestrictedToAssignedCustomers` —
  directamente aplicable a los analistas que solo ven ciertos clientes.

### Interfaz y experiencia

- Inertia + Vue 3 + Ant Design Vue 4 + Tailwind v4, con las convenciones SAP ya
  fijadas (`.sap-index` / `.sap-form` / `.show-page sap-show`, `.bulk-bar`
  pegada al pie, `FormFooter floating`, papeleras con `ResponsiveTable`).
- Los 8 esquemas de color, tema oscuro, responsive móvil.
- Filtros avanzados con conectores AND/OR por cláusula (`FilterApplier`).
- Vistas guardadas, selector de columnas, favoritos, vistos recientemente,
  búsqueda global.
- Operaciones masivas con paso a cola sobre 200 registros, deshacer 60 s,
  duplicar, editar todo en lote.
- Exportación CSV en streaming + Excel/PDF/Word asíncronos.
- Importación en 3 capas con vista previa y confirmación en dos pasos.
- Internacionalización es/en completa, con el trait
  `DerivesAttributesFromLang` en los FormRequest.
- Tour de incorporación, notificaciones, mensajería interna.

### Informes

- Firmantes por workspace (`report_signers`) con relación traducible.
- Flujo de aprobación por lote: `report_requests` → `report_instances` →
  `report_approvals`, con marca "EN REVISIÓN" mientras no está aprobado.
- Verificación HMAC + QR contra el log de auditoría, con portal público
  `/verify/{code}`.
- Generación de PDF con dompdf y de Word con PhpWord, incluidas las lecciones
  ya aprendidas: nada de `@php(...)` en línea en el blade, helper `$safe()`
  para símbolos que Helvetica no tiene, pie de página por canvas, `w:sz` en
  medios puntos enteros en OOXML.

### Herramientas de desarrollo

- **`php artisan make:module {Nombre} --group={Grupo}`**: genera ~50 archivos
  (controlador, servicio, modelo, 9 FormRequest, 6 jobs, 3 exports, 1 import,
  6 páginas Vue, 13 componentes, config, i18n × 2, migración, factory) y
  registra el módulo. Todos los catálogos de TR LAB salen de aquí.
- El editor de reglas de `/system_management/diagnostic-rules` es el molde
  directo del editor de normas y cuadros de límites (semáforos editables,
  copia-al-escribir por tenant, restaurar de fábrica, datasets JSON).
- `FeatureGate` + `config/features.php` para el gating por plan.

---

## 3. Se rehace desde cero

### Clientes

El `Customer` de TRAFODEX está pensado para el dueño de la flota, con jerarquía
cliente → ubicación → subestación y "clientes asignados" a usuarios. En el
laboratorio el cliente es quien contrata y factura, y puede no ser el dueño del
equipo. Se rehace con `customer_contacts`, `customer_sites`, `customer_areas` y
`external_ref` al cliente equivalente de TRAFODEX.

Sí se copia el **patrón** (el módulo Customer sigue siendo la referencia de
módulo completo: jerarquía, logo, API REST), no la tabla.

### Equipos

`Transformer` de TRAFODEX es un transformador con ejes de diagnóstico
(`health_index`, `fault_type`, `gassing_rate`, `paper_dp`, `ieee_condition`).
En el laboratorio el objeto es un **equipo** genérico del que se toma una
muestra de fluido: transformador, conmutador, reactor, bushing, cable,
interruptor, electrobomba, intercambiador. Sin campos de diagnóstico: eso vive
en TRAFODEX.

Se rehace como `equipment` (ver `02-MODELO-DE-DATOS.md`).

### Muestras y resultados

`chromatographicals`, `fiquis`, `furanos`, `fpots` de TRAFODEX son tablas de
**una fila por muestra con una columna por parámetro**. Sirven bien para el
diagnóstico (siempre son los mismos 9 gases, los mismos 5 fisicoquímicos), pero
no para un laboratorio que hoy corre 26 pruebas y mañana 30.

TR LAB usa `results` (una fila por parámetro medido). Es el mismo motivo por el
que se descarta `rem_report_details`.

---

## 4. No se trae bajo ningún concepto

Los motores de diagnóstico de TRAFODEX:
`ChromatographyEngine`, `DuvalService`, `RatioMethodsService`,
`FuranoDiagnosisService`, `FiquisDiagnosisService`, `FpotDiagnosisService`,
`HealthIndexService`, `IeeeDgaStatusService`, y sus datos
(`cromas_rules.json`, `duval_zones.json`, `ratio_methods.json`,
`ieee_c57104_*.json`).

Motivo: **el laboratorio no diagnostica el equipo**. Emite un informe de ensayo
contra un criterio de aceptación. El diagnóstico (índice de salud, tipo de
falla, triángulos de Duval, condición IEEE) es competencia de TRAFODEX y ya
está verificado ahí — duplicarlo garantiza que las dos aplicaciones terminen
dando números distintos para la misma muestra.

Tampoco se trae el tablero de flota, ni las tendencias por transformador, ni el
pronóstico.

> Corolario práctico: si alguien pide "que el informe del laboratorio muestre
> el índice de salud", la respuesta no es portar el motor, es pedírselo a
> TRAFODEX por la API (ver `04-INTEGRACION-TRAFODEX.md`, sección 5).

---

## 5. Divergencias del núcleo que hay que aceptar

Al copiar el núcleo, TR LAB tendrá cosas que TRAFODEX no necesita y viceversa:

| TR LAB agrega | TRAFODEX no lo necesita |
|---|---|
| Instrumentos y calibraciones | — |
| Cartas de control y reglas de Westgard | — |
| Almacén de reactivos con lotes y vencimiento | — |
| Hojas de trabajo con fórmulas evaluadas en servidor | — |
| Correlativos anuales de muestra | — |
| Indicadores OTD | — |

Se documenta en el `CLAUDE.md` de TR LAB para que no se intente "sincronizar"
los dos núcleos por simetría.

---

## 6. Procedimiento de arranque del repositorio

1. Copiar el esqueleto de TRAFODEX **sin** los módulos de negocio ni los
   motores de diagnóstico: `app/Traits`, `app/Support` (menos
   `Support/Diagnostics`), `app/Http/Controllers/Concerns`, `app/Services`
   (menos `Services/Diagnostics`), `resources/js/Layouts`,
   `resources/js/Components` (los transversales), `config` del núcleo,
   `app/Console/Commands/MakeModuleCommand.php` y sus plantillas.
2. Mantener: usuarios, roles, permisos, tenants, planes, ajustes, idiomas,
   países, módulos del sistema, auditoría, notificaciones, mensajería,
   vistas guardadas, favoritos, informes/firmas/aprobaciones.
3. Borrar: `transformers`, `chromatographicals`, `fiquis`, `furanos`, `fpots`,
   `tests`, `standards`, `variables`, `rule_sets`, `rules`, `rule_conditions`,
   `result_scales`, `diagnostic_datasets`, `fiqui_params`, `fiqui_thresholds`,
   el tablero de flota y todos sus seeders.
   > `standards` se borra y se vuelve a crear con el esquema de TR LAB
   > (`kind`, `edition`, `superseded_by_id`): no es la misma tabla.
4. Renombrar la aplicación, ajustar `config/app.php`, `.env.example` y la marca.
5. Correr `php artisan make:module` para el primer catálogo y verificar que el
   generador quedó funcional en el repositorio nuevo. **Esta verificación es
   la puerta de salida de la fase 0**: si el generador no funciona, todas las
   fases siguientes se hacen a mano.
