-- ============================================================
-- LEGACY: solo BD sin prefijos / histórico. Instalación nueva desde schema.sql: omitir.
-- Migración 004: Capturas por envío (múltiples respuestas por enlace) + modo único/múltiple
-- ============================================================

-- Tabla: una fila por cada envío completado del formulario público (empleado / persona)
CREATE TABLE IF NOT EXISTS `formularios_capturas` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `envio_id`   INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_capturas_envio` (`envio_id`),
  CONSTRAINT `fk_capturas_envio`
    FOREIGN KEY (`envio_id`) REFERENCES `formularios_envios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modo del enlace: un solo llenado vs encuesta (muchos)
ALTER TABLE `formularios_envios`
  ADD COLUMN `modo` ENUM('unico','multiple') NOT NULL DEFAULT 'multiple' AFTER `estado`;

-- Respuestas atadas a una captura concreta
ALTER TABLE `formularios_respuestas`
  ADD COLUMN `captura_id` INT UNSIGNED NULL AFTER `envio_id`;

-- Datos existentes: una captura por cada envío que ya tenía respuestas
INSERT INTO `formularios_capturas` (`envio_id`, `created_at`)
SELECT `envio_id`, MIN(`created_at`) FROM `formularios_respuestas` GROUP BY `envio_id`;

UPDATE `formularios_respuestas` `r`
INNER JOIN `formularios_capturas` `c` ON `c`.`envio_id` = `r`.`envio_id`
SET `r`.`captura_id` = `c`.`id`
WHERE `r`.`captura_id` IS NULL;

ALTER TABLE `formularios_respuestas`
  MODIFY `captura_id` INT UNSIGNED NOT NULL;

ALTER TABLE `formularios_respuestas`
  ADD CONSTRAINT `fk_respuestas_captura`
    FOREIGN KEY (`captura_id`) REFERENCES `formularios_capturas`(`id`) ON DELETE CASCADE;

ALTER TABLE `formularios_respuestas`
  ADD UNIQUE KEY `uk_captura_campo` (`captura_id`, `campo_id`);
