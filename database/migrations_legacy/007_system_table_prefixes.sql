-- ============================================================
-- Migración 007: Prefijos de tablas de PLATAFORMA
-- ============================================================
-- Aplicar sobre una BD creada con el schema anterior (sin prefijos).
-- Después ejecutar 008_domain_table_prefix_dom.sql
--
-- Bitácora: schema histórico `bitacora`; código antiguo `bitacoras`.
-- Debe existir UNA tabla fuente. Si solo existe `bitacoras`:
--   RENAME TABLE `bitacoras` TO `log_bitacora`;
-- y comenta/omite la línea RENAME desde `bitacora` abajo.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

RENAME TABLE `usuarios` TO `auth_usuarios`;
RENAME TABLE `roles` TO `auth_roles`;
RENAME TABLE `permisos` TO `auth_permisos`;
RENAME TABLE `roles_permisos` TO `auth_roles_permisos`;
RENAME TABLE `usuarios_roles` TO `auth_usuarios_roles`;

RENAME TABLE `configuraciones` TO `cfg_configuraciones`;
RENAME TABLE `catalogos_auxiliares` TO `cfg_catalogos_auxiliares`;

RENAME TABLE `bitacora` TO `log_bitacora`;

CREATE TABLE IF NOT EXISTS `core_modules` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` VARCHAR(64)  NOT NULL,
  `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_core_modules_key` (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `int_webhooks` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`     VARCHAR(150) NOT NULL,
  `url`        VARCHAR(500) NOT NULL,
  `evento`     VARCHAR(80)  NOT NULL DEFAULT '',
  `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_int_webhooks_evento` (`evento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rep_metric_defs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(80)  NOT NULL,
  `descripcion` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rep_metric_defs_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tmp_jobs` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue`          VARCHAR(64)     NOT NULL DEFAULT 'default',
  `payload`        JSON            DEFAULT NULL,
  `intentos`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `disponible_en`  DATETIME        DEFAULT NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tmp_jobs_queue` (`queue`, `disponible_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sys_kv` (
  `clave`      VARCHAR(100) NOT NULL,
  `valor`      TEXT         DEFAULT NULL,
  `updated_at` DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
