-- =====================================================================
-- SOLO usar en bases que aún tienen tablas dom_* del dominio de ejemplo
-- (imprenta/pipeline CRM). Ejecutar una vez en entorno correcto tras backup.
-- Orden DROP respetando dependencias FK hacia tabla padre después de hijos.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `dom_control_calidad`;
DROP TABLE IF EXISTS `dom_ordenes_produccion`;
DROP TABLE IF EXISTS `dom_disenos`;
DROP TABLE IF EXISTS `dom_entregas`;
DROP TABLE IF EXISTS `dom_pagos`;
DROP TABLE IF EXISTS `dom_facturas`;
DROP TABLE IF EXISTS `dom_estados_pedidos`;
DROP TABLE IF EXISTS `dom_pedidos_detalles`;
DROP TABLE IF EXISTS `dom_pedidos`;
DROP TABLE IF EXISTS `dom_cotizaciones_detalles`;
DROP TABLE IF EXISTS `dom_cotizaciones`;
DROP TABLE IF EXISTS `dom_solicitudes`;
DROP TABLE IF EXISTS `dom_formularios_respuestas`;
DROP TABLE IF EXISTS `dom_formularios_capturas`;
DROP TABLE IF EXISTS `dom_formularios_envios`;
DROP TABLE IF EXISTS `dom_formularios_campos`;
DROP TABLE IF EXISTS `dom_formularios_plantillas`;
DROP TABLE IF EXISTS `dom_clientes_datos_fiscales`;
DROP TABLE IF EXISTS `dom_clientes_contactos`;
DROP TABLE IF EXISTS `dom_clientes`;
DROP TABLE IF EXISTS `dom_precios_productos`;
DROP TABLE IF EXISTS `dom_variantes`;
DROP TABLE IF EXISTS `dom_productos`;
DROP TABLE IF EXISTS `dom_categorias`;
DROP TABLE IF EXISTS `dom_tecnicas_personalizacion`;
DROP TABLE IF EXISTS `dom_temporadas`;

DELETE FROM `log_bitacora` WHERE `tabla` LIKE 'dom\_%' ESCAPE '\\';

SET FOREIGN_KEY_CHECKS = 1;
