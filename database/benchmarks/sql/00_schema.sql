-- Esquema del banco de pruebas.
--
-- Vive en el esquema `bench` y no toca nada de `public`: la aplicacion puede
-- seguir corriendo mientras el banco de pruebas trabaja, y limpiar es un
-- DROP SCHEMA.

drop schema if exists bench cascade;
create schema bench;

-- Generador determinista (Lehmer, dos vueltas para decorrelacionar).
-- Por que no random(): el banco de pruebas necesita poder regenerar EL MISMO
-- juego de datos mas tarde. En la escala grande no entran a la vez la tabla
-- vertical y su copia particionada, asi que la particionada se genera despues
-- de borrar la otra y tiene que salir identica para que la comparacion valga.
create or replace function bench.rnd(seed bigint)
returns double precision
language sql immutable parallel safe as $$
  select ((((abs(seed) % 2147483647) * 48271) % 2147483647) * 48271 % 2147483647)::double precision
         / 2147483647.0
$$;

-- Catalogo de parametros medidos.
-- Se deriva de las plantillas REALES de la aplicacion (public.test_fields) para
-- que la cantidad de columnas por prueba y sus nombres sean los del laboratorio
-- y no una invencion. Se materializa para que `bench` quede autocontenido.
create table bench.analytes (
  id                 int primary key,
  test_definition_id int          not null,
  test_code          varchar(60)  not null,
  code               varchar(60)  not null,
  col_name           varchar(63)  not null,
  unit               varchar(30),
  base_value         double precision not null
);

insert into bench.analytes (id, test_definition_id, test_code, code, col_name, unit, base_value)
select
  (row_number() over (order by td.id, tf.sort_order))::int,
  td.id,
  td.code,
  tf.code,
  tf.code,
  coalesce(nullif(tf.unit, ''), 'ppm'),
  -- Escala tipica por parametro. Solo importa que los valores tengan una
  -- dispersion parecida a la real: de ella depende que fraccion de la flota
  -- supera el umbral en la consulta 3.
  -- El orden de las ramas importa: 'acetileno_c2h2_ppm' tambien contiene 'h2'.
  case
    when tf.code like 'acetileno%'  then 3
    when tf.code like 'etileno%'    then 25
    when tf.code like 'etano%'      then 20
    when tf.code like 'metano%'     then 30
    when tf.code like 'hidrogeno%'  then 60
    when tf.code like 'oxigeno%'    then 12000
    when tf.code like 'nitrogeno%'  then 55000
    when tf.code like 'dcarbono%'   then 3000
    when tf.code like 'mcarbono%'   then 400
    when tf.code like 'total_de_gases%' then 500
    when tf.code = 'total'          then 70000
    when tf.code like 'grado%'      then 900
    when tf.code = 'resultado_ppm'  then 15
    when tf.code like 'resultado_mg%' then 0.04
    else 10
  end
from public.test_fields tf
join public.test_definitions td on td.id = tf.test_definition_id
where td.id in (10, 16, 1, 6)          -- cromatografia, DP, numero acido, agua
  and tf.deleted_at is null
  -- Que se considera "una celda medida": los campos numericos y los resultados
  -- de texto (r1/r2 del Karl Fischer, el mgKOH/g del numero acido). El numero
  -- de muestra, la norma y los selectores de instrumento son metadatos de la
  -- hoja de trabajo, no mediciones.
  and (tf.type = 'number' or (tf.type = 'text' and tf.code <> 'no_de_muestra'));

-- Prevalencia de cada prueba sobre el universo de muestras. No todas las
-- muestras corren las cuatro pruebas: la cromatografia se le hace a todo,
-- el grado de polimerizacion casi nunca (exige papel del equipo).
create table bench.test_mix (
  test_definition_id int primary key,
  prevalence         double precision not null
);
insert into bench.test_mix values (10, 1.00), (6, 0.95), (1, 0.90), (16, 0.12);

-- ---------------------------------------------------------------------------
-- Forma A: vertical (una fila por celda medida)
-- ---------------------------------------------------------------------------

-- Cabecera de la muestra. La forma vertical la necesita igual (es donde vive
-- el numero de muestra), asi que su tamano se suma al total de la forma
-- vertical al comparar espacio en disco.
create table bench.samples (
  sample_id     bigint primary key,
  tenant_id     int  not null,
  equipment_id  int  not null,
  measured_at   date not null,
  no_de_muestra varchar(30)
);

create table bench.results_v (
  id                 bigserial primary key,
  tenant_id          int not null,
  equipment_id       int not null,
  analyte_id         int not null,
  test_definition_id int not null,
  measured_at        date not null,
  value_num          numeric(24,8),
  qualifier          varchar(4),
  unit               varchar(30)
);

-- ---------------------------------------------------------------------------
-- Forma B: ancha, una tabla por prueba
-- ---------------------------------------------------------------------------
-- Las columnas son las mismas que declara la plantilla real de cada prueba
-- (13 / 16 / 9 / 9 columnas de datos), con el tipo que le corresponde:
-- number -> numeric, select -> int (referencia a un instrumento), text -> varchar.

create table bench.w_analisis_cromatografico (
  id            bigserial primary key,
  tenant_id     int  not null,
  equipment_id  int  not null,
  measured_at   date not null,
  no_de_muestra varchar(30),
  norma         int,
  hidrogeno_h2_ppm            numeric(24,8),
  oxigeno_o2_ppm              numeric(24,8),
  nitrogeno_n2_ppm            numeric(24,8),
  metano_ch4_ppm              numeric(24,8),
  mcarbono_co_ppm             numeric(24,8),
  dcarbono_co2_ppm            numeric(24,8),
  etileno_c2h4_ppm            numeric(24,8),
  etano_c2h6_ppm              numeric(24,8),
  acetileno_c2h2_ppm          numeric(24,8),
  total_de_gases_combustibles numeric(24,8),
  total                       numeric(24,8)
);

create table bench.w_grado_de_polimerizacion (
  id            bigserial primary key,
  tenant_id     int  not null,
  equipment_id  int  not null,
  measured_at   date not null,
  no_de_muestra varchar(30),
  norma         int,
  masa_g                         numeric(24,8),
  contenido_de_agua_en           numeric(24,8),
  tiempo_muestra_s               numeric(24,8),
  constante_viscosimetro_muestra numeric(24,8),
  tiempo_blanco                  numeric(24,8),
  constante_viscosimetro_blanco  numeric(24,8),
  viscosidad_de_muestra_t        numeric(24,8),
  viscosidad_de_solventet0       numeric(24,8),
  concetracion_muestra_g100ml    numeric(24,8),
  viscosidad_especifica_ns       numeric(24,8),
  k_de_martin                    numeric(24,8),
  viscosidad_intrinseca_n        numeric(24,8),
  grado_de_polimerizacion        numeric(24,8),
  promedio                       numeric(24,8)
);

create table bench.w_numero_acido (
  id            bigserial primary key,
  tenant_id     int  not null,
  equipment_id  int  not null,
  measured_at   date not null,
  no_de_muestra varchar(30),
  norma             int,
  bureta_pp_la_01c  int,
  balanza_pp_la_01c int,
  factor_koh              numeric(24,8),
  vol_blanco              numeric(24,8),
  peso_aceite_g           numeric(24,8),
  volumen_gastado_ml      numeric(24,8),
  resultado_mgkohg_aceite numeric(24,8)
);

create table bench.w_contenido_de_agua (
  id            bigserial primary key,
  tenant_id     int  not null,
  equipment_id  int  not null,
  measured_at   date not null,
  no_de_muestra varchar(30),
  norma             int,
  balanza_pp_la_01c int,
  r1_pp_la_01c      int,
  r1                numeric(24,8),
  r2_pp_la_01c      int,
  r2                numeric(24,8),
  repetibilidad     numeric(24,8),
  resultado_ppm     numeric(24,8)
);
