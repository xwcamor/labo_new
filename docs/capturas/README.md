# Capturas del módulo Pruebas de Muestras

Tomadas con la aplicación corriendo y las 29 plantillas reales del sistema
viejo ya importadas. La prueba de ejemplo es **Análisis Cromatográfico**
(id 10 en el sistema Rails), con sus 13 columnas tal como estaban allá.

| Captura | Qué muestra |
|---|---|
| `07-menu-lateral.png` | El grupo **Pruebas de Muestras** en el menú, con sus seis entradas |
| `01-pruebas-buscando-cromas.png` | El listado de pruebas, filtrando por "Cromatogr" |
| `02-ficha-cromas.png` | La ficha de la prueba |
| `03-columnas-de-cromas.png` | El editor de columnas: las 13 columnas con su tipo y su **rol** |
| `04-valores-constantes.png` | Los valores que se arrastran de una muestra a la siguiente |
| `05-hojas-de-trabajo.png` | El listado de hojas de trabajo |
| `06-bancada-cromas.png` | La grilla del analista: patrón control, duplicado y dos muestras |
| `06-nueva-carta-de-control.png` | El alta de una carta de control |
| `00-tablero.png` | El tablero, para ubicarse |

## Cómo llegar a cromatografía

    Menú → Pruebas de Muestras → Pruebas → "Análisis Cromatográfico"

Desde la ficha de la prueba se llega a sus **Columnas** y a sus **Valores
constantes**. Para cargar ensayos:

    Menú → Pruebas de Muestras → Hojas de trabajo → Nueva hoja de trabajo
    → elegir "Análisis Cromatográfico" y la fecha

## Lo que se ve en la grilla, y por qué

La hoja exige **patrón control** y **duplicado** antes de admitir muestras.
Es la regla del sistema viejo, que allá vivía en las opciones de un select y
un envío directo salteaba; acá la verifica el servidor.

Las nueve columnas de gases son las mismas del sistema anterior. **Ninguna
está marcada como resultado todavía**: el importador solo marca el resultado
cuando puede deducirlo sin ambigüedad, y en cromatografía detectó "Total de
Gases Combustibles". Los nueve gases deberían declararse cada uno como
resultado y apuntar a su parámetro (H2, O2, N2, CH4, CO, CO2, C2H4, C2H6,
C2H2). Se hace desde el editor de columnas, sin tocar código, y es lo que
permite que el informe consulte por parámetro en vez de por posición.
