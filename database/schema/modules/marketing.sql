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
  `email_verify_token` VARCHAR(64) NULL,
  `email_verify_code_hash` VARCHAR(64) NULL,
  `email_verify_expires_at` DATETIME NULL,
  `email_verified_at` DATETIME NULL,
  `email_verify_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `utm_source`    VARCHAR(120)    DEFAULT NULL,
  `utm_medium`    VARCHAR(120)    DEFAULT NULL,
  `utm_campaign`  VARCHAR(120)    DEFAULT NULL,
  `landing_variant` VARCHAR(40)   DEFAULT NULL,
  `visitor_id`    CHAR(36)        DEFAULT NULL,
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
  KEY `idx_mkt_leads_landing_variant` (`landing_variant`, `created_at`),
  UNIQUE KEY `dom_mkt_leads_api_tenant_public_id_unique` (`api_tenant_public_id`),
  UNIQUE KEY `dom_mkt_leads_external_ref_unique` (`external_ref`),
  UNIQUE KEY `uq_mkt_leads_email_verify_token` (`email_verify_token`)
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
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`              VARCHAR(150)    NOT NULL,
  `slug`                VARCHAR(50)     DEFAULT NULL,
  `precio_mensual`      DECIMAL(10,2)   DEFAULT NULL,
  `precio_anual`        DECIMAL(10,2)   DEFAULT NULL,
  `features`            JSON            DEFAULT NULL,
  `mensajes_mes_limite` INT UNSIGNED    DEFAULT NULL,
  `demo_dias`           INT UNSIGNED    DEFAULT NULL,
  `destacado`           TINYINT(1)      NOT NULL DEFAULT 0,
  `badge`               VARCHAR(60)     DEFAULT NULL,
  `orden`               INT             NOT NULL DEFAULT 0,
  `activo`              TINYINT(1)      NOT NULL DEFAULT 1,
  `deleted`             TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`          BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`          DATETIME        DEFAULT NULL,
  `updated_by`          BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`          DATETIME        DEFAULT NULL,
  `deleted_by`          BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dom_mkt_paquetes_slug_unique` (`slug`),
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
  UNIQUE KEY `uq_mkt_plantillas_clave` (`clave`)
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

CREATE TABLE IF NOT EXISTS `dom_mkt_ordenes` (
  `id`                              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`                       CHAR(26)        NOT NULL,
  `paquete_id`                      BIGINT UNSIGNED NOT NULL,
  `paquete_slug`                    VARCHAR(50)     NOT NULL,
  `ciclo`                           VARCHAR(20)     NOT NULL,
  `precio_snapshot`                 DECIMAL(10,2)   NOT NULL,
  `mensajes_mes_limite_snapshot`    INT UNSIGNED    DEFAULT NULL,
  `nombre`                          VARCHAR(150)    NOT NULL,
  `email`                           VARCHAR(190)    NOT NULL,
  `telefono`                        VARCHAR(40)     NOT NULL,
  `empresa`                         VARCHAR(190)    NOT NULL,
  `direccion`                       VARCHAR(255)    NOT NULL,
  `rfc`                             VARCHAR(20)     DEFAULT NULL,
  `lead_id`                         BIGINT UNSIGNED DEFAULT NULL,
  `api_tenant_public_id`            CHAR(26)        DEFAULT NULL,
  `status`                          VARCHAR(30)     NOT NULL DEFAULT 'pending_transfer',
  `transfer_notified_at`            DATETIME        DEFAULT NULL,
  `authorized_at`                   DATETIME        DEFAULT NULL,
  `authorized_by`                   BIGINT UNSIGNED DEFAULT NULL,
  `api_activation_error`            TEXT            DEFAULT NULL,
  `deleted`                         TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`                      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`                      BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`                      DATETIME        DEFAULT NULL,
  `updated_by`                      BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`                      DATETIME        DEFAULT NULL,
  `deleted_by`                      BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mkt_ordenes_public_id` (`public_id`),
  KEY `idx_mkt_ordenes_status` (`status`),
  KEY `idx_mkt_ordenes_email` (`email`),
  KEY `idx_mkt_ordenes_deleted` (`deleted`),
  KEY `idx_mkt_ordenes_lead` (`lead_id`),
  KEY `idx_mkt_ordenes_paquete` (`paquete_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_variant_weights` (
  `slug`       VARCHAR(40)     NOT NULL,
  `weight`     DECIMAL(8,4)    NOT NULL DEFAULT 0,
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_variant_proposals` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `status`      VARCHAR(20)     NOT NULL DEFAULT 'pending',
  `payload`     JSON            NOT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME        DEFAULT NULL,
  `resolved_by` BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_var_prop_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_landing_sessions` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`       CHAR(36)        NOT NULL,
  `visitor_id`      CHAR(36)        NOT NULL,
  `variant_slug`    VARCHAR(40)     NOT NULL,
  `is_preview`      TINYINT(1)      NOT NULL DEFAULT 0,
  `duration_ms`     INT UNSIGNED    NOT NULL DEFAULT 0,
  `max_scroll_pct`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `exit_section`    VARCHAR(60)     DEFAULT NULL,
  `first_seen_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mkt_land_sess_public` (`public_id`),
  KEY `idx_mkt_land_sess_visitor` (`visitor_id`),
  KEY `idx_mkt_land_sess_variant` (`variant_slug`, `is_preview`, `first_seen_at`),
  KEY `idx_mkt_land_sess_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_landing_events` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`   BIGINT UNSIGNED DEFAULT NULL,
  `visitor_id`   CHAR(36)        NOT NULL,
  `variant_slug` VARCHAR(40)     NOT NULL,
  `event_type`   VARCHAR(40)     NOT NULL,
  `meta`         JSON            DEFAULT NULL,
  `is_preview`   TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_land_evt_var` (`variant_slug`, `is_preview`, `created_at`),
  KEY `idx_mkt_land_evt_type` (`event_type`),
  KEY `idx_mkt_land_evt_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Permisos RBAC ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver marketing',       'marketing.ver',       'marketing', 'Acceso de lectura al módulo de marketing'),
('Crear en marketing',  'marketing.crear',     'marketing', 'Crear contenido/paquetes/plantillas'),
('Editar en marketing', 'marketing.editar',    'marketing', 'Editar contenido/paquetes/plantillas'),
('Eliminar en marketing','marketing.eliminar', 'marketing', 'Eliminar lógico en marketing'),
('Gestionar marketing', 'marketing.gestionar', 'marketing', 'Gestionar ajustes del módulo de marketing'),
('Gestionar leads',     'marketing.leads',     'marketing', 'Gestionar la bandeja de leads'),
('Publicar contenido',  'marketing.publicar',  'marketing', 'Publicar páginas y contenido público'),
('Autorizar órdenes',   'marketing.ordenes',   'marketing', 'Autorizar órdenes de membresía'),
('Experimentos landing','marketing.experimentos','marketing','Ver métricas y aceptar/rechazar propuestas de peso');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'marketing.ver','marketing.crear','marketing.editar','marketing.eliminar',
  'marketing.gestionar','marketing.leads','marketing.publicar','marketing.ordenes',
  'marketing.experimentos'
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

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 5, 'marketing-ordenes', 'Órdenes', 'bi-receipt', '/admin/crud/mkt_ordenes', '/admin/crud/mkt_ordenes', 'marketing.ordenes', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 6, 'marketing-experimentos', 'Experimentos', 'bi-graph-up', '/admin/marketing/experimentos', '/admin/marketing/experimentos', 'marketing.experimentos', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing';

-- ── Datos demo (genéricos, idempotentes) ──────────────────────────────────────
INSERT INTO `dom_mkt_paquetes` (`nombre`, `slug`, `precio_mensual`, `precio_anual`, `features`, `mensajes_mes_limite`, `demo_dias`, `destacado`, `badge`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'Demo' AS nombre, 'demo' AS slug, 0.00 AS precio_mensual, 0.00 AS precio_anual,
         JSON_ARRAY('100 mensajes/mes', '30 días de prueba', '1 número WhatsApp') AS features,
         100 AS mensajes_mes_limite, 30 AS demo_dias,
         0 AS destacado, NULL AS badge, 0 AS orden, 0 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `slug` = 'demo');

INSERT INTO `dom_mkt_paquetes` (`nombre`, `slug`, `precio_mensual`, `precio_anual`, `features`, `mensajes_mes_limite`, `demo_dias`, `destacado`, `badge`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'Starter' AS nombre, 'starter' AS slug, 2199.00 AS precio_mensual, 21990.00 AS precio_anual,
         JSON_ARRAY('1 instancia WhatsApp', 'Hasta ~5000 mensajes/mes', 'Soporte por correo') AS features,
         5000 AS mensajes_mes_limite, NULL AS demo_dias,
         0 AS destacado, NULL AS badge, 1 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `slug` = 'starter');

INSERT INTO `dom_mkt_paquetes` (`nombre`, `slug`, `precio_mensual`, `precio_anual`, `features`, `mensajes_mes_limite`, `demo_dias`, `destacado`, `badge`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'Business' AS nombre, 'business' AS slug, 4499.00 AS precio_mensual, 44990.00 AS precio_anual,
         JSON_ARRAY('Hasta 3 instancias WhatsApp', 'Hasta ~80000 mensajes/mes', 'Campañas + plantillas', 'Soporte prioritario') AS features,
         80000 AS mensajes_mes_limite, NULL AS demo_dias,
         1 AS destacado, 'Más popular' AS badge, 2 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `slug` = 'business');

INSERT INTO `dom_mkt_paquetes` (`nombre`, `slug`, `precio_mensual`, `precio_anual`, `features`, `mensajes_mes_limite`, `demo_dias`, `destacado`, `badge`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'Enterprise' AS nombre, 'empresa' AS slug, NULL AS precio_mensual, NULL AS precio_anual,
         JSON_ARRAY('Instancias a medida', 'Volumen personalizado', 'SLA dedicado', 'Integración API') AS features,
         NULL AS mensajes_mes_limite, NULL AS demo_dias,
         0 AS destacado, NULL AS badge, 3 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `slug` = 'empresa');

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'home' AS pagina, 'hero' AS clave,
         JSON_OBJECT('badge','WhatsApp Business API','titulo','Automatiza WhatsApp para tu negocio con la API de Lebytek','subtitulo','Envía campañas masivas, respuestas automáticas y notificaciones desde tu sistema. Conecta tu CRM o software en minutos y conversa donde tus clientes ya están.','cta_texto','Solicitar demo gratis','cta_url','#demo','cta2_texto','Ver paquetes','cta2_url','#paquetes','media',JSON_OBJECT('img','/assets/publico/hero-dashboard.svg','alt','Panel de conversaciones WhatsApp')) AS contenido,
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
         JSON_OBJECT('titulo','Integra WhatsApp Business API sin complicaciones técnicas','lead','Conecta tu aplicación, CRM o procesos internos mediante una URL segura y token de acceso. Implementación en minutos.','items',JSON_ARRAY(
           JSON_OBJECT('icon','bi-plug-fill','titulo','API lista para conectar','texto','Envía y recibe mensajes de WhatsApp directamente desde tu sistema usando una URL y token de autenticación simple.'),
           JSON_OBJECT('icon','bi-send-check-fill','titulo','Mensajes automatizados','texto','Configura confirmaciones de pedidos, recordatorios de citas, promociones y notificaciones transaccionales de forma automática.'),
           JSON_OBJECT('icon','bi-shield-lock-fill','titulo','Acceso seguro por token','texto','Cada instancia tiene sus propias credenciales. Tú controlas el uso y evitas gastos inesperados.'),
           JSON_OBJECT('icon','bi-lightning-charge-fill','titulo','Implementación en minutos','texto','Recibe tus credenciales en minutos y comienza a probar la API sin desarrollar integraciones complejas.'),
           JSON_OBJECT('icon','bi-graph-up-arrow','titulo','Escalable según tu crecimiento','texto','Comienza con una línea de WhatsApp y agrega más instancias conforme crezca tu volumen de mensajes.'),
           JSON_OBJECT('icon','bi-headset','titulo','Soporte especializado','texto','Acompañamiento técnico durante la configuración y soporte prioritario según tu plan.')
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
         )), 4, 1
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `clave` = 'testimonios');

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'home', 'faq',
         JSON_OBJECT('titulo','Preguntas frecuentes','lead','Respuestas rápidas sobre la API WhatsApp Business de Lebytek.','items',JSON_ARRAY(
           JSON_OBJECT('pregunta','¿Qué es la API WhatsApp Business de Lebytek?','respuesta',''),
           JSON_OBJECT('pregunta','¿Cuánto tarda en activarse la demo?','respuesta',''),
           JSON_OBJECT('pregunta','¿Puedo conectar mi CRM o sistema interno?','respuesta',''),
           JSON_OBJECT('pregunta','¿Los planes incluyen acompañamiento técnico?','respuesta',''),
           JSON_OBJECT('pregunta','¿Puedo automatizar campañas y notificaciones?','respuesta',''),
           JSON_OBJECT('pregunta','¿Sirve para negocios en México?','respuesta','')
         )), 5, 1
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `clave` = 'faq');

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'home', 'footer',
         JSON_OBJECT('legal','Plataforma de mensajería WhatsApp Business para equipos en México.','columnas',JSON_ARRAY(
           JSON_OBJECT('titulo','Producto','links',JSON_ARRAY(JSON_OBJECT('texto','Paquetes','url','#paquetes'),JSON_OBJECT('texto','FAQ','url','#faq'),JSON_OBJECT('texto','Demo','url','#demo'),JSON_OBJECT('texto','Acceder','url','/login'))),
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
