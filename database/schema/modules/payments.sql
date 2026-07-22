-- database/schema/modules/payments.sql
-- Bootstrap del módulo Pagos (plataforma). Idempotente.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `pay_events` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`      VARCHAR(40)     NOT NULL,
  `event_id`      VARCHAR(190)    NOT NULL,
  `order_ref`     VARCHAR(64)     DEFAULT NULL,
  `type`          VARCHAR(60)     NOT NULL,
  `payload_hash`  CHAR(64)        NOT NULL,
  `meta`          JSON            DEFAULT NULL,
  `processed_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pay_events_provider_event` (`provider`, `event_id`),
  KEY `idx_pay_events_order_ref` (`order_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
