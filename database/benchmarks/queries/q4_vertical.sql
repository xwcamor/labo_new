-- Consulta 4 (vertical): todos los resultados de un equipo, de todas las
-- pruebas, de los ultimos 5 anos. Es el informe consolidado.
-- En la forma vertical agregar la prueba 30 no cambia esta consulta: son filas.
select r.test_definition_id, r.analyte_id, r.measured_at, r.value_num, r.qualifier, r.unit
from bench.results_v r
where r.equipment_id = :eq
  and r.measured_at >= date '2021-07-28'
order by r.measured_at, r.test_definition_id, r.analyte_id;
