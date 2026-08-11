-- database/schema/modules/invoicing.sql
-- Bootstrap del módulo Invoicing (plataforma). Idempotente.
-- inv_events status: claimed | issued | needs_reconcile | canceled
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `inv_events` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`            VARCHAR(40)     NOT NULL,
  `idempotency_key`     VARCHAR(190)    NOT NULL,
  `source_ref`          VARCHAR(64)     DEFAULT NULL,
  `type`                VARCHAR(60)     NOT NULL,
  `provider_invoice_id` VARCHAR(190)    DEFAULT NULL,
  `uuid`                VARCHAR(50)     DEFAULT NULL,
  `folio_number`        VARCHAR(40)     DEFAULT NULL,
  `status`              VARCHAR(40)     NOT NULL DEFAULT 'claimed',
  `meta`                JSON            DEFAULT NULL,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME        DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_events_provider_idempotency` (`provider`, `idempotency_key`),
  KEY `idx_inv_events_source_ref` (`source_ref`),
  KEY `idx_inv_events_provider_status` (`provider`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inv_organizations` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_key`    VARCHAR(40)     NOT NULL,
  `external_org_id` VARCHAR(190)    NOT NULL DEFAULT '',
  `mode`            VARCHAR(10)     NOT NULL DEFAULT 'test',
  `label`           VARCHAR(190)    DEFAULT NULL,
  `meta`            JSON            DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_organizations_provider_external` (`provider_key`, `external_org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Permisos RBAC ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Emitir facturas',      'invoicing.emitir',      'invoicing', 'Emitir CFDI desde origen de negocio'),
('Cancelar facturas',    'invoicing.cancelar',    'invoicing', 'Cancelar CFDI emitido'),
('Descargar facturas',   'invoicing.descargar',   'invoicing', 'Descargar PDF/XML de facturas'),
('Enviar facturas',      'invoicing.enviar',      'invoicing', 'Enviar factura por email'),
('Reconciliar facturas', 'invoicing.reconciliar', 'invoicing', 'Reconciliar estado con proveedor y ops');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'invoicing.emitir','invoicing.cancelar','invoicing.descargar','invoicing.enviar','invoicing.reconciliar'
)
WHERE `r`.`slug` = 'administrador';

SET FOREIGN_KEY_CHECKS = 1;
