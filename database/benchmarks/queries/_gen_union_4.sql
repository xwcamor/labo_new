-- Consulta 4 (ancho, variante B): el UNION que devuelve la MISMA forma que la
-- consulta vertical, es decir una fila por parametro medido. Es lo que hay que
-- escribir si el informe consolidado quiere recorrer los resultados de manera
-- uniforme. Este es el texto que crece con cada prueba nueva: hoy son cuatro
-- ramas y 34 pares columna/parametro escritos a mano; con 29 pruebas son 29
-- ramas y unos 190 pares.
select 10 as test_definition_id, u.analyte_id, w.measured_at, u.value_num
from bench.w_analisis_cromatografico w
cross join lateral (values
  (10, w.hidrogeno_h2_ppm), (11, w.oxigeno_o2_ppm), (12, w.nitrogeno_n2_ppm),
  (13, w.metano_ch4_ppm), (14, w.mcarbono_co_ppm), (15, w.dcarbono_co2_ppm),
  (16, w.etileno_c2h4_ppm), (17, w.etano_c2h6_ppm), (18, w.acetileno_c2h2_ppm),
  (19, w.total_de_gases_combustibles), (20, w.total)
) as u(analyte_id, value_num)
where w.equipment_id = :eq and w.measured_at >= date '2021-07-28'
union all
select 16, u.analyte_id, w.measured_at, u.value_num
from bench.w_grado_de_polimerizacion w
cross join lateral (values
  (21, w.masa_g), (22, w.contenido_de_agua_en), (23, w.tiempo_muestra_s),
  (24, w.constante_viscosimetro_muestra), (25, w.tiempo_blanco),
  (26, w.constante_viscosimetro_blanco), (27, w.viscosidad_de_muestra_t),
  (28, w.viscosidad_de_solventet0), (29, w.concetracion_muestra_g100ml),
  (30, w.viscosidad_especifica_ns), (31, w.k_de_martin),
  (32, w.viscosidad_intrinseca_n), (33, w.grado_de_polimerizacion), (34, w.promedio)
) as u(analyte_id, value_num)
where w.equipment_id = :eq and w.measured_at >= date '2021-07-28'
union all
select 1, u.analyte_id, w.measured_at, u.value_num
from bench.w_numero_acido w
cross join lateral (values
  (1, w.factor_koh), (2, w.vol_blanco), (3, w.peso_aceite_g),
  (4, w.volumen_gastado_ml), (5, w.resultado_mgkohg_aceite)
) as u(analyte_id, value_num)
where w.equipment_id = :eq and w.measured_at >= date '2021-07-28'
union all
select 6, u.analyte_id, w.measured_at, u.value_num
from bench.w_contenido_de_agua w
cross join lateral (values
  (6, w.r1), (7, w.r2), (8, w.repetibilidad), (9, w.resultado_ppm)
) as u(analyte_id, value_num)
where w.equipment_id = :eq and w.measured_at >= date '2021-07-28'

order by 3, 1, 2;
