-- Indices de las dos formas.
--
-- Se crean DESPUES de cargar los datos: construir un btree sobre la tabla ya
-- llena es varias veces mas rapido que mantenerlo fila por fila durante la carga.
--
-- Nivel 1 = los dos indices que el encargo pide probar sobre la forma vertical.
-- Nivel 2 = los que ademas pondria cualquiera que mire los planes de las cuatro
-- consultas. Se miden por separado a proposito: la diferencia entre los dos
-- niveles es justamente la pregunta de fondo, porque el sistema viejo se volvio
-- lento por falta de indice util, no por ser vertical.

set maintenance_work_mem = '2GB';

-- ---------- Forma vertical, nivel 1 ----------

-- Sirve a la consulta 2 (tendencia de un parametro de un equipo): las tres
-- columnas del predicado y del orden estan en el indice, en el orden correcto.
create index ix_v_eq_an_date on bench.results_v (equipment_id, analyte_id, measured_at desc);

-- El indice que el encargo pide probar para el tablero de flota. Se anticipa
-- que va a rendir mal: no lleva tenant_id, asi que para un solo espacio de
-- trabajo obliga a recorrer el parametro de TODOS los inquilinos.
create index ix_v_an_date on bench.results_v (analyte_id, measured_at);

-- ---------- Forma ancha ----------
-- Se le da a cada tabla exactamente lo mismo que a la vertical: acceso por
-- equipo y fecha, y acceso por inquilino para el tablero. Dar menos a una de
-- las dos formas seria amanar la medicion.

create index ix_w_cro_eq_date on bench.w_analisis_cromatografico (equipment_id, measured_at desc);
create index ix_w_dp_eq_date  on bench.w_grado_de_polimerizacion (equipment_id, measured_at desc);
create index ix_w_ac_eq_date  on bench.w_numero_acido            (equipment_id, measured_at desc);
create index ix_w_ag_eq_date  on bench.w_contenido_de_agua       (equipment_id, measured_at desc);

create index ix_w_cro_tn_eq_date on bench.w_analisis_cromatografico (tenant_id, equipment_id, measured_at desc);
