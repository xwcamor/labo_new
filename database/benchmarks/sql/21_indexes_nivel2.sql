-- Nivel 2: los indices que faltan para que la forma vertical resuelva bien las
-- consultas 1, 3 y 4. Se aplican en una segunda pasada para poder reportar el
-- antes y el despues.

set maintenance_work_mem = '2GB';

-- Consultas 1 y 4: todo lo de un equipo, ordenado por fecha, sin filtrar por
-- parametro. Con el indice de nivel 1 esto obliga a recorrer las 34 ramas de
-- analyte_id o a caer en recorrido secuencial.
create index ix_v_eq_date on bench.results_v (equipment_id, measured_at desc);

-- Consulta 3: un parametro, dentro de un espacio de trabajo, ultimo valor por
-- equipo. El INCLUDE evita ir al monton por cada equipo de la flota.
create index ix_v_tn_an_eq_date on bench.results_v (tenant_id, analyte_id, equipment_id, measured_at desc)
  include (value_num);

-- La forma ancha recibe el equivalente exacto, o la comparacion no vale: sin el
-- INCLUDE el planificador elige mapa de bits mas ordenamiento y pierde por una
-- razon que no tiene nada que ver con la forma de la tabla.
--
-- Ojo con la asimetria que esconde este par de indices, porque es un resultado
-- del banco de pruebas y no un detalle de implementacion: el indice vertical
-- cubre el tablero de flota de los 34 parametros con una sola estructura,
-- porque el parametro es una columna. El ancho cubre UN gas; para cubrir los
-- once habria que incluir las once columnas, es decir duplicar la tabla.
create index ix_w_cro_tn_eq_date_inc on bench.w_analisis_cromatografico
  (tenant_id, equipment_id, measured_at desc) include (hidrogeno_h2_ppm);
