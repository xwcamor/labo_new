# Auditoría B — Fórmulas y cálculos de columnas

**Área**: fórmulas y cálculos de columnas.
**Sistema viejo**: `/home/user/labo_old` (Ruby on Rails 7.0, base MySQL `lab_app_development`). Solo lectura.
**Sistema nuevo**: `/workspace/labo_new` (Laravel 13 + Inertia + Vue 3, Postgres).
**Fecha**: 2026-07-30.

---

## 1. Alcance y fuentes

Se inventarió TODO cálculo numérico del sistema viejo que produce el valor de una
columna, de un total del informe o de un dato derivado, y se verificó uno por uno
contra el sistema nuevo.

### Fuentes consultadas en el viejo

| Fuente | Qué aportó |
|---|---|
| `db/seeds.rb:151-176` | `LabCategoryDetail` con `blur_calculation`. Snapshot ANTIGUO: solo 3 fórmulas. |
| `db/seeds.rb:186-343` | `LabCategorySubDetail`: las columnas y su `num_pos`, necesarias para resolver los `colN`. |
| `db/schema.rb:70-81` | La columna `blur_calculation` es `longtext`. |
| `db/migrate/20230126051409_create_lab_category_details.rb:8` | Su creación (`t.longtext :blur_calculation`). |
| `db/seeds2.rb:151-177` | Segundo juego de semillas: los mismos 26 registros SIN la columna `blur_calculation`. Cero fórmulas. |
| `app/views/pr_management/templates/lab_details/partials/_calculation_script.html.erb` | La inyección con `html_safe`. |
| `app/views/pr_management/templates/lab_details/partials/_form_new_nested.erb` | Cómo se numeraban los `colN`. |
| `app/views/pr_management/templates/lab_details/partials/_form_new_nested_poli.html.erb` | Las sub-lecturas `colN-M` del Grado de Polimerización. |
| `app/views/pr_management/configurations/category_details/partials/_form_new.html.erb:28` | El `textarea` rotulado "Fórmula de Programación" donde se pegaba el JavaScript. |
| `app/views/im_management/rem_reports/partials/_report_cromas.erb:305-460` | Totales, relaciones y porcentajes de gases del informe. |
| `app/views/im_management/rem_reports/partials/_report_physicals.erb` | Reglas de redondeo y de límite de detección de los fisicoquímicos. |
| `app/views/im_management/rem_reports/partials/_report_furanos.erb:35,51,67,83,99` | Límite de detección de furanos (10 ppb). |
| `app/views/im_management/rem_reports/partials/_form_add_details_cromas_default_values.html.erb:1` | Conteo de gases fuera de norma. |
| `app/models/rem_report_detail.rb:191,393,397` | `num_ten` / `num_pot` → máximo del texto separado por barras. |
| `app/models/lab_sub_detail.rb:16-17` | Las N lecturas guardadas concatenadas con `/`. |
| `app/models/lab_file_detail.rb:24-26` | Parche SQL de furanos (`substring_index`). |
| `app/views/pr_management/templates/imports/partials/_form_show.html.erb:12-23` | El "parser" del archivo del instrumento (regex sobre texto). |
| `app/views/pr_management/templates/patron_tendences/partials/_form_edit.html.erb` | LAS/LAI/LC/LCI/LCS de la carta de control: campos de texto, sin fórmula. |
| `app/assets/javascripts/*.js` (110 líneas en total) | Revisados: NO contienen ningún cálculo de laboratorio (solo `trim`, `numbersOnly`, clonado de campos, toggles de paneles). |
| `app/helpers/application_helper.rb`, `app/models/*.rb` | Barrido de aritmética: el único `sum` del proyecto está en `app/models/stock_detail.rb:30,36` y es de almacén, no de laboratorio. |
| `1.txt_examples/HITACHI/DPA 75C - 1101908010/*.txt` | Protocolo del espinterómetro: trae las 5 mediciones, el valor medio, el desvío estándar y el CV%, ya calculados por el instrumento. |

### No hay `.sql` en `db/` del viejo

`find /home/user/labo_old -name "*.sql"` no devuelve nada: el repositorio del
viejo no versiona ningún volcado. El `db/seeds.rb` es un snapshot de 2023 con
**solo 3** fórmulas.

La fuente autorizada del estado de producción es el volcado de definiciones que
el sistema nuevo sí versiona:
`docs/migracion/esquema/catalogos-definiciones.sql`, líneas 548-606
(`INSERT INTO lab_category_details`, 29 filas) y 612+
(`INSERT INTO lab_category_sub_details`, 208 filas). Ahí hay **8 pruebas con
`blur_calculation` no vacío** (ids 1, 6, 10, 12, 16, 22, 28, 29), no 3. Todo lo
que sigue se coteja contra ese volcado, y se indica cuando el `seeds.rb` difiere.

---

## 2. Cómo se resolvían los `colN` y por qué era frágil

El formulario renderizaba las columnas de la prueba en un bucle y el `id` del
input era **el índice de la iteración**, no el identificador de la columna:

```erb
<% @lab_category_sub_details.each_with_index do |array,index| %>
  <input class="form-control text-black" id="col<%= index + 1 %>" type="text" ...
```
`app/views/pr_management/templates/lab_details/partials/_form_new_nested.erb:11,31`

Y la colección venía ordenada por posición:

```ruby
@lab_category_sub_details = LabCategorySubDetail
  .where("deleted= 0 AND lab_category_detail_id = ?", params[:lab_category_detail_id])
  .order("num_pos ASC")
```
`app/controllers/pr_management/templates/lab_details_controller.rb:160`
(idéntico en `admin_templates/lab_details_controller.rb:129`,
`templates/labs_controller.rb:217`, `admin_templates/labs_controller.rb:189`)

O sea: **`col5` significaba "la quinta columna visible de esta prueba"**. No el
campo "Factor KOH", ni la fila 5 de la tabla: la quinta por `num_pos` entre las
no borradas. De ahí salen cinco fragilidades, todas verificables:

1. **Insertar una columna en el medio corría todo.** Está documentado en la
   propia base: la `description` de la prueba 10 dice
   *"Si se cambia la posicion de las columnas que se usen calculos se tiene que
   cambiar la formula de los campos"*
   (`catalogos-definiciones.sql:567`). Y ocurrió de verdad: en el snapshot
   `seeds.rb:156` el Contenido de Agua promediaba `col4` y `col5`; en producción
   promedia `col5` y `col7`, porque se insertaron dos columnas de selección
   ("R1 PP-LA-01C" en `num_pos` 4 y "R2 PP-LA-01C" en 6) y hubo que reescribir
   el JavaScript a mano.
2. **Borrar una columna también corría todo**, en silencio: el `where` filtra
   `deleted = 0`, así que un borrado lógico reindexa los `colN` posteriores.
3. **Los `<select>` consumían un índice sin emitir un `id`.** El bloque de
   selección no imprime `id="colN"`
   (`_form_new_nested.erb:52-57`), así que `document.getElementById('col2')`
   devolvía `null` para la columna "Norma" y cualquier fórmula que la tocara
   lanzaba `TypeError` y abortaba el resto del bloque.
4. **El servidor no recalculaba ni validaba nada.** El resultado llegaba en el
   POST como cualquier otro campo. Un POST directo escribía lo que quisiera en
   la columna de resultado.
5. **`NaN` quedaba guardado como texto en la base.** El `parseFloat` de un campo
   vacío daba `NaN`, `toFixed` lo volvía la cadena `"NaN"` y se persistía. El
   informe tenía código dedicado a cazarlo y bloquear el guardado:
   ```erb
   <% if @lab_sub_detail.name == "NaN"%>
     ... Revisar Muestra
     <% @aci_block_save = "bloquear" %>
   ```
   `app/views/im_management/rem_reports/partials/_form_add_details_physicals.html.erb:42-45`.
   El repositorio incluso trae un `README_FIND_DUPLICATED_NUM_TESTS.md` para
   rastrear registros con problemas.

Encima, **el consumo también era posicional**: el informe tomaba el resultado de
cada prueba con `LabCategorySubDetail.where("lab_category_detail_id= 1").order("num_pos ASC").last`
(`_form_add_details_physicals.html.erb:36`, y lo mismo en las líneas 92, 136,
180, 224, 268, 312, 360, 404, 448, 480, 525, 557) — es decir, "la última
columna", con el comentario `<!-- Busca el ultimo id de que llame RESULTADO -->`.
Ese `where` **no filtra `deleted=0`**, así que una columna borrada al final
devolvía un resultado inexistente. La gráfica de tendencia hace lo mismo
(`tendences_controller.rb:19`).

Y donde no era posicional, era por id clavado a mano:
`LabSubDetail.find_by(..., lab_category_sub_detail_id: 61)` para el hidrógeno,
62 para el oxígeno… hasta 71
(`_form_add_details_cromas.html.erb:33,86,...`; `tendences_controller.rb:25-33`;
`lab_file_detail.rb:26` con `IN (80,81,82,83,84)` y el comentario
`#DONT MOVE FURANOS COLUMN ORDER`).

### Cómo lo resuelve el nuevo

La fórmula nombra la columna por su **código** (`volumen_gastado_ml`), se analiza
con un parser propio y la evalúa el servidor:

- `app/Services/Lab/FormulaParser.php` — tokenizador + shunting-yard a RPN.
  Lista CERRADA de funciones (`abs, round, min, max, sqrt, log10, ln, exp, pow,
  avg, sum`), topes de largo/tokens/profundidad, y cualquier carácter fuera de
  la aritmética corta con `FormulaException`. No hay `eval` en ninguna parte.
- `app/Services/Lab/FormulaEvaluator.php` — nunca lanza por datos: falta de
  dato, división por cero y dominio inválido devuelven `null` en vez de `NaN`.
  `toNumber()` descarta booleanos y texto no numérico.
- `app/Services/Lab/FormulaResolver.php` — orden **topológico** (Kahn), no de
  pantalla; detecta ciclos y los excluye.
- `app/Services/Lab/FormulaValidator.php` + `TestFieldController::checkFormula`
  (`app/Http/Controllers/LabManagement/TestFieldController.php:225`, ruta en
  `routes/lab_management.php:196`) — valida contra los códigos reales de esa
  prueba, en vivo, mientras el supervisor escribe.
- El código de columna se fuerza a identificador válido:
  `regex:/^[a-z][a-z0-9_]*$/` en la validación
  (`TestFieldController.php:264`) y el importador mueve el número inicial al
  final (`2-Furfuraldehído` → `furfuraldehido_2`,
  `app/Console/Commands/ImportLegacyTestsCommand.php:426-437`).

---

## 3. Tabla A — Fórmulas de columna (`blur_calculation`)

Las 8 pruebas con `blur_calculation` no vacío suman **36 asignaciones de
columna**: 22 numéricas, 13 concatenaciones de texto y 1 reescritura del propio
insumo. Se listan todas.

Referencia de líneas del volcado: prueba 1 → `catalogos-definiciones.sql:549`;
6 → `:559`; 10 → `:567`; 12 → `:571`; 16 → `:579`; 22 → `:591`; 28 → `:603`;
29 → `:605`.

### A.1 — Número Ácido (prueba id 1)

Columnas por `num_pos`: 1 Nº de Muestra · 2 Norma · 3 Bureta · 4 Balanza ·
**5 Factor KOH** · **6 Vol Blanco** · **7 Peso aceite (g)** ·
**8 Volumen gastado (mL)** · **9 Resultado (mgKOH/g aceite)**.

| # | Columna calculada | JS original | Notación matemática | Fórmula en el nuevo | ¿Coinciden? |
|---|---|---|---|---|---|
| A1 | `col9` Resultado (mgKOH/g aceite) | `var result = (col8-col6)*col5/col7; document.getElementById('col9').value = result.toFixed(3);` | `(V_gastado − V_blanco) · F_KOH / m_aceite`, 3 decimales | `(volumen_gastado_ml - vol_blanco) * factor_koh / peso_aceite_g`, `decimals=3` | **SÍ**, idéntica |

Evidencia nuevo: `database/seeders/data/test_formulas.json` →
`numero_acido.resultado_mgkohg_aceite`; verificado en base
(`type=computed`, `is_locked=1`, `decimals=3`). Prueba:
`tests/Unit/Lab/FormulaEvaluatorTest.php:29`.

### A.2 — Contenido de Agua (prueba id 6)

Columnas: 1 Nº de Muestra · 2 Norma · 3 Balanza · 4 R1 PP-LA-01C (selección) ·
**5 R1** · 6 R2 PP-LA-01C (selección) · **7 R2** · **8 Repetibilidad** ·
**9 Resultado ppm**.

| # | Columna calculada | JS original | Notación matemática | Fórmula en el nuevo | ¿Coinciden? |
|---|---|---|---|---|---|
| A2 | `col8` Repetibilidad | `var repe = col5-col7; document.getElementById('col8').value = Math.abs(repe).toFixed(1);` | `\|R1 − R2\|`, 1 decimal | `abs(r1 - r2)`, `decimals=1` | **SÍ** |
| A3 | `col9` Resultado ppm | `var promedio = (col5+col7)/2; document.getElementById('col9').value = promedio.toFixed(0);` | `(R1 + R2) / 2`, 0 decimales | `(r1 + r2) / 2`, `decimals=0` | **SÍ** |

Nota: en el viejo `R1` y `R2` estaban declaradas de **tipo TEXTO**
(`lab_category_sub_detail_type_id = 1`) y el JavaScript las leía con
`parseFloat`. El nuevo las corrige a numéricas al sembrar
(`test_formulas.json` → `entradas_numericas`), porque en el nuevo el tipo decide en qué
columna cae el dato.

**Diferencia respecto del `seeds.rb`**: allí (línea 156) la misma fórmula usaba
`col4`/`col5` y escribía en `col6`/`col7`, y además el orden de las dos columnas
de salida estaba invertido (Resultado en `num_pos` 6, Repetibilidad en 7). Es la
prueba directa de la fragilidad posicional: no cambió la química, cambió la
posición de las columnas.

### A.3 — Análisis Cromatográfico (prueba id 10)

Columnas: 1 Nº de Muestra · 2 Norma · **3 H2** · **4 O2** · **5 N2** · **6 CH4** ·
**7 CO** · **8 CO2** · **9 C2H4** · **10 C2H6** · **11 C2H2** ·
**12 Total de Gases Combustibles** · **13 Total**.

| # | Columna calculada | JS original | Notación matemática | Fórmula en el nuevo | ¿Coinciden? |
|---|---|---|---|---|---|
| A4 | `col12` Total de Gases Combustibles | `var comresult = col3+col6+col7+col9+col10+col11; ... col12 = comresult.toFixed(2)` | `H2 + CH4 + CO + C2H4 + C2H6 + C2H2` (TDCG), 2 decimales | `hidrogeno_h2_ppm + metano_ch4_ppm + mcarbono_co_ppm + etileno_c2h4_ppm + etano_c2h6_ppm + acetileno_c2h2_ppm`, `decimals=2` | **SÍ** |
| A5 | `col13` Total | `var result = col3+col4+col5+col6+col7+col8+col9+col10+col11; ... col13 = result.toFixed(2)` | Suma de los 9 gases, 2 decimales | Suma de los 9 códigos, `decimals=2` | **SÍ** |

Se verificó término por término: A4 deja fuera O2 (`col4`), N2 (`col5`) y CO2
(`col8`), que no son combustibles — es la definición de IEEE C57.104. El nuevo
respeta exactamente esa exclusión.

### A.4 — Furanos (prueba id 12)

Columnas: 1 Nº de Muestra · 2 Norma · 3 Equipo · **4 2-Furfuraldehído** ·
5 5-HMF · 6 2-Acetilfurano · 7 5-Metil-2-FAL · 8 2-Furfuril Alcohol ·
**9 Grado de Polimerización**.

| # | Columna calculada | JS original | Notación matemática | Fórmula en el nuevo | ¿Coinciden? |
|---|---|---|---|---|---|
| A6 | `col9` Grado de Polimerización | `var fal_ppm = col4 / 1000; var log_fal = Math.log10(fal_ppm); var shen_numerator = 1.51 - log_fal; var shen_denominator = 0.0035; var result = shen_numerator / shen_denominator; ... col9 = result.toFixed(0)` — el propio JS deja el comentario `//=(1.51-LOG10(2FAL/1000))/0.0035` | `DP = (1,51 − log₁₀(2FAL[ppb] / 1000)) / 0,0035`, 0 decimales (Chendong) | `(1.51 - log10(furfuraldehido_2 / 1000)) / 0.0035`, `decimals=0` | **SÍ**, idéntica |
| A7 | `col4` 2-Furfuraldehído (el propio insumo) | `if (!isNaN(col4) && col4 % 1 !== 0) { col4Input.value = col4.toFixed(3); }` | Reescribe la lectura cruda del analista a 3 decimales, en el sitio | **FALTA** — no se replica. `furanos.furfuraldehido_2` tiene `decimals = NULL` (verificado en base) | **NO** — ver Hueco H4 |

El `log10` de un valor ≤ 0 devuelve `null` en el nuevo (no `-Infinity`):
`FormulaEvaluator::call()`, con el comentario explícito de que 2FAL en 0 ppb
significa "no se detectó furano", no un DP. Prueba:
`tests/Unit/Lab/FormulaEvaluatorTest.php:59-80`.

### A.5 — Grado de Polimerización del papel (prueba id 16) — **EL HUECO GRANDE**

Es el bloque de JavaScript más largo del viejo: 16 columnas, y las columnas 3-8
partidas en sub-lecturas (`col3-1`, `col5-4`…) porque el formulario emitía 2 o 4
inputs por columna según un **rango de índices escrito en el HTML**:

```erb
<% if index > 1 && index < 4 %>   <!-- COLUMNA 3 y 4-->  <% 2.times do |time_str| %>
<% elsif index > 3 && index < 8 %> <!-- COLUMNA 5 y 8-->  <% 4.times do |time_str| %>
<% elsif index > 7 && index < 15 %><!-- COLUMNA 9 y 15--> <% 2.times do |time_str| %>
```
`app/views/pr_management/templates/lab_details/partials/_form_new_nested_poli.html.erb:6,32,63`

Las N lecturas se guardaban **concatenadas en un solo texto separado por `/`** y
la vista las partía para mostrarlas
(`app/models/lab_sub_detail.rb:16-17`: `name.gsub('/', '<br><br>')`).

Columnas: 1 Nº de Muestra · 2 Norma · **3 Masa (g)** ×2 ·
**4 Contenido de Agua (%)** ×2 · **5 Tiempo muestra (s)** ×4 ·
**6 Constante viscosímetro muestra** ×4 · **7 Tiempo Blanco** ×4 ·
**8 Constante viscosímetro blanco** ×4 · **9 Viscosidad de muestra (T)** ×2 ·
**10 Viscosidad de Solvente (T0)** ×2 · **11 Concentración muestra (g/100mL)** ×2 ·
**12 Viscosidad específica (ηs)** ×2 · **13 K de Martin** ×2 ·
**14 Viscosidad Intrínseca (η)** ×2 · **15 Grado de polimerización** ×2 ·
**16 Promedio**.

| # | Columna calculada | JS original | Notación matemática | Fórmula en el nuevo | ¿Coinciden? |
|---|---|---|---|---|---|
| A8 | `col9-1` Viscosidad de muestra, réplica 1 | `var result7 = ( (col51*col61) + (col52*col62) )/2;` 2 dec | `T₁ = (t₁·C₁ + t₂·C₂) / 2` | — | **FALTA** |
| A9 | `col9-2` Viscosidad de muestra, réplica 2 | `var result8 = ( (col53*col63) + (col54*col64) )/2;` 2 dec | `T₂ = (t₃·C₃ + t₄·C₄) / 2` | — | **FALTA** |
| A10 | `col10-1` Viscosidad de solvente, réplica 1 | `var result9 = ( (col71*col81) + (col72*col82) )/2;` 2 dec | `T0₁ = (tb₁·Cb₁ + tb₂·Cb₂) / 2` | — | **FALTA** |
| A11 | `col10-2` Viscosidad de solvente, réplica 2 | `var result10 = ( (col73*col83) + (col74*col84) )/2;` 2 dec | `T0₂ = (tb₃·Cb₃ + tb₄·Cb₄) / 2` | — | **FALTA** |
| A12 | `col11-1` Concentración, réplica 1 | `var result11 = col31*100/(45*( 1+(col41/100) ));` 2 dec | `C₁ = m₁·100 / (45 · (1 + H₁/100))` — 45 mL de solvente, corregido por la humedad del papel | — | **FALTA** |
| A13 | `col11-2` Concentración, réplica 2 | `var result12 = col32*100/(45*( 1+(col42/100) ));` 2 dec | `C₂ = m₂·100 / (45 · (1 + H₂/100))` | — | **FALTA** |
| A14 | `col12-1` Viscosidad específica, réplica 1 | `var result13 = (result7-result9)/result9;` 2 dec | `ηs₁ = (T₁ − T0₁) / T0₁` | — | **FALTA** |
| A15 | `col12-2` Viscosidad específica, réplica 2 | `var result14 = (result8-result10)/result10;` 2 dec | `ηs₂ = (T₂ − T0₂) / T0₂` | — | **FALTA** |
| A16 | `col14-1` Viscosidad intrínseca, réplica 1 | `var result15 = col131/result11;` 2 dec | `[η]₁ = K_Martin₁ / C₁` | — | **FALTA** (y **revisar**: ver Hueco H1, nota normativa) |
| A17 | `col14-2` Viscosidad intrínseca, réplica 2 | `var result16 = col132/result12;` 2 dec | `[η]₂ = K_Martin₂ / C₂` | — | **FALTA** |
| A18 | `col15-1` Grado de polimerización, réplica 1 | `var result17 = (result15/0.0075);` 2 dec | `DP₁ = [η]₁ / 0,0075` (Mark-Houwink con k = 7,5·10⁻³, α = 1; el criterio de ASTM D4243) | — | **FALTA** |
| A19 | `col15-2` Grado de polimerización, réplica 2 | `var result18 = (result16/0.0075);` 2 dec | `DP₂ = [η]₂ / 0,0075` | — | **FALTA** |
| A20 | `col16` Promedio | `var resultcol16 = (result17 + result18)/2;` 2 dec | `DP = (DP₁ + DP₂) / 2` | — | **FALTA** |
| A21-A33 | `col3`,`col4`,`col5`,`col6`,`col7`,`col8`,`col9`,`col10`,`col11`,`col12`,`col13`,`col14`,`col15` | `var result1 = col31 + "/" + col32; document.getElementById('col3').value = result1;` (y 12 más iguales) | Concatenación de las N lecturas en un solo texto con `/` | **NO APLICA** — el nuevo guarda cada lectura como fila propia (`worksheet_values.replicate_no` + `value_num`) y no necesita la cadena | **No corresponde portar** |

Estado en el nuevo: la prueba **existe con sus 16 columnas y las réplicas
correctas** (verificado en base: `masa_g` 2, `contenido_de_agua_en` 2,
`tiempo_muestra_s` 4, `constante_viscosimetro_muestra` 4, `tiempo_blanco` 4,
`constante_viscosimetro_blanco` 4, y 2 en las derivadas), sembradas desde
`database/seeders/data/test_replicates.json`. Pero **las 13 fórmulas están sin
portar**, declarado a propósito en
`database/seeders/data/test_formulas.json` → `pendientes.grado_de_polimerizacion`:
el motor evalúa cada réplica por separado y una fórmula no puede referirse a la
sub-lectura de otra réplica (A8 cruza las lecturas 1 y 2; A9 las 3 y 4).

### A.6 — Sedimentos (prueba id 22)

Columnas: 1 Nº de Muestra · 2 Norma · **3 Sedimentos orgánicos** ·
**4 Sedimentos inorgánicos** · **5 Lodos Solubles** · **6 Total de Sedimentos**.

| # | Columna calculada | JS original | Notación matemática | Fórmula en el nuevo | ¿Coinciden? |
|---|---|---|---|---|---|
| A34 | `col6` Total de Sedimentos | `var col5 = parseFloat(...'col5'...); var result = col3+col4; ... col6 = result.toFixed(3)` | `orgánicos + inorgánicos`, 3 decimales. **`col5` (lodos solubles) se lee y NO se usa** | `sedimentos_organicos + sedimentos_inorganicos`, `decimals=3` | **SÍ**, fiel al viejo (lodos excluidos a propósito) |

El nuevo documenta la exclusión y deja anotado que si el laboratorio quiere
sumar los lodos es agregar un término al JSON, sin tocar código.

### A.7 — Resistividad Volumétrica 25 °C y 100 °C (pruebas id 28 y 29)

Columnas (idénticas en ambas): 1 Nº de Muestra · 2 Norma · 3 Tipo de Equipo ·
4 Temperatura (ºC) · **5 Rho+ (Ωcm)** · **6 Rho− (Ωcm)** ·
**7 Resultado (Ωcm)**.

| # | Columna calculada | JS original | Notación matemática | Fórmula en el nuevo | ¿Coinciden? |
|---|---|---|---|---|---|
| A35 | `col7` Resultado (Ωcm), 25 °C | `var result = (col5+col6)/2; document.getElementById('col7').value = result.toExponential(2).toUpperCase();` | `(ρ⁺ + ρ⁻) / 2`, presentado en notación exponencial con 2 decimales | `(rho_ocm + rho_ocm_2) / 2`, `decimals=2` | **SÍ en la fórmula; NO en el redondeo** — ver Hueco H5 |
| A36 | `col7` Resultado (Ωcm), 100 °C | Idéntico | Idéntico | Idéntico | Igual que A35 |
| — | (código muerto en la prueba 28) | `var t = document.getElementById("time"); t.textContent = t.textContent.slice(0, -3); var colu = document.getElementById("col5"); colu.textContent = colu.textContent.slice(0, -3);` | Recorta 3 caracteres del `textContent` de un elemento `#time` y del propio `col5`. Sobre un `<input>` no hace nada (`textContent` está vacío) | **NO APLICA** | **No corresponde portar** (residuo) |

### Recuento de la Tabla A

| | Cantidad |
|---|---|
| Asignaciones de columna en los 8 bloques | 36 |
| — numéricas (fórmulas propiamente dichas) | 22 |
| — concatenaciones de texto (estructurales, resueltas por réplicas) | 13 |
| — reescritura del propio insumo (A7) | 1 |
| **Fórmulas numéricas portadas** | **9** (A1-A6, A34, A35, A36) |
| **Fórmulas numéricas FALTANTES** | **13** (A8-A20, todas de la prueba 16) |

Las 9 portadas se verificaron en la base del nuevo, una por una:

```
numero_acido.resultado_mgkohg_aceite            = (volumen_gastado_ml - vol_blanco) * factor_koh / peso_aceite_g   dec=3
contenido_de_agua.repetibilidad                 = abs(r1 - r2)                                                     dec=1
contenido_de_agua.resultado_ppm                 = (r1 + r2) / 2                                                    dec=0
analisis_cromatografico.total_de_gases_combustibles = h2 + ch4 + co + c2h4 + c2h6 + c2h2 (códigos completos)        dec=2
analisis_cromatografico.total                   = los 9 gases                                                      dec=2
furanos.grado_de_polimerizacion                 = (1.51 - log10(furfuraldehido_2 / 1000)) / 0.0035                  dec=0
sedimentos.total_de_sedimentos                  = sedimentos_organicos + sedimentos_inorganicos                     dec=3
resistividad_volumetrica_25o.resultado_ocm      = (rho_ocm + rho_ocm_2) / 2                                        dec=2
resistividad_volumetrica_100o.resultado_ocm     = (rho_ocm + rho_ocm_2) / 2                                        dec=2
```
Las 9 quedaron con `type = computed` y `is_locked = 1`, o sea de solo lectura en
la hoja. En el viejo eso era una bandera aparte que había que acordarse de
marcar, y su propia ayuda lo pedía por escrito
(`catalogos-definiciones.sql:567`: *"Se recomienda bloquear la edicion en las
columnas que sean resultados de calculos"*).

---

## 4. Tabla B — Cálculos del informe (cromatografía)

Todos viven en `app/views/im_management/rem_reports/partials/_report_cromas.erb`.
Los insumos se toman de un `<div style="display: none">` (líneas 305-316) que
además **asigna variables mientras imprime**.

Base común (líneas 314-315):
`@tgc_val = CO + H2 + CH4 + C2H6 + C2H4 + C2H2` ·
`@sc1c2_val = CH4 + C2H6 + C2H4 + C2H2`

| # | Valor | Fórmula original (línea) | Notación | En el nuevo (`LegacyReportRenderer.php`) | ¿Coinciden? |
|---|---|---|---|---|---|
| B1 | `TG` | `@relacionest0 = rem_report_detail.cro11_val.to_f` (325) — **lee la columna "Total" que cargó el laboratorio** | Total de gases | `$tg = array_sum($v)` (:256) — **recalcula la suma de los 9** | **SÍ en el concepto, NO en la fuente** — ver Hueco H6 |
| B2 | `TGC` | `@relacionest1 = rem_report_detail.cro10_val.to_f` (331) — **lee la columna del laboratorio** | Total de combustibles | `$tgc = co+h2+ch4+c2h6+c2h4+c2h2` (:254) — recalcula | **Igual que B1** |
| B3 | `TGC-CO` | `@relacionest2 = @tgc_val - @co_val` (337) — aquí sí recalcula | `TGC − CO` | `$tgc - $v['co']` (:266) | **SÍ** |
| B4 | `TGC(%)` | `@relacionest5 = (@relacionest1/@relacionest0)*100` (352) — cociente de las **dos columnas del laboratorio** | `TGC / TG × 100` | `$d($tgc * 100, $tg)` (:267) — de los dos recalculados | **Igual que B1** |
| B5 | `CH4/H2` | `@relaciones1 = @ch4_val/@h2_val` (363) | `CH₄ / H₂` | `$d($v['ch4'], $v['h2'])` (:269) | **SÍ** |
| B6 | `C2H2/H2` | `@relaciones1a = @c2h2_val/@h2_val` (368) | `C₂H₂ / H₂` | (:270) | **SÍ** |
| B7 | `C2H2/C2H4` | `@relaciones3 = @c2h2_val/@c2h4_val` (378) | `C₂H₂ / C₂H₄` | (:271) | **SÍ** |
| B8 | `C2H2/C2H6` | `@relaciones4 = @c2h2_val/@c2h6_val` (384) | `C₂H₂ / C₂H₆` | (:272) | **SÍ** |
| B9 | `C2H4/C2H6` | `@relaciones7 = @c2h4_val/@c2h6_val` (403) | `C₂H₄ / C₂H₆` | (:273) | **SÍ** |
| B10 | `CO2/CO` | `@relaciones10 = @co2_val/@co_val` (410) | `CO₂ / CO` | (:274) | **SÍ** |
| B11 | `O2/N2` | `@relaciones11a = @o2_val/@n2_val` (421) | `O₂ / N₂` | (:275) | **SÍ** |
| B12 | `%H2` | `@relaciones12 = ( @h2_val/(@sc1c2_val + @h2_val ) )*100` (422) | `H₂ / (TGC − CO) × 100` | `$d($v['h2'] * 100, $base)` con `$base = $sc + $v['h2']` (:257,278) | **SÍ** |
| B13 | `%CH4` | (427) | `CH₄ / (TGC − CO) × 100` | (:279) | **SÍ** |
| B14 | `%C2H6` | (432) | `C₂H₆ / (TGC − CO) × 100` | (:280) | **SÍ** |
| B15 | `%C2H4` | (437) | `C₂H₄ / (TGC − CO) × 100` | (:281) | **SÍ** |
| B16 | `%C2H2` | (442) | `C₂H₂ / (TGC − CO) × 100` | (:282) | **SÍ** |
| B17 | `S(C1-C2)` | `@relacionest3 = @sc1c2_val` (343), **con `style="display: none"`** | `CH₄+C₂H₆+C₂H₄+C₂H₂` | Existe como intermedio `$sc` (:255) pero no se imprime | **Paridad** (en el viejo tampoco se veía) |
| B18 | `C2H2/CH4` | (372), oculto | `C₂H₂ / CH₄` | No existe | **No aplica** (oculto en el viejo) |
| B19 | `C2H4/CH4` | (392), oculto | `C₂H₄ / CH₄` | No existe | **No aplica** (oculto) |
| B20 | `C2H6/CH4` | (398), oculto | `C₂H₆ / CH₄` | No existe | **No aplica** (oculto) |
| B21 | `N2/O2` | (416), oculto | `N₂ / O₂` | No existe (sí el inverso, B11) | **No aplica** (oculto) |

Manejo de la división por cero: el viejo dejaba salir el `NaN` de Ruby y lo
tapaba con `if @relacionesN.nan? then 0.0` en cada uno de los 21 lugares. El
nuevo lo hace en un solo lugar: `$d = fn ($a, $b) => $b == 0.0 ? '0.0' : ...`
(`LegacyReportRenderer.php:260`). Mismo resultado visible, un solo punto de
control.

**Nota sobre el informe nuevo**: `LegacyReportRenderer` reproduce la maqueta
vieja. El informe propio del sistema nuevo (`TestReportPayload`) no imprime este
cuadro de relaciones; las relaciones de gases con valor diagnóstico
(Rogers/Doernenburg, Duval) viven en TrafoDex, no en el laboratorio.

**Resto de las secciones del informe viejo**: se revisaron
`_report_furanos.erb`, `_report_pcbs.erb`, `_report_sedimentos.erb`,
`_report_particles.erb`, `_report_metales.erb`, `_report_azufres.erb`,
`_report_inhibidores.erb`, `_report_physicals.erb`. **Ninguna calcula nada**:
imprimen el valor tal como vino del laboratorio, con reglas de redondeo (Tabla
C). No hay promedios de rigidez, ni correcciones por temperatura o densidad, ni
repetibilidad del factor de potencia en el informe — ver Huecos H2 y H3.

---

## 5. Tabla C — Reglas de presentación numérica (redondeos y límites de detección)

No son fórmulas de columna, pero **cambian el número que ve el cliente**, así que
se auditan igual. En el viejo estaban clavadas en el HTML, repetidas hasta tres
veces por gas (una por rama del `if` que decidía el color de la celda).

| # | Regla original | Dónde | En el nuevo | ¿Coinciden? |
|---|---|---|---|---|
| C1 | H2 `< 1` | `_report_cromas.erb:36,44,50` | `GASES['h2'] = 1.0` (`LegacyReportRenderer.php:54`, const en `:53-63`) y `detection_limits.json` | **SÍ** |
| C2 | O2 `< 105.4` | `:67` | `105.4` (:55) | **SÍ** |
| C3 | N2 `< 396.2` | `:82` | `396.2` (:56) | **SÍ** |
| C4 | CH4 `< 0.3` | `:97,105,111` | `0.3` (:57) | **SÍ** |
| C5 | CO `< 0.3` | `:129,137,143` | `0.3` (:58) | **SÍ** |
| C6 | CO2 `< 4.0` | `:161,169,175` | `4.0` (:59) | **SÍ** |
| C7 | C2H4 `< 0.3` | `:193,201,207` | `0.3` (:60) | **SÍ** |
| C8 | C2H6 `< 0.3` | `:225,233,239` | `0.3` (:61) | **SÍ** |
| C9 | C2H2 `< 0.4` | `:257,265,271` | `0.4` (:62) | **SÍ** |
| C10 | Gases con 1 decimal | `number_with_precision(..., precision: 1)` | `number_format($valor, 1, ...)` (:249) | **SÍ** |
| C11 | Los 5 furanos `< 10` (ppb) y truncados a entero (`.to_i`) | `_report_furanos.erb:35,51,67,83,99` | `detection_limits.json → furanos.*: 10`; el truncamiento a entero **no** se replica (el campo usa sus `decimals`) | **SÍ el límite; NO el truncamiento** — diferencia menor |
| C12 | Acidez en **tres tramos**: `< 0.005` → `"< 0.01"`; entre 0.005 y 0.010 → imprime **`"0.01"` forzado, no el medido**; ≥ 0.010 → 2 decimales | `_report_physicals.erb:44-54` | Reproducido literal en el informe legado: `'acid' => $valor < 0.010 ? ($valor < 0.005 ? '< 0.01' : '0.01') : number_format($valor, 2, ...)` (`LegacyReportRenderer.php:364`). En el informe NUEVO se simplificó a un límite único de 0.01, declarado en `detection_limits.json → _doc.pendiente_numero_acido` | **SÍ en el informe legado; SIMPLIFICADO en el nuevo** — ver Hueco H7 |
| C13 | Factor de potencia (25/90/100 °C) con 3 decimales | `_report_physicals.erb:76,101,126` | `'fp25','fp90','fp100' => number_format($valor, 3, ...)` (:365) | **SÍ** |
| C14 | Rigidez dieléctrica **truncada a entero** (`rig_val.to_i`: 44,9 kV se imprime 44) | `_report_physicals.erb:151,155,157` y `176,180,182` (electrodos planos) | `'rig','rig877' => (string)(int) $valor` (:366), con el comentario que lo señala | **SÍ**, se replicó a propósito |
| C15 | Acidez: el cálculo guarda 3 decimales (A1) y el informe muestra 2 | A1 + C12 | `decimals=3` en la columna, 2 en el informe legado | **SÍ** |

El nuevo saca estos números del HTML y los pone en datos
(`database/seeders/data/detection_limits.json`, sembrado por
`LabDetectionLimitsSeeder`, columna `test_fields.detection_limit`), con el
alcance declarado: *"Solo presentación. El veredicto (`results.spec_status`) se
decide al validar la hoja con el valor medido y no lo toca este número."*
El JSON avisa además que los valores están **sin validar por el laboratorio**.

---

## 6. Tabla D — Otros cálculos escondidos

| # | Cálculo | Dónde en el viejo | En el nuevo | ¿Coinciden? |
|---|---|---|---|---|
| D1 | Clase de tensión del equipo = **máximo** de la placa `"500/220/33"` — `num_ten.split('/').map(&:to_f).max` | `app/models/rem_report_detail.rb:191,393` (y `num_pot` en `:397`). Recalculado en cinco lugares distintos sobre un string | `Equipment::getVoltageClassAttribute()` → `mayor(voltages())` sobre tres columnas numéricas (`app/Models/Equipment.php:96-98`); `power_rating` idem (`:134`) | **SÍ**, mismo criterio, un solo lugar |
| D2 | Corte inclusivo de la banda de tensión (`@num_ten <= 69`) | `rem_report_detail.rb:203` y ~40 ramas más | `SpecSetResolver` compara con tope inclusivo y lo documenta (`app/Services/Lab/SpecSetResolver.php:165`) | **SÍ** |
| D3 | Conteo de gases fuera de norma para redactar el análisis: `@total_cro = @hid_error + @met_error + @mon_error + @dio_error + @eti_error + @eta_error + @ace_error` | `_form_add_details_cromas_default_values.html.erb:1` (y `@total_fq` equivalente en el de fisicoquímicos) | `DiagnosisTextService`: `$senalados->count()` alimenta `{count}` y la pluralización (`app/Services/Lab/DiagnosisTextService.php:246,262`), con plantillas en `database/seeders/data/diagnosis_templates.json` | **SÍ** (auditoría propia aparte) |
| D4 | Media / desvío estándar / CV% de la rigidez dieléctrica: **NO se calculaban**. El protocolo del espinterómetro los trae ya calculados y el sistema raspaba solo `"Valor medio:"` con una expresión regular | `1.txt_examples/.../0614.1.txt` (5 mediciones + `Valor medio` + `Desviación estándar` + `Desviación estándar/val. medio: 9.3 %`); columna importada en `catalogos-definiciones.sql` fila 31 (`imported_value = 'Valor medio:'`); parser en `imports/partials/_form_show.html.erb:12-23` | `InstrumentFileParser` sabe leer las N ocurrencias de una etiqueta (`app/Services/Lab/InstrumentFileParser.php`, con el ejemplo `{"code":"rig_1","mode":"label","match":"Medición 1:"}`), pero **no hay ningún `instrument_formats` sembrado** y la prueba de rigidez no declara réplicas | **Paridad con el viejo (nada se calculaba), pero la capacidad quedó sin cablear** — ver Hueco H2 |
| D5 | Corrección de la tensión interfacial por densidad y temperatura: la prueba 5 tiene las columnas ("Densidad Aceite", "Temp. Aceite", "Temp. Agua", "Densidad Agua", "Tensión Corregida Agua (70-74 mN/m)", "Tensión Interfacial Aceite (mN/m)") pero `blur_calculation` está **vacío** | `catalogos-definiciones.sql:557` + filas 37-42 | Mismas columnas, ninguna fórmula | **Paridad**: no existía y sigue sin existir — ver Hueco H3 |
| D6 | Viscosidad cinemática = Constante × Tiempo: la prueba 17 tiene "Constante", "Tiempo (Segundos)" y "Resultado (mm2/s)", sin fórmula. Y "Constante" y "Resultado" están declaradas de **tipo TEXTO** | `catalogos-definiciones.sql:581` + filas 111-118 | Sin fórmula | **Paridad** — ver Hueco H3 |
| D7 | "Contenido Total de PCB'S" = suma de los tres Aroclor: sin fórmula, y las cuatro columnas son de **tipo TEXTO** | `catalogos-definiciones.sql:569` + filas del detalle 11 | Sin fórmula | **Paridad** — ver Hueco H3 |
| D8 | "Código ISO (X/Y/Z)" de Partículas (ISO 4406, se deriva de los conteos): sin fórmula, todas las columnas de tipo TEXTO | detalle 18 | Sin fórmula | **Paridad** — ver Hueco H3 |
| D9 | Límites de la carta de control (LAS / LAI / LC / LCI / LCS): **campos de texto que el usuario tipeaba**. Ninguna derivación de media y desvío | `patron_tendences/partials/_form_edit.html.erb` (5 `text_field`), mostrados en `tendences/partials/_multi_content.html.erb:16-52` y en los 8 `_amcharts*.html.erb` | `QcChart::derive()` los calcula: `LC ± warn_sigma·σ` y `LC ± action_sigma·σ`, con σ configurable (`app/Models/QcChart.php:230-247`); `limits()` prefiere el cálculo sobre lo guardado | **MEJORA**: el nuevo agrega un cálculo que el viejo no tenía |
| D10 | Repetibilidad del duplicado: el viejo **obligaba a cargar duplicados y no los comparaba nunca**. La única repetibilidad calculada era la de Contenido de Agua (A2) | — | `RepeatabilityEvaluator::compare()` con criterio absoluto o relativo sobre el promedio de las dos lecturas (`app/Services/Lab/RepeatabilityEvaluator.php`) | **MEJORA** |
| D11 | Reglas de Westgard sobre la serie del patrón control | No existía (el viejo dibujaba las 5 líneas y nada más) | `WestgardEvaluator` (1_3s, 2_2s, R_4s, 4_1s, 10x) | **MEJORA** |
| D12 | Normalización de furanos importados: `UPDATE lab_file_details SET name = substring_index(name,' ',1) WHERE lab_category_sub_detail_id IN (80,81,82,83,84)` — un UPDATE **global sobre toda la tabla** en cada `after_create`, con ids clavados y el comentario `#DONT MOVE FURANOS COLUMN ORDER` | `app/models/lab_file_detail.rb:24-26` | La unidad se descarta al leer el número, sin lista de caracteres a borrar ni UPDATE global (`InstrumentFileParser`) | **SÍ el efecto; el mecanismo ya no hace falta** |

---

## 7. Recuento

### Fórmulas de columna (`blur_calculation`)

| | Viejo | Portadas | Faltan |
|---|---|---|---|
| Numéricas | **22** | **9** | **13** |
| Concatenaciones de texto (estructurales) | 13 | — | no corresponde |
| Reescritura del insumo (A7) | 1 | 0 | 1 (menor) |
| **Total de asignaciones** | **36** | **9** | **14** |

Las 13 numéricas faltantes son **todas** de la prueba **Grado de Polimerización
del papel** (id 16). Las otras 7 pruebas con fórmula están portadas al 100 %.

### Cálculos del informe de cromatografía

| | Cantidad |
|---|---|
| Valores visibles en el viejo | 16 |
| Portados | **16** (2 con diferencia de fuente: B1, B2) |
| Valores ocultos en el viejo (`display:none`) | 5 |
| De ésos, presentes como intermedio | 1 (B17) |
| De ésos, ausentes | 4 (B18-B21) — sin impacto: no se imprimían |

### Reglas de presentación

15 reglas identificadas (C1-C15): **14 portadas** con paridad exacta, **1
simplificada** en el informe nuevo (C12, el tramo medio de la acidez) y **1
truncamiento menor no replicado** (C11, furanos a entero).

### Otros cálculos

12 puntos (D1-D12): **3 portados con paridad** (D1, D2, D3), **1 con el efecto
portado y el mecanismo eliminado** (D12), **5 en paridad porque el viejo tampoco
calculaba** (D4-D8), **3 agregados por el nuevo** (D9, D10, D11).

---

## 8. HUECOS

### H1 — Las 13 fórmulas del Grado de Polimerización del papel · Severidad **ALTA**

**Qué falta**: A8-A20. Toda la cadena de cálculo viscosimétrico: viscosidad de
muestra y de solvente por réplica, concentración corregida por humedad,
viscosidad específica, viscosidad intrínseca, DP por réplica y su promedio.

**Por qué importa**: es la única prueba que mide **directamente** la degradación
del papel aislante, la propiedad que decide si un transformador se repara o se
retira. Hoy el analista tiene que calcular las 13 en una planilla aparte y
tipear los resultados: es exactamente el escenario que la migración existe para
eliminar, con la diferencia de que ahora el cálculo no queda ni auditado ni
versionado en ninguna parte. Además la carga manual de un valor derivado no
puede ser verificada por el revisor, que ve un número sin su origen.

**Bloqueo real, no olvido**: el motor evalúa cada réplica por separado y A8 cruza
las sub-lecturas 1 y 2 mientras A9 cruza las 3 y 4. Está declarado en
`database/seeders/data/test_formulas.json → pendientes.grado_de_polimerizacion`.
Antes de portar hay que decidir con el laboratorio si las 4 pesadas son 4
réplicas de una determinación o 2 determinaciones de 2 lecturas cada una — y esa
respuesta define si hacen falta funciones de agregación entre réplicas
(`avg_replicate(campo, 1, 2)`) o si el modelo de réplicas debe anidar un nivel.

**Nota normativa a resolver en el mismo pase**: A16 calcula la viscosidad
intrínseca como `K_Martin / C`. La ecuación de Martin es
`[η] = ηs / (C · (1 + k·ηs))`, que no es lo que hace el JavaScript: el `col13`
("K de Martin") lo tipea el analista y el código lo divide por la concentración,
sin usar la viscosidad específica que él mismo calculó dos columnas antes
(`col12`, A14-A15, **que queda sin consumir**). Es decir: el viejo calcula ηs y
después no lo usa. Hay que confirmar con el laboratorio qué se carga realmente
en esa columna antes de replicar el cálculo — copiarlo tal cual arrastraría un
error, y corregirlo sin confirmar cambiaría números históricos.

**Efecto secundario ya presente**: las columnas 9-12 y 14-16 de esa prueba están
en la base con `is_locked = 1` y sin fórmula. Hoy no bloquea nada porque la
grilla decide la solo-lectura por `type === 'computed'` o por tener fórmula
(`resources/js/Components/Worksheets/WorksheetGrid.vue:88`,
`WorksheetCell.vue:55`) y no consulta `is_locked` — pero es metadato
contradictorio: dice "esto no se escribe a mano" en las siete columnas que hoy
**solo** se pueden escribir a mano. Si alguien cablea `is_locked` en la grilla,
la prueba queda inutilizable.

### H2 — La rigidez dieléctrica no captura las 5 mediciones, ni su desvío, ni su CV · Severidad **ALTA**

**Qué falta**: el protocolo del espinterómetro trae 5 mediciones, el valor
medio, el desvío estándar y el CV%
(`1.txt_examples/HITACHI/DPA 75C - 1101908010/0614.1.txt`). El viejo raspaba
**solo** `"Valor medio:"` y descartaba el resto
(`catalogos-definiciones.sql`, columna 31: `imported_value = 'Valor medio:'`).
El nuevo tiene el motor para leerlas (`InstrumentFileParser`, con el ejemplo
`"match": "Medición 1:"` escrito en su propia documentación) pero **no hay
ningún registro en `instrument_formats`**, y la prueba `rigidez_dielectrica` no
declara réplicas.

**Por qué importa**: en un ensayo de rigidez la **dispersión es el dato**. Cinco
disrupciones de 64/57/54/61/68 kV promedian 60,8 kV, igual que 61/61/60/61/61 —
y significan cosas distintas: la primera muestra contaminación con partículas o
humedad, la segunda es un aceite limpio. Guardar solo el promedio borra esa
información de forma irreversible. ASTM D1816/D877 piden informar la dispersión
justamente por eso, y el CV% que el instrumento ya calcula es el criterio de
aceptación del propio ensayo. Además, sin las 5 lecturas no se puede verificar
el promedio informado: es un número sin trazabilidad, en un laboratorio
acreditado ISO/IEC 17025.

**Es paridad con el viejo**, no una regresión. Pero cerrarlo es barato: declarar
un `instrument_formats` para el DPA 75C con las 5 etiquetas + `replicates = 5` en
la columna de resultado, y poner el promedio como fórmula
(`avg(rig_1, rig_2, rig_3, rig_4, rig_5)`) en vez de importarlo. El desvío y el
CV necesitan dos funciones que el parser todavía no tiene (`stdev`, `cv`) —
agregar una función es una fila en `FormulaParser::FUNCTIONS` y su caso en
`FormulaEvaluator::call()`.

### H3 — Cuatro resultados que deberían ser calculados y se siguen tipeando · Severidad **MEDIA**

Cuatro columnas de resultado son, matemáticamente, una función de columnas que
están en la misma pantalla, y en las dos versiones del sistema las escribe el
analista a mano:

| Prueba | Columna | Lo que debería ser | Estado en el viejo | Estado en el nuevo |
|---|---|---|---|---|
| Tensión Interfacial (5) | "Tensión Corregida Agua" y "Tensión Interfacial Aceite (mN/m)" | Corrección por densidad y temperatura a partir de las 4 columnas de entrada que la prueba ya tiene | Sin fórmula (`blur_calculation` vacío) | Sin fórmula |
| Viscocidad (17) | "Resultado (mm2/s)" | `Constante × Tiempo` | Sin fórmula, y la columna es de **tipo TEXTO** | Sin fórmula |
| PCB (11) | "Contenido Total de PCB'S" | Suma de Aroclor 1242 + 1254 + 1260 | Sin fórmula, las 4 columnas de **tipo TEXTO** | Sin fórmula |
| Partículas (18) | "Código ISO (X/Y/Z)" | Derivación ISO 4406 de los 7 conteos | Sin fórmula, todas de **tipo TEXTO** | Sin fórmula |

**Por qué importa**: un resultado tipeado es un resultado que nadie verificó. Y
el tipo TEXTO agrava el problema: el sistema no puede ni comparar el valor contra
el límite de norma ni graficarlo, porque no es un número para la base. En el
caso de la tensión interfacial el hueco es doble — el laboratorio **está**
haciendo una corrección fisicoquímica en una planilla externa y el sistema
guarda únicamente su desenlace.

**No es una regresión de la migración**: es deuda heredada. Pero aquí se cierra
con datos (una fórmula en el editor de columnas y un cambio de tipo), no con
código — que es precisamente lo que la arquitectura nueva compró.

### H4 — El redondeo del 2-FAL en la entrada no se replica · Severidad **BAJA**

A7: el viejo reescribía la casilla del analista a 3 decimales
(`col4Input.value = col4.toFixed(3)`) **antes** de calcular el DP. En el nuevo
`furanos.furfuraldehido_2` tiene `decimals = NULL` (verificado en base), así que
el DP se calcula con todos los decimales que el analista cargó.

**Por qué importa poco pero importa**: `log10` amortigua el efecto — la
diferencia en el DP entre un 2FAL de 1234,5678 y 1234,568 ppb es de menos de un
punto de DP, y el DP se informa con 0 decimales. Nunca va a cambiar un
diagnóstico. Pero sí puede cambiar el último dígito de un valor histórico
recalculado, y eso complica cualquier cotejo contra los informes viejos. Se cierra
poniendo `decimals = 3` en la columna, sin tocar código.

### H5 — El redondeo de la resistividad volumétrica cambió de naturaleza · Severidad **BAJA**

A35/A36: el viejo hacía `result.toExponential(2).toUpperCase()`, o sea guardaba
la **cadena** `"1.23E+12"` — tres cifras significativas, y el resto de la
precisión se perdía en la base. El nuevo guarda el número y aplica
`round($valor, 2)`: dos **decimales**, no dos cifras significativas.

**Por qué importa**: para valores del orden de 10¹² el `round(…, 2)` no hace
nada, así que el nuevo conserva más precisión que el viejo — es una mejora, pero
**los números no van a coincidir dígito a dígito** con los registros históricos.
Y hay un riesgo latente en la dirección opuesta: si alguna vez se informa
resistividad en unidades que den valores sub-unitarios, `decimals = 2` los
destruiría. Conviene revisar si esa columna debería llevar cifras significativas
en vez de decimales, o `decimals` más holgado.

### H6 — TG y TGC del informe: la fuente cambió de columna a suma · Severidad **BAJA**, con matiz

B1/B2: el informe viejo imprimía TG y TGC **leyendo las columnas** `cro11_val` y
`cro10_val`, que a su vez venían de las columnas calculadas de la hoja (A4/A5).
El nuevo los recalcula desde los 9 gases.

**Por qué importa**: el viejo era **internamente inconsistente** — mostraba TGC
leído de la columna (línea 331) pero calculaba TGC−CO recomputando la suma
(línea 337). Si la columna de la hoja y la suma de los gases discrepaban (y
podían: un POST directo escribía cualquier cosa en la columna calculada, o la
columna quedaba con el texto `"NaN"`), el informe mostraba dos números
incompatibles en el mismo cuadro. El nuevo no puede discrepar porque hay una
sola fuente.

Es una corrección, no un hueco. Se registra porque **es una diferencia numérica
frente a los informes históricos** en cualquier muestra cuya columna de totales
estuviera mal, y eso hay que saberlo antes de comparar informes viejos con
nuevos.

### H7 — El tramo medio de la acidez se simplificó en el informe nuevo · Severidad **BAJA**, requiere decisión del laboratorio

C12: el viejo tenía tres tramos, y el del medio es raro — entre 0,005 y 0,010
mgKOH/g imprimía **`0.01` forzado**, no el valor medido ni un `<`. El informe
legado del nuevo lo reproduce exacto
(`LegacyReportRenderer.php:364`), pero el informe nuevo usa un límite de
detección único de 0,01 (`detection_limits.json → numero_acido`), lo que manda
todo lo que esté por debajo a `"< 0.01"`.

**Por qué importa**: es un cambio en lo que ve el cliente. Un aceite con acidez
0,007 medida salía `0.01` y ahora sale `< 0.01`. Los dos textos dicen "acidez
muy baja" y ninguno cambia el veredicto, pero el laboratorio tiene que decidir
si ese tramo era un criterio metrológico (el método distingue entre "por debajo
del límite" y "en el límite") o un parche de presentación. Está anotado como
pendiente en el propio JSON.

### H8 — Los límites de detección están sin validar · Severidad **MEDIA**

No es un hueco de fórmula, pero afecta el número informado: los 15 valores de la
Tabla C se portaron **tal cual estaban clavados en el HTML del informe viejo**, y
`detection_limits.json` lo declara: *"SIN VALIDAR por el laboratorio. Son los
cortes que el informe viejo venía imprimiendo; hay que confirmarlos contra los
certificados de los métodos vigentes."*

**Por qué importa**: un límite de detección es una propiedad del método
**vigente**, con su certificado. Números como `105.4` para el O₂ o `396.2` para
el N₂ tienen la pinta de haber salido de una validación concreta en una fecha
concreta; si el método o el equipo cambiaron desde entonces, el informe está
declarando un límite que el laboratorio ya no puede sostener ante el ente
acreditador. Ahora al menos están en un archivo con nombre y se editan sin tocar
código — en el viejo estaban repetidos hasta tres veces por gas en el HTML.

### H9 — La fórmula está visible en la pantalla de columnas, pero apenas · Severidad **BAJA** (usabilidad)

`resources/js/Pages/TestDefinitions/Fields.vue:216` imprime la fórmula bajo la
etiqueta de la columna:

```vue
<td>
    <span class="tfp-label">{{ field.label }}</span>
    <div v-if="field.formula" class="tfp-formula">{{ field.formula }}</div>
</td>
```

Con este estilo (`:344`): `font-size: 0.72rem`, `color: var(--color-text-muted)`,
`max-width: 320px`, `overflow-wrap: anywhere`.

**Qué está bien**: la fórmula se ve sin abrir nada, en monoespaciada, en la misma
fila que su columna. El editor (`resources/js/Components/TestFields/FormulaField.vue`)
la comprueba en vivo contra el servidor y muestra los errores, las columnas que
referencia y los ciclos. Eso es mucho más de lo que tenía el viejo, donde el
JavaScript vivía en un `textarea` rotulado "Fórmula de Programación"
(`configurations/category_details/partials/_form_new.html.erb:28`) sin ninguna
validación.

**Qué le falta**:
1. **No hay encabezado.** La tabla tiene columnas para Tipo, Rol, Unidad,
   Requerido, Reutilizable, Visible en informe y Réplicas, pero la fórmula va
   como subtítulo sin rótulo. Quien no sabe que existe no la busca.
2. **Contraste bajo a 0,72 rem** en gris atenuado para el contenido que más
   necesita revisarse en una auditoría de laboratorio.
3. **No se ve la dependencia inversa.** Al mirar `factor_koh` no hay forma de
   saber que `resultado_mgkohg_aceite` depende de él. Es la pregunta que hay que
   contestar antes de borrar o renombrar una columna, y hoy se contesta leyendo
   las 9 fórmulas a ojo. El dato ya existe en el servidor
   (`FormulaParser::variables()`, y `checkFormula` ya lo devuelve como `uses`).
4. **`max-width: 320px` con `overflow-wrap: anywhere`** parte la fórmula del
   `analisis_cromatografico.total` (9 términos, 180 caracteres) en varias líneas
   cortando códigos a media palabra.

Ninguna de las cuatro es un riesgo de cálculo. Las cuatro son razones por las que
una fórmula equivocada puede pasar una revisión.

---

## 9. Qué revisar antes de dar el área por cerrada

1. Decidir con el laboratorio el modelo de réplicas del Grado de Polimerización
   (H1) y, en el mismo pase, confirmar qué se carga en "K de Martin".
2. Declarar el formato del DPA 75C y pasar la rigidez a 5 réplicas + promedio
   calculado; evaluar agregar `stdev` y `cv` al parser (H2).
3. Convertir a fórmula los cuatro resultados que hoy se tipean, y cambiar de
   TEXTO a número las columnas involucradas (H3).
4. Confirmar los 15 límites de detección contra los certificados vigentes (H8).
5. Resolver el tramo medio de la acidez (H7).
6. Poner `decimals` en `furanos.furfuraldehido_2` (H4) y revisar el redondeo de
   la resistividad (H5).
7. Quitar el `is_locked = 1` de las columnas sin fórmula del Grado de
   Polimerización, o cablearlo junto con las fórmulas — hoy es metadato que
   contradice el uso real (H1, efecto secundario).
