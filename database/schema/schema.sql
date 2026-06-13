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
  `email_verificado_en` DATETIME   DEFAULT NULL,
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

-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED    NOT NULL,
  `tipo`       VARCHAR(30)     NOT NULL,
  `token_hash` CHAR(64)        NOT NULL,
  `expira_en`  DATETIME        NOT NULL,
  `usado_en`   DATETIME        DEFAULT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tokens_usuario_tipo` (`usuario_id`, `tipo`),
  INDEX `idx_tokens_hash` (`token_hash`),
  CONSTRAINT `fk_tokens_usuario`
      FOREIGN KEY (`usuario_id`) REFERENCES `auth_usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_login_intentos` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dimension`  VARCHAR(10)     NOT NULL,
  `clave`      VARCHAR(255)    NOT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_login_intentos_busqueda` (`dimension`, `clave`, `created_at`)
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

-- ------------------------------------------------------------
-- Núcleo: ledger central de archivos subidos (uploads de cualquier módulo).
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `core_archivos` (
  `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `entidad_tipo`   VARCHAR(80)      NOT NULL,
  `entidad_id`     INT UNSIGNED     DEFAULT NULL,
  `coleccion`      VARCHAR(60)      NOT NULL DEFAULT 'default',
  `ruta`           VARCHAR(500)     NOT NULL,
  `thumbnail_ruta` VARCHAR(500)     DEFAULT NULL,
  `nombre_original` VARCHAR(255)    DEFAULT NULL,
  `mime`           VARCHAR(120)     DEFAULT NULL,
  `extension`      VARCHAR(20)      DEFAULT NULL,
  `tamano_bytes`   BIGINT UNSIGNED  NOT NULL DEFAULT 0,
  `disco`          VARCHAR(20)      NOT NULL DEFAULT 'public',
  `es_actual`      TINYINT(1)       NOT NULL DEFAULT 0,
  `creado_por`     INT UNSIGNED     DEFAULT NULL,
  `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME         DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_archivos_entidad` (`entidad_tipo`, `entidad_id`, `coleccion`),
  INDEX `idx_archivos_actual`  (`entidad_tipo`, `entidad_id`, `coleccion`, `es_actual`),
  INDEX `idx_archivos_deleted` (`deleted_at`),
  CONSTRAINT `fk_archivos_creado_por`
      FOREIGN KEY (`creado_por`) REFERENCES `auth_usuarios`(`id`) ON DELETE SET NULL
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

-- ============================================================
-- DATOS INICIALES (idempotente — greenfield bootstrap)
-- Fusiona seeds 010–035 + permisos clientes (ex migración core).
-- ============================================================

INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`) VALUES
  ('Ver administración', 'administracion.ver', 'administracion'),
  ('Gestionar usuarios', 'usuarios.gestionar', 'administracion'),
  ('Gestionar roles', 'roles.gestionar', 'administracion'),
  ('Ver bitácora', 'bitacora.ver', 'administracion'),
  ('Ver dashboard', 'dashboard.ver', 'dashboard'),
  ('Ver estado del sistema', 'sistema.ver', 'sistema');

INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
  ('Ver clientes', 'clientes.ver', 'clientes', 'CRUD Engine: listar y ver clientes'),
  ('Crear clientes', 'clientes.crear', 'clientes', 'CRUD Engine: crear clientes'),
  ('Editar clientes', 'clientes.editar', 'clientes', 'CRUD Engine: editar clientes'),
  ('Eliminar clientes', 'clientes.eliminar', 'clientes', 'CRUD Engine: borrado lógico clientes');

INSERT IGNORE INTO `auth_roles` (`nombre`, `slug`, `descripcion`) VALUES
  ('Administrador', 'administrador', 'Acceso total al sistema'),
  ('Operador', 'operador', 'Acceso al dashboard (extender al añadir dominio)'),
  ('Soporte', 'soporte', 'Rol de ejemplo mínimo hasta definir módulos'),
  ('Usuario', 'usuario', 'Usuario registrado desde el formulario público');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
CROSS JOIN `auth_permisos` `p`
WHERE `r`.`slug` = 'administrador';

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` = 'dashboard.ver'
WHERE `r`.`slug` IN ('operador', 'soporte', 'usuario');

INSERT IGNORE INTO `core_menu_items`
  (`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
VALUES
  (NULL, 10, 'dashboard', 'Dashboard', 'bi-speedometer2', '/admin/dashboard', '/admin/dashboard', 'dashboard.ver', NULL, 1),
  (NULL, 20, 'administracion', 'Administración', 'bi-shield-lock', NULL, '/admin/administracion', 'administracion.ver', NULL, 1);

INSERT IGNORE INTO `core_menu_items`
  (`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT
  `p`.`id`,
  `r`.`orden`,
  `r`.`slug`,
  `r`.`label`,
  `r`.`icon`,
  `r`.`url`,
  NULL,
  `r`.`permiso_slug`,
  NULL,
  1
FROM (SELECT 10 AS orden, 'administracion_usuarios' AS slug, 'Usuarios' AS label, 'bi-people' AS icon, '/admin/administracion/usuarios' AS url, 'usuarios.gestionar' AS permiso_slug
      UNION ALL SELECT 20, 'administracion_roles', 'Roles y permisos', 'bi-key', '/admin/administracion/roles', 'roles.gestionar'
      UNION ALL SELECT 30, 'administracion_ajustes', 'Ajustes', 'bi-gear', '/admin/ajustes', 'administracion.ver'
      UNION ALL SELECT 40, 'sistema_estado', 'Estado del sistema', 'bi-hdd-stack', '/admin/sistema/estado', 'sistema.ver') AS `r`
JOIN `core_menu_items` AS `p` ON `p`.`slug` = 'administracion'
WHERE NOT EXISTS (
  SELECT 1 FROM `core_menu_items` `x` WHERE `x`.`slug` = `r`.`slug`
);

INSERT IGNORE INTO `auth_usuarios`
  (`nombre`, `apellido`, `email`, `password`, `activo`)
VALUES (
  'Admin',
  'Sistema',
  'admin@sistema.local',
  '$2y$12$KkB982JOpNRlhl.OCJFKAef/1elptraPngsoWY9l95OLDmLEze95K',
  1
);

INSERT IGNORE INTO `auth_usuarios_roles` (`usuario_id`, `rol_id`)
SELECT `u`.`id`, `r`.`id`
FROM `auth_usuarios` `u`
JOIN `auth_roles` `r` ON `r`.`slug` = 'administrador'
WHERE `u`.`email` = 'admin@sistema.local';

INSERT IGNORE INTO `cfg_configuraciones` (`clave`, `valor`, `tipo`, `descripcion`) VALUES
  ('empresa_nombre', 'Framework Lebytek', 'string', 'Nombre de la empresa'),
  ('empresa_logo', '', 'string', 'URL del logo'),
  ('empresa_mostrar_nombre', '1', 'boolean', 'Mostrar nombre junto al logo en login y barras de navegación'),
  ('menu_layout', 'side', 'string', 'Posición del menú: side, top, bottom'),
  ('primary_color', '#0d6efd', 'string', 'Color principal del sistema'),
  ('navbar_color', '#1a1d2e', 'string', 'Color de fondo del navbar/sidebar'),
  ('body_color', '#f0f2f5', 'string', 'Color de fondo del área de contenido'),
  ('dark_mode', '0', 'boolean', 'Modo oscuro activado');

SET FOREIGN_KEY_CHECKS = 1;
