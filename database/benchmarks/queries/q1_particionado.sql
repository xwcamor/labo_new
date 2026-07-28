-- Consulta 1 sobre la forma vertical particionada. Tampoco lleva fecha conocida
-- de antemano: la ultima muestra puede estar en cualquier particion.
select r.analyte_id, r.test_definition_id, r.value_num, r.qualifier, r.unit
from bench.results_vp r
where r.equipment_id = :eq
  and r.measured_at = (select max(measured_at) from bench.results_vp where equipment_id = :eq);
