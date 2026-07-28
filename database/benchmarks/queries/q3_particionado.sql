-- Consulta 3 sobre la forma vertical particionada. No lleva filtro de fecha
-- (el tablero quiere el ULTIMO valor, sea de cuando sea), asi que no hay nada
-- que descartar: toca las once particiones.
with ultimo as (
  select distinct on (r.equipment_id) r.equipment_id, r.measured_at, r.value_num
  from bench.results_vp r
  where r.tenant_id = :tn
    and r.analyte_id = :an
  order by r.equipment_id, r.measured_at desc
)
select count(*) as equipos, count(*) filter (where value_num > :umbral) as sobre_umbral
from ultimo;
