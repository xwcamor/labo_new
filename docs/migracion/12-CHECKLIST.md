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

### A3. `[x]` Nueve familias sin plantilla de análisis

**Problema.** Partículas, sedimentos, metales, viscosidad, DBDS, inflamación,
fluidez, inhibidor y pasivador salen con el párrafo **en blanco**. Hay 6
plantillas de 15.

**Solución.** Las quince familias tienen plantilla en `diagnosis_templates.json`,
con los textos **copiados tal cual** de los ERB del sistema anterior —dobles
espacios, tildes faltantes y frases a medias incluidas: son los párrafos que el
laboratorio viene firmando, y corregirlos es decisión suya, ahora sin deploy.
Cada plantilla anota en `_origen` el archivo y las líneas de las que salió.

**Dos siguen sin poder dispararse, y no es por el texto**: partículas y metales
**no declaran ningún parámetro medible** (`analyte_map.json → pendientes`), así
que no producen resultados y no hay sobre qué opinar. Las plantillas quedan
escritas y salen solas el día que el laboratorio declare esas columnas. Mientras
tanto el motor **no dice nada** para ellas: "no se detectó presencia de metales"
sobre cero mediciones es la misma afirmación falsa que leer un cuadro de límites
ausente como "cumple".

**Cómo lo verifico.**
```
php artisan test --filter=DiagnosisTextTest      # 13 pruebas en verde
```
En pantalla: una muestra con esas pruebas validadas y el autodiagnóstico
generado. Trece de las quince filas con texto; partículas y metales en blanco
hasta que se declaren sus parámetros.

### A4. `[x]` Bandas graduadas en el autodiagnóstico

**Problema.** El motor solo distingue "ninguno / uno / varios" fuera de norma.
El viejo tenía bandas por valor: furanos (DP 700 / 450 / 250), grado de
polimerización (1000 / 650 / 350), pasivador (50 / 70), inhibidor (0.08 / 3).
Hoy furanos dice lo mismo con DP 800 que con DP 200.

**Solución.** `bands[]` en la plantilla: rango + texto propio, [min, max) con
`max_inclusive` / `min_exclusive` para los bordes que el viejo escribía al revés
(el escalón del pasivador era `>= 50 && <= 70`). **No se resolvió con
`spec_limits`** y no podía resolverse así: siete de esas familias no tienen
cuadro de límites —ni lo tenían allá—, el corte no es un criterio de aceptación
sino una escala de interpretación, y `spec_status` solo distingue tres estados
donde el papel necesita cuatro textos.

Dos bandas que se pisan **no** se resuelven por orden de aparición: el párrafo
sale vacío. Un error de datos escondido dentro de una frase firmada es peor que
un hueco visible.

Para los metales, que se nombran por presencia y no por norma, la plantilla
declara `threshold` (el `> 0.05` que el viejo tenía en la vista de carga).

**Cómo lo verifico.** `test_todas_las_bandas_del_archivo_real_dan_un_texto_propio`
barre TODAS las familias con bandas del archivo real, una sonda por banda, y
falla si dos devuelven el mismo párrafo o si alguna queda muda por solaparse.
Los cuatro cortes de furanos y del grado de polimerización tienen su prueba
propia, y el borde de los 70 ppm del pasivador también.

### A5. `[x]` El texto tiene que poder citar el valor medido

**Problema.** Los marcadores son `{ok} {failed} {norm} {count}`. No hay
`{value}`. Se pierden "se detectó **7.3 ppm** de dibencil disulfuro", "punto de
inflamación a **X °C**", el código ISO 4406 desglosado y el resultado del
azufre por método.

**Solución.** `{value}` (valor + unidad), `{value_num}` (pelado), `{unit}`, los
tres con `:codigo` para pedir un parámetro concreto de la familia y con `[n]`
para tomar el n-ésimo tramo de un código compuesto (el ISO 4406 es "18/16/13").
Y `{failed_values}`, que lista los señalados **con** su valor: es lo que el
párrafo de metales necesitaba, y de paso se va la coma colgando que el viejo
imprimía antes de "como compuestos metálicos".

La unidad sale del parámetro, no escrita dentro de la frase. **Efecto**: el DBDS
ahora dice `7.30 mg/kg` donde el papel viejo decía `7.3 ppm` — misma magnitud, y
coincide con la unidad que la tabla del informe ya imprime. El signo de un valor
censurado se conserva: ">300 °C" no se publica como "300 °C".

**Cómo lo verifico.** `test_el_marcador_value_imprime_el_numero_medido_con_su_unidad`
sobre el párrafo real del DBDS, más los decimales, el signo, el código partido y
el pedido por código de parámetro.

### A6. `[x]` Enlazar la fila de bancada con la prueba pedida

**Problema.** El servicio sabe resolver muestra, prueba y equipo a partir de
`sample_test_id`, pero **el controlador no acepta ese campo** y la grilla manda
el código tipeado. Toda fila que un analista cargue a mano queda sin enlace.
Medido: en la base de demostración las 120 filas **sí** lo tienen, porque el
sembrador llama al servicio directo — el problema es de la pantalla, y por eso
no se ve en la demostración.

Sin ese enlace: el avance de la muestra nunca se escribe, el equipo cae al
tipeado a mano, y **el bloque de condiciones del informe sale vacío en todas
las páginas**.

**Solución.** `saveRow` acepta `sample_test_id`, la ficha manda las pruebas que
esta hoja espera, y la columna del Nº de muestra pasó de campo de texto a
**selector**: se elige "2026-0031 · Abengoa Peru SA · T-01" en vez de tipear el
correlativo. La fila queda atada por clave foránea y hereda muestra, código y
equipo.

Las pruebas que YA están en una fila de la hoja entran siempre en la lista, sin
mirar su estado: si no, el selector no encuentra su propia opción y Ant Design
cae a mostrar el número crudo del identificador — la hoja cargada se leía "124"
donde dice "2026-0018".

La celda del correlativo se llena igual al elegir: el enlace de verdad es la
clave foránea, pero esa columna existe en la plantilla y se imprime en la hoja
de bancada firmada.

**Cómo lo verifico.**
```
php artisan tinker --execute="echo App\Models\WorksheetRow::where('kind','sample')->whereNull('sample_test_id')->count();"
```
Tiene que dar 0 **después de cargar una fila desde el navegador**, no solo
después del sembrador. Y el PDF de esa muestra tiene que traer fecha de
análisis, temperatura y humedad.

```
php artisan test --filter=WorksheetRoutesTest   # 8 pruebas, 2 nuevas
```
`test_la_fila_se_ata_a_la_prueba_pedida_y_hereda_muestra_y_equipo` y
`test_la_ficha_ofrece_las_pruebas_que_esta_hoja_espera`.

### A7. `[x]` El descargo legal en todas las páginas

**Problema.** El viejo lo tenía en el pie de todas; el nuevo, solo en la
última. En un formato donde cada hoja se lee sola —y se fotocopia suelta— es un
retroceso.

**Solución.** Bloque `position: fixed` en el pie, junto al código de muestra y
al de verificación que ya se repetían. Deja de imprimirse en el bloque de la
última hoja, donde estaba duplicado.

**Cómo lo verifico.** `php artisan report:compare` y buscar el texto en el PDF:
tiene que aparecer tantas veces como páginas tenga.

### A8. `[x]` Los azufres en una sola hoja

**Problema.** El viejo imprimía los tres sub-ensayos (1275B, 62535 48 h, 62535
72 h) en la misma hoja. Hoy salen en tres.

**Solución.** `config('lab.report_families_by_test')` — una excepción POR
PRUEBA, no por grupo: los tres azufres viven en "Otros" junto a PCB, furanos y
metales, y declararlo por grupo mandaría los quince ensayos a la misma página.
Migración de relleno que respeta lo que el laboratorio haya reagrupado a mano.

**Cómo lo verifico.**
```
php artisan tinker --execute="foreach (App\Models\TestDefinition::where('report_comment_group','azufre_corrosivo')->pluck('code') as \$c) echo \$c, PHP_EOL;"
```
Tres códigos. Y una muestra con los tres azufres tiene que dar **una** sección.

### A9. `[x]` Los cinco furanos no se imprimen

**Problema.** Detectado al sembrar los límites de detección: en la prueba de
furanos solo `grado_de_polimerizacion` tiene `report_visible`. Los cinco
compuestos (2-FAL, 5-HMF, 2-ACF, 5-MEF, 2-FOL) tienen `role = none` y **no
salen en el informe**. El viejo los imprimía los cinco, más el DP.

**Solución.** Se declararon los cinco parámetros (`fal`, `hme`, `ace`, `mfu`,
`fua`, en ppb) y se enlazaron sus columnas en `analyte_map.json`. **No fue una
adivinanza**: son los mismos códigos que TrafoDex ya usa en su tabla `furanos`,
así que hay dos fuentes independientes. Con eso las cinco columnas quedan
`role = result` + `report_visible`, y sus límites de detección (`< 10` ppb) ya
estaban sembrados esperándolas.

Furanos sale de la lista de pendientes de `analyte_map.json`. Quedan cuatro:
tensión interfacial, partículas, metales y condición visual.

**Cómo lo verifico.**
```
php artisan db:seed --class=LabAnalyteMapSeeder   # "columnas enlazadas: 41"
```
El PDF de una muestra con furanos tiene que traer seis filas, no una.

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

### C2. `[x]` `worksheets.sample_temp_c` no tiene dónde cargarse

La columna existe, es asignable y **el informe la imprime** — pero no está en
el formulario ni en la validación del controlador. Son dos líneas.
**Verificación:** cargarla desde la pantalla y verla en el PDF.
**Resuelto (2026-07-29):** el campo está en el formulario de la hoja (alta y
edición de cabecera) y en la validación de `store()`/`update()`. De paso la
bancada quedó en el estándar de los módulos: Editar/Eliminar/Bloquear en el
encabezado, edición de cabecera (la prueba no se cambia), página de baja con
motivo y candado manual además del automático por antigüedad.

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

### C9. `[x]` Completitud de la recepción a la vista (los 4 iconos del viejo)

Investigado en el viejo (2026-07-29): el index de `rems` mostraba 4 iconos por
fila — Series (`series_done`: correlativos con transformador), Trabajos
(`jobs_done`: pruebas asignadas), Datos (`datas_done`: valores cargados),
Informes (`reports_done`: emitidos vs pactados). Eran **banderas cacheadas en
`rems` que se recalculaban con `Rem.update` DENTRO del ERB de la ficha**: si
nadie abría la remisión, el listado mentía. El "bloqueo" del completado era
**manual** (columna `state`, que la misma pantalla rotulaba "Bloqueado" y
"Completado" según el lugar); el único evento nocturno de MySQL
(`update_is_urgent`, README_EVENTS.md) solo apagaba la urgencia — no bloqueaba
nada.
**Qué cambia:** el index de recepciones deriva los 4 chequeos (Equipo ·
Pruebas · Datos · Informes) en la MISMA consulta del listado (withCount), sin
caché; y el cierre dejó de ser un botón: al emitir el informe las pruebas
publicadas pasan a "informado" (`SampleProgressService::markReported` — era el
eslabón que faltaba en la cadena de estados), la muestra recalcula su estado y
la recepción se CIERRA SOLA cuando la última queda informada (y REABRE si un
ensayo vuelve a la cola).
**Verificación:** `SampleReportTest` (emitir informa las pruebas y cierra la
recepción · una prueba no publicada no se marca informada · la recepción
reabre) + captura del index con los chips.

### C10. `[x]` Las dos plantillas de exportación del informe

El botón de exportar ofrece **PDF clásico** (la maqueta del sistema anterior:
una hoja por prueba, sello ANAB, relaciones de gases — extraída del comando
`report:compare` al servicio `LegacyReportRenderer`, ruta
`sample_reports.pdf_legacy`) y **PDF moderno** (la plantilla nueva). Mismos
datos: si el informe está emitido, ambos imprimen su snapshot congelado. El
clásico no lleva QR ni código de verificación (la maqueta vieja no los tenía);
cada descarga queda auditada con la plantilla usada. Además el borrador ahora
tiene su botón **Eliminar** (con motivo) en la pestaña Informes; el emitido
sigue sin poder borrarse.
**Verificación:** captura de la fila de Informes con los dos botones + PDF
clásico generado por el servicio (195 KB, `%PDF-1.7`).

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
