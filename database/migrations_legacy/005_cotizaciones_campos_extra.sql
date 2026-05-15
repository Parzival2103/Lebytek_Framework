-- LEGACY: solo BD sin prefijos. Instalación nueva: omitir.
-- Campos extra para cotizaciones: notas internas, tiempo de entrega, condiciones al cliente (PDF)
ALTER TABLE `cotizaciones`
  ADD COLUMN `notas_internas` TEXT DEFAULT NULL AFTER `notas`,
  ADD COLUMN `tiempo_entrega` VARCHAR(500) DEFAULT NULL AFTER `notas_internas`,
  ADD COLUMN `texto_condiciones_pdf` TEXT DEFAULT NULL AFTER `tiempo_entrega`;
