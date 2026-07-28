-- Consulta 3, variante global, sobre la forma ancha.
with ultimo as (
  select distinct on (w.equipment_id) w.equipment_id, w.measured_at, w.hidrogeno_h2_ppm as value_num
  from bench.w_analisis_cromatografico w
  order by w.equipment_id, w.measured_at desc
)
select count(*) as equipos, count(*) filter (where value_num > :umbral) as sobre_umbral
from ultimo;
