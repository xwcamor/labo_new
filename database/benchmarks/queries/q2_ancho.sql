-- Consulta 2 (ancho): la misma serie, leyendo una columna de la tabla de la prueba.
select measured_at, hidrogeno_h2_ppm
from bench.w_analisis_cromatografico
where equipment_id = :eq
  and measured_at >= date '2021-07-28'
order by measured_at;
