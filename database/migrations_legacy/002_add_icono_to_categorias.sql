-- LEGACY: solo BD sin prefijos. Instalación nueva desde schema.sql: omitir.
ALTER TABLE `categorias` ADD COLUMN `icono` VARCHAR(60) DEFAULT NULL AFTER `nombre`;
ALTER TABLE `categorias` ADD COLUMN IF NOT EXISTS `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;
