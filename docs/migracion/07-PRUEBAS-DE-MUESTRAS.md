# Pruebas de Muestras — diseño del módulo

> Este documento responde una pregunta concreta que se hizo al empezar:
> **"en teoría cada módulo debe tener su tabla, si es así corrígeme"**.
>
> La respuesta corta es **no**, y conviene dejar escrito el porqué, porque la
> idea es razonable y va a volver a aparecer.

---

## 1. Por qué NO una tabla por prueba

El laboratorio tiene **29 pruebas** (Número Ácido, Rigidez Dieléctrica,
Cromatografía, Furanos, Grado de Polimerización, Factor de Potencia...). La
intuición dice: 29 tablas, cada una con las columnas de su ensayo. Cada tabla
queda limpia, tipada e indexada.

El problema es qué pasa con la prueba número 30.

Con una tabla por prueba, agregar un ensayo nuevo exige una migración, un
modelo, un controlador, un formulario y un despliegue. El laboratorio deja de
poder dar de alta una prueba y vuelve a depender del programador. Y no es
hipotético: agregar una COLUMNA a un ensayo existente —una medición más, una
norma nueva— tiene el mismo costo.

Es el mismo problema que la tabla de 221 columnas del sistema viejo, apenas
rotado 90 grados: en vez de una tabla ancha imposible de mantener, 29 tablas
angostas que igual hay que crear a mano una por una. En los dos casos **lo que
varía está en el esquema en vez de estar en las filas**, y por eso hace falta
un programador para cada cambio del negocio.

Hay un detalle que vale la pena reconocer: **el sistema Rails viejo ya había
resuelto bien esta parte**. Su cadena de plantillas

```
lab_category_detail_types → lab_category_details → lab_category_sub_details
     (categoría)                 (la prueba)              (la columna)
```

permite al laboratorio agregar una prueba o una columna sin tocar código, y eso
se conserva tal cual. Lo que estaba mal en el sistema viejo no era esto: era
todo lo que colgaba de esto. La tabla de 221 columnas, las normas clavadas en
el código, las fórmulas en JavaScript guardadas en la base.

---

## 2. Pero tampoco un EAV puro: son dos capas

El otro extremo también es una trampa. Si TODO vive en una tabla genérica de
`(fila, columna, valor)`, la flexibilidad se paga en la consulta: sacar "la
acidez de este equipo en los últimos cinco años" obliga a unir por texto,
filtrar por campo y convertir cadenas a números en cada consulta. Las tendencias
se arrastran y los informes se vuelven lentos.

Por eso hay **dos capas**, y cada una hace una sola cosa:

| Capa | Tabla | Qué es | Para qué sirve |
|---|---|---|---|
| Cruda | `worksheet_values` | El dato tal como lo cargó el analista, guiado por la plantilla | Flexible: soporta cualquier prueba nueva sin tocar el esquema. Es el registro de lo que se hizo en la bancada, y es lo que audita el laboratorio |
| Materializada | `results` (fase 5) | Una fila por parámetro medido, tipada e indexada | Es lo que consultan el informe, las tendencias y el histórico |

La capa cruda **nunca se pisa**. La materializada **se puede reconstruir** desde
la cruda en cualquier momento. Si mañana cambia una fórmula, se recalcula
`results` sin tocar lo que cargó el analista.

Esa separación es la que permite tener las dos cosas a la vez: que el
laboratorio agregue pruebas solo, y que el informe consulte rápido.

---

## 3. Lo que se conserva y lo que se cambia

### Se conserva

- La cadena de plantillas (grupo → prueba → columna → opciones).
- Los tres tipos de fila de la hoja de trabajo: **patrón control**, **muestra**,
  **duplicado**. Es lo que sostiene el control de calidad analítica.
- La regla de que no se cargan muestras hasta tener patrón y duplicado.
- Las cartas de control con sus cinco límites.
- La importación de archivos crudos de los instrumentos.

### Se cambia

| Del sistema viejo | Acá | Por qué |
|---|---|---|
| Fórmulas en JavaScript guardadas en la base e inyectadas con `html_safe` | Expresión evaluada en el servidor (`app/Services/Lab/`) | El servidor no podía recalcular ni validar. Un `POST` directo escribía cualquier cosa en el resultado, y cuando la fórmula operaba sobre un campo vacío quedaba el texto `NaN` guardado en la base — había un panel entero dedicado a cazar esos registros. Además, quien editaba la configuración ejecutaba código arbitrario en el navegador de todos los analistas |
| La columna 1 es el Nº de muestra, la 2 es la Norma, la última es el Resultado | `test_fields.role` declarado | Los tres supuestos eran posicionales y no estaban declarados en ningún lado. De ahí el aviso en mayúsculas del README viejo: "LA COLUMNA RESULTADO SIEMPRE ES LA ÚLTIMA". Reordenar el cuadro rompía el enlace con la muestra, la norma del informe y el gráfico de tendencias, en silencio |
| El vínculo hoja↔muestra por texto (`num_test.split('-')` interpolado en SQL) | Clave foránea, más el código textual que se tipeó | Si el analista pegaba el código sin disparar el evento del teclado, el campo quedaba vacío y el resultado nunca llegaba al informe, sin aviso |
| Todos los valores como texto en una sola columna, incluidos números e ids de opciones | Columnas tipadas (`value_num` / `value_text` / `option_id` / `instrument_id`) | Ordenar o promediar exigía convertir en cada consulta, y conviven en la base `1.5E-03`, `<0.5`, vacíos y `NaN` |
| `patron_tendences`: 5 límites × 9 gases = 45 columnas de texto, solo para cromatografía | Una fila por (prueba, parámetro) en `qc_charts` | Agregar la carta de otro parámetro exigía cinco columnas nuevas y tocar el formulario |
| Los límites de la carta se pisaban al cambiar el lote del patrón | Vigencia (`effective_from` / `effective_to`) | Las cartas históricas quedaban dibujadas contra límites que no eran los de su momento |
| Los cinco límites se dibujaban y nadie los evaluaba | Reglas de Westgard (`WestgardEvaluator`) | El analista tenía que darse cuenta mirando el gráfico |
| Los duplicados se exigían y no se comparaban nunca | `qc_duplicates` + `RepeatabilityEvaluator` | El analista hacía el trabajo doble y el dato se perdía |
| Cuatro tipos de columna en una tabla editable, con los ids 1/2/3/4 escritos a mano en diez vistas | `config/lab_field_types.php` | Agregar un quinto tipo desde la interfaz guardaba la fila y no hacía nada: ninguna vista sabía renderizarlo. Parecía dato editable y era código disfrazado |
| Instrumentos como opciones de texto ("Bureta PP-LA-01C") | Tabla `instruments` con calibración | No había forma de saber si el equipo estaba calibrado el día del ensayo, que es justamente lo que pide ISO 17025 |
| Mediciones repetidas como N columnas, o concatenadas con `/` en un campo de texto | `replicates` + `worksheet_values.replicate_no` | La rigidez dieléctrica se mide 5 o 6 veces y se promedia. El Grado de Polimerización guardaba varios valores en una cadena que la vista volvía a separar con un reemplazo de texto |
| El bloqueo de la hoja solo escondía botones | `status` + `locked_at` verificados en el servidor | No había ninguna comprobación en los controladores: un `POST` directo modificaba una hoja bloqueada |
| "Bloqueado" y "Validado" terminaron siendo el mismo campo | Dos ejes separados | En el modelo Ruby están las líneas del significado anterior comentadas, y el filtro de búsqueda quedó con la semántica invertida: filtrar por "Bloqueado" devolvía los desbloqueados |
| El mapeo de los archivos de instrumento, en SQL con ids literales | `instrument_formats.column_map` | El código traía el comentario `DONT MOVE FURANOS COLUMN ORDER` sobre una consulta con `lab_category_sub_detail_id IN (80,81,82,83,84)` |

El detalle completo de cada hallazgo, con archivo y línea del sistema viejo,
está en
[`docs/origen-ruby/AUDITORIA-PRUEBAS-DE-MUESTRAS.md`](../origen-ruby/AUDITORIA-PRUEBAS-DE-MUESTRAS.md).

---

## 4. Las tablas

```
test_groups                 Categoría de pruebas (Físico Químico, Cromatografía)
└─ test_definitions         La prueba
   ├─ test_fields           Sus columnas (tipo, unidad, fórmula, rol, réplicas)
   │  └─ test_field_options Opciones de las columnas de selección
   └─ worksheets            Hoja de trabajo: una prueba, un día, un analista
      └─ worksheet_rows     Una fila: muestra / patrón / duplicado / blanco
         └─ worksheet_values  El valor de una columna en esa fila y esa réplica

analytes                    El parámetro medible (acidez, rigidez, H2, CH4)
instruments                 Equipamiento con su calibración (ISO 17025)
instrument_formats          Cómo se lee cada archivo de instrumento, como dato
instrument_files            Los archivos crudos subidos

qc_charts                   Carta de control de un parámetro, con vigencia
├─ qc_points                Cada medición del patrón, con su z y su regla
└─ qc_duplicates            El par de un duplicado, con su diferencia
```

### `analytes` es la pieza que faltaba

Vale la pena señalarla aparte, porque su ausencia es la razón por la que el
sistema viejo necesitaba una tabla de 221 columnas.

Sin un concepto de "parámetro medido", el `Hidrógeno H2 ppm` de la hoja de
cromatografía y el `H2` del informe son dos textos sin ninguna relación entre
sí. La única manera de unirlos es tener una columna fija por cada uno, en una
tabla que crece cada vez que aparece un parámetro nuevo. Con `analytes`, el
campo de la hoja declara a qué parámetro alimenta (`output_analyte_id`) y el
informe consulta por parámetro, no por posición.

---

## 5. Correspondencia con los menús del sistema viejo

| Menú viejo | Dónde queda |
|---|---|
| Categorías de Módulos | Módulo **Grupos de pruebas** (`test_groups`) |
| Módulos | Módulo **Pruebas** (`test_definitions`) |
| Columnas de los Módulos | Editor de columnas **dentro** de la ficha de la prueba |
| Tipos de Columnas | Pantalla de referencia de solo lectura sobre `config/lab_field_types.php` |
| Muestras | **Hojas de trabajo** (`worksheets`) |
| Valores Constantes | Los campos con `is_reusable` y su `default_value`, editables por prueba |
| Límite de Tendencias | **Cartas de control** (`qc_charts`) |
| Tendencias | **Gráfico de control** sobre `qc_points`, con evaluación de Westgard |

El editor de columnas deja de ser un menú aparte y pasa a estar dentro de la
prueba. En el sistema viejo eran dos pantallas que editaban lo mismo con
formularios distintos, y las dos versiones se habían desincronizado: la de alta
no mostraba el orden ni la acreditación de las opciones, y la de edición sí.

---

## 6. Lo que queda abierto

Preguntas para el laboratorio, que ningún diseño puede responder solo:

1. **`report_use`** ("Mostrar en Reporte") se configuraba en la interfaz vieja y
   **ningún código lo leía**: los informes tomaban los valores por id de columna
   escrito a mano. ¿Debía filtrar las columnas del informe? Acá está como
   `report_visible` y sí funciona, pero hay que confirmar qué esperaba mostrar.
2. **El auto-bloqueo a los 3 días** está comentado en el código viejo, o sea que
   se intentó y se abandonó. ¿Lo quieren de vuelta, ahora como tarea programada?
3. **Cinco columnas de resultado ambiguas** quedaron sin marcar en la
   importación de plantillas (Tensión Interfacial, PCB, Partículas, Metales en
   Aceite, Sedimentos): hay que decidir cuál de sus columnas es el resultado.
4. **La separación de electrodos de la rigidez dieléctrica** (D877 son siempre
   2.54 mm, D1816 admite 1 o 2 mm y los kV no son comparables entre sí).
5. **Cuántas sedes de laboratorio** tiene cada empresa, que define si la hoja de
   trabajo necesita además un ámbito por sede.
