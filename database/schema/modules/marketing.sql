-- database/schema/modules/marketing.sql
-- Bootstrap del módulo Marketing y Contenido Público.
-- Ejecutado solo cuando el wizard/instalador selecciona el módulo marketing.
-- Crea tablas dom_mkt_*, permisos RBAC, menú y datos demo genéricos. Idempotente.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `dom_mkt_leads` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(150)    NOT NULL,
  `email`         VARCHAR(190)    NOT NULL,
  `telefono`      VARCHAR(40)     DEFAULT NULL,
  `mensaje`       TEXT            DEFAULT NULL,
  `estado`        VARCHAR(30)     NOT NULL DEFAULT 'pendiente',
  `utm_source`    VARCHAR(120)    DEFAULT NULL,
  `utm_medium`    VARCHAR(120)    DEFAULT NULL,
  `utm_campaign`  VARCHAR(120)    DEFAULT NULL,
  `api_tenant_public_id` CHAR(26) NULL,
  `external_ref`  VARCHAR(255)    DEFAULT NULL,
  `api_provisioned_at` DATETIME   DEFAULT NULL,
  `api_provision_error` TEXT       DEFAULT NULL,
  `deleted`       TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`    BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`    DATETIME        DEFAULT NULL,
  `updated_by`    BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`    DATETIME        DEFAULT NULL,
  `deleted_by`    BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_leads_estado` (`estado`),
  KEY `idx_mkt_leads_deleted` (`deleted`),
  UNIQUE KEY `dom_mkt_leads_api_tenant_public_id_unique` (`api_tenant_public_id`),
  UNIQUE KEY `dom_mkt_leads_external_ref_unique` (`external_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_provisiones` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id`       BIGINT UNSIGNED DEFAULT NULL,
  `access_token`  CHAR(64)        DEFAULT NULL,
  `expira_en`     DATETIME        DEFAULT NULL,
  `estado`        VARCHAR(30)     NOT NULL DEFAULT 'pendiente',
  `payload`       JSON            DEFAULT NULL,
  `deleted`       TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`    BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`    DATETIME        DEFAULT NULL,
  `updated_by`    BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`    DATETIME        DEFAULT NULL,
  `deleted_by`    BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mkt_prov_token` (`access_token`),
  KEY `idx_mkt_prov_lead` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_paquetes` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`         VARCHAR(150)    NOT NULL,
  `precio_mensual` DECIMAL(10,2)   DEFAULT NULL,
  `precio_anual`   DECIMAL(10,2)   DEFAULT NULL,
  `features`       JSON            DEFAULT NULL,
  `destacado`      TINYINT(1)      NOT NULL DEFAULT 0,
  `badge`          VARCHAR(60)     DEFAULT NULL,
  `orden`          INT             NOT NULL DEFAULT 0,
  `activo`         TINYINT(1)      NOT NULL DEFAULT 1,
  `deleted`        TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`     BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`     DATETIME        DEFAULT NULL,
  `updated_by`     BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`     DATETIME        DEFAULT NULL,
  `deleted_by`     BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_paquetes_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_bloques` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pagina`      VARCHAR(120)    NOT NULL DEFAULT 'home',
  `clave`       VARCHAR(120)    NOT NULL,
  `contenido`   JSON            DEFAULT NULL,
  `orden`       INT             NOT NULL DEFAULT 0,
  `activo`      TINYINT(1)      NOT NULL DEFAULT 1,
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_bloques_pagina` (`pagina`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_plantillas` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave`       VARCHAR(120)    NOT NULL,
  `asunto`      VARCHAR(255)    NOT NULL,
  `cuerpo`      MEDIUMTEXT      NOT NULL,
  `activo`      TINYINT(1)      NOT NULL DEFAULT 1,
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_plantillas_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_secuencias` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(150)    NOT NULL,
  `pasos`       JSON            DEFAULT NULL,
  `activo`      TINYINT(1)      NOT NULL DEFAULT 1,
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_paginas` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(160)    NOT NULL,
  `titulo`      VARCHAR(200)    NOT NULL,
  `layout`      VARCHAR(60)     NOT NULL DEFAULT 'default',
  `publicada`   TINYINT(1)      NOT NULL DEFAULT 0,
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mkt_paginas_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Permisos RBAC ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver marketing',       'marketing.ver',       'marketing', 'Acceso de lectura al módulo de marketing'),
('Crear en marketing',  'marketing.crear',     'marketing', 'Crear contenido/paquetes/plantillas'),
('Editar en marketing', 'marketing.editar',    'marketing', 'Editar contenido/paquetes/plantillas'),
('Eliminar en marketing','marketing.eliminar', 'marketing', 'Eliminar lógico en marketing'),
('Gestionar marketing', 'marketing.gestionar', 'marketing', 'Gestionar ajustes del módulo de marketing'),
('Gestionar leads',     'marketing.leads',     'marketing', 'Gestionar la bandeja de leads'),
('Publicar contenido',  'marketing.publicar',  'marketing', 'Publicar páginas y contenido público');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'marketing.ver','marketing.crear','marketing.editar','marketing.eliminar',
  'marketing.gestionar','marketing.leads','marketing.publicar'
)
WHERE `r`.`slug` = 'administrador';

-- ── Menú dinámico ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
VALUES
(NULL, 80, 'marketing', 'Marketing', 'bi-megaphone', NULL, '/admin/crud/mkt_', 'marketing.ver', 'marketing', 1);

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 1, 'marketing-leads', 'Leads', 'bi-people', '/admin/crud/mkt_leads', '/admin/crud/mkt_leads', 'marketing.leads', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 2, 'marketing-paquetes', 'Paquetes', 'bi-box-seam', '/admin/crud/mkt_paquetes', '/admin/crud/mkt_paquetes', 'marketing.ver', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 3, 'marketing-bloques', 'Contenido', 'bi-layout-text-window', '/admin/crud/mkt_bloques', '/admin/crud/mkt_bloques', 'marketing.publicar', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 4, 'marketing-plantillas', 'Plantillas correo', 'bi-envelope-paper', '/admin/crud/mkt_plantillas', '/admin/crud/mkt_plantillas', 'marketing.gestionar', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing';

-- ── Datos demo (genéricos, idempotentes) ──────────────────────────────────────
INSERT INTO `dom_mkt_paquetes` (`nombre`, `precio_mensual`, `precio_anual`, `features`, `destacado`, `badge`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'Starter' AS nombre, 499.00 AS precio_mensual, 4990.00 AS precio_anual,
         JSON_ARRAY('1 instancia WhatsApp','Hasta 2 usuarios','500 mensajes/mes','Soporte por correo') AS features,
         0 AS destacado, NULL AS badge, 1 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes`);

INSERT INTO `dom_mkt_paquetes` (`nombre`, `precio_mensual`, `precio_anual`, `features`, `destacado`, `badge`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'Business' AS nombre, 999.00 AS precio_mensual, 9990.00 AS precio_anual,
         JSON_ARRAY('3 instancias WhatsApp','Hasta 10 usuarios','5 000 mensajes/mes','Campañas + plantillas','Soporte prioritario') AS features,
         1 AS destacado, 'Más popular' AS badge, 2 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `nombre` = 'Business');

INSERT INTO `dom_mkt_paquetes` (`nombre`, `precio_mensual`, `precio_anual`, `features`, `destacado`, `badge`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'Enterprise' AS nombre, NULL AS precio_mensual, NULL AS precio_anual,
         JSON_ARRAY('Instancias ilimitadas','Usuarios a medida','Volumen personalizado','SLA dedicado','Integración API') AS features,
         0 AS destacado, NULL AS badge, 3 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `nombre` = 'Enterprise');

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'home' AS pagina, 'hero' AS clave,
         JSON_OBJECT('badge','WhatsApp Business API','titulo','Automatiza WhatsApp para tu negocio','subtitulo','Campañas, respuestas y demo en minutos. Conecta tu equipo con clientes donde ya conversan.','cta_texto','Solicitar demo gratis','cta_url','#demo','cta2_texto','Ver paquetes','cta2_url','#paquetes','media',JSON_OBJECT('img','/assets/publico/hero-dashboard.svg','alt','Panel de conversaciones WhatsApp')) AS contenido,
         1 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `clave` = 'hero');

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'home', 'trust',
         JSON_OBJECT('items', JSON_ARRAY(
           JSON_OBJECT('valor','10k+','etiqueta','Mensajes al mes'),
           JSON_OBJECT('valor','99.9%','etiqueta','Disponibilidad'),
           JSON_OBJECT('valor','< 5 min','etiqueta','Demo activa'),
           JSON_OBJECT('valor','24/7','etiqueta','Soporte técnico')
         )), 2, 1
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `clave` = 'trust');

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'home', 'features',
         JSON_OBJECT('titulo','Todo lo que necesitas en un solo lugar','lead','Desde la primera demo hasta campañas masivas, sin complicaciones técnicas.','items',JSON_ARRAY(
           JSON_OBJECT('icon','bi-chat-dots-fill','titulo','Bandeja unificada','texto','Centraliza conversaciones de WhatsApp con tu equipo en un panel claro.'),
           JSON_OBJECT('icon','bi-send-check-fill','titulo','Campañas masivas','texto','Envía promociones y avisos con plantillas aprobadas y seguimiento en tiempo real.'),
           JSON_OBJECT('icon','bi-lightning-charge-fill','titulo','Demo instantánea','texto','Tras tu solicitud, activamos una instancia de prueba con credenciales por correo.'),
           JSON_OBJECT('icon','bi-shield-lock-fill','titulo','Seguro y escalable','texto','Infraestructura multi-tenant, colas Redis y API oficial Green.'),
           JSON_OBJECT('icon','bi-graph-up-arrow','titulo','Métricas claras','texto','Estados de entrega, respuestas y rendimiento de campañas en un vistazo.'),
           JSON_OBJECT('icon','bi-headset','titulo','Acompañamiento','texto','Onboarding guiado y soporte humano para arrancar sin fricción.')
         )), 3, 1
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `clave` = 'features');

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'home', 'testimonios',
         JSON_OBJECT('items', JSON_ARRAY(
           JSON_OBJECT('texto','En una semana teníamos campañas corriendo y el equipo respondiendo desde el mismo panel.','autor','María G. — Retail'),
           JSON_OBJECT('texto','La demo nos convenció al instante. El flujo de solicitud a credenciales fue impecable.','autor','Carlos R. — Servicios'),
           JSON_OBJECT('texto','Pasamos de chats dispersos a un proceso ordenado. El soporte de Lebytek fue clave.','autor','Ana L. — Clínica')
         )), 5, 1
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `clave` = 'testimonios');

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'home', 'footer',
         JSON_OBJECT('legal','Plataforma de mensajería WhatsApp Business para equipos en México.','columnas',JSON_ARRAY(
           JSON_OBJECT('titulo','Producto','links',JSON_ARRAY(JSON_OBJECT('texto','Paquetes','url','#paquetes'),JSON_OBJECT('texto','Demo','url','#demo'),JSON_OBJECT('texto','Acceder','url','/login'))),
           JSON_OBJECT('titulo','Empresa','links',JSON_ARRAY(JSON_OBJECT('texto','Contacto','url','#demo'),JSON_OBJECT('texto','Soporte','url','mailto:soporte@lebytek.com')))
         )), 6, 1
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `clave` = 'footer');

INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT * FROM (
  SELECT 'lead_autoresponder' AS clave, 'Recibimos tu solicitud — WhatsApp API para tu negocio' AS asunto,
         'Plantilla HTML en app/Presentation/Views/emails/lead_welcome.php' AS cuerpo, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas`);

SET FOREIGN_KEY_CHECKS = 1;
