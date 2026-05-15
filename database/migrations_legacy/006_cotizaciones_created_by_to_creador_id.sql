-- LEGACY: solo BD sin prefijos. Tras 007 usar REFERENCES `auth_usuarios`.
-- Alinea cotizaciones con convención creador_id (como pedidos, solicitudes).
-- Solo si la columna aún se llama created_by (schema antiguo). Si ya es creador_id, omitir esta migración.

ALTER TABLE `cotizaciones` DROP FOREIGN KEY `fk_cotizaciones_usuario`;
ALTER TABLE `cotizaciones` CHANGE `created_by` `creador_id` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `cotizaciones` ADD CONSTRAINT `fk_cotizaciones_creador`
  FOREIGN KEY (`creador_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL;
