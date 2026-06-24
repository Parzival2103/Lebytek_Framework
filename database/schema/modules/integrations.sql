-- database/schema/modules/integrations.sql
-- Bootstrap del módulo Integraciones y Conectores (Fase 1).
-- Crea la tabla int_logs y los permisos RBAC. Idempotente (re-ejecutable).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `int_logs` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel`             VARCHAR(40)     NOT NULL,
  `driver`              VARCHAR(60)     NOT NULL,
  `recipient_masked`    VARCHAR(190)    NOT NULL,
  `status`              VARCHAR(20)     NOT NULL,
  `provider_message_id` VARCHAR(190)    DEFAULT NULL,
  `error`               VARCHAR(500)    DEFAULT NULL,
  `meta`                JSON            DEFAULT NULL,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`          BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_int_logs_channel` (`channel`, `status`),
  KEY `idx_int_logs_provider_msg` (`provider_message_id`),
  KEY `idx_int_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Permisos RBAC ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver integraciones',     'integrations.ver',        'integrations', 'Acceso de lectura al módulo de integraciones'),
('Enviar mensajes',       'integrations.enviar',     'integrations', 'Disparar envíos salientes vía la fachada'),
('Configurar integraciones','integrations.configurar','integrations', 'Gestionar la configuración del módulo');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'integrations.ver','integrations.enviar','integrations.configurar'
)
WHERE `r`.`slug` = 'administrador';

-- ── Instancias (Fase 2): fuente única de credenciales Green API ──────────────────
CREATE TABLE IF NOT EXISTS `int_accounts` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`         VARCHAR(40)     NOT NULL DEFAULT 'green_api',
  `label`            VARCHAR(190)    NOT NULL,
  `instance_id`      VARCHAR(190)    NOT NULL,
  `token_encrypted`  TEXT            NOT NULL,
  `is_default`       TINYINT(1)      NOT NULL DEFAULT 0,
  `lead_id`          BIGINT UNSIGNED DEFAULT NULL,
  `status`           VARCHAR(20)     NOT NULL DEFAULT 'manual',
  `provisioned_via`  VARCHAR(20)     NOT NULL DEFAULT 'manual',
  `meta`             JSON            DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`       BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_int_accounts_default` (`is_default`),
  KEY `idx_int_accounts_lead` (`lead_id`),
  KEY `idx_int_accounts_provider` (`provider`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
VALUES
(NULL, 95, 'integraciones', 'Integraciones', 'bi-plug', '/admin/integraciones', '/admin/integraciones', 'integrations.ver', 'integrations', 1);

SET FOREIGN_KEY_CHECKS = 1;
