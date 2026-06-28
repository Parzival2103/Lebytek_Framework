-- database/schema/modules/marketing_demo.sql
-- Datos demo (sabor WhatsApp SaaS) para ejercitar el front público de Marketing.
-- Solo datos: las tablas dom_mkt_* ya existen tras marketing.sql.
-- Idempotente (UPDATE guardado / INSERT ... WHERE NOT EXISTS).
-- Cargar con: php scripts/seed.php --marketing-demo
SET NAMES utf8mb4;

-- ── Paquetes ──────────────────────────────────────────────────────────────────
UPDATE `dom_mkt_paquetes` SET `activo` = 0 WHERE `nombre` = 'Plan Demo';

INSERT INTO `dom_mkt_paquetes` (`nombre`,`precio_mensual`,`precio_anual`,`features`,`destacado`,`badge`,`orden`,`activo`)
SELECT * FROM (SELECT
  'Básico' AS nombre, 69.00 AS precio_mensual, 599.00 AS precio_anual,
  JSON_ARRAY('5,000 mensajes/mes','1 número de WhatsApp','Soporte por correo') AS features,
  0 AS destacado, NULL AS badge, 1 AS orden, 1 AS activo
) AS t WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `nombre` = 'Básico');

INSERT INTO `dom_mkt_paquetes` (`nombre`,`precio_mensual`,`precio_anual`,`features`,`destacado`,`badge`,`orden`,`activo`)
SELECT * FROM (SELECT
  'Pro' AS nombre, 99.00 AS precio_mensual, 899.00 AS precio_anual,
  JSON_ARRAY('30,000 mensajes/mes','1 número de WhatsApp','Soporte prioritario') AS features,
  1 AS destacado, 'Más popular' AS badge, 2 AS orden, 1 AS activo
) AS t WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `nombre` = 'Pro');

INSERT INTO `dom_mkt_paquetes` (`nombre`,`precio_mensual`,`precio_anual`,`features`,`destacado`,`badge`,`orden`,`activo`)
SELECT * FROM (SELECT
  'Empresa' AS nombre, NULL AS precio_mensual, NULL AS precio_anual,
  JSON_ARRAY('Mensajes ilimitados','Múltiples números','Soporte dedicado') AS features,
  0 AS destacado, NULL AS badge, 3 AS orden, 1 AS activo
) AS t WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `nombre` = 'Empresa');

-- ── Bloque hero (demo-autoritativo: UPDATE si existe, INSERT si falta) ─────────
UPDATE `dom_mkt_bloques` SET `contenido` = JSON_OBJECT(
  'titulo','Envía mensajes de WhatsApp desde tus programas',
  'subtitulo','API simple y confiable para automatizar notificaciones, alertas y mensajes a tus clientes.',
  'badge','API de WhatsApp',
  'cta_texto','Solicitar demo','cta_url','#demo',
  'cta2_texto','Ver paquetes','cta2_url','#paquetes',
  'media', JSON_OBJECT('img','/assets/publico/hero-mock.jpg','alt','Vista previa de mensajería automatizada')
), `activo` = 1 WHERE `pagina` = 'home' AND `clave` = 'hero';

INSERT INTO `dom_mkt_bloques` (`pagina`,`clave`,`contenido`,`orden`,`activo`)
SELECT * FROM (SELECT 'home' AS pagina, 'hero' AS clave, JSON_OBJECT(
  'titulo','Envía mensajes de WhatsApp desde tus programas',
  'subtitulo','API simple y confiable para automatizar notificaciones, alertas y mensajes a tus clientes.',
  'badge','API de WhatsApp',
  'cta_texto','Solicitar demo','cta_url','#demo',
  'cta2_texto','Ver paquetes','cta2_url','#paquetes',
  'media', JSON_OBJECT('img','/assets/publico/hero-mock.jpg','alt','Vista previa de mensajería automatizada')
) AS contenido, 1 AS orden, 1 AS activo) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'hero');

-- ── Bloque trust ──────────────────────────────────────────────────────────────
INSERT INTO `dom_mkt_bloques` (`pagina`,`clave`,`contenido`,`orden`,`activo`)
SELECT * FROM (SELECT 'home' AS pagina, 'trust' AS clave, JSON_OBJECT('items', JSON_ARRAY(
  JSON_OBJECT('valor','REST API','etiqueta','Integración simple'),
  JSON_OBJECT('valor','< 5 min','etiqueta','Tiempo de setup'),
  JSON_OBJECT('valor','24/7','etiqueta','Entrega confiable')
)) AS contenido, 2 AS orden, 1 AS activo) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'trust');

-- ── Bloque testimonios ────────────────────────────────────────────────────────
INSERT INTO `dom_mkt_bloques` (`pagina`,`clave`,`contenido`,`orden`,`activo`)
SELECT * FROM (SELECT 'home' AS pagina, 'testimonios' AS clave, JSON_OBJECT('items', JSON_ARRAY(
  JSON_OBJECT('texto','Integramos la API en una tarde y ahora enviamos confirmaciones automáticas.','autor','María G., E-commerce'),
  JSON_OBJECT('texto','El servicio es estable y el soporte responde rápido.','autor','Carlos R., SaaS de citas'),
  JSON_OBJECT('texto','Pasamos de SMS a WhatsApp y mejoró la tasa de respuesta.','autor','Lucía M., Logística')
)) AS contenido, 4 AS orden, 1 AS activo) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'testimonios');

-- ── Bloque footer ─────────────────────────────────────────────────────────────
INSERT INTO `dom_mkt_bloques` (`pagina`,`clave`,`contenido`,`orden`,`activo`)
SELECT * FROM (SELECT 'home' AS pagina, 'footer' AS clave, JSON_OBJECT(
  'columnas', JSON_ARRAY(
    JSON_OBJECT('titulo','Producto','links', JSON_ARRAY(
      JSON_OBJECT('texto','Paquetes','url','#paquetes'),
      JSON_OBJECT('texto','Solicitar demo','url','#demo')
    )),
    JSON_OBJECT('titulo','Empresa','links', JSON_ARRAY(
      JSON_OBJECT('texto','Acceder','url','/login')
    ))
  ),
  'legal','Mensajería automatizada de WhatsApp para tu negocio.'
) AS contenido, 9 AS orden, 1 AS activo) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'footer');

-- ── Plantilla autoresponder (copy demo) ───────────────────────────────────────
UPDATE `dom_mkt_plantillas`
SET `asunto` = 'Gracias por tu interés en nuestra API de WhatsApp',
    `cuerpo` = 'Hola {{nombre}}, recibimos tu solicitud de demo y te contactaremos en menos de 24 horas.'
WHERE `clave` = 'lead_autoresponder';
