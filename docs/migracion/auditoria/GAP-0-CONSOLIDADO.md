# Lo que el sistema viejo hace y el nuevo no — consolidado

> **Fecha:** 2026-08-02.
> **Alcance:** los seis bloques del sistema Rails, auditados en paralelo contra
> el sistema nuevo. Entre los seis se abrieron **~510 archivos** del viejo
> (controladores, modelos, vistas, rutas y esquema).
>
> **Detalle por bloque** — este documento es el índice y el orden de trabajo; la
> evidencia `archivo:línea` de cada hueco está en su archivo:
>
> | | Bloque | Archivo |
> |---|---|---|
> | 1 | Recepciones, correlativos, muestreadores | [`GAP-1-recepciones.md`](GAP-1-recepciones.md) |
> | 2 | Informes, firmas y la redacción del análisis | [`GAP-2-informes.md`](GAP-2-informes.md) |
> | 3 | Equipos, clientes y catálogos | [`GAP-3-equipos-catalogos.md`](GAP-3-equipos-catalogos.md) |
> | 4 | Bancada y cuadros de condiciones | [`GAP-4-bancada-condiciones.md`](GAP-4-bancada-condiciones.md) |
> | 5 | Reportes gerenciales e integración | [`GAP-5-reportes-integracion.md`](GAP-5-reportes-integracion.md) |
> | 6 | Usuarios, permisos, auditoría y el menú lateral | [`GAP-6-transversal.md`](GAP-6-transversal.md) |

---

## 0. Por qué esta auditoría reemplaza a la anterior

`E-cobertura-tablas.md` compara TABLAS. Ese fue el error de método: una tabla
puede estar «portada» y aun así haber perdido el campo que la hacía útil, o
tener el campo y no tener pantalla.

El caso que lo demuestra: `rem_user_signatures` figura como **PORTADA** con la
nota *«las dos tablas del viejo se unificaron en una»*. Pero `rem_signatures` es
**quién firma el informe** y `rem_user_signatures` es **quién autoriza el ingreso
de la muestra** — dos momentos y dos responsabilidades distintas. Al unificarlas
se perdió el autorizador, que en el viejo es un campo **obligatorio** del alta de
la recepción (`_form_new.html.erb:69`). La auditoría de tablas lo dio por bueno.

Esta auditoría compara **lo que el usuario puede hacer**: pantallas, columnas,
filtros, botones, campos del formulario y frases impresas.

---

## 1. Los cinco que hay que resolver antes de cualquier otra cosa

Estos cinco no son «le falta una columna». Son afirmaciones falsas en papeles
firmados, o datos que se pierden sin aviso.

### A. El informe declaraba limpio un aceite contaminado con PCB — CORREGIDO

La plantilla del análisis de PCB decidía por CUÁNTOS parámetros quedaron fuera
de norma. El PCB no tiene cuadro de límites sembrado, así que ninguno queda
nunca fuera de norma, así que el caso era **siempre** el de «todo en orden», y
el informe imprimía

> «El contenido de PCB se encuentra por debajo del límite establecido. La
> muestra NO se clasifica como contaminada.»

**con cualquier concentración medida.** Además el texto era redacción nueva: no
citaba la IEC 60422:2024, no mencionaba el corte de 2 ppm y perdía la línea del
**D.S. N°018-2025-SA**, que es la única afirmación del informe con efecto legal.

El viejo decide por el VALOR MEDIDO
(`_form_add_details_pcbs_default_values.html.erb:31-46`). Portado tal cual, con
bandas sobre el número. Verificado: con 0.5 ppm dice libre, con 12 ppm dice
contaminado y cita el decreto.

Queda señalado —y NO corregido por criterio propio— que el texto del viejo mezcla
dos cortes en la misma frase: clasifica por 2 ppm y después afirma estar «DENTRO
de la concentración peligrosa (>50mg/kg)». Con 10 ppm medidos eso es falso. Es
una decisión del laboratorio, no del programa.

### B. La cromatografía emite juicios que nadie del laboratorio redactó

Las plantillas del nuevo agregan «no se evidencia actividad de falla incipiente»
y «se recomienda repetir el ensayo». Verificado por búsqueda en las 116 vistas
del viejo: **cero coincidencias**. Son dos juicios técnicos inventados, en un
informe firmado.

### C. La norma se cita sin mirar el tipo de aceite

Las seis plantillas de cromatografía y fisicoquímico declaran `oil_types: []`.
Una muestra de silicona o de éster sale citando la norma del mineral, y un
conmutador —que el viejo declaraba explícitamente *sin valores típicos*— recibe
un diagnóstico que afirma estar dentro de ellos. El motor ya soporta el filtro:
falta el dato.

### D. Quince relaciones apuntaban a una clase que no existe — CORREGIDO

`App\Models\Transformer` no existe en este repositorio; quedó del scaffold del
sistema de diagnóstico. Dos de las quince están en el **portal público** de
informes compartidos, así que el enlace que recibe un cliente moría con un error
fatal. Cinco se repuntaron a `Equipment` con su clave foránea explícita; las
otras seis no tienen columna en `equipment` ni la tuvieron nunca y se eliminaron.

### E. Borrar arrastraba nada — CORREGIDO a medias

**La entrega (corregido).** Dar de baja una recepción dejaba vivas sus muestras y
sus informes: la bancada las seguía ofreciendo y se podía emitir el papel de una
entrega que ya no existe. Verificado en la base antes de tocar nada. Ahora
arrastra en transacción, y una entrega con informe **emitido** no se borra.

**La fila de la bancada (PENDIENTE).** `destroyRow()` solo borra la fila; como es
borrado suave, la cascada de `results` no dispara: el resultado sobrevive, el
punto de la carta de control queda y la prueba no vuelve a la cola.

---

## 2. Recuento

| Bloque | AUSENTE | PARCIAL | DECIDIDO | Total |
|---|---|---|---|---|
| 1 · Recepciones | 6 | 5 | 2 | 13 |
| 2 · Informes | 11 | 7 | 1 | 19 |
| 3 · Equipos y catálogos | 3 | 6 | 8 | 17 |
| 4 · Bancada y condiciones | 6 | 8 | 10 | 24 |
| 5 · Reportes e integración | 13 | 4 | 10 | 27 |
| 6 · Transversal | 7 | 4 | 9 | 20 |
| **Total** | **46** | **34** | **40** | **120** |

**DECIDIDO** = documentado como no-portar con su motivo (almacén, etiquetas con
QR, reportes gerenciales de fases posteriores). No son huecos abiertos.

**El menú lateral**, que es el inventario real de módulos: de **43 entradas** del
viejo, **19 tienen equivalente completo, 7 parcial y 17 ninguno**. De esas 17, 16
ya estaban documentadas; la única nueva es que **«Sistema de expansión» perdió su
pantalla** — la tabla, el modelo y el sembrador existen, y el valor alimenta un
desplegable de la ficha del equipo, pero no hay ruta ni menú para editarlo.

---

## 3. Lo que corta la operación el día del corte

### El envío a TrafoDex no existe en ninguna forma

El viejo escribía cromatografía, fisicoquímico y furanos **directo** en la base
del sistema de diagnóstico, desde tres asistentes
(`import_cromas_controller.rb:122-140` y sus dos hermanos), y daba de alta el
transformador del otro lado creando además sus filas de Duval y DGA. En el nuevo
solo existe `equipment.external_ref`: una caja de texto que se tipea a mano.

**Consecuencia:** desde el corte, TrafoDex se queda sin datos. Su índice de salud
y su tablero de flota se congelan en la última carga del sistema viejo. Y un
equipo nuevo nunca aparece del otro lado, así que aunque se construyera el envío,
los resultados no tendrían dónde entrar.

Es el hueco más grande de los 46 y no tiene nada construido.

### El PCB y el azufre no tienen cuadro de límites

Es la causa de A. El azufre ya se resolvió portando su plantilla; el PCB también.
Pero la causa de fondo sigue: **sin cuadro de límites, cualquier familia cuya
plantilla decida por conteo de incumplimientos afirma «todo en orden»**. Hay que
revisar las 21 plantillas con ese criterio, no solo las dos que ya explotaron.

### La misma muestra se puede cargar dos veces en la bancada

No hay índice único sobre `sample_test_id` y el selector sigue ofreciendo la
muestra ya cargada. Dos filas producen dos resultados del mismo parámetro.

---

## 4. Correcciones a auditorías anteriores

La auditoría nueva corrigió tres cosas que las anteriores daban por ciertas:

1. **`fiq_temperatures.fiq_lab_pre` no es presión atmosférica.** Es «Temp. de
   Muestra en Laboratorio (°C)», y el informe la imprime en °C.
   `E-cobertura-tablas.md` §2.8 la trata como presión.
2. **La presión atmosférica sí tiene columna y está cableada de punta a punta**
   (`worksheets.lab_pressure_hpa`). El «pendiente normativo» que figuraba abierto
   estaba mal por partida doble.
3. **`transformer_uploads` es un importador de Excel, no carga de fotos.** El
   sistema viejo no tiene ningún adjunto de equipo, así que ahí no había hueco.

Y una aclaración de método: `sample_management/samples_controller.rb` tiene **0
bytes** y ninguna ruta lo declara. La bancada real del viejo es
`pr_management/templates`. Cualquier conclusión anterior sacada de ese archivo
vacío no vale.

---

## 5. Orden de trabajo propuesto

**Primero — el informe no puede mentir.** B y C de la sección 1: sacar las frases
inventadas de cromatografía y declarar el tipo de aceite en las plantillas.
Revisar las 21 plantillas que deciden por conteo. Nada de esto necesita esquema
nuevo: es dato.

**Segundo — que no se pierda trabajo.** La fila de bancada que se borra sin
retirar su resultado, y el índice único que impide cargar dos veces la misma
muestra.

**Tercero — decidir sobre TrafoDex.** No es una tarea de programación hasta que
esté decidido qué reemplaza al enlace directo entre bases: una API, un archivo,
o que el diagnóstico lea de acá. Sin esa decisión no hay nada que construir.

**Cuarto — lo que se ve.** Las ocho columnas del índice de equipos, el
autorizador del ingreso, la pantalla de «Sistema de expansión», los filtros del
listado de recepciones, la exportación del registro de auditoría.
