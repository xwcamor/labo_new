# Auditoría: qué hacía el sistema viejo y qué falta en el nuevo

> **Para qué existe este documento.** Se venía trabajando por pedidos sueltos y
> sin una lista de qué falta. Acá está el barrido completo del sistema Ruby
> (`labo_old`) contra LaboRep, hecho con evidencia (archivo:línea) y no de
> memoria. Cada hallazgo dice si el nuevo lo cubre, lo cubre a medias o falta.
>
> **Cómo se hizo.** Seis auditorías en paralelo, una por dominio: informe PDF ·
> columnas y cálculos · autodiagnósticos y settings · integración con TrafoDex ·
> inventario de módulos · bancada, control de calidad y temperaturas.
>
> **Regla de lectura.** "FALTA" significa que el papel viejo hacía algo que el
> nuevo hoy no hace. No todo lo que falta hay que construirlo: hay cosas del
> viejo que estaban mal y se descartaron a propósito, y están marcadas como
> tales. Lo que NO se hace acá es dar por bueno algo sin haberlo mirado.

---

## Lo urgente, en una pantalla

Ocho cosas cambian lo que sale impreso en un papel que el laboratorio firma, o
dejan una decisión del laboratorio en manos de un programador. En orden:

| # | Qué | Por qué primero |
|---|---|---|
| 1 | **Límites de detección** (`< 1` ppm de H₂, `< 10` ppb de furanos, `< 0.01` de acidez) | El informe nuevo publica números que el papel acreditado nunca publicó. Es contenido, no maquetado |
| 2 | **9 familias sin plantilla de análisis** | Partículas, sedimentos, metales, viscosidad, DBDS, inflamación, fluidez, inhibidor y pasivador salen con el párrafo **en blanco** |
| 3 | **Renombrar el código de una columna rompe las fórmulas en silencio** | El campo calculado se guarda vacío, sin error. Un certificado con la celda del resultado en blanco |
| 4 | **Los 25 cuadros de límites están sin validar por el laboratorio** | De `spec_status` depende el color de la celda, el veredicto y todo el texto generado |
| 5 | **Las plantillas de texto viven en un JSON del repositorio** | Cambiar una coma sigue exigiendo un deploy — que es exactamente lo que se venía a resolver |
| 6 | **Los azufres salen en 3 hojas donde el viejo sacaba 1** | Se arregla con una fila de datos |
| 7 | **El descargo legal sale solo en la última página** | El viejo lo tenía en el pie de todas. En un formato donde cada hoja se lee sola, es un retroceso |
| 8 | **Verificar `is_accredited` de D3612 y D1275** | Si el dump no trae la marca, cromatografía y azufres **pierden el sello ANAB** que el papel viejo sí llevaba |

---

## 1. El informe PDF

### 1.1 Cuántas pruebas por hoja

El viejo no imprimía una prueba por página: imprimía **una familia por página**,
en 16 secciones (`labo_old/app/views/im_management/rem_reports/show.erb:13-131`).
Dos familias además de la fisicoquímica comparten hoja:

- **Fisicoquímicos** — 13 parámetros en una tabla, cada fila con su norma. **Ya
  resuelto** (commit `b3a6481`).
- **Cromatografía** — 9 gases **más un bloque RELACIONES** (TGC, TGC-CO, %GAS,
  CH₄/H₂, C₂H₂/C₂H₄, CO₂/CO, O₂/N₂), `_report_cromas.erb:318-458`.
- **Azufres** — **3 sub-ensayos en la misma hoja** (1275B, 62535 48 h, 62535
  72 h), `_report_azufres.erb:11,38,65`.

| Hallazgo | Estado | Qué hacer |
|---|---|---|
| Familia fisicoquímica en una tabla | **OK** | — |
| Azufres: 3 ensayos, 1 hoja | **FALTA** — hoy salen 3 hojas | Declarar la familia. **Solo datos**, sin código |
| Bloque RELACIONES de cromatografía | **FALTA** | Campos `computed` con `report_visible`, o sección propia |
| Orden de las 16 secciones | **PARCIAL** — ahora sale de `sort_order` | Verificar que los `sort_order` sembrados reproduzcan el orden del papel |
| Columna ITEM no correlativa (el viejo imprimía 1, 2, 4, 5… con hueco) | **OK** (mejor: renumera por hoja) | Confirmar con el laboratorio que el cambio les sirve |

### 1.2 El sello ANAB

**En el viejo estaba mandrakeado a nivel de página.** Hay dos parciales gemelos
—`_report_logo_main.erb` con el sello, `_report_logo_parcial.erb` sin él— y quién
lo lleva lo decidía **qué parcial escribió el programador en la línea 2 de cada
sección**. Con sello: fisicoquímicos, cromatografía, azufres. Sin sello: las
otras 13. Ninguna condición, ninguna consulta. Cambiar el alcance acreditado
exigía editar HTML.

El párrafo del certificado con el número `AT-2596` estaba **copiado literal tres
veces** en el ERB.

**En el nuevo está resuelto y mejor**: el sello es dato del workspace
(`tenants.accreditation_logo`) y se estampa por página según si alguna fila de
esa hoja se corrió con un método dentro del alcance. El hecho (`is_accredited`,
booleano) está separado del rótulo (`accreditation_flag`) — mezclarlos ya había
hecho que `"NA"` contara como acreditado.

> **VERIFICADO CONTRA LA BASE — y es una contradicción del sistema viejo, no
> nuestra.** De los 37 métodos sembrados, **13 están marcados como acreditados**,
> y **ninguno de ellos es de cromatografía ni de azufres**:
>
> | Prueba | Método | Flag |
> |---|---|---|
> | Análisis cromatográfico | ASTM 3612 - Método C | **NA** |
> | Azufre 1275B | ASTM 1275 | **NA** |
> | Azufre 62535 (48 y 72 h) | IEC 62535 | **NA** |
>
> Acreditados: D974 (número ácido), D924 (factor de potencia 25/90/100), D1816
> (rigidez), D971 (tensión interfacial), D1533 (agua), D1500 (color), D1524
> (condición visual), D7777 (densidad).
>
> O sea: **el papel viejo estampaba el sello ANAB y el párrafo del certificado en
> las hojas de cromatografía y azufres, mientras sus propias filas decían (NA)**.
> Las dos afirmaciones no pueden ser ciertas a la vez. Con la regla nueva —el
> sello sale si algún método de la hoja está dentro del alcance— esas dos hojas
> **dejan de llevarlo**.
>
> **Esto no lo decide un programador.** Si el alcance acreditado incluye D3612 y
> D1275, se marcan como acreditadas y el sello vuelve solo. Si no lo incluye, el
> papel viejo venía estampando un sello que no correspondía. Hay que mirar el
> certificado AT-2596 y decidir.

### 1.3 Números convertidos a texto — los límites de detección

Esto es lo más grave de toda la auditoría. El informe viejo **no imprimía el
número medido cuando estaba por debajo del límite de detección**: imprimía el
límite con un `<`. Los cortes estaban clavados en el HTML, repetidos hasta tres
veces por gas.

**Cromatografía** (`_report_cromas.erb`):

| Gas | Corte |
|---|---|
| H₂ | `< 1` |
| O₂ | `< 105.4` |
| N₂ | `< 396.2` |
| CH₄, CO, C₂H₄, C₂H₆ | `< 0.3` |
| CO₂ | `< 4.0` |
| C₂H₂ | `< 0.4` |

**Furanos** (`_report_furanos.erb:35-103`): `< 10` ppb en los cinco compuestos.

**Número Ácido** (`_report_physicals.erb:45-54`) — tres tramos:
`< 0.005` → imprime **"< 0.01"** · `0.005–0.010` → imprime **"0.01"** (un número
forzado, no el medido) · `≥ 0.010` → dos decimales.

**Estado en el nuevo: FALTA el mecanismo.** El `<` existe (`qualifier` `lt`/`gt`
en `TestReportPayload::valor()`) pero **solo si el analista lo tipea**. No hay
columna de límite de detección: `test_fields` tiene `min_value`, `max_value`,
`decimals`, y **nada de LOD/LOQ**. Hoy un H₂ de 0.4 ppm se imprime "0.4" donde el
papel acreditado decía "< 1".

**Qué hacer:** agregar `detection_limit` a `test_fields` y aplicarlo en
`TestReportPayload::valor()`. Y preguntar al laboratorio si el tramo intermedio
del número ácido (el "0.01" forzado) se conserva o era un parche.

> **Sobre el "un 4 impreso como <5"**: no existe. No hay ninguna regla `< 5` en
> el informe viejo. Lo más parecido es el límite referencial de 5 ppm del DBDS,
> que está como texto de una celda, y los "<50" de pasivador y PCB. El repo
> viejo tiene un solo commit, así que no hay historia donde buscar una versión
> anterior. Los cortes que **sí** existen son los tres bloques de arriba.

Lo que sí se corrigió: el viejo truncaba con `.to_i` la rigidez (44.9 kV
imprimía **44**) y los furanos. En el nuevo el redondeo es por campo
(`test_fields.decimals`).

### 1.4 Plantillas y textos

En el viejo estaban en la base: la norma de referencia, la norma del método con
su flag (solo fisicoquímicos), los valores de orientación (aunque **escritos por
código clavado**), los comentarios y el firmante.

Todo lo demás estaba en el ERB: ~40 títulos y cabeceras, los nombres de los
analitos y sus unidades, las normas por fila (`ASTM D5837` ×5, `ASTM D7151` ×8,
`ASTM D6786` ×8…), los límites impresos como texto de celda (DBDS "5", inhibidor
"0.08 - 3.00 %"), las notas al pie, la razón social ×5, la dirección, y el
descargo legal — que además tiene **una frase duplicada literal**, un copy/paste
que salió impreso en todos los informes emitidos.

**Estado en el nuevo: mayormente resuelto**, con tres huecos:

| Hueco | Detalle |
|---|---|
| Descargo legal | El viejo lo ponía en el pie de **todas** las páginas; el nuevo, solo en la última. Hay que moverlo al pie común |
| Bloque RELACIONES | Sin equivalente (ver 1.1) |
| Nota de Chendong en furanos | Falta la línea `reports.test_footnote.{furanos}` |

### 1.5 Temperaturas y condiciones de ensayo

El viejo tenía cuatro temperaturas de campo en `rem_reports` (aceite del
transformador, aceite en campo, ambiente, humedad relativa) impresas con
`if valor.to_i == 0 → "-"`. **Es un colador**: nulo, cero real y cualquier valor
entre −1 y 1 (0.4 °C) se imprimían todos igual.

Y condiciones de laboratorio **solo en dos familias**: fisicoquímicos
(`fiq_lab_pre/tem/hum`) y cromatografía (`cro_lab_pre/tem/hum`). Trampa de
nomenclatura: `*_pre` es **temperatura de la muestra** en fiquis y **presión
atmosférica** en cromas. Las otras 13 familias solo imprimían la fecha.

**En el nuevo es mejor**: las cuatro de campo son decimales anulables (el colador
desapareció) y las condiciones de laboratorio pasaron a ser **por hoja de
bancada**, así que las 15 familias las declaran, no dos.

| Falta | Detalle |
|---|---|
| Presión atmosférica | No hay columna en `worksheets`. Para cromatografía es dato de ensayo, no adorno |
| Catálogo de condiciones por día | El viejo tenía `fiq_temperatures`/`cro_temperatures` y pre-llenaba el formulario con la fila del día. Es comodidad, pero es lo que hacía que el dato se cargara |

---

## 2. Columnas de las pruebas y cálculos

### 2.1 Cómo eran en el viejo

Las columnas se definían en `lab_category_sub_details`, con atributos que ya
existen en el nuevo (`test_fields`). Dos cosas de ese diseño importan:

**Los roles no existían.** El viejo deducía tres por **posición** y no los
declaraba en ningún lado:

- Nº de muestra = **columna 1** (jQuery copia `#col1`)
- Norma = **`num_pos == 2`**
- Resultado = **la última columna** — y esa consulta ni siquiera filtra
  `deleted = 0`, así que una columna dada de baja podía ganar

De ahí el aviso en mayúsculas del procedimiento operativo del propio
laboratorio: *"OJO: LA COLUMNA RESULTADO SIEMPRE ES LA ULTIMA"*.

**El checkbox "Mostrar en Reporte" no hacía nada.** `report_use` existía, se
guardaba, se editaba… y no se consumía en ninguna parte.

Y hay un bug real en el editor: la pantalla de alta guardaba las banderas
`is_blur`/`is_blocked`/`is_reuse` como 0/1 y la de edición como 1/2, mientras el
render compara `== 1`. Editar una columna le daba vuelta el significado a las
tres.

### 2.2 Los cálculos: JavaScript guardado en la base

Uno por PRUEBA, en `lab_category_details.blur_calculation`, inyectado sin
escapar dentro de una función `calculate()` y ejecutado **solo en el navegador**,
al cargar la página y al salir de cada campo marcado. No había evaluación en el
servidor: el campo resultado era `readonly` (que un POST directo saltea) y si un
operando llegaba vacío quedaba el texto literal **`NaN` guardado en la base**.

Ocho pruebas de 29 tenían fórmula. La del grado de polimerización del papel es de
~90 líneas y usa **concatenación de strings como estructura de datos**
(`col31 + "/" + col32`), que después se vuelve a partir al mostrar.

La rigidez dieléctrica **no tenía fórmula**: el promedio de las 5-6 mediciones no
estaba automatizado.

### 2.3 Referencias entre columnas: por POSICIÓN

Ésta es la respuesta a *"¿qué pasa si cambio el nombre de una columna?"*, y tiene
dos mitades.

**En el viejo: renombrar era seguro; reordenar era fatal y silencioso.** El id
del DOM se generaba con el índice ordinal: `col7` no era `num_pos = 7`, era *"la
séptima columna viva de esta prueba"*. Insertar una columna en el medio corría
todo un lugar y `(col8-col6)*col5/col7` pasaba a operar sobre otras cuatro
celdas — sin error, sin aviso, con un número plausible en el certificado. El
sistema lo sabía y lo delegaba al humano con una alerta roja: *"Si la columna
contiene cálculos por favor cambiar la fórmula de los campos."*

**En el nuevo, el flujo es:**

1. Las fórmulas viven en `test_fields.formula`, **una por campo**, y referencian
   a las otras columnas **por código** (`peso_aceite_g`), no por posición.
2. Se validan **antes de guardarse**: sintaxis, paréntesis, función inexistente y
   campo inexistente en esa prueba.
3. Se evalúan **en el servidor**, con parser propio (shunting-yard, sin `eval`),
   lista cerrada de funciones y orden topológico — detecta ciclos.
4. Corren en dos momentos con el **mismo motor**: al guardar la fila, y mientras
   el analista escribe (un endpoint de vista previa que no escribe nada). El
   navegador **no calcula**.
5. Nunca producen `NaN`: falta de dato, división por cero o `log10(0)` dan
   `null`.
6. **Reordenar es seguro** (hay test). **Borrar una columna referenciada está
   bloqueado**, con el listado de fórmulas que la usan.

**Y acá está el agujero: renombrar el `code` no está protegido.** La validación
al editar revisa solo la fórmula del campo que se está tocando, no las de los
demás. Renombrar `peso_aceite_g` → `peso_muestra` deja la fórmula del número
ácido apuntando a un código inexistente; el resultado se guarda **vacío, sin
error y sin aviso**. Es el mismo tipo de falla que el viejo tenía al reordenar,
movida de lugar.

**Qué hacer:** replicar el chequeo de borrado en la edición cuando cambia el
`code` — o reescribir las fórmulas dependientes en la misma transacción. Y
revalidar `instrument_formats.column_map`, que también referencia por código.

Segundo detalle: editar una fórmula **no recalcula las hojas ya cargadas**. No es
una regresión (el viejo tampoco), pero conviene saberlo.

### 2.4 Valores constantes

El viejo tenía tres mecanismos:

**(a) Constantes por columna**, con pantalla propia. Las reales de producción:

| Prueba | Constante | Valor |
|---|---|---|
| Número Ácido | Factor KOH | **0.514** |
| Número Ácido | Volumen del blanco | **0.181** |
| Tensión Interfacial | Densidad del agua | 0.998 |
| Tensión Interfacial | Temperatura del agua | 20.1 |
| Factor de potencia 25 / 90 / 100 º | Temperatura y humedad ambiente | 20.2/60 · 21.5/62 · 20.2/66 |
| Rigidez / Rigidez electrodos planos | Temperatura y humedad ambiente | 20.2/65 · 21.3/41 |
| Resistividad 25 / 100 º | Temperatura | 25 · 100 |

> **Trampa.** El `seeds.rb` del repo viejo trae **Factor KOH 0.5531** y **volumen
> del blanco 0.512** — otros números. Son los de siembra, no los de producción.
> Sembrar desde ahí arranca con el factor equivocado.

**(b) Límites de carta de control** por prueba (LAS/LAI/LCS/LCI/LC).

**(c) Constantes clavadas dentro del JavaScript**, que no eran dato y no se
podían editar sin tocar la fórmula: `1.51` y `0.0035` (Chendong), `1000`
(ppb→ppm), `45` y `0.0075` (grado de polimerización del papel).

**Estado en el nuevo:** (a) y (b) están, con paridad y mejor ámbito — las
constantes ahora arrastran el valor de la fila anterior de la tanda. (c) está a
medias: las de Chendong quedaron **dentro del texto de la fórmula** (editables
sin tocar código, pero sin nombre ni pantalla), y las del grado de polimerización
no existen porque esa prueba no se portó — está declarado, y requiere que el
laboratorio defina si las cuatro pesadas son réplicas de una determinación o dos
de dos.

**La separación de electrodos no es un dato en ninguno de los dos.** El viejo lo
resolvía teniendo dos pruebas distintas (D1816 y "electrodos planos" = D877) más
una columna "Espinterómetro". Los kV de D877 (2.54 mm) y D1816 (1 o 2 mm) no son
comparables, y hoy nada registra el gap. `test_methods.conditions` es el lugar
donde iría.

---

## 3. Autodiagnósticos, plantillas y settings

### 3.1 Cómo funcionaba el "autodiagnóstico" viejo

**El botón "Diagnóstico Automático" estaba `disabled` y su método era un método
vacío.** El autodiagnóstico real era otra cosa: al renderizar la pantalla de
carga, si la columna del comentario estaba vacía, un parcial ERB armaba el texto
y lo dejaba escrito en el `<textarea>`. El analista lo corregía y se guardaba.

Los textos vivían en **15 archivos ERB, 1134 líneas**, con condicionales anidados
por aceite × tipo de equipo × cuántos parámetros fallaron, y la frase escrita
adentro de cada rama. Cada norma citada estaba escrita a mano en cada rama.

Cosas que se ven en ese código y explican por qué había que rehacerlo:

- La lista de "los que están bien" se armaba concatenando `if`s → **queda una
  coma colgando** cuando el último no aplica, y así salió impreso.
- Una rama de aceite cita **"IEC 610203-2025"**, un número de norma que no
  existe.
- Hay un comentario del propio autor: *"ERA EL 7 PERO LE PUSE 17 PARA IMITIR NO
  SE QUE PUEDA MALOGRAR"*.
- Los límites que disparan el texto se calculan en **dos copias** del árbol
  `if/elsif` (una al crear, otra al actualizar) que **ya divergieron**: un
  informe de aceite de girasol **nace sin norma**, y los instrumentos, bushings y
  cables nacen con todos los límites en `"-"` y los reciben recién al re-guardar.
- El cuadro de horno mineral está marcado en el propio código con `#####REVISAR`.

**En el nuevo está mejor concebido**: hay un servicio de verdad
(`DiagnosisTextService`), el texto se guarda en `sample_diagnoses` con
`is_edited`, la lista se arma con los parámetros realmente medidos y con
conjunción antes del último (adiós coma colgando), la norma sale del
`spec_source` **congelado al validar** en vez de estar escrita en la frase, y si
ninguna plantilla casa **no inventa texto**.

### 3.2 Lo que falta del autodiagnóstico

| # | Falta | Detalle |
|---|---|---|
| 1 | **9 familias sin plantilla** | Partículas, sedimentos, metales, viscosidad, DBDS, inflamación, fluidez, inhibidor, pasivador. Hoy salen **en blanco**. Hay 6 de 15 |
| 2 | **Bandas graduadas** | El motor solo distingue "ninguno / uno / varios" fuera de norma. Furanos (DP 700/450/250) y grado de polimerización (1000/650/350) necesitan **N bandas con texto propio** |
| 3 | **Citar el valor medido** | Los marcadores son `{ok} {failed} {norm} {count}`. **No hay `{value}`**. Se pierden "se detectó **7.3 ppm** de DBDS", "punto de inflamación a **X °C**", el código ISO 4406 desglosado y el resultado del azufre por método |
| 4 | **Recomendaciones de acción** | "agregue un pasivador de cobre hasta al menos 100 ppm", "regenere o cambie el aceite", "mantenga un control regular". Van junto con las bandas |
| 5 | **Las plantillas no son editables** | Viven en `diagnosis_templates.json`, leído **del disco** por el servicio. No hay tabla, ni seeder, ni pantalla. Cambiar una frase sigue exigiendo un deploy |

Los umbrales del viejo, para escribir las 9 familias que faltan:

| Familia | Bandas |
|---|---|
| Furanos (DP) | ≥700 bueno · 450-700 medianamente envejecido · 250-450 envejecido · <250 severamente degradado |
| Grado de polimerización | ≥1000 nuevo · 650-1000 bueno · 350-650 medianamente envejecido · <350 envejecido (IEC 60450) |
| PCB | ≥2 ppm contaminado (IEC 60422-2024) + cita del D.S. N°018-2025-SA y el corte de 50 mg/kg |
| DBDS | <5 / ≥5 ppm (IEC 60422-2013) + recomendación de pasivador |
| Pasivador | <50 deficiente · 50-70 justo · >70 bueno, cada uno con su recomendación |
| Inhibidor | <0.08 % Tipo I no inhibido · 0.08-3 % Tipo II (ASTM D3487-16). Solo aceites 1, 2, 3, 8 |
| Sedimentos | <0.001 / ≥0.001 |
| Viscosidad | ≤12 / >12 |
| Metales | por presencia de los 8, pluralizando |
| Azufre corrosivo | sin umbral: interpola el resultado cualitativo por método |
| Partículas | sin umbral: parte el código ISO y explica ISO 4406 (4/6/14 μm) |
| Inflamación / Fluidez | sin umbral: texto fijo + el valor |

### 3.3 Los cuadros de límites

Los 25 cuadros del viejo se extrajeron programáticamente a `spec_limits_legacy.json`
y el modelo de datos nuevo es correcto: límite como **número** con operador,
banda de aviso, vigencia, y `test_methods.conditions` para el gap de electrodos.

Dos cosas abiertas, y las dos importan:

- **El propio JSON dice "SIN VALIDAR por el laboratorio"**, con siete anomalías
  documentadas del viejo. De `spec_status` depende el color de cada celda, el
  veredicto y **todo** el texto generado.
- **No hay pantalla de edición.** No existen rutas ni páginas de `spec_sets`: los
  cuadros solo se cargan por seeder. Es el mismo problema que el viejo tenía con
  el árbol de `if/elsif`, con mejor forma.

También queda sin resolver el **selector manual del valor de orientación** (la
lupa que abría un modal con radio buttons para elegir otro límite para esa
muestra). En el viejo esas tablas estaban hardcodeadas en 24 parciales ERB. Hay
que decidir si el override por muestra se mantiene; si sí, alimentarlo desde
`spec_limits`.

### 3.4 Settings

**El viejo no tenía tabla de configuración.** Lo que llamaba "Configuración" era
un hub de accesos a CRUDs de catálogos. El nuevo tiene `settings` con 28 claves
en 10 grupos, pantalla y un seeder que refresca metadatos sin pisar valores.

Lo único que falta: los **KPI de servicio** que el viejo tenía como constantes
Ruby — 5 días de plazo, 2 de emisión, 3 de entrega. Si se migra el reporte de
cumplimiento, esos tres números necesitan lugar.

### 3.5 Constantes del laboratorio

Todo lo que en el viejo estaba clavado —logo, sello, razón social, dirección,
descargo legal, el número de certificado `AT-2596` repetido en tres archivos— es
ahora dato del workspace. Y si están vacíos, el informe **no dibuja nada**: un
laboratorio sin acreditar no emite un papel que insinúe que sí.

Queda **cargar los textos reales** en el workspace (las columnas nacen vacías), y
decidir si se agrega la **vigencia de la acreditación**, que el viejo no tenía.

---

## 4. La integración con TrafoDex

### 4.1 Qué era realmente

No eran "un par de tablas compartidas". Era **una segunda conexión a la base de
TrafoDex con el mismo usuario y sin restricciones**, sobre **14 tablas**, con
escritura **y borrado** en 8 de ellas: clientes, países, tipos de aceite, marcas,
tipos de conmutador y los tres niveles de jerarquía. El laboratorio no tenía
clientes propios: leía y escribía los de la otra base.

Estaba escondido de dos maneras: cinco modelos con `establish_connection`
explícito, y **diez más** que heredan de una clase abstracta donde la única pista
es que la línea original quedó comentada. Y el nombre literal de la base está
escrito en 10 vistas, con `JOIN` que cruzan ambas en una sola consulta.

### 4.2 Tres defectos que explican bugs conocidos

- **El `.to_f` de los fisicoquímicos convierte "no medido" en 0.0.** Es la línea
  exacta que fabricó los 7476 ceros de rigidez que en TrafoDex hubo que anular
  después, porque el motor los comparaba contra el mínimo de la norma y bajaba el
  índice de salud de transformadores sanos.
- **Los gases van sin conversión**: se asigna texto a una columna decimal y el
  motor castea. Un `"<1"` entra como **0.00** sin aviso. (Y ahora sabemos de
  dónde salen esos `"<1"`: son los límites de detección de §1.3.)
- **La jerarquía de relleno se crea desde una vista, en un GET**: sede `"-"`,
  área `"-"`, y solo la subestación con dato real. Renderizar la pantalla de
  confirmación ya escribía en la base ajena antes de confirmar nada. Y la cascada
  avanza **un nivel por render**: había que recargar hasta tres veces, y si el
  operador apretaba antes, el error salía como *"Registro Duplicado."*

### 4.3 Qué viajaba y qué no

De las **66 columnas de resultado** del viejo, viajaban **21**:

| Familia | Se medía | Viajaba |
|---|---|---|
| Cromatografía | 11 | 9 (fuera: TDCG y total de gases — TrafoDex los recalcula) |
| Fisicoquímico | 13 | 7 (fuera: FP 90 °C, color, condición, densidad, resistividad ×2) |
| Furanos | 6 | 5 (fuera: grado de polimerización — **no hay columna destino**) |
| PCB, azufres, inhibidor, metales, partículas, sedimentos, y 6 más | 36 | **0** |

Además solo viajaban los informes **Principales**, y solo la **primera** fila de
detalle de cada informe.

### 4.4 El plan

**Un módulo, no tablas compartidas.** Sin segunda conexión: LaboRep tiene sus
catálogos propios — eso **ya está hecho**, y es lo que evita que el laboratorio
se quede sin clientes el día que TrafoDex reemplace al TRAPP viejo. Encima va un
envío por API con cola, reintentos y clave de idempotencia.

Estado real: **4 capacidades resueltas** (desacople, catálogos propios, la
transformación de la placa, los ceros corregidos), **1 a medias**
(`equipment.external_ref` implementado; la búsqueda y la bandeja de conciliación
no), **11 documentadas sin una línea de código**, **9 sin decidir**.

### 4.5 Tres decisiones pendientes del dueño

1. **Los 20 tipos de equipo contra los 3 de TrafoDex.** El viejo mapeaba con
   *"si es mayor a 3 → Potencia"*. Es pérdida de información pura.
2. **La columna de procedencia.** El viejo marcaba `db_system_id = 2` en cada
   cliente creado desde el laboratorio. Nunca se leyó, y **se perdió en la
   migración a TrafoDex**: ahí no existe. Sin ella no se puede responder "¿esto
   lo cargó alguien acá o entró por el laboratorio?".
3. **Qué jerarquía lleva un equipo creado por API**, ahora que nadie fabrica los
   `"-"`.

---

## 5. Inventario de módulos: qué existe en cada uno

> Nota de método: `db/schema.rb` del viejo está desactualizado — declara 18
> tablas de 47 reales. El inventario se hizo contra el volcado de estructura que
> este repo versiona en `docs/migracion/esquema/`, cruzado con los modelos.

### 5.1 Lo que falta entero

Cuatro bloques del sistema viejo no existen en el nuevo. Ninguno es un descuido
de esta sesión: tres están en el plan maestro como fases 10 y 11, y el cuarto es
la integración. Pero conviene tenerlos escritos en un solo lugar.

| Bloque | Qué era | Tamaño |
|---|---|---|
| **Almacén** | Insumos, préstamos, movimientos y devoluciones | 5 tablas, 2 pantallas + histórico |
| **Etiquetas con QR** | `stickers` + el `qr_code` del correlativo, para pegar en el frasco | 1 tabla, 1 CRUD, generación de QR |
| **Reportes gerenciales** | OTD · Análisis de Laboratorio · Registro de Muestras · Formato de Ingreso · Tareas · Reportes Entregados · Listado de Reportes | 7 pantallas con export |
| **Integración TrafoDex** | 4 asistentes de 4 pasos + 4 endpoints JSON | ver §4 |

Del OTD, además, el viejo tenía los umbrales como constantes Ruby (5 / 2 / 3
días). Hoy `labDashboard()` devuelve un arreglo vacío con un comentario que
promete la fase 11.

### 5.2 Los degradados silenciosos

Éstos son los que preocupan, porque no se ven: el sistema funciona igual y la
pérdida aparece meses después.

| Qué | En el viejo | Hoy |
|---|---|---|
| Marca de aceite | catálogo con CRUD | **texto libre** en el informe |
| Unidad de volumen de aceite | catálogo con CRUD | **texto libre** en el equipo |
| Punto de muestreo | catálogo, FK en el equipo **y** en el informe | **texto libre** (varchar 80) |
| Motivo del informe | catálogo | **texto libre** (varchar 80) |
| Bitácora diaria de condiciones del laboratorio | dos tablas con CRUD, consultables por fecha | por hoja de bancada, sin bitácora |
| Presión atmosférica del laboratorio | columna en la bitácora | **no existe** |
| Evidencia entregada por el cliente | columna + pantalla | **no existe** |
| Marcar tareas en lote de una remisión | pantalla propia | **no existe** |

Cuatro catálogos convertidos en texto libre es exactamente el camino por el que
la base vieja terminó con "2500 gal", "2500 galones" y "2500Gal" en la misma
columna. Hay que decidir cuáles vuelven a ser catálogo.

### 5.3 Dos cabos sueltos del código nuevo

- **El permiso `worksheets.validate` está sembrado y ninguna ruta lo usa.** Es
  coherente con la decisión de que la hoja se publica sola y el momento oficial
  es la emisión del informe — pero el permiso huérfano hay que borrarlo o
  cablearlo, no dejarlo.
- **No hay editor de normas y límites.** `standards`, `spec_sets` y
  `spec_limits` solo se cargan por seeder: no existen rutas ni páginas. Es el
  mismo hallazgo de §3.3, visto desde el inventario.

### 5.4 Lo que el viejo tenía muerto y no hay que migrar

Para que nadie lo busque después: `supervisors` (controlador sin ruta y sin
tabla), `tickets` (sin modelo, sin ruta), `report_management/reports` (el modelo
`Report` no existe — el CRUD entero explota), `sample_management/samples`
(archivo de 0 bytes), `admin_templates/*` ("Modo Supervisor", copia divergida de
`templates/*`), `labs_controller_old.rb`, `rem_conditions` (tabla con 0 filas y 0
referencias: un intento previo de sacar los límites a datos, abandonado), y dos
rutas declaradas sin controlador (`conditions_management/inhibidores`,
`stock_management/stock_units`).

### 5.5 Lo que el nuevo tiene y el viejo no

No todo es deuda. El nuevo suma: control de calidad con Westgard y repetibilidad
por duplicados, instrumentos con vencimiento de calibración (que ISO 17025 pide),
multi-sede (`laboratories`), analitos como entidad, flujo de aprobación de
informes por lote, portal público para el cliente con OTP, verificación del
informe por HMAC + QR, permisos por módulo y acción —el viejo tenía los cuatro
CRUD de plantillas detrás de **un solo permiso indistinto**—, y contraseñas
hasheadas: la tabla `users` del viejo guarda `real_password` **en claro**.

---

## 6. Bancada, control de calidad y temperaturas

### 6.1 La temperatura: respuesta a la pregunta directa

**En el sistema viejo la temperatura NUNCA corrige un resultado. Se registra y se
imprime.**

No es una inferencia: el volcado de producción tiene **las 29 fórmulas
completas** del sistema, y **ninguna referencia una columna de temperatura**. Las
pruebas donde uno esperaría una corrección —factor de potencia, rigidez, tensión
interfacial, viscosidad— tienen la fórmula **vacía**: el analista tipea el
resultado ya calculado.

La temperatura se maneja en tres capas, y conviene entenderlas porque explican
por qué no hay corrección:

1. **Se fija.** El ensayo se corre **a** 25, 90 o 100 °C. Por eso "Factor de
   potencia 25º" y "Factor de potencia 100º" son **pruebas distintas** y no un
   parámetro con corrección.
2. **Se registra**, en tres lugares: la grilla de la bancada (ambiente, humedad,
   muestra), un **registro diario del laboratorio** independiente de los ensayos,
   y a veces el propio archivo del instrumento (la columna trae
   `"Temperatura del ensayo:"` y se lee del TXT).
3. **Se imprime** en la cabecera de cada sección del informe.

**La única corrección aritmética real del laboratorio es la tensión interfacial,
y es por DENSIDAD, no por temperatura** (Harkins–Jordan, ASTM D971). Las
temperaturas de aceite y agua se registran porque son las condiciones a las que
se leyeron esas densidades, pero no entran en la fórmula. Y **ni siquiera está
implementada en el Ruby**: la fórmula vive en la planilla Excel de bancada
(`1.txt_examples/Plantilla Analista 1.xlsm`, hoja *Tensión*) y el analista trae
los dos números tipeados a mano. Las constantes de esa fórmula (circunferencia
del anillo y relación R/r) están clavadas en la celda y son **del tensiómetro
concreto**.

### 6.2 Lo que se perdió antes de esta migración

Dos chequeos que la planilla Excel hacía y el Ruby **ya no portó**:

- **Factor de potencia**: rango entre las dos determinaciones contra un límite de
  precisión `0.01 + 0.1 × máx`, con formato condicional.
- **Rigidez**: coeficiente de variación de las 5 rupturas
  (`desvío / promedio × 100`), como pide D1816.

El nuevo tiene lo necesario para recuperarlos (réplicas tipadas y fórmulas por
campo); falta declarar los campos y las expresiones.

### 6.3 El agujero operativo de la bancada

**La fila de la bancada no se puede enlazar con la prueba pedida desde la
pantalla.** El servicio lo soporta —`inheritFromSampleTest` resuelve la muestra,
la prueba y el equipo a partir de `sample_test_id`— pero el controlador **no
acepta ese campo** en la validación de `saveRow`, y la grilla manda
`sample_code`, o sea **texto tipeado**, igual que el viejo.

> **Medido, para no exagerar:** en la base de demostración las 120 filas de
> muestra **sí** tienen `sample_id` y `sample_test_id`, porque el sembrador llama
> al servicio directamente. El problema es de la **pantalla**: toda fila que un
> analista cargue a mano queda sin enlace.

Y ese enlace faltante deja inertes tres cosas ya construidas:

- El **avance de la muestra** nunca se escribe (`markInProgress()` sale de
  inmediato si no hay `sample_test_id`).
- El **equipo** cae al que se tipeó a mano en la fila.
- El **bloque de condiciones del informe** —fecha de análisis, temperatura y
  humedad del laboratorio— se busca por `worksheet_rows.sample_id`: sin enlace,
  sale vacío en todas las páginas.

Es un cambio chico: una regla de validación, un dato más en la pantalla y un
selector en la grilla.

### 6.4 Control de calidad

El motor nuevo está **muy por encima** del viejo. El viejo dibujaba cinco líneas
en un gráfico y ahí terminaba: no comparaba, no marcaba, no avisaba, no
registraba la violación. El analista tenía que mirar y darse cuenta. Los
duplicados eran **obligatorios para cargar y nunca se comparaban con su
original** — cero código los leía. Westgard no existía. Y cambiar el lote del
patrón **pisaba** los límites, así que las cartas históricas quedaban dibujadas
contra los límites de hoy.

El nuevo tiene Westgard de verdad (1-3s, 2-2s, R-4s, 4-1s, 10x), z congelado por
punto, vigencia y lote de los límites, repetibilidad por duplicados, y exclusión
de puntos con motivo.

Lo que falta es **la operación**:

| # | Falta | Detalle |
|---|---|---|
| 1 | **Los límites reales del viejo no están migrados** | Los 45 valores de producción hay que cargarlos carta por carta a mano. No hay importador |
| 2 | **Westgard no corre sin `sd`** | Las cartas del viejo tienen los cinco límites pero **no la desviación**. Cargándolas tal cual, las reglas quedan apagadas y solo funciona el semáforo por límites. Hay que derivar `sd` al importar |
| 3 | **Un patrón fuera de control no bloquea ni avisa** | Se pinta el punto y se guarda la regla violada, pero la hoja sigue publicando resultados y nadie se entera. Mismo agujero operativo del viejo, con mejor diagnóstico |
| 4 | Duplicados solo dentro de la misma hoja | Un duplicado corrido al día siguiente no se compara |
| 5 | Cartas de texto | `Condición Visual` tenía `'PASA'` en los cinco límites; las columnas son decimales |

### 6.5 Instrumentos y calibración

En el viejo **no había entidad instrumento**: era una opción de texto de una
columna de selección, con el código de calibración escrito dentro del nombre. El
mismo equipo se declaraba N veces, una por cada columna que lo usaba. No había
fecha de calibración, ni vencimiento, ni certificado, ni marca, ni serie. Y si
vencía, el sistema **no podía saberlo**: alguien ocultaba la opción a mano cuando
se acordaba.

El nuevo tiene entidad, calibración de primera clase, semáforo de cuatro estados
—con la decisión correcta de que **sin fecha es "desconocido", no "vigente"**—, y
qué equipo ofrece cada columna.

Lo que falta:

| # | Falta | Detalle |
|---|---|---|
| 1 | **La calibración vencida no bloquea nada** | Se avisa en el selector y se puede cargar y publicar igual. Es defendible como decisión, pero **hoy no está escrita en ningún lado**. Para ISO 17025 el mínimo es: bloquear, exigir motivo, o dejar constancia en el resultado |
| 2 | **El estado de calibración no se congela con el resultado** | Dentro de dos años, responder "¿este resultado se midió con el equipo calibrado?" exige cruzar contra el estado **actual** del instrumento, que ya cambió. Es el mismo error que la migración de las cartas ya corrigió para los límites |
| 3 | **No hay aviso de vencimiento** | El índice de la base se creó explícitamente "para el aviso del tablero", y ese aviso no existe |

### 6.6 Datos históricos de bancada

Hay `legacy_id` en `worksheets`, `worksheet_rows`, `worksheet_values` y
`qc_charts`, y **ningún comando que los llene**. El único importador es el de
definiciones. Si el laboratorio quiere ver sus corridas viejas, falta el ETL.

---

## Plan: en qué orden

No es una lista de deseos. Está ordenada por lo que rompe un papel firmado, lo
que deja muerta una función ya construida, y lo que es sólo comodidad.

### Bloque A — antes de emitir un informe a un cliente

1. **Límites de detección** — `detection_limit` en `test_fields` + aplicarlo al
   imprimir. Sin esto el informe publica números que el papel acreditado nunca
   publicó.
2. **Decidir el alcance acreditado** (D3612 y D1275). Es una respuesta del
   laboratorio, no código.
3. **Las 9 familias sin plantilla de análisis.** Hoy salen en blanco.
4. **Enlazar la fila de bancada con la prueba pedida.** Cambio chico, desbloquea
   tres cosas ya construidas.
5. **El descargo legal en todas las páginas**, no solo en la última.
6. **Los azufres en una sola hoja.** Una fila de datos.

### Bloque B — antes de que el laboratorio opere solo

7. **Renombrar el código de una columna no puede romper fórmulas en silencio.**
8. **Validar los 25 cuadros de límites** con el laboratorio, y **construir su
   pantalla de edición**.
9. **Importar los límites de control del viejo**, derivando la desviación.
10. **Que un patrón fuera de control avise**, y decidir si bloquea.
11. **Política de instrumento vencido**, escrita y aplicada; y congelar el estado
    de calibración en el resultado.
12. **Las plantillas de texto a una tabla editable**, con bandas y `{valor}`.

### Bloque C — paridad con el viejo

13. Bitácora diaria de condiciones del laboratorio, con presión atmosférica.
14. Los cuatro catálogos que se volvieron texto libre.
15. Bloque RELACIONES de cromatografía.
16. Repetibilidad del factor de potencia y CV de la rigidez (perdidos ya en el
    Ruby).
17. Etiquetas con QR.
18. Almacén.
19. Los 7 reportes gerenciales, con sus umbrales como settings.

### Bloque D — decisiones del dueño, sin código hasta que responda

20. Los 20 tipos de equipo contra los 3 de TrafoDex.
21. La columna de procedencia que se perdió.
22. Qué jerarquía lleva un equipo creado por API.
23. Si la corrección de tensión interfacial la calcula el sistema, y de dónde
    salen las constantes del tensiómetro.
24. Si el tramo intermedio del número ácido (el "0.01" forzado) se conserva.

### Fuera del plan, a limpiar

- El permiso `worksheets.validate` está sembrado y ninguna ruta lo usa.
- `Worksheet::STATUS_CLOSED` está declarado y nadie lo escribe.
- `worksheets.sample_temp_c` se imprime en el informe y **no hay campo donde
  cargarla**.

---

## Procedencia

Las seis auditorías se corrieron sobre `/home/user/labo_old` (Ruby, solo
lectura), `/workspace/labo_new` y `/home/user/trafodex`. Cada afirmación de este
documento sale de leer el código, no de la memoria de nadie. Donde el agente no
encontró algo, este documento lo dice en vez de suponerlo — el caso más claro es
el "4 impreso como <5", que **no existe** en el sistema viejo.
