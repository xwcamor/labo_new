-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 28-07-2026 a las 04:18:50
-- Versión del servidor: 8.0.42-0ubuntu0.24.10.1
-- Versión de PHP: 8.4.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `lab_app_development`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `accesses`
--

CREATE TABLE `accesses` (
  `id` bigint NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ar_internal_metadata`
--

CREATE TABLE `ar_internal_metadata` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audits`
--

CREATE TABLE `audits` (
  `id` bigint NOT NULL,
  `auditable_id` int DEFAULT NULL,
  `auditable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `associated_id` int DEFAULT NULL,
  `associated_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `audited_changes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `version` int DEFAULT '0',
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `remote_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `request_uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `created_at` datetime(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cro_temperatures`
--

CREATE TABLE `cro_temperatures` (
  `id` int NOT NULL,
  `date_temperature` date DEFAULT NULL,
  `cro_lab_pre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro_lab_tem` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro_lab_hum` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `db_systems`
--

CREATE TABLE `db_systems` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fiq_temperatures`
--

CREATE TABLE `fiq_temperatures` (
  `id` int NOT NULL,
  `date_temperature` date DEFAULT NULL,
  `fiq_lab_pre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fiq_lab_tem` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fiq_lab_hum` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `import_transformers`
--

CREATE TABLE `import_transformers` (
  `id` int NOT NULL,
  `file_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `num_serie` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `num_tag` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `num_ten` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `num_pot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `transformer_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `conmutation_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `transformer_preservation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `oil_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `mark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `transformer_oil_mark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `transformer_oil_unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `oil_qty` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `age` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `was_upload` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `labs`
--

CREATE TABLE `labs` (
  `id` bigint NOT NULL,
  `lab_category_detail_id` bigint NOT NULL,
  `date_rehearsal` date NOT NULL,
  `user_id` bigint NOT NULL,
  `validate_user_id` bigint DEFAULT NULL,
  `state` int DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lab_category_details`
--

CREATE TABLE `lab_category_details` (
  `id` bigint NOT NULL,
  `lab_category_detail_type_id` bigint DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `container` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `num_pos` int DEFAULT NULL,
  `is_grouped` int DEFAULT NULL,
  `blur_calculation` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `unit_name_amchart` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `has_reuse` int DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lab_category_detail_types`
--

CREATE TABLE `lab_category_detail_types` (
  `id` bigint NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `icon_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lab_category_sub_details`
--

CREATE TABLE `lab_category_sub_details` (
  `id` bigint NOT NULL,
  `lab_category_detail_id` bigint DEFAULT NULL,
  `lab_category_sub_detail_type_id` bigint DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `num_pos` int DEFAULT NULL,
  `is_required` int DEFAULT NULL,
  `is_blocked` int DEFAULT NULL,
  `is_blur` int DEFAULT NULL,
  `is_reuse` int DEFAULT NULL,
  `reuse_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `is_imported` int DEFAULT NULL,
  `imported_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `imported_remove_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `report_use` int DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lab_category_sub_detail_options`
--

CREATE TABLE `lab_category_sub_detail_options` (
  `id` bigint NOT NULL,
  `lab_category_sub_detail_id` bigint DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `updated_at` datetime(6) NOT NULL,
  `created_at` datetime(6) NOT NULL,
  `applicability_flag` varchar(5) COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'A, NA, etc.',
  `num_pos` int DEFAULT NULL,
  `is_hidden` tinyint(1) DEFAULT '0',
  `deleted` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lab_category_sub_detail_types`
--

CREATE TABLE `lab_category_sub_detail_types` (
  `id` bigint NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lab_details`
--

CREATE TABLE `lab_details` (
  `id` bigint NOT NULL,
  `lab_id` bigint NOT NULL,
  `lab_detail_type_id` bigint NOT NULL,
  `num_test` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `user_id` bigint NOT NULL,
  `lab_file_id` bigint DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lab_detail_types`
--

CREATE TABLE `lab_detail_types` (
  `id` bigint NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lab_files`
--

CREATE TABLE `lab_files` (
  `id` bigint NOT NULL,
  `test_num` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `lab_category_detail_id` bigint DEFAULT NULL,
  `lab_id` bigint DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lab_file_details`
--

CREATE TABLE `lab_file_details` (
  `id` bigint NOT NULL,
  `lab_file_id` bigint DEFAULT NULL,
  `lab_category_sub_detail_id` bigint DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lab_sub_details`
--

CREATE TABLE `lab_sub_details` (
  `id` bigint NOT NULL,
  `lab_detail_id` bigint NOT NULL,
  `lab_category_sub_detail_id` bigint NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `norms`
--

CREATE TABLE `norms` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `patron_tendences`
--

CREATE TABLE `patron_tendences` (
  `id` int NOT NULL,
  `lab_category_detail_id` int DEFAULT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `lci` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `lai` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `lc` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `las` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `lcs` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `oxi_lci` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `oxi_lai` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `oxi_lc` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `oxi_las` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `oxi_lcs` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `nit_lci` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `nit_lai` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `nit_lc` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `nit_las` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `nit_lcs` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `met_lci` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `met_lai` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `met_lc` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `met_las` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `met_lcs` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `mon_lci` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `mon_lai` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `mon_lc` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `mon_las` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `mon_lcs` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `dio_lci` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `dio_lai` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `dio_lc` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `dio_las` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `dio_lcs` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `eti_lci` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `eti_lai` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `eti_lc` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `eti_las` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `eti_lcs` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `eta_lci` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `eta_lai` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `eta_lc` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `eta_las` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `eta_lcs` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `ace_lci` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `ace_lai` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `ace_lc` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `ace_las` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `ace_lcs` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profiles`
--

CREATE TABLE `profiles` (
  `id` bigint NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profile_accesses`
--

CREATE TABLE `profile_accesses` (
  `id` bigint NOT NULL,
  `access_id` bigint DEFAULT NULL,
  `profile_id` bigint DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rems`
--

CREATE TABLE `rems` (
  `id` bigint NOT NULL,
  `date_received` datetime DEFAULT NULL,
  `date_deliver` date DEFAULT NULL,
  `sampler_id` bigint DEFAULT NULL,
  `num_os` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `customer_id` bigint DEFAULT NULL,
  `ea_val` int DEFAULT NULL,
  `va_val` int DEFAULT NULL,
  `dc_val` int DEFAULT NULL,
  `observation` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `rem_user_signature_id` bigint DEFAULT NULL,
  `num_fiq` int DEFAULT NULL,
  `num_cro` int DEFAULT NULL,
  `num_pcb` int DEFAULT NULL,
  `num_fur` int DEFAULT NULL,
  `num_par` int DEFAULT NULL,
  `num_azu` int DEFAULT NULL,
  `num_sed` int DEFAULT NULL,
  `num_met` int DEFAULT NULL,
  `num_vis` int DEFAULT NULL,
  `num_dbd` int DEFAULT NULL,
  `num_inf` int DEFAULT NULL,
  `num_flu` int DEFAULT NULL,
  `num_inh` int DEFAULT NULL,
  `num_pol` int DEFAULT NULL,
  `num_pas` int DEFAULT NULL,
  `qty_num_pack` int DEFAULT NULL,
  `qty_num_test` int DEFAULT NULL,
  `state` int DEFAULT NULL,
  `validity` int DEFAULT NULL,
  `correlative_confirmed` int DEFAULT NULL,
  `is_urgent` int DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `delete_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `delete_user_id` int DEFAULT NULL,
  `series_done` int DEFAULT NULL,
  `jobs_done` int DEFAULT NULL,
  `datas_done` int DEFAULT NULL,
  `reports_done` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rem_conditions`
--

CREATE TABLE `rem_conditions` (
  `id` int NOT NULL,
  `transformer_oil_type` int DEFAULT NULL,
  `lab_category_details` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cond_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rem_correlatives`
--

CREATE TABLE `rem_correlatives` (
  `id` int NOT NULL,
  `rem_id` int DEFAULT NULL,
  `num_test` int DEFAULT NULL,
  `year_test` int DEFAULT NULL,
  `transformer_id` int DEFAULT NULL,
  `pending_tr` int DEFAULT NULL,
  `pending_tk` int DEFAULT NULL,
  `pending_va` int DEFAULT NULL,
  `qr_code` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `is_urgent` int DEFAULT NULL,
  `date_urgent` date DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `comment_deleted` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rem_jobs`
--

CREATE TABLE `rem_jobs` (
  `id` int NOT NULL,
  `rem_correlative_id` int DEFAULT NULL,
  `lab_category_detail_id` int DEFAULT NULL,
  `lab_detail_id` int DEFAULT NULL,
  `task_done` int DEFAULT NULL,
  `state` tinyint(1) DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rem_reports`
--

CREATE TABLE `rem_reports` (
  `id` bigint NOT NULL,
  `type_report` int DEFAULT NULL,
  `num_report` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `num_report_small` int DEFAULT NULL,
  `num_report_year` int DEFAULT NULL,
  `rem_correlative_id` bigint DEFAULT NULL,
  `transformer_id` int DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `contact_info` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `date_rec` date DEFAULT NULL,
  `end_user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `date_emi` date DEFAULT NULL,
  `sampler_id` int DEFAULT NULL,
  `date_ent` date DEFAULT NULL,
  `date_pue` date DEFAULT NULL,
  `rem_report_reason_id` int DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `mark_id` int DEFAULT NULL,
  `num_ten` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `num_pot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `transformer_type_id` int DEFAULT NULL,
  `oil_type_id` int DEFAULT NULL,
  `transformer_oil_mark_id` int DEFAULT NULL,
  `age` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `conmutation_type_id` int DEFAULT NULL,
  `transformer_preservation_id` int DEFAULT NULL,
  `oil_qty` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `transformer_oil_unit_id` int DEFAULT NULL,
  `transformer_point_id` int DEFAULT NULL,
  `oil_temp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `amb_temp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `hum_rel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `num_tag` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `rem_signature_id` bigint DEFAULT NULL,
  `tra_temp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `date_mue` date DEFAULT NULL,
  `operation` int DEFAULT NULL,
  `was_updated` int DEFAULT NULL,
  `state` int DEFAULT NULL,
  `customer_evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rem_report_details`
--

CREATE TABLE `rem_report_details` (
  `id` bigint NOT NULL,
  `rem_report_id` bigint DEFAULT NULL,
  `aci_display` int DEFAULT NULL,
  `f25_display` int DEFAULT NULL,
  `f90_display` int DEFAULT NULL,
  `f100_display` int DEFAULT NULL,
  `rig_display` int DEFAULT NULL,
  `rigep_display` int DEFAULT NULL,
  `ten_display` int DEFAULT NULL,
  `agu_display` int DEFAULT NULL,
  `col_display` int DEFAULT NULL,
  `con_display` int DEFAULT NULL,
  `den_display` int DEFAULT NULL,
  `r25_display` int DEFAULT NULL,
  `r100_display` int DEFAULT NULL,
  `cro_display` int DEFAULT NULL,
  `pcb_display` int DEFAULT NULL,
  `fur_display` int DEFAULT NULL,
  `azu_display` int DEFAULT NULL,
  `azu48_display` int DEFAULT NULL,
  `azu72_display` int DEFAULT NULL,
  `pol_display` int DEFAULT NULL,
  `vis_display` int DEFAULT NULL,
  `par_display` int DEFAULT NULL,
  `met_display` int DEFAULT NULL,
  `inh_display` int DEFAULT NULL,
  `dbd_display` int DEFAULT NULL,
  `sed_display` int DEFAULT NULL,
  `flu_display` int DEFAULT NULL,
  `inf_display` int DEFAULT NULL,
  `pas_display` int DEFAULT NULL,
  `aci_lab_detail_id` bigint DEFAULT NULL,
  `f25_lab_detail_id` bigint DEFAULT NULL,
  `f90_lab_detail_id` bigint DEFAULT NULL,
  `f100_lab_detail_id` bigint DEFAULT NULL,
  `rig_lab_detail_id` bigint DEFAULT NULL,
  `rigep_lab_detail_id` bigint DEFAULT NULL,
  `ten_lab_detail_id` bigint DEFAULT NULL,
  `agu_lab_detail_id` bigint DEFAULT NULL,
  `col_lab_detail_id` bigint DEFAULT NULL,
  `con_lab_detail_id` bigint DEFAULT NULL,
  `den_lab_detail_id` bigint DEFAULT NULL,
  `r25_lab_detail_id` bigint DEFAULT NULL,
  `r100_lab_detail_id` bigint DEFAULT NULL,
  `cro_lab_detail_id` bigint DEFAULT NULL,
  `pcb_lab_detail_id` bigint DEFAULT NULL,
  `fur_lab_detail_id` bigint DEFAULT NULL,
  `azu_lab_detail_id` bigint DEFAULT NULL,
  `azu48_lab_detail_id` bigint DEFAULT NULL,
  `azu72_lab_detail_id` bigint DEFAULT NULL,
  `pol_lab_detail_id` bigint DEFAULT NULL,
  `vis_lab_detail_id` bigint DEFAULT NULL,
  `par_lab_detail_id` bigint DEFAULT NULL,
  `met_lab_detail_id` bigint DEFAULT NULL,
  `inh_lab_detail_id` bigint DEFAULT NULL,
  `dbd_lab_detail_id` bigint DEFAULT NULL,
  `sed_lab_detail_id` bigint DEFAULT NULL,
  `flu_lab_detail_id` bigint DEFAULT NULL,
  `inf_lab_detail_id` bigint DEFAULT NULL,
  `pas_lab_detail_id` bigint DEFAULT NULL,
  `aci_val` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `f25_val` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `f90_val` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `f100_val` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `rig_val` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `rigep_val` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `ten_val` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `agu_val` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `col_val` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `con_val` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `den_val` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `r25_val` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `r100_val` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro_val` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro2_val` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro3_val` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro4_val` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro5_val` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro6_val` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro7_val` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro8_val` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro9_val` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro10_val` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro11_val` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `pcb_val` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `pcb2_val` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `pcb3_val` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `pcb4_val` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fur_val` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fur2_val` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fur3_val` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fur4_val` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fur5_val` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fur6_val` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `azu_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `azu2_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `azu48_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `azu482_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `azu72_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `pol_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `vis_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `par_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `par2_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `par3_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `par4_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `par5_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `par6_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `par7_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `par8_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `met_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `met2_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `met3_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `met4_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `met5_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `met6_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `met7_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `met8_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `inh_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `dbd_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `sed_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `sed2_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `sed3_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `sed4_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `flu_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `inf_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `pas_val` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `aci_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `f25_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `f90_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `f100_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `rig_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `rigep_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `ten_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `agu_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `col_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `con_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `den_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `r25_ori` char(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `r100_ori` char(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro2_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro3_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro4_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro5_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro6_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro7_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro8_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro9_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `pcb_ori` char(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fur_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `azu_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `azu48_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `azu72_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `pol_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `vis_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `par_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `met_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `inh_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `dbd_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `sed_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `flu_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `inf_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `pas_ori` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fiq_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `cro_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `pcb_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `fur_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `azu_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `pol_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `vis_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `par_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `met_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `inh_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `dbd_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `sed_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `flu_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `inf_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `pas_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `fiq_date` date DEFAULT NULL,
  `cro_date` date DEFAULT NULL,
  `pcb_date` date DEFAULT NULL,
  `fur_date` date DEFAULT NULL,
  `par_date` date DEFAULT NULL,
  `azu_date` date DEFAULT NULL,
  `sed_date` date DEFAULT NULL,
  `met_date` date DEFAULT NULL,
  `vis_date` date DEFAULT NULL,
  `dbd_date` date DEFAULT NULL,
  `inf_date` date DEFAULT NULL,
  `flu_date` date DEFAULT NULL,
  `inh_date` date DEFAULT NULL,
  `pol_date` date DEFAULT NULL,
  `pas_date` date DEFAULT NULL,
  `fiq_norm_id` int DEFAULT NULL,
  `fiq_lab_pre` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fiq_lab_tem` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fiq_lab_hum` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro_norm_id` int DEFAULT NULL,
  `cro_lab_pre` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro_lab_tem` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cro_lab_hum` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `pcb_norm_id` int DEFAULT NULL,
  `dbd_norm_id` int DEFAULT NULL,
  `inh_norm_id` int DEFAULT NULL,
  `pol_norm_id` int DEFAULT NULL,
  `fiq_item1` tinyint DEFAULT NULL,
  `fiq_item2` tinyint DEFAULT NULL,
  `fiq_item3` tinyint DEFAULT NULL,
  `fiq_item4` tinyint DEFAULT NULL,
  `fiq_item5` tinyint DEFAULT NULL,
  `fiq_item6` tinyint DEFAULT NULL,
  `fiq_item7` tinyint DEFAULT NULL,
  `fiq_item8` tinyint DEFAULT NULL,
  `fiq_item9` tinyint DEFAULT NULL,
  `fiq_item10` tinyint DEFAULT NULL,
  `fiq_item11` tinyint DEFAULT NULL,
  `fiq_item12` tinyint DEFAULT NULL,
  `fiq_item13` tinyint DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rem_report_detail_issues`
--

CREATE TABLE `rem_report_detail_issues` (
  `id` int NOT NULL,
  `rem_report_detail_id` int DEFAULT NULL,
  `web_url_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rem_report_reasons`
--

CREATE TABLE `rem_report_reasons` (
  `id` bigint NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rem_signatures`
--

CREATE TABLE `rem_signatures` (
  `id` bigint NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rem_user_signatures`
--

CREATE TABLE `rem_user_signatures` (
  `id` bigint NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `samplers`
--

CREATE TABLE `samplers` (
  `id` bigint NOT NULL,
  `country_id` bigint DEFAULT NULL,
  `num_doc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `schema_migrations`
--

CREATE TABLE `schema_migrations` (
  `version` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stickers`
--

CREATE TABLE `stickers` (
  `id` int NOT NULL,
  `num_test` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `date_test` date DEFAULT NULL,
  `name_test` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `responsable_test` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stocks`
--

CREATE TABLE `stocks` (
  `id` int NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `qty` int DEFAULT NULL,
  `stock_unit_id` int DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stock_details`
--

CREATE TABLE `stock_details` (
  `id` int NOT NULL,
  `date_loan` date DEFAULT NULL,
  `is_loan` tinyint(1) DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stock_detail_moves`
--

CREATE TABLE `stock_detail_moves` (
  `id` int NOT NULL,
  `stock_detail_id` int DEFAULT NULL,
  `stock_id` int DEFAULT NULL,
  `qty` int DEFAULT NULL,
  `comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `qty_return` int DEFAULT NULL,
  `date_return` date DEFAULT NULL,
  `comment_return` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `qty_pending` int DEFAULT NULL,
  `date_pending` date DEFAULT NULL,
  `comment_pending` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stock_detail_returns`
--

CREATE TABLE `stock_detail_returns` (
  `id` int NOT NULL,
  `stock_detail_move_id` int DEFAULT NULL,
  `date_return` date DEFAULT NULL,
  `qty_return` int DEFAULT NULL,
  `comment_return` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stock_units`
--

CREATE TABLE `stock_units` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transformers`
--

CREATE TABLE `transformers` (
  `id` int NOT NULL,
  `num_serie` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `num_ten` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `num_pot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `transformer_type_id` int DEFAULT NULL,
  `conmutation_type_id` int DEFAULT NULL,
  `transformer_preservation_id` int DEFAULT NULL,
  `oil_type_id` int DEFAULT NULL,
  `mark_id` int DEFAULT NULL,
  `transformer_oil_mark_id` int DEFAULT NULL,
  `transformer_oil_unit_id` int DEFAULT NULL,
  `transformer_point_id` int DEFAULT NULL,
  `num_tag` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `oil_qty` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `age` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transformer_oil_marks`
--

CREATE TABLE `transformer_oil_marks` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transformer_oil_units`
--

CREATE TABLE `transformer_oil_units` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transformer_points`
--

CREATE TABLE `transformer_points` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transformer_preservations`
--

CREATE TABLE `transformer_preservations` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transformer_types`
--

CREATE TABLE `transformer_types` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` bigint NOT NULL,
  `num_doc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `lastname1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `lastname2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `cellphone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `real_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `hashed_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `salt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `profile_id` bigint NOT NULL,
  `country_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `password_reset_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `password_reset_token_date` datetime(6) DEFAULT NULL,
  `password_reset_change_date` datetime(6) DEFAULT NULL,
  `password_expires_after` datetime(6) DEFAULT NULL,
  `authentication_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `last_signed_in_on` datetime(6) DEFAULT NULL,
  `signed_up_on` datetime(6) DEFAULT NULL,
  `state` int DEFAULT NULL,
  `deleted` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `accesses`
--
ALTER TABLE `accesses`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `ar_internal_metadata`
--
ALTER TABLE `ar_internal_metadata`
  ADD PRIMARY KEY (`key`);

--
-- Indices de la tabla `audits`
--
ALTER TABLE `audits`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cro_temperatures`
--
ALTER TABLE `cro_temperatures`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `db_systems`
--
ALTER TABLE `db_systems`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `fiq_temperatures`
--
ALTER TABLE `fiq_temperatures`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `import_transformers`
--
ALTER TABLE `import_transformers`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `labs`
--
ALTER TABLE `labs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_labs_lab_category_detail_id` (`lab_category_detail_id`),
  ADD KEY `fk_labs_user_id` (`user_id`);

--
-- Indices de la tabla `lab_category_details`
--
ALTER TABLE `lab_category_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lab_category_detail_type_id` (`lab_category_detail_type_id`);

--
-- Indices de la tabla `lab_category_detail_types`
--
ALTER TABLE `lab_category_detail_types`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `lab_category_sub_details`
--
ALTER TABLE `lab_category_sub_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sub_details_category_detail` (`lab_category_detail_id`),
  ADD KEY `fk_sub_details_type` (`lab_category_sub_detail_type_id`);

--
-- Indices de la tabla `lab_category_sub_detail_options`
--
ALTER TABLE `lab_category_sub_detail_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_options_sub_detail` (`lab_category_sub_detail_id`);

--
-- Indices de la tabla `lab_category_sub_detail_types`
--
ALTER TABLE `lab_category_sub_detail_types`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `lab_details`
--
ALTER TABLE `lab_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lab_details_user_id` (`user_id`),
  ADD KEY `fk_lab_details_lab_id` (`lab_id`),
  ADD KEY `fk_lab_details_lab_detail_type_id` (`lab_detail_type_id`);

--
-- Indices de la tabla `lab_detail_types`
--
ALTER TABLE `lab_detail_types`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `lab_files`
--
ALTER TABLE `lab_files`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `lab_file_details`
--
ALTER TABLE `lab_file_details`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `lab_sub_details`
--
ALTER TABLE `lab_sub_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lab_sub_details_lab_detail_id` (`lab_detail_id`),
  ADD KEY `fk_lab_sub_details_lab_category_sub_detail_id` (`lab_category_sub_detail_id`);

--
-- Indices de la tabla `norms`
--
ALTER TABLE `norms`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `patron_tendences`
--
ALTER TABLE `patron_tendences`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `profile_accesses`
--
ALTER TABLE `profile_accesses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_profile_accesses_access_id` (`access_id`),
  ADD KEY `fk_profile_accesses_profile_id` (`profile_id`);

--
-- Indices de la tabla `rems`
--
ALTER TABLE `rems`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rem_conditions`
--
ALTER TABLE `rem_conditions`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rem_correlatives`
--
ALTER TABLE `rem_correlatives`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rem_jobs`
--
ALTER TABLE `rem_jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rem_reports`
--
ALTER TABLE `rem_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rem_report_details`
--
ALTER TABLE `rem_report_details`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rem_report_detail_issues`
--
ALTER TABLE `rem_report_detail_issues`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rem_report_reasons`
--
ALTER TABLE `rem_report_reasons`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rem_signatures`
--
ALTER TABLE `rem_signatures`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rem_user_signatures`
--
ALTER TABLE `rem_user_signatures`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `samplers`
--
ALTER TABLE `samplers`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `schema_migrations`
--
ALTER TABLE `schema_migrations`
  ADD PRIMARY KEY (`version`);

--
-- Indices de la tabla `stickers`
--
ALTER TABLE `stickers`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `stocks`
--
ALTER TABLE `stocks`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `stock_details`
--
ALTER TABLE `stock_details`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `stock_detail_moves`
--
ALTER TABLE `stock_detail_moves`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `stock_detail_returns`
--
ALTER TABLE `stock_detail_returns`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `stock_units`
--
ALTER TABLE `stock_units`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `transformers`
--
ALTER TABLE `transformers`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `transformer_oil_marks`
--
ALTER TABLE `transformer_oil_marks`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `transformer_oil_units`
--
ALTER TABLE `transformer_oil_units`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `transformer_points`
--
ALTER TABLE `transformer_points`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `transformer_preservations`
--
ALTER TABLE `transformer_preservations`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `transformer_types`
--
ALTER TABLE `transformer_types`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_users_profile_id` (`profile_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `accesses`
--
ALTER TABLE `accesses`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audits`
--
ALTER TABLE `audits`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cro_temperatures`
--
ALTER TABLE `cro_temperatures`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `db_systems`
--
ALTER TABLE `db_systems`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fiq_temperatures`
--
ALTER TABLE `fiq_temperatures`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `import_transformers`
--
ALTER TABLE `import_transformers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `labs`
--
ALTER TABLE `labs`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lab_category_details`
--
ALTER TABLE `lab_category_details`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lab_category_detail_types`
--
ALTER TABLE `lab_category_detail_types`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lab_category_sub_details`
--
ALTER TABLE `lab_category_sub_details`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lab_category_sub_detail_options`
--
ALTER TABLE `lab_category_sub_detail_options`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lab_category_sub_detail_types`
--
ALTER TABLE `lab_category_sub_detail_types`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lab_details`
--
ALTER TABLE `lab_details`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lab_detail_types`
--
ALTER TABLE `lab_detail_types`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lab_files`
--
ALTER TABLE `lab_files`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lab_file_details`
--
ALTER TABLE `lab_file_details`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lab_sub_details`
--
ALTER TABLE `lab_sub_details`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `norms`
--
ALTER TABLE `norms`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `patron_tendences`
--
ALTER TABLE `patron_tendences`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `profiles`
--
ALTER TABLE `profiles`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `profile_accesses`
--
ALTER TABLE `profile_accesses`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rems`
--
ALTER TABLE `rems`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rem_conditions`
--
ALTER TABLE `rem_conditions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rem_correlatives`
--
ALTER TABLE `rem_correlatives`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rem_jobs`
--
ALTER TABLE `rem_jobs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rem_reports`
--
ALTER TABLE `rem_reports`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rem_report_details`
--
ALTER TABLE `rem_report_details`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rem_report_detail_issues`
--
ALTER TABLE `rem_report_detail_issues`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rem_report_reasons`
--
ALTER TABLE `rem_report_reasons`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rem_signatures`
--
ALTER TABLE `rem_signatures`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rem_user_signatures`
--
ALTER TABLE `rem_user_signatures`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `samplers`
--
ALTER TABLE `samplers`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `stickers`
--
ALTER TABLE `stickers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `stocks`
--
ALTER TABLE `stocks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `stock_details`
--
ALTER TABLE `stock_details`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `stock_detail_moves`
--
ALTER TABLE `stock_detail_moves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `stock_detail_returns`
--
ALTER TABLE `stock_detail_returns`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `stock_units`
--
ALTER TABLE `stock_units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `transformers`
--
ALTER TABLE `transformers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `transformer_oil_marks`
--
ALTER TABLE `transformer_oil_marks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `transformer_oil_units`
--
ALTER TABLE `transformer_oil_units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `transformer_points`
--
ALTER TABLE `transformer_points`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `transformer_preservations`
--
ALTER TABLE `transformer_preservations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `transformer_types`
--
ALTER TABLE `transformer_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `labs`
--
ALTER TABLE `labs`
  ADD CONSTRAINT `fk_labs_lab_category_detail_id` FOREIGN KEY (`lab_category_detail_id`) REFERENCES `lab_category_details` (`id`),
  ADD CONSTRAINT `fk_labs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `lab_category_details`
--
ALTER TABLE `lab_category_details`
  ADD CONSTRAINT `fk_lab_category_detail_type_id` FOREIGN KEY (`lab_category_detail_type_id`) REFERENCES `lab_category_detail_types` (`id`);

--
-- Filtros para la tabla `lab_category_sub_details`
--
ALTER TABLE `lab_category_sub_details`
  ADD CONSTRAINT `fk_sub_details_category_detail` FOREIGN KEY (`lab_category_detail_id`) REFERENCES `lab_category_details` (`id`),
  ADD CONSTRAINT `fk_sub_details_type` FOREIGN KEY (`lab_category_sub_detail_type_id`) REFERENCES `lab_category_sub_detail_types` (`id`);

--
-- Filtros para la tabla `lab_category_sub_detail_options`
--
ALTER TABLE `lab_category_sub_detail_options`
  ADD CONSTRAINT `fk_options_sub_detail` FOREIGN KEY (`lab_category_sub_detail_id`) REFERENCES `lab_category_sub_details` (`id`);

--
-- Filtros para la tabla `lab_details`
--
ALTER TABLE `lab_details`
  ADD CONSTRAINT `fk_lab_details_lab_detail_type_id` FOREIGN KEY (`lab_detail_type_id`) REFERENCES `lab_detail_types` (`id`),
  ADD CONSTRAINT `fk_lab_details_lab_id` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`),
  ADD CONSTRAINT `fk_lab_details_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `lab_sub_details`
--
ALTER TABLE `lab_sub_details`
  ADD CONSTRAINT `fk_lab_sub_details_lab_category_sub_detail_id` FOREIGN KEY (`lab_category_sub_detail_id`) REFERENCES `lab_category_sub_details` (`id`),
  ADD CONSTRAINT `fk_lab_sub_details_lab_detail_id` FOREIGN KEY (`lab_detail_id`) REFERENCES `lab_details` (`id`);

--
-- Filtros para la tabla `profile_accesses`
--
ALTER TABLE `profile_accesses`
  ADD CONSTRAINT `fk_profile_accesses_access_id` FOREIGN KEY (`access_id`) REFERENCES `accesses` (`id`),
  ADD CONSTRAINT `fk_profile_accesses_profile_id` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`);

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_profile_id` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
