# La frontera entre labapp y trapp

> Mapa de qué dato es de quién en los dos sistemas viejos, y qué le pasó a cada
> uno al cruzar. Es la referencia que hay que tener al lado al escribir los
> importadores del histórico: cada invención listada acá es un dato falso que
> hoy vive en la base de TrafoDex.
>
> Todo sale de leer el código de `labo_old`, el volcado de estructura de
> `lab_app_development`, el esquema de `tr_app_development` y los volcados de
> datos que alimentan a TrafoDex. **No hay acceso a las bases de producción**:
> lo que depende de ellas está marcado como no verificado.
>
> Complementa a [`14-PLAN-MIGRACION-DATOS.md`](14-PLAN-MIGRACION-DATOS.md).

---

## 1. Cómo se tocan los dos sistemas

labapp declara dos conexiones en `config/database.yml`: `primary`
(`lab_app_development`, propia) y `primary2` (`tr_app_development`, la de trapp).
**Quince modelos apuntan a la segunda**, y solo cinco lo dicen de frente:

- Con `establish_connection(:primary2)` y `table_name` explícito:
  `TransformerTrapp`, `ChromatographicalTrapp`, `PhysicalTrapp`, `FuranoTrapp`,
  `CustomerTrapp` (este último **es código muerto**: cero usos).
- Heredando de `Primary2` (`app/models/primary2.rb`), donde la única marca es que
  la línea normal quedó comentada arriba:

```ruby
#class Customer < ActiveRecord::Base
class Customer < Primary2
```

  `Customer`, `CustomerLocation`, `CustomerArea`, `CustomerSubstation`,
  `Country`, `Mark`, `OilType`, `ConmutationType`, `ChromatographicalDuval`,
  `ChromatographicalDgaDiag`.

Un análisis escrito solo sobre los cinco visibles deja fuera clientes, jerarquía
y cuatro catálogos. **La consecuencia operativa**: el día que trapp viejo se
apague, labapp no queda desactualizado — deja de funcionar, porque se queda sin
clientes, sin países, sin marcas y sin tipos de aceite.

---

## 2. Clientes: una sola tabla, compartida

No hay copia ni sincronización. `customers` vive físicamente en
`tr_app_development` y los dos sistemas la usan. Un cliente creado en trapp
aparece en labapp de inmediato, y al revés, porque **es la misma fila**.

En el volcado de estructura de `lab_app_development` **no existe** ninguna tabla
`customers`. (Sí quedan una `customers` y una `countries` locales declaradas en
`db/schema.rb`, residuo anterior al cambio a `Primary2`, que hoy no lee ningún
modelo. Ese archivo está desactualizado y **no es fuente de verdad**.)

**labapp no solo lee: hace CRUD completo** sobre la tabla de trapp
(`im_management/customers_controller.rb`, `create` / `update` / `destroy`).

### `db_system_id`

Existe, se llama `db_system_id` (no `db_system`), y `app/models/customer.rb`
la escribe en cada alta:

```ruby
before_save :save_default_values, :if => :new_record?

def save_default_values
  self.db_system_id = 2
  self.deleted = 0
end
```

Era la marca de procedencia. Dos precisiones:

- **En labapp nadie la lee jamás.** Un `grep` sobre todo el repositorio devuelve
  esa única línea. Es de escritura pura.
- El modelo `DbSystem` existe y **está huérfano**: sin tabla, sin migración, sin
  referencias, y hereda de `ActiveRecord::Base` sin `establish_connection`, o sea
  que apunta a la base equivocada.
- Qué valor escribe trapp (¿`1`?) **no se pudo verificar**: ese repositorio no
  está disponible y el exportador descartó la columna al migrar a TrafoDex.

### Los campos del cliente

Cinco, iguales en formulario, listado y ficha: **País · Nº Documento · Cliente ·
Dirección · Contacto**. "Dirección" es `customers.address`.

**No hay ningún campo "Ubicación" en el módulo de clientes.** La palabra aparece
en otros dos lugares que se confunden con este: la "Locación" del formulario del
transformador (§4) y la tabla `customer_locations` (§3).

### Borrado

`update_attribute(:deleted, 1)`, borrado lógico. **A los hijos no les pasa
nada**: `has_many :rems` y `has_many :transformers` no declaran `dependent:`, y
la jerarquía ni siquiera está declarada como asociación. Quedan activos y
visibles para trapp las subestaciones, áreas, ubicaciones y transformadores de un
cliente borrado.

---

## 3. La jerarquía es una escalera, no un dato

`tr_app_development.transformers` tiene **una sola** columna de las tres:

```sql
CREATE TABLE `transformers` (
  `id` bigint NOT NULL,
  `customer_substation_id` bigint DEFAULT NULL,   -- la unica
  ...
```

**No tiene `customer_id`, ni `customer_location_id`, ni `customer_area_id`.** El
único vínculo del transformador con su cliente es la cadena:

```
transformer -> customer_substation -> customer_area -> customer_location -> customer
```

Por eso labapp tenía que armar los tres niveles: no porque trapp pidiera tres
datos, sino porque **la subestación es el único enganche, y para llegar a una
subestación hay que atravesar los otros dos**. La ubicación y el área `-` no eran
datos: eran los peldaños.

Eso explica que `LegacyTransformersSeeder` de TrafoDex **derive** el `customer_id`
con un JOIN que sube por esa cadena, en vez de copiarlo: en el origen no existe.

La columna es `DEFAULT NULL`, así que la base no obliga. Pero un transformador
sin subestación queda huérfano de cliente e invisible en cualquier listado por
cliente: era obligatorio por diseño, no por restricción.

### Quién crea cada peldaño

| Nivel | Quién lo crea | Cuándo | Nombre |
|---|---|---|---|
| Ubicación | `customer.rb`, `after_create` | al dar de alta el cliente | `-` |
| Área | `customer.rb`, `after_create` | al dar de alta el cliente | `-` |
| Subestación | **la VISTA** `_form_step3.html.erb:62` | **al RENDERIZAR** el paso 3 del import | el texto de `transformers.location` |

Tres cosas de esto merecen atención, y ninguna es evidente:

1. **El alta de cliente NO crea la subestación.** El método se llama
   `create_customer_location_and_customer_area` y hace exactamente eso.
2. **La subestación se crea al dibujar la pantalla, no al confirmar.** Buscar
   `create` en el controlador no encuentra nada: está en la vista. Recargas,
   dobles clics o una precarga del navegador escriben en la base de trapp sin que
   nadie haya confirmado nada. La única defensa es un `where(...).size == 0`, sin
   transacción ni índice único: dos renders simultáneos crean dos subestaciones
   idénticas.
3. **Se crea un solo nivel por render.** Si falta la ubicación, se crea la
   ubicación y ahí termina; hay que volver a entrar para el área y una tercera vez
   para la subestación. Si alguien llega al paso 4 antes de que converja, el
   controlador (que solo hace `find_by`) devuelve nulo, revienta al pedir el `id`,
   y el `rescue` reporta **"Registro Duplicado."** — un mensaje que no tiene nada
   que ver con lo que pasó.

**Efecto sobre los datos ya migrados**: los clientes nacidos en trapp, que ya
tenían su jerarquía real con nombres descriptivos, reciben de labapp una
ubicación y un área fantasma llamadas `-` en paralelo. Se ve en los CSV ya
cargados: nombres reales como "ABENGOA PERU S.A.- SE FRANCOISE" conviviendo con
`-` y `.`.

---

## 4. Equipos: dos padrones, y el bueno es el del laboratorio

`lab_app_development.transformers` es tabla **propia** de labapp
(`class Transformer < ActiveRecord::Base`). trapp tiene la suya. Son padrones
independientes, y la importación va **solo de labapp hacia trapp**.

El campo "Locación" del formulario del transformador (`transformers.location`,
texto libre y obligatorio) es el que termina como **nombre de la subestación**.
No tiene nada que ver con `customers.address`, que es la dirección del cliente.

---

## 5. Inventario del mandrakeo

Todo lo que la importación escribe en trapp con un valor que no viene de labapp.
Origen: `app/controllers/trapp_management/import_transformers_controller.rb`.

| Campo en trapp | De dónde sale | Valor | Consecuencia |
|---|---|---|---|
| `num_vol` (tensión) | regla | `num_ten.split('/').map(&:to_f).max` — de `"220/60/10"` queda **220.0** | Se pierden secundario y terciario. **Alimenta diagnóstico**: la clase de tensión define los umbrales IEEE C57.106 |
| `num_pot` | regla | igual, solo el máximo | La potencia por devanado se pierde |
| `transformer_type_id` | regla | `return "1" if id > 3` | **18 de los 21 tipos** (bujes, reactores, interruptores, electrobombas, intercambiadores) se vuelven "Potencia". **Alimenta diagnóstico**: el tipo es una de las cuatro claves del cuadro de reglas de cromatografía |
| `connection_type_id` | literal | `16` (el grupo `"-"`) | **1.484 de 2.551 equipos** (58%). No alimenta diagnóstico, pero sale impreso como si fuera dato de placa |
| `num_health` | literal | `0` | **640 equipos** con índice 0. **No es un diagnóstico: es el valor de fábrica del importador.** Cualquier tablero que lo lea antes de recalcular muestra basura |
| `state_health` | literal | `"Muy Malo"` | Ídem |
| `color_health` | literal | `"red"` | Ídem, semáforo rojo sin base |
| `num_serie` | regla | `.delete(" \t\r\n")` | Borra **todos** los espacios, incluidos los internos: `"110019 cuba"` se guarda `"110019cuba"` |
| `num_fas` (fases) | no se escribe | **nulo** | 1.075 equipos (42%). No es invención: es hueco |
| `num_tap` | no se escribe | **nulo** | 1.148 equipos |
| `transformer_preservation_id` | **copia literal** | el de labapp | **NO es invención.** Los catálogos coinciden 1:1 (4 filas, ids 1-4). Riesgo latente: el catálogo local es editable sin restricción, y una quinta fila viajaría a una FK que allá no existe |
| `customer_locations.name` | literal, en la vista | `-` | Nodo inventado |
| `customer_areas.name` | literal, en la vista | `-` | Nodo inventado |
| `chromatographical_duvals` | fila vacía | solo `transformer_id` | Y su `after_update` clava `triangle_diag_first: "PD"` sobre todas las filas del equipo cuando el campo queda vacío: mandrakeo de diagnóstico |
| `chromatographical_dga_diags` | fila vacía | solo `transformer_id` | — |

### Datos que labapp tenía y la importación tiró

- `oil_qty`, `transformer_oil_unit_id`, `transformer_oil_mark_id`,
  `transformer_point_id`: trapp no tiene columnas.
- `cro10_val` (total de gases combustibles) y `cro11_val` (total de gases):
  TrafoDex los recalcula.
- **`fur6_val` — el grado de polimerización MEDIDO.** La línea que lo escribía
  está **comentada** en el importador, y trapp tampoco tenía columna. Resultado:
  TrafoDex hoy **estima** el DP con Chendong a partir del 2-FAL cuando el
  laboratorio lo había medido. Se cambió una medición por una derivación.
- `f90_val`, PCB, azufre, viscosidad, color, densidad, inhibidor, sedimentos:
  ninguno viaja.

---

## 6. El cero que significa "no medido"

**La invención más costosa, y sigue viva en TrafoDex.**

En Ruby `nil.to_f` es `0.0`. Los importadores de fiquis y furanos aplican `.to_f`
a todos los parámetros, así que **cada uno que el laboratorio nunca midió entró
como una medición de cero**.

TrafoDex ya anula los ceros de rigidez y factor de potencia
(`LegacyPhysicalsSeeder`), pero **acidez, agua y tensión interfacial siguen con
ceros falsos**:

| Parámetro | Ceros con otros parámetros medidos | Dirección | Efecto del cero falso |
|---|---|---|---|
| Acidez | **1.273** | menor es mejor | **La mejor nota posible.** Infla el índice de salud |
| Tensión interfacial | **479** | mayor es mejor | **La peor nota posible.** Hunde equipos sanos |
| Agua | **222** | menor es mejor | La mejor nota posible |

**Asimetría no intencional**: cromatografía deja nulo donde fiquis y furanos
escriben 0, para exactamente la misma situación. Dos importadores del mismo autor
tratando la ausencia de dato de dos maneras opuestas.

Esto es lo que decide el paso 8 del [paso a paso](15-PASO-A-PASO.md), y ya no es
teórico.

---

## 7. La clave de enlace, y por qué es frágil

Es `num_serie`, sola.

- Alta de equipo: por exclusión, `num_serie NOT IN (...)`.
- Alta de muestras: `TransformerTrapp.find_by(deleted: 0, num_serie: ...)`.

Cinco problemas concretos:

1. **15 series repetidas entre 31 transformadores vivos.** `find_by` sin `order`
   deja que MySQL elija: las muestras pueden estar en la unidad equivocada.
   Intentaron arreglarlo con `num_tag` y `.order(:id).last` — el bloque está
   comentado — y revirtieron a la versión frágil.
2. **La pantalla valida por TAG y el código empareja por SERIE.** Un equipo con
   tag correcto y serie distinta pasa la validación visual y revienta al escribir.
3. **Esa validación es solo de interfaz.** El paso 4 no la repite.
4. **La serie se limpia al escribir y no al buscar.** Los 31 equipos con espacios
   en la serie quedan desincronizados de forma permanente: nunca salen de la lista
   de "por importar" y sus muestras nunca encuentran su equipo.
5. **Si el equipo no existe del otro lado**, `find_by` devuelve nulo y la línea
   siguiente lanza. En cromas, fiquis y furanos **no hay `rescue`**: error 500 a
   mitad del lote.

### Transaccionalidad e idempotencia

- **No hay ni un `transaction do`** en los cuatro importadores. Un fallo deja la
  mitad del lote escrita.
- Transformadores: usa `save!`, así que una segunda corrida **falla ruidosamente**
  y aborta.
- Cromas, fiquis y furanos: usan `.save` sin comprobar el retorno, así que una
  segunda corrida **salta en silencio** y la pantalla dice "Se subieron los datos"
  igual. El usuario no sabe cuántas filas entraron.
- La unicidad es sobre el `datetime` completo y el importador siempre escribe
  medianoche. Una muestra cargada a mano en trapp con hora real no colisiona: **es
  el origen de los duplicados** que `DeduplicateLegacySamplesSeeder` limpia.

---

## 8. Catálogos

### Compartidos (viven en `tr_app_development`)

| Tabla | labapp | TrafoDex | LaboRep |
|---|---|---|---|
| `oil_types` | lee **y** crea, edita, borra | `oil_types`, 6 | `oil_types`, 4 |
| `conmutation_types` | lee **y** crea, edita, borra | `tap_changer_types`, 3 | `tap_changer_types`, 3 |
| `marks` | lee **y** crea, edita, borra | `brands`, 251 | `brands`, 251 |
| `countries` | lee **y** crea, edita, borra | `countries`, 26 | `countries`, 26 |

**En `oil_types`, `marks` y `countries`, las acciones `create`, `update` y
`destroy` NO comprueban ningún permiso.** El gate está solo en las pantallas. Un
POST directo crea filas en la base de trapp desde cualquier usuario autenticado.
Los tipos de aceite tienen además el enlace del menú escondido tras un permiso que
solo tiene el perfil 1 — pero esconder el enlace no cierra el alta.

### Locales de labapp

| Tabla | Filas | En LaboRep |
|---|---|---|
| `transformer_types` | 21 | `equipment_types`, 21, ids preservados |
| `transformer_preservations` | 4 | 4, ids preservados |
| `transformer_oil_marks` | 52 | **degradado a texto**: `equipment.oil_brand varchar(120)` |
| `transformer_oil_units` | 6 | **degradado a texto**: `equipment.oil_volume_unit varchar(20)` |
| `transformer_points` | 4 | **degradado a texto**: `samples.sampling_point varchar` |
| `norms` | 12 | `standards`, 40 + `test_methods` |

---

## 9. El choque de aceites entre los dos sistemas NUEVOS

**Problema latente que hay que resolver antes de que exista el envío por API.**

| LaboRep (4) | TrafoDex (6) |
|---|---|
| `mineral` | `mineral` |
| `silicona` | `silicona` |
| **`ester_vegetal`** | **`vegetal_soya`** · **`vegetal_girasol`** |
| `ester_sintetico` | `ester_sintetico` |
| — | `ninguno` |

`LabTransformerService::resolveOil` de TrafoDex hace
`OilType::where('code', $code)->first()`, **coincidencia literal, sin alias**. Un
equipo con éster vegetal enviado desde LaboRep recibe un **422 `unknown_oil`**.
Son 70 equipos históricos. Este desajuste **no figura** entre los cuatro que
documenta el contrato de la API.

Está latente y no fallando porque el envío de LaboRep hacia TrafoDex todavía no
existe: no hay cliente HTTP, ni cola de salida, ni tabla de mensajes.

### Y los mismos ids viejos se atribuyen distinto

| id viejo | equipos | labapp | TrafoDex |
|---|---|---|---|
| 2 | 6 | mineral | `ester_sintetico` |
| 3 | 4 | mineral | `vegetal_soya` |
| 6 | 3 | vegetal | `vegetal_girasol` |
| 8 | **80** | **mineral** | **`ninguno`** (sin reglas, cae al respaldo IEEE) |

Son **93 equipos** cuya familia de aceite cambia según qué criterio se aplique. El
peor es el id 8: labapp le aplicaba límites de mineral y TrafoDex lo trata como
equipo sin aceite.

Cuántos tipos de aceite tenía trapp **no se pudo determinar**: no hay volcado de
esa tabla. Lo que sí se sabe es un piso de **8 ids en uso** en equipos reales y
**9 ramas** en el código de labapp. La cifra de "3" no se sostiene por ningún lado.

---

## 10. Clientes en los sistemas nuevos

LaboRep tiene tabla propia, en base propia. **No hay nada compartido**: el patrón
de la segunda conexión no se replicó, y ningún modelo de ninguno de los dos
declara una conexión al otro.

Y es un **clon literal** de TrafoDex: migraciones, seeder y los cuatro CSV son
byte a byte los mismos, y llegaron en el primer commit ("Base: copia de TrafoDex
como punto de partida"). Los ids del sistema viejo se preservaron; **los slugs
no** — cada instalación sortea los suyos, y la API de TrafoDex rutea por slug.

Qué usa el laboratorio de la jerarquía, de verdad:

| Superficie | Cliente | Ubicación | Área | Subestación |
|---|---|---|---|---|
| Formulario de equipo | sí | sí | sí | sí |
| Ficha del equipo | — | no | no | no |
| Índice y filtros | — | no | no | no |
| Informe de ensayo | sí | sí | **no** | solo como respaldo |
| Recepción, muestra, bancada | por `customer_id` | no | no | no |

**El nivel "área" se captura y no sale a ninguna parte.** Son 1.940 filas para
nada. Proporción actual: 4.151 filas de jerarquía para 1 equipo cargado.

La API de TrafoDex está escrita asumiendo que **TrafoDex es el dueño** del cliente
y de la jerarquía: el laboratorio no los administra, los resuelve. Pero **no hay
API de jerarquía** —el propio documento lo dice y rechaza explícitamente fabricar
la subestación `-` como hacía el viejo—, así que hoy la subestación tiene que
existir en TrafoDex, puesta a mano, antes de poder dar de alta un equipo.

Nadie escribió cuál de los dos sistemas es el dueño del cliente. Es una decisión
abierta.

---

## Lo que no se pudo verificar

Todo lo que exige una base de producción. En concreto:

- Qué valor escribe trapp en `db_system_id`, y si alguien allá lo lee.
- El contenido literal de `tr_app_development.oil_types`, `marks`,
  `conmutation_types` y `countries`. El único archivo de trapp disponible es
  estructura sin una sola fila.
- Cuáles de esos tipos de aceite estaban con `deleted = 1`. Un catálogo de 9 filas
  con 5 borradas es compatible con "el desplegable mostraba 4".
- Si los catálogos de preservación coinciden fila por fila entre las dos bases.
  La evidencia es fuerte y coherente por tres vías, pero es inferencia.
- Cuántos de los 2.551 transformadores de trapp entraron por la importación y
  cuántos se dieron de alta a mano. No hay marca de origen.
- Cuántas filas de bancada quedan huérfanas al conciliar el número de muestra por
  texto.
- Si trapp escribe en alguna tabla de labapp. Exige el repositorio de trapp, que
  no está disponible.
