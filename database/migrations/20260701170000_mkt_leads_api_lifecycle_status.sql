ALTER TABLE `dom_mkt_leads`
  ADD COLUMN `api_lifecycle_status` VARCHAR(32) NOT NULL DEFAULT 'none' AFTER `api_provision_error`;
