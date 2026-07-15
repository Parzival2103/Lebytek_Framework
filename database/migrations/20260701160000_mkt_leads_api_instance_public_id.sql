ALTER TABLE `dom_mkt_leads`
  ADD COLUMN `api_instance_public_id` CHAR(26) NULL AFTER `api_tenant_public_id`;
