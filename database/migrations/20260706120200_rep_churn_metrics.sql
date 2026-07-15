-- Snapshots mensuales de churn y señales de riesgo.
CREATE TABLE IF NOT EXISTS `rep_churn_monthly` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_year` SMALLINT NOT NULL,
  `period_month` TINYINT NOT NULL,
  `clients_start` INT UNSIGNED NOT NULL DEFAULT 0,
  `clients_lost` INT UNSIGNED NOT NULL DEFAULT 0,
  `churn_rate_pct` DECIMAL(6,3) NOT NULL DEFAULT 0,
  `demos_started` INT UNSIGNED NOT NULL DEFAULT 0,
  `demos_converted` INT UNSIGNED NOT NULL DEFAULT 0,
  `demo_conversion_pct` DECIMAL(6,3) NULL,
  `active_by_usage` INT UNSIGNED NOT NULL DEFAULT 0,
  `at_risk_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `net_new_clients` INT UNSIGNED NOT NULL DEFAULT 0,
  `calculated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rep_churn_period` (`period_year`, `period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rep_risk_signals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` BIGINT UNSIGNED NULL,
  `tenant_public_id` CHAR(26) NULL,
  `signal_type` VARCHAR(64) NOT NULL,
  `severity` ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `payload_json` JSON NULL,
  `detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME NULL,
  `notified_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_risk_open` (`resolved_at`, `signal_type`),
  KEY `idx_risk_tenant` (`tenant_public_id`),
  KEY `idx_risk_lead_type_day` (`lead_id`, `signal_type`, `detected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
