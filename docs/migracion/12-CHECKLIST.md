# Checklist: problema · solución · cómo lo verifico

> Sale de las nueve auditorías del sistema viejo (ver
> [`11-AUDITORIA-VIEJO-VS-NUEVO.md`](11-AUDITORIA-VIEJO-VS-NUEVO.md)). Cada
> línea tiene **qué está mal**, **qué hay que hacer** y **cómo se comprueba que
> quedó bien** — con un comando o una pantalla concreta, no "revisar que ande".
>
> **Estado**: `[ ]` pendiente · `[x]` hecho y verificado · `[?]` decisión del
> dueño, sin código hasta que responda.
>
> Los dos informes para comparar se generan con:
> ```
> php artisan report:compare            # la primera muestra con resultados
> php artisan report:compare 2026-0001  # una en concreto
> ```
> Deja `informe-VIEJO-*.pdf` y `informe-NUEVO-*.pdf` en `storage/app/comparacion`.

---

## Bloque A — antes de emitir un informe a un cliente

### A1. `[x]` Límites de detección

**Problema.** El informe viejo no imprimía el valor medido cuando caía por
debajo del límite de detección: imprimía el límite con un `<`. Un hidrógeno de
0.4 ppm salía `< 1`. Esos cortes estaban clavados en el HTML, repetidos hasta
tres veces por gas. El sistema nuevo imprimía `0.4` — un número con una
precisión que el método no tiene.

**Solución.** Columna `test_fields.detection_limit`, aplicada al imprimir.
Editable desde la ficha de la columna. **Solo presentación**: el veredicto se
decidió al validar con el valor medido y no se toca — mezclarlos haría que el
papel y el criterio discrepen otra vez. El `<` que tipea el analista gana sobre
el del catálogo. Valores de fábrica sembrados desde
`database/seeders/data/detection_limits.json`.

**Cómo lo verifico.**
```
php artisan test --filter="limite_de_deteccion"     # 5 pruebas en verde
php artisan db:seed --class=LabDetectionLimitsSeeder  # "sembrados: 15"
```
En el PDF: un gas por debajo del corte tiene que salir `< 1`, `< 0.3`, `< 4`…
y su color de celda tiene que seguir siendo el que le corresponde por norma.

### A2. `[?]` El alcance acreditado: ¿D3612 y D1275 están o no?

**Problema.** El papel viejo estampaba el sello ANAB y el párrafo del
certificado en las hojas de cromatografía y azufres, **mientras sus propias
filas decían (NA)**. Verificado contra la base: de 37 métodos, 13 están
marcados como acreditados y ninguno es de esas dos familias. Las dos
afirmaciones no pueden ser ciertas a la vez.

**Solución.** No es código. Mirar el certificado AT-2596: si el alcance incluye
D3612 y D1275, se marcan acreditadas y el sello vuelve solo. Si no, el papel
viejo venía estampando un sello que no correspondía.

**Cómo lo verifico.** El listado de métodos y su marca:
```
php artisan tinker --execute="foreach (App\Models\TestFieldOption::with('field.definition')->whereHas('field', fn(\$q)=>\$q->where('role','standard'))->get() as \$o) printf(\"%-38s %-30s %s\n\", \$o->field?->definition?->code, \$o->value, \$o->is_accredited?'SI':'no');"
```
Después: generar el PDF y confirmar que el sello sale en las hojas que el
certificado cubre y en ninguna más.

### A3. `[ ]` Nueve familias sin plantilla de análisis

**Problema.** Partículas, sedimentos, metales, viscosidad, DBDS, inflamación,
fluidez, inhibidor y pasivador salen con el párrafo **en blanco**. Hay 6
plantillas de 15.

**Solución.** Escribir las nueve en `diagnosis_templates`, con los umbrales del
viejo (están transcritos en la auditoría §3.2). Va junto con A4 y A5, porque
casi todas necesitan bandas y el valor medido.

**Cómo lo verifico.** Una muestra con esas pruebas validadas, autodiagnóstico
generado, y las 15 filas de la página de análisis con texto. Ninguna vacía.

### A4. `[ ]` Bandas graduadas en el autodiagnóstico

**Problema.** El motor solo distingue "ninguno / uno / varios" fuera de norma.
El viejo tenía bandas por valor: furanos (DP 700 / 450 / 250), grado de
polimerización (1000 / 650 / 350), pasivador (50 / 70), inhibidor (0.08 / 3).
Hoy furanos dice lo mismo con DP 800 que con DP 200.

**Solución.** Agregar `bands[]` a la plantilla (rango + texto propio), o
resolverlo con escalones de `spec_limits`.

**Cómo lo verifico.** Cuatro muestras de furanos, una por banda, tienen que dar
cuatro párrafos distintos. Test que fije los cuatro cortes.

### A5. `[ ]` El texto tiene que poder citar el valor medido

**Problema.** Los marcadores son `{ok} {failed} {norm} {count}`. No hay
`{value}`. Se pierden "se detectó **7.3 ppm** de dibencil disulfuro", "punto de
inflamación a **X °C**", el código ISO 4406 desglosado y el resultado del
azufre por método.

**Solución.** Agregar `{value}` / `{value:analito}` con su unidad al armado del
texto.

**Cómo lo verifico.** El párrafo de DBDS de una muestra real tiene que traer el
número. Test con una plantilla que use `{value}`.

### A6. `[ ]` Enlazar la fila de bancada con la prueba pedida

**Problema.** El servicio sabe resolver muestra, prueba y equipo a partir de
`sample_test_id`, pero **el controlador no acepta ese campo** y la grilla manda
el código tipeado. Toda fila que un analista cargue a mano queda sin enlace.
Medido: en la base de demostración las 120 filas **sí** lo tienen, porque el
sembrador llama al servicio directo — el problema es de la pantalla, y por eso
no se ve en la demostración.

Sin ese enlace: el avance de la muestra nunca se escribe, el equipo cae al
tipeado a mano, y **el bloque de condiciones del informe sale vacío en todas
las páginas**.

**Solución.** Aceptar `sample_test_id` en la validación de `saveRow`, mandar
las pruebas pendientes a la pantalla, y poner un selector en la grilla.

**Cómo lo verifico.**
```
php artisan tinker --execute="echo App\Models\WorksheetRow::where('kind','sample')->whereNull('sample_test_id')->count();"
```
Tiene que dar 0 **después de cargar una fila desde el navegador**, no solo
después del sembrador. Y el PDF de esa muestra tiene que traer fecha de
análisis, temperatura y humedad.

### A7. `[ ]` El descargo legal en todas las páginas

**Problema.** El viejo lo tenía en el pie de todas; el nuevo, solo en la
última. En un formato donde cada hoja se lee sola —y se fotocopia suelta— es un
retroceso.

**Solución.** Moverlo al pie común del blade.

**Cómo lo verifico.** Buscar el texto en cada página del PDF; tiene que
aparecer tantas veces como páginas tenga.

### A8. `[ ]` Los azufres en una sola hoja

**Problema.** El viejo imprimía los tres sub-ensayos (1275B, 62535 48 h, 62535
72 h) en la misma hoja. Hoy salen en tres.

**Solución.** Declarar la familia. **Solo datos**: una entrada en
`config('lab.report_families')` o el campo "Tabla del informe" de la prueba.

**Cómo lo verifico.** Una muestra con los tres azufres tiene que dar **una**
sección con tres filas.

### A9. `[ ]` Los cinco furanos no se imprimen

**Problema.** Detectado al sembrar los límites de detección: en la prueba de
furanos solo `grado_de_polimerizacion` tiene `report_visible`. Los cinco
compuestos (2-FAL, 5-HMF, 2-ACF, 5-MEF, 2-FOL) tienen `role = none` y **no
salen en el informe**. El viejo los imprimía los cinco, más el DP.

**Solución.** Marcarlos `role = result` con su analito y `report_visible`.
Es dato, no código.

**Cómo lo verifico.** El PDF de una muestra con furanos tiene que traer seis
filas, no una.

### A10. `[ ]` El idioma del autodiagnóstico queda congelado

**Problema.** El texto se genera y se guarda. Si se genera con la sesión en
inglés, el informe en español imprime el texto en inglés. Se vio al armar la
comparación: el párrafo salió con "contenido de agua **and** rigidez
dieléctrica".

**Solución.** Decidir: guardar el idioma junto al texto y regenerar al cambiar,
o generar siempre en el idioma del workspace.

**Cómo lo verifico.** Generar con la sesión en inglés y abrir el informe en
español: no puede haber una palabra en inglés.

---

## Bloque B — antes de que el laboratorio opere solo

### B1. `[ ]` Renombrar el código de una columna no puede romper fórmulas

**Problema.** Al editar solo se revisa la fórmula del campo que se toca. Si se
renombra `peso_aceite_g`, la fórmula del número ácido queda apuntando a un
código inexistente y el resultado se guarda **vacío, sin error y sin aviso**.
Es el mismo tipo de falla que el viejo tenía al reordenar columnas.

**Solución.** Replicar el chequeo del borrado en la edición cuando cambia el
`code` — o reescribir las fórmulas dependientes en la misma transacción. Y
revalidar `instrument_formats.column_map`, que también referencia por código.

**Cómo lo verifico.** Test: renombrar una columna usada por otra fórmula tiene
que rebotar con el listado de fórmulas afectadas, o dejarlas reescritas.

### B2. `[ ]` Validar los 25 cuadros de límites y darles pantalla

**Problema.** El JSON dice "SIN VALIDAR por el laboratorio", con siete
anomalías del viejo documentadas. Y **no hay pantalla de edición**: solo se
cargan por seeder. De `spec_status` dependen el color de cada celda, el
veredicto y todo el texto generado.

**Solución.** Revisarlos con el laboratorio y construir el editor.

**Cómo lo verifico.** Cambiar un límite desde la pantalla, revalidar una hoja y
ver el veredicto cambiar. Y que el JSON deje de decir "sin validar".

### B3. `[ ]` Importar los límites de control del viejo

**Problema.** Los 45 valores reales hay que cargarlos carta por carta a mano.
Y las cartas del viejo tienen los cinco límites pero **no la desviación**:
cargadas tal cual, las reglas de Westgard quedan apagadas.

**Solución.** Importador `patron_tendences → qc_charts`, derivando `sd` (por
ejemplo `(LCS − LC) / 3`) y marcándolo como derivado.

**Cómo lo verifico.** Tras importar, ninguna carta con `sd` nulo, y un patrón
fuera de control tiene que disparar una regla de Westgard.

### B4. `[ ]` Que un patrón fuera de control avise

**Problema.** Se pinta el punto y se guarda la regla violada, pero la hoja
sigue publicando resultados y nadie se entera. Es el mismo agujero operativo
del viejo, con mejor diagnóstico.

**Solución.** Decidir si bloquea o solo notifica, y cablearlo.

**Cómo lo verifico.** Cargar un patrón fuera de control y comprobar que llega
la notificación (y, si se decide, que la hoja no publica).

### B5. `[ ]` Política de instrumento con calibración vencida

**Problema.** Se avisa en el selector y se puede cargar y publicar igual. Es
defendible, pero **hoy no está escrito en ningún lado**. Para ISO 17025 el
mínimo es: bloquear, exigir motivo, o dejar constancia en el resultado.

**Solución.** Decidir la política y aplicarla en el servidor, no solo en la UI.

**Cómo lo verifico.** Test que intente guardar una fila con un instrumento
vencido y compruebe el comportamiento elegido.

### B6. `[ ]` Congelar el estado de calibración en el resultado

**Problema.** `results` no guarda el instrumento ni su vigencia al momento del
ensayo. Dentro de dos años, responder "¿esto se midió con el equipo calibrado?"
exige cruzar contra el estado **actual**, que ya cambió. Es el mismo error que
ya se corrigió para los límites de las cartas de control.

**Solución.** Columnas congeladas en `results`, escritas al materializar.

**Cómo lo verifico.** Vencer la calibración de un instrumento después de
validar una hoja: el resultado tiene que seguir diciendo que estaba vigente.

### B7. `[ ]` Las plantillas de texto a una tabla editable

**Problema.** Viven en un JSON leído **del disco**. No hay tabla, ni seeder, ni
pantalla. Cambiar una coma sigue exigiendo un deploy — que es exactamente lo
que este proyecto vino a resolver.

**Solución.** Tabla `diagnosis_templates` con `tenant_id` para override por
workspace, seeder desde el JSON, y pantalla de edición.

**Cómo lo verifico.** Cambiar una frase desde la pantalla y ver el informe
nuevo con ese texto, sin tocar archivos.

---

## Bloque C — paridad con el viejo

### C1. `[ ]` Bitácora diaria de condiciones del laboratorio

El viejo tenía dos tablas por fecha (presión, temperatura, humedad) con CRUD, y
**precargaba** el formulario con la fila del día. Hoy hay que tipear todo en
cada hoja. Falta además la **presión atmosférica**, que para cromatografía es
dato de ensayo y no tiene columna en ninguna parte.
**Verificación:** cargar la bitácora del día y abrir una hoja nueva: los tres
valores tienen que venir puestos.

### C2. `[ ]` `worksheets.sample_temp_c` no tiene dónde cargarse

La columna existe, es asignable y **el informe la imprime** — pero no está en
el formulario ni en la validación del controlador. Son dos líneas.
**Verificación:** cargarla desde la pantalla y verla en el PDF.

### C3. `[ ]` Los cuatro catálogos que se volvieron texto libre

Marca de aceite, unidad de volumen, punto de muestreo y motivo del informe eran
catálogos con CRUD y hoy son texto libre. Es el camino por el que la base vieja
terminó con "2500 gal", "2500 galones" y "2500Gal" en la misma columna.
**Verificación:** que el campo sea un selector y que no se puedan escribir dos
variantes del mismo valor.

### C4. `[ ]` Bloque RELACIONES de cromatografía

TGC, TGC-CO, TGC(%), CH₄/H₂, C₂H₂/C₂H₄, C₂H₂/C₂H₆, C₂H₄/C₂H₆, CO₂/CO, O₂/N₂ y
los cinco porcentajes. Todas las fórmulas están transcritas en la auditoría.
Van como campos calculados con `report_visible`, no como código en el blade.
**Verificación:** comparar los números contra el PDF viejo de la misma muestra
(`php artisan report:compare`).

### C5. `[ ]` Repetibilidad del factor de potencia y CV de la rigidez

Se perdieron **antes** de esta migración: la planilla Excel calculaba el rango
del FP contra un límite `0.01 + 0.1 × máx` y el coeficiente de variación de las
5 rupturas de la rigidez, y el Ruby no los portó. El nuevo tiene réplicas
tipadas y fórmulas por campo, que es la vía para recuperarlos.
**Verificación:** una hoja de rigidez con 5 lecturas tiene que mostrar el CV.

### C6. `[ ]` Etiquetas con QR

El viejo tenía dos mecanismos que no se hablaban: una etiqueta sin QR con el
responsable **clavado en el HTML** ("Flor Palacios"), y un módulo aparte cuyo
QR codificaba **una URL de producción escrita en el modelo** que llevaba a la
remisión padre detrás de un login. Una etiqueta por impresión: 40 muestras, 40
clics.
**Qué cambia:** el QR sale del código de la muestra y lleva a la verificación
pública que ya existe; impresión **por lote**; tamaño de etiqueta como dato.
**Verificación:** seleccionar una recepción de 40 muestras y obtener un PDF con
40 etiquetas; escanear una y llegar a la muestra correcta sin iniciar sesión.

### C7. `[ ]` Almacén

No existe nada en el nuevo. En el viejo era **préstamo de equipos**, no
insumos, y tenía un defecto de fondo: `stocks.qty` **nunca se toca** — no baja
al prestar ni sube al devolver. Tampoco registra quién pide, quién autoriza ni
para cuándo. Y `stock_units` ni siquiera tiene controlador.
**Qué cambia:** el saldo se deriva de los movimientos, no es un número tipeado.
**Verificación:** prestar y devolver, y que el disponible cierre solo. No poder
prestar más de lo que hay.

### C8. `[ ]` Los reportes gerenciales

Siete pantallas del viejo que en realidad son cuatro cosas: un indicador (OTD),
una matriz de resultados, **un listado de recepciones repetido cuatro veces**
con cuatro Excel distintos, y un listado de informes.
**Qué cambia:** los umbrales (5 / 2 / 3 días) dejan de ser constantes de código
y pasan a `Setting`; y el OTD mide contra la **fecha comprometida** (`due_at`),
que es la métrica que el viejo quería y no podía calcular.
**Ojo:** el Excel del viejo y su propio modelo usan **dos definiciones
distintas de OTD** en la misma pantalla. Hay que elegir una.
**Verificación:** una recepción entregada después de su fecha comprometida
tiene que contar como fuera de plazo; y el número no puede cambiar según el día
en que se baje el reporte.

---

## Bloque D — decisiones del dueño

| # | Decisión | Por qué no puedo decidirla yo |
|---|---|---|
| D1 | **Los 20 tipos de equipo contra los 3 de TrafoDex** | El viejo mapeaba con "si es mayor a 3 → Potencia". TrafoDex tiene 3 y agregar los 17 es insertar filas, pero un tipo sin cuadro de reglas de cromas cae al respaldo IEEE, y eso cambia el índice de salud |
| D2 | **La columna de procedencia** | El viejo marcaba `db_system_id = 2` en cada cliente creado desde el laboratorio. Nunca se leyó y **se perdió**: en TrafoDex no existe. Sin ella no se puede responder "¿esto entró por el laboratorio?" |
| D3 | **Qué jerarquía lleva un equipo creado por API** | El viejo fabricaba sede `"-"` y área `"-"` desde una vista, en un GET. Ahora nadie las fabrica |
| D4 | **La corrección de tensión interfacial** | Es por densidad (Harkins–Jordan), no por temperatura, y **nunca estuvo en el Ruby**: vive en una planilla Excel con las constantes del tensiómetro clavadas en la celda. Si el sistema la calcula, hay que decidir de dónde salen esas constantes |
| D5 | **El tramo intermedio del número ácido** | El viejo imprimía `0.01` —un número forzado, no el medido— para valores entre 0.005 y 0.010. ¿Criterio o parche? |
| D6 | **Almacén: ¿solo préstamo de equipos, o también reactivos?** | Un laboratorio acreditado necesita lote y vencimiento de reactivo. El viejo no cubre ninguno de los dos bien |

---

## Lo que hay que limpiar

- `[ ]` El permiso `worksheets.validate` está sembrado y **ninguna ruta lo usa**.
- `[ ]` `Worksheet::STATUS_CLOSED` está declarado y **nadie lo escribe**.
- `[ ]` `test_field_options` de cromatografía y azufres: definir el flag real
  (depende de A2).

## Lo que NO hay que portar

Del viejo hay código muerto que no vale la pena buscar después: `supervisors`
(sin ruta y sin tabla), `tickets` (sin modelo), `report_management/reports` (el
modelo no existe y el CRUD entero explota), `sample_management/samples`
(archivo de 0 bytes), `admin_templates/*` (copia divergida), `rem_conditions`
(tabla con 0 filas: un intento previo de sacar los límites a datos, abandonado),
`rem_correlatives.qr_code` (columna que nadie escribe ni lee), los seis filtros
de los reportes que ninguna pantalla expone, y los `.xls` que son HTML con otra
extensión.
