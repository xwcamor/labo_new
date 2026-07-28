-- Forma vertical particionada por ano de medicion.
--
-- Es el escenario que le interesa al dueno del proyecto: importar el historico
-- ano por ano y poder desprenderse de un ano viejo con un DETACH en vez de un
-- DELETE de millones de filas.
--
-- Un detalle que hay que pagar y conviene decir: una tabla particionada no
-- admite una clave primaria que no incluya la columna de particion. La clave
-- pasa de (id) a (id, measured_at), o sea un indice mas ancho. No es teoria:
-- se ve en el tamano en disco que reporta el banco de pruebas.

drop table if exists bench.results_vp cascade;

create table bench.results_vp (
  id                 bigserial,
  tenant_id          int not null,
  equipment_id       int not null,
  analyte_id         int not null,
  test_definition_id int not null,
  measured_at        date not null,
  value_num          numeric(24,8),
  qualifier          varchar(4),
  unit               varchar(30),
  primary key (id, measured_at)
) partition by range (measured_at);

do $$
declare a int;
begin
  for a in 2016 .. 2026 loop
    execute format(
      'create table bench.results_vp_%s partition of bench.results_vp for values from (%L) to (%L)',
      a, format('%s-01-01', a), format('%s-01-01', a + 1));
  end loop;
end $$;

-- Misma formula y misma semilla que 11_load_vertical.sql: la tabla particionada
-- tiene que contener exactamente el mismo dato que la no particionada, que a
-- esta altura ya fue borrada para que entren las dos en el disco.
create or replace function bench.load_vp(p_lo int, p_hi int) returns void
language sql as $$
  insert into bench.results_vp
    (tenant_id, equipment_id, analyte_id, test_definition_id, measured_at, value_num, qualifier, unit)
  select s.tenant_id, s.equipment_id, a.id, a.test_definition_id, s.measured_at,
         round((a.base_value * (0.10 + 2.40 * power(bench.rnd(s.sample_id * 211 + a.id), 3)))::numeric, 4),
         case when bench.rnd(s.sample_id * 997 + a.id) < 0.03 then '<' end,
         a.unit
  from bench.samples s
  join bench.test_mix m
    on bench.rnd(s.sample_id * 7 + m.test_definition_id) < m.prevalence
  join bench.analytes a
    on a.test_definition_id = m.test_definition_id
  where s.equipment_id between p_lo and p_hi;
$$;
