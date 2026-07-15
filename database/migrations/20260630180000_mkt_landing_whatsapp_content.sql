-- Enriquece la landing pública con contenido WhatsApp Business (Lebytek).
-- Idempotente: actualiza bloques existentes e inserta los faltantes.

UPDATE `dom_mkt_bloques`
SET `contenido` = JSON_OBJECT(
  'badge', 'WhatsApp Business API',
  'titulo', 'Automatiza WhatsApp para tu negocio',
  'subtitulo', 'Campañas, respuestas y demo en minutos. Conecta tu equipo con clientes donde ya conversan.',
  'cta_texto', 'Solicitar demo gratis',
  'cta_url', '#demo',
  'cta2_texto', 'Ver paquetes',
  'cta2_url', '#paquetes',
  'media', JSON_OBJECT('img', '/assets/publico/hero-dashboard.svg', 'alt', 'Panel de conversaciones WhatsApp')
),
    `orden` = 1,
    `activo` = 1
WHERE `pagina` = 'home' AND `clave` = 'hero' AND `deleted` = 0;

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT 'home', 'trust',
  JSON_OBJECT('items', JSON_ARRAY(
    JSON_OBJECT('valor', '10k+', 'etiqueta', 'Mensajes al mes'),
    JSON_OBJECT('valor', '99.9%', 'etiqueta', 'Disponibilidad'),
    JSON_OBJECT('valor', '< 5 min', 'etiqueta', 'Demo activa'),
    JSON_OBJECT('valor', '24/7', 'etiqueta', 'Soporte técnico')
  )),
  2, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'trust' AND `deleted` = 0
);

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT 'home', 'features',
  JSON_OBJECT('titulo', 'Todo lo que necesitas en un solo lugar', 'lead', 'Desde la primera demo hasta campañas masivas, sin complicaciones técnicas.', 'items', JSON_ARRAY(
    JSON_OBJECT('icon', 'bi-chat-dots-fill', 'titulo', 'Bandeja unificada', 'texto', 'Centraliza conversaciones de WhatsApp con tu equipo en un panel claro.'),
    JSON_OBJECT('icon', 'bi-send-check-fill', 'titulo', 'Campañas masivas', 'texto', 'Envía promociones y avisos con plantillas aprobadas y seguimiento en tiempo real.'),
    JSON_OBJECT('icon', 'bi-lightning-charge-fill', 'titulo', 'Demo instantánea', 'texto', 'Tras tu solicitud, activamos una instancia de prueba con credenciales por correo.'),
    JSON_OBJECT('icon', 'bi-shield-lock-fill', 'titulo', 'Seguro y escalable', 'texto', 'Infraestructura multi-tenant, colas Redis y API oficial Green.'),
    JSON_OBJECT('icon', 'bi-graph-up-arrow', 'titulo', 'Métricas claras', 'texto', 'Estados de entrega, respuestas y rendimiento de campañas en un vistazo.'),
    JSON_OBJECT('icon', 'bi-headset', 'titulo', 'Acompañamiento', 'texto', 'Onboarding guiado y soporte humano para arrancar sin fricción.')
  )),
  3, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'features' AND `deleted` = 0
);

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT 'home', 'testimonios',
  JSON_OBJECT('items', JSON_ARRAY(
    JSON_OBJECT('texto', 'En una semana teníamos campañas corriendo y el equipo respondiendo desde el mismo panel.', 'autor', 'María G. — Retail'),
    JSON_OBJECT('texto', 'La demo nos convenció al instante. El flujo de solicitud a credenciales fue impecable.', 'autor', 'Carlos R. — Servicios'),
    JSON_OBJECT('texto', 'Pasamos de chats dispersos a un proceso ordenado. El soporte de Lebytek fue clave.', 'autor', 'Ana L. — Clínica')
  )),
  5, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'testimonios' AND `deleted` = 0
);

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT 'home', 'footer',
  JSON_OBJECT(
    'legal', 'Plataforma de mensajería WhatsApp Business para equipos en México.',
    'columnas', JSON_ARRAY(
      JSON_OBJECT('titulo', 'Producto', 'links', JSON_ARRAY(
        JSON_OBJECT('texto', 'Paquetes', 'url', '#paquetes'),
        JSON_OBJECT('texto', 'Demo', 'url', '#demo'),
        JSON_OBJECT('texto', 'Acceder', 'url', '/login')
      )),
      JSON_OBJECT('titulo', 'Empresa', 'links', JSON_ARRAY(
        JSON_OBJECT('texto', 'Contacto', 'url', '#demo'),
        JSON_OBJECT('texto', 'Soporte', 'url', 'mailto:soporte@lebytek.com')
      ))
    )
  ),
  6, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'footer' AND `deleted` = 0
);

INSERT INTO `dom_mkt_paquetes` (`nombre`, `precio_mensual`, `precio_anual`, `features`, `destacado`, `badge`, `orden`, `activo`)
SELECT 'Starter', 499.00, 4990.00,
  JSON_ARRAY('1 instancia WhatsApp', 'Hasta 2 usuarios', '500 mensajes/mes', 'Soporte por correo'),
  0, NULL, 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `nombre` = 'Starter' AND `deleted` = 0);

UPDATE `dom_mkt_paquetes`
SET `nombre` = 'Business',
    `precio_mensual` = 999.00,
    `precio_anual` = 9990.00,
    `features` = JSON_ARRAY('3 instancias WhatsApp', 'Hasta 10 usuarios', '5 000 mensajes/mes', 'Campañas + plantillas', 'Soporte prioritario'),
    `destacado` = 1,
    `badge` = 'Más popular',
    `orden` = 2,
    `activo` = 1
WHERE `nombre` = 'Plan Demo' AND `deleted` = 0;

INSERT INTO `dom_mkt_paquetes` (`nombre`, `precio_mensual`, `precio_anual`, `features`, `destacado`, `badge`, `orden`, `activo`)
SELECT 'Enterprise', NULL, NULL,
  JSON_ARRAY('Instancias ilimitadas', 'Usuarios a medida', 'Volumen personalizado', 'SLA dedicado', 'Integración API'),
  0, NULL, 3, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `nombre` = 'Enterprise' AND `deleted` = 0);
