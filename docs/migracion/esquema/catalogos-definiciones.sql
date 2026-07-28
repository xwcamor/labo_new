-- Catálogos y definiciones del sistema Rails viejo (lab_app_development).
-- FILTRADO A PROPOSITO: este repo es PUBLICO.
--
-- INCLUYE solo tablas de DEFINICION: qué pruebas existen, qué columnas tiene
-- cada hoja de trabajo, qué opciones y normas hay cargadas, los catálogos de
-- equipo y el modelo de permisos del sistema viejo.
--
-- NO INCLUYE, y no debe incluirse nunca:
--   samplers, rem_user_signatures  -> nombres del personal del laboratorio
--   transformers, rems, rem_*      -> equipos y muestras de clientes reales
--   labs, lab_details, lab_files   -> hojas de trabajo con resultados
--   stocks, stickers, import_*     -> operativo
--
-- Exportado del volcado completo que el dueño facilitó el 2026-07-28.

INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(1, 'Config - Módulo Usuarios', 0, '2023-04-07 02:47:26.700978', '2023-04-07 02:47:26.700978');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(2, 'Usuarios Buscador', 1, '2023-04-07 02:47:26.712048', '2023-04-07 02:47:26.712048');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(3, 'Usuarios Nuevo', 1, '2023-04-07 02:47:26.725542', '2023-04-07 02:47:26.725542');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(4, 'Usuarios Ver', 1, '2023-04-07 02:47:26.737951', '2023-04-07 02:47:26.737951');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(5, 'Usuarios Editar', 1, '2023-04-07 02:47:26.748831', '2023-04-07 02:47:26.748831');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(6, 'Usuarios Eliminar', 1, '2023-04-07 02:47:26.761279', '2023-04-07 02:47:26.761279');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(7, 'Usuarios Cambiar Password', 1, '2023-04-07 02:47:26.773879', '2023-04-07 02:47:26.773879');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(8, 'Config - Módulo Perfiles', 0, '2023-04-07 02:47:26.784670', '2023-04-07 02:47:26.784670');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(9, 'Perfiles - Buscador', 8, '2023-04-07 02:47:26.797092', '2023-04-07 02:47:26.797092');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(10, 'Perfiles - Nuevo', 8, '2023-04-07 02:47:26.810970', '2023-04-07 02:47:26.810970');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(11, 'Perfiles - Ver', 8, '2023-04-07 02:47:26.826081', '2023-04-07 02:47:26.826081');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(12, 'Perfiles - Editar', 8, '2023-04-07 02:47:26.841563', '2023-04-07 02:47:26.841563');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(13, 'Perfiles - Eliminar', 8, '2023-04-07 02:47:26.853826', '2023-04-07 02:47:26.853826');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(14, 'Config - Módulo Accesos', 0, '2023-04-07 02:47:26.864184', '2023-04-07 02:47:26.864184');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(15, 'Accesos - Buscador', 14, '2023-04-07 02:47:26.875152', '2023-04-07 02:47:26.875152');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(16, 'Accesos - Nuevo', 14, '2023-04-07 02:47:26.886397', '2023-04-07 02:47:26.886397');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(17, 'Accesos - Ver', 14, '2023-04-07 02:47:26.896139', '2023-04-07 02:47:26.896139');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(18, 'Accesos - Editar', 14, '2023-04-07 02:47:26.908368', '2023-04-07 02:47:26.908368');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(19, 'Accesos - Eliminar', 14, '2023-04-07 02:47:26.920471', '2023-04-07 02:47:26.920471');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(20, 'Accesses - Grant - Nuevo', 14, '2023-04-07 02:47:26.931527', '2023-04-07 02:47:26.931527');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(21, 'Accesses - Grant - Editar', 14, '2023-04-07 02:47:26.944033', '2023-04-07 02:47:26.944033');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(22, 'Accesses - Grant - Eliminar', 14, '2023-04-07 02:47:26.955121', '2023-04-07 02:47:26.955121');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(23, 'Config - Mòdulo Configuración', 0, '2023-04-07 02:47:26.966586', '2023-04-07 02:47:26.966586');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(24, 'Configuración - Vista Pirincipal', 23, '2023-04-07 02:47:26.979905', '2023-04-07 02:47:26.979905');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(25, 'Auditoría', 0, '2023-04-07 02:47:26.991268', '2023-04-07 02:47:26.991268');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(26, 'Auditoria - Principal', 25, '2023-04-07 02:47:27.001746', '2023-04-07 02:47:27.001746');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(27, 'Auditoria - Admin', 25, '2023-04-07 02:47:27.014385', '2023-04-07 02:47:27.014385');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(28, 'Pruebas de Muestras', 0, '2023-04-07 02:47:27.023760', '2023-04-07 02:47:27.023760');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(29, 'PM - Configurar Limites de Grafica Tendencia', 28, '2023-04-07 02:47:27.036463', '2023-04-07 02:47:27.036463');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(30, 'PM - Bloquear', 28, '2023-04-07 02:47:27.048317', '2023-04-07 02:47:27.048317');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(31, 'PM - Valores Constantes', 28, '2023-04-07 02:47:27.059273', '2023-04-07 02:47:27.059273');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(32, 'PM - Grafica de Tendencia', 28, '2023-04-07 02:47:27.071063', '2023-04-07 02:47:27.071063');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(33, 'PM - Busqueda', 28, '2023-04-07 02:47:27.083802', '2023-04-07 02:47:27.083802');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(34, 'PM - Nuevo', 28, '2023-04-07 02:47:27.094435', '2023-04-07 02:47:27.094435');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(35, 'PM - Ver', 28, '2023-04-07 02:47:27.106709', '2023-04-07 02:47:27.106709');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(36, 'PM- Editar', 28, '2023-04-07 02:47:27.118611', '2023-04-07 02:47:27.118611');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(37, 'PM - Eliminar', 28, '2023-04-07 02:47:27.129236', '2023-04-07 02:47:27.129236');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(38, 'PM - Ajustes Adicionaales', 28, '2023-04-07 02:47:27.141979', '2023-04-07 02:47:27.141979');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(39, 'Registro de Ingreso de Muestras', 0, '2023-04-07 02:47:27.153190', '2023-04-07 02:47:27.153190');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(40, 'RIM - Busqueda', 39, '2023-04-07 02:47:27.153190', '2023-04-07 02:47:27.153190');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(41, 'RIM - Nuevo', 39, '2023-04-07 02:47:27.153190', '2023-04-07 02:47:27.153190');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(42, 'RIM - Ver', 39, '2023-04-07 02:47:27.153190', '2023-04-07 02:47:27.153190');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(43, 'RIM - Editar', 39, '2023-04-07 02:47:27.153190', '2023-04-07 02:47:27.153190');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(44, 'RIM - Eliminar', 39, '2023-04-07 02:47:27.153190', '2023-04-07 02:47:27.153190');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(45, 'RIM - Ajustes Adicionales', 39, '2023-04-07 02:47:27.153190', '2023-04-07 02:47:27.153190');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(46, 'RIM - Bloquear', 39, '2023-04-07 02:47:27.153190', '2023-10-17 00:21:41.232258');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(47, 'RIM - Reportes', 39, '2023-10-17 00:13:19.557515', '2023-10-17 00:13:19.557515');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(48, 'RIM - Admin Correlativo', 39, '2023-10-17 00:18:08.806254', '2023-10-17 00:18:08.806254');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(49, 'Reportes', 0, '2023-10-17 00:47:12.103307', '2023-10-17 00:47:12.103307');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(50, 'Analisis de Laboratorio', 49, '2023-10-17 00:47:19.216224', '2023-10-17 00:47:19.216224');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(51, 'Registro de Muestras detallado', 49, '2023-10-17 00:47:35.224230', '2023-10-17 00:47:35.224230');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(52, 'Formato de Registro de ingreso de muestras', 49, '2023-10-17 00:47:50.195625', '2023-10-17 00:47:50.195625');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(53, 'Registro de muestras', 49, '2023-10-17 00:48:04.805634', '2023-10-17 00:48:04.805634');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(54, 'Reportes Entregados', 49, '2023-10-17 00:48:13.678598', '2023-10-17 00:48:13.678598');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(55, 'Listado de Reportes', 49, '2023-10-17 00:48:22.769394', '2023-10-17 00:48:22.769394');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(56, 'Inventario', 0, '2023-10-17 00:51:49.307905', '2023-10-17 00:51:49.307905');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(57, 'Listado de Stock - Busqueda', 56, '2023-10-17 00:52:34.582368', '2023-10-17 00:52:34.582368');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(58, 'Seguimiento de Stock - Busqueda', 56, '2023-10-17 00:53:07.995964', '2023-10-17 00:53:07.995964');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(59, 'TR APP', 0, '2023-10-22 00:01:03.115299', '2023-10-22 00:01:03.115299');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(60, 'Transformadores', 59, '2023-10-22 00:01:51.515927', '2023-10-22 00:03:20.020212');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(61, 'Cromas ', 59, '2023-10-22 00:02:14.793027', '2023-10-22 00:02:55.138284');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(62, 'Fiquis', 59, '2023-10-22 02:32:08.430967', '2023-10-22 02:32:08.430967');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(63, 'Furanos', 59, '2023-10-23 08:57:33.515330', '2023-10-23 08:57:33.515330');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(64, 'RIM - Control de Temperaturas', 39, '2023-10-23 08:57:44.262441', '2023-10-23 08:57:44.262441');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(65, 'RIM - Stickers', 39, '2023-10-23 08:57:44.262441', '2023-10-23 08:57:44.262441');
INSERT INTO `accesses` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(66, 'Listado de Reportes OTD', 49, '2023-10-17 00:48:22.769394', '2023-10-17 00:48:22.769394');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(217, '2025-10-15', '1010', '19.5', '67', 0, '2025-10-27 15:03:33', '2025-10-27 15:03:33');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(218, '2025-10-29', '1009', '20.3', '49', 0, '2025-10-29 22:12:39', '2025-10-29 22:12:39');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(219, '2025-11-03', '1010', '19.9', '47', 0, '2025-11-03 19:30:43', '2025-11-04 20:56:30');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(220, '2025-11-04', '1010', '21.0', '59', 0, '2025-11-04 20:56:12', '2025-11-04 20:56:12');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(221, '2025-11-06', '1010', '19.9', '70', 0, '2025-11-07 01:23:56', '2025-11-07 01:23:56');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(222, '2025-11-11', '1011', '20.0', '60', 0, '2025-11-11 01:27:05', '2025-11-11 01:27:05');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(223, '2025-11-13', '1011', '21.2', '56', 0, '2025-11-14 03:36:55', '2025-11-14 03:36:55');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(224, '2025-11-18', '1009', '20.7', '73', 0, '2025-11-19 18:49:30', '2025-11-19 18:50:13');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(225, '2025-11-19', '1008', '22.3', '50', 0, '2025-11-19 18:49:53', '2025-11-19 18:49:53');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(226, '2025-11-20', '1011', '19.3', '64', 0, '2025-11-20 15:04:14', '2025-11-20 15:04:14');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(227, '2025-11-21', '1009', '20.4', '64', 0, '2025-11-23 19:27:18', '2025-11-23 19:27:18');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(228, '2025-11-22', '1006', '19.7', '59', 0, '2025-11-23 19:28:07', '2025-11-23 19:28:07');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(229, '2025-11-23', '1007', '19.8', '59', 0, '2025-11-23 19:29:14', '2025-11-23 19:29:14');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(230, '2025-11-24', '1008', '20.6', '62', 0, '2025-11-25 04:50:50', '2025-11-25 04:50:50');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(231, '2025-11-25', '1006', '22.4', '74', 0, '2025-11-26 08:35:11', '2025-11-26 08:35:11');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(232, '2025-11-26', '1004', '20.9', '59', 0, '2025-11-26 08:35:40', '2025-11-26 08:35:40');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(233, '2025-11-27', '1006', '20.1', '62', 0, '2025-11-27 08:42:32', '2025-11-27 08:42:32');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(234, '2025-12-01', '1007', '20.4', '61', 0, '2025-12-01 19:27:55', '2025-12-01 19:27:55');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(235, '2025-12-05', '1008', '19.6', '55', 0, '2025-12-05 19:32:58', '2025-12-05 19:32:58');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(236, '2025-12-11', '1009', '22.3', '76', 0, '2025-12-11 17:59:17', '2025-12-11 17:59:17');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(237, '2025-12-12', '1006', '19.7', '52', 0, '2025-12-12 19:47:29', '2025-12-12 19:47:29');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(238, '2025-12-15', '1007', '20.1', '73', 0, '2025-12-15 23:13:05', '2025-12-15 23:13:05');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(239, '2025-12-18', '1007', '20.9', '71', 0, '2025-12-18 16:24:50', '2025-12-18 16:24:50');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(240, '2025-12-19', '1010', '21.7', '51', 0, '2025-12-19 14:03:30', '2025-12-19 14:03:30');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(241, '2025-12-22', '1008', '21.5', '70', 0, '2025-12-22 13:11:29', '2025-12-22 13:11:29');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(242, '2025-12-23', '1009', '20.3', '50', 0, '2025-12-23 15:21:05', '2025-12-23 15:21:05');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(243, '2025-12-24', '1009', '19.3', '55', 0, '2025-12-24 20:33:14', '2025-12-24 20:33:14');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(244, '2026-01-06', '1008', '20.1', '52', 0, '2026-01-06 17:58:59', '2026-01-06 17:58:59');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(245, '2025-12-30', '1009', '20.7', '53', 0, '2026-01-08 18:53:54', '2026-01-08 18:53:54');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(246, '2026-01-13', '1010', '21.1', '52', 0, '2026-01-13 13:24:48', '2026-01-13 13:24:48');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(247, '2026-01-12', '1009', '20.5', '51', 0, '2026-01-13 13:25:10', '2026-01-13 13:25:10');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(248, '2026-01-15', '1009', '20.5', '49', 0, '2026-01-15 15:58:06', '2026-01-15 15:58:06');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(249, '2026-01-16', '1008', '19.9', '51', 0, '2026-01-16 13:15:52', '2026-01-16 13:15:52');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(250, '2026-01-19', '1009', '21.1', '55', 0, '2026-01-19 21:22:55', '2026-01-19 21:22:55');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(251, '2026-01-21', '1010', '20.0', '49', 0, '2026-01-21 22:08:33', '2026-01-21 22:08:33');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(252, '2026-01-22', '1009', '23.4', '64', 0, '2026-01-23 13:44:12', '2026-01-23 13:44:12');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(253, '2026-01-27', '1009', '23.1', '60', 0, '2026-01-28 19:50:34', '2026-01-28 19:50:34');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(254, '2026-01-29', '1008', '22.5', '61', 0, '2026-01-29 19:06:12', '2026-01-29 19:06:12');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(255, '2026-02-02', '1010', '21.5', '53', 0, '2026-02-02 21:31:11', '2026-02-02 21:31:11');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(256, '2026-02-03', '1012', '22.2', '52', 0, '2026-02-03 14:26:07', '2026-02-03 14:26:07');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(257, '2026-02-06', '1010', '22.7', '58', 0, '2026-02-06 16:15:07', '2026-02-06 16:15:07');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(258, '2026-02-09', '1006', '23.4', '49', 0, '2026-02-09 21:23:11', '2026-02-10 15:17:04');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(259, '2026-02-10', '1009', '23.3', '57', 0, '2026-02-10 15:16:44', '2026-02-10 15:16:44');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(260, '2026-02-11', '1006', '24.4', '58', 0, '2026-02-11 19:14:47', '2026-02-11 19:14:47');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(261, '2026-02-12', '1007', '24.4', '57', 0, '2026-02-12 18:37:05', '2026-02-12 18:37:17');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(262, '2026-02-16', '1007', '24.9', '49', 0, '2026-02-16 19:36:58', '2026-02-16 19:36:58');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(263, '2026-02-17', '1008', '24.9', '52', 0, '2026-02-17 16:18:11', '2026-02-17 16:18:11');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(264, '2026-02-18', '1007', '24.9', '56', 0, '2026-02-18 16:12:51', '2026-02-18 16:12:51');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(265, '2026-02-19', '1004', '21.1', '61', 0, '2026-02-19 21:25:00', '2026-02-19 21:25:00');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(266, '2026-02-20', '1006', '22.8', '60', 0, '2026-02-20 14:48:44', '2026-02-20 14:48:44');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(267, '2026-02-23', '1006', '22.7', '61', 0, '2026-02-23 17:41:31', '2026-02-23 17:41:31');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(268, '2026-02-26', '1005', '23.9', '53', 0, '2026-02-26 21:19:53', '2026-02-26 21:19:53');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(269, '2026-02-27', '1005', '24.9', '56', 0, '2026-02-27 14:00:55', '2026-02-27 14:00:55');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(270, '2026-03-02', '1005', '24.3', '60', 0, '2026-03-02 14:22:27', '2026-03-02 14:22:27');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(271, '2026-03-03', '1005', '24.5', '52', 0, '2026-03-03 15:12:03', '2026-03-03 15:12:03');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(272, '2026-03-04', '1005', '22.7', '59', 0, '2026-03-04 19:51:31', '2026-03-04 19:51:31');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(273, '2026-02-24', '1010', '22.4', '47', 0, '2026-03-04 21:25:43', '2026-03-04 21:25:43');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(274, '2026-03-05', '1005', '24.9', '64', 0, '2026-03-05 13:52:53', '2026-03-05 13:52:53');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(275, '2026-03-11', '1005', '24.9', '58', 0, '2026-03-11 21:31:58', '2026-03-11 21:31:58');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(276, '2026-02-25', '1007', '24.3', '52', 0, '2026-03-16 20:08:01', '2026-03-16 20:08:01');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(277, '2026-03-18', '1005', '24.7', '58', 0, '2026-03-18 19:07:11', '2026-03-18 19:07:11');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(278, '2026-03-23', '1005', '24.3', '60', 0, '2026-03-23 15:58:48', '2026-03-23 15:58:48');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(279, '2026-03-20', '1005', '24.3', '59', 0, '2026-03-30 15:26:20', '2026-03-30 15:26:20');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(280, '2026-03-30', '1005', '24.9', '52', 0, '2026-03-30 17:59:29', '2026-03-30 17:59:29');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(281, '2026-03-31', '1005', '24.6', '59', 0, '2026-03-31 20:12:04', '2026-03-31 20:12:04');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(282, '2026-03-24', '1005', '24.3', '50', 0, '2026-04-01 14:55:26', '2026-04-01 14:55:26');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(283, '2026-03-09', '1005', '24.9', '58', 0, '2026-04-01 15:19:14', '2026-04-01 15:19:14');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(284, '2026-04-13', '1006', '20.7', '55', 0, '2026-04-13 21:14:35', '2026-04-13 21:14:35');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(285, '2026-04-17', '1007', '24.9', '67', 0, '2026-04-17 18:31:26', '2026-04-17 18:31:26');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(286, '2026-04-14', '1008', '22.4', '58', 0, '2026-04-17 20:09:33', '2026-04-17 20:09:33');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(287, '2026-04-22', '1010', '22.9', '55', 0, '2026-04-22 16:21:38', '2026-04-22 16:21:38');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(288, '2026-04-21', '1010', '22.9', '55', 0, '2026-04-22 16:49:04', '2026-04-22 16:49:04');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(289, '2026-05-04', '1009', '23.4', '59', 0, '2026-05-08 14:47:08', '2026-05-08 14:47:08');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(290, '2026-04-27', '1004', '23.9', '54', 0, '2026-05-08 14:48:14', '2026-05-08 14:48:14');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(291, '2026-05-08', '1008', '22.5', '58', 0, '2026-05-12 18:29:51', '2026-05-12 18:29:51');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(292, '2026-05-12', '1007', '23.7', '53', 0, '2026-05-12 18:30:29', '2026-05-12 18:30:29');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(293, '2026-05-17', '1007', '23.4', '52', 0, '2026-05-17 20:07:41', '2026-05-17 20:07:41');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(294, '2026-05-18', '1009', '23.0', '58', 0, '2026-05-18 13:28:16', '2026-05-18 13:28:16');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(295, '2026-05-20', '1006', '23.6', '52', 0, '2026-05-20 19:36:12', '2026-05-20 19:36:12');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(296, '2026-05-21', '1007', '23.8', '53', 0, '2026-05-22 19:58:11', '2026-05-22 19:58:11');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(297, '2026-05-22', '1007', '23.7', '53', 0, '2026-05-22 23:36:37', '2026-05-22 23:36:37');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(298, '2026-05-25', '1008', '23.6', '53', 0, '2026-05-27 20:59:26', '2026-05-27 20:59:26');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(299, '2026-05-26', '1008', '23.4', '53', 0, '2026-05-28 17:23:09', '2026-05-28 17:23:09');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(300, '2026-05-29', '1007', '22.7', '56', 0, '2026-05-29 19:35:30', '2026-05-29 19:35:30');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(301, '2026-05-27', '1006', '23.4', '53', 0, '2026-05-29 21:14:28', '2026-05-29 21:14:28');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(302, '2026-06-01', '1005', '23.2', '57', 0, '2026-06-03 16:17:01', '2026-06-03 16:17:01');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(303, '2026-06-02', '1010', '23.3', '58', 0, '2026-06-03 17:44:23', '2026-06-03 17:44:23');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(304, '2026-06-04', '1010', '23.4', '56', 0, '2026-06-04 20:37:29', '2026-06-04 20:37:29');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(305, '2026-06-09', '1013', '23.4', '52', 0, '2026-06-09 19:14:55', '2026-06-09 19:14:55');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(306, '2026-06-08', '1011', '23.3', '54', 0, '2026-06-09 19:15:23', '2026-06-09 19:15:23');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(307, '2026-06-12', '1007', '23.3', '63', 0, '2026-06-12 20:24:11', '2026-06-12 20:24:11');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(308, '2026-06-15', '1008', '23.3', '64', 0, '2026-06-19 15:24:12', '2026-06-19 15:24:12');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(309, '2026-06-16', '1009', '23.2', '65', 0, '2026-06-19 20:23:47', '2026-06-19 20:23:47');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(310, '2026-06-17', '1010', '23.3', '66', 0, '2026-06-23 15:35:44', '2026-06-23 15:35:44');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(311, '2026-06-22', '1011', '23', '61', 0, '2026-06-23 15:36:07', '2026-06-23 15:36:07');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(312, '2026-06-23', '1011', '22.9', '60', 0, '2026-06-25 20:13:54', '2026-06-25 20:13:54');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(313, '2026-07-06', '1010', '22.9', '60', 0, '2026-07-07 16:58:47', '2026-07-07 16:58:47');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(314, '2026-07-13', '1009', '22.9', '59', 0, '2026-07-13 16:06:29', '2026-07-13 16:06:29');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(315, '2026-07-17', '1008', '22.9', '52', 0, '2026-07-17 14:11:52', '2026-07-17 14:11:52');
INSERT INTO `cro_temperatures` (`id`, `date_temperature`, `cro_lab_pre`, `cro_lab_tem`, `cro_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(316, '2026-07-21', '1010', '23.4', '59', 0, '2026-07-21 16:15:55', '2026-07-21 16:15:55');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(254, '2025-11-07', '21.0', '20.2', '59', 0, '2025-11-07 01:25:54', '2025-11-07 01:30:03');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(255, '2025-11-05', '21.0', '22.6', '56', 0, '2025-11-07 01:27:44', '2025-11-07 01:30:12');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(256, '2025-11-04', '20.0', '20.2', '59', 0, '2025-11-07 01:29:53', '2025-11-07 01:30:23');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(257, '2025-10-29', '21.2', '21.7', '52', 0, '2025-11-07 15:15:04', '2025-11-07 15:15:04');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(258, '2025-11-13', '21.0', '20.9', '62', 0, '2025-11-14 03:37:37', '2025-11-14 03:37:37');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(259, '2025-11-27', '20.3', '20.3', '67', 0, '2025-11-27 12:58:44', '2025-11-27 12:58:44');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(260, '2025-12-01', '20.6', '20.6', '53', 0, '2025-12-01 19:28:26', '2025-12-01 19:28:26');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(261, '2025-12-05', '22.5', '22.5', '68', 0, '2025-12-05 19:25:56', '2025-12-05 19:25:56');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(262, '2025-12-10', '21.0', '22.3', '53', 0, '2025-12-10 13:13:39', '2025-12-10 13:13:39');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(263, '2025-12-02', '22.3', '22.3', '62', 0, '2025-12-10 19:53:56', '2025-12-10 19:53:56');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(264, '2025-12-03', '20.2', '20.2', '61', 0, '2025-12-11 18:34:46', '2025-12-11 18:34:46');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(265, '2025-12-11', '20.1', '22.2', '58', 0, '2025-12-11 21:44:27', '2025-12-11 21:44:27');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(266, '2025-12-12', '21.1', '22.9', '60', 0, '2025-12-12 19:46:49', '2025-12-12 19:46:49');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(267, '2025-12-15', '21.1', '22.3', '69', 0, '2025-12-15 23:14:16', '2025-12-15 23:14:16');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(268, '2025-12-18', '21.0', '21.6', '58', 0, '2025-12-18 16:25:31', '2025-12-18 16:25:31');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(269, '2025-12-16', '22.4', '22.4', '59', 0, '2025-12-19 11:50:48', '2025-12-19 11:50:48');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(270, '2025-12-19', '21.2', '21.2', '58', 0, '2025-12-19 14:00:12', '2025-12-19 14:00:12');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(271, '2025-12-22', '21.4', '22.0', '57', 0, '2025-12-22 20:43:13', '2025-12-22 20:43:13');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(272, '2025-12-23', '23.0', '23.5', '56', 0, '2025-12-23 15:21:57', '2025-12-23 15:21:57');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(273, '2025-12-24', '20.4', '20.1', '59', 0, '2025-12-24 20:13:05', '2025-12-24 20:13:05');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(274, '2025-12-29', '20.2', '23.1', '55', 0, '2025-12-29 20:56:25', '2025-12-29 20:56:25');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(275, '2026-01-05', '22.4', '22.9', '56', 0, '2026-01-05 21:23:35', '2026-01-05 21:23:35');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(276, '2026-01-07', '21.1', '21.1', '54', 0, '2026-01-08 19:02:03', '2026-01-08 19:02:03');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(277, '2026-01-08', '18', '21.7', '58', 0, '2026-01-08 20:18:51', '2026-01-08 20:19:25');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(278, '2026-01-13', '21.0', '20.6', '56', 0, '2026-01-13 13:24:11', '2026-01-13 13:24:11');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(279, '2026-01-12', '21.0', '20.2', '53', 0, '2026-01-13 13:30:02', '2026-01-13 13:30:02');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(280, '2026-01-14', '22.1', '21.3', '57', 0, '2026-01-14 21:03:29', '2026-01-14 21:03:29');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(281, '2026-01-15', '21.0', '22.1', '60', 0, '2026-01-15 15:59:18', '2026-01-15 15:59:18');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(282, '2026-01-16', '20.4', '22.9', '55', 0, '2026-01-16 13:27:34', '2026-01-16 13:27:34');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(283, '2026-01-19', '21.4', '21.4', '61', 0, '2026-01-21 14:25:00', '2026-01-21 14:25:00');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(284, '2026-01-21', '21.0', '23.8', '60', 0, '2026-01-21 14:58:35', '2026-01-21 14:58:35');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(285, '2026-01-20', '21.2', '19.5', '48', 0, '2026-01-21 15:24:27', '2026-01-21 15:24:27');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(286, '2026-01-26', '20.2', '19.1', '59', 0, '2026-01-26 21:14:19', '2026-01-26 21:14:19');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(287, '2026-01-27', '23.5', '20.4', '58', 0, '2026-01-28 19:17:44', '2026-01-28 19:17:44');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(288, '2026-01-28', '22.0', '19.5', '57', 0, '2026-01-28 19:18:26', '2026-01-28 19:18:26');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(289, '2026-01-29', '20.4', '22.4', '60', 0, '2026-01-30 12:44:44', '2026-01-30 12:44:44');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(290, '2026-01-30', '20.4', '22.7', '59', 0, '2026-01-30 12:44:57', '2026-01-30 12:44:57');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(291, '2026-02-03', '22.1', '20.1', '60', 0, '2026-02-03 16:29:11', '2026-02-03 16:29:11');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(292, '2026-02-02', '20.4', '20.5', '59', 0, '2026-02-03 16:29:29', '2026-02-03 16:29:29');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(293, '2026-02-04', '20.4', '20.8', '60', 0, '2026-02-04 21:29:07', '2026-02-04 21:29:07');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(294, '2026-02-05', '20.0', '21.2', '57', 0, '2026-02-05 20:44:55', '2026-02-05 20:44:55');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(295, '2026-02-06', '21.1', '23.1', '56', 0, '2026-02-06 16:59:53', '2026-02-06 16:59:53');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(296, '2026-02-09', '21.0', '20.9', '60', 0, '2026-02-09 21:24:10', '2026-02-09 21:24:10');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(297, '2026-02-10', '20.2', '20.3', '59', 0, '2026-02-10 15:57:09', '2026-02-10 15:57:09');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(298, '2026-02-11', '21.1', '22.4', '59', 0, '2026-02-11 19:16:02', '2026-02-11 19:16:02');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(299, '2026-02-12', '21.3', '23.8', '57', 0, '2026-02-12 18:35:03', '2026-02-12 18:35:03');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(300, '2026-02-16', '20.2', '21.9', '57', 0, '2026-02-16 19:42:26', '2026-02-16 19:42:26');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(301, '2026-02-13', '20.4', '21.9', '58', 0, '2026-02-16 19:42:43', '2026-02-16 19:42:43');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(302, '2026-02-18', '21.0', '24.3', '69', 0, '2026-02-18 16:14:45', '2026-02-18 16:14:45');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(303, '2026-02-19', '20.4', '23.1', '55', 0, '2026-02-19 13:23:59', '2026-02-19 13:23:59');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(304, '2026-02-20', '20.4', '23.1', '66', 0, '2026-02-20 14:22:06', '2026-02-20 14:22:06');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(305, '2026-02-23', '21.7', '21.7', '61', 0, '2026-02-23 17:45:46', '2026-02-23 17:45:46');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(306, '2026-02-26', '21.0', '24.6', '67', 0, '2026-02-26 21:20:40', '2026-02-26 21:20:40');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(307, '2026-02-27', '21.0', '24.4', '56', 0, '2026-02-27 14:01:35', '2026-02-27 14:01:35');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(308, '2026-03-03', '22.0', '21.0', '56', 0, '2026-03-03 20:28:54', '2026-03-03 20:28:54');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(309, '2026-03-04', '20.8', '19.0', '59', 0, '2026-03-04 18:47:41', '2026-03-04 18:47:41');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(310, '2026-03-09', '20.9', '20.0', '61', 0, '2026-03-09 21:03:15', '2026-03-09 21:03:15');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(311, '2026-03-11', '20.4', '20.4', '65', 0, '2026-03-11 21:32:52', '2026-03-11 21:32:52');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(312, '2026-03-16', '20.9', '20.4', '62', 0, '2026-03-16 21:39:11', '2026-03-16 21:39:45');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(313, '2026-03-23', '20.9', '20.4', '61', 0, '2026-03-23 15:56:06', '2026-03-23 15:56:06');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(314, '2026-03-24', '21.0', '19.5', '58', 0, '2026-03-24 19:10:54', '2026-03-24 19:10:54');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(315, '2026-03-30', '20.0', '20.0', '54', 0, '2026-03-30 14:36:59', '2026-03-30 14:36:59');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(316, '2026-03-31', '23.1', '23.4', '54', 0, '2026-04-01 15:18:01', '2026-04-01 15:18:01');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(317, '2026-04-01', '20.2', '20.2', '57', 0, '2026-04-01 19:09:43', '2026-04-01 19:09:43');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(318, '2026-04-07', '21.4', '21.4', '59', 0, '2026-04-09 12:37:27', '2026-04-09 12:37:27');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(319, '2026-03-18', '20.2', '20.2', '59', 0, '2026-04-09 12:59:23', '2026-04-09 12:59:23');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(320, '2026-04-09', '20.2', '20.2', '63', 0, '2026-04-09 19:24:48', '2026-04-09 19:24:48');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(321, '2026-04-10', '20.9', '20.5', '61', 0, '2026-04-10 17:56:17', '2026-04-10 17:56:17');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(322, '2026-04-15', '20.4', '20.8', '61', 0, '2026-04-15 16:12:37', '2026-04-15 16:12:37');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(323, '2026-04-17', '21.6', '20.7', '63', 0, '2026-04-17 13:21:44', '2026-04-17 13:21:44');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(324, '2026-04-16', '21.2', '21.2', '68', 0, '2026-04-17 19:43:33', '2026-04-17 19:46:25');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(325, '2026-04-20', '20.8', '20.4', '58', 0, '2026-04-20 21:16:38', '2026-04-20 21:16:38');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(326, '2026-04-21', '21.4', '21.4', '59', 0, '2026-04-22 16:48:02', '2026-04-22 16:48:02');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(327, '2026-04-22', '20.2', '20.2', '62', 0, '2026-04-22 16:48:18', '2026-04-22 16:48:18');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(328, '2026-04-27', '20.9', '20.8', '62', 0, '2026-04-27 21:22:31', '2026-04-27 21:22:31');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(329, '2026-05-11', '21.0', '23.6', '56', 0, '2026-05-11 15:41:57', '2026-05-11 15:41:57');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(330, '2026-05-07', '22.2', '22.2', '57', 0, '2026-05-12 18:27:07', '2026-05-12 18:27:07');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(331, '2026-05-12', '20.0', '21.8', '61', 0, '2026-05-12 20:55:58', '2026-05-12 20:55:58');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(332, '2026-05-17', '21.0', '21.0', '55', 0, '2026-05-17 20:07:04', '2026-05-17 20:07:04');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(333, '2026-05-18', '21.0', '22.0', '61', 0, '2026-05-18 13:27:30', '2026-05-18 13:27:30');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(334, '2026-05-19', '20.0', '20.4', '57', 0, '2026-05-19 19:25:33', '2026-05-19 19:25:33');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(335, '2026-05-20', '21.0', '21.8', '62', 0, '2026-05-20 16:40:53', '2026-05-20 16:40:53');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(336, '2026-05-22', '22.1', '22.1', '61', 0, '2026-05-22 20:02:08', '2026-05-22 20:02:08');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(337, '2026-05-29', '23.1', '23.1', '58', 0, '2026-05-29 19:36:42', '2026-05-29 19:36:42');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(338, '2026-05-28', '22.2', '22.2', '63', 0, '2026-05-29 19:37:23', '2026-05-29 19:37:23');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(339, '2026-05-27', '20.4', '20.4', '63', 0, '2026-05-29 19:39:58', '2026-05-29 19:39:58');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(340, '2026-06-01', '21.1', '20.4', '62', 0, '2026-06-01 20:30:48', '2026-06-01 20:30:48');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(341, '2026-06-02', '20.6', '20.4', '67', 0, '2026-06-03 13:37:16', '2026-06-03 13:37:16');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(342, '2026-06-04', '20.2', '20.2', '64', 0, '2026-06-04 20:36:51', '2026-06-04 20:36:51');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(343, '2026-06-05', '20.1', '20.4', '62', 0, '2026-06-05 21:13:49', '2026-06-05 21:13:49');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(344, '2026-06-09', '21.4', '21.4', '59', 0, '2026-06-09 19:14:11', '2026-06-09 19:14:11');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(345, '2026-06-17', '21.6', '21.6', '65', 0, '2026-06-19 15:23:07', '2026-06-19 15:23:07');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(346, '2026-06-15', '20.9', '20.9', '58', 0, '2026-06-22 20:46:17', '2026-06-22 20:46:17');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(347, '2026-06-23', '20.1', '20.1', '60', 0, '2026-06-23 15:49:32', '2026-06-23 15:49:32');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(348, '2026-06-24', '20', '21.4', '75', 0, '2026-06-24 16:58:34', '2026-06-24 16:58:34');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(349, '2026-07-06', '20.6', '20.6', '70', 0, '2026-07-08 16:46:45', '2026-07-08 16:46:45');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(350, '2026-07-10', '20.2', '20.2', '63', 0, '2026-07-13 20:40:11', '2026-07-13 20:41:11');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(351, '2026-07-17', '20.2', '20.2', '48', 0, '2026-07-17 17:32:31', '2026-07-17 17:32:31');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(352, '2026-07-21', '20.1', '20.1', '54', 0, '2026-07-21 16:15:26', '2026-07-21 16:15:26');
INSERT INTO `fiq_temperatures` (`id`, `date_temperature`, `fiq_lab_pre`, `fiq_lab_tem`, `fiq_lab_hum`, `deleted`, `created_at`, `updated_at`) VALUES
(353, '2026-07-24', '21.0', '20.9', '66', 0, '2026-07-24 21:19:51', '2026-07-24 21:19:51');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 1, 'Número Ácido', 'Botella para FQ', 1, 0, 'var col5 = parseFloat(document.getElementById(\'col5\').value);\r\nvar col6 = parseFloat(document.getElementById(\'col6\').value);\r\nvar col7 = parseFloat(document.getElementById(\'col7\').value);\r\nvar col8 = parseFloat(document.getElementById(\'col8\').value);\r\nvar result = (col8-col6)*col5/col7;\r\ndocument.getElementById(\'col9\').value = result.toFixed(3);', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'mgKOH/g', 1, 0, '2023-04-07 02:47:28.101377', '2023-04-07 10:45:43.661646');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 1, 'Factor De Potencia 25º', 'Botella para FQ', 2, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', '%', 1, 0, '2023-04-07 02:47:28.113750', '2023-04-07 02:47:28.113750');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 1, 'Factor De Potencia 100º', 'Botella para FQ', 4, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', '%', 1, 0, '2023-04-07 02:47:28.124946', '2023-04-07 02:47:28.124946');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(4, 1, 'Rigidez Dieléctrica', 'Botella para FQ', 5, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'kV/2.0mm', 1, 0, '2023-04-07 02:47:28.138365', '2023-04-07 02:47:28.138365');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(5, 1, 'Tensión Interfacial', 'Botella para FQ', 7, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'mN/m', 1, 0, '2023-04-07 02:47:28.148737', '2023-04-07 02:47:28.148737');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(6, 1, 'Contenido de Agua', 'Botella para FQ', 8, 0, 'var col5 = parseFloat(document.getElementById(\'col5\').value);\r\nvar col7 = parseFloat(document.getElementById(\'col7\').value); \r\n\r\nvar repe = col5-col7;\r\ndocument.getElementById(\'col8\').value = Math.abs(repe).toFixed(1);\r\n\r\nvar promedio = (col5+col7)/2;\r\ndocument.getElementById(\'col9\').value = promedio.toFixed(0);', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'ppm', 0, 0, '2023-04-07 02:47:28.160695', '2024-12-02 22:24:01.343015');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(7, 1, 'Color', 'Botella para FQ', 9, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', NULL, 0, 0, '2023-04-07 02:47:28.171276', '2023-04-07 02:47:28.171276');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(8, 1, 'Condición Visual', 'Botella para FQ', 10, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', NULL, 0, 0, '2023-04-07 02:47:28.182773', '2023-04-07 02:47:28.182773');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(9, 1, 'Densidad Relativa', 'Botella para FQ', 11, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', NULL, 0, 0, '2023-04-07 02:47:28.193145', '2023-04-07 02:47:28.193145');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(10, 2, 'Análisis Cromatográfico', 'Jeringa', 14, 0, 'var col3 = parseFloat(document.getElementById(\'col3\').value);\r\nvar col4 = parseFloat(document.getElementById(\'col4\').value);\r\nvar col5 = parseFloat(document.getElementById(\'col5\').value);\r\nvar col6 = parseFloat(document.getElementById(\'col6\').value);\r\nvar col7 = parseFloat(document.getElementById(\'col7\').value);\r\nvar col8 = parseFloat(document.getElementById(\'col8\').value);\r\nvar col9 = parseFloat(document.getElementById(\'col9\').value);\r\nvar col10 = parseFloat(document.getElementById(\'col10\').value);\r\nvar col11 = parseFloat(document.getElementById(\'col11\').value);\r\n\r\nvar comresult = col3+col6+col7+col9+col10+col11;\r\ndocument.getElementById(\'col12\').value = comresult.toFixed(2);\r\nvar result = col3+col4+col5+col6+col7+col8+col9+col10+col11;\r\ndocument.getElementById(\'col13\').value = result.toFixed(2);', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n\r\n- Si se cambia la posicion de las columnas que se usen calculos se tiene que cambiar la formula de los campos.\r\n\r\n- Se recomienda bloquear la edicion en las columnas que sean resultados de calculos.', 'ppm', 0, 0, '2023-04-07 02:47:28.205743', '2023-10-11 21:42:37.310357');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(11, 3, 'PCB', 'Frascos PCB', 15, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'ppm', 0, 0, '2023-04-07 02:47:28.216339', '2023-04-07 02:47:28.216339');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(12, 3, 'Furanos', 'Frascos Furanos', 16, 0, 'var col4Input = document.getElementById(\'col4\');\r\nvar col4 = parseFloat(col4Input.value);\r\n\r\n// Redondear visualmente a 3 decimales si tiene más (pero dejar enteros como están)\r\nif (!isNaN(col4) && col4 % 1 !== 0) {\r\n    col4Input.value = col4.toFixed(3);\r\n}\r\n\r\nvar fal_ppm = col4 / 1000;\r\nvar log_fal = Math.log10(fal_ppm); \r\n\r\nvar shen_numerator = 1.51 - log_fal;\r\nvar shen_denominator = 0.0035;\r\n\r\nvar result = shen_numerator / shen_denominator;\r\n\r\ndocument.getElementById(\'col9\').value = result.toFixed(0);\r\n\r\n\r\n//=(1.51-LOG10(2FAL/1000))/0.0035', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'ppb', 0, 0, '2023-04-07 02:47:28.227935', '2025-07-05 08:04:45.505606');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(13, 3, 'Azufre 1275B', 'Frascos Azufre', 17, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', NULL, 0, 0, '2023-04-07 02:47:28.240465', '2023-04-07 02:47:28.240465');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(14, 3, 'Azufre 62535 (48 horas)', 'Frascos Azufre', 18, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', NULL, 0, 0, '2023-04-07 02:47:28.253876', '2023-04-07 02:47:28.253876');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(15, 3, 'Azufre 62535 (72 horas)', 'Frascos Azufre', 19, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', NULL, 0, 0, '2023-04-07 02:47:28.265002', '2023-04-07 02:47:28.265002');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(16, 3, 'Grado de Polimerización', 'Grado Polimerización', 20, 0, 'var col31 = parseFloat(document.getElementById(\'col3-1\').value);\r\nvar col32 = parseFloat(document.getElementById(\'col3-2\').value);\r\nvar result1 = col31 + \"/\" + col32;  \r\ndocument.getElementById(\'col3\').value = result1;\r\n\r\nvar col41 = parseFloat(document.getElementById(\'col4-1\').value);\r\nvar col42 = parseFloat(document.getElementById(\'col4-2\').value);\r\nvar result2 = col41 + \"/\" + col42;  \r\ndocument.getElementById(\'col4\').value = result2;\r\n\r\nvar col51 = parseFloat(document.getElementById(\'col5-1\').value);\r\nvar col52 = parseFloat(document.getElementById(\'col5-2\').value);\r\nvar col53 = parseFloat(document.getElementById(\'col5-3\').value);\r\nvar col54 = parseFloat(document.getElementById(\'col5-4\').value);\r\nvar result3 = col51 + \"/\" + col52 + \"/\" + col53 + \"/\" + col54;  \r\ndocument.getElementById(\'col5\').value = result3;\r\n\r\nvar col61 = parseFloat(document.getElementById(\'col6-1\').value);\r\nvar col62 = parseFloat(document.getElementById(\'col6-2\').value);\r\nvar col63 = parseFloat(document.getElementById(\'col6-3\').value);\r\nvar col64 = parseFloat(document.getElementById(\'col6-4\').value);\r\nvar result4 = col61 + \"/\" + col62 + \"/\" + col63 + \"/\" + col64;  \r\ndocument.getElementById(\'col6\').value = result4;\r\n\r\nvar col71 = parseFloat(document.getElementById(\'col7-1\').value);\r\nvar col72 = parseFloat(document.getElementById(\'col7-2\').value);\r\nvar col73 = parseFloat(document.getElementById(\'col7-3\').value);\r\nvar col74 = parseFloat(document.getElementById(\'col7-4\').value);\r\nvar result5 = col71 + \"/\" + col72 + \"/\" + col73 + \"/\" + col74;  \r\ndocument.getElementById(\'col7\').value = result5;\r\n\r\nvar col81 = parseFloat(document.getElementById(\'col8-1\').value);\r\nvar col82 = parseFloat(document.getElementById(\'col8-2\').value);\r\nvar col83 = parseFloat(document.getElementById(\'col8-3\').value);\r\nvar col84 = parseFloat(document.getElementById(\'col8-4\').value);\r\nvar result6 = col81 + \"/\" + col82 + \"/\" + col83 + \"/\" + col84;  \r\ndocument.getElementById(\'col8\').value = result6;\r\n\r\nvar result7  = ( (col51*col61) + (col52*col62) )/2;\r\ndocument.getElementById(\'col9-1\').value = result7.toFixed(2);\r\n\r\nvar result8  = ( (col53*col63) + (col54*col64) )/2;\r\ndocument.getElementById(\'col9-2\').value = result8.toFixed(2);\r\n\r\nvar resultcol9 = result7 + \"/\" + result8;  \r\ndocument.getElementById(\'col9\').value = resultcol9;\r\n\r\nvar result9  = ( (col71*col81) + (col72*col82) )/2;\r\ndocument.getElementById(\'col10-1\').value = result9.toFixed(2);\r\n\r\nvar result10 = ( (col73*col83) + (col74*col84) )/2;\r\ndocument.getElementById(\'col10-2\').value = result10.toFixed(2);\r\n\r\nvar resultcol10 = result9 + \"/\" + result10;  \r\ndocument.getElementById(\'col10\').value = resultcol10;\r\n\r\nvar result11 = col31*100/(45*( 1+(col41/100) ));\r\ndocument.getElementById(\'col11-1\').value = result11.toFixed(2);\r\n\r\nvar result12 = col32*100/(45*( 1+(col42/100) ));\r\ndocument.getElementById(\'col11-2\').value = result12.toFixed(2);\r\n\r\nvar resultcol11 = result11.toFixed(2) + \"/\" + result12.toFixed(2);  \r\ndocument.getElementById(\'col11\').value = resultcol11;\r\n\r\nvar result13 = (result7-result9)/result9;\r\ndocument.getElementById(\'col12-1\').value = result13.toFixed(2);\r\n\r\nvar result14 = (result8-result10)/result10;\r\ndocument.getElementById(\'col12-2\').value = result14.toFixed(2);\r\n\r\nvar resultcol12 = result13.toFixed(2) + \"/\" + result14.toFixed(2);  \r\ndocument.getElementById(\'col12\').value = resultcol12;\r\n\r\nvar col131 = parseFloat(document.getElementById(\'col13-1\').value);\r\nvar col132 = parseFloat(document.getElementById(\'col13-2\').value);\r\n\r\nvar resultcol13 = col131.toFixed(2) + \"/\" + col132.toFixed(2);  \r\ndocument.getElementById(\'col13\').value = resultcol13;\r\n\r\nvar result15 = col131/result11;\r\ndocument.getElementById(\'col14-1\').value = result15.toFixed(2);\r\n\r\nvar result16 = col132/result12;\r\ndocument.getElementById(\'col14-2\').value = result16.toFixed(2);\r\n\r\nvar resultcol14 = result15.toFixed(2) + \"/\" + result16.toFixed(2);  \r\ndocument.getElementById(\'col14\').value = resultcol14;\r\n\r\nvar result17 = (result15/0.0075);\r\ndocument.getElementById(\'col15-1\').value = result17.toFixed(2);\r\n\r\nvar result18 = (result16/0.0075);\r\ndocument.getElementById(\'col15-2\').value = result18.toFixed(2);\r\n\r\nvar resultcol15 = result17.toFixed(2) + \"/\" + result18.toFixed(2);  \r\ndocument.getElementById(\'col15\').value = resultcol15;\r\n\r\nvar resultcol16 = (result17 + result18)/2;  \r\ndocument.getElementById(\'col16\').value = resultcol16.toFixed(2);', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'GP', 0, 0, '2023-04-07 02:47:28.278823', '2023-05-15 01:54:11.846357');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(17, 3, 'Viscocidad', 'Frascos Visocidad', 21, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'cSt', 0, 0, '2023-04-07 02:47:28.290315', '2023-04-07 02:47:28.290315');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(18, 3, 'Partículas', 'Frascos Partículas', 22, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'Partículas/ml', 0, 0, '2023-04-07 02:47:28.301710', '2023-04-07 02:47:28.301710');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(19, 3, 'Metales en Aceite', 'Frascos Metales', 23, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'ppm', 0, 0, '2023-04-07 02:47:28.312900', '2023-04-07 02:47:28.312900');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(20, 3, 'Inhibidor', 'Frascos Inhibidor', 24, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', '%', 0, 0, '2023-04-07 02:47:28.325114', '2023-04-07 02:47:28.325114');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(21, 3, 'DBDS', 'Frascos DBDS', 25, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'ppm', 0, 0, '2023-04-07 02:47:28.336341', '2023-04-07 02:47:28.336341');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(22, 3, 'Sedimentos', 'Frascos Sedimentos', 26, 0, 'var col3 = parseFloat(document.getElementById(\'col3\').value);\r\nvar col4 = parseFloat(document.getElementById(\'col4\').value);\r\nvar col5 = parseFloat(document.getElementById(\'col5\').value);\r\n\r\nvar result = col3+col4;\r\ndocument.getElementById(\'col6\').value = result.toFixed(3);', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', '% ', 0, 0, '2023-04-07 02:47:28.350340', '2023-10-11 18:22:18.940595');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(23, 3, 'Fluidez', 'Frascos Fluidez', 27, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'ºC', 0, 0, '2023-04-07 02:47:28.361724', '2023-04-07 02:47:28.361724');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(24, 3, 'Inflamación', 'Frascos Inflamante', 28, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'ºC', 0, 0, '2023-04-07 02:47:28.374728', '2023-04-07 02:47:28.374728');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(25, 3, 'Pasivador', 'Frascos Pasivador', 29, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'ppm', 0, 0, '2023-04-07 02:47:28.389118', '2023-04-09 10:40:23.445842');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(26, 1, 'Factor De Potencia 90º', 'Botella para FQ', 3, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', '%', 1, 0, '2023-04-07 02:47:28.399671', '2023-04-07 02:47:28.399671');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(27, 1, 'Rigidez Dielectrica Electrodos planos', 'Botella para FQ', 6, 0, '', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.\r\n', 'kV/2.0mm', 1, 0, '2023-07-27 19:46:43.263327', '2023-10-26 14:36:09.209845');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(28, 1, 'Resistividad Volumétrica 25º', 'Botella para FQ', 12, 0, 'var col5 = parseFloat(document.getElementById(\'col5\').value);\r\n\r\nvar col6 = parseFloat(document.getElementById(\'col6\').value);\r\n\r\nvar result = (col5+col6)/2;\r\ndocument.getElementById(\'col7\').value = result.toExponential(2).toUpperCase();\r\n\r\n\r\n\r\n\r\n  var t = document.getElementById(\"time\");\r\n  t.textContent = t.textContent.slice(0, -3);\r\n\r\n\r\n  var colu = document.getElementById(\"col5\");\r\n  colu.textContent = colu.textContent.slice(0, -3);', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.', 'Ωcm', 1, 0, '2024-01-10 18:01:49.617180', '2024-01-12 00:15:32.880932');
INSERT INTO `lab_category_details` (`id`, `lab_category_detail_type_id`, `name`, `container`, `num_pos`, `is_grouped`, `blur_calculation`, `description`, `unit_name_amchart`, `has_reuse`, `deleted`, `created_at`, `updated_at`) VALUES
(29, 1, 'Resistividad Volumétrica 100º', 'Botella para FQ', 13, 0, 'var col5 = parseFloat(document.getElementById(\'col5\').value);\r\n\r\nvar col6 = parseFloat(document.getElementById(\'col6\').value);\r\n\r\nvar result = (col5+col6)/2;\r\ndocument.getElementById(\'col7\').value = result.toExponential(2).toUpperCase();', '- No cambiar el check de Mostrar en Reporte porque cambia la presentación del reporte.', 'Ωcm', 1, 0, '2024-01-10 18:37:06.496340', '2024-01-12 00:22:52.787095');
INSERT INTO `lab_category_detail_types` (`id`, `name`, `icon_label`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'Fisico Quimico', 'bong', 0, '2023-04-07 02:47:28.047227', '2023-05-11 14:01:46.708944');
INSERT INTO `lab_category_detail_types` (`id`, `name`, `icon_label`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 'Cromatografias', 'syringe', 0, '2023-04-07 02:47:28.060213', '2023-05-11 14:02:10.473340');
INSERT INTO `lab_category_detail_types` (`id`, `name`, `icon_label`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 'Otros', 'flask-vial', 0, '2023-04-07 02:47:28.071784', '2023-05-11 14:03:29.871890');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.487939', '2023-07-25 04:04:46.784267');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 1, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.496849', '2023-07-25 04:04:46.804007');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 1, 3, 'Bureta PP-LA-01C', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.507199', '2023-07-25 04:04:46.829032');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(4, 1, 3, 'Balanza PP-LA-01C', 4, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.517442', '2023-07-25 04:04:46.851603');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(5, 1, 2, 'Factor KOH', 5, 1, 0, 1, 1, '0.514', 0, '', '', 0, 0, '2023-04-07 02:47:28.528020', '2026-07-17 14:33:17.502859');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(6, 1, 2, 'Vol Blanco', 6, 1, 0, 1, 1, '0.181', 0, '', '', 0, 0, '2023-04-07 02:47:28.537538', '2026-07-21 15:46:53.571402');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(7, 1, 2, 'Peso aceite (g)', 7, 0, 0, 1, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.547634', '2023-10-17 03:57:45.113773');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(8, 1, 2, 'Volumen gastado (mL)', 8, 0, 0, 1, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.557458', '2023-10-17 01:40:02.670400');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(9, 1, 1, 'Resultado (mgKOH/g aceite)', 9, 0, 1, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:28.565929', '2023-10-17 01:41:38.832603');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(10, 2, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, 'Número de muestra:', '', 0, 0, '2023-04-07 02:47:28.574352', '2023-06-19 13:57:48.520364');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(11, 2, 3, 'Tipo de Equipo', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.583788', '2023-04-08 14:43:43.027982');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(12, 2, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.593934', '2023-04-08 14:43:43.020596');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(13, 2, 2, 'Temperatura Ambiente (ºC)', 4, 1, 0, 0, 1, '20.2', 0, '', '', 0, 0, '2023-04-07 02:47:28.604481', '2026-05-29 17:08:57.543938');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(14, 2, 2, 'Humedad Ambiente (%)', 5, 1, 0, 0, 1, '60', 0, '', '', 0, 0, '2023-04-07 02:47:28.613487', '2026-04-13 14:52:38.902875');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(15, 2, 2, 'Temperatura Muestra (ºC)', 6, 1, 0, 0, 0, '', 1, 'Temperatura del ensayo:', '°C', 0, 0, '2023-04-07 02:47:28.621294', '2023-04-07 02:47:28.621294');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(16, 2, 2, 'Resultado (%)', 7, 1, 0, 0, 0, '', 1, 'a 60 Hz:', '%', 1, 0, '2023-04-07 02:47:28.630688', '2023-07-25 04:19:03.972433');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(17, 3, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, 'Número de muestra:', '', 0, 0, '2023-04-07 02:47:28.639619', '2023-06-19 13:58:01.792444');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(18, 3, 3, 'Tipo de Equipo', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.649595', '2023-06-19 13:58:01.853525');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(19, 3, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.659097', '2023-06-19 13:58:01.824531');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(20, 3, 2, 'Temperatura Ambiente (ºC)', 4, 1, 0, 0, 1, '20.2', 0, '', '', 0, 0, '2023-04-07 02:47:28.669445', '2024-07-26 09:35:36.462745');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(21, 3, 2, 'Humedad Ambiente (%)', 5, 1, 0, 0, 1, '66', 0, '', '', 0, 0, '2023-04-07 02:47:28.678925', '2024-07-25 09:44:34.761005');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(22, 3, 2, 'Temperatura Muestra (ºC)', 6, 1, 0, 0, 0, '', 1, 'Temperatura del ensayo:', '°C', 0, 0, '2023-04-07 02:47:28.688169', '2023-04-07 02:47:28.688169');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(23, 3, 2, 'Resultado (%)', 7, 1, 0, 0, 0, '', 1, 'a 60 Hz:', '%', 1, 0, '2023-04-07 02:47:28.698777', '2023-07-25 04:19:14.396269');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(24, 4, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, 'Número de muestra:', '', 0, 0, '2023-04-07 02:47:28.708875', '2023-06-19 13:58:13.703245');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(25, 4, 3, 'Tipo de Fluido', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.718985', '2023-06-19 13:58:13.751930');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(26, 4, 2, 'Temperatura Ambiente (ºC)', 4, 1, 0, 0, 1, '20.2', 0, '', '', 0, 0, '2023-04-07 02:47:28.728955', '2026-06-16 15:54:46.134680');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(27, 4, 2, 'Humedad Ambiente (%)', 5, 1, 0, 0, 1, '65', 0, '', '', 0, 0, '2023-04-07 02:47:28.738197', '2026-06-16 15:54:46.247141');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(28, 4, 3, 'Espinterometro', 6, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.749001', '2023-06-19 13:58:13.833775');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(29, 4, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.758923', '2023-06-19 13:58:13.720423');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(30, 4, 2, 'Temperatura Muestra (ºC)', 7, 1, 0, 0, 0, '', 1, 'Temperatura:', '°C', 0, 0, '2023-04-07 02:47:28.767597', '2023-10-26 08:18:17.795271');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(31, 4, 2, 'Resultado (KV)', 8, 1, 0, 0, 0, '', 1, 'Valor medio:', 'kV', 1, 0, '2023-04-07 02:47:28.779502', '2023-10-26 08:18:17.821310');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(32, 5, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.792423', '2023-07-27 18:25:29.425243');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(33, 5, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.804661', '2023-07-27 18:25:29.438015');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(34, 5, 3, 'Tensiómetro PP-LA-01C', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.816763', '2023-07-27 18:25:29.456299');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(35, 5, 3, 'Densímetro PP-LA-01C', 4, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.830584', '2023-07-27 18:25:29.474200');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(36, 5, 3, 'Termómetro PP-LA-01C', 5, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.846342', '2023-07-27 18:25:29.482781');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(37, 5, 2, 'Densidad Aceite', 6, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.856350', '2023-10-17 04:11:14.421658');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(38, 5, 2, 'Temp. Aceite', 7, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.866716', '2023-10-17 04:11:14.429334');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(39, 5, 2, 'Temp. Agua', 8, 1, 0, 0, 1, '20.1', 0, '', '', 0, 0, '2023-04-07 02:47:28.876662', '2024-05-21 21:34:39.811016');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(40, 5, 2, 'Densidad Agua', 9, 1, 0, 0, 1, '0.998', 0, '', '', 0, 0, '2023-04-07 02:47:28.886275', '2025-09-12 18:38:02.618164');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(41, 5, 2, 'Tensión Corregida Agua (70-74 Mn/m)', 10, 0, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.896391', '2023-10-17 04:11:14.436073');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(42, 5, 2, 'Tensión Interfacial Aceite (mN/m)', 11, 0, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:28.905351', '2023-10-17 04:11:14.442496');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(43, 6, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.916514', '2023-07-27 18:51:21.179257');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(44, 6, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.925862', '2023-07-27 18:51:21.188319');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(45, 6, 3, 'Balanza PP-LA-01C', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.935983', '2023-07-27 18:51:21.197940');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(46, 6, 1, 'R1', 5, 1, 0, 1, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.944191', '2024-12-02 11:51:34.410122');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(47, 6, 1, 'R2', 7, 1, 0, 1, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.954011', '2024-12-02 11:51:34.491552');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(48, 6, 2, 'Resultado ppm', 9, 1, 1, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:28.962741', '2024-12-02 11:51:34.582846');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(49, 6, 2, 'Repetibilidad', 8, 1, 1, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.971833', '2024-12-02 11:51:34.545171');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(50, 7, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.981318', '2023-07-27 18:51:43.421125');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(51, 7, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:28.991446', '2023-07-27 18:51:43.431496');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(52, 7, 3, 'Equipo PP-LA-01C', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.000737', '2023-07-27 18:51:43.444474');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(53, 7, 1, 'Resultado', 6, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.009519', '2023-10-05 07:06:29.127321');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(54, 8, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.017426', '2023-07-27 18:51:56.631425');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(55, 8, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.026870', '2023-07-27 18:51:56.650054');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(56, 8, 1, 'Resultado', 3, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.035307', '2023-07-27 18:51:56.663770');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(57, 9, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.044466', '2023-07-27 18:52:07.048949');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(58, 9, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.054543', '2023-07-27 18:52:07.064018');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(59, 9, 2, 'Resultado', 3, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.064627', '2023-07-27 18:52:07.074639');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(60, 10, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 1, 'Sample name:', '', 0, 0, '2023-04-07 02:47:29.074902', '2023-09-15 17:18:56.076781');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(61, 10, 2, 'Hidrógeno H2 ppm', 3, 1, 0, 1, 0, '', 1, '\r\nH2         ', '', 1, 0, '2023-04-07 02:47:29.084726', '2025-02-03 13:48:16.303225');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(62, 10, 2, 'Oxígeno O2 ppm', 4, 1, 0, 1, 0, '', 1, '\r\nO2          ', '', 1, 0, '2023-04-07 02:47:29.094713', '2023-07-24 20:22:18.740898');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(63, 10, 2, 'Nitrógeno N2 ppm', 5, 1, 0, 1, 0, '', 1, 'N2          ', '', 1, 0, '2023-04-07 02:47:29.107958', '2023-07-24 20:22:18.762964');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(64, 10, 2, 'Metano CH4 ppm', 6, 1, 0, 1, 0, '', 1, 'CH4         ', '', 1, 0, '2023-04-07 02:47:29.117787', '2023-07-24 20:22:18.786455');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(65, 10, 2, 'M.Carbono CO ppm', 7, 1, 0, 1, 0, '', 1, 'CO          ', '', 1, 0, '2023-04-07 02:47:29.127071', '2023-07-24 20:22:18.802568');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(66, 10, 2, 'D.Carbono CO2 ppm', 8, 1, 0, 1, 0, '', 1, 'CO2         ', '', 1, 0, '2023-04-07 02:47:29.136366', '2023-07-24 20:22:18.821326');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(67, 10, 2, 'Etileno C2H4 ppm', 9, 1, 0, 1, 0, '', 1, 'C2H4        ', '', 1, 0, '2023-04-07 02:47:29.145643', '2023-07-24 20:22:18.843010');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(68, 10, 2, 'Etano C2H6 ppm', 10, 1, 0, 1, 0, '', 1, 'C2H6        ', '', 1, 0, '2023-04-07 02:47:29.156231', '2023-07-24 20:22:18.869564');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(69, 10, 2, 'Acetileno C2H2 ppm', 11, 1, 0, 1, 0, '', 1, 'C2H2        ', '', 1, 0, '2023-04-07 02:47:29.165519', '2023-07-24 20:22:18.882449');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(70, 10, 2, 'Total de Gases Combustibles', 12, 1, 1, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.175877', '2023-07-24 20:22:18.898478');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(71, 10, 2, 'Total', 13, 1, 1, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.184752', '2023-07-24 20:22:18.918052');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(72, 11, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.193869', '2023-07-24 02:32:03.050133');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(73, 11, 3, 'Equipo PP-LA-01C', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.204973', '2023-07-24 02:32:03.115132');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(74, 11, 1, 'Aroclor 1242', 4, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.214541', '2023-07-27 18:52:35.784488');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(75, 11, 1, 'Aroclor 1254', 5, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.224255', '2023-07-27 18:52:35.799749');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(76, 11, 1, 'Aroclor 1260', 6, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.232763', '2023-07-27 18:52:35.810877');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(77, 11, 1, 'Contenido Total de PCB´S', 7, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.243328', '2023-07-25 03:49:18.009411');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(78, 12, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '23-', '', 0, 0, '2023-04-07 02:47:29.254037', '2023-06-19 13:59:03.560084');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(79, 12, 3, 'Equipo PP-LA-01C', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.263722', '2023-07-25 02:40:50.981980');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(80, 12, 2, '2-Furfuraldehido	', 4, 1, 0, 1, 0, '', 1, '2-furfuraldehido ', '', 1, 0, '2023-04-07 02:47:29.273903', '2025-11-18 14:58:51.100353');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(81, 12, 2, '5-Hidroxi-Metil-Furfuraldehido', 5, 1, 0, 0, 0, '', 1, '5-hidroximetil-2-furano', '', 1, 0, '2023-04-07 02:47:29.284142', '2025-11-18 14:58:51.231253');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(82, 12, 2, '2-Acetilfurano', 6, 1, 0, 0, 0, '', 1, '2-acetilfurano', '', 1, 0, '2023-04-07 02:47:29.294031', '2025-11-18 15:01:40.786636');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(83, 12, 2, '5-Metil-2-Furfuraldehido', 7, 1, 0, 0, 0, '', 1, '5-metl-2-furfualdehido', '', 1, 0, '2023-04-07 02:47:29.304605', '2025-11-18 14:58:51.452792');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(84, 12, 2, '2-Furfuril Alcohol', 8, 1, 0, 0, 0, '', 1, 'furfurilalcohol', '', 1, 0, '2023-04-07 02:47:29.314222', '2025-11-18 15:01:40.850355');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(85, 12, 2, 'Grado de Polimerización', 9, 1, 1, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.322539', '2023-07-25 02:40:51.131262');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(86, 26, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.331998', '2023-05-11 14:20:15.098910');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(87, 26, 3, 'Tipo de Equipo', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.341005', '2023-05-11 14:20:15.119389');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(88, 26, 3, 'Norma', 2, 1, 1, 1, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.349869', '2023-09-14 16:33:39.904716');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(89, 26, 2, 'Temperatura Ambiente (ºC)', 4, 1, 0, 0, 1, '21.5', 0, '', '', 0, 0, '2023-04-07 02:47:29.360423', '2023-05-11 14:20:15.154581');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(90, 26, 2, 'Humedad Ambiente (%)', 5, 1, 0, 0, 1, '62', 0, '', '', 0, 0, '2023-04-07 02:47:29.370289', '2023-05-11 14:20:15.184494');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(91, 26, 2, 'Temperatura Muestra (ºC)', 6, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.380136', '2023-05-11 14:20:15.202428');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(92, 26, 2, 'Resultado (%)', 7, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.388427', '2023-07-27 19:17:57.778746');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(93, 13, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.399156', '2023-12-02 23:50:14.721368');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(94, 13, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.408866', '2025-12-13 01:58:23.165699');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(95, 13, 3, 'Equipo PP-LA-01C', 5, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.419094', '2023-04-24 12:24:32.237659');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(96, 13, 1, 'ASTM D130', 6, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.428077', '2023-07-27 18:53:02.072423');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(97, 13, 1, 'Resultado', 7, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.438659', '2023-07-27 18:53:02.081632');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(98, 14, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.449221', '2023-12-02 23:49:21.439381');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(99, 14, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.460903', '2025-12-13 01:58:04.868158');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(100, 14, 3, 'Equipo PP-LA-01C', 5, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.471000', '2023-04-24 12:27:12.099627');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(101, 14, 1, 'ASTM D130', 6, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.481492', '2023-07-27 18:53:19.146246');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(102, 14, 1, 'Resultado', 7, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.491873', '2023-07-27 18:53:19.155298');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(103, 15, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.501085', '2023-12-02 23:49:45.871232');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(104, 15, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.509216', '2025-12-13 01:57:25.811711');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(105, 15, 3, 'Equipo PP-LA-01C', 5, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.520067', '2023-04-24 12:27:57.705246');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(106, 15, 1, 'Resultado', 6, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.529457', '2023-07-27 18:53:30.482755');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(107, 15, 1, 'Resultado Lámina de Cobre', 7, 1, 0, 0, 0, '', 0, '', '', 0, 1, '2023-04-07 02:47:29.540264', '2023-07-27 12:58:46.833140');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(108, 16, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.549964', '2023-05-11 18:45:40.139864');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(109, 16, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.560343', '2023-05-11 18:45:40.157259');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(110, 16, 2, 'Masa (g)', 3, 1, 0, 1, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.569502', '2023-05-11 21:34:13.571510');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(111, 17, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.580197', '2023-05-15 02:05:53.064666');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(112, 17, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.589714', '2023-05-15 02:05:53.080966');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(113, 17, 1, 'Termómetro PP-LA-01C', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.599511', '2023-05-15 02:05:53.097180');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(114, 17, 2, 'Temperatura (ºC)', 4, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.609824', '2023-05-15 02:05:53.120145');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(115, 17, 1, 'Viscosímetro Nª', 5, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.619819', '2023-05-15 02:05:53.155931');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(116, 17, 1, 'Constante', 6, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.628935', '2023-05-15 02:05:53.186177');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(117, 17, 2, 'Tiempo (Segundos)', 7, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.638869', '2023-05-15 02:05:53.212142');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(118, 17, 1, 'Resultado (mm2/s)', 8, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.648848', '2023-07-27 18:54:06.781981');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(119, 18, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.658459', '2023-07-25 03:17:41.380761');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(120, 18, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.668465', '2023-07-25 03:17:41.460573');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(121, 18, 1, '> 4 um', 4, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.676361', '2023-07-25 03:17:41.486195');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(122, 18, 1, '> 6 um', 5, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.686626', '2023-07-25 03:17:41.506197');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(123, 18, 1, '> 10 um', 6, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.696118', '2023-07-25 03:17:41.546777');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(124, 18, 1, '> 14 um', 7, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.704748', '2023-07-25 03:17:41.582926');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(125, 18, 1, '> 21 um', 8, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.715984', '2023-07-25 03:17:41.605116');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(126, 18, 1, '> 38 um', 9, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.727207', '2023-07-25 03:17:41.635520');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(127, 18, 1, '> 70 um', 10, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.737076', '2023-07-25 03:17:41.656428');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(128, 18, 1, 'Código ISO (X/Y/Z)', 3, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.747570', '2023-12-16 04:13:17.356652');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(129, 19, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.757901', '2023-07-27 18:55:24.244431');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(130, 19, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.768754', '2023-07-27 18:55:24.256643');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(131, 19, 1, 'Aluminio (AL)', 3, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.778947', '2023-07-27 18:55:24.266765');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(132, 19, 1, 'Cobre (Cu)', 4, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.790566', '2023-07-27 18:55:24.283910');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(133, 19, 1, 'Fierro (Fe)', 5, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.800185', '2023-07-27 18:55:24.301168');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(134, 19, 1, 'Plomo (Pb)', 6, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.815216', '2023-07-27 18:55:24.310231');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(135, 19, 1, 'Plata (Ag)', 7, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.829762', '2023-07-27 18:55:24.328203');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(136, 19, 1, 'Estaño (Sn)', 8, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.839990', '2023-07-27 18:55:24.342084');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(137, 19, 1, 'Znic (Zn)', 9, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.848326', '2023-07-27 18:55:24.366197');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(138, 19, 1, 'Silicio (Sn)', 10, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.857276', '2023-07-27 18:55:24.381407');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(139, 20, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.867009', '2023-07-27 18:55:48.845588');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(140, 20, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.875486', '2023-07-27 18:55:48.856786');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(141, 20, 1, 'Resultado', 3, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.885943', '2023-07-27 18:55:48.868722');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(142, 21, 1, 'Nº de Equipo', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.894157', '2025-12-13 01:55:02.125478');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(143, 21, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.903062', '2025-12-13 01:55:02.234783');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(144, 21, 1, 'Resultado', 4, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.913655', '2023-07-27 18:56:33.230163');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(145, 22, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.923225', '2023-07-27 18:57:10.022432');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(146, 22, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.931865', '2023-07-27 18:57:10.037171');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(147, 22, 2, 'Sedimentos orgánicos', 3, 1, 0, 1, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.941445', '2023-07-27 18:58:15.355420');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(148, 22, 2, 'Sedimentos inorgánicos', 4, 1, 0, 1, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.949957', '2023-07-27 18:58:15.371367');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(149, 22, 2, 'Lodos Solubles\r\n', 5, 1, 0, 1, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.960318', '2023-07-27 18:58:15.381567');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(150, 22, 2, 'Total de Sedimentos', 6, 1, 1, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.968824', '2023-07-27 18:58:15.394939');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(151, 23, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.978707', '2023-07-27 19:01:08.031384');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(152, 23, 1, 'Resultado', 3, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:29.988928', '2023-07-27 19:04:23.445641');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(153, 24, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:29.997327', '2023-07-27 19:01:20.747653');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(154, 24, 1, 'Resultado', 3, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:30.008691', '2023-07-27 19:05:16.529406');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(155, 25, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-07 02:47:30.019459', '2023-04-09 10:40:23.470896');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(156, 25, 1, 'Resultado', 3, 1, 0, 0, 0, '', 0, '', '', 1, 0, '2023-04-07 02:47:30.029426', '2023-07-27 19:05:56.264803');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(157, 13, 4, 'Fecha Inicial', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-24 12:22:01.690834', '2025-12-13 01:58:23.017458');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(158, 13, 4, 'Fecha Final', 4, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-24 12:24:32.300033', '2025-12-13 01:58:23.087606');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(159, 14, 4, 'Fecha Inicial', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-24 12:27:12.158414', '2025-12-13 01:58:04.734687');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(160, 14, 4, 'Fecha Final', 4, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-24 12:27:12.172768', '2025-12-13 01:58:04.809415');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(161, 15, 4, 'Fecha Inicial', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-24 12:27:57.773020', '2025-12-13 01:57:25.654808');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(162, 15, 4, 'Fecha Final', 4, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-04-24 12:27:57.792203', '2025-12-13 01:57:25.737315');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(163, 16, 2, 'Contenido de Agua en (%)', 4, 1, 0, 1, 0, '', 0, '', '', 0, 0, '2023-05-11 18:45:40.188527', '2023-05-11 21:34:13.599089');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(164, 16, 2, 'Tiempo muestra (s)', 5, 1, 0, 1, 0, '', 0, '', '', 0, 0, '2023-05-11 18:45:40.210683', '2023-05-11 21:34:13.627871');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(165, 16, 2, 'Constante viscosímetro muestra', 6, 1, 0, 1, 0, '', 0, '', '', 0, 0, '2023-05-11 18:45:40.227202', '2023-05-11 21:34:13.650376');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(166, 16, 2, 'Tiempo Blanco', 7, 1, 0, 1, 0, '', 0, '', '', 0, 0, '2023-05-11 18:49:51.375161', '2023-05-11 21:34:13.671797');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(167, 16, 2, 'Constante viscosímetro blanco', 8, 1, 0, 1, 0, '', 0, '', '', 0, 0, '2023-05-11 18:49:51.384463', '2023-05-11 21:34:13.690600');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(168, 16, 2, 'Viscosidad de muestra (T)', 9, 1, 1, 0, 0, '', 0, '', '', 0, 0, '2023-05-11 18:49:51.404247', '2023-05-12 00:58:30.369656');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(169, 16, 2, 'Viscosidad de Solvente(T0)', 10, 1, 1, 0, 0, '', 0, '', '', 0, 0, '2023-05-11 18:51:30.629840', '2023-05-12 00:58:30.395655');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(170, 16, 2, 'Concetracion muestra (g/100mL)', 11, 1, 1, 0, 0, '', 0, '', '', 0, 0, '2023-05-11 18:51:30.642397', '2023-05-12 01:06:39.133571');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(171, 16, 2, 'Viscosidad especifica (ns)', 12, 1, 1, 0, 0, '', 0, '', '', 0, 0, '2023-05-11 18:51:30.658179', '2023-05-12 01:23:21.321939');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(172, 16, 2, 'K  de Martin', 13, 1, 0, 1, 0, '', 0, '', '', 0, 0, '2023-05-11 18:58:59.904866', '2023-05-11 21:34:13.712362');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(173, 16, 2, 'Viscosidad Intrinseca (n)', 14, 1, 1, 0, 0, '', 0, '', '', 0, 0, '2023-05-11 18:58:59.926410', '2023-05-12 01:37:13.871934');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(174, 16, 2, 'Grado de polimerización', 15, 1, 1, 0, 0, '', 0, '', '', 0, 0, '2023-05-11 19:17:16.075401', '2023-05-12 01:41:29.479527');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(175, 16, 2, 'Promedio', 16, 1, 1, 0, 0, '', 0, '', '', 0, 0, '2023-05-11 19:17:16.090293', '2023-05-12 01:41:29.502524');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(176, 21, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-05-27 13:17:25.640622', '2023-05-27 13:17:25.640622');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(177, 10, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-07-07 12:49:57.265741', '2023-07-07 12:49:57.265741');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(178, 11, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-07-24 02:32:03.222498', '2023-07-24 02:32:03.222498');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(179, 12, 3, 'Norma', 2, 0, 0, 0, 0, '', 0, '', '', 0, 0, '2023-07-25 02:40:51.147952', '2023-07-25 02:40:51.147952');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(180, 23, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-07-27 19:04:23.457927', '2023-07-27 19:04:23.457927');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(181, 24, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-07-27 19:05:16.558660', '2023-07-27 19:05:16.558660');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(182, 25, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-07-27 19:05:56.275937', '2023-07-27 19:05:56.275937');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(183, 27, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-07-27 19:46:43.267701', '2023-07-27 19:46:43.267701');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(184, 27, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-07-27 19:46:43.285905', '2023-07-27 19:46:43.285905');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(185, 27, 3, 'Tipo de Fluido', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-08-12 04:09:35.861788', '2023-08-12 04:11:13.012036');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(186, 27, 2, 'Temperatura Ambiente (ºC)', 4, 1, 0, 0, 1, '21.3', 0, '', '', 0, 0, '2023-08-12 04:09:35.885746', '2023-08-12 04:12:41.112061');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(187, 27, 2, 'Humedad Ambiente (%)', 5, 1, 0, 0, 1, '41', 0, '', '', 0, 0, '2023-08-12 04:09:35.905145', '2023-08-12 04:12:41.132670');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(188, 27, 3, 'Espinterometro', 6, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-08-12 04:09:35.923772', '2023-08-12 04:11:13.096365');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(189, 27, 2, 'Temperatura Muestra (ºC)', 7, 1, 0, 0, 0, '', 1, 'Temperatura:', '°C', 0, 0, '2023-08-12 04:09:35.939350', '2023-10-26 14:35:10.154429');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(190, 27, 2, 'Resultado (KV)', 8, 1, 0, 0, 0, '', 1, 'Valor medio:', 'kV', 0, 0, '2023-08-12 04:09:35.953056', '2023-10-26 14:35:10.164943');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(191, 7, 1, 'LEC 1', 4, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-10-05 07:05:38.374079', '2023-10-05 07:06:29.097803');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(192, 7, 1, 'LEC 2', 5, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2023-10-05 07:05:38.428081', '2023-10-05 07:06:29.111391');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(193, 28, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 1, 'Número de muestra:', '', 0, 0, '2024-01-10 18:01:49.621469', '2024-01-10 18:17:58.105607');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(194, 28, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2024-01-10 18:01:49.637650', '2024-01-10 18:39:02.536675');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(195, 28, 3, 'Tipo de Equipo', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2024-01-10 18:01:49.652748', '2024-01-10 18:01:49.652748');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(196, 28, 2, 'Temperatura (ºC)', 4, 1, 0, 0, 1, '25', 1, 'Temperatura del ensayo:', '°C', 0, 0, '2024-01-10 18:01:49.681056', '2024-01-10 18:39:35.367157');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(197, 28, 2, 'Rho+ (Ωcm)', 5, 1, 0, 1, 0, '', 1, 'Rho+:', ':Ωcm', 0, 0, '2024-01-10 18:01:49.696090', '2024-01-10 18:54:05.790908');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(198, 28, 2, 'Rho- (Ωcm)', 6, 1, 0, 1, 0, '', 1, 'Rho-:', ':Ωcm', 0, 0, '2024-01-10 18:01:49.712800', '2024-01-10 18:55:12.890018');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(199, 28, 2, 'Resultado (Ωcm)', 7, 1, 1, 0, 0, '', 0, '', '', 1, 0, '2024-01-10 18:01:49.729938', '2024-01-10 19:18:20.276059');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(200, 29, 1, 'Nº de Muestra', 1, 1, 0, 0, 0, '', 1, 'Número de muestra:', '', 0, 0, '2024-01-10 18:37:06.501127', '2024-01-10 18:37:06.501127');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(201, 29, 3, 'Norma', 2, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2024-01-10 18:37:06.531111', '2024-01-10 19:04:46.853153');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(202, 29, 3, 'Tipo de Equipo', 3, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2024-01-10 18:37:06.546717', '2024-01-10 18:37:06.546717');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(203, 29, 2, 'Temperatura (ºC)', 4, 1, 0, 0, 1, '100', 1, 'Temperatura del ensayo:', '°C', 0, 0, '2024-01-10 18:37:06.561553', '2024-01-10 18:38:33.781241');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(204, 29, 2, 'Rho+ (Ωcm)', 5, 1, 0, 1, 0, '', 1, 'Rho+:', ':Ωcm', 0, 0, '2024-01-10 18:37:06.577354', '2024-01-10 18:37:06.577354');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(205, 29, 2, 'Rho- (Ωcm)', 6, 1, 0, 1, 0, '', 1, 'Rho-:', ':Ωcm', 0, 0, '2024-01-10 18:37:06.589457', '2024-01-10 18:37:06.589457');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(206, 29, 2, 'Resultado (Ωcm)', 7, 1, 1, 0, 0, '', 0, '', '', 0, 0, '2024-01-10 18:37:06.616450', '2024-01-10 18:37:06.616450');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(207, 6, 3, 'R1 PP-LA-01C', 4, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2024-12-02 11:51:34.619075', '2024-12-02 22:26:57.685609');
INSERT INTO `lab_category_sub_details` (`id`, `lab_category_detail_id`, `lab_category_sub_detail_type_id`, `name`, `num_pos`, `is_required`, `is_blocked`, `is_blur`, `is_reuse`, `reuse_value`, `is_imported`, `imported_value`, `imported_remove_value`, `report_use`, `deleted`, `created_at`, `updated_at`) VALUES
(208, 6, 3, 'R2 PP-LA-01C', 6, 1, 0, 0, 0, '', 0, '', '', 0, 0, '2024-12-02 11:51:34.632617', '2024-12-02 22:26:57.718648');
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(1, 2, 'ASTM D974', '2023-04-07 02:47:30.049137', '2023-04-07 02:47:30.049137', 'A', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(2, 3, 'PP-LA-01C-065', '2024-12-02 10:52:15.501222', '2023-04-07 02:47:30.059220', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(3, 3, 'PP-LA-01C-023', '2024-12-02 10:52:15.503397', '2023-04-07 02:47:30.068761', NULL, 3, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(4, 4, 'PP-LA-01C-056', '2023-04-07 02:47:30.077593', '2023-04-07 02:47:30.077593', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(5, 11, 'PP-LA-01C-087', '2025-12-13 14:13:19.628907', '2023-04-07 02:47:30.085214', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(6, 11, 'PP-LA-01C-076', '2025-12-13 14:13:19.630824', '2023-04-07 02:47:30.093387', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(7, 12, 'ASTM D924', '2023-04-07 02:47:30.101012', '2023-04-07 02:47:30.101012', 'A', 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(8, 12, 'ASTM D1169', '2025-12-13 14:13:19.625224', '2023-04-07 02:47:30.109820', 'NA', 4, 0, 1);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(9, 12, 'IEC 60247', '2023-04-07 02:47:30.117558', '2023-04-07 02:47:30.117558', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(10, 12, 'IEC 61620', '2025-12-13 14:13:19.627004', '2023-04-07 02:47:30.126606', 'NA', 3, 0, 1);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(11, 18, 'PP-LA-01C-087', '2025-12-13 14:25:54.985393', '2023-04-07 02:47:30.135848', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(12, 18, 'PP-LA-01C-076', '2025-12-13 14:25:54.987103', '2023-04-07 02:47:30.145921', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(13, 19, 'ASTM D924', '2023-04-07 02:47:30.156451', '2023-04-07 02:47:30.156451', 'A', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(14, 19, 'ASTM D1169', '2025-12-13 14:30:38.585842', '2023-04-07 02:47:30.164990', 'NA', 2, 0, 1);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(15, 19, 'IEC 60247', '2025-12-13 14:25:54.979974', '2023-04-07 02:47:30.174124', 'NA', 3, 0, 1);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(16, 19, 'IEC 61620', '2025-12-13 14:25:54.982860', '2023-04-07 02:47:30.183779', 'NA', 4, 0, 1);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(17, 25, 'Mineral', '2023-04-07 02:47:30.197341', '2023-04-07 02:47:30.197341', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(18, 25, 'Vegetal', '2023-04-07 02:47:30.206919', '2023-04-07 02:47:30.206919', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(19, 25, 'Ester Sintético', '2023-04-07 02:47:30.217009', '2023-04-07 02:47:30.217009', NULL, 3, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(20, 25, 'Silicona', '2023-04-07 02:47:30.227482', '2023-04-07 02:47:30.227482', NULL, 4, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(21, 28, 'PP-LA-01C-003', '2023-04-07 02:47:30.235916', '2023-04-07 02:47:30.235916', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(22, 28, 'PP-LA-01C-006', '2023-04-07 02:47:30.245327', '2023-04-07 02:47:30.245327', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(23, 29, 'ASTM D1816', '2023-04-07 02:47:30.259574', '2023-04-07 02:47:30.259574', 'A', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(24, 29, 'IEC 60156', '2023-04-07 02:47:30.267316', '2023-04-07 02:47:30.267316', 'NA', 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(25, 33, 'ASTM D971', '2023-04-07 02:47:30.277068', '2023-04-07 02:47:30.277068', 'A', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(26, 34, 'PP-LA-01C-094', '2023-04-07 02:47:30.286866', '2023-04-07 02:47:30.286866', NULL, 2, 1, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(27, 34, 'PP-LA-01C-095', '2023-04-07 02:47:30.295557', '2023-04-07 02:47:30.295557', NULL, 3, 1, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(28, 35, 'PP-LA-01C-014', '2023-04-07 02:47:30.305233', '2023-04-07 02:47:30.305233', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(29, 36, 'PP-LA-01C-021', '2023-04-07 02:47:30.313900', '2023-04-07 02:47:30.313900', NULL, 3, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(30, 36, 'PP-LA-01C-022', '2023-04-07 02:47:30.322893', '2023-04-07 02:47:30.322893', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(31, 36, 'PP-LA-01C-031', '2023-04-07 02:47:30.333044', '2023-04-07 02:47:30.333044', NULL, 4, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(32, 36, 'PP-LA-01C-078', '2023-04-07 02:47:30.341643', '2023-04-07 02:47:30.341643', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(33, 44, 'ASTM D1533', '2023-04-07 02:47:30.351193', '2023-04-07 02:47:30.351193', 'A', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(34, 45, 'PP-LA-01C-056', '2023-04-07 02:47:30.361074', '2023-04-07 02:47:30.361074', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(35, 51, 'ASTM D1500', '2023-04-07 02:47:30.370112', '2023-04-07 02:47:30.370112', 'A', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(36, 52, 'PP-LA-01C-045', '2023-04-07 02:47:30.379873', '2023-04-07 02:47:30.379873', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(37, 55, 'ASTM D1524', '2023-04-07 02:47:30.388235', '2023-04-07 02:47:30.388235', 'A', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(38, 58, 'ASTM D1298', '2023-04-07 02:47:30.396589', '2023-04-07 02:47:30.396589', 'NA', 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(39, 58, 'ASTM D7777', '2023-04-07 02:47:30.406739', '2023-04-07 02:47:30.406739', 'A', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(40, 58, 'ASTM D4052', '2023-04-07 02:47:30.419287', '2023-04-07 02:47:30.419287', 'NA', 3, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(41, 73, 'PP-LA-01C-082', '2025-12-13 14:45:27.830219', '2023-04-07 02:47:30.430463', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(42, 79, 'PP-LA-01C-102', '2025-12-13 14:45:12.079104', '2023-04-07 02:47:30.439635', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(43, 94, 'ASTM 1275', '2025-12-13 14:44:58.152457', '2023-04-07 02:47:30.450130', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(44, 99, 'IEC 62535', '2025-12-13 14:44:45.500923', '2023-04-07 02:47:30.459683', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(45, 104, 'IEC 62535', '2025-12-13 14:44:32.758180', '2023-04-07 02:47:30.468255', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(46, 109, 'ASTM D4243', '2025-12-13 14:44:19.339172', '2023-04-07 02:47:30.478626', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(47, 112, 'ASTM D445', '2025-12-13 14:43:28.936380', '2023-04-07 02:47:30.488424', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(48, 120, 'ASTM D6786', '2025-12-13 14:43:13.097182', '2023-04-07 02:47:30.497514', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(49, 130, 'ASTM D7151', '2025-12-13 14:42:50.755231', '2023-04-07 02:47:30.506350', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(50, 140, 'ASTM D2668', '2025-12-13 14:41:24.372227', '2023-04-07 02:47:30.516728', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(51, 95, 'PP-LA-01C-013', '2025-12-13 14:44:58.155139', '2023-04-07 02:47:30.526674', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(52, 100, 'PP-LA-01C-013', '2025-12-13 14:44:45.503460', '2023-04-07 02:47:30.536793', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(53, 105, 'PP-LA-01C-013', '2025-12-13 14:44:32.762321', '2023-04-07 02:47:30.545696', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(54, 143, 'IEC 62697', '2023-04-07 02:47:30.554467', '2023-04-07 02:47:30.554467', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(55, 146, 'ASTM D1698', '2023-04-07 02:47:30.563930', '2023-04-07 02:47:30.563930', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(56, 87, 'PP-LA-01C-087', '2023-04-07 02:47:30.572582', '2023-04-07 02:47:30.572582', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(57, 87, 'PP-LA-01C-076', '2023-04-07 02:47:30.581539', '2023-04-07 02:47:30.581539', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(58, 88, 'ASTM D924', '2023-04-07 02:47:30.591899', '2023-04-07 02:47:30.591899', 'A', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(62, 177, 'ASTM 3612 - Método C', '2025-12-13 14:45:47.857105', '2023-07-07 12:49:57.273540', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(63, 178, 'ASTM D4059', '2025-12-13 14:45:27.827952', '2023-07-24 02:32:03.232343', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(64, 179, 'ASTM D5837 ', '2025-12-13 14:45:12.076876', '2023-07-25 02:40:51.154284', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(65, 180, 'ASTM D97', '2023-07-27 19:04:23.460223', '2023-07-27 19:04:23.460223', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(66, 181, 'ASTM D92', '2025-12-13 14:39:47.860636', '2023-07-27 19:05:16.561454', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(67, 182, 'IEC 60666-10', '2023-07-27 19:06:16.132908', '2023-07-27 19:05:56.283248', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(68, 184, 'ASTM D877 ', '2025-12-13 07:17:43.311215', '2023-07-27 19:46:43.290705', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(69, 185, 'Mineral', '2025-12-13 07:17:43.314341', '2023-08-12 04:11:13.031936', NULL, 4, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(70, 185, 'Vegetal', '2025-12-13 07:17:43.316765', '2023-08-12 04:11:13.035873', NULL, 3, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(71, 185, 'Ester Sintético', '2025-12-13 07:17:43.318745', '2023-08-12 04:11:13.038976', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(72, 185, 'Silicona', '2025-12-13 07:17:43.321036', '2023-08-12 04:11:13.046269', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(73, 188, 'PP-LA-01C-003', '2025-12-13 07:17:43.323696', '2023-08-12 04:11:13.114943', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(74, 184, 'IEC 60156', '2023-08-12 04:13:46.800209', '2023-08-12 04:12:41.108880', 'NA', 2, 0, 1);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(75, 188, 'PP-LA-01C-006', '2025-12-13 07:17:43.325707', '2023-08-12 04:12:41.155042', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(76, 195, '-', '2024-01-10 18:01:49.656850', '2024-01-10 18:01:49.656850', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(77, 194, 'ASTM D1169', '2024-01-10 18:08:01.081603', '2024-01-10 18:08:01.081603', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(78, 201, 'ASTM D1169', '2024-01-10 18:37:06.535294', '2024-01-10 18:37:06.535294', 'NA', 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(79, 202, '-', '2024-01-10 18:37:06.550407', '2024-01-10 18:37:06.550407', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(80, 46, 'R1 PP-LA-01C-007', '2024-12-02 10:14:04.390995', '2024-12-02 10:11:40.140386', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(81, 46, 'R1 PP-LA-01C-106', '2024-12-02 10:13:06.110186', '2024-12-02 10:13:06.110186', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(82, 47, 'R1 PP-LA-01C-011', '2024-12-02 10:14:04.393815', '2024-12-02 10:13:06.147737', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(83, 47, 'R1 PP-LA-01C-106', '2024-12-02 10:13:06.149602', '2024-12-02 10:13:06.149602', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(84, 3, 'PP-LA-01C-100', '2024-12-02 10:36:45.995021', '2024-12-02 10:36:45.995021', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(85, 3, 'PP-LA-01C-100.', '2024-12-02 10:52:15.505103', '2024-12-02 10:40:09.429805', NULL, 4, 0, 1);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(86, 79, 'PP-LA-01C-002', '2024-12-02 10:47:08.916712', '2024-12-02 10:42:30.361104', NULL, 3, 0, 1);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(87, 79, 'PP-LA-01C-107', '2025-12-13 14:45:12.080720', '2024-12-02 10:48:20.182131', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(88, 207, 'R1 PP-LA-01C-007', '2024-12-02 21:38:05.610324', '2024-12-02 11:51:34.621884', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(89, 208, 'R2 PP-LA-01C-011', '2024-12-02 21:38:05.665278', '2024-12-02 11:51:34.635147', NULL, 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(90, 207, 'R1 PP-LA-01C-106', '2024-12-02 21:38:05.612426', '2024-12-02 21:38:05.612426', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(91, 208, 'R2 PP-LA-01C-106', '2024-12-02 21:38:05.667293', '2024-12-02 21:38:05.667293', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(92, 88, 'IEC60247', '2025-12-13 06:54:07.407625', '2025-03-21 16:36:50.473481', 'NA', 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(93, 34, 'PP-LA-01C-112', '2025-06-10 13:29:05.129170', '2025-06-10 13:29:05.129170', NULL, 1, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(94, 2, 'IEC 62021', '2025-12-13 06:16:50.965684', '2025-12-13 06:16:50.965684', 'NA', 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(95, 181, 'ASTM D93', '2025-12-13 14:39:47.863271', '2025-12-13 14:39:47.863271', 'NA', 2, 0, 0);
INSERT INTO `lab_category_sub_detail_options` (`id`, `lab_category_sub_detail_id`, `name`, `updated_at`, `created_at`, `applicability_flag`, `num_pos`, `is_hidden`, `deleted`) VALUES
(96, 140, 'IEC 60666-10', '2025-12-13 14:41:24.374094', '2025-12-13 14:41:24.374094', 'NA', 2, 0, 0);
INSERT INTO `lab_category_sub_detail_types` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'Texto', 0, '2023-04-07 02:47:28.420847', '2023-04-07 02:47:28.420847');
INSERT INTO `lab_category_sub_detail_types` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 'Número', 0, '2023-04-07 02:47:28.436313', '2023-04-07 02:47:28.436313');
INSERT INTO `lab_category_sub_detail_types` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 'Selección', 0, '2023-04-07 02:47:28.450789', '2023-04-07 02:47:28.450789');
INSERT INTO `lab_category_sub_detail_types` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(4, 'Fecha', 0, '2023-04-07 02:47:28.463644', '2023-04-07 02:47:28.463644');
INSERT INTO `lab_detail_types` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'Patrón Control', 0, '2023-04-07 02:47:30.645377', '2023-04-07 02:47:30.645377');
INSERT INTO `lab_detail_types` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 'Muestra', 0, '2023-04-07 02:47:30.657228', '2023-04-07 02:47:30.657228');
INSERT INTO `lab_detail_types` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 'Duplicado', 0, '2023-04-07 02:47:30.670254', '2023-04-07 02:47:30.670254');
INSERT INTO `norms` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'IEEE C57.106-2015', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `norms` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 'IEC 60599-2022', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `norms` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 'IEC 60422-2024', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `norms` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(4, 'IEC 60450', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `norms` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(5, 'IEEE C57.111-1989(R2009)', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `norms` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(6, 'IEEE Std C57.146-2005', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `norms` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(7, 'IEC 61203:1992', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `norms` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(8, 'IEEE Std C57.155-2014', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `norms` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(9, 'IEEE C57.147-2018', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `norms` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(10, 'ASTM D3487-16', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `norms` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(11, 'IEC 60296:2012', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `norms` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(12, 'IEC 610203-2025', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 1, 'Número Ácido', '0.095', '0.095', '0.109', '0.121', '0.121', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2025-10-22 20:27:12');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 2, 'Factor De Potencia 25º', '0.0018', '0.0025', '0.0039', '0.0053', '0.0059', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2024-01-10 16:21:42');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 3, 'Factor De Potencia 100º', '0.022', '0.031', '0.050', '0.068', '0.077', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(4, 4, 'Rigidez Dieléctrica', '16.8', '18.6', '22', '25.4', '27.2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2024-10-30 11:47:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(5, 5, 'Tensión Interfacial', '46.47', '47.11', '48.38', '49.65', '50.29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2024-01-10 14:14:22');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(6, 6, 'Contenido de Agua', '20', '22', '25', '28', '30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2024-12-02 21:56:31');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(7, 7, 'Color', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(8, 8, 'Condición Visual', 'PASA', 'PASA', 'PASA', 'PASA', 'PASA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-10-04 15:44:10');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(9, 9, 'Densidad Relativa', '0.8813', '0.8811', '0.8817', '0.8823', '0.8821', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2025-01-10 09:05:26');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(10, 10, 'Análisis Cromatográfico', '52.88', '54.26', '57.01', '59.76', '63.89', '3446.12', '3473.70', '3528.84', '3583.99', '3611.56', '19213.69', '19396.69', '19762.69', '20128.69', '20311.69', '77.66', '79.13', '82.07', '85.00', '86.47', '302.97', '308.70', '320.15', '331.59', '337.32', '6135.65', '6206.63', '6348.60', '6490.56', '6561.55', '153.95', '156.34', '161.13', '165.91', '168.30', '196.31', '198.66', '203.35', '208.04', '210.39', '67.77', '68.89', '71.14', '73.39', '74.52', 0, '2023-01-25 11:51:37', '2023-10-05 05:33:43');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(11, 11, 'PCB', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(12, 12, 'Furanos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(13, 13, 'Azufre 1275B', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(14, 14, 'Azufre 62535 (48 horas)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(15, 15, 'Azufre 62535 (72 horas)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(16, 16, 'Grado de Polimerización', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(17, 17, 'Viscocidad', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(18, 18, 'Partículas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(19, 19, 'Metales en Aceite', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(20, 20, 'Inhibidor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(21, 21, 'DBDS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(22, 22, 'Sedimentos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(23, 23, 'Fluidez', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(24, 24, 'Inflamación', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(25, 25, 'Pasivador', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(26, 26, 'Factor De Potencia 90º', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `patron_tendences` (`id`, `lab_category_detail_id`, `comment`, `lci`, `lai`, `lc`, `las`, `lcs`, `oxi_lci`, `oxi_lai`, `oxi_lc`, `oxi_las`, `oxi_lcs`, `nit_lci`, `nit_lai`, `nit_lc`, `nit_las`, `nit_lcs`, `met_lci`, `met_lai`, `met_lc`, `met_las`, `met_lcs`, `mon_lci`, `mon_lai`, `mon_lc`, `mon_las`, `mon_lcs`, `dio_lci`, `dio_lai`, `dio_lc`, `dio_las`, `dio_lcs`, `eti_lci`, `eti_lai`, `eti_lc`, `eti_las`, `eti_lcs`, `eta_lci`, `eta_lai`, `eta_lc`, `eta_las`, `eta_lcs`, `ace_lci`, `ace_lai`, `ace_lc`, `ace_las`, `ace_lcs`, `deleted`, `created_at`, `updated_at`) VALUES
(27, 27, 'Rigidez Dielectrica Electrodos planos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `profiles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Administrador Principal', 'Perfil de Administrador Creador del Sistema Web', '2023-04-07 02:47:26.657000', '2023-04-07 02:47:26.657000');
INSERT INTO `profiles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(2, 'Hitachi Master', 'Administrador del Sistema', '2023-04-07 02:47:26.668034', '2023-04-07 02:47:26.668034');
INSERT INTO `profiles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(3, 'Hitachi Operadores', 'No elimina datos,no elimina OS', '2023-04-07 02:47:26.679972', '2023-04-07 02:47:26.679972');
INSERT INTO `profiles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(4, 'Hitachi Operadores - Admin', 'Muestra todo auditoria y ajustes del sistema', '2023-09-01 20:54:33.283430', '2023-09-01 20:54:33.283430');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1469, 1, 1, '2024-07-19 00:56:10.252331', '2024-07-19 00:56:10.252331');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1470, 2, 1, '2024-07-19 00:56:10.256985', '2024-07-19 00:56:10.256985');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1471, 3, 1, '2024-07-19 00:56:10.260766', '2024-07-19 00:56:10.260766');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1472, 4, 1, '2024-07-19 00:56:10.263693', '2024-07-19 00:56:10.263693');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1473, 5, 1, '2024-07-19 00:56:10.266740', '2024-07-19 00:56:10.266740');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1474, 6, 1, '2024-07-19 00:56:10.269318', '2024-07-19 00:56:10.269318');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1475, 7, 1, '2024-07-19 00:56:10.272895', '2024-07-19 00:56:10.272895');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1476, 8, 1, '2024-07-19 00:56:10.276566', '2024-07-19 00:56:10.276566');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1477, 9, 1, '2024-07-19 00:56:10.279504', '2024-07-19 00:56:10.279504');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1478, 10, 1, '2024-07-19 00:56:10.283055', '2024-07-19 00:56:10.283055');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1479, 11, 1, '2024-07-19 00:56:10.287032', '2024-07-19 00:56:10.287032');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1480, 12, 1, '2024-07-19 00:56:10.291715', '2024-07-19 00:56:10.291715');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1481, 13, 1, '2024-07-19 00:56:10.294887', '2024-07-19 00:56:10.294887');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1482, 14, 1, '2024-07-19 00:56:10.298619', '2024-07-19 00:56:10.298619');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1483, 15, 1, '2024-07-19 00:56:10.301756', '2024-07-19 00:56:10.301756');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1484, 16, 1, '2024-07-19 00:56:10.305065', '2024-07-19 00:56:10.305065');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1485, 17, 1, '2024-07-19 00:56:10.308783', '2024-07-19 00:56:10.308783');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1486, 18, 1, '2024-07-19 00:56:10.312108', '2024-07-19 00:56:10.312108');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1487, 19, 1, '2024-07-19 00:56:10.315601', '2024-07-19 00:56:10.315601');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1488, 20, 1, '2024-07-19 00:56:10.319800', '2024-07-19 00:56:10.319800');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1489, 21, 1, '2024-07-19 00:56:10.323726', '2024-07-19 00:56:10.323726');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1490, 22, 1, '2024-07-19 00:56:10.327900', '2024-07-19 00:56:10.327900');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1491, 23, 1, '2024-07-19 00:56:10.333510', '2024-07-19 00:56:10.333510');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1492, 24, 1, '2024-07-19 00:56:10.342825', '2024-07-19 00:56:10.342825');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1493, 25, 1, '2024-07-19 00:56:10.347036', '2024-07-19 00:56:10.347036');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1494, 26, 1, '2024-07-19 00:56:10.354819', '2024-07-19 00:56:10.354819');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1495, 27, 1, '2024-07-19 00:56:10.358818', '2024-07-19 00:56:10.358818');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1496, 28, 1, '2024-07-19 00:56:10.373125', '2024-07-19 00:56:10.373125');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1497, 29, 1, '2024-07-19 00:56:10.383146', '2024-07-19 00:56:10.383146');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1498, 30, 1, '2024-07-19 00:56:10.392096', '2024-07-19 00:56:10.392096');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1499, 31, 1, '2024-07-19 00:56:10.409650', '2024-07-19 00:56:10.409650');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1500, 32, 1, '2024-07-19 00:56:10.419710', '2024-07-19 00:56:10.419710');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1501, 33, 1, '2024-07-19 00:56:10.427972', '2024-07-19 00:56:10.427972');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1502, 34, 1, '2024-07-19 00:56:10.435540', '2024-07-19 00:56:10.435540');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1503, 35, 1, '2024-07-19 00:56:10.440711', '2024-07-19 00:56:10.440711');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1504, 36, 1, '2024-07-19 00:56:10.446053', '2024-07-19 00:56:10.446053');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1505, 37, 1, '2024-07-19 00:56:10.479064', '2024-07-19 00:56:10.479064');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1506, 38, 1, '2024-07-19 00:56:10.485072', '2024-07-19 00:56:10.485072');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1507, 39, 1, '2024-07-19 00:56:10.490341', '2024-07-19 00:56:10.490341');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1508, 40, 1, '2024-07-19 00:56:10.495904', '2024-07-19 00:56:10.495904');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1509, 41, 1, '2024-07-19 00:56:10.500974', '2024-07-19 00:56:10.500974');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1510, 42, 1, '2024-07-19 00:56:10.504988', '2024-07-19 00:56:10.504988');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1511, 43, 1, '2024-07-19 00:56:10.508128', '2024-07-19 00:56:10.508128');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1512, 44, 1, '2024-07-19 00:56:10.511597', '2024-07-19 00:56:10.511597');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1513, 45, 1, '2024-07-19 00:56:10.517163', '2024-07-19 00:56:10.517163');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1514, 46, 1, '2024-07-19 00:56:10.521523', '2024-07-19 00:56:10.521523');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1515, 47, 1, '2024-07-19 00:56:10.525741', '2024-07-19 00:56:10.525741');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1516, 48, 1, '2024-07-19 00:56:10.529088', '2024-07-19 00:56:10.529088');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1517, 49, 1, '2024-07-19 00:56:10.532130', '2024-07-19 00:56:10.532130');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1518, 50, 1, '2024-07-19 00:56:10.535680', '2024-07-19 00:56:10.535680');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1519, 51, 1, '2024-07-19 00:56:10.540811', '2024-07-19 00:56:10.540811');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1520, 52, 1, '2024-07-19 00:56:10.544123', '2024-07-19 00:56:10.544123');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1521, 53, 1, '2024-07-19 00:56:10.547480', '2024-07-19 00:56:10.547480');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1522, 54, 1, '2024-07-19 00:56:10.551850', '2024-07-19 00:56:10.551850');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1523, 55, 1, '2024-07-19 00:56:10.555757', '2024-07-19 00:56:10.555757');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1524, 56, 1, '2024-07-19 00:56:10.560196', '2024-07-19 00:56:10.560196');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1525, 57, 1, '2024-07-19 00:56:10.564659', '2024-07-19 00:56:10.564659');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1526, 58, 1, '2024-07-19 00:56:10.568624', '2024-07-19 00:56:10.568624');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1527, 59, 1, '2024-07-19 00:56:10.572828', '2024-07-19 00:56:10.572828');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1528, 60, 1, '2024-07-19 00:56:10.575865', '2024-07-19 00:56:10.575865');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1529, 61, 1, '2024-07-19 00:56:10.579429', '2024-07-19 00:56:10.579429');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1530, 62, 1, '2024-07-19 00:56:10.582871', '2024-07-19 00:56:10.582871');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1531, 63, 1, '2024-07-19 00:56:10.586672', '2024-07-19 00:56:10.586672');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1532, 64, 1, '2024-07-19 00:56:10.589666', '2024-07-19 00:56:10.589666');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1533, 65, 1, '2024-07-19 00:56:10.592621', '2024-07-19 00:56:10.592621');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1534, 66, 1, '2024-07-19 00:56:10.596295', '2024-07-19 00:56:10.596295');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1535, 1, 2, '2024-07-19 00:56:25.322823', '2024-07-19 00:56:25.322823');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1536, 2, 2, '2024-07-19 00:56:25.326228', '2024-07-19 00:56:25.326228');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1537, 3, 2, '2024-07-19 00:56:25.328265', '2024-07-19 00:56:25.328265');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1538, 4, 2, '2024-07-19 00:56:25.330757', '2024-07-19 00:56:25.330757');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1539, 5, 2, '2024-07-19 00:56:25.333247', '2024-07-19 00:56:25.333247');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1540, 6, 2, '2024-07-19 00:56:25.336406', '2024-07-19 00:56:25.336406');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1541, 23, 2, '2024-07-19 00:56:25.339200', '2024-07-19 00:56:25.339200');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1542, 24, 2, '2024-07-19 00:56:25.342933', '2024-07-19 00:56:25.342933');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1543, 25, 2, '2024-07-19 00:56:25.345723', '2024-07-19 00:56:25.345723');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1544, 26, 2, '2024-07-19 00:56:25.348305', '2024-07-19 00:56:25.348305');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1545, 27, 2, '2024-07-19 00:56:25.352366', '2024-07-19 00:56:25.352366');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1546, 28, 2, '2024-07-19 00:56:25.354710', '2024-07-19 00:56:25.354710');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1547, 29, 2, '2024-07-19 00:56:25.356913', '2024-07-19 00:56:25.356913');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1548, 30, 2, '2024-07-19 00:56:25.360835', '2024-07-19 00:56:25.360835');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1549, 31, 2, '2024-07-19 00:56:25.364274', '2024-07-19 00:56:25.364274');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1550, 32, 2, '2024-07-19 00:56:25.367465', '2024-07-19 00:56:25.367465');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1551, 33, 2, '2024-07-19 00:56:25.371015', '2024-07-19 00:56:25.371015');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1552, 34, 2, '2024-07-19 00:56:25.374926', '2024-07-19 00:56:25.374926');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1553, 35, 2, '2024-07-19 00:56:25.380376', '2024-07-19 00:56:25.380376');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1554, 36, 2, '2024-07-19 00:56:25.384971', '2024-07-19 00:56:25.384971');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1555, 37, 2, '2024-07-19 00:56:25.387774', '2024-07-19 00:56:25.387774');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1556, 39, 2, '2024-07-19 00:56:25.392705', '2024-07-19 00:56:25.392705');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1557, 40, 2, '2024-07-19 00:56:25.396787', '2024-07-19 00:56:25.396787');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1558, 41, 2, '2024-07-19 00:56:25.399785', '2024-07-19 00:56:25.399785');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1559, 42, 2, '2024-07-19 00:56:25.403047', '2024-07-19 00:56:25.403047');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1560, 43, 2, '2024-07-19 00:56:25.407090', '2024-07-19 00:56:25.407090');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1561, 44, 2, '2024-07-19 00:56:25.411594', '2024-07-19 00:56:25.411594');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1562, 45, 2, '2024-07-19 00:56:25.416325', '2024-07-19 00:56:25.416325');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1563, 46, 2, '2024-07-19 00:56:25.420939', '2024-07-19 00:56:25.420939');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1564, 47, 2, '2024-07-19 00:56:25.424907', '2024-07-19 00:56:25.424907');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1565, 48, 2, '2024-07-19 00:56:25.428255', '2024-07-19 00:56:25.428255');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1566, 49, 2, '2024-07-19 00:56:25.432334', '2024-07-19 00:56:25.432334');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1567, 50, 2, '2024-07-19 00:56:25.438393', '2024-07-19 00:56:25.438393');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1568, 51, 2, '2024-07-19 00:56:25.443652', '2024-07-19 00:56:25.443652');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1569, 52, 2, '2024-07-19 00:56:25.448135', '2024-07-19 00:56:25.448135');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1570, 53, 2, '2024-07-19 00:56:25.451746', '2024-07-19 00:56:25.451746');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1571, 54, 2, '2024-07-19 00:56:25.455117', '2024-07-19 00:56:25.455117');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1572, 55, 2, '2024-07-19 00:56:25.458513', '2024-07-19 00:56:25.458513');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1573, 56, 2, '2024-07-19 00:56:25.461632', '2024-07-19 00:56:25.461632');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1574, 57, 2, '2024-07-19 00:56:25.464093', '2024-07-19 00:56:25.464093');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1575, 58, 2, '2024-07-19 00:56:25.466842', '2024-07-19 00:56:25.466842');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1576, 59, 2, '2024-07-19 00:56:25.470990', '2024-07-19 00:56:25.470990');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1577, 60, 2, '2024-07-19 00:56:25.473577', '2024-07-19 00:56:25.473577');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1578, 61, 2, '2024-07-19 00:56:25.476989', '2024-07-19 00:56:25.476989');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1579, 62, 2, '2024-07-19 00:56:25.480060', '2024-07-19 00:56:25.480060');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1580, 63, 2, '2024-07-19 00:56:25.482377', '2024-07-19 00:56:25.482377');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1581, 64, 2, '2024-07-19 00:56:25.484898', '2024-07-19 00:56:25.484898');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1582, 65, 2, '2024-07-19 00:56:25.488403', '2024-07-19 00:56:25.488403');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1583, 66, 2, '2024-07-19 00:56:25.491342', '2024-07-19 00:56:25.491342');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1584, 32, 3, '2024-07-19 00:56:39.734747', '2024-07-19 00:56:39.734747');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1585, 33, 3, '2024-07-19 00:56:39.740855', '2024-07-19 00:56:39.740855');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1586, 34, 3, '2024-07-19 00:56:39.745524', '2024-07-19 00:56:39.745524');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1587, 35, 3, '2024-07-19 00:56:39.748820', '2024-07-19 00:56:39.748820');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1588, 36, 3, '2024-07-19 00:56:39.752635', '2024-07-19 00:56:39.752635');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1589, 40, 3, '2024-07-19 00:56:39.756030', '2024-07-19 00:56:39.756030');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1590, 41, 3, '2024-07-19 00:56:39.759279', '2024-07-19 00:56:39.759279');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1591, 42, 3, '2024-07-19 00:56:39.763779', '2024-07-19 00:56:39.763779');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1592, 45, 3, '2024-07-19 00:56:39.767071', '2024-07-19 00:56:39.767071');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1593, 49, 3, '2024-07-19 00:56:39.770760', '2024-07-19 00:56:39.770760');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1594, 50, 3, '2024-07-19 00:56:39.775704', '2024-07-19 00:56:39.775704');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1595, 51, 3, '2024-07-19 00:56:39.779514', '2024-07-19 00:56:39.779514');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1596, 52, 3, '2024-07-19 00:56:39.783250', '2024-07-19 00:56:39.783250');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1597, 53, 3, '2024-07-19 00:56:39.787037', '2024-07-19 00:56:39.787037');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1598, 54, 3, '2024-07-19 00:56:39.790502', '2024-07-19 00:56:39.790502');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1599, 55, 3, '2024-07-19 00:56:39.793098', '2024-07-19 00:56:39.793098');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1600, 64, 3, '2024-07-19 00:56:39.796529', '2024-07-19 00:56:39.796529');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1601, 65, 3, '2024-07-19 00:56:39.799358', '2024-07-19 00:56:39.799358');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1602, 66, 3, '2024-07-19 00:56:39.802541', '2024-07-19 00:56:39.802541');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1603, 29, 4, '2024-07-19 00:57:08.564925', '2024-07-19 00:57:08.564925');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1604, 30, 4, '2024-07-19 00:57:08.575189', '2024-07-19 00:57:08.575189');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1605, 31, 4, '2024-07-19 00:57:08.579321', '2024-07-19 00:57:08.579321');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1606, 32, 4, '2024-07-19 00:57:08.582361', '2024-07-19 00:57:08.582361');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1607, 33, 4, '2024-07-19 00:57:08.584718', '2024-07-19 00:57:08.584718');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1608, 34, 4, '2024-07-19 00:57:08.587695', '2024-07-19 00:57:08.587695');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1609, 35, 4, '2024-07-19 00:57:08.590731', '2024-07-19 00:57:08.590731');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1610, 36, 4, '2024-07-19 00:57:08.592962', '2024-07-19 00:57:08.592962');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1611, 37, 4, '2024-07-19 00:57:08.595960', '2024-07-19 00:57:08.595960');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1612, 39, 4, '2024-07-19 00:57:08.598455', '2024-07-19 00:57:08.598455');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1613, 40, 4, '2024-07-19 00:57:08.601455', '2024-07-19 00:57:08.601455');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1614, 41, 4, '2024-07-19 00:57:08.604782', '2024-07-19 00:57:08.604782');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1615, 42, 4, '2024-07-19 00:57:08.608720', '2024-07-19 00:57:08.608720');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1616, 43, 4, '2024-07-19 00:57:08.611540', '2024-07-19 00:57:08.611540');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1617, 44, 4, '2024-07-19 00:57:08.614777', '2024-07-19 00:57:08.614777');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1618, 45, 4, '2024-07-19 00:57:08.617494', '2024-07-19 00:57:08.617494');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1619, 46, 4, '2024-07-19 00:57:08.620421', '2024-07-19 00:57:08.620421');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1620, 47, 4, '2024-07-19 00:57:08.623864', '2024-07-19 00:57:08.623864');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1621, 49, 4, '2024-07-19 00:57:08.627661', '2024-07-19 00:57:08.627661');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1622, 50, 4, '2024-07-19 00:57:08.631679', '2024-07-19 00:57:08.631679');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1623, 51, 4, '2024-07-19 00:57:08.634896', '2024-07-19 00:57:08.634896');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1624, 52, 4, '2024-07-19 00:57:08.639055', '2024-07-19 00:57:08.639055');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1625, 53, 4, '2024-07-19 00:57:08.642547', '2024-07-19 00:57:08.642547');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1626, 54, 4, '2024-07-19 00:57:08.645408', '2024-07-19 00:57:08.645408');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1627, 55, 4, '2024-07-19 00:57:08.649922', '2024-07-19 00:57:08.649922');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1628, 56, 4, '2024-07-19 00:57:08.653676', '2024-07-19 00:57:08.653676');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1629, 57, 4, '2024-07-19 00:57:08.656330', '2024-07-19 00:57:08.656330');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1630, 58, 4, '2024-07-19 00:57:08.659266', '2024-07-19 00:57:08.659266');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1631, 59, 4, '2024-07-19 00:57:08.662920', '2024-07-19 00:57:08.662920');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1632, 60, 4, '2024-07-19 00:57:08.666269', '2024-07-19 00:57:08.666269');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1633, 61, 4, '2024-07-19 00:57:08.668646', '2024-07-19 00:57:08.668646');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1634, 62, 4, '2024-07-19 00:57:08.671651', '2024-07-19 00:57:08.671651');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1635, 63, 4, '2024-07-19 00:57:08.674290', '2024-07-19 00:57:08.674290');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1636, 64, 4, '2024-07-19 00:57:08.676418', '2024-07-19 00:57:08.676418');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1637, 65, 4, '2024-07-19 00:57:08.681702', '2024-07-19 00:57:08.681702');
INSERT INTO `profile_accesses` (`id`, `access_id`, `profile_id`, `created_at`, `updated_at`) VALUES
(1638, 66, 4, '2024-07-19 00:57:08.686117', '2024-07-19 00:57:08.686117');
INSERT INTO `rem_report_reasons` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'Rutina', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `rem_report_reasons` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 'Evento', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `rem_report_reasons` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 'Tratamiento Termo Vacío', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `rem_report_reasons` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(4, 'Tratamiento Regeneración', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `rem_report_reasons` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(5, 'Cambio de aceite', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `rem_report_reasons` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(6, 'Otros', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `stock_units` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'unidad', 0, '2023-09-05 02:27:23', '2023-09-05 02:27:23');
INSERT INTO `stock_units` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 'juego', 0, '2023-09-05 02:27:23', '2023-09-05 02:27:23');
INSERT INTO `stock_units` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 'paquete', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(1, '-', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 'Nynas', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 'Nynas Distro DT11EU', 0, '2023-01-25 11:51:37', '2026-03-24 12:52:43');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(4, 'Electrolube', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(5, 'Nynas Orion I', 0, '2023-01-25 11:51:37', '2023-10-02 19:29:07');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(6, 'Nynas 10GBN', 0, '2023-01-25 11:51:37', '2023-11-27 16:46:33');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(8, 'Nynas Izar I', 0, '2023-09-06 18:34:16', '2023-09-06 18:34:16');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(9, 'NYNAS LIBRA 1', 1, '2023-09-07 21:18:55', '2026-03-24 12:50:22');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(10, 'Nynas Nytro Izar II', 1, '2023-09-11 13:32:29', '2023-10-05 18:15:45');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(11, 'Shell Diala D', 0, '2023-09-11 13:41:27', '2023-09-11 13:41:27');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(12, 'Shell', 0, '2023-09-11 13:46:41', '2023-09-11 13:46:41');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(13, 'Nynas Izar II', 0, '2023-09-11 13:50:37', '2023-09-11 13:50:37');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(14, 'Hyvotl I', 1, '2023-09-11 20:09:17', '2024-01-12 14:37:07');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(15, 'Nytro Libra', 0, '2023-09-12 19:25:41', '2023-09-12 19:25:41');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(16, 'Envirotemp FR3', 0, '2023-09-20 20:39:19', '2024-09-11 11:05:13');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(17, 'Nynas Taurus', 0, '2023-09-22 21:33:34', '2023-09-22 21:33:34');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(18, 'NYNAS 10XN', 0, '2023-10-02 19:11:18', '2023-10-02 19:11:18');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(19, 'Ergon-Hyvolt I', 0, '2023-10-04 13:05:50', '2023-10-04 13:05:50');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(20, 'PURAMIN', 0, '2023-10-05 19:47:12', '2023-10-05 19:47:12');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(21, 'Nynas Orion II', 0, '2023-10-10 13:33:32', '2023-10-10 13:33:32');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(22, 'Ergon HyVolt II', 0, '2023-10-16 18:30:45', '2023-10-16 18:30:45');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(23, 'FR3', 0, '2023-10-19 18:53:05', '2023-10-19 18:53:05');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(24, 'Nynas Nitro Izar II', 1, '2023-11-06 19:41:53', '2026-03-24 12:49:30');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(25, 'Nynas Libra', 0, '2023-11-07 21:17:53', '2026-03-24 12:51:26');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(26, 'CALTRAN 60-00', 0, '2023-11-14 13:46:18', '2023-11-14 13:46:18');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(27, 'NYTRO ORION I', 0, '2023-11-14 13:49:03', '2023-11-14 13:49:03');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(28, 'NYTRO IZAR I', 0, '2023-11-14 13:56:00', '2023-11-14 13:56:00');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(29, 'NYNAS NITRO ORION I', 1, '2023-11-14 14:14:39', '2026-03-24 12:50:53');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(30, 'HYVOLT I', 0, '2023-11-15 14:07:59', '2023-11-15 14:07:59');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(31, 'NYTRO 10GBN', 0, '2023-11-27 16:48:50', '2023-11-27 16:48:50');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(32, 'SHELL DIALA DX', 0, '2023-12-18 13:42:35', '2023-12-18 13:42:35');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(33, 'ELECTRO 77', 0, '2023-12-19 15:00:45', '2023-12-19 15:00:45');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(34, 'Nytro Orion II', 0, '2023-12-21 10:35:56', '2023-12-21 10:35:56');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(35, 'Shell Diala S2', 0, '2024-01-22 19:56:12', '2024-01-22 19:56:12');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(36, 'RP-Electra 3B-208', 0, '2024-03-19 14:32:40', '2024-03-19 14:32:40');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(37, 'Nynas Nytro Taurus', 1, '2024-04-23 13:32:37', '2026-03-24 12:50:38');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(38, 'Caltran N60-30', 0, '2024-04-23 13:38:29', '2024-04-23 13:38:29');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(39, 'Midel 7131', 0, '2024-04-30 14:03:08', '2024-04-30 14:03:08');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(40, 'Univolt', 0, '2024-04-30 14:13:06', '2024-04-30 14:13:06');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(41, 'Lubtroil', 0, '2024-05-02 18:27:08', '2024-05-02 18:27:08');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(42, 'SHELL S4 ZX-I', 0, '2024-05-28 16:39:37', '2024-05-28 16:39:37');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(43, 'Shell Diala A', 0, '2024-06-21 15:01:35', '2024-06-21 15:01:35');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(44, 'Maker Electra 3', 0, '2024-07-02 20:53:00', '2024-07-02 20:54:19');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(45, 'Dow Corning® 561', 0, '2024-09-23 09:44:35', '2024-09-23 09:44:35');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(46, 'Nynas Nitro Izar I', 1, '2025-08-14 14:53:33', '2026-03-24 12:50:01');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(47, 'ESTER 7131', 0, '2025-08-14 15:25:44', '2025-08-14 15:25:44');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(48, 'Shell Diala', 0, '2025-09-01 14:33:08', '2025-09-01 14:33:08');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(49, 'NYNAS 10GBNP', 0, '2025-09-23 12:58:18', '2025-09-23 12:58:18');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(50, 'SINOPEC', 0, '2026-03-11 20:12:39', '2026-03-11 20:12:39');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(51, 'CALTRAN N60-08', 0, '2026-06-01 19:40:43', '2026-06-01 19:40:43');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(52, 'MIDEL', 0, '2026-07-17 16:57:11', '2026-07-17 16:57:11');
INSERT INTO `transformer_oil_marks` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(53, 'REPSOL', 0, '2026-07-17 16:57:35', '2026-07-17 16:57:35');
INSERT INTO `transformer_oil_units` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'Kg', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_oil_units` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 'Lb', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_oil_units` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 'L', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_oil_units` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(4, 'Gl', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_oil_units` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(5, 'Cil', 0, '2023-01-25 11:51:37', '2023-09-04 02:43:59');
INSERT INTO `transformer_oil_units` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(6, '-', 0, '2023-01-25 11:51:37', '2023-09-04 02:43:59');
INSERT INTO `transformer_points` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(1, '-', 0, '2023-06-20 12:37:35', '2023-06-20 12:38:01');
INSERT INTO `transformer_points` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 'Inferior', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_points` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 'Medio', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_points` (`id`, `name`, `deleted`, `created_at`, `updated_at`) VALUES
(4, 'Superior', 0, '2023-01-25 11:51:37', '2023-09-04 02:44:22');
INSERT INTO `transformer_preservations` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'Bolsa Membrana', 'TRAPP', 0, '2023-01-25 11:51:37', '2023-06-20 12:13:46');
INSERT INTO `transformer_preservations` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 'Gas Space', 'TRAPP', 0, '2023-01-25 11:51:37', '2023-06-20 12:13:46');
INSERT INTO `transformer_preservations` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 'Respiración Libre', 'TRAPP', 0, '2023-01-25 11:51:37', '2023-09-04 02:43:37');
INSERT INTO `transformer_preservations` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(4, '-', NULL, 0, '2023-01-25 11:51:37', '2023-09-03 00:07:08');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'Transformador de Potencia', 'TRAPP', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(2, 'Transformador de Distribución', 'TRAPP', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(3, 'Transformador de Horno', 'TRAPP', 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(4, 'Transformador de Corriente', NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(5, 'Transformador de Voltaje', NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(6, 'Instrumento', NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(7, 'Bushing', NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(8, 'Cables\r\n', NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(9, 'Interruptor', NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(10, 'Conmutador', NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(11, 'Reactor', NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(12, 'Máquina de Termovacío', NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(13, 'Transformador', NULL, 1, '2023-01-25 11:51:37', '2025-09-09 00:28:31');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(14, 'Transformador de Rectificador', NULL, 0, '2023-01-25 11:51:37', '2023-01-25 11:51:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(15, 'Trafomix', NULL, 0, '2023-01-25 11:51:37', '2023-09-04 02:03:27');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(16, '-', NULL, 0, '2023-09-04 15:07:16', '2023-09-04 15:07:16');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(17, 'Autotransformador', NULL, 0, '2023-09-05 13:29:09', '2023-09-05 13:34:21');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(18, 'Electrobomba', NULL, 0, '2023-09-07 20:34:37', '2023-09-07 20:34:37');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(19, 'Magneto', NULL, 0, '2023-10-09 15:42:34', '2023-10-09 15:42:34');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(20, 'Intercambiador', NULL, 0, '2023-11-17 16:00:33', '2023-11-17 16:00:33');
INSERT INTO `transformer_types` (`id`, `name`, `comment`, `deleted`, `created_at`, `updated_at`) VALUES
(21, 'Regulador de Voltaje', NULL, 0, '2024-08-09 16:44:40', '2024-08-09 16:44:40');
