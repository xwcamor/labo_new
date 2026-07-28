-- Consulta 3 (vertical): ultimo valor de un parametro por equipo del espacio de
-- trabajo, y cuantos superan el umbral.
with ultimo as (
  select distinct on (r.equipment_id) r.equipment_id, r.measured_at, r.value_num
  from bench.results_v r
  where r.tenant_id = :tn
    and r.analyte_id = :an
  order by r.equipment_id, r.measured_at desc
)
select count(*) as equipos, count(*) filter (where value_num > :umbral) as sobre_umbral
from ultimo;
