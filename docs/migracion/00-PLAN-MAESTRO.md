# Plan maestro — migración del laboratorio de Rails a Laravel (TR LAB)

> Migración del sistema de laboratorio (`labo_old`, Ruby on Rails 2019-2023) a
> una aplicación Laravel nueva construida sobre el núcleo de TRAFODEX.
>
> Documentos de apoyo:
> - [`01-ANALISIS-SISTEMA-ACTUAL.md`](01-ANALISIS-SISTEMA-ACTUAL.md) — qué existe hoy y qué está roto
> - [`02-MODELO-DE-DATOS.md`](02-MODELO-DE-DATOS.md) — el esquema nuevo
> - [`03-NORMAS-Y-LIMITES.md`](03-NORMAS-Y-LIMITES.md) — normas y límites como datos
> - [`04-INTEGRACION-TRAFODEX.md`](04-INTEGRACION-TRAFODEX.md) — la API
> - [`05-REUSO-DEL-CORE.md`](05-REUSO-DEL-CORE.md) — qué se copia de TRAFODEX y qué no

---

## 1. Decisiones tomadas

Se resolvieron en el análisis. Están para no volver a discutirlas; si alguna no
convence, es mejor cambiarla ahora que en la fase 6.

| # | Decisión | Motivo |
|---|---|---|
| 1 | **Aplicación Laravel aparte**, base de datos propia, núcleo copiado de TRAFODEX | dominios y públicos distintos; ver `05` §1 |
| 2 | **PostgreSQL**, no MySQL | alineado con TRAFODEX; `unaccent`, JSON, tipos numéricos serios |
| 3 | La tabla ancha `rem_report_details` **desaparece**: una fila por parámetro medido | agregar un parámetro deja de ser una migración |
| 4 | Normas, límites, métodos, parámetros y textos **son datos**, no código | es el pedido explícito y el mismo principio rector de TRAFODEX |
| 5 | Los límites tienen **vigencia** (`effective_from/to`) y se **congelan** en el informe emitido | un informe de 2023 no puede cambiar cuando se actualiza la norma en 2027 |
| 6 | La **norma de método** (la que registra el analista) es dato de primera clase y participa en la resolución del límite | es exactamente el reclamo del sistema viejo |
| 7 | `transformers` → **`equipment`** (genérico) | el laboratorio recibe muestras de 20 tipos de equipo, no solo transformadores |
| 8 | Clientes y equipos **se rehacen**, no se copian de TRAFODEX | el modelo del laboratorio es distinto |
| 9 | Integración por **API HTTP con idempotencia y cola**, nunca más escritura directa en la base ajena | ver `04` §1 |
| 10 | El **diagnóstico del equipo no se porta**: es de TRAFODEX | dos motores = dos números distintos para la misma muestra |
| 11 | Las **fórmulas de cálculo** se evalúan en el servidor con expresiones declarativas, no con JavaScript guardado en la base | hoy el servidor no puede recalcular ni validar |
| 12 | Se conserva la numeración `REM-{año}-{n}`, `{año}-{4 dígitos}` y `REP-LAB-{año}-{4 dígitos}` | el laboratorio y sus clientes ya la usan |

### Lo que hay que confirmar con el laboratorio antes de la fase 2

Sin estas respuestas la fase 2 se puede empezar, pero no cerrar. Lista completa
en `03-NORMAS-Y-LIMITES.md` §7. Los tres que más pesan:

1. Con qué método y separación de electrodos se midió históricamente la rigidez
   (D877 a 2.54 mm o D1816 a 1.0/2.0 mm). Afecta a la migración de datos.
2. Si los aceites 2, 3, 8 y 9 son alias de mineral o fluidos con criterio propio.
3. Si "IEC 610203-2025" existe o es un error de tipeo por IEC 61203 / IEC 62770.
   Se está imprimiendo en informes firmados.

---

## 1.bis El motivo real de urgencia

La migración no es solo modernización. El laboratorio **comparte 14 tablas**
con la base del sistema Rails viejo de transformadores (`tr_app_development`),
y no solo las de muestras: también `customers`, `countries`, `oil_types`,
`marks`, `conmutation_types` y la jerarquía de sedes y subestaciones.

Diez de esos modelos ni siquiera lo declaran de forma visible: heredan de una
clase abstracta `Primary2` y parecen modelos normales del laboratorio.
Inventario completo en `01-ANALISIS-SISTEMA-ACTUAL.md` §4.

Esa base es la del **TRAPP original en Ruby**, no la de TrafoDex: MySQL contra
PostgreSQL, `physicals` contra `fiquis`, `num_hid` contra `h2`. Cuando TrafoDex
reemplace al sistema viejo en producción, el laboratorio no se queda con una
integración obsoleta: **se queda sin clientes y sin catálogos**, porque nunca
los tuvo propios.

Consecuencia para el plan: la fase 1 incluye crear esas cinco tablas y
migrarles los datos, y el laboratorio no puede quedar desacoplado después del
corte de TrafoDex — tiene que estarlo antes.

---

## 2. Riesgo conocido antes de empezar

`db/schema.rb` de `labo_old` solo declara **18 de las 47 tablas reales**. Las
tablas centrales (`rems`, `rem_correlatives`, `rem_reports`,
`rem_report_details`, `transformers`, `stocks`, todos los catálogos) se crearon
fuera de las migraciones y no están versionadas.

**RESUELTO para la base del laboratorio.** El volcado de estructura está en
[`esquema/lab_app_development-estructura.sql`](esquema/lab_app_development-estructura.sql)
(47 tablas, 100 `ALTER TABLE`, **0 filas de datos** — es `--no-data`, se puede
versionar sin riesgo). Las cifras de este plan ya salen de ahí, no de inferencia:

- `rem_report_details`: **221 columnas** (66 `_val`, 37 `_ori`, 29 `_display`,
  29 `_lab_detail_id`, 15 `_date`, 15 `_comment`, 6 `_norm_id`, 24 varias)
- `rem_reports`: 43 · `rems`: 41 · `norms`: 5 (solo `id`, `name`, `deleted`)
- Confirmado que **no existen** `customers`, `countries`, `oil_types`, `marks`
  ni `conmutation_types` en la base del laboratorio (ver §1.bis)
- Aparece `rem_conditions`: el intento previo de sacar los límites a datos,
  con 0 filas y sin ninguna referencia en el código (ver `01` §3.2)

**Sigue pendiente**: el `mysqldump --no-data` de `tr_app_development`, para las
cinco tablas que hay que repatriar, y un volcado con datos anonimizado para
probar el ETL de la fase 12.

---

## 3. Las fases

Trece fases. El orden no es arbitrario: la fase 2 va antes que todo el flujo
operativo porque el motor de límites es de lo que dependen las fases 5, 6 y 12.

Las estimaciones son para **una persona a tiempo completo** e incluyen pruebas.
Son órdenes de magnitud, no compromisos.

---

### Fase 0 — Cimientos (2-3 semanas)

Levantar el repositorio nuevo con el núcleo funcionando.

- Repositorio `labo` (o `tr-lab`) con el esqueleto de TRAFODEX, siguiendo el
  procedimiento de `05-REUSO-DEL-CORE.md` §6.
- PostgreSQL + `unaccent`, cola `database`, disco `local`.
- Usuarios, roles, permisos, tenants, planes, ajustes, idiomas, auditoría,
  notificaciones, mensajería, vistas guardadas, favoritos.
- Marca y textos base es/en.
- `CLAUDE.md` propio del repositorio.
- Volcado de estructura y de datos anonimizado de la base vieja.

**Puerta de salida**: `php artisan make:module Prueba --group=X` genera un
módulo que compila, pasa sus pruebas y se ve bien en claro y oscuro.

---

### Fase 1 — Maestros del laboratorio (2-3 semanas)

- Catálogos con el generador: `equipment_types`, `oil_types`, `brands`,
  `oil_brands`, `oil_volume_units`, `tap_changer_types`, `preservations`,
  `sampling_points`, `samplers`, `containers`, `report_reasons`, `countries`.
- `instruments` con calibración y vencimiento (nuevo; lo pide ISO 17025).
- **Clientes** con contactos, sedes y áreas.
  > No es rediseñar: es **crear lo que no existe**. `customers`, `countries`,
  > `oil_types`, `marks` y `conmutation_types` viven hoy en la base del sistema
  > viejo de transformadores. Hay que crear las tablas **y migrarles los datos**
  > desde `tr_app_development`.
- **Equipos** (`equipment`) con tensión y potencia numéricas, y con los **20
  tipos** reales del laboratorio. Hoy se mandan al otro sistema colapsados a 3
  (todo lo que no es potencia/distribución/horno viaja como "potencia").
- Importación de equipos desde Excel, reemplazando `Transformer.import` (que
  hoy lee columnas por letra `A..N` y revienta si falta un catálogo).

**Puerta de salida**: se puede cargar un cliente con sus equipos e importar un
Excel real del laboratorio sin tocar código.

---

### Fase 2 — Normas, métodos y límites (3-4 semanas) — **fase crítica**

Es el corazón del pedido. Detalle completo en `03-NORMAS-Y-LIMITES.md`.

- Tablas `standards`, `analytes`, `test_methods`, `spec_sets`, `spec_limits`.
- **Extracción** de los 24 cuadros del código Ruby a un JSON versionado
  (9 de fisicoquímico + 15 de cromatografía, ~230 límites).
- `SpecSetResolver` (aceite × tipo de equipo × banda de tensión × vigencia,
  con preferencia tenant sobre global y desempate por especificidad).
- `SpecLimitResolver` (analito + método registrado → límite).
- `LimitEvaluator` (`dentro | fuera | sin_criterio | no_medido`).
- Editor en pantalla, clonado del de reglas de TRAFODEX, con copia-al-escribir
  por workspace y restaurar de fábrica.

**Puerta de salida**: para cada uno de los 24 cuadros, una prueba automatizada
que compara el resultado del resolutor nuevo contra el bloque de código Ruby
equivalente. Se acepta la fase cuando los 24 coinciden y las diferencias se
explican (no se acomoda el motor para que empate: si el Ruby estaba mal, se
documenta y gana el motor nuevo — mismo criterio que usó TRAFODEX en su
verificación contra el sistema viejo).

> Regla operativa desde aquí: si aparece una condición nueva y el instinto es
> escribir un `if` en el motor, casi siempre es una fila de datos.

---

### Fase 3 — Recepción de muestras (3 semanas)

- `receptions`, `samples`, `sample_tests`.
- Generación de correlativos anuales, con bloqueo por transacción (hoy
  `RemCorrelative.create` dentro de un `times do` es una condición de carrera:
  dos recepciones simultáneas pueden tomar el mismo número).
- Checks de conformidad (envase / volumen / tarjeta) con motivo de rechazo.
- Prioridad, fecha comprometida, semáforo de días restantes.
- Bandeja de recepción con los estados y filtros.
- **Solo se crean las tareas de las pruebas solicitadas**, no las 26.
- Etiquetas con QR por muestra (portar `stickers`).

**Puerta de salida**: se registra una recepción de 5 muestras con 3 pruebas cada
una y quedan 15 tareas, no 130.

---

### Fase 4 — Bancada: plantillas y hojas de trabajo (4-5 semanas)

- `test_groups`, `test_definitions`, `test_fields`, `test_field_options`.
- Editor de plantillas de ensayo (el `pr_management/configurations` del viejo,
  rehecho).
- `worksheets`, `worksheet_rows`, `worksheet_values`.
- **Motor de fórmulas en servidor** sobre expresiones declarativas, con
  migración de las `blur_calculation` existentes (son pocas: número ácido, agua,
  furanos).
- Carga de la hoja de trabajo con la muestra elegida por **selector**, no
  escribiendo el código a mano.
- Filas de patrón, muestra y duplicado.
- Cierre y validación de hoja por el supervisor.

**Puerta de salida**: las 26 pruebas del sistema viejo quedan cargadas como
plantillas y una hoja de trabajo real se puede completar de punta a punta.

---

### Fase 5 — Resultados y evaluación (2-3 semanas)

- Tabla `results`: al validar una hoja, los campos con `output_analyte_id` se
  materializan como resultados, con su método, analista, instrumento y
  condiciones de laboratorio.
- Evaluación contra el cuadro resuelto por la fase 2.
- Vista consolidada por muestra: todos los parámetros con valor, método,
  límite y veredicto.
- Repetición de ensayo (`replaced_by_id`) sin borrar el original.

**Puerta de salida**: la pantalla equivalente a la del sistema viejo
("Análisis de Resultado") muestra los mismos números para una muestra real,
pero con la norma de método correcta al lado de cada límite.

---

### Fase 6 — Informe de laboratorio (4-5 semanas)

- `reports`, `report_findings`, `report_narratives`.
- **Congelado del criterio**: `report_findings` copia límite y código de norma.
- `narrative_templates` + generador, reemplazando los cinco bloques ERB
  duplicados. Texto generado y texto final separados, para que el supervisor
  edite sin perder el original.
- Numeración `REP-LAB-{año}-{4 dígitos}` con transacción.
- PDF con dompdf replicando el formato actual (`1.txt_examples/MODELO DE
  INFORME.pdf` es la referencia) y Word editable.
- Firmantes, flujo de aprobación por lote y verificación HMAC + QR, todo
  reutilizado de TRAFODEX.
- Informes adicionales y de corrección, ligados al principal.

**Puerta de salida**: un informe emitido con el sistema nuevo y el mismo informe
del sistema viejo, puestos lado a lado, coinciden en valores, límites y
veredictos; las diferencias de redacción están aprobadas por el laboratorio.

---

### Fase 7 — Integración con TRAFODEX (3 semanas)

Del lado de TR LAB: `integration_targets`, `outbound_messages`, job de envío con
reintento exponencial, bandeja de envíos, bandeja de conciliación de equipos.

Del lado de TRAFODEX: `LabResultApiController`, abilities `lab:write` y
`transformers:*`, `idempotency_keys`, `sample_documents`, endpoint de búsqueda
de transformador, disparo del recálculo de diagnóstico y caché de flota.

Contrato completo en `04-INTEGRACION-TRAFODEX.md`.

**Puerta de salida**: enviar el mismo informe dos veces crea **una** muestra en
TRAFODEX; el PDF queda adjunto y descargable desde la ficha del transformador;
el índice de salud se recalcula solo.

---

### Fase 8 — Control de calidad analítica (3 semanas)

- `qc_charts` y `qc_results` alimentados de las filas de patrón.
- Cartas de control (Levey-Jennings) en **una** vista parametrizada por
  analito, no diez parciales de 405 líneas.
- Reglas de Westgard (1_3s, 2_2s, R_4s, 4_1s, 10x) con alerta.
- Repetibilidad a partir de los duplicados.
- "Valores constantes" (los patrones de referencia) y "límites de tendencias"
  del sistema viejo.

**Puerta de salida**: la carta de control de un analito reproduce la del
sistema viejo con los mismos datos históricos.

---

### Fase 9 — Archivos de instrumento (2-3 semanas)

- Carga de los `.txt` del cromatógrafo y de furanos (formatos reales en
  `1.txt_examples/HITACHI/`).
- Analizadores por formato, declarados como datos (`instrument_formats`:
  separador, fila de encabezado, mapa columna → campo), no con IDs clavados
  como hoy (`lab_category_sub_detail_id IN (80,81,82,83,84)`).
- Vista previa antes de confirmar, reutilizando la importación en 3 capas.
- Trazabilidad: qué archivo originó qué fila.

---

### Fase 10 — Almacén y etiquetas (2 semanas)

- `stock_items`, `stock_lots`, `stock_movements`, con vencimiento de lote y
  alerta de mínimo (mejora sobre el viejo, que no controla vencimientos).
- Préstamo y devolución.
- Consumo asociado a la hoja de trabajo.

---

### Fase 11 — Indicadores (2 semanas)

- OTD, tiempo de emisión, tiempo de entrega, con umbrales **configurables**
  (hoy son constantes en el modelo).
- Carga por analista, por prueba y por cliente.
- Trabajos pendientes, informes sin emitir, muestras vencidas.
- Exportación a Excel con el motor del núcleo, reemplazando los parciales ERB
  de 250 líneas.

---

### Fase 12 — Migración de datos históricos (4-6 semanas)

La fase más larga y la que más depende de decisiones del laboratorio.

- ETL MySQL → PostgreSQL, seeders idempotentes con volcados versionados
  (mismo patrón que los `Legacy*Seeder` de TRAFODEX).
- **La transposición**: cada `rem_report_details` de ~250 columnas se convierte
  en N filas de `results`. Un mapa columna → analito + método.
- Vinculación de las hojas de trabajo históricas con sus muestras
  (hoy es por texto; hay que resolverlo y **reportar los huérfanos**, no
  descartarlos en silencio).
- Los informes ya emitidos se cargan con sus límites tal como salieron, no
  recalculados.
- Ceros imposibles: el sistema viejo escribía `0` donde no se midió
  (TRAFODEX ya se topó con esto: 626 ensayos con solo D877 y 104 con solo el
  factor a 100 °C aparecieron al anular los ceros). Hay que decidir por
  parámetro si `0` es "no medido" o un valor real.
- Reconciliación: recuentos por año, por cliente y por prueba, viejo contra
  nuevo, con el detalle de lo que no cuadra.

**Puerta de salida**: informe de reconciliación aprobado por el laboratorio,
con la lista explícita de lo que no se pudo migrar y por qué.

---

### Fase 13 — Corte y puesta en producción (2-3 semanas)

- Despliegue (droplet, base blindada según `docs/DROPLET-POSTGRES-SECURITY.md`
  de TRAFODEX).
- Capacitación de analistas y supervisores.
- **Corte paralelo**: 2-4 semanas cargando en los dos sistemas y comparando
  informes antes de apagar el viejo.
- `labo_old` queda en solo lectura, no se apaga de inmediato.
- Respaldos, monitoreo, manual de usuario.

---

## 4. Camino crítico y paralelizables

```
0 ──> 1 ──> 3 ──> 4 ──> 5 ──> 6 ──> 7 ──> 13
      └──> 2 ────────────┘
                          
En paralelo, una vez cerrada la fase 5:
  8 (control de calidad)   ── independiente
  9 (archivos instrumento) ── depende de 4
  10 (almacén)             ── independiente desde la fase 1
  11 (indicadores)         ── depende de 6
  12 (migración)           ── se puede empezar en cuanto cierre la fase 5;
                              hay que terminarla antes de la 13
```

Total del camino crítico: **32 a 42 semanas** para una persona. Con dos
personas y las fases 8-11 en paralelo, del orden de 22 a 28 semanas.

---

## 5. Qué se entrega en cada fase

Cada fase cierra con:

- Migraciones y seeders idempotentes.
- Pruebas (Feature + Unit) verdes: `php artisan test --filter={Modulo}`.
- `npm run build` sin errores.
- Interfaz revisada en claro y oscuro, y en móvil.
- Textos en es/en.
- Permisos sembrados en `RolesAndPermissionsSeeder`.
- Entrada en la barra lateral.
- Documentación de la fase en `docs/`.

Nada se da por cerrado sin construir y correr las pruebas.

---

## 6. Riesgos y cómo se atacan

| Riesgo | Impacto | Mitigación |
|---|---|---|
| El laboratorio se queda sin clientes ni catálogos cuando TrafoDex reemplace al TRAPP viejo | **crítico** | las cinco tablas prestadas se crean y se migran en la fase 1; el desacople tiene que estar listo **antes** del corte de TrafoDex, no después |
| El esquema real de la base vieja no está versionado | alto | volcado de estructura en la fase 0, antes de diseñar el ETL |
| Los 20 tipos de equipo colapsan a 3 al enviarse (bushings diagnosticados como transformadores de potencia) | alto | la API viaja con códigos; TrafoDex rechaza explícitamente lo que no sabe diagnosticar en vez de aceptarlo como "potencia" |
| Los valores de orientación del código tienen errores (`#####REVISAR`, `"16"` sin unidad) | alto | la fase 2 los extrae uno por uno y el laboratorio los firma |
| Los datos históricos tienen ceros que significan "no medido" | alto | decisión por parámetro en la fase 12, con recuento del impacto |
| La rigidez histórica no registra el gap | medio | pregunta 1 de §1; si no hay respuesta, se migra como "método no registrado" y se excluye de comparaciones entre métodos |
| Divergencia entre el núcleo de TR LAB y el de TRAFODEX | medio | se acepta y se documenta; extraer un paquete común queda como paso posterior |
| La integración duplica muestras en TRAFODEX | alto | `Idempotency-Key` obligatorio, validado en la puerta de salida de la fase 7 |
| El corte se hace sin paralelo y aparece un vacío | alto | fase 13 con 2-4 semanas de doble carga obligatorias |
| Alcance que crece durante la migración | medio | la migración replica el sistema viejo con la deuda arreglada; toda funcionalidad nueva va a una lista aparte, después del corte |

---

## 7. Lo primero que conviene hacer

1. Conseguir el `mysqldump --no-data` de producción.
2. Responder las tres preguntas de §1.
3. Arrancar la fase 0.

Las fases 0 y 2 se pueden solapar: mientras se arma el repositorio, la
extracción de los 24 cuadros a JSON es trabajo de escritorio que no depende de
que haya código funcionando.
