ALTER TABLE `dom_mkt_leads`
  ADD COLUMN `api_tenant_public_id` CHAR(26) NULL AFTER `utm_campaign`,
  ADD COLUMN `external_ref` VARCHAR(255) NULL AFTER `api_tenant_public_id`,
  ADD COLUMN `api_provisioned_at` DATETIME NULL AFTER `external_ref`,
  ADD COLUMN `api_provision_error` TEXT NULL AFTER `api_provisioned_at`,
  ADD UNIQUE KEY `dom_mkt_leads_api_tenant_public_id_unique` (`api_tenant_public_id`),
  ADD UNIQUE KEY `dom_mkt_leads_external_ref_unique` (`external_ref`);

INSERT IGNORE INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
VALUES (
  'demo_credenciales_api',
  'Tus credenciales de acceso — Lebytek',
  'Hola {{nombre}},\n\nTu demo está lista. Usa este token para conectar con nuestra API:\n\nToken: {{token}}\nBase URL: {{api_base_url}}\n\nConserva este correo; el token no se vuelve a mostrar.\n\nSaludos,\nEquipo Lebytek',
  1
);
