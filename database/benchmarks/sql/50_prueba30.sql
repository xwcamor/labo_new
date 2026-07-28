-- Escenario "la prueba 30".
--
-- El laboratorio tiene 29 pruebas. El banco de pruebas solo reprodujo cuatro,
-- asi que la pregunta "cuanto cuesta el informe consolidado cuando hay 30
-- tablas" quedaria en una extrapolacion. Aqui se responde midiendo: se crean
-- las 26 tablas que faltan, con el ancho promedio real de una prueba del
-- laboratorio (unas 6 columnas medidas), y se las llena SOLO para los equipos
-- del espacio de trabajo de prueba. Asi el UNION de 30 ramas es real y su costo
-- tambien, sin pagar el disco de 26 pruebas a escala completa.
--
-- Que se esta midiendo con esto: el costo que la forma ancha agrega a CADA
-- informe consolidado por cada prueba que el laboratorio da de alta. En la forma
-- vertical ese costo es cero, porque una prueba nueva son filas, no ramas.

create or replace function bench.crear_pruebas_extra(p_n int, p_lo int, p_hi int)
returns void language plpgsql as $$
declare i int;
begin
  for i in 1 .. p_n loop
    execute format($t$
      drop table if exists bench.w_extra_%s;
      create table bench.w_extra_%s (
        id bigserial primary key,
        tenant_id int not null, equipment_id int not null, measured_at date not null,
        no_de_muestra varchar(30), norma int,
        v1 numeric(24,8), v2 numeric(24,8), v3 numeric(24,8),
        v4 numeric(24,8), v5 numeric(24,8), v6 numeric(24,8)
      );
    $t$, i, i);

    execute format($t$
      insert into bench.w_extra_%s (tenant_id, equipment_id, measured_at, no_de_muestra, norma,
                                    v1, v2, v3, v4, v5, v6)
      select s.tenant_id, s.equipment_id, s.measured_at, s.no_de_muestra, 1,
             round((10 * (0.1 + 2.4 * bench.rnd(s.sample_id * 311 + %s)))::numeric, 4),
             round((10 * (0.1 + 2.4 * bench.rnd(s.sample_id * 313 + %s)))::numeric, 4),
             round((10 * (0.1 + 2.4 * bench.rnd(s.sample_id * 317 + %s)))::numeric, 4),
             round((10 * (0.1 + 2.4 * bench.rnd(s.sample_id * 331 + %s)))::numeric, 4),
             round((10 * (0.1 + 2.4 * bench.rnd(s.sample_id * 337 + %s)))::numeric, 4),
             round((10 * (0.1 + 2.4 * bench.rnd(s.sample_id * 347 + %s)))::numeric, 4)
      from bench.samples s
      where s.equipment_id between %s and %s
        and bench.rnd(s.sample_id * 7 + 100 + %s) < 0.90;
    $t$, i, i, i, i, i, i, i, p_lo, p_hi, i);

    execute format(
      'create index ix_w_extra_%s_eq_date on bench.w_extra_%s (equipment_id, measured_at desc)', i, i);
    execute format('analyze bench.w_extra_%s', i);
  end loop;
end $$;

-- Devuelve el texto del UNION de 4 + p_extra ramas, con el mismo formato de
-- salida que la consulta vertical (una fila por parametro medido).
create or replace function bench.union_sql(p_extra int) returns text
language plpgsql as $$
declare
  extra text := '';
  i int;
begin
  for i in 1 .. p_extra loop
    extra := extra || format($t$
union all
select 100 + %s, u.analyte_id, w.measured_at, u.value_num
from bench.w_extra_%s w
cross join lateral (values
  (1, w.v1), (2, w.v2), (3, w.v3), (4, w.v4), (5, w.v5), (6, w.v6)
) as u(analyte_id, value_num)
where w.equipment_id = :eq and w.measured_at >= date '2021-07-28'$t$, i, i);
  end loop;
  return extra;
end $$;
