-- Límites estructurados por paquete (cuota mensual, días demo).
ALTER TABLE `dom_mkt_paquetes`
  ADD COLUMN `slug` VARCHAR(50) NULL AFTER `nombre`,
  ADD COLUMN `mensajes_mes_limite` INT UNSIGNED NULL AFTER `features`,
  ADD COLUMN `demo_dias` INT UNSIGNED NULL AFTER `mensajes_mes_limite`,
  ADD UNIQUE KEY `dom_mkt_paquetes_slug_unique` (`slug`);

UPDATE `dom_mkt_paquetes` SET `slug` = 'basico', `mensajes_mes_limite` = 5000 WHERE `nombre` = 'Básico' AND `deleted` = 0 AND `slug` IS NULL;
UPDATE `dom_mkt_paquetes` SET `slug` = 'pro', `mensajes_mes_limite` = 30000 WHERE `nombre` = 'Pro' AND `deleted` = 0 AND `slug` IS NULL;
UPDATE `dom_mkt_paquetes` SET `slug` = 'empresa', `mensajes_mes_limite` = NULL WHERE `nombre` IN ('Empresa', 'Enterprise') AND `deleted` = 0 AND `slug` IS NULL;

INSERT INTO `dom_mkt_paquetes` (`nombre`, `slug`, `precio_mensual`, `precio_anual`, `features`, `mensajes_mes_limite`, `demo_dias`, `destacado`, `orden`, `activo`)
SELECT 'Demo', 'demo', 0.00, 0.00,
  JSON_ARRAY('100 mensajes/mes', '30 días de prueba', '1 número WhatsApp'),
  100, 30, 0, 0, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `slug` = 'demo' AND `deleted` = 0);
