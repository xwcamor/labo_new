-- Consulta 3, variante global: el mismo tablero pero sobre TODOS los equipos de
-- todos los espacios de trabajo. Es lo que ve un administrador del sistema, y es
-- el unico caso del banco de pruebas donde el agregado toca de verdad todo el
-- universo. Se mide aparte porque no es la consulta que corre el laboratorio.
with ultimo as (
  select distinct on (r.equipment_id) r.equipment_id, r.measured_at, r.value_num
  from bench.results_v r
  where r.analyte_id = :an
  order by r.equipment_id, r.measured_at desc
)
select count(*) as equipos, count(*) filter (where value_num > :umbral) as sobre_umbral
from ultimo;
