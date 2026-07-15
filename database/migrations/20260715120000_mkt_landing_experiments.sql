-- database/migrations/20260715120000_mkt_landing_experiments.sql
-- Sigue database/migrations/README.md: IF NOT EXISTS en DDL; grants idempotentes.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `dom_mkt_variant_weights` (
  `slug`       VARCHAR(40)     NOT NULL,
  `weight`     DECIMAL(8,4)    NOT NULL DEFAULT 0,
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_variant_proposals` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `status`      VARCHAR(20)     NOT NULL DEFAULT 'pending',
  `payload`     JSON            NOT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME        DEFAULT NULL,
  `resolved_by` BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_var_prop_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_landing_sessions` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`       CHAR(36)        NOT NULL,
  `visitor_id`      CHAR(36)        NOT NULL,
  `variant_slug`    VARCHAR(40)     NOT NULL,
  `is_preview`      TINYINT(1)      NOT NULL DEFAULT 0,
  `duration_ms`     INT UNSIGNED    NOT NULL DEFAULT 0,
  `max_scroll_pct`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `exit_section`    VARCHAR(60)     DEFAULT NULL,
  `first_seen_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mkt_land_sess_public` (`public_id`),
  KEY `idx_mkt_land_sess_visitor` (`visitor_id`),
  KEY `idx_mkt_land_sess_variant` (`variant_slug`, `is_preview`, `first_seen_at`),
  KEY `idx_mkt_land_sess_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_landing_events` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`   BIGINT UNSIGNED DEFAULT NULL,
  `visitor_id`   CHAR(36)        NOT NULL,
  `variant_slug` VARCHAR(40)     NOT NULL,
  `event_type`   VARCHAR(40)     NOT NULL,
  `meta`         JSON            DEFAULT NULL,
  `is_preview`   TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_land_evt_var` (`variant_slug`, `is_preview`, `created_at`),
  KEY `idx_mkt_land_evt_type` (`event_type`),
  KEY `idx_mkt_land_evt_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `dom_mkt_leads`
  ADD COLUMN IF NOT EXISTS `landing_variant` VARCHAR(40) DEFAULT NULL AFTER `utm_campaign`,
  ADD COLUMN IF NOT EXISTS `visitor_id` CHAR(36) DEFAULT NULL AFTER `landing_variant`;

CREATE INDEX IF NOT EXISTS `idx_mkt_leads_landing_variant`
  ON `dom_mkt_leads` (`landing_variant`, `created_at`);

INSERT INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`)
SELECT 'Experimentos landing', 'marketing.experimentos', 'marketing', 'Ver métricas y aceptar/rechazar propuestas de peso'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `auth_permisos` WHERE `slug` = 'marketing.experimentos');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` = 'marketing.experimentos'
WHERE `r`.`slug` = 'administrador';

INSERT IGNORE INTO `core_menu_items`
  (`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 6, 'marketing-experimentos', 'Experimentos', 'bi-graph-up', '/admin/marketing/experimentos', '/admin/marketing/experimentos', 'marketing.experimentos', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing'
  AND NOT EXISTS (SELECT 1 FROM core_menu_items c WHERE c.slug = 'marketing-experimentos');
