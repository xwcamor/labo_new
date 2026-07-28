-- Consulta 4 sobre la forma vertical particionada. Filtra 5 anos, asi que
-- descarta las particiones anteriores, pero igual toca seis.
select r.test_definition_id, r.analyte_id, r.measured_at, r.value_num, r.qualifier, r.unit
from bench.results_vp r
where r.equipment_id = :eq
  and r.measured_at >= date '2021-07-28'
order by r.measured_at, r.test_definition_id, r.analyte_id;
