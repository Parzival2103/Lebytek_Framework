-- ============================================================
-- LEGACY: solo BD sin prefijos y antes de 007/008. Instalación nueva: omitir.
-- Migración 003: Renombrar columnas FK para cumplir convención tabla_id
-- ============================================================


-- disenos: created_by -> creador_id (sin FK previa)
ALTER TABLE `disenos` CHANGE `created_by` `creador_id` INT UNSIGNED DEFAULT NULL;

-- ordenes_produccion: created_by -> creador_id (sin FK previa)
ALTER TABLE `ordenes_produccion` CHANGE `created_by` `creador_id` INT UNSIGNED DEFAULT NULL;

-- entregas: created_by -> creador_id (sin FK previa)
ALTER TABLE `entregas` CHANGE `created_by` `creador_id` INT UNSIGNED DEFAULT NULL;

-- pagos: created_by -> creador_id (sin FK previa)
ALTER TABLE `pagos` CHANGE `created_by` `creador_id` INT UNSIGNED DEFAULT NULL;

-- facturas: created_by -> creador_id (sin FK previa)
ALTER TABLE `facturas` CHANGE `created_by` `creador_id` INT UNSIGNED DEFAULT NULL;

-- formularios_envios: enviado_por -> enviado_por_id
ALTER TABLE `formularios_envios` DROP FOREIGN KEY `fk_envios_usuario`;
ALTER TABLE `formularios_envios` CHANGE `enviado_por` `enviado_por_id` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `formularios_envios` ADD CONSTRAINT `fk_envios_enviado_por`
    FOREIGN KEY (`enviado_por_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL;

-- control_calidad: revisado_by -> revisado_por_id
ALTER TABLE `control_calidad` DROP FOREIGN KEY `fk_calidad_usuario`;
ALTER TABLE `control_calidad` CHANGE `revisado_by` `revisado_por_id` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `control_calidad` ADD CONSTRAINT `fk_calidad_revisado_por`
    FOREIGN KEY (`revisado_por_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL;
