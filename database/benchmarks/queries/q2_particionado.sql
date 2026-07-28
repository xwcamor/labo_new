-- Consulta 2 sobre la forma vertical particionada. El filtro de 5 anos permite
-- descartar particiones en tiempo de planificacion.
select r.measured_at, r.value_num
from bench.results_vp r
where r.equipment_id = :eq
  and r.analyte_id = :an
  and r.measured_at >= date '2021-07-28'
order by r.measured_at;
