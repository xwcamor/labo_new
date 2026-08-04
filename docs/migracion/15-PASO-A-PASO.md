# Migración: qué hacer, en orden

> El [plan](14-PLAN-MIGRACION-DATOS.md) dice QUÉ hay que hacer y por qué.
> Este documento dice **en qué orden, quién hace cada cosa y cómo saber que
> un paso terminó**. Si te perdiste, empieza por acá.
>
> Cada paso tiene: **quién**, **qué**, **cómo se sabe que terminó**. No pases al
> siguiente sin cerrar el anterior, salvo donde diga que va en paralelo.

---

## Dónde estamos

El sistema nuevo funciona: se cargan recepciones, muestras, bancada e informes.
Lo que falta es meterle **la historia del sistema viejo**.

No se puede empezar a escribir el importador todavía, y el motivo es simple:
**no sabemos cuántos datos hay.** Las cifras que circulan en la documentación no
están respaldadas por ningún conteo. Escribir un ETL sin saber si son 5.000 o
500.000 filas es decidir a ciegas.

---

## BLOQUE A — Lo que se puede cerrar ya (esta semana)

### Paso 1 · Qué se versiona — RESUELTO (2026-08-04)
Decisión del dueño, ya aplicada. Queda escrita acá porque condiciona el resto.

Los datos de negocio del laboratorio **sí se versionan** en este repositorio,
aunque sea público: razones sociales de empresas, sedes, padrón de equipos,
números de muestra y mediciones de aceite son información comercial corriente.
No identifican a una persona ni habilitan a nadie a entrar a ningún lado.

Lo que se gana no es comodidad: **la migración se vuelve reproducible desde un
clon limpio**. Quien clona corre `setup:project` y obtiene la base con su
historia, y la verificación se puede repetir cuantas veces haga falta sin
pedirle archivos a nadie. Es el mismo criterio con el que TrafoDex versiona sus
`*_legacy.sql`, y es lo que permitió auditar aquella migración meses después.

**Dos excepciones, y no son de estilo:**

- La tabla **`users`**: su columna `real_password` guarda la contraseña en texto
  plano. Una contraseña no es un dato de negocio, es una credencial, la gente
  las reutiliza entre servicios, y publicarla perjudica a alguien que no
  participó de esta decisión. Tampoco hace falta para migrar: los usuarios del
  sistema nuevo se dan de alta de nuevo. Cubierto por el `.gitignore`.
- Las **imágenes de firma escaneada**. El nombre del firmante sí se versiona
  (hace falta para que el informe diga quién firma); la firma manuscrita vive
  fuera del repositorio, en `storage/app/legacy-assets`.

El criterio completo está en la cabecera de
[`esquema/catalogos-definiciones.sql`](esquema/catalogos-definiciones.sql), que
es donde lo va a leer el próximo que toque un volcado.

---

### Paso 2 · Mandar la consulta del censo
**Quién:** tú, o quien tenga acceso a la base MySQL de producción.
**Qué:** es lo que desbloquea todo lo demás. Una sola consulta:

```sql
SELECT 'rems'             t, COUNT(*) n, MIN(created_at) desde, MAX(created_at) hasta FROM rems             WHERE deleted=0
UNION ALL SELECT 'rem_correlatives', COUNT(*), MIN(created_at), MAX(created_at) FROM rem_correlatives WHERE deleted=0
UNION ALL SELECT 'rem_jobs',         COUNT(*), MIN(created_at), MAX(created_at) FROM rem_jobs         WHERE deleted=0
UNION ALL SELECT 'rem_reports',      COUNT(*), MIN(created_at), MAX(created_at) FROM rem_reports      WHERE deleted=0
UNION ALL SELECT 'rem_report_details',COUNT(*),MIN(created_at), MAX(created_at) FROM rem_report_details WHERE deleted=0
UNION ALL SELECT 'labs',             COUNT(*), MIN(created_at), MAX(created_at) FROM labs             WHERE deleted=0
UNION ALL SELECT 'lab_details',      COUNT(*), MIN(created_at), MAX(created_at) FROM lab_details      WHERE deleted=0
UNION ALL SELECT 'lab_sub_details',  COUNT(*), MIN(created_at), MAX(created_at) FROM lab_sub_details  WHERE deleted=0
UNION ALL SELECT 'transformers',     COUNT(*), MIN(created_at), MAX(created_at) FROM transformers     WHERE deleted=0;
```

Y estas tres, que son las que deciden cuánto trabajo manual va a haber:

```sql
-- (1) Series de transformador repetidas: si son pocas, el emparejamiento
--     con TrafoDex se automatiza; si son muchas, lo tiene que mirar alguien.
SELECT num_serie, COUNT(*) n FROM transformers
WHERE deleted=0 AND num_serie IS NOT NULL AND TRIM(num_serie) <> ''
GROUP BY num_serie HAVING COUNT(*) > 1 ORDER BY n DESC;

-- (2) Filas de bancada cuyo numero de muestra NO existe como correlativo.
--     Son los huerfanos del vinculo por texto. Es EL numero del proyecto.
SELECT COUNT(*) huerfanos FROM lab_details d
WHERE d.deleted=0 AND d.num_test IS NOT NULL AND TRIM(d.num_test) <> ''
  AND NOT EXISTS (
    SELECT 1 FROM rem_correlatives c
    WHERE c.deleted=0
      AND c.year_test = CAST(SUBSTRING_INDEX(d.num_test,'-',1) AS UNSIGNED)
      AND c.num_test  = CAST(SUBSTRING_INDEX(d.num_test,'-',-1) AS UNSIGNED)
  );

-- (3) Muestras entradas a trapp DESPUES del volcado del 2026-06-07.
--     Ese delta hoy no esta en TrafoDex y nadie lo tiene contado.
--     Correr contra tr_app_development:
SELECT 'chromatographicals' t, COUNT(*) FROM chromatographicals WHERE deleted=0 AND created_at > '2026-06-07'
UNION ALL SELECT 'physicals', COUNT(*) FROM physicals WHERE deleted=0 AND created_at > '2026-06-07'
UNION ALL SELECT 'furanos',   COUNT(*) FROM furanos   WHERE deleted=0 AND created_at > '2026-06-07';
```

**Cómo se sabe que terminó:** tienes los números pegados en una respuesta.
Con eso se dimensiona el proyecto de verdad.

---

### Paso 3 · Pedir el volcado con datos
**Quién:** quien tenga acceso al servidor.
**Qué:** un `mysqldump` de las tablas operativas, **sin las de personas**.

```bash
mysqldump -u USUARIO -p --no-create-info --skip-extended-insert \
  lab_app_development \
  rems rem_correlatives rem_jobs rem_reports rem_report_details \
  labs lab_details lab_sub_details transformers \
  > database/seeders/data/legacy/lab_app-datos.sql
```

`--skip-extended-insert` importa: escribe una tupla por línea, que es lo que el
lector de volcados espera y lo que hace legible el `diff` de git cuando llegue
un volcado nuevo.

El archivo **se commitea** (paso 1). Lo que no se pide es `users`: su columna
`real_password` guarda la contraseña en texto plano.

**Cómo se sabe que terminó:** el archivo está en
`database/seeders/data/legacy/`, `git status` sí lo menciona, y `head -40` sobre
él muestra `INSERT INTO` con una tupla por línea.

---

### Paso 4 · Cerrar los pendientes chicos del día a día
**Quién:** tú decides, yo ejecuto. Van en paralelo, no bloquean nada.

1. **Las cinco columnas de equipo sin lista.** Viscosidad (Termómetro,
   Viscosímetro), DBDS (Nº de Equipo) y las dos Resistividad Volumétrica (Tipo
   de Equipo) hoy ofrecen **el catálogo entero**: en la columna del termómetro
   aparecen buretas y cromatógrafos. Pasa porque en el sistema viejo esas cuatro
   eran texto libre y no había lista que importar.
   **Necesito de ti:** qué equipos van en cada una.
2. **El Termómetro de Viscosidad** es la última etiqueta con "PP-LA-01C" pegado.
   **Necesito de ti:** un "sí".
3. **El Resultado de Viscosidad** es calculable (`tiempo × constante`, ASTM
   D445). Pasarlo a calculado lo vuelve de solo lectura.
   **Necesito de ti:** si lo calcula el sistema o lo sigue escribiendo el
   analista.

---

## BLOQUE B — Lo que yo construyo mientras llegan los números

No necesita ni un dato real. Se prueba con volcados sintéticos de tres filas.

### Paso 5 · El lector de volcados
Una sola clase `LegacyDumpReader` que los siete importadores van a usar, en vez
del parser copiado en cada uno. Con una mejora sobre el de TrafoDex: **deriva las
columnas del `INSERT INTO ... (...)` del propio archivo** en lugar de tenerlas
escritas a mano, así un cambio de esquema se detecta en vez de tragarse.

### Paso 6 · El comando de censo
`php artisan migracion:censo` — lee el volcado y reporta, por tabla: filas, rango
de fechas, huérfanos por clave foránea, y **cuántos ceros tiene cada parámetro
numérico**. Ese último dato es el insumo del Paso 8.

### Paso 7 · El esqueleto de la verificación
El comando que al final va a comparar viejo contra nuevo, con la estructura ya
probada en TrafoDex: leer el valor viejo **siempre del volcado y nunca de la
base**, y segmentar la población antes de calcular ningún porcentaje.

---

## BLOQUE C — Las decisiones del laboratorio

Ninguna es técnica. Ninguna la puedo tomar yo. Todas se pueden ir resolviendo en
paralelo, pero **el Paso 8 bloquea la importación de resultados**.

### Paso 8 · El cero, parámetro por parámetro
**El más importante.** El sistema viejo escribía `0` donde no se midió. Si se
importa tal cual, el motor lo compara contra el mínimo de la norma y marca
**fuera de norma a aceites sanos**.

TrafoDex ya lo pagó: en su tabla de fisicoquímicos no había **ni un solo nulo**
en rigidez, 0 de 7.476 filas. Y se arregló para un campo creyéndolo cerrado
mientras seguía vivo en otros tres. Al anularlos aparecieron 626 ensayos que
hasta entonces eran invisibles.

**Qué se necesita:** el Paso 6 produce la tabla "parámetro · cuántos ceros". Tú
la recorres y marcas, para cada uno: **¿el cero es físicamente posible?** En
acidez, agua y tensión interfacial puede ser real. En una rigidez dieléctrica,
no existe.

### Paso 9 · El número de informe
¿El informe migrado conserva el número del sistema viejo, o adopta el formato
nuevo `REP-LAB-AAAA-NNNN`? Si se cambia, el cliente deja de encontrar el número
impreso en los papeles que ya tiene.

### Paso 10 · Las hojas de trabajo históricas
¿Entran como **completas** o como **en carga**? De eso dependen dos cosas: si
producen resultados consultables (una hoja en carga no publica nada), y si el
candado automático las cierra a todas la primera noche.

---

## BLOQUE D — La migración propiamente dicha

Recién acá se escribe el importador. **Por módulo, no por año**, y con una prueba
de humo antes de soltar todo.

```
 11. Prueba de humo    el año con menos datos, las siete etapas completas
 12. Equipos           lab_app.transformers → equipment
 13. Recepciones       rems → receptions ; rem_correlatives → samples
 14. Pruebas pedidas   rem_jobs → sample_tests
 15. Bancada           labs → worksheets → rows → values
 16. Resultados        lab:rebuild-results  (NO se importan, se reconstruyen)
 17. Informes          rem_reports → sample_reports, con sus límites congelados
 18. Contadores        GREATEST(last_number, MAX(number)) por año — obligatorio
 19. Verificación      paridad + reconciliación por recuento
```

El paso 11 no es opcional: correr las siete etapas sobre el año más chico, medir
cuánto tarda, mirar los huérfanos y recién entonces seguir. Es lo que evita
descubrir un problema estructural con 200.000 filas ya cargadas.

El paso 18 tampoco: si los contadores no se corrigen, la primera recepción
después del arranque puede **imprimir una etiqueta con un número que ya está en
un informe entregado**.

---

## Si solo vas a hacer una cosa hoy

El **Paso 2**. Todo lo demás espera esos números, y son diez minutos de quien
tenga acceso a la base.
