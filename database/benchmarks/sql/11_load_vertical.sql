-- Carga la forma vertical para un rango de equipos.
--
-- El JOIN con bench.test_mix es el que decide que pruebas corrio cada muestra.
-- Se apoya en bench.rnd con una semilla que depende solo de (muestra, prueba),
-- no del parametro: si una muestra corrio cromatografia, corrio sus once gases,
-- no siete de once.
--
-- La distribucion del valor usa el cubo del aleatorio para quedar sesgada hacia
-- abajo con una cola larga, que es como se comportan los gases disueltos. Con
-- una uniforme, la mitad de la flota superaria cualquier umbral y la consulta 3
-- mediria un filtro que en produccion no filtra nada.

insert into bench.results_v
  (tenant_id, equipment_id, analyte_id, test_definition_id, measured_at, value_num, qualifier, unit)
select
  s.tenant_id,
  s.equipment_id,
  a.id,
  a.test_definition_id,
  s.measured_at,
  round((a.base_value * (0.10 + 2.40 * power(bench.rnd(s.sample_id * 211 + a.id), 3)))::numeric, 4),
  case when bench.rnd(s.sample_id * 997 + a.id) < 0.03 then '<' end,
  a.unit
from bench.samples s
join bench.test_mix m
  on bench.rnd(s.sample_id * 7 + m.test_definition_id) < m.prevalence
join bench.analytes a
  on a.test_definition_id = m.test_definition_id
where s.equipment_id between :lo and :hi;
