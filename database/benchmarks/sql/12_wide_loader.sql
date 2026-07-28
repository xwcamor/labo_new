-- Cargador de la forma ancha.
--
-- Por que se arma la sentencia por texto en vez de escribirla a mano: las
-- columnas de cada tabla ancha salen del mismo catalogo bench.analytes que
-- alimenta la forma vertical. Escribirlas dos veces significaria que un cambio
-- en la plantilla deje las dos formas con datos distintos y la comparacion
-- pierda sentido. Aqui las dos leen la misma fuente y aplican la misma formula
-- con la misma semilla, asi que celda por celda contienen exactamente lo mismo.

create or replace function bench.load_wide(
  p_test        int,
  p_table       text,
  p_lo          int,
  p_hi          int,
  p_extra_cols  text default null,
  p_extra_vals  text default null
) returns bigint
language plpgsql as $$
declare
  cols text;
  vals text;
  n    bigint;
begin
  select string_agg(quote_ident(col_name), ', ' order by id),
         string_agg(
           format('round((%s * (0.10 + 2.40 * power(bench.rnd(s.sample_id * 211 + %s), 3)))::numeric, 4)',
                  base_value, id),
           ', ' order by id)
    into cols, vals
  from bench.analytes
  where test_definition_id = p_test;

  if p_extra_cols is not null then
    cols := p_extra_cols || ', ' || cols;
    vals := p_extra_vals || ', ' || vals;
  end if;

  execute format($f$
    insert into bench.%I (tenant_id, equipment_id, measured_at, no_de_muestra, %s)
    select s.tenant_id, s.equipment_id, s.measured_at, s.no_de_muestra, %s
    from bench.samples s
    join bench.test_mix m
      on m.test_definition_id = %s
     and bench.rnd(s.sample_id * 7 + m.test_definition_id) < m.prevalence
    where s.equipment_id between %s and %s
  $f$, p_table, cols, vals, p_test, p_lo, p_hi);

  get diagnostics n = row_count;
  return n;
end
$$;

-- Envoltorio para no repetir en el guion los metadatos propios de cada prueba
-- (la norma aplicada y los instrumentos usados, que en la forma ancha ocupan
-- columna y por lo tanto cuentan para el tamano en disco).
create or replace function bench.load_wide_all(p_lo int, p_hi int)
returns void language plpgsql as $$
begin
  perform bench.load_wide(10, 'w_analisis_cromatografico', p_lo, p_hi,
                          'norma', '1 + (s.sample_id % 3)::int');
  perform bench.load_wide(16, 'w_grado_de_polimerizacion', p_lo, p_hi,
                          'norma', '1 + (s.sample_id % 2)::int');
  perform bench.load_wide(1,  'w_numero_acido', p_lo, p_hi,
                          'norma, bureta_pp_la_01c, balanza_pp_la_01c',
                          '1 + (s.sample_id % 2)::int, 1 + (s.sample_id % 4)::int, 1 + (s.sample_id % 5)::int');
  perform bench.load_wide(6,  'w_contenido_de_agua', p_lo, p_hi,
                          'norma, balanza_pp_la_01c, r1_pp_la_01c, r2_pp_la_01c',
                          '1 + (s.sample_id % 2)::int, 1 + (s.sample_id % 5)::int, 1 + (s.sample_id % 3)::int, 1 + (s.sample_id % 3)::int');
end
$$;
