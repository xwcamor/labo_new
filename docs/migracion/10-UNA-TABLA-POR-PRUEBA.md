# ¿Una tabla por prueba?

> El laboratorio lo preguntó tres veces, y las tres veces con razón. Este
> documento no es otro argumento: es lo que cuesta cada opción, y el comando que
> le da la tabla ancha sin obligarlo a elegir.
>
> Antes de nada, la pregunta directa que hizo: **¿el modelo genérico es para
> conservar su base vieja?** No. No queda ni una tabla, ni una columna, ni un id
> del sistema anterior. El esquema es nuevo entero. La razón es otra y está más
> abajo.

---

## 1. Lo que ya tiene: la tabla ancha, por prueba

```bash
php artisan lab:build-views
```

Genera **29 vistas, una por prueba**, con las columnas que el laboratorio le
puso a cada una:

```sql
SELECT * FROM v_analisis_cromatografico;
```

```
FECHA       TIPO     NRO_MUESTRA  NORMA          H2      O2        N2        CH4    CO      CO2      C2H4   C2H6  C2H2  TDCG    TOTAL
2026-06-10  sample   2026-0031    ASTM 3612…     26.46   9789.35   51921.15  10.18  269.7   3048.19  8.04   6.06  1.92  322.36  65081.05
2026-06-10  sample   2026-0032    ASTM 3612…     55.01   11534.2   49512.79  24.89  365.34  3230.18  49.14  15.32 15.68 525.38  64802.55
```

Es, columna por columna, la exportación a Excel que el laboratorio mandó. Y las
29 están:

```
v_analisis_cromatografico   v_numero_acido        v_contenido_de_agua
v_rigidez_dielectrica       v_tension_interfacial v_factor_de_potencia_25o
v_furanos                   v_pcb                 v_metales_en_aceite   …
```

Se conectan desde Excel, desde Power BI o desde cualquier cosa que hable SQL.
El tiempo de respuesta sobre la base actual es de **6 a 10 ms**.

---

## 2. Entonces, ¿por qué no son TABLAS?

La diferencia **no es el rendimiento**. Eso ya se midió sobre 84 millones de
filas ([`08-BENCHMARK-VERTICAL-VS-ANCHO.md`](08-BENCHMARK-VERTICAL-VS-ANCHO.md)):
con los índices correctos ninguna de las dos formas pasa de 200 ms, y con los
índices equivocados las dos se caen igual.

La diferencia es **qué pasa el día que el laboratorio agrega una prueba o una
columna**.

| | Tabla física por prueba | Vista por prueba |
|---|---|---|
| Agregar una prueba | `CREATE TABLE` | volver a correr un comando |
| Agregar una columna a una prueba | `ALTER TABLE` | volver a correr un comando |
| Quién puede hacerlo | un programador, o la aplicación con permiso para alterar su propio esquema | el supervisor del laboratorio, desde la pantalla |
| Si sale mal | los datos están en la tabla que se alteró | una vista no guarda nada: se borra y se rehace |
| Queda versionado | sí, si lo hace un programador; no, si lo hace la aplicación | el comando es código; el resultado es derivado |

El punto que me parece decisivo es el tercero. Hoy el laboratorio agrega una
prueba desde la pantalla. Con tablas físicas hay dos caminos y ninguno es
cómodo:

- **Que lo haga un programador cada vez.** Entre 2023 y 2024 se agregaron cuatro
  pruebas (Rigidez Electrodos Planos, Resistividad 25 °C y 100 °C, Factor de
  Potencia 90 °C). Son unas dos al año: no es mucho, pero cada una queda
  esperando a que alguien la programe.
- **Que la aplicación ejecute el `ALTER TABLE`.** Eso significa darle al usuario
  de base de datos permiso para modificar el esquema. Una aplicación que puede
  alterar sus tablas es una aplicación donde un error borra una.

---

## 3. Y lo que el sistema anterior ya demostró

Esto no es teoría: **su sistema anterior probó las dos formas**.

**Una columna por prueba.** La tabla `rems` lleva quince contadores:
`num_fiq`, `num_cro`, `num_pcb`, `num_fur`, `num_par`, `num_azu`, `num_sed`,
`num_met`, `num_vis`, `num_dbd`, `num_inf`, `num_flu`, `num_inh`, `num_pol`,
`num_pas`.

El laboratorio corre **29** pruebas. Las cuatro que se agregaron después **no
tienen columna**. Hoy una remisión no puede registrar cuántos envases de
Resistividad recibió, porque agregar la prueba exigía un `ALTER TABLE` que nadie
corrió. El esquema se quedó atrás del laboratorio, que es exactamente el riesgo.

**Una tabla ancha.** `rem_report_details` tiene **221 columnas** y **un solo
índice: la clave primaria**. La forma horizontal ya estaba y no hizo rápido a
nada.

---

## 4. Dónde el modelo genérico es peor, y se admite

Para que la comparación sea honesta:

- **Cuesta más entenderlo.** Un `SELECT * FROM cromas` se lee solo; llegar al
  mismo dato pasando por `worksheet_values` no. Por eso existen las vistas: la
  lectura vuelve a ser obvia.
- **Necesita un paso de materialización.** Al validar una hoja hay que escribir
  los `results`. Con tablas por prueba el dato ya estaría en su lugar.
- **La capa cruda es incómoda.** `worksheet_values` es la parte menos agradable
  del esquema, y no se puede consultar directo. Es el precio de que el
  laboratorio configure sus propias pruebas.

---

## 5. Si aun así prefiere tablas físicas

Es una decisión legítima y el cambio está acotado. Lo que quedaría igual:

- `results` — la capa consultable, tipada e indexada;
- los cuadros de límites y el veredicto;
- la recepción, los correlativos y el estado;
- las vistas (pasarían a ser las tablas mismas).

Lo que cambiaría: `worksheet_values` se reemplaza por 29 tablas, una por prueba,
y el generador de vistas pasa a ser un generador de migraciones. El motor de
fórmulas, la bancada y el control de calidad no se tocan.

Lo que se perdería: agregar una prueba o una columna desde la pantalla. Pasaría
a ser una migración, revisada y desplegada.

**Es su llamado.** Si lo decide, se hace. Lo que no voy a hacer es darle a la
aplicación permiso para alterar su propio esquema en producción — eso sí lo
considero un error, con tablas o sin ellas.
