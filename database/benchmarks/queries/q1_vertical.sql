-- Consulta 1 (vertical): todos los parametros de la ultima muestra de un equipo.
select r.analyte_id, r.test_definition_id, r.value_num, r.qualifier, r.unit
from bench.results_v r
where r.equipment_id = :eq
  and r.measured_at = (select max(measured_at) from bench.results_v where equipment_id = :eq);
