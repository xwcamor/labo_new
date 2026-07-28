-- Consulta 2 (vertical): serie historica de un parametro de un equipo, 5 anos.
select r.measured_at, r.value_num
from bench.results_v r
where r.equipment_id = :eq
  and r.analyte_id = :an
  and r.measured_at >= date '2021-07-28'
order by r.measured_at;
