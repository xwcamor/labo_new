# Fuentes del laboratorio — los papeles originales

Los archivos de esta carpeta los facilitó el dueño del laboratorio el **2026-07-31**
y son **fuente primaria**: son los documentos con los que el laboratorio trabaja,
anteriores al sistema Ruby y anteriores a éste. Cuando un número de este proyecto
discrepe de lo que dice acá, **manda lo que dice acá** — el sistema Ruby era una
implementación de estos papeles, y una implementación puede tener errores que el
papel no tiene.

Hasta ahora la única fuente versionada era el volcado parcial de la base vieja
(`docs/migracion/esquema/catalogos-definiciones.sql`) más el código Ruby. Los dos
son derivados: el volcado tiene lo que alguien cargó y el código tiene lo que
alguien programó. Estos Excel son el criterio en sí.

---

## Qué hay

### `VALORES_DE_ORIENTACION.xlsx` — el cuadro de límites completo

Tres hojas: **FQ** (fisicoquímicos), **CR** (cromatografía), **OTROS** (PCB,
azufre, DBDS, inhibidor, viscosidades, grado de polimerización, pasivador).

Es **el cuadro de reglas y condiciones** que el sistema nuevo tenía a medias. Su
matriz completa cruza cuatro dimensiones:

| Dimensión | Valores |
|---|---|
| Tipo de fluido | mineral · silicona · Midel · vegetal (soya FR3 y girasol) |
| **Estado del aceite** | **nuevo · nuevo en el trafo · antes de energizar · en servicio · tratado · usado** |
| Clase de tensión | ≤69 kV · >69–<230 kV · ≥230–<345 kV (y ≥230 sin tope) |
| Tipo de equipo | distribución <5 MVA · potencia ≥5 MVA · horno · corriente · voltaje · instrumento · bushing · cables · interruptor · conmutador |

El **estado del aceite** es la dimensión que faltaba: el sistema Ruby asignaba
automáticamente solo las filas de *en servicio*, y las demás únicamente se
alcanzaban si el analista abría el selector y elegía a mano. En este proyecto la
columna existe (`spec_sets.service_state`) y está sin llenar.

Y trae un dato que el resto no distinguía: la rigidez dieléctrica tiene **tres
filas, una por método**, con valores distintos —**ASTM D1816**, **ASTM D877** e
**IEC 60156**—. El límite depende de con qué método se midió.

### `DIAGNOSTICO_REPORTES.xlsx` y `DIAGNOSTICO_REPORTES_web.xlsx`

Los **textos de diagnóstico** que se imprimen en el informe, redactados por el
laboratorio, con las bandas que disparan cada párrafo. Siete hojas: `ACEITE`,
`SILICONA`, `MIDEL`, `FR3-VEGETAL`, `INTERRUPTORES`, `IEC60422`, `OTRO`.

Además de la prosa llevan **su propia copia de la matriz de límites**, que sirve
para cotejar contra `VALORES_DE_ORIENTACION.xlsx`.

Las dos versiones se conservan a propósito: la hoja `IEC60422` difiere entre
ellas (128 filas contra 1000), así que no son la misma revisión.

### `F-PG-TR-LA-17-39_Carta_de_control_AGUA_2022.xlsx`

La **carta de control del patrón interno** del laboratorio, para el contenido de
agua, con su código de formato controlado (`F-PG-TR-LA-17-39`, versión 1). Cuatro
hojas: `Carta control`, `Carta control Temp (2)`, `LIMITES `, y la tabla de
verificación del higrómetro `TCV HIGRÓMETRO TH-01`.

**De acá salen las fórmulas de los cinco límites**, que en el sistema nuevo eran
`warn_sigma`/`action_sigma` con un valor de fábrica sin origen documentado:

```
línea central   LC  = promedio de las lecturas del patrón
límite interior      = LC ± 2σ      ← advertencia
límite exterior      = LC ± 3σ      ← control / acción
```

Confirmado con los números de la hoja: `LC = 22.7`, `σ = 0.489193565807`, y
`22.7 + 2σ = 23.6784`, `22.7 + 3σ = 24.1676`. Coincide con `warn_sigma = 2` y
`action_sigma = 3`.

> **⚠ TRAMPA AL SEMBRAR: los nombres de los cinco límites no son fiables.**
>
> Las dos hojas del MISMO archivo usan las siglas al revés:
>
> | | LC + 2σ (interior) | LC + 3σ (exterior) |
> |---|---|---|
> | hoja `LIMITES ` (fila 2) | `LCS` | `LAS` |
> | hoja `Carta control` | `LAS` | `LCS` |
>
> Y el volcado de la base vieja (`patron_tendences`, 27 filas) tampoco es
> consistente entre sus propias filas: en *Factor de Potencia 25º* la columna
> `lci` es la de 3σ, y en *Densidad Relativa* la de 3σ es `lai`.
>
> **Al migrar hay que mapear por DISTANCIA A LA LÍNEA CENTRAL, no por el nombre
> de la columna.** El más alejado es el de control (3σ) y el intermedio es el de
> advertencia (2σ). Mapear por nombre invierte los límites en una parte de las
> cartas, y una carta con los límites invertidos da por buena una corrida fuera
> de control.

---

## Lo que estos archivos ya resolvieron

- **El acetileno de «transformador de voltaje · mineral» es 16 ppm.** La hoja
  `CR` lo dice directamente (`DE Voltaje → C2H2 16`). En el sistema Ruby ese
  límite estaba escrito `"16"` sin la palabra «máximo»
  (`rem_report_detail.rb:522` y su copia `rem_report.rb:588`), y como el código
  derivaba el número quitándole al texto las letras de «(máximo)» —una operación
  que en Ruby devuelve `nil` cuando no hay nada que quitar—, el límite se leía
  **0** y cualquier acetileno detectable salía en rojo. El papel siempre dijo 16.
- **Los ocho parámetros que este proyecto tenía sin criterio** sí lo tienen en la
  hoja `OTROS`: PCB `Libre de PCB <2`, azufre `No corrosivo`, DBDS `5 - máximo`,
  inhibidor `0.08 - 3.00%`, viscosidades por temperatura, grado de polimerización
  con sus cuatro bandas y pasivador con sus tres.
- **El grado de polimerización y el pasivador llevan la condición en palabras
  dentro del criterio**: `1000-1200 (Nuevo)`, `<50 ppm (Deficiente)`. No es un
  rango numérico con una etiqueta al lado: la etiqueta es parte del criterio, y
  el modelo de datos necesita dónde guardarla.

## Lo que NO está acá, y por qué

Dos de los seis archivos que entregó el laboratorio no se versionan, porque este
repositorio es **público**. Están en `storage/app/legacy-assets/fuentes-con-datos-reales/`,
que está fuera del control de versiones, junto con los logos:

| Archivo | Motivo |
|---|---|
| `LAB_Plantilla_de_importacion_de_transformadores_por_cliente.xlsx` | Bajo la cabecera trae tres filas de equipos reales de un cliente: ubicación, número de serie, TAG, tensión, potencia, marca y volumen de aceite. |
| `VALORES_DE_ORIENTACION_web.xlsx` | Es la revisión con más contenido de la matriz, pero dos de sus celdas anotan con nombre y apellido quién pidió un cambio de criterio. |

Si hace falta consultarlos, están en esa carpeta de la instalación. Para
versionar el `_web` habría que quitarle esas dos anotaciones, y eso ya no sería
el documento original.
