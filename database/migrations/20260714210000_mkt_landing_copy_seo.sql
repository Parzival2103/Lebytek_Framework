-- Copy/SEO refresh de la landing pública + bloque FAQ.
-- Idempotente: UPDATE de bloques existentes; INSERT del FAQ si falta.
-- Ejecutar en VPS vía el runner de migraciones (no aplicar a mano en prod sin review).

UPDATE `dom_mkt_bloques`
SET `contenido` = JSON_OBJECT(
  'badge', 'WhatsApp Business API',
  'titulo', 'Automatiza WhatsApp para tu negocio con la API de Lebytek',
  'subtitulo', 'Envía campañas masivas, respuestas automáticas y notificaciones desde tu sistema. Conecta tu CRM o software en minutos y conversa donde tus clientes ya están.',
  'cta_texto', 'Solicitar demo gratis',
  'cta_url', '#demo',
  'cta2_texto', 'Ver paquetes',
  'cta2_url', '#paquetes',
  'media', JSON_OBJECT('img', '/assets/publico/hero-dashboard.svg', 'alt', 'Panel de conversaciones WhatsApp')
),
    `orden` = 1,
    `activo` = 1
WHERE `pagina` = 'home' AND `clave` = 'hero' AND `deleted` = 0;

UPDATE `dom_mkt_bloques`
SET `contenido` = JSON_OBJECT(
  'titulo', 'Integra WhatsApp Business API sin complicaciones técnicas',
  'lead', 'Conecta tu aplicación, CRM o procesos internos mediante una URL segura y token de acceso. Implementación en minutos.',
  'items', JSON_ARRAY(
    JSON_OBJECT(
      'icon', 'bi-plug-fill',
      'titulo', 'API lista para conectar',
      'texto', 'Envía y recibe mensajes de WhatsApp directamente desde tu sistema usando una URL y token de autenticación simple.'
    ),
    JSON_OBJECT(
      'icon', 'bi-send-check-fill',
      'titulo', 'Mensajes automatizados',
      'texto', 'Configura confirmaciones de pedidos, recordatorios de citas, promociones y notificaciones transaccionales de forma automática.'
    ),
    JSON_OBJECT(
      'icon', 'bi-shield-lock-fill',
      'titulo', 'Acceso seguro por token',
      'texto', 'Cada instancia tiene sus propias credenciales. Tú controlas el uso y evitas gastos inesperados.'
    ),
    JSON_OBJECT(
      'icon', 'bi-lightning-charge-fill',
      'titulo', 'Implementación en minutos',
      'texto', 'Recibe tus credenciales en minutos y comienza a probar la API sin desarrollar integraciones complejas.'
    ),
    JSON_OBJECT(
      'icon', 'bi-graph-up-arrow',
      'titulo', 'Escalable según tu crecimiento',
      'texto', 'Comienza con una línea de WhatsApp y agrega más instancias conforme crezca tu volumen de mensajes.'
    ),
    JSON_OBJECT(
      'icon', 'bi-headset',
      'titulo', 'Soporte especializado',
      'texto', 'Acompañamiento técnico durante la configuración y soporte prioritario según tu plan.'
    )
  )
),
    `orden` = 3,
    `activo` = 1
WHERE `pagina` = 'home' AND `clave` = 'features' AND `deleted` = 0;

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT 'home', 'faq',
  JSON_OBJECT(
    'titulo', 'Preguntas frecuentes',
    'lead', 'Respuestas rápidas sobre la API WhatsApp Business de Lebytek.',
    'items', JSON_ARRAY(
      JSON_OBJECT(
        'pregunta', '¿Qué es la API WhatsApp Business de Lebytek?',
        'respuesta', ''
      ),
      JSON_OBJECT(
        'pregunta', '¿Cuánto tarda en activarse la demo?',
        'respuesta', ''
      ),
      JSON_OBJECT(
        'pregunta', '¿Puedo conectar mi CRM o sistema interno?',
        'respuesta', ''
      ),
      JSON_OBJECT(
        'pregunta', '¿Los planes incluyen acompañamiento técnico?',
        'respuesta', ''
      ),
      JSON_OBJECT(
        'pregunta', '¿Puedo automatizar campañas y notificaciones?',
        'respuesta', ''
      ),
      JSON_OBJECT(
        'pregunta', '¿Sirve para negocios en México?',
        'respuesta', ''
      )
    )
  ),
  5, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'faq' AND `deleted` = 0
);

-- Si el bloque FAQ ya existía (p. ej. editado a mano), no tocamos las respuestas.
-- Solo reordenamos testimonios/footer para dejar espacio visual.
UPDATE `dom_mkt_bloques`
SET `orden` = 4
WHERE `pagina` = 'home' AND `clave` = 'testimonios' AND `deleted` = 0 AND `orden` = 5;

UPDATE `dom_mkt_bloques`
SET `orden` = 6
WHERE `pagina` = 'home' AND `clave` = 'footer' AND `deleted` = 0 AND `orden` < 6;

UPDATE `dom_mkt_bloques`
SET `contenido` = JSON_SET(
  `contenido`,
  '$.columnas[0].links',
  JSON_ARRAY(
    JSON_OBJECT('texto', 'Paquetes', 'url', '#paquetes'),
    JSON_OBJECT('texto', 'FAQ', 'url', '#faq'),
    JSON_OBJECT('texto', 'Demo', 'url', '#demo'),
    JSON_OBJECT('texto', 'Acceder', 'url', '/login')
  )
)
WHERE `pagina` = 'home' AND `clave` = 'footer' AND `deleted` = 0;
