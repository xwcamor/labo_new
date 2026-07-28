# Extracción de los cuadros de límites del sistema viejo

← [Plan maestro](../00-PLAN-MAESTRO.md) · [Normas y límites](../03-NORMAS-Y-LIMITES.md)

> Fase 2, primer paso. Los valores de orientación que estaban clavados en el
> Ruby ahora son datos: `database/seeders/data/spec_limits_legacy.json`.
>
> **Estado: EXTRAÍDO, SIN VALIDAR por el laboratorio.** No sembrar en
> producción hasta resolver las anomalías de abajo.

---

## Qué se extrajo

| | |
|---|---|
| Cuadros | **25** (16 de cromatografía, 9 de fisicoquímico) |
| Límites | **243** |
| Origen | `labo_old/app/models/rem_report_detail.rb`, métodos `set_orientation_croma_values` y `set_orientation_fiqui_values` |
| Método | **extracción programática** de los bloques `RemReportDetail.update()`; los valores no se transcribieron a mano |

Cada cuadro guarda su línea de origen (`app/models/rem_report_detail.rb:471`),
así que cualquier número se puede contrastar contra el Ruby en un paso.

De los 243 límites: 134 parsean como `valor + máximo/mínimo`, 7 son criterios
de texto (condición visual), 101 son `"-"` (sin criterio) y 1 es una anomalía.

---

## Por qué hay dos fuentes y cuál se tomó

Los mismos cuadros están **duplicados** en el sistema viejo:

- `RemReportDetail#set_orientation_*_values` — corre en `after_create`
- `RemReport#refresh_orientation_*_values` — corre en `after_update`

Se compararon las dos, bloque por bloque, de forma automática:

- **Los 30 bloques de valores son idénticos.** Los números no divergen.
- **De las 39 condiciones, una diverge.** Es la anomalía `DIVERGENCIA-1`.

Se tomó la versión de `RemReportDetail` como base (es la que corre primero) y
se documentó la divergencia en vez de elegir en silencio.

---

## Las 8 anomalías

Están en el JSON, en `anomalias[]`, con su gravedad. Resumen:

### DIVERGENCIA-1 — alta · los límites cambian con el segundo guardado

```ruby
# al CREAR   (rem_report_detail.rb:447)
if @transformer_type_id.to_i == 1 or == 2 or == 3 or == 4 or == 5 or == 13
# al ACTUALIZAR (rem_report.rb:517)
if @transformer_type_id.to_i < 10 or @transformer_type_id.to_i == 13
```

Los tipos **6 (instrumento), 7 (bushing) y 8 (cables)** con aceite mineral
caen al `else` cuando se crea el informe — salen todos los gases en `"-"` — y
reciben sus valores reales recién en el primer reguardado. El mismo informe
muestra criterios distintos según cuántas veces se guardó.

El tipo 9 (interruptor) también está afectado, pero su cuadro es todo `"-"` de
todas formas, así que es inocuo.

**Decisión tomada**: el cuadro por tipo es el correcto — existe y tiene valores
publicados. La rama de creación estaba incompleta. Los tres cuadros se
conservan en el JSON, marcados.

### DIVERGENCIA-2 — media · girasol sin norma al crear

`save_default_values` cubre los aceites `{1,2,3,8}`, `{4}`, `{7}`, `{5}`.
`refresh_orientation_fiqui_values` cubre `{1,2,3,8}`, `{4}`, `{7}`, `{5,6,9}`.

Un informe de girasol (aceite 6) recién creado imprime `-` donde va la norma;
guardado dos veces imprime `IEC 610203-2025`.

### ACEITES-SIN-CUADRO — alta

Los aceites **2, 3, 8 y 9** reciben norma pero **ninguna rama los captura**
para asignarles límites: caen al `else` y salen todos en `"-"`. Hay que decidir
si son alias de mineral/vegetal o fluidos con criterio propio. Sin esa
respuesta, cualquier muestra de esos aceites se emite sin criterio.

### RIG-GAP — alta

`rig` (40/47/50 kV) y `rigep` ("electrodos planos") se tratan como parámetros
distintos, cuando son **la misma propiedad medida por dos métodos**. D877 fija
2.54 mm; D1816 admite 1.0 o 2.0 mm y los kV no son comparables. Sin el gap
registrado no se puede saber contra qué norma se juzgó lo histórico.

Es el mismo pendiente que TrafoDex tiene abierto en su backlog.

### ESTER-SINTETICO — media

El aceite 7 se trata de dos formas contradictorias: en fisicoquímico comparte
cuadro con el vegetal (`oil == 5 or 7`), en cromatografía tiene cuadro propio
("Midel"). Uno de los dos está mal.

### HORNO-REVISAR — media

El propio código marca el cuadro de horno mineral con `#####REVISAR **`.

### IEC-610203 — media

`IEC 610203-2025` no es un número de norma IEC válido (probablemente IEC 61203
o IEC 62770). Se está imprimiendo en informes firmados.

### C2H2-VOLTAJE — baja

En "De voltaje · Mineral" el acetileno dice `"16"`, sin `máximo`. El parser lo
lee igual, pero el informe imprime `16` pelado.

---

## Las condiciones van por CÓDIGO, no por ID

Los cuadros condicionan por `oil_type_code` y `equipment_type_code`
(`mineral`, `bushing`), no por el ID numérico del sistema viejo.

En la primera versión de esta extracción sí iban por ID, y el
`LabCatalogsSeeder` clavaba los IDs del Ruby para que coincidieran. Era cómodo
para verificar, y estaba mal: acopla las claves primarias del sistema nuevo a
las de un sistema muerto. Alguien que en 2028 lea `oil_type_id = 7` necesitaría
un mapa de 2019 para saber que es Midel — o sea, exactamente el "mandrakeo" del
que esta migración se trata de salir.

La correspondencia ID viejo → código vive en `_meta.aceites` y `_meta.equipos`
del JSON, y sirve **solo** para el ETL de la fase 12.

---

## Lo que NO se hizo, a propósito

**No se corrigió ningún número.** El JSON refleja lo que el sistema viejo hace
hoy, con sus contradicciones marcadas. Corregir en la extracción haría
imposible verificar la paridad después.

El orden es: extraer → verificar contra los informes emitidos → que el
laboratorio firme los números → recién ahí sembrar.

## Siguiente paso: verificar contra la realidad

El JSON sale del **código**. La base tiene la **copia aplicada** de cada
informe emitido, en `rem_report_details.*_ori`. Contrastar las dos responde
sola varias preguntas:

- si los cuadros inalcanzables de DIVERGENCIA-1 aparecen en informes reales
  (y en cuántos),
- qué aceites tienen muestras de verdad, y si 2, 3, 8 y 9 se usan,
- si algún informe salió con criterios que el código actual ya no produce.

Para eso hace falta el volcado con datos:

```bash
mysqldump -u USER -p lab_app_development rem_report_details \
  --where="deleted=0" --no-create-info > ori_aplicados.sql
```

Esa tabla no tiene nombres de clientes: son números y textos de límite.
