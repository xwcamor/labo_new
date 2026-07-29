# Dónde se edita cada cosa (y qué todavía no tiene pantalla)

> Respuestas verificadas contra el código el 2026-07-29. Cada punto cita la ruta
> real y el archivo, para que no haya que adivinar.

## 1. Las columnas de una prueba

**Pantalla:** `/es/lab_management/test_definitions/{slug}/fields`
**Archivo:** `resources/js/Pages/TestDefinitions/Fields.vue`
**Cómo se llega:** módulo Pruebas → abrir la prueba → Columnas.

Se edita por columna: orden (arrastrando), etiqueta, código, tipo, rol, unidad,
obligatorio, reutilizable, visible en el informe y cantidad de réplicas. El
reordenamiento es seguro: las fórmulas referencian el CÓDIGO de la columna, no su
posición — es la corrección del defecto más grave del sistema anterior, donde
`col7` significaba "la séptima columna viva" y borrar una columna anterior
cambiaba en silencio lo que calculaba cada fórmula.

Va anidada bajo la prueba y no como módulo suelto a propósito: una columna no
significa nada fuera de su prueba, y en el sistema anterior las dos pantallas que
editaban lo mismo (el editor dentro de la prueba y un CRUD aparte) se
desincronizaron.

## 2. Las constantes

**Pantalla:** `/es/lab_management/test_definitions/{slug}/constants`
**Archivo:** `resources/js/Pages/TestDefinitions/Constants.vue`
**Cómo se llega:** desde el editor de columnas, botón "Constantes".

Son los valores que valen para todas las filas de la hoja (el factor de la
solución titulante, el peso de la muestra): se declaran una vez en la prueba y la
bancada los aplica a cada réplica. La columna se marca como reutilizable en el
editor de columnas y su valor se fija acá.

## 3. La fórmula de una columna calculada

**Se VE** en el editor de columnas: cada columna con fórmula la muestra debajo de
su nombre (`Fields.vue:216`), y al editar la columna se valida en el servidor
antes de guardar (`test_definitions.fields.check_formula` →
`TestFieldController::checkFormula`), que detecta referencias inexistentes y
círculos.

**En la bancada** la celda calculada se marca como tal y muestra la vista previa
del resultado mientras el analista escribe (`WorksheetCell.vue`), pero **no
muestra el texto de la fórmula**. Es una mejora pendiente razonable: el analista
que ve un número raro debería poder leer de dónde sale sin ir a la configuración
de la prueba.

El motor está en `app/Services/Lab/Formula{Parser,Evaluator,Resolver,Validator}.php`
y calcula en el SERVIDOR. En el sistema anterior la fórmula era JavaScript crudo
guardado en la base e inyectado en el HTML con `html_safe`: solo existía en el
navegador, el servidor no podía recalcular nada, y quien editaba la fórmula
editaba código que corría en la sesión de otro.

## 4. Los textos del diagnóstico automático — NO TIENE PANTALLA

Hoy las plantillas viven en un archivo del repositorio:
`database/seeders/data/diagnosis_templates.json`, leído por
`app/Services/Lab/DiagnosisTextService.php:504`. Para cambiar una frase hay que
editar ese archivo y volver a sembrar.

**Esto contradice el principio del proyecto** (todo lo que puede cambiar vive en
datos, el código solo tiene fórmulas) y es el hueco más importante de esta lista:
el texto del diagnóstico es exactamente lo que el laboratorio quiere ajustar sin
programador, y sale impreso en el informe del cliente.

**Lo que corresponde construir**, siguiendo el patrón que TrafoDex ya tiene para
sus reglas de diagnóstico (tabla editable + pantalla de edición con restaurar
valores de fábrica):

1. Tabla `diagnosis_templates` con `tenant_id` nullable (null = plantilla de
   fábrica; una fila por tenant la personaliza sin tocar el estándar).
2. Pantalla de edición por familia, con las bandas (rangos) y el texto de cada
   una, en los dos idiomas, con vista previa del párrafo resuelto.
3. Botón "Restaurar" que borra el override del tenant y vuelve a la plantilla de
   fábrica del JSON.

## 5. La tensión en kV y la potencia en MVA con barras ("220/60/10")

**Cómo está hoy:** seis campos numéricos separados —`voltage_kv_hv`,
`voltage_kv_lv`, `voltage_kv_tv`, `power_mva`, `power_mva_2`, `power_mva_3`— y la
etiqueta que se muestra e imprime se arma uniendo con barras
(`Equipment::voltage_label` / `power_label`). Guardar números y no la cadena es lo
correcto: la clase de tensión que decide el criterio IEEE C57.106 necesita un
número comparable, y en el sistema anterior era texto libre.

**Los tres huecos confirmados (2026-07-29):**

- **El formulario no acepta la cadena.** Los seis campos son `InputNumber`: quien
  copia "220/60/10" de la placa o de una planilla no puede pegarlo — el control lo
  descarta sin explicar por qué. Falta aceptar la cadena en el campo de alta
  tensión y repartirla sola en los tres campos (con separadores tolerantes:
  barra, guion, coma, con o sin "kV", con espacios), mostrando el resultado para
  que el usuario lo confirme.
- **La importación no trae la placa.** `app/Imports/BusinessManagement/Equipment/EquipmentImport.php`
  **no mapea ninguna columna de tensión ni de potencia**: importar el padrón de
  equipos deja la placa vacía, y es justo el dato que viene con barras en los
  archivos del laboratorio.
- **No hay parseo de barras en ninguna parte del sistema**
  (`grep "explode('/')"` solo aparece en el motor de diagnóstico, para otra cosa).

**Pendiente de medir contra los datos reales:** cuántos segmentos traen de verdad
los valores del sistema anterior (si hay casos de más de tres) y qué separadores
aparecen. Eso decide si tres campos alcanzan.

---

## Auditoría profunda del sistema anterior — pendiente de ejecutar

Se lanzaron doce revisiones en paralelo del sistema anterior contra el nuevo y
**todas se cortaron por límite de sesión de la cuenta**, no por error del
proyecto. Quedan pendientes, cada una con su documento de salida en
`docs/migracion/auditoria/`:

| # | Área | Documento |
|---|---|---|
| A | Columnas de pruebas y constantes (`lab_category_sub_detail*`, `is_reuse`/`reuse_value`) | `A-columnas-y-constantes.md` |
| B | Inventario de TODAS las fórmulas (`blur_calculation`) y su paridad | `B-formulas.md` |
| C | Textos del diagnóstico automático: cómo se producían y dónde se editaban | `C-textos-diagnostico.md` |
| D | Placa del equipo y el formato con barras, contra los datos reales | `D-placa-equipos.md` |
| E | Matriz de cobertura tabla por tabla del esquema completo | `E-cobertura-tablas.md` |
| F | Inventario de todas las pantallas y acciones (menú reconstruido) | `F-pantallas-y-acciones.md` |
| G | Control de calidad: patrones, límites y tendencias | `G-control-de-calidad.md` |
| H | Instrumentos, calibración y almacén | `H-instrumentos-y-almacen.md` |
| I | Usuarios, perfiles, los accesos numerados y las firmas | `I-permisos-y-firmas.md` |
| J | Clientes, jerarquía y la integración con el sistema de transformadores | `J-clientes-y-integracion.md` |
| K | Reportes gerenciales, indicadores y exportaciones | `K-reportes-gerenciales.md` |
| L | Adjuntos, registro de auditoría, catálogos sueltos y temperaturas | `L-adjuntos-auditoria-temperaturas.md` |

Cada una tiene que entregar: equivalencias con evidencia `archivo:línea`, la lista
de lo que falta con severidad, y la consecuencia de negocio de cada ausencia.
