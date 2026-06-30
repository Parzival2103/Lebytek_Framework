-- database/schema/modules/marketing.sql
-- Bootstrap del módulo Marketing y Contenido Público.
-- Ejecutado solo cuando el wizard/instalador selecciona el módulo marketing.
-- Crea tablas dom_mkt_*, permisos RBAC, menú y datos demo genéricos. Idempotente.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `dom_mkt_leads` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(150)    NOT NULL,
  `email`         VARCHAR(190)    NOT NULL,
  `telefono`      VARCHAR(40)     DEFAULT NULL,
  `mensaje`       TEXT            DEFAULT NULL,
  `estado`        VARCHAR(30)     NOT NULL DEFAULT 'pendiente',
  `utm_source`    VARCHAR(120)    DEFAULT NULL,
  `utm_medium`    VARCHAR(120)    DEFAULT NULL,
  `utm_campaign`  VARCHAR(120)    DEFAULT NULL,
  `api_tenant_public_id` CHAR(26) NULL,
  `external_ref`  VARCHAR(255)    DEFAULT NULL,
  `api_provisioned_at` DATETIME   DEFAULT NULL,
  `api_provision_error` TEXT       DEFAULT NULL,
  `deleted`       TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`    BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`    DATETIME        DEFAULT NULL,
  `updated_by`    BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`    DATETIME        DEFAULT NULL,
  `deleted_by`    BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_leads_estado` (`estado`),
  KEY `idx_mkt_leads_deleted` (`deleted`),
  UNIQUE KEY `dom_mkt_leads_api_tenant_public_id_unique` (`api_tenant_public_id`),
  UNIQUE KEY `dom_mkt_leads_external_ref_unique` (`external_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_provisiones` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id`       BIGINT UNSIGNED DEFAULT NULL,
  `access_token`  CHAR(64)        DEFAULT NULL,
  `expira_en`     DATETIME        DEFAULT NULL,
  `estado`        VARCHAR(30)     NOT NULL DEFAULT 'pendiente',
  `payload`       JSON            DEFAULT NULL,
  `deleted`       TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`    BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`    DATETIME        DEFAULT NULL,
  `updated_by`    BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`    DATETIME        DEFAULT NULL,
  `deleted_by`    BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mkt_prov_token` (`access_token`),
  KEY `idx_mkt_prov_lead` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_paquetes` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`         VARCHAR(150)    NOT NULL,
  `precio_mensual` DECIMAL(10,2)   DEFAULT NULL,
  `precio_anual`   DECIMAL(10,2)   DEFAULT NULL,
  `features`       JSON            DEFAULT NULL,
  `destacado`      TINYINT(1)      NOT NULL DEFAULT 0,
  `badge`          VARCHAR(60)     DEFAULT NULL,
  `orden`          INT             NOT NULL DEFAULT 0,
  `activo`         TINYINT(1)      NOT NULL DEFAULT 1,
  `deleted`        TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`     BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`     DATETIME        DEFAULT NULL,
  `updated_by`     BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`     DATETIME        DEFAULT NULL,
  `deleted_by`     BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_paquetes_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_bloques` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pagina`      VARCHAR(120)    NOT NULL DEFAULT 'home',
  `clave`       VARCHAR(120)    NOT NULL,
  `contenido`   JSON            DEFAULT NULL,
  `orden`       INT             NOT NULL DEFAULT 0,
  `activo`      TINYINT(1)      NOT NULL DEFAULT 1,
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_bloques_pagina` (`pagina`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_plantillas` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave`       VARCHAR(120)    NOT NULL,
  `asunto`      VARCHAR(255)    NOT NULL,
  `cuerpo`      MEDIUMTEXT      NOT NULL,
  `activo`      TINYINT(1)      NOT NULL DEFAULT 1,
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_plantillas_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_secuencias` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(150)    NOT NULL,
  `pasos`       JSON            DEFAULT NULL,
  `activo`      TINYINT(1)      NOT NULL DEFAULT 1,
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_paginas` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(160)    NOT NULL,
  `titulo`      VARCHAR(200)    NOT NULL,
  `layout`      VARCHAR(60)     NOT NULL DEFAULT 'default',
  `publicada`   TINYINT(1)      NOT NULL DEFAULT 0,
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mkt_paginas_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Permisos RBAC ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver marketing',       'marketing.ver',       'marketing', 'Acceso de lectura al módulo de marketing'),
('Crear en marketing',  'marketing.crear',     'marketing', 'Crear contenido/paquetes/plantillas'),
('Editar en marketing', 'marketing.editar',    'marketing', 'Editar contenido/paquetes/plantillas'),
('Eliminar en marketing','marketing.eliminar', 'marketing', 'Eliminar lógico en marketing'),
('Gestionar marketing', 'marketing.gestionar', 'marketing', 'Gestionar ajustes del módulo de marketing'),
('Gestionar leads',     'marketing.leads',     'marketing', 'Gestionar la bandeja de leads'),
('Publicar contenido',  'marketing.publicar',  'marketing', 'Publicar páginas y contenido público');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'marketing.ver','marketing.crear','marketing.editar','marketing.eliminar',
  'marketing.gestionar','marketing.leads','marketing.publicar'
)
WHERE `r`.`slug` = 'administrador';

-- ── Menú dinámico ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
VALUES
(NULL, 80, 'marketing', 'Marketing', 'bi-megaphone', NULL, '/admin/crud/mkt_', 'marketing.ver', 'marketing', 1);

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 1, 'marketing-leads', 'Leads', 'bi-people', '/admin/crud/mkt_leads', '/admin/crud/mkt_leads', 'marketing.leads', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 2, 'marketing-paquetes', 'Paquetes', 'bi-box-seam', '/admin/crud/mkt_paquetes', '/admin/crud/mkt_paquetes', 'marketing.ver', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 3, 'marketing-bloques', 'Contenido', 'bi-layout-text-window', '/admin/crud/mkt_bloques', '/admin/crud/mkt_bloques', 'marketing.publicar', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 4, 'marketing-plantillas', 'Plantillas correo', 'bi-envelope-paper', '/admin/crud/mkt_plantillas', '/admin/crud/mkt_plantillas', 'marketing.gestionar', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing';

-- ── Datos demo (genéricos, idempotentes) ──────────────────────────────────────
INSERT INTO `dom_mkt_paquetes` (`nombre`, `precio_mensual`, `precio_anual`, `features`, `destacado`, `badge`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'Plan Demo' AS nombre, 299.00 AS precio_mensual, 2990.00 AS precio_anual,
         JSON_ARRAY('Soporte por correo','Hasta 3 usuarios','Reportes básicos') AS features,
         1 AS destacado, 'Popular' AS badge, 1 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes`);

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'home' AS pagina, 'hero' AS clave,
         JSON_OBJECT('titulo','Tu negocio, en línea','subtitulo','Captura clientes con una landing lista para usar','cta_texto','Solicita una demo','cta_url','#demo') AS contenido,
         1 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques`);

INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT * FROM (
  SELECT 'lead_autoresponder' AS clave, 'Gracias por tu interés' AS asunto,
         'Hola {{nombre}}, recibimos tu solicitud y te contactaremos pronto.' AS cuerpo, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas`);

SET FOREIGN_KEY_CHECKS = 1;
