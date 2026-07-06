-- Extensiones de leads para churn, vencimiento demo y cuota.
ALTER TABLE `dom_mkt_leads`
  ADD COLUMN `demo_started_at` DATETIME NULL AFTER `api_provisioned_at`,
  ADD COLUMN `demo_expires_at` DATETIME NULL AFTER `demo_started_at`,
  ADD COLUMN `paquete_id` BIGINT UNSIGNED NULL AFTER `demo_expires_at`,
  ADD COLUMN `plan_slug` VARCHAR(50) NULL AFTER `paquete_id`,
  ADD COLUMN `converted_at` DATETIME NULL AFTER `plan_slug`,
  ADD COLUMN `cancelled_at` DATETIME NULL AFTER `converted_at`,
  ADD COLUMN `last_activity_at` DATETIME NULL AFTER `cancelled_at`,
  ADD COLUMN `first_authorized_at` DATETIME NULL AFTER `last_activity_at`,
  ADD COLUMN `first_message_sent_at` DATETIME NULL AFTER `first_authorized_at`,
  ADD KEY `idx_mkt_leads_demo_expires` (`demo_expires_at`),
  ADD KEY `idx_mkt_leads_last_activity` (`last_activity_at`),
  ADD KEY `idx_mkt_leads_plan_slug` (`plan_slug`);

-- Backfill demos existentes (30 días desde provision).
UPDATE `dom_mkt_leads`
SET `demo_started_at` = `api_provisioned_at`,
    `demo_expires_at` = DATE_ADD(`api_provisioned_at`, INTERVAL 30 DAY),
    `plan_slug` = 'demo'
WHERE `api_provisioned_at` IS NOT NULL
  AND `demo_expires_at` IS NULL;
