# Normas, métodos y límites como datos

> El problema central del sistema viejo y la parte del rediseño que más hay que
> cuidar. Si esto queda bien, agregar una norma nueva o una edición nueva es
> cargar filas; si queda mal, se vuelve a necesitar un despliegue por cada
> cambio normativo.

---

## 1. Tres cosas distintas que hoy están mezcladas

| Concepto | Ejemplo | Quién lo define | Dónde vive hoy |
|---|---|---|---|
| **Norma de método** | ASTM D1816, 2.0 mm | El analista, al ejecutar el ensayo | `lab_sub_details` (campo "Norma", `num_pos: 2`) |
| **Norma de aceptación** | IEEE C57.106-2015 | El criterio de evaluación del informe | `rem_report_details.fiq_norm_id`, asignado por `oil_type_id` clavado |
| **Norma de diagnóstico** | IEC 60599-2022, Duval | El motor que interpreta gases | TRAFODEX (no es del laboratorio) |

La confusión entre las dos primeras es lo que produce informes internamente
incoherentes: el PDF imprime "ASTM D877" como método y al lado un límite de
`45.0 - mínimo` que sale de la tabla de D1816. Son separaciones de electrodos
distintas (D877 fija 2.54 mm; D1816 admite 1.0 o 2.0 mm) y los kV no son
comparables entre sí.

**Decisión**: las tres se modelan en la misma tabla `standards` con un campo
`kind`, pero se usan en lugares distintos y **nunca** se sustituyen entre sí.
El laboratorio es dueño de las dos primeras; la tercera es de TRAFODEX.

---

## 2. `analytes` — el parámetro, separado de cómo se mide

Error del sistema viejo: `rig` y `rigep` son dos columnas; `f25`, `f90` y `f100`
son tres. En realidad hay **dos parámetros**:

- Rigidez dieléctrica, medida por D877 (electrodos planos) o D1816 (semiesféricos,
  a 1.0 o 2.0 mm).
- Factor de potencia, medido a 25 °C, 90 °C o 100 °C.

Modelado:

```
analytes
  code=rig   name="Rigidez dieléctrica"   unit=kV   direction=higher_better
  code=pot   name="Factor de potencia"    unit=%    direction=lower_better
  code=acid  name="Número ácido"          unit="mg KOH/g"  direction=lower_better
  code=ten   name="Tensión interfacial"   unit="mN/m"  direction=higher_better
  code=wat   name="Contenido de agua"     unit=ppm  direction=lower_better
  code=col   name="Color"                 unit=—    direction=lower_better
  code=con   name="Condición visual"      unit=—    direction=qualitative
  code=h2 …  (los 9 gases)
  code=fal   name="2-Furfuraldehído"      unit=ppb  direction=lower_better
  …

test_methods
  analyte=rig  standard=ASTM D877   conditions={"gap_mm":2.54}  label="ASTM D877 · 2.54 mm"
  analyte=rig  standard=ASTM D1816  conditions={"gap_mm":1.0}   label="ASTM D1816 · 1.0 mm"
  analyte=rig  standard=ASTM D1816  conditions={"gap_mm":2.0}   label="ASTM D1816 · 2.0 mm"
  analyte=pot  standard=ASTM D924   conditions={"temp_c":25}    label="ASTM D924 · 25 °C"
  analyte=pot  standard=ASTM D924   conditions={"temp_c":100}   label="ASTM D924 · 100 °C"
```

Con esto:

- El informe muestra **una** fila "Rigidez dieléctrica" con el método al lado,
  en vez de dos filas que compiten.
- El límite se busca por `(analyte, test_method)`, así que la separación de
  electrodos deja de ser un supuesto.
- Agregar "rigidez a 1.0 mm" es una fila, no una columna nueva en 6 archivos.

> **Nota de arrastre**: TRAFODEX tiene el mismo asunto anotado en su
> `CLAUDE.md` — los umbrales de `rig` vienen del Ruby viejo sin registro del
> gap y están rotulados 2.0 mm, pendiente de confirmar con el laboratorio. En
> TR LAB el gap queda **registrado por ensayo** desde el día uno, así que ese
> pendiente se resuelve solo hacia adelante. Para lo histórico hay que
> decidir con qué método se etiqueta lo migrado (ver fase 12).

---

## 3. `spec_sets` — el cuadro de valores de orientación

Un `spec_set` es un cuadro completo: el conjunto de límites que aplica a una
combinación. Es la traducción a datos de cada bloque `update_orientations` del
Ruby.

```
spec_sets
  id, slug, tenant_id
  standard_id        → norma de aceptación (IEEE C57.106 ed. 2015)
  label              "Fisicoquímico · Mineral · Potencia · 69-230 kV · En servicio"
  oil_type_id        nullable (null = cualquiera)
  equipment_type_id  nullable
  service_state      new | in_service | null
  voltage_from, voltage_to   nullable (kV)
  power_from, power_to       nullable (MVA)
  effective_from, effective_to   ← VIGENCIA
  is_active, source_note, sort_order
```

### 3.1 Ejemplo: el bloque mineral de `RemReportDetail`

El código actual:

```ruby
if @transformer_oil_type_id.to_i == 1
  if @num_ten <= 69
    aci_ori: "0.20 - máximo", rig_ori: "40.0 - mínimo", ten_ori: "25.0 - mínimo",
    agu_ori: "35.0 - máximo", f25_ori: "0.50 - máximo", f100_ori: "5.0 - máximo",
    con_ori: "Brillante y Claro"
  elsif @num_ten > 69 && @num_ten < 230
    aci_ori: "0.15 - máximo", rig_ori: "47.0 - mínimo", ten_ori: "30.0 - mínimo", …
  elsif @num_ten >= 230
    aci_ori: "0.10 - máximo", rig_ori: "50.0 - mínimo", ten_ori: "32.0 - mínimo", …
```

Se convierte en tres `spec_sets` y sus `spec_limits`:

| spec_set | analyte | operator | min | max | display |
|---|---|---|---|---|---|
| Mineral · ≤69 kV | acid | `<=` | — | 0.20 | `0.20 - máximo` |
| Mineral · ≤69 kV | rig | `>=` | 40.0 | — | `40.0 - mínimo` |
| Mineral · ≤69 kV | ten | `>=` | 25.0 | — | `25.0 - mínimo` |
| Mineral · ≤69 kV | wat | `<=` | — | 35.0 | `35.0 - máximo` |
| Mineral · ≤69 kV | pot (25 °C) | `<=` | — | 0.50 | `0.50 - máximo` |
| Mineral · ≤69 kV | pot (100 °C) | `<=` | — | 5.0 | `5.0 - máximo` |
| Mineral · ≤69 kV | con | `text` | — | — | `Brillante y Claro` |
| Mineral · 69-230 kV | acid | `<=` | — | 0.15 | `0.15 - máximo` |
| … | | | | | |

Los cuadros a extraer del código (inventario completo, para la fase 2):

**Fisicoquímico** — conmutador ≤69 / >69; mineral ≤69 / 69-230 / ≥230; silicona;
éster ≤72.5 / 72.5-170 / ≥170. Total: 9 cuadros.

**Cromatografía** — conmutador; reactor mineral; mineral por tipo de equipo
(distribución, potencia, horno, corriente, voltaje, instrumento, bushing,
cables, interruptor); silicona; Midel; soya; girasol. Total: 15 cuadros.

24 cuadros × ~8-11 límites = alrededor de 230 filas de `spec_limits`. Se cargan
con un seeder desde un JSON editable, igual que `cromas_rules.json` en TRAFODEX.

### 3.2 Resolución de un cuadro

```php
SpecSetResolver::resolve(Sample $sample, string $group, ?Carbon $at = null): ?SpecSet
```

1. Filtra `spec_sets` activos del grupo (fiqui/dga/…) cuya vigencia cubra `$at`
   (por defecto `sample.sampled_at`, **no** `now()`).
2. Filtra por `oil_type_id`, `equipment_type_id`, `service_state` y por rango
   de tensión/potencia del equipo.
3. Prefiere el del tenant sobre el global (mismo patrón que
   `ChromatographyEngine::resolveRuleSet` en TRAFODEX).
4. Entre varios candidatos gana **el más específico**: se puntúa cuántos
   criterios no nulos coinciden.
5. Si no hay ninguno → devuelve `null`.

**`null` no es "todo bien".** El informe marca esos parámetros como
`sin_criterio` y la narrativa dice explícitamente
"no se tiene referencia de valores típicos para este fluido y tipo de equipo".
Nunca se rellena con `"-"` en silencio como hoy.

Esto es la contraparte exacta de la lección que TRAFODEX ya aprendió: un aceite
sin reglas devolvía "100 Excelente" y ocultaba una muestra peligrosa.

### 3.3 La vigencia es lo que arregla los informes históricos

```
spec_sets  standard=IEEE C57.106 ed.2015  effective_from=2015-01-01  effective_to=2020-03-31
spec_sets  standard=IEEE C57.106 ed.2019  effective_from=2020-04-01  effective_to=null
```

Cuando el laboratorio adopte una edición nueva:

1. Se carga el cuadro nuevo con `effective_from` = fecha de adopción.
2. Se cierra el anterior poniéndole `effective_to`.
3. **No se toca ningún informe emitido**: `report_findings` guarda el límite y
   el código de norma copiados.

Y `standards.superseded_by_id` permite avisar en pantalla:
"IEEE C57.106-2015 fue reemplazada por la edición 2019; hay 43 muestras
pendientes que todavía usan la anterior".

---

## 4. Cómo entra la norma que registró el analista

Éste es el arreglo concreto del reclamo "las normas no se actualizan de acuerdo
a las que tenía guardadas en las pruebas individuales".

Flujo nuevo:

```
1. El analista ejecuta la prueba en la hoja de trabajo.
   El campo "Norma" (test_field type=standard) apunta a un test_method concreto.
      → worksheet_values.option_id → test_field_options.standard_id

2. Al validar la hoja, se materializa el resultado:
      results.test_method_id = el método REGISTRADO por el analista
      results.value_num      = el valor del campo con output_analyte_id

3. Al armar el informe, para cada result:
      spec_limit = SpecLimitResolver::for($specSet, $result->analyte_id,
                                          $result->test_method_id)
```

`SpecLimitResolver` busca en este orden:

1. Límite del cuadro para `(analyte, test_method)` exacto.
2. Límite del cuadro para `(analyte, test_method = null)` — genérico.
3. Nada → `sin_criterio`.

Con eso, si el analista midió por D877 y el cuadro tiene un límite específico
para D877, se usa ése; si el cuadro solo define el genérico, se usa el genérico
pero el informe **muestra el método real** al lado. Y si el cuadro define
límites solo para D1816 y el ensayo se hizo por D877, sale `sin_criterio` con
aviso, en vez de comparar peras con manzanas.

### 4.1 Validación al cerrar la hoja de trabajo

Se agrega un chequeo que hoy no existe: si el método registrado por el analista
no tiene límite en el cuadro vigente, la hoja se puede cerrar igual pero el
informe queda con una advertencia visible para el supervisor. Es una
inconsistencia real del laboratorio, no un error de software; el sistema tiene
que exhibirla, no taparla.

---

## 5. Evaluación y veredicto

```php
LimitEvaluator::evaluate($result, $specLimit): Verdict
```

Devuelve `dentro | fuera | sin_criterio | no_medido` más una `severity` opcional.

Reglas:

- `<=` → fuera si `value > max`
- `>=` → fuera si `value < min`
- `between` → fuera si `value < min || value > max`
- `text` → comparación normalizada (sin acentos, minúsculas) contra `text_value`
- Sin límite → `sin_criterio`
- Sin `result` → `no_medido` (distinto de "cumple")

`warn_ratio` (opcional, por límite) marca "cerca del límite" — por ejemplo
`0.9` en un `<=` pinta ámbar entre el 90 % y el 100 % del máximo. Es el
equivalente del `cell_alert_sev` de TRAFODEX y **nunca** oculta un valor fuera
de norma.

El parseo de texto desaparece. Hoy el límite se recupera con
`(aci_ori.strip.delete! "(máximo)").to_f`, que además usa `delete!` (destructivo,
devuelve `nil` si no encontró nada, y ahí `to_f` da `0.0` → todo queda "fuera de
norma"). En el modelo nuevo el límite ya es numérico; `display_override` es solo
presentación.

---

## 6. Edición desde la interfaz

Se clona el editor que TRAFODEX ya tiene en `/system_management/diagnostic-rules`:

| Pantalla | Qué edita | Quién |
|---|---|---|
| Normas | `standards`: código, edición, organismo, vigencia, reemplazo | super |
| Parámetros | `analytes`: nombre, unidad, decimales, dirección, grupo | super |
| Métodos | `test_methods`: norma + condiciones + acreditación | super / admin |
| Cuadros de límites | `spec_sets` + `spec_limits`, con vista de tabla completa | super global; admin copia-al-escribir de su tenant |
| Plantillas de texto | `narrative_templates` por bloque, condición e idioma | admin |

Copia-al-escribir (igual que los `rule_sets` de TRAFODEX): cuando el admin de
un workspace edita un cuadro global, se crea su copia con `tenant_id`;
"restaurar" borra el override y vuelve a caer en el de fábrica.

Cada cuadro guarda `source_note` — de dónde salió el número (tabla, página de
la norma, correo del cliente). Hoy esa trazabilidad solo existe como comentario
de código, y en varios casos ni eso: hay un `#####REVISAR **` sobre los valores
de horno mineral y un `cro9_ori: "16"` sin la palabra "máximo" en transformador
de voltaje.

---

## 7. Pendientes que el laboratorio tiene que confirmar

Salieron del análisis del código y no se pueden resolver leyendo:

1. **Gap de rigidez de los datos históricos.** Los límites de `rig` (40/47/50 kV)
   corresponden a D1816, pero la columna `rigep` ("electrodos planos") sugiere
   que también se mide por D877 sin registrar el gap. Confirmar con qué método
   y gap se midió lo histórico antes de migrar.
2. **Valores de horno mineral.** El propio código los marca `#####REVISAR **`.
3. **`cro9_ori: "16"`** en transformador de voltaje: falta la palabra
   "máximo"; hoy el parser lo interpreta igual, pero es una fila a verificar.
4. **Aceites 2, 3, 8 y 9.** El código los mapea a la norma de mineral
   (`oil_type_id == 1 or 2 or 3 or 8`) pero no tienen cuadro de límites propio.
   Hay que decidir si son alias de mineral o fluidos distintos.
5. **Éster sintético.** `set_orientation_fiqui_values` lo trata junto con
   vegetal (`== 5 or == 7`), pero `save_default_values` le asigna otra norma
   (`oil_type_id == 7` → IEC 610203-2025 por separado). Uno de los dos está mal.
6. **"IEC 610203-2025"** no es un número de norma IEC válido (probablemente
   IEC 61203 o IEC 62770). Verificar antes de imprimirlo en informes firmados.
7. **Umbrales de OTD** (5 / 2 / 3 días): confirmar si son compromiso comercial
   o meta interna, y si varían por cliente.
