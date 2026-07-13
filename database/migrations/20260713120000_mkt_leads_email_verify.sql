ALTER TABLE `dom_mkt_leads`
  ADD COLUMN `email_verify_token` VARCHAR(64) NULL AFTER `estado`,
  ADD COLUMN `email_verify_code_hash` VARCHAR(64) NULL AFTER `email_verify_token`,
  ADD COLUMN `email_verify_expires_at` DATETIME NULL AFTER `email_verify_code_hash`,
  ADD COLUMN `email_verified_at` DATETIME NULL AFTER `email_verify_expires_at`,
  ADD COLUMN `email_verify_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `email_verified_at`,
  ADD UNIQUE KEY `uq_mkt_leads_email_verify_token` (`email_verify_token`);
