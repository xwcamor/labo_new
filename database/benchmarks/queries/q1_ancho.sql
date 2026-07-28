-- Consulta 1 (ancho): la ultima fila de cada una de las cuatro tablas de prueba.
-- Son cuatro sentencias porque eso es lo que hace la aplicacion: un modelo por
-- prueba, una consulta por modelo. El tiempo reportado es la suma de las cuatro.
select * from bench.w_analisis_cromatografico where equipment_id = :eq order by measured_at desc limit 1;
select * from bench.w_grado_de_polimerizacion where equipment_id = :eq order by measured_at desc limit 1;
select * from bench.w_numero_acido            where equipment_id = :eq order by measured_at desc limit 1;
select * from bench.w_contenido_de_agua       where equipment_id = :eq order by measured_at desc limit 1;
