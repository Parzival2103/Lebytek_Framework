-- ============================================================
-- SCHEMA — Framework base (solo plataforma)
-- Motor: MySQL 8.0+ / MariaDB 10.6+
-- Charset: utf8mb4 — Collation: utf8mb4_unicode_ci
--
-- Prefijos:
--   auth_ cfg_ log_ core_ int_ rep_ tmp_ sys_ = plataforma
-- Tablas dom_* = nuevos módulos de negocio (añadir al crear dominios; ver docs/uso-de-modulo-dominio.md)
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- NÚCLEO: Autenticación y RBAC
-- ============================================================

CREATE TABLE IF NOT EXISTS `auth_usuarios` (
  `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(100)     NOT NULL,
  `apellido`      VARCHAR(100)     NOT NULL,
  `email`         VARCHAR(191)     NOT NULL UNIQUE,
  `password`      VARCHAR(255)     NOT NULL,
  `avatar`        VARCHAR(500)     DEFAULT NULL,
  `activo`        TINYINT(1)       NOT NULL DEFAULT 1,
  `ultimo_acceso` DATETIME         DEFAULT NULL,
  `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME         DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_usuarios_email`  (`email`),
  INDEX `idx_usuarios_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `auth_roles` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(100)   NOT NULL,
  `slug`        VARCHAR(60)    NOT NULL UNIQUE,
  `descripcion` VARCHAR(500)   DEFAULT '',
  `activo`      TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `auth_permisos` (
  `id`                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `nombre`            VARCHAR(150)   NOT NULL,
  `slug`              VARCHAR(100)   NOT NULL UNIQUE,
  `modulo`            VARCHAR(60)    NOT NULL DEFAULT '',
  `descripcion`       VARCHAR(500)   DEFAULT '',
  `activo`            TINYINT(1)     NOT NULL DEFAULT 1,
  `deprecated_at`     DATETIME       DEFAULT NULL,
  `deprecated_reason` VARCHAR(255)   DEFAULT NULL,
  `created_at`        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_permisos_modulo` (`modulo`),
  INDEX `idx_auth_permisos_activo_modulo` (`activo`, `modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `auth_roles_permisos` (
  `rol_id`     INT UNSIGNED NOT NULL,
  `permiso_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`rol_id`, `permiso_id`),
  CONSTRAINT `fk_roles_permisos_rol`
    FOREIGN KEY (`rol_id`)     REFERENCES `auth_roles`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_roles_permisos_permiso`
    FOREIGN KEY (`permiso_id`) REFERENCES `auth_permisos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `auth_usuarios_roles` (
  `usuario_id` INT UNSIGNED NOT NULL,
  `rol_id`     INT UNSIGNED NOT NULL,
  PRIMARY KEY (`usuario_id`, `rol_id`),
  CONSTRAINT `fk_usuarios_roles_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `auth_usuarios`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usuarios_roles_rol`
    FOREIGN KEY (`rol_id`)     REFERENCES `auth_roles`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NÚCLEO: Configuración del sistema
-- ============================================================

CREATE TABLE IF NOT EXISTS `cfg_configuraciones` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `clave`       VARCHAR(100)   NOT NULL UNIQUE,
  `valor`       TEXT           DEFAULT '',
  `tipo`        ENUM('string','boolean','integer','json') DEFAULT 'string',
  `descripcion` VARCHAR(500)   DEFAULT '',
  `updated_at`  DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_configuraciones_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NÚCLEO: Bitácora de auditoría
-- ============================================================

CREATE TABLE IF NOT EXISTS `log_bitacora` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT UNSIGNED    DEFAULT NULL,
  `accion`      VARCHAR(50)     NOT NULL,
  `tabla`       VARCHAR(80)     DEFAULT '',
  `registro_id` INT UNSIGNED    DEFAULT NULL,
  `detalle`     TEXT            DEFAULT NULL,
  `ip`          VARCHAR(45)     DEFAULT '',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_bitacora_usuario`    (`usuario_id`),
  INDEX `idx_bitacora_created_at` (`created_at`),
  CONSTRAINT `fk_bitacora_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `auth_usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Catálogos auxiliares
-- ============================================================

CREATE TABLE IF NOT EXISTS `cfg_catalogos_auxiliares` (
  `id`        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tipo`      VARCHAR(60)   NOT NULL,
  `valor`     VARCHAR(200)  NOT NULL,
  `etiqueta`  VARCHAR(200)  NOT NULL,
  `orden`     SMALLINT      NOT NULL DEFAULT 0,
  `activo`    TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  INDEX `idx_catalogos_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Plataforma: tablas extensibles (inicialmente vacías)
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_modules` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` VARCHAR(64)  NOT NULL,
  `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_core_modules_key` (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Núcleo: ítems de menú admin (jerárquicos; RBAC por permiso_slug ↔ sesión).
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `core_menu_items` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`       INT UNSIGNED DEFAULT NULL,
  `orden`           SMALLINT     NOT NULL DEFAULT 0,
  `slug`            VARCHAR(80)  NOT NULL,
  `label`           VARCHAR(150) NOT NULL,
  `icon`            VARCHAR(80)  DEFAULT NULL,
  `url`             VARCHAR(500) DEFAULT NULL COMMENT 'Vacío si solo tiene submenús',
  `match`           VARCHAR(500) DEFAULT NULL COMMENT 'Prefijo URI para activar padre/sub',
  `permiso_slug`    VARCHAR(100) DEFAULT NULL COMMENT 'Coincide con auth_permisos.slug; visible si vacío sin capa extra si la política lo permite',
  `vertical_module` VARCHAR(64)  DEFAULT NULL COMMENT 'Igual que id de menú legacy / filtro VerticalProfile',
  `activo`          TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_core_menu_items_slug` (`slug`),
  KEY `idx_core_menu_parent_orden` (`parent_id`, `orden`, `activo`),
  CONSTRAINT `fk_core_menu_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `core_menu_items`(`id`) ON DELETE CASCADE
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

-- ─────────────────────────────────────────────────────────────────────────
-- Versionado de instalación (instalador / estado del sistema)
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `cfg_migraciones` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modulo`      VARCHAR(64)  NOT NULL,
  `archivo`     VARCHAR(255) NOT NULL,
  `checksum`    CHAR(64)     NOT NULL,
  `aplicada_en` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cfg_migraciones_archivo` (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cfg_modulos` (
  `clave`          VARCHAR(64) NOT NULL,
  `version`        VARCHAR(20) NOT NULL,
  `activo`         TINYINT(1)  NOT NULL DEFAULT 1,
  `instalado_en`   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
