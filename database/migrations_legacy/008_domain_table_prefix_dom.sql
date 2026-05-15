-- ============================================================
-- Migración 008: Prefijo dom_ en tablas de dominio (ejemplo)
-- ============================================================
-- Ejecutar DESPUÉS de 007_system_table_prefixes.sql
-- Instalaciones nuevas: usar solo database/schema/schema.sql (ya incluye dom_*).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

RENAME TABLE `temporadas` TO `dom_temporadas`;
RENAME TABLE `categorias` TO `dom_categorias`;
RENAME TABLE `productos` TO `dom_productos`;
RENAME TABLE `variantes` TO `dom_variantes`;
RENAME TABLE `tecnicas_personalizacion` TO `dom_tecnicas_personalizacion`;
RENAME TABLE `precios_productos` TO `dom_precios_productos`;
RENAME TABLE `clientes` TO `dom_clientes`;
RENAME TABLE `clientes_contactos` TO `dom_clientes_contactos`;
RENAME TABLE `clientes_datos_fiscales` TO `dom_clientes_datos_fiscales`;
RENAME TABLE `formularios_plantillas` TO `dom_formularios_plantillas`;
RENAME TABLE `formularios_campos` TO `dom_formularios_campos`;
RENAME TABLE `formularios_envios` TO `dom_formularios_envios`;
RENAME TABLE `formularios_capturas` TO `dom_formularios_capturas`;
RENAME TABLE `formularios_respuestas` TO `dom_formularios_respuestas`;
RENAME TABLE `solicitudes` TO `dom_solicitudes`;
RENAME TABLE `cotizaciones` TO `dom_cotizaciones`;
RENAME TABLE `cotizaciones_detalles` TO `dom_cotizaciones_detalles`;
RENAME TABLE `pedidos` TO `dom_pedidos`;
RENAME TABLE `pedidos_detalles` TO `dom_pedidos_detalles`;
RENAME TABLE `estados_pedidos` TO `dom_estados_pedidos`;
RENAME TABLE `disenos` TO `dom_disenos`;
RENAME TABLE `ordenes_produccion` TO `dom_ordenes_produccion`;
RENAME TABLE `control_calidad` TO `dom_control_calidad`;
RENAME TABLE `entregas` TO `dom_entregas`;
RENAME TABLE `pagos` TO `dom_pagos`;
RENAME TABLE `facturas` TO `dom_facturas`;

SET FOREIGN_KEY_CHECKS = 1;
