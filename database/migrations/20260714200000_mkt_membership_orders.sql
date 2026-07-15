-- Órdenes de membresía (transferencia bancaria) + backfill de slugs/límites comercial.

CREATE TABLE IF NOT EXISTS `dom_mkt_ordenes` (
  `id`                              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`                       CHAR(26)        NOT NULL,
  `paquete_id`                      BIGINT UNSIGNED NOT NULL,
  `paquete_slug`                    VARCHAR(50)     NOT NULL,
  `ciclo`                           VARCHAR(20)     NOT NULL,
  `precio_snapshot`                 DECIMAL(10,2)   NOT NULL,
  `mensajes_mes_limite_snapshot`    INT UNSIGNED    DEFAULT NULL,
  `nombre`                          VARCHAR(150)    NOT NULL,
  `email`                           VARCHAR(190)    NOT NULL,
  `telefono`                        VARCHAR(40)     NOT NULL,
  `empresa`                         VARCHAR(190)    NOT NULL,
  `direccion`                       VARCHAR(255)    NOT NULL,
  `rfc`                             VARCHAR(20)     DEFAULT NULL,
  `lead_id`                         BIGINT UNSIGNED DEFAULT NULL,
  `api_tenant_public_id`            CHAR(26)        DEFAULT NULL,
  `status`                          VARCHAR(30)     NOT NULL DEFAULT 'pending_transfer',
  `transfer_notified_at`            DATETIME        DEFAULT NULL,
  `authorized_at`                   DATETIME        DEFAULT NULL,
  `authorized_by`                   BIGINT UNSIGNED DEFAULT NULL,
  `api_activation_error`            TEXT            DEFAULT NULL,
  `deleted`                         TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`                      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`                      BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`                      DATETIME        DEFAULT NULL,
  `updated_by`                      BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`                      DATETIME        DEFAULT NULL,
  `deleted_by`                      BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mkt_ordenes_public_id` (`public_id`),
  KEY `idx_mkt_ordenes_status` (`status`),
  KEY `idx_mkt_ordenes_email` (`email`),
  KEY `idx_mkt_ordenes_deleted` (`deleted`),
  KEY `idx_mkt_ordenes_lead` (`lead_id`),
  KEY `idx_mkt_ordenes_paquete` (`paquete_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill slugs/límites alineados al design (Starter/Business/Enterprise).
UPDATE `dom_mkt_paquetes`
SET `slug` = 'starter', `mensajes_mes_limite` = 5000
WHERE `nombre` = 'Starter' AND `deleted` = 0 AND (`slug` IS NULL OR `slug` = '' OR `slug` = 'basico');

UPDATE `dom_mkt_paquetes`
SET `slug` = 'business', `mensajes_mes_limite` = 80000
WHERE `nombre` = 'Business' AND `deleted` = 0 AND (`slug` IS NULL OR `slug` = '' OR `slug` = 'pro');

UPDATE `dom_mkt_paquetes`
SET `slug` = 'empresa', `mensajes_mes_limite` = NULL
WHERE `nombre` IN ('Enterprise', 'Empresa') AND `deleted` = 0 AND (`slug` IS NULL OR `slug` = '');

UPDATE `dom_mkt_paquetes`
SET `activo` = 0
WHERE `slug` = 'demo' AND `deleted` = 0;

INSERT INTO `auth_permisos` (`clave`, `descripcion`, `modulo`)
SELECT 'marketing.ordenes', 'Autorizar órdenes de membresía', 'marketing'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `auth_permisos` WHERE `clave` = 'marketing.ordenes');
