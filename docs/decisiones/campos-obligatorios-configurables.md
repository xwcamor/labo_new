# ¿Un módulo para marcar campos obligatorios u opcionales?

**Estado:** evaluado. Recomendación: **no construir el módulo genérico.** Parte ya
existe; el resto tiene cuatro problemas que hay que resolver antes de escribir
una línea, y uno de ellos es de seguridad.

---

## 1. Para las pruebas de muestra, ya está hecho

No hace falta un módulo nuevo: cada columna de una prueba (`test_fields`) tiene su
casilla **obligatoria**, editable desde el editor de columnas, y se hace valer al
publicar la hoja.

| Pieza | Dónde |
|---|---|
| El dato | `test_fields.is_required` |
| El editor | Editor de columnas de la prueba (`TestFieldFormModal.vue`) |
| Donde se exige | `WorksheetService::missingRequiredValues()` — la hoja no se publica con una celda obligatoria vacía |

Y se exige en el momento correcto: al **publicar**, no al escribir. El analista
puede anotar lo que tiene en la mano y completar después; lo que no puede es
publicar un ensayo incompleto. El sistema anterior intentó lo contrario —bloquear
la carga— y terminó desactivando su propio control (el botón quedó envuelto en un
`display:none` con el comentario "SE HA COMENTADO PARA VALIDAR MUESTRAS").

Hay un caso resuelto que conviene no volver a romper: el **código de muestra** es
obligatorio, pero un patrón, un duplicado o un blanco no son la muestra de un
cliente y no lo llevan. Exigirlo en esas filas dejaba la hoja sin poder cerrarse,
reclamando una celda que la propia hoja no deja llenar.

---

## 2. Para los módulos de negocio, no existe — y así está bien por ahora

En Clientes, Equipos, Muestreadores, Marcas, etc., lo obligatorio vive en el
`FormRequest` de cada módulo (por ejemplo `EquipmentFieldRules`, donde `name` y
`customer_id` son `required`). Cambiarlo hoy es tocar código.

Sacarlo a datos es posible —hay un solo lugar por módulo donde están las reglas,
que es el enganche natural— pero tiene estos cuatro problemas.

### Problema 1 — El formulario no es la única puerta

Los mismos modelos se escriben desde:

- el formulario (`FormRequest`),
- el **importador de Excel** (`app/Imports/BusinessManagement/*`),
- la **API del laboratorio** (`/api/v1`, que da de alta equipos),
- los **seeders** y los volcados históricos.

Una regla que viva solo en el `FormRequest` es una regla que el importador
saltea. Y eso no es hipotético: es exactamente el agujero del sistema anterior,
donde la pantalla normal de carga exigía patrón y duplicado y la pantalla "admin"
dejaba elegir cualquier tipo de fila sin contar nada.

**Consecuencia de diseño:** el resolvedor tiene que ser UNO y los cuatro caminos
tienen que pasar por él. Si se implementa solo en el formulario, no se implementó.

### Problema 2 — Hay filas que ya tienen el campo vacío

Marcar `Equipment.serial` como obligatorio no arregla los equipos que ya lo tienen
en nulo. Lo que hace es que el próximo que abra uno de esos equipos para corregir
el TAG no pueda guardar, porque el formulario le reclama un número de serie que
nadie tiene.

Hay que decidir explícitamente entre:

- **aplica solo a registros nuevos** (más suave, pero deja una base con dos
  criterios conviviendo y nadie sabe cuál es cuál), o
- **aplica a todos** y hay que llenar los huecos primero (más limpio, pero es
  trabajo real del laboratorio antes de poder activar la casilla).

Sin esa decisión, activar una casilla bloquea la edición de registros viejos y
parece un error del sistema.

### Problema 3 — Hacer OPCIONAL un campo rompe cosas, y no todas se ven

Esta es la mitad peligrosa, y no es simétrica con la anterior.

| Campo | ¿Se puede volver opcional? | Por qué |
|---|---|---|
| `samples.equipment_id` | Sí | Ya lo es. Una muestra de un cilindro no viene de ningún equipo. |
| `receptions.customer_id` | **No** | De él sale el cuadro de límites y la cabecera del informe. Sin cliente el informe sale entero en raya y nadie lo nota hasta imprimirlo. |
| `equipment.oil_type_id` | **No** | Decide qué cuadro de límites aplica. Sin aceite no hay criterio, y "sin criterio" no es "cumple". |
| `equipment.voltage_kv_hv` | **No** | Define la clase de tensión del IEEE C57.106, o sea el límite contra el que se juzga. |

O sea: la lista de campos que se pueden aflojar es un **catálogo cerrado**, no
"todas las columnas del módulo". Ofrecer la casilla en todos los campos es
regalarle al usuario una forma de romper el diagnóstico sin ningún aviso.

### Problema 4 — La base no puede garantizarlo

Un campo obligatorio-por-configuración sigue siendo `nullable` en la base: la
columna tiene que aceptar nulo porque mañana la configuración puede cambiar. Así
que la garantía vale exactamente lo que valga el camino de código — y de ahí que
el Problema 1 sea el que manda.

Es la diferencia con `test_fields.is_required`, que no promete integridad de base:
promete que la HOJA no se publica. Esa es una promesa que el código sí puede
cumplir, porque publicar es un solo punto.

### Lo que la gente quiere de verdad, y que esto no es

Al segundo día el pedido deja de ser "obligatorio sí/no" y pasa a ser
*"el número de serie es obligatorio **si** el equipo es un transformador de
potencia"*. Eso no es una casilla: es un motor de reglas, con su evaluador de
expresiones y su editor. Vale la pena saberlo antes de empezar, porque la casilla
simple es el primer escalón de eso y no un destino.

---

## 3. Recomendación

1. **Pruebas de muestra:** nada que hacer, ya está.
2. **Módulos de negocio:** si se hace, hacerlo angosto:
   - una lista CERRADA de campos aflojables, declarada en `config/` y revisada
     contra el motor de diagnóstico (los cuatro de la tabla de arriba quedan
     fuera);
   - override **por workspace**, no global;
   - **un** resolvedor compartido por el formulario, el importador y la API;
   - y la decisión del Problema 2 tomada y escrita antes de exponer la pantalla.
3. **Mientras no esté:** cambiar un obligatorio es una línea en el `FormRequest`
   del módulo, que es donde hoy se lee de un vistazo cuál es la regla.

Lo que NO conviene es la versión genérica sobre todas las columnas de todos los
módulos. Es la que se ve más potente en una demostración y la que puede dejar un
informe sin criterio de aceptación sin que nadie se entere.
