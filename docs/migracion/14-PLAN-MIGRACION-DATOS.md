# Plan de migración de datos: labapp (Rails/MySQL) → LaboRep (Laravel/Postgres)

> Desarrolla la **Fase 12** del [plan maestro](00-PLAN-MAESTRO.md), que la
> enunciaba en veinte líneas. Acá está el orden, el método, las trampas
> conocidas, cómo se demuestra que salió bien y qué falta decidir.
>
> Lo que sigue sale de leer el código de los dos sistemas y el esquema vivo del
> nuevo. **Nada de esto se verificó contra la base MySQL de producción**, porque
> no está disponible en el entorno de desarrollo. Los puntos que dependen de
> ella están marcados como tales y son la primera tarea del plan.

---

## 1. La frontera: qué es de quién

El sistema viejo del laboratorio **no tiene una sola base**. `config/database.yml`
declara dos conexiones: `primary` (`lab_app_development`, propia) y `primary2`
(`tr_app_development`, la del sistema de diagnóstico "trapp"). Quince modelos del
laboratorio apuntan a la segunda.

Cinco lo dicen de frente, con `establish_connection(:primary2)` y `table_name`
explícito. Los otros diez **no se ven**: heredan de una clase `Primary2` y la
única marca es que la línea normal quedó comentada arriba.

```ruby
#class Customer < ActiveRecord::Base
class Customer < Primary2
```

Un plan escrito solo sobre los cinco visibles deja fuera clientes, su jerarquía
completa y cuatro catálogos.

### Compartido con trapp (una sola tabla, los dos sistemas la usan)

`customers`, `customer_locations`, `customer_areas`, `customer_substations`,
`countries`, `marks`, `oil_types`, `conmutation_types`.

No hay copia ni sincronización: es la misma fila. El laboratorio da de alta
clientes desde su propio menú y la fila nace en la base de trapp, con
`db_system_id = 2` como única marca de origen. Al crear un cliente inserta
además su ubicación y su área con nombre `"-"`.

**Consecuencia operativa**: el día que trapp viejo se apague, labapp no queda
desactualizado — deja de funcionar, porque se queda sin clientes, sin países,
sin marcas y sin tipos de aceite. Eso fija el orden: LaboRep tiene que estar
desacoplado **antes** del apagado, no después.

### De trapp, escrito por el laboratorio

`transformers` (de trapp), `chromatographicals`, `physicals`, `furanos`,
`chromatographical_duvals`, `chromatographical_dga_diags`.

El laboratorio los llena con asistentes de cuatro pasos
(`app/controllers/trapp_management/`). El enlace es por **número de serie en
texto**, con `find_by`, que devuelve el primero: si hay dos equipos con la misma
serie —y el modelo lo permite— el resultado se carga en el equipo equivocado.

### Propio del laboratorio (y de nadie más)

Todo `lab_app_development`: 47 tablas. Recepciones (`rems`), muestras
(`rem_correlatives`), pruebas pedidas (`rem_jobs`), informes (`rem_reports` +
`rem_report_details`), hojas de trabajo (`labs` → `lab_details` →
`lab_sub_details`), archivos de instrumento, almacén, etiquetas, bitácoras de
condiciones ambientales, usuarios y permisos.

**Y el padrón de equipos**: `lab_app_development.transformers` es tabla propia
(`class Transformer < ActiveRecord::Base`, sin `Primary2`).

---

## 2. Los transformadores: dos padrones, y el bueno es el del laboratorio

Cada sistema tenía el suyo, y había un módulo para empujar del laboratorio hacia
trapp (`trapp_management/import_transformers_controller.rb`). **Ese empujón
pierde información**, y eso decide de dónde se migra el equipo.

| Dato | Laboratorio | Lo que llegó a trapp |
|---|---|---|
| Tensión | `num_ten varchar` — `"220/60/10"` | `num_vol` = `split('/').map(&:to_f).max` → **60.0** |
| Potencia | `num_pot varchar` — la placa completa | igual, solo el máximo |
| Ubicación | `location varchar`, texto libre | se busca una subestación por ese texto |
| Tipo de equipo | 21 tipos | `return "1" if id > 3` → todo lo que no es Potencia/Distribución/Horno **se vuelve Potencia** |
| Tipo de conexión | — | `connection_type_id: 16` clavado |
| Índice de salud | — | `num_health: 0, state_health: "Muy Malo"` clavado |

Los 2.562 transformadores que TrafoDex ya tiene migrados son **esa copia
aplanada**, no el original. La placa completa con sus barras solo existe en la
base del laboratorio.

Dos corolarios:

- El registro de equipos de LaboRep **no se copia de TrafoDex**: se migra del
  laboratorio, y después se empareja con el equipo de TrafoDex por número de
  serie, guardando su `slug` en `equipment.external_ref` (que es lo que la API
  de TrafoDex ya espera).
- Los 580 equipos con `HI 0 / "Muy Malo"` que `verify:legacy` excluye **no son
  un diagnóstico**: son el valor que el laboratorio clavaba al dar de alta. Si
  algún tablero los lee como "Muy Malo", muestra basura.

Riesgo latente sin verificar: `transformer_preservation_id` se copia tal cual
entre las dos bases, pero ese catálogo es **local del laboratorio**. Depende de
que los ids coincidieran por casualidad. No se puede comprobar sin las dos bases.

---

## 3. El orden

El orden intuitivo —clientes, transformadores, muestras, registros, informes—
es correcto en su tramo final y no aplica en el primero: clientes y jerarquía
**ya están cargados** (344 + 843 + 1.940 + 1.368, con el id del sistema viejo
como clave primaria), y los transformadores no vienen de donde parecía.

```
 0. CENSO Y CONGELAMIENTO      cuánto hay, dónde y hasta qué fecha
 1. EQUIPOS                    lab_app.transformers → equipment
 2. RECEPCIONES Y MUESTRAS     rems → receptions ; rem_correlatives → samples
 3. PRUEBAS PEDIDAS            rem_jobs → sample_tests
 4. BANCADA                    labs → worksheets → rows → values
 5. RESULTADOS                 lab:rebuild-results (NO importar la tabla)
 6. INFORMES                   rem_reports → sample_reports (límites congelados)
 7. SATÉLITES                  bitácoras, almacén, etiquetas, usuarios
 8. CONTADORES Y VERIFICACIÓN  ver §6 y §7
```

**Por módulo, no por año.** Migrar "2019 completo, después 2020" obliga a
recorrer las siete etapas siete veces y a mantener la conciliación abierta todo
ese tiempo. Además el enlace entre bancada y muestra es por texto (§5.2) y sus
huérfanos solo se pueden juzgar con el padrón entero cargado.

El corte por año sí sirve para **una prueba de humo**: correr las siete etapas
sobre el año más chico, medir, mirar los huérfanos y recién entonces soltar el
resto.

---

## 4. El método: el patrón de la casa, con cuatro cambios

Se reusa lo que ya funcionó dos veces (los `Legacy*Seeder` de TrafoDex y el
`import:legacy-tests` de este repo), no se inventa uno nuevo.

**Se copia tal cual:**

1. **Volcado MySQL crudo versionado + parser propio en PHP.** No `mysql < dump.sql`.
   Corre sin MySQL instalado, da control del mapeo y es testeable. La disciplina
   que lo hace tolerante sin ser laxo: descartar toda tupla cuyo número de campos
   no coincida con el esperado.
2. **Propiedad pública `$file` inyectable**, para probar cada importador con un
   volcado sintético de tres filas. Cuesta una línea.
3. **Si falta el archivo, avisa y sigue.** Permite que el repo público no lleve
   los volcados y que `setup:project` siga corriendo.
4. **Todo en un `DatabaseSeeder`, en orden fijo**, terminando con
   `db:fix-sequences`.
5. **No corregir ningún número en la extracción.** Las anomalías se anotan con
   su gravedad dentro del propio archivo de datos. Corregir en el ETL hace
   imposible verificar la paridad después.
6. **Lo que el importador no puede decidir, lo lista y no lo inventa.**

**Cambia, y por qué:**

1. **Identidad por `legacy_id`, no por clave primaria.** TrafoDex conserva el id
   viejo como PK; este repo ya probó eso y lo revirtió, con razón: acoplar las
   claves del sistema nuevo a las de uno muerto obliga a consultar un mapa de
   2019 para entender una fila de 2028. Con `legacy_id` unsigned nullable único
   + `updateOrCreate`, el ETL pasa de "insertar lo que falta" a **sincronizar** —
   que es lo que hace falta para el delta (§8). Trampa ya pisada: `legacy_id = NULL`
   no empareja nada en SQL; las filas sin origen necesitan otra clave.
2. **Reportar los huérfanos, no contarlos.** Los seeders de TrafoDex descartan
   en silencio lo que no resuelve una FK y solo informan un total. Acá hace falta
   `--report=archivo.csv` con id de origen, motivo del descarte y los campos para
   buscarlo en el viejo. Con el vínculo bancada→muestra por texto, van a ser
   muchos.
3. **Un solo `LegacyDumpReader`, no el parser copiado 47 veces.** Y que **derive
   las columnas del `INSERT INTO ... (...)` del propio volcado** en vez de
   declararlas a mano: así un cambio de esquema se detecta en vez de tragarse.
4. **`--dry-run` en todos**, no solo en el de definiciones. Para un ETL de varias
   semanas de iteración es la diferencia entre probar una hipótesis en un minuto
   o en veinte.

---

## 5. Las trampas que rompen la importación

### 5.1 Los contadores no se enteran, y el año en curso reemite números

`sample_counters`, `reception_counters` y `report_counters` solo avanzan cuando
el asignador los toca. Insertar la historia por SQL los deja intactos: la primera
recepción confirmada después del arranque emite `2026-0002` y choca contra
`samples_codigo_unico` — o, si el número está libre por un hueco, **imprime una
etiqueta con un número que ya existe en un informe entregado**.

Corrección obligatoria, una sentencia por contador, con `MAX` y no `COUNT` (la
historia tiene huecos), creando la fila de los años que no existan:

```sql
UPDATE sample_counters c SET last_number = GREATEST(c.last_number, m.mx)
FROM (SELECT tenant_id, year, MAX(number) mx FROM samples GROUP BY 1,2) m
WHERE c.tenant_id IS NOT DISTINCT FROM m.tenant_id AND c.year = m.year;
```

### 5.2 El vínculo bancada → muestra es por texto, sin clave foránea

El número de muestra vive en tres lugares: `rem_correlatives.year_test` +
`num_test` (el autoritativo), `lab_details.num_test` (texto) y una celda "Nº de
Muestra" en `lab_sub_details` para cada una de las 29 pruebas. **No hay ninguna
FK entre ellos.** El sistema viejo reconstruye el vínculo partiendo el texto por
el guion en cada guardado:

```ruby
@num_year = @lab_detail.first.num_test.split('-')[0].strip
@num_test = @lab_detail.first.num_test.split('-').last.strip
```

Un espacio de más o un año mal tipeado rompe el vínculo en silencio. Que el
propio repo viejo tenga un `README_FIND_DUPLICATED_NUM_TESTS.md` —un cuaderno de
consultas SQL para cazar números duplicados a mano— prueba que el problema
existía y que nadie lo cerró.

**Etapa de conciliación explícita, con su informe**, antes de decidir qué hacer
con los huérfanos. Cuántos son es hoy una incógnita.

### 5.3 `tenant_id` no se autorrellena en consola, y falla en dos direcciones opuestas

Los traits `BelongsToTenant` y `BelongsToTenantOrGlobal` salen antes si no hay
usuario autenticado. Un importador por comando escribe `tenant_id = NULL` en todo
lo que no le pase explícito. Y el nulo significa lo contrario según la tabla:

- En `samples`, `receptions`, `worksheets`, `results`, `sample_tests` (trait
  estricto): la fila **queda invisible para todos los usuarios no-super**. El
  import "funciona" y el laboratorio no ve nada.
- En los catálogos (`analytes`, `instruments`, `standards`, `spec_sets`): la fila
  queda **global, visible para todos los workspaces**.

### 5.4 `slug` es NOT NULL en tablas cuyo modelo no lo genera

`receptions`, `samples`, `sample_reports`, `test_fields`, `test_methods`,
`standards`, `spec_sets` tienen `slug NOT NULL` y **ningún hook `creating`**: hoy
lo pone a mano el servicio que las crea. Un `Sample::create([...])` sin `slug`
explícito revienta.

### 5.5 `results` es derivada: no se importa, se reconstruye

El único índice único es `(worksheet_row_id, analyte_id, replicate_no)` y
`worksheet_row_id` admite nulos: en Postgres los nulos no chocan, así que N
corridas del importador escriben N copias. Peor, `lab:rebuild-results` recorre
**hojas de trabajo**, o sea que un resultado sin fila detrás **no se puede
regenerar**.

La única ruta segura: cargar `worksheets` + `worksheet_rows` + `worksheet_values`
y dejar que `lab:rebuild-results` escriba `results`. Con una condición: el
comando **solo procesa hojas en estado `validated`**; una hoja histórica
importada en `draft` no produce ni un resultado.

### 5.6 El cero que significa "no medido"

El sistema viejo escribía `0` donde no se midió. TrafoDex lo pagó caro: el motor
comparaba ese 0 contra el mínimo de la norma y marcaba **fuera de norma a
transformadores sanos**. En `fiquis` no había ni un solo nulo en rigidez: 0 de
7.476 filas. Y se arregló para un campo creyéndolo cerrado, mientras seguía vivo
en otros tres. Al anularlos aparecieron 626 ensayos con solo D877 y 104 con solo
el factor a 100 °C, que hasta entonces eran invisibles.

Acá la decisión va **parámetro por parámetro y antes de importar**, con recuento
y firmada por el laboratorio: para cada uno de los 58 analitos, cuántos ceros
hay, si el cero es físicamente posible, y qué se hace. En acidez, agua y tensión
interfacial el cero puede ser real. En una rigidez dieléctrica, no existe.

### 5.7 Efectos que hay que apagar, y trabajos que hay que correr

**Apagar durante la corrida:**

- `features.audit_log_enabled = false`. Es el único generador de volumen
  desbocado de la cadena: una fila de auditoría con el registro entero
  serializado por cada creación.
- No usar `SampleReportService::issue()` ni `ReceptionService::confirm()` para
  historia: reservan correlativos **nuevos** e ignoran el número viejo.
- No usar `WorksheetService::saveRow()` fila por fila: aplica reglas que la
  historia sucia no cumple y **publica sola** en cuanto la fila queda completa.

**Correr después, o el dato queda a medias:**

- `lab:rebuild-results` (§5.5).
- Los estados: sin `SampleProgressService`, `sample_tests.status`,
  `samples.status` y `receptions.status` quedan en su valor por omisión y todos
  los listados mienten. No hay comando de reproceso masivo; hay que escribirlo.
- `db:fix-sequences`, obligatorio tras cualquier inserción con ids explícitos.
- `lab:build-views` si el import trae plantillas nuevas.

**Y una tarea programada que hay que decidir antes del arranque:**
`worksheets:auto-lock` pone candado a toda hoja con fecha de más de cuatro
meses. La primera noche después del import, **toda la historia queda bloqueada
de golpe**. Puede ser exactamente lo que se quiere; hay que quererlo a propósito.

---

## 6. Lo que hay que inventar, y marcado como tal

Ningún importador debe fabricar un dato en silencio. Lo que el destino exige y
el origen probablemente no tenga:

| Campo | Qué poner | ¿Se inventa? |
|---|---|---|
| `tenant_id` | `1` (el laboratorio) | No. El inquilino es una dimensión nueva sin equivalente en un sistema de un solo laboratorio. |
| `slug` | aleatorio de 22 | No. Identificador de URL sin significado. |
| `created_by` | un usuario **"Migración"** creado para esto | Sí. Por eso conviene que el valor diga qué es, en vez de reusar el usuario de la API. |
| `receptions.received_at` | la fecha de entrega; si no, la de la primera muestra | Con `now()` sí, y con consecuencias: el año de esa fecha define el ejercicio del correlativo. |
| `samples.reception_id` | toda muestra cuelga de una recepción | **La invención más grande de la migración.** Si el viejo tiene muestras sueltas hay que fabricar una recepción contenedora, y tiene que quedar reconocible en el `code`. |
| `samples.year/number` | del correlativo viejo | Sí, y es peligroso: un número inventado es indistinguible de uno real. **Preferible dejar la muestra afuera y listarla.** |
| `worksheets.run_date` | la fecha del ensayo | Sí, y falsea el veredicto: es la fecha que decide qué cuadro de límites aplica. |
| `validated_by` / `validated_at` | del viejo, y si no, **nulo** | Dejarlo nulo es lo honesto. Decir que alguien revisó lo que no revisó es peor que no decir nada. |
| `oil_type_id` | correspondencia explícita | Sin él no se resuelve el cuadro y `spec_status` queda nulo — que el informe declara como "sin criterio", **nunca como "cumple"**. Elegir "mineral por omisión" sería inventar un criterio de aceptación. |
| `sample_reports.verify_code` | **nulo** para historia | Fabricarlo falsifica una garantía: el portal valida contra el registro de auditoría, y un código sin respaldo promete una verificación que no existe. |
| `qualifier` (`>`/`<`) | del texto viejo | Si el viejo ya limpió el signo antes de convertir, el matiz se perdió y **no se puede reconstruir**. Hay que decirlo, no rellenarlo. |

Y una regla de contraste que ya está en el código y aplica igual acá: **un cuadro
vacío no es un cuadro ausente**, y "sin reglas" no es "cumple". Un aceite sin
cuadro de límites hacía que TrafoDex mostrara "100 Excelente" ocultando una
muestra que el IEEE C57.104 marca peligrosa. Está calificado en su repositorio
como bug de seguridad.

---

## 7. Cómo se demuestra que salió bien

La migración de TrafoDex verificó la **fidelidad del cálculo** y nunca la
**completitud de la carga**. Acá hacen falta las dos.

### 7.1 Paridad de reglas — el método a copiar

`verify:legacy` de TrafoDex compara el resultado calculado, no los datos: el
índice de salud que el viejo dejó cacheado contra lo que produce el motor nuevo.
Tres decisiones suyas valen más que el comando entero:

1. **El valor viejo se lee siempre del volcado, nunca de la base**, reusando el
   parser del propio importador. Si se leyera de la base, la verificación estaría
   comparando el motor nuevo contra sí mismo.
2. **Segmentar la población antes de calcular el porcentaje**, con una categoría
   por causa y su justificación escrita en el código: nunca diagnosticado,
   borrado, parcial por diseño, snapshot desactualizado, discrepancia real. Es la
   diferencia entre "91,6 % de paridad" y "el motor falla en el 60 % de los
   casos" — la misma corrida, distinta honestidad estadística.
3. **Dos métricas, no una**: el valor numérico con tolerancia y la clasificación
   con coincidencia exacta. Acá el par natural es *valor del resultado* (con
   tolerancia por decimales del parámetro) y *`spec_status`* (dentro / cerca /
   fuera de norma), exacto.

Y el criterio de cierre, que también se copia: **el sistema viejo es el punto de
partida de la comparación, no el árbitro. El árbitro es la norma.** En TrafoDex,
de las 10 discrepancias finales que cambiaban el semáforo, en las tres agrupadas
por patrón ganó el motor nuevo: el viejo daba "Muy Bueno" a un 220 kV con
rigidez de 33 kV, crítica según IEEE C57.106.

### 7.2 El equivalente acá: `lab:diff-viejo`, ya construido

Este repo no tiene un índice de salud escalar que comparar, y no lo va a tener.
El sustituto ya existe: `lab:diff-viejo` compara **parámetro impreso por
parámetro impreso**, y su referencia no se escribió para el comando — **es la
misma constante que dibuja el papel viejo**. Su cabecera explica por qué
`report:compare` no alcanzaba: *"si una fila falta en los dos, se ven igual de
vacíos y nadie lo nota. Los cuatro defectos que aparecieron el 2026-08-02 se
encontraron TODOS a mano, mirando. Ninguno habría aparecido en una comparación
de PDF contra PDF."*

Lo que falta es alimentarlo con las muestras históricas migradas.

### 7.3 Paridad del motor de límites

`rem_report_details.*_ori` guarda **el criterio con el que salió cada informe
emitido**. Compararlo contra lo que produce `SpecSetResolver` + `SpecEvaluator`
para la misma muestra y la misma fecha es la prueba del motor de límites, y usa
un dato que ya está en el origen.

### 7.4 Reconciliación por recuento

Filas por año, por cliente y por prueba, viejo contra nuevo, con el detalle de lo
que no cuadra. Es lo que TrafoDex no hizo. **Es la puerta de salida de la fase**:
informe de reconciliación aprobado por el laboratorio, con la lista explícita de
lo que no se pudo migrar y por qué.

---

## 8. El delta y el corte

Los volcados con los que TrafoDex se cargó son del **2026-06-07**, y llegan hasta
~2026-05-26 en cromatografías y ~2026-05-21 en fisicoquímicos y furanos. Todo lo
que el asistente de labapp haya insertado en trapp después de esa fecha está en
el trapp viejo y **no en TrafoDex**. Ese delta hoy no está cuantificado.

El patrón de TrafoDex resolvió la carga inicial y **no** resolvió el delta. Por
eso la identidad por `legacy_id` con `updateOrCreate` (§4) no es una preferencia
estética: es lo que permite volver a correr el ETL sobre datos nuevos sin
duplicar.

### El riesgo más serio del plan

`rem_report_details` del laboratorio es **el original**; las 25.138 muestras de
TrafoDex son una **copia parcial** (el asistente viejo solo cruzaba lo que tenía
equipo ya existente en trapp y valor ≥ 0). Si LaboRep arranca reprocesando su
cola de salida hacia la API de TrafoDex sin una marca de "esto ya se envió",
**reenvía todo**. Y las redes de seguridad de TrafoDex no lo frenan:

- La clave de idempotencia es **por petición**: un reenvío lleva claves nuevas.
- El índice único de número de informe **deja fuera a las históricas**, que
  tienen ese campo en nulo a propósito.
- Lo único que quedaría es la regla "una muestra por fecha y prueba", efectiva
  solo mientras las fechas coincidan exactamente. Y ya se sabe que no siempre
  coinciden: los duplicados que TrafoDex tuvo que limpiar eran la misma muestra a
  `00:00` y a `05:00`, artefacto de la conversión a UTC de un sistema en UTC-5.

**Hace falta una marca explícita en LaboRep de qué ya se envió**, y la referencia
natural es la fecha de corte del volcado.

### La API no sirve para cargar el histórico

Está diseñada para el flujo nuevo y hay cinco límites que se acumulan: caudal de
60 pedidos por minuto; `Idempotency-Key` obligatoria por petición; el diagnóstico
corre en **cada** llamada (recalcular el índice del mismo transformador una vez
por muestra vieja); el número de informe choca dentro del mismo envío; y los
datos históricos no tienen lo que la API exige (subestación, y 17 de los 20 tipos
de equipo dan 422 a propósito).

El histórico se migra como ya se migró lo de trapp: volcados y seeders
idempotentes, sin red de por medio.

---

## 9. Lo que falta antes de escribir la primera línea de código

### 9.1 Datos que solo salen de la base de producción

1. **El censo.** `SELECT COUNT(*), MIN(created_at), MAX(created_at)` por cada una
   de las 21 tablas operativas. Hoy no hay ni un conteo real de `rems`,
   `rem_correlatives`, `rem_jobs`, `labs`, `lab_details`, `lab_sub_details`.
   Las cifras que circulan en `docs/` no están respaldadas: el "más de 2.400
   equipos" no tiene fuente citada y es sospechosamente cercano a los 2.562 de
   TrafoDex; las "4.000 muestras al año" son un supuesto de dimensionamiento del
   banco de pruebas, nunca una medición.
2. **Si existen datos anteriores a abril de 2023.** Todos los catálogos del
   volcado nacen el 2023-04-07. No se puede distinguir si es la fecha real de
   arranque o la de una resiembra sobre datos más antiguos.
3. **Cuántos `lab_details` quedan huérfanos** al conciliar `num_test` contra
   `rem_correlatives` (§5.2). Hay que resolverlo **antes** de decidir la
   estrategia de la bancada, no después.
4. **Cuántas series de transformador están repetidas**, que es lo que decide si
   el emparejamiento con TrafoDex se puede automatizar o necesita una persona.
5. **El tamaño del delta post 2026-06-07** (§8).
6. **El volcado con datos de `rem_report_details`.** Está previsto en
   `.gitignore` y no se consiguió. Sin él no se puede estimar el trabajo real de
   la transposición ni cuántos huérfanos va a haber.

### 9.2 Decisiones del laboratorio

1. **El cero, parámetro por parámetro** (§5.6). Entregable: tabla con analito,
   cantidad de ceros, si el cero es físicamente posible, y decisión. Firmada.
2. **El número de informe.** ¿`sample_reports.code` conserva el formato viejo, o
   adopta `REP-LAB-AAAA-NNNN` y el cliente deja de encontrar el número impreso en
   papeles ya entregados? No es una decisión técnica.
3. **Las hojas históricas, ¿`validated` o `draft`?** De eso depende si producen
   resultados (§5.5) y si el candado automático las cierra la primera noche
   (§5.7).
4. **El alta de clientes después del corte.** La API de TrafoDex **no expone la
   jerarquía** cliente → sede → área → subestación: asume que ya existen, puestos
   por alguien más. Hoy los pone el laboratorio. Es un hueco funcional sin
   resolver, y no lo cubre este plan.
5. **Las tres preguntas del plan maestro que siguen abiertas** (el gap de
   rigidez histórico, si los aceites 2/3/8/9 son alias o fluidos propios, si
   "IEC 610203-2025" es errata). La primera afecta directamente a esta
   migración.

### 9.3 Una cuestión ajena a la migración, pero que la toca

`database/seeders/data/customers.csv` está versionado en este repositorio
**público** y contiene 344 razones sociales reales con dirección y código. Junto
con `customer_locations.csv` (843), `customer_areas.csv` (1.940) y
`customer_substations.csv` (1.368). Vinieron heredados de TrafoDex, que es
privado, en el commit inicial.

El criterio escrito en la cabecera de `esquema/catalogos-definiciones.sql`
excluye "equipos y muestras de clientes reales" y enumera tablas de muestras y
equipos, pero no la de clientes. **Hoy el criterio escrito y el contenido del
repositorio no dicen lo mismo.** Sea cual sea la respuesta, tiene que quedar
anotada ahí junto al resto del criterio, antes de que esta migración agregue una
sola fila más de origen comercial.

---

## 10. Resumen de una página

- La frontera con trapp es de **quince** tablas, no cinco: diez están escondidas
  detrás de una clase base.
- **Clientes y jerarquía: compartidos**, ya cargados, no se migran. **Equipos:
  del laboratorio**, se migran de ahí porque TrafoDex tiene la copia aplanada.
- **Por módulo, no por año**, con una prueba de humo sobre el año más chico.
- El método es el de la casa, con `legacy_id` en vez de clave primaria heredada,
  huérfanos reportados en vez de contados, un solo lector de volcados y
  `--dry-run` en todo.
- Seis trampas conocidas, todas con corrección escrita: contadores, vínculo por
  texto, `tenant_id` en consola, `slug` sin hook, `results` derivada, el cero.
- Se demuestra con paridad de reglas segmentada, `lab:diff-viejo` alimentado con
  la historia, paridad del motor de límites contra `*_ori`, y reconciliación por
  recuento. Las dos primeras se copian de TrafoDex; la última es la que allá
  faltó.
- Antes de escribir código hacen falta seis números que solo salen de la base de
  producción y cinco decisiones que solo puede tomar el laboratorio.
