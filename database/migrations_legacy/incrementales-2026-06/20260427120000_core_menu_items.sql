-- core_menu_items: menú administrativo desde BD (incremental sobre instalaciones ya creadas)
-- Compatible con MySQL 8.0+ / MariaDB 10.6+
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `core_menu_items` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`       INT UNSIGNED DEFAULT NULL,
  `orden`           SMALLINT     NOT NULL DEFAULT 0,
  `slug`            VARCHAR(80)  NOT NULL,
  `label`           VARCHAR(150) NOT NULL,
  `icon`            VARCHAR(80)  DEFAULT NULL,
  `url`             VARCHAR(500) DEFAULT NULL,
  `match`           VARCHAR(500) DEFAULT NULL,
  `permiso_slug`    VARCHAR(100) DEFAULT NULL,
  `vertical_module` VARCHAR(64)  DEFAULT NULL,
  `activo`          TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_core_menu_items_slug` (`slug`),
  KEY `idx_core_menu_parent_orden` (`parent_id`, `orden`, `activo`),
  CONSTRAINT `fk_core_menu_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `core_menu_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
