-- Genera la cabecera de muestras para un rango de equipos.
--
-- Modelo del universo: cada equipo se muestrea dos veces por ano durante diez
-- anos (2016-2025) = 20 muestras. El desfase aleatorio de hasta 20 dias evita
-- que todas las muestras del pais caigan el mismo dia, que es lo que haria que
-- el indice por fecha se comportara mejor de lo que se comporta en la realidad.

insert into bench.samples (sample_id, tenant_id, equipment_id, measured_at, no_de_muestra)
select
  ((e - 1)::bigint * 20 + k),
  ((e - 1) / :equip_per_tenant + 1),
  e,
  (date '2016-03-15'
     + ((k - 1) / 2) * interval '1 year'
     + ((k - 1) % 2) * interval '6 months'
     + (bench.rnd(e::bigint * 31 + k) * 20)::int * interval '1 day')::date,
  'M-' || lpad(((e - 1)::bigint * 20 + k)::text, 9, '0')
from generate_series(:lo, :hi) e,
     generate_series(1, 20) k;
