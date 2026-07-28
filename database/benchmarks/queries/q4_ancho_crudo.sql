-- Consulta 4 (ancho, variante A): cuatro lecturas crudas, una por prueba.
-- Es lo mas barato que puede hacer la forma ancha: no unifica nada, deja que la
-- aplicacion transponga las columnas a filas en PHP. El costo de esa transposicion
-- no aparece en este numero.
select * from bench.w_analisis_cromatografico where equipment_id = :eq and measured_at >= date '2021-07-28' order by measured_at;
select * from bench.w_grado_de_polimerizacion where equipment_id = :eq and measured_at >= date '2021-07-28' order by measured_at;
select * from bench.w_numero_acido            where equipment_id = :eq and measured_at >= date '2021-07-28' order by measured_at;
select * from bench.w_contenido_de_agua       where equipment_id = :eq and measured_at >= date '2021-07-28' order by measured_at;
