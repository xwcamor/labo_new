# Volcados con datos del sistema viejo

Acá van los volcados MySQL de `lab_app_development` que alimentan la migración
del histórico. **Se versionan**, igual que los `*_legacy.sql` de TrafoDex, y por
el mismo motivo: es lo que hace que la migración sea reproducible desde un clon
limpio. Quien clona corre `setup:project` y obtiene la base con su historia, sin
depender de que alguien le pase archivos por otro canal.

El criterio completo de qué se puede versionar y qué no está en la cabecera de
[`catalogos-definiciones.sql`](../../../../docs/migracion/esquema/catalogos-definiciones.sql).
En dos líneas: los datos de negocio sí; la tabla `users` **no**, porque su
columna `real_password` guarda la contraseña en texto plano y eso es una
credencial, no un dato de negocio. Está cubierto por una regla del `.gitignore`.

## Cómo generar los volcados

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

## Si la carpeta está vacía

El proyecto se instala igual: los importadores del histórico avisan que falta el
archivo y siguen, sin romper el seed. Lo que no vas a tener es la historia.

## El paso a paso

[`docs/migracion/15-PASO-A-PASO.md`](../../../../docs/migracion/15-PASO-A-PASO.md)
