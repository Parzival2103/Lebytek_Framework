# Cimientos del módulo "Marketing y Contenido Público" — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir los cimientos desacoplables del módulo Marketing (esqueleto + registro + rutas públicas + integración con Ajustes + contratos de extensión + CRUDs de contenido + captación de leads + portal cliente genérico), sin portar el dominio WhatsApp del ejemplo.

**Architecture:** El módulo sigue la convención de `reportes`/`calendario`: código distribuido por capas Onion con sub-namespace `Marketing`, un manifiesto en `config/modules/marketing.php`, un toggle `modules.marketing` en `config/vertical.php`, y registro condicional (rutas, bindings, providers) que deja el módulo inerte si el toggle está apagado. El núcleo nunca referencia clases de Marketing: el acoplamiento es siempre inverso (Marketing implementa interfaces del núcleo) o por registro declarativo.

**Tech Stack:** PHP 8.1+, MVC+Onion propio, Router custom, Container DI custom, CRUD Engine, PhpMailerMailer, microtest harness (`tests/run.php`).

## Global Constraints

- **PHP:** 8.1+, `declare(strict_types=1);` en todo archivo PHP nuevo.
- **Prefijos de tabla obligatorios:** contenido del módulo = `dom_mkt_*`; settings = `cfg_*` con claves namespaced `mkt_*`. Nunca tablas sin prefijo.
- **Sin FKs cross-módulo** hacia tablas del núcleo (salvo el patrón `created_by` como columna suelta, sin constraint).
- **Regla de desacople:** ningún archivo bajo `app/Kernel`, `app/Domain` (salvo interfaces transversales nuevas), ni controladores/servicios del núcleo puede `use` una clase del namespace `App\…\Marketing`. Toggle off ⇒ cero rutas, cero bindings, cero menú, cero secciones de Ajustes.
- **Toggle por defecto `false`** durante todo el desarrollo de cimientos (riesgo de tocar `/` mitigado).
- **Naming:** clases PascalCase, métodos camelCase, slugs de permiso `marketing.accion`, claves de settings `mkt_snake_case`, rutas/vistas `snake_case.php`, JSON keys del CRUD según convención existente.
- **Tests:** harness propio. Crear `tests/Marketing/*Test.php` con `test()/assert_*`. Ejecutar con `php tests/run.php Marketing`. No usar PHPUnit (la suite real corre con `php tests/run.php`).
- **Idempotencia SQL:** todo `marketing.sql` usa `CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`, y demo-data guardada con `WHERE NOT EXISTS`.
- **Identidad del módulo:** clave `marketing`; nombre "Marketing y Contenido Público"; `requiere: ['core','crud-engine']`.

---

## File Structure

**Configuración / registro**
- `config/modules/marketing.php` — manifiesto (NUEVO)
- `config/vertical.php` — +1 línea toggle (MODIFICAR)
- `config/container.php` — bindings condicionales + registro de providers (MODIFICAR)
- `config/settings_sections.php` — lista declarativa de SettingsSectionProviders (NUEVO)
- `config/cruds/mkt_leads.json`, `mkt_paquetes.json`, `mkt_bloques.json`, `mkt_plantillas.json`, `mkt_secuencias.json` (NUEVOS)

**Base de datos**
- `database/schema/modules/marketing.sql` — bootstrap idempotente (NUEVO)

**Rutas**
- `routes/web.php` — include condicional + precedencia de `/` (MODIFICAR)
- `routes/marketing.php` — rutas públicas + admin del módulo (NUEVO)

**Domain (contratos)**
- `app/Domain/Marketing/Contracts/LandingContentProviderInterface.php`
- `app/Domain/Marketing/Contracts/CommercialPackageSourceInterface.php`
- `app/Domain/Marketing/Contracts/LeadCaptureHandlerInterface.php`
- `app/Domain/Marketing/Contracts/ProvisionAdapterInterface.php`
- `app/Domain/Marketing/Contracts/MarketingContentRepositoryInterface.php`
- `app/Domain/Marketing/ValueObjects/LeadDraft.php`, `LeadResult.php`, `Lead.php`, `Provision.php`, `ProvisionResult.php`, `MagicLinkToken.php`
- `app/Domain/Interfaces/SettingsSectionProviderInterface.php` — transversal (NUEVO, fuera de Marketing)

**Application**
- `app/Application/Services/SettingsSectionRegistry.php` — transversal (NUEVO)
- `app/Application/Marketing/CapturarLeadUseCase.php`
- `app/Application/Marketing/RenderLandingUseCase.php`

**Infrastructure**
- `app/Infrastructure/Repositories/PdoMarketingContentRepository.php`
- `app/Infrastructure/Marketing/CrudLandingContentProvider.php`
- `app/Infrastructure/Marketing/CrudCommercialPackageSource.php`
- `app/Infrastructure/Marketing/LeadCapture/PersistLeadHandler.php`, `NotifyInternalHandler.php`, `AutoresponderHandler.php`
- `app/Infrastructure/Marketing/Settings/MarketingCorreoSettingsProvider.php`, `MarketingPaquetesSettingsProvider.php`, `MarketingTrackingSettingsProvider.php`, `MarketingContenidoSettingsProvider.php`

**Presentation**
- `app/Presentation/Controllers/Publico/LandingController.php`
- `app/Presentation/Controllers/Publico/LeadController.php`
- `app/Presentation/Controllers/Publico/PortalClienteController.php`
- `app/Presentation/Controllers/Admin/AjustesController.php` — index/guardar extensibles (MODIFICAR)
- `app/Presentation/Views/publico/layout.php`, `landing.php`, `portal.php`
- `app/Presentation/Views/admin/ajustes/index.php` — render de secciones de providers (MODIFICAR)
- `app/Presentation/Views/admin/ajustes/_provider_section.php` (NUEVO partial)

**Tests** — `tests/Marketing/*Test.php`

---

## Phase 1 — Andamiaje inerte (Fase 4.1 del spec)

### Task 1: Manifiesto del módulo + toggle

**Files:**
- Create: `config/modules/marketing.php`
- Modify: `config/vertical.php` (añadir `'marketing' => false`)
- Test: `tests/Marketing/ManifestTest.php`

**Interfaces:**
- Consumes: `App\Application\Install\ModuleRegistry::get(string): ?ModuleManifest`, `ModuleManifest->{clave,version,requiere,cruds,bootstrapSql,permisos}`.
- Produces: manifiesto cargable con clave `marketing`, `requiere=['core','crud-engine']`, `bootstrap_sql='database/schema/modules/marketing.sql'`, `cruds=['mkt_leads','mkt_paquetes','mkt_bloques','mkt_plantillas','mkt_secuencias']`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/ManifestTest.php
declare(strict_types=1);

use App\Application\Install\ModuleRegistry;

test('marketing manifiesto se carga con identidad y dependencias correctas', function (): void {
    $registry = new ModuleRegistry(ROOT_PATH . '/config/modules');
    $m = $registry->get('marketing');
    assert_true($m !== null, 'manifiesto marketing existe');
    assert_same('marketing', $m->clave);
    assert_same('1.0.0', $m->version);
    assert_true(in_array('core', $m->requiere, true), 'requiere core');
    assert_true(in_array('crud-engine', $m->requiere, true), 'requiere crud-engine');
});

test('marketing manifiesto declara bootstrap_sql existente', function (): void {
    $registry = new ModuleRegistry(ROOT_PATH . '/config/modules');
    $m = $registry->get('marketing');
    assert_same('database/schema/modules/marketing.sql', $m->bootstrapSql);
});

test('marketing manifiesto declara los CRUDs de contenido', function (): void {
    $registry = new ModuleRegistry(ROOT_PATH . '/config/modules');
    $m = $registry->get('marketing');
    foreach (['mkt_leads','mkt_paquetes','mkt_bloques','mkt_plantillas','mkt_secuencias'] as $crud) {
        assert_true(in_array($crud, $m->cruds, true), "declara crud {$crud}");
    }
});

test('toggle marketing existe y por defecto está apagado', function (): void {
    $vertical = require ROOT_PATH . '/config/vertical.php';
    assert_true(array_key_exists('marketing', $vertical['modules']), 'toggle declarado');
    assert_same(false, $vertical['modules']['marketing'], 'apagado por defecto');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/ManifestTest`
Expected: FAIL — `manifiesto marketing existe` (get devuelve null) y `toggle declarado`.

- [ ] **Step 3: Create the manifest**

```php
<?php
// config/modules/marketing.php
declare(strict_types=1);

// Manifiesto del módulo Marketing y Contenido Público.
// Cimientos desacoplables: CMS público + captación de leads + paquetes + settings.
// Bootstrap (tablas dom_mkt_*, permisos, menú, demo) en schema/modules/marketing.sql.
return [
    'clave'         => 'marketing',
    'nombre'        => 'Marketing y Contenido Público',
    'descripcion'   => 'CMS público, captación de leads, paquetes y automatizaciones de correo.',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core', 'crud-engine'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/marketing.sql',
    'cruds'         => ['mkt_leads', 'mkt_paquetes', 'mkt_bloques', 'mkt_plantillas', 'mkt_secuencias'],
    'permisos'      => [
        'marketing.ver', 'marketing.crear', 'marketing.editar', 'marketing.eliminar',
        'marketing.gestionar', 'marketing.leads', 'marketing.publicar',
    ],
    'menu'          => [],
    'providers'     => [],
];
```

- [ ] **Step 4: Add the toggle**

In `config/vertical.php`, inside the `'modules' => [ ... ]` array, add the line after `'reportes' => true,`:

```php
        'reportes'       => true,
        'marketing'      => false,
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/run.php Marketing/ManifestTest`
Expected: PASS (4 passed).

- [ ] **Step 6: Commit**

```bash
git add config/modules/marketing.php config/vertical.php tests/Marketing/ManifestTest.php
git commit -m "feat(marketing): manifiesto y toggle del modulo (inerte por defecto)"
```

---

### Task 2: Bootstrap SQL (tablas, permisos, menú, demo)

**Files:**
- Create: `database/schema/modules/marketing.sql`
- Test: `tests/Marketing/SchemaBootstrapTest.php`

**Interfaces:**
- Produces: tablas `dom_mkt_leads`, `dom_mkt_provisiones`, `dom_mkt_paquetes`, `dom_mkt_bloques`, `dom_mkt_plantillas`, `dom_mkt_secuencias`, `dom_mkt_paginas`; permisos `marketing.{ver,crear,editar,eliminar,gestionar,leads,publicar}`; menú padre `marketing` + hijos de CRUD; demo (1 paquete, 1 bloque hero, 1 plantilla). Estas tablas/columnas son consumidas por los CRUD JSON (Task 11) y por `PdoMarketingContentRepository` (Task 12): `dom_mkt_bloques(pagina, clave, contenido JSON, orden, activo)`, `dom_mkt_paquetes(nombre, precio_mensual, precio_anual, features JSON, destacado, badge, orden, activo)`, `dom_mkt_leads(nombre,email,telefono,mensaje,estado,utm_source,utm_medium,utm_campaign,created_by)`, `dom_mkt_provisiones(lead_id,access_token,expira_en,estado,payload JSON)`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/SchemaBootstrapTest.php
declare(strict_types=1);

test('marketing.sql crea todas las tablas dom_mkt_* de forma idempotente', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true($sql !== false, 'archivo existe');
    foreach ([
        'dom_mkt_leads', 'dom_mkt_provisiones', 'dom_mkt_paquetes',
        'dom_mkt_bloques', 'dom_mkt_plantillas', 'dom_mkt_secuencias', 'dom_mkt_paginas',
    ] as $tabla) {
        assert_true(str_contains($sql, "CREATE TABLE IF NOT EXISTS `{$tabla}`"), "crea {$tabla}");
    }
});

test('marketing.sql inserta permisos y menú con INSERT IGNORE', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(str_contains($sql, "'marketing.ver'"), 'permiso ver');
    assert_true(str_contains($sql, "'marketing.gestionar'"), 'permiso gestionar');
    assert_true(str_contains($sql, "'marketing.leads'"), 'permiso leads');
    assert_true(str_contains($sql, 'INSERT IGNORE INTO `auth_permisos`'), 'permisos idempotentes');
    assert_true(str_contains($sql, 'INSERT IGNORE INTO `core_menu_items`'), 'menú idempotente');
    assert_true(str_contains($sql, "'marketing'"), 'menú padre marketing');
});

test('marketing.sql siembra demo guardada por NOT EXISTS', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(str_contains($sql, 'NOT EXISTS'), 'demo idempotente');
    assert_true(str_contains($sql, 'access_token'), 'columna magic-link presente');
    assert_true(str_contains($sql, '`payload`'), 'columna payload JSON presente');
});

test('marketing.sql no define FKs cross-módulo', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(!str_contains($sql, 'FOREIGN KEY'), 'sin FOREIGN KEY declaradas');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/SchemaBootstrapTest`
Expected: FAIL — `archivo existe` (file_get_contents devuelve false).

- [ ] **Step 3: Create the bootstrap SQL**

```sql
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
  `deleted`       TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`    BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`    DATETIME        DEFAULT NULL,
  `updated_by`    BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`    DATETIME        DEFAULT NULL,
  `deleted_by`    BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_leads_estado` (`estado`),
  KEY `idx_mkt_leads_deleted` (`deleted`)
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
  SELECT 'Plan Demo' AS nombre, 299.00 AS precio_mensual, 2990.00 AS precio_anual,
         JSON_ARRAY('Soporte por correo','Hasta 3 usuarios','Reportes básicos') AS features,
         1 AS destacado, 'Popular' AS badge, 1 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes`);

INSERT INTO `dom_mkt_bloques` (`pagina`, `clave`, `contenido`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'home' AS pagina, 'hero' AS clave,
         JSON_OBJECT('titulo','Tu negocio, en línea','subtitulo','Captura clientes con una landing lista para usar','cta_texto','Solicita una demo','cta_url','#demo') AS contenido,
         1 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques`);

INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)
SELECT * FROM (
  SELECT 'lead_autoresponder' AS clave, 'Gracias por tu interés' AS asunto,
         'Hola {{nombre}}, recibimos tu solicitud y te contactaremos pronto.' AS cuerpo, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas`);

SET FOREIGN_KEY_CHECKS = 1;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Marketing/SchemaBootstrapTest`
Expected: PASS (4 passed).

- [ ] **Step 5: Lint the SQL file is referenced & re-run full manifest test**

Run: `php tests/run.php Marketing`
Expected: PASS (Task 1 + Task 2 tests all green).

- [ ] **Step 6: Commit**

```bash
git add database/schema/modules/marketing.sql tests/Marketing/SchemaBootstrapTest.php
git commit -m "feat(marketing): bootstrap SQL idempotente (tablas, permisos, menu, demo)"
```

---

### Task 3: Remediación de seguridad del módulo legado

**Files:**
- Modify/Delete: `nuevo_modulo/config.example.php` (contiene credenciales reales)

**Interfaces:** N/A (tarea de seguridad). Hallazgo del spec §1: el archivo de ejemplo legado contiene password BD/SMTP y un hash bcrypt de admin reales.

- [ ] **Step 1: Confirm the leak**

Run: `php -r "echo file_exists('nuevo_modulo/config.example.php') ? 'EXISTE' : 'no';"`
Expected: `EXISTE` (si ya no existe, marcar la tarea como completada y saltar al commit de nota).

- [ ] **Step 2: Inspect the file for real credentials**

Read `nuevo_modulo/config.example.php`. Confirm presence of literal secrets (e.g. `Qazzaqwerrew1B`, bcrypt `$2y$...`).

- [ ] **Step 3: Replace real values with placeholders**

Overwrite the file so it contains only placeholders (`CHANGE_ME`, `your-smtp-host`, etc.) and no real password/hash. Preserve the structure/keys so it documents required config, but with zero real secrets.

- [ ] **Step 4: Verify no secret remains**

Run: `grep -R "Qazzaqwerrew1B" nuevo_modulo/ || echo "LIMPIO"`
Expected: `LIMPIO`.

- [ ] **Step 5: Record the rotation requirement**

Append a line to the design spec note (or create `docs/superpowers/specs/2026-06-23-modulo-marketing-cimientos-design.md` §1 follow-up) stating: "Credenciales filtradas reemplazadas por placeholders el 2026-06-23. ACCIÓN OPERATIVA PENDIENTE: rotar el password de BD/SMTP en los entornos donde se haya usado."

- [ ] **Step 6: Commit**

```bash
git add nuevo_modulo/config.example.php docs/superpowers/specs/2026-06-23-modulo-marketing-cimientos-design.md
git commit -m "security(marketing): purgar credenciales reales del config de ejemplo legado"
```

---

## Phase 2 — Rutas públicas + layout público + raíz condicional (Fase 4.2)

### Task 4: Archivo de rutas del módulo + include condicional + precedencia de `/`

**Files:**
- Create: `routes/marketing.php`
- Create: `app/Presentation/Controllers/Publico/LandingController.php` (versión estática inicial)
- Modify: `routes/web.php`
- Test: `tests/Marketing/RoutesWiringTest.php`

**Interfaces:**
- Consumes: `$router` (en scope desde Bootstrap), `\App\Kernel\Config\Config::get('vertical.modules.marketing', false)`, `App\Presentation\Middlewares\CsrfMiddleware`.
- Produces: `routes/marketing.php` registra `GET /` → `Publico\LandingController::index`. `routes/web.php` solo registra el `/` por defecto (login) cuando marketing está OFF. `LandingController::index(Request): Response` (estático por ahora; Task 12 lo conecta al provider).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/RoutesWiringTest.php
declare(strict_types=1);

test('web.php incluye marketing de forma condicional al toggle', function (): void {
    $web = file_get_contents(ROOT_PATH . '/routes/web.php');
    assert_true($web !== false);
    assert_true(str_contains($web, "vertical.modules.marketing"), 'lee el toggle');
    assert_true(str_contains($web, "routes/marketing.php"), 'incluye routes/marketing.php');
});

test('web.php registra el / por defecto SOLO si marketing está apagado', function (): void {
    $web = file_get_contents(ROOT_PATH . '/routes/web.php');
    // El default '/' debe estar dentro de un guard !$marketingActivo
    assert_true(str_contains($web, '$marketingActivo'), 'usa la bandera de toggle');
    assert_true(str_contains($web, "if (!\$marketingActivo)"), 'guarda el / por defecto');
});

test('routes/marketing.php registra la raíz pública hacia LandingController', function (): void {
    $mkt = file_get_contents(ROOT_PATH . '/routes/marketing.php');
    assert_true($mkt !== false);
    assert_true(str_contains($mkt, "LandingController"), 'apunta a LandingController');
    assert_true(str_contains($mkt, "->get('/'"), 'registra GET /');
});

test('LandingController es clase válida y tiene index', function (): void {
    assert_true(class_exists(\App\Presentation\Controllers\Publico\LandingController::class), 'clase existe');
    assert_true(method_exists(\App\Presentation\Controllers\Publico\LandingController::class, 'index'), 'tiene index');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/RoutesWiringTest`
Expected: FAIL — `routes/marketing.php` no existe / `LandingController` no existe.

- [ ] **Step 3: Create the static LandingController**

```php
<?php
// app/Presentation/Controllers/Publico/LandingController.php
declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Application\Services\ConfiguracionService;

final class LandingController extends BaseController
{
    public function __construct(
        private readonly ConfiguracionService $configuracionService
    ) {}

    public function index(Request $request): Response
    {
        return $this->view('publico/landing', [
            'empresaNombre' => $this->configuracionService->empresaNombre(),
            'empresaLogo'   => $this->configuracionService->empresaLogo(),
            'bloques'       => [],   // Task 12 lo reemplaza por el provider de contenido
            'paquetes'      => [],   // Task 12 lo reemplaza por el package source
        ], 'publico/layout');
    }
}
```

- [ ] **Step 4: Create the marketing routes file**

```php
<?php
// routes/marketing.php
// Rutas del módulo Marketing. Incluido condicionalmente desde routes/web.php
// solo cuando vertical.modules.marketing === true. Tiene $router en scope.

use App\Presentation\Controllers\Publico\LandingController;
use App\Presentation\Middlewares\CsrfMiddleware;

// Raíz pública: con el módulo activo, "/" sirve la landing (no el login).
$router->get('/', [LandingController::class, 'index']);

// (Task 13 añade aquí el POST público de captación con CsrfMiddleware.)
// (Task 14 añade aquí el portal cliente magic-link.)
```

- [ ] **Step 5: Wire web.php (conditional include + root precedence)**

In `routes/web.php`, replace the default root line (`$router->get('/', [AuthController::class, 'showLogin']);`) and add the include. After the `use` block and before `$router->get('/login', ...)`:

```php
$marketingActivo = (bool) \App\Kernel\Config\Config::get('vertical.modules.marketing', false);
if ($marketingActivo) {
    require ROOT_PATH . '/routes/marketing.php';
}

$router->get('/login',  [AuthController::class, 'showLogin']);
if (!$marketingActivo) {
    $router->get('/', [AuthController::class, 'showLogin']);
}
$router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
```

(Remove the original standalone `$router->get('/', [AuthController::class, 'showLogin']);` at line 31.)

- [ ] **Step 6: Register LandingController binding in the container (conditional)**

In `config/container.php`, near the end of the closure (after the existing conditional bindings or just before the closing `}`), add a marketing block guarded by the toggle:

```php
    if ((bool) Config::get('vertical.modules.marketing', false)) {
        $container->bind(\App\Presentation\Controllers\Publico\LandingController::class, function (Container $c) {
            return new \App\Presentation\Controllers\Publico\LandingController(
                $c->get(ConfiguracionService::class)
            );
        });
    }
```

(Confirm `use App\Kernel\Config\Config;` is already imported at top of container.php — it is, per the file header.)

- [ ] **Step 7: Run wiring test + lint**

Run: `php tests/run.php Marketing/RoutesWiringTest`
Expected: PASS (4 passed).
Run: `php -l routes/web.php && php -l routes/marketing.php && php -l app/Presentation/Controllers/Publico/LandingController.php`
Expected: `No syntax errors detected` for each.

- [ ] **Step 8: Commit**

```bash
git add routes/web.php routes/marketing.php app/Presentation/Controllers/Publico/LandingController.php config/container.php tests/Marketing/RoutesWiringTest.php
git commit -m "feat(marketing): rutas publicas condicionales y precedencia de raiz"
```

---

### Task 5: Layout público + vista de landing

**Files:**
- Create: `app/Presentation/Views/publico/layout.php`
- Create: `app/Presentation/Views/publico/landing.php`
- Test: `tests/Marketing/PublicViewTest.php`

**Interfaces:**
- Consumes: variables `empresaNombre`, `empresaLogo`, `bloques`, `paquetes`, `content` (inyectada por `ViewHelper::render` como el cuerpo de la vista), `App\Kernel\Helpers\ViewHelper::e()`.
- Produces: layout HTML público autónomo (sin nav admin, sin AuthMiddleware) que envuelve `$content`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/PublicViewTest.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

test('layout público renderiza el contenido inyectado y el nombre de empresa', function (): void {
    $html = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME Demo',
        'empresaLogo'   => '',
        'bloques'       => ['hero' => ['titulo' => 'Hola Mundo Hero', 'subtitulo' => 'Sub', 'cta_texto' => 'Demo', 'cta_url' => '#demo']],
        'paquetes'      => [],
    ], 'publico/layout');

    assert_true(str_contains($html, '<!DOCTYPE html>'), 'es documento HTML completo');
    assert_true(str_contains($html, 'ACME Demo'), 'muestra el nombre de empresa');
    assert_true(str_contains($html, 'Hola Mundo Hero'), 'renderiza el bloque hero');
    assert_true(!str_contains($html, 'AuthMiddleware'), 'sin restos de admin');
});

test('landing pública sin bloques no rompe (degradación)', function (): void {
    $html = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME',
        'empresaLogo'   => '',
        'bloques'       => [],
        'paquetes'      => [],
    ], 'publico/layout');
    assert_true(str_contains($html, '<!DOCTYPE html>'), 'renderiza igualmente');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: FAIL — `Vista no encontrada: …/publico/landing.php`.

- [ ] **Step 3: Create the public layout**

```php
<?php
// app/Presentation/Views/publico/layout.php

use App\Kernel\Helpers\ViewHelper;

$empresaNombre = $empresaNombre ?? '';
$empresaLogo   = $empresaLogo ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ViewHelper::e($empresaNombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/">
                <?php if ($empresaLogo !== ''): ?>
                    <img src="<?= ViewHelper::e($empresaLogo) ?>" alt="" height="32">
                <?php endif; ?>
                <span class="fw-semibold"><?= ViewHelper::e($empresaNombre) ?></span>
            </a>
            <a href="/login" class="btn btn-outline-secondary btn-sm">Acceder</a>
        </div>
    </nav>

    <main>
        <?= $content ?? '' ?>
    </main>

    <footer class="border-top py-4 mt-5">
        <div class="container text-center text-muted small">
            &copy; <?= date('Y') ?> <?= ViewHelper::e($empresaNombre) ?>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

- [ ] **Step 4: Create the landing view**

```php
<?php
// app/Presentation/Views/publico/landing.php

use App\Kernel\Helpers\ViewHelper;

$bloques  = $bloques ?? [];
$paquetes = $paquetes ?? [];
$hero     = $bloques['hero'] ?? ['titulo' => '', 'subtitulo' => '', 'cta_texto' => '', 'cta_url' => '#'];
?>
<section class="py-5 bg-white">
    <div class="container text-center py-4">
        <h1 class="display-5 fw-bold"><?= ViewHelper::e($hero['titulo'] ?? '') ?></h1>
        <p class="lead text-muted"><?= ViewHelper::e($hero['subtitulo'] ?? '') ?></p>
        <?php if (!empty($hero['cta_texto'])): ?>
            <a href="<?= ViewHelper::e($hero['cta_url'] ?? '#') ?>" class="btn btn-primary btn-lg mt-3">
                <?= ViewHelper::e($hero['cta_texto']) ?>
            </a>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($paquetes)): ?>
<section class="py-5" id="paquetes">
    <div class="container">
        <h2 class="h3 text-center mb-4">Paquetes</h2>
        <div class="row g-4 justify-content-center">
            <?php foreach ($paquetes as $p): ?>
                <div class="col-md-4">
                    <div class="card h-100 <?= !empty($p['destacado']) ? 'border-primary' : '' ?>">
                        <div class="card-body text-center">
                            <?php if (!empty($p['badge'])): ?>
                                <span class="badge bg-primary mb-2"><?= ViewHelper::e($p['badge']) ?></span>
                            <?php endif; ?>
                            <h3 class="h5"><?= ViewHelper::e($p['nombre'] ?? '') ?></h3>
                            <p class="display-6 fw-bold"><?= ViewHelper::e((string) ($p['precio_mensual'] ?? '')) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: PASS (2 passed).

- [ ] **Step 6: Commit**

```bash
git add app/Presentation/Views/publico/ tests/Marketing/PublicViewTest.php
git commit -m "feat(marketing): layout y vista de landing publica"
```

---

## Phase 3 — Contratos de extensión + bindings condicionales (Fase 4.3)

### Task 6: Value Objects + interfaces de extensión del dominio

**Files:**
- Create: `app/Domain/Marketing/ValueObjects/LeadDraft.php`, `LeadResult.php`, `Lead.php`, `Provision.php`, `ProvisionResult.php`
- Create: `app/Domain/Marketing/Contracts/LandingContentProviderInterface.php`, `CommercialPackageSourceInterface.php`, `LeadCaptureHandlerInterface.php`, `ProvisionAdapterInterface.php`, `MarketingContentRepositoryInterface.php`
- Test: `tests/Marketing/ValueObjectsTest.php`

**Interfaces:**
- Produces (consumidas por Tasks 12, 13, 14):
  - `LeadDraft(string $nombre, string $email, ?string $telefono, ?string $mensaje, array $utm = [])`; getters `nombre()`, `email()`, `telefono()`, `mensaje()`, `utm()`.
  - `LeadResult(bool $ok, ?int $leadId, array $errores = [])`; `ok()`, `leadId()`, `errores()`.
  - `Lead(int $id, string $nombre, string $email, string $estado)`; getters.
  - `Provision(int $id, ?int $leadId, string $accessToken, string $estado, array $payload)`; getters.
  - `ProvisionResult(bool $ok, ?int $provisionId, array $datos = [])`; getters.
  - `LandingContentProviderInterface::getBloques(string $pagina): array`
  - `CommercialPackageSourceInterface::listarPaquetes(): array`
  - `LeadCaptureHandlerInterface::handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult`
  - `ProvisionAdapterInterface::aprovisionar(Lead $lead, array $credenciales): ProvisionResult` y `estado(Provision $p): array`
  - `MarketingContentRepositoryInterface::bloquesPorPagina(string $pagina): array` y `paquetesActivos(): array`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/ValueObjectsTest.php
declare(strict_types=1);

use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;

test('LeadDraft expone sus campos', function (): void {
    $d = new LeadDraft('Ana', 'ana@example.com', '555', 'Hola', ['utm_source' => 'fb']);
    assert_same('Ana', $d->nombre());
    assert_same('ana@example.com', $d->email());
    assert_same('555', $d->telefono());
    assert_same('Hola', $d->mensaje());
    assert_same('fb', $d->utm()['utm_source']);
});

test('LeadResult ok y errores', function (): void {
    $ok = new LeadResult(true, 42);
    assert_same(true, $ok->ok());
    assert_same(42, $ok->leadId());
    $fail = new LeadResult(false, null, ['email' => 'inválido']);
    assert_same(false, $fail->ok());
    assert_same('inválido', $fail->errores()['email']);
});

test('las interfaces de extensión existen', function (): void {
    foreach ([
        \App\Domain\Marketing\Contracts\LandingContentProviderInterface::class,
        \App\Domain\Marketing\Contracts\CommercialPackageSourceInterface::class,
        \App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface::class,
        \App\Domain\Marketing\Contracts\ProvisionAdapterInterface::class,
        \App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class,
    ] as $iface) {
        assert_true(interface_exists($iface), "{$iface} existe");
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/ValueObjectsTest`
Expected: FAIL — clases/interfaces no encontradas.

- [ ] **Step 3: Create the Value Objects**

```php
<?php
// app/Domain/Marketing/ValueObjects/LeadDraft.php
declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class LeadDraft
{
    /** @param array<string,string> $utm */
    public function __construct(
        private readonly string $nombre,
        private readonly string $email,
        private readonly ?string $telefono = null,
        private readonly ?string $mensaje = null,
        private readonly array $utm = [],
    ) {}

    public function nombre(): string { return $this->nombre; }
    public function email(): string { return $this->email; }
    public function telefono(): ?string { return $this->telefono; }
    public function mensaje(): ?string { return $this->mensaje; }
    /** @return array<string,string> */
    public function utm(): array { return $this->utm; }
}
```

```php
<?php
// app/Domain/Marketing/ValueObjects/LeadResult.php
declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class LeadResult
{
    /** @param array<string,string> $errores */
    public function __construct(
        private readonly bool $ok,
        private readonly ?int $leadId = null,
        private readonly array $errores = [],
    ) {}

    public function ok(): bool { return $this->ok; }
    public function leadId(): ?int { return $this->leadId; }
    /** @return array<string,string> */
    public function errores(): array { return $this->errores; }
    public function withLeadId(int $id): self { return new self(true, $id, $this->errores); }
}
```

```php
<?php
// app/Domain/Marketing/ValueObjects/Lead.php
declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class Lead
{
    public function __construct(
        private readonly int $id,
        private readonly string $nombre,
        private readonly string $email,
        private readonly string $estado,
    ) {}

    public function id(): int { return $this->id; }
    public function nombre(): string { return $this->nombre; }
    public function email(): string { return $this->email; }
    public function estado(): string { return $this->estado; }
}
```

```php
<?php
// app/Domain/Marketing/ValueObjects/Provision.php
declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class Provision
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        private readonly int $id,
        private readonly ?int $leadId,
        private readonly string $accessToken,
        private readonly string $estado,
        private readonly array $payload = [],
    ) {}

    public function id(): int { return $this->id; }
    public function leadId(): ?int { return $this->leadId; }
    public function accessToken(): string { return $this->accessToken; }
    public function estado(): string { return $this->estado; }
    /** @return array<string,mixed> */
    public function payload(): array { return $this->payload; }
}
```

```php
<?php
// app/Domain/Marketing/ValueObjects/ProvisionResult.php
declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class ProvisionResult
{
    /** @param array<string,mixed> $datos */
    public function __construct(
        private readonly bool $ok,
        private readonly ?int $provisionId = null,
        private readonly array $datos = [],
    ) {}

    public function ok(): bool { return $this->ok; }
    public function provisionId(): ?int { return $this->provisionId; }
    /** @return array<string,mixed> */
    public function datos(): array { return $this->datos; }
}
```

- [ ] **Step 4: Create the interfaces**

```php
<?php
// app/Domain/Marketing/Contracts/LandingContentProviderInterface.php
declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface LandingContentProviderInterface
{
    /**
     * Bloques de contenido de una página pública, indexados por clave.
     * @return array<string,array<string,mixed>>  ej: ['hero'=>['titulo'=>...]]
     */
    public function getBloques(string $pagina): array;
}
```

```php
<?php
// app/Domain/Marketing/Contracts/CommercialPackageSourceInterface.php
declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface CommercialPackageSourceInterface
{
    /** @return list<array<string,mixed>> */
    public function listarPaquetes(): array;
}
```

```php
<?php
// app/Domain/Marketing/Contracts/LeadCaptureHandlerInterface.php
declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;

interface LeadCaptureHandlerInterface
{
    /** Procesa un paso del pipeline de captación; devuelve el resultado acumulado. */
    public function handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult;
}
```

```php
<?php
// app/Domain/Marketing/Contracts/ProvisionAdapterInterface.php
declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

use App\Domain\Marketing\ValueObjects\Lead;
use App\Domain\Marketing\ValueObjects\Provision;
use App\Domain\Marketing\ValueObjects\ProvisionResult;

interface ProvisionAdapterInterface
{
    /** @param array<string,mixed> $credenciales */
    public function aprovisionar(Lead $lead, array $credenciales): ProvisionResult;

    /** @return array<string,mixed> */
    public function estado(Provision $p): array;
}
```

```php
<?php
// app/Domain/Marketing/Contracts/MarketingContentRepositoryInterface.php
declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface MarketingContentRepositoryInterface
{
    /** @return array<string,array<string,mixed>> bloques indexados por clave */
    public function bloquesPorPagina(string $pagina): array;

    /** @return list<array<string,mixed>> */
    public function paquetesActivos(): array;
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/run.php Marketing/ValueObjectsTest`
Expected: PASS (3 passed).

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Marketing/ tests/Marketing/ValueObjectsTest.php
git commit -m "feat(marketing): contratos de extension y value objects del dominio"
```

---

### Task 7: Bindings condicionales de los providers default (placeholder)

**Files:**
- Modify: `config/container.php`
- Test: `tests/Marketing/ContainerWiringTest.php`

**Interfaces:**
- Consumes: `App\Kernel\Container\Container::bind/has/get`, `Config::get('vertical.modules.marketing')`.
- Produces: dentro del bloque `if (marketing)` del container, los bindings de interfaces de Marketing a sus implementaciones default (creadas en Tasks 12-14). En esta tarea solo se establece el bloque y un test que verifica que el binding de `LandingController` ya existe dentro del guard de toggle (regresión del wiring de Task 4). Las implementaciones concretas se añaden en sus tasks.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/ContainerWiringTest.php
declare(strict_types=1);

test('container.php agrupa los bindings de marketing bajo el guard del toggle', function (): void {
    $src = file_get_contents(ROOT_PATH . '/config/container.php');
    assert_true($src !== false);
    assert_true(str_contains($src, "Config::get('vertical.modules.marketing'"), 'lee el toggle del módulo');
    assert_true(str_contains($src, 'Publico\\LandingController'), 'registra LandingController');
});
```

- [ ] **Step 2: Run test to verify it fails or passes**

Run: `php tests/run.php Marketing/ContainerWiringTest`
Expected: PASS already (Task 4 added the guard + LandingController binding). If it FAILS, re-apply Task 4 Step 6. This task formalizes the guard as the single home for all Marketing bindings.

- [ ] **Step 3: Document the binding block**

In `config/container.php`, ensure the marketing block from Task 4 has a header comment so later tasks append into the same block:

```php
    // ── Módulo Marketing (bindings condicionales al toggle; ver config/modules/marketing.php) ──
    if ((bool) Config::get('vertical.modules.marketing', false)) {
        $container->bind(\App\Presentation\Controllers\Publico\LandingController::class, function (Container $c) {
            return new \App\Presentation\Controllers\Publico\LandingController(
                $c->get(ConfiguracionService::class)
            );
        });
        // Tasks 12-14 añaden aquí: MarketingContentRepositoryInterface, providers, use cases,
        // LeadController, PortalClienteController.
    }
```

- [ ] **Step 4: Run test + lint**

Run: `php tests/run.php Marketing/ContainerWiringTest`
Expected: PASS (1 passed).
Run: `php -l config/container.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add config/container.php tests/Marketing/ContainerWiringTest.php
git commit -m "chore(marketing): formalizar bloque de bindings condicionales"
```

---

## Phase 4 — Ajustes extensible (Fase 4.4)

### Task 8: `SettingsSectionProviderInterface` + `SettingsSectionRegistry`

**Files:**
- Create: `app/Domain/Interfaces/SettingsSectionProviderInterface.php`
- Create: `app/Application/Services/SettingsSectionRegistry.php`
- Create: `config/settings_sections.php`
- Test: `tests/Marketing/SettingsRegistryTest.php`

**Interfaces:**
- Produces:
  - `SettingsSectionProviderInterface`: `clave(): string`, `titulo(): string`, `icono(): string`, `permiso(): string`, `campos(): array` (cada campo: `['name'=>string,'label'=>string,'type'=>string,'group'?=>string,'secret'?=>bool,'default'?=>string,'options'?=>array,'help'?=>string]`).
  - `SettingsSectionRegistry::__construct(array $providers)`; `visibles(array $permisos): array` (filtra por `permiso()` y devuelve providers visibles); `fieldNames(array $permisos): array` (lista plana de `name` de los providers visibles, para que `AjustesController::guardar` sepa qué persistir).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/SettingsRegistryTest.php
declare(strict_types=1);

use App\Application\Services\SettingsSectionRegistry;
use App\Domain\Interfaces\SettingsSectionProviderInterface;

function fakeSettingsProvider(string $clave, string $permiso, array $fieldNames): SettingsSectionProviderInterface
{
    return new class($clave, $permiso, $fieldNames) implements SettingsSectionProviderInterface {
        public function __construct(private string $c, private string $p, private array $f) {}
        public function clave(): string { return $this->c; }
        public function titulo(): string { return 'T ' . $this->c; }
        public function icono(): string { return 'bi-gear'; }
        public function permiso(): string { return $this->p; }
        public function campos(): array {
            return array_map(fn($n) => ['name' => $n, 'label' => $n, 'type' => 'text'], $this->f);
        }
    };
}

test('visibles filtra providers por permiso del usuario', function (): void {
    $reg = new SettingsSectionRegistry([
        fakeSettingsProvider('correo', 'marketing.gestionar', ['mkt_mail_host']),
        fakeSettingsProvider('otro', 'permiso.inexistente', ['x']),
    ]);
    $visibles = $reg->visibles(['marketing.gestionar']);
    assert_same(1, count($visibles));
    assert_same('correo', $visibles[0]->clave());
});

test('fieldNames devuelve campos planos solo de providers visibles', function (): void {
    $reg = new SettingsSectionRegistry([
        fakeSettingsProvider('correo', 'marketing.gestionar', ['mkt_mail_host', 'mkt_mail_from']),
        fakeSettingsProvider('oculto', 'no.tengo', ['mkt_secreto']),
    ]);
    $names = $reg->fieldNames(['marketing.gestionar']);
    assert_true(in_array('mkt_mail_host', $names, true), 'incluye host');
    assert_true(in_array('mkt_mail_from', $names, true), 'incluye from');
    assert_true(!in_array('mkt_secreto', $names, true), 'excluye oculto');
});

test('registro vacío devuelve listas vacías', function (): void {
    $reg = new SettingsSectionRegistry([]);
    assert_same([], $reg->visibles(['x']));
    assert_same([], $reg->fieldNames(['x']));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/SettingsRegistryTest`
Expected: FAIL — interfaz/registry no existen.

- [ ] **Step 3: Create the interface**

```php
<?php
// app/Domain/Interfaces/SettingsSectionProviderInterface.php
declare(strict_types=1);

namespace App\Domain\Interfaces;

/**
 * Sección extensible de la pantalla de Ajustes. Un módulo registra una o varias
 * implementaciones (vía config/settings_sections.php) y AjustesController las
 * renderiza/persiste sin conocer el módulo concreto.
 */
interface SettingsSectionProviderInterface
{
    public function clave(): string;
    public function titulo(): string;
    public function icono(): string;
    /** Slug RBAC requerido para ver/editar esta sección. */
    public function permiso(): string;

    /**
     * Definiciones declarativas de campos.
     * @return list<array{name:string,label:string,type:string,group?:string,secret?:bool,default?:string,options?:array<string,string>,help?:string}>
     */
    public function campos(): array;
}
```

- [ ] **Step 4: Create the registry**

```php
<?php
// app/Application/Services/SettingsSectionRegistry.php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Interfaces\SettingsSectionProviderInterface;

final class SettingsSectionRegistry
{
    /** @var list<SettingsSectionProviderInterface> */
    private array $providers;

    /** @param list<SettingsSectionProviderInterface> $providers */
    public function __construct(array $providers = [])
    {
        $this->providers = array_values($providers);
    }

    /**
     * Providers cuyo permiso posee el usuario.
     * @param list<string> $permisos
     * @return list<SettingsSectionProviderInterface>
     */
    public function visibles(array $permisos): array
    {
        return array_values(array_filter(
            $this->providers,
            static fn(SettingsSectionProviderInterface $p) => in_array($p->permiso(), $permisos, true)
        ));
    }

    /**
     * Nombres de campo planos de los providers visibles (para persistir en guardar()).
     * @param list<string> $permisos
     * @return list<string>
     */
    public function fieldNames(array $permisos): array
    {
        $names = [];
        foreach ($this->visibles($permisos) as $provider) {
            foreach ($provider->campos() as $campo) {
                if (isset($campo['name']) && is_string($campo['name'])) {
                    $names[] = $campo['name'];
                }
            }
        }
        return $names;
    }
}
```

- [ ] **Step 5: Create the empty config list**

```php
<?php
// config/settings_sections.php
declare(strict_types=1);

// Lista declarativa de SettingsSectionProviders (FQCN). Patrón análogo a
// config/dashboard.php. El container instancia solo los de módulos activos.
// Marketing añade los suyos en Task 9 (condicionados al toggle dentro de container.php).
return [
    'providers' => [],
];
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php tests/run.php Marketing/SettingsRegistryTest`
Expected: PASS (3 passed).

- [ ] **Step 7: Commit**

```bash
git add app/Domain/Interfaces/SettingsSectionProviderInterface.php app/Application/Services/SettingsSectionRegistry.php config/settings_sections.php tests/Marketing/SettingsRegistryTest.php
git commit -m "feat(settings): interfaz de seccion extensible y registry transversal"
```

---

### Task 9: Providers de Ajustes del módulo Marketing

**Files:**
- Create: `app/Infrastructure/Marketing/Settings/MarketingCorreoSettingsProvider.php`, `MarketingPaquetesSettingsProvider.php`, `MarketingTrackingSettingsProvider.php`, `MarketingContenidoSettingsProvider.php`
- Test: `tests/Marketing/MarketingSettingsProvidersTest.php`

**Interfaces:**
- Consumes: `SettingsSectionProviderInterface`.
- Produces: 4 providers con `permiso()='marketing.gestionar'` y `campos()` con claves `mkt_*`. Field names usados por la vista (Task 10) y por `fieldNames()`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/MarketingSettingsProvidersTest.php
declare(strict_types=1);

use App\Infrastructure\Marketing\Settings\MarketingCorreoSettingsProvider;
use App\Infrastructure\Marketing\Settings\MarketingPaquetesSettingsProvider;
use App\Infrastructure\Marketing\Settings\MarketingTrackingSettingsProvider;
use App\Infrastructure\Marketing\Settings\MarketingContenidoSettingsProvider;
use App\Domain\Interfaces\SettingsSectionProviderInterface;

test('los 4 providers de marketing implementan la interfaz y exigen marketing.gestionar', function (): void {
    foreach ([
        new MarketingCorreoSettingsProvider(),
        new MarketingPaquetesSettingsProvider(),
        new MarketingTrackingSettingsProvider(),
        new MarketingContenidoSettingsProvider(),
    ] as $p) {
        assert_true($p instanceof SettingsSectionProviderInterface, get_class($p) . ' implementa la interfaz');
        assert_same('marketing.gestionar', $p->permiso());
        assert_true($p->clave() !== '', 'tiene clave');
        assert_true(count($p->campos()) > 0, 'declara campos');
    }
});

test('todos los campos de marketing usan prefijo mkt_', function (): void {
    foreach ([
        new MarketingCorreoSettingsProvider(),
        new MarketingPaquetesSettingsProvider(),
        new MarketingTrackingSettingsProvider(),
        new MarketingContenidoSettingsProvider(),
    ] as $p) {
        foreach ($p->campos() as $campo) {
            assert_true(str_starts_with($campo['name'], 'mkt_'), $campo['name'] . ' usa prefijo mkt_');
        }
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/MarketingSettingsProvidersTest`
Expected: FAIL — clases no encontradas.

- [ ] **Step 3: Create the Correo provider**

```php
<?php
// app/Infrastructure/Marketing/Settings/MarketingCorreoSettingsProvider.php
declare(strict_types=1);

namespace App\Infrastructure\Marketing\Settings;

use App\Domain\Interfaces\SettingsSectionProviderInterface;

final class MarketingCorreoSettingsProvider implements SettingsSectionProviderInterface
{
    public function clave(): string { return 'marketing_correo'; }
    public function titulo(): string { return 'Correo y automatizaciones'; }
    public function icono(): string { return 'bi-envelope'; }
    public function permiso(): string { return 'marketing.gestionar'; }

    public function campos(): array
    {
        return [
            ['name' => 'mkt_mail_host', 'label' => 'SMTP host (override)', 'type' => 'text', 'help' => 'Vacío ⇒ usa el SMTP global del sistema.'],
            ['name' => 'mkt_mail_from', 'label' => 'Remitente del módulo', 'type' => 'text'],
            ['name' => 'mkt_mail_secuencias', 'label' => 'Activar secuencias', 'type' => 'toggle', 'default' => '0'],
        ];
    }
}
```

- [ ] **Step 4: Create the Paquetes provider**

```php
<?php
// app/Infrastructure/Marketing/Settings/MarketingPaquetesSettingsProvider.php
declare(strict_types=1);

namespace App\Infrastructure\Marketing\Settings;

use App\Domain\Interfaces\SettingsSectionProviderInterface;

final class MarketingPaquetesSettingsProvider implements SettingsSectionProviderInterface
{
    public function clave(): string { return 'marketing_paquetes'; }
    public function titulo(): string { return 'Paquetes comerciales'; }
    public function icono(): string { return 'bi-box-seam'; }
    public function permiso(): string { return 'marketing.gestionar'; }

    public function campos(): array
    {
        return [
            ['name' => 'mkt_paquetes_moneda', 'label' => 'Moneda', 'type' => 'text', 'default' => 'MXN'],
            ['name' => 'mkt_paquetes_ciclo', 'label' => 'Ciclo por defecto', 'type' => 'select',
             'options' => ['mensual' => 'Mensual', 'anual' => 'Anual'], 'default' => 'mensual'],
        ];
    }
}
```

- [ ] **Step 5: Create the Tracking provider**

```php
<?php
// app/Infrastructure/Marketing/Settings/MarketingTrackingSettingsProvider.php
declare(strict_types=1);

namespace App\Infrastructure\Marketing\Settings;

use App\Domain\Interfaces\SettingsSectionProviderInterface;

final class MarketingTrackingSettingsProvider implements SettingsSectionProviderInterface
{
    public function clave(): string { return 'marketing_tracking'; }
    public function titulo(): string { return 'Marketing y tracking'; }
    public function icono(): string { return 'bi-graph-up'; }
    public function permiso(): string { return 'marketing.gestionar'; }

    public function campos(): array
    {
        return [
            ['name' => 'mkt_analytics_id', 'label' => 'ID de Analytics', 'type' => 'text'],
            ['name' => 'mkt_pixel_id', 'label' => 'ID de píxel', 'type' => 'text'],
            ['name' => 'mkt_captacion_activa', 'label' => 'Captación de leads activa', 'type' => 'toggle', 'default' => '1'],
        ];
    }
}
```

- [ ] **Step 6: Create the Contenido provider**

```php
<?php
// app/Infrastructure/Marketing/Settings/MarketingContenidoSettingsProvider.php
declare(strict_types=1);

namespace App\Infrastructure\Marketing\Settings;

use App\Domain\Interfaces\SettingsSectionProviderInterface;

final class MarketingContenidoSettingsProvider implements SettingsSectionProviderInterface
{
    public function clave(): string { return 'marketing_contenido'; }
    public function titulo(): string { return 'Contenido público'; }
    public function icono(): string { return 'bi-layout-text-window'; }
    public function permiso(): string { return 'marketing.gestionar'; }

    public function campos(): array
    {
        return [
            ['name' => 'mkt_pagina_inicio', 'label' => 'Página de inicio activa', 'type' => 'text', 'default' => 'home'],
            ['name' => 'mkt_slug_base', 'label' => 'Slug base público', 'type' => 'text', 'default' => ''],
            ['name' => 'mkt_mostrar_testimonios', 'label' => 'Mostrar testimonios', 'type' => 'toggle', 'default' => '1'],
        ];
    }
}
```

- [ ] **Step 7: Register providers in container (conditional) + build the registry**

In `config/container.php`, inside the marketing toggle block (Task 7), add:

```php
        $container->singleton(\App\Application\Services\SettingsSectionRegistry::class, function () {
            return new \App\Application\Services\SettingsSectionRegistry([
                new \App\Infrastructure\Marketing\Settings\MarketingCorreoSettingsProvider(),
                new \App\Infrastructure\Marketing\Settings\MarketingPaquetesSettingsProvider(),
                new \App\Infrastructure\Marketing\Settings\MarketingTrackingSettingsProvider(),
                new \App\Infrastructure\Marketing\Settings\MarketingContenidoSettingsProvider(),
            ]);
        });
```

Also, OUTSIDE the toggle block (so it always resolves), add a fallback empty registry **before** the marketing block so the binding always exists for AjustesController:

```php
    // Registry de secciones de Ajustes — vacío por defecto; los módulos activos lo sustituyen.
    $container->singleton(\App\Application\Services\SettingsSectionRegistry::class, function () {
        return new \App\Application\Services\SettingsSectionRegistry([]);
    });
```

(The marketing block, running after, overrides the singleton binding when the toggle is on. Verify the container's `singleton` allows re-binding; if it throws on redefine, instead build the providers array conditionally in a single binding — see Step 8 fallback.)

- [ ] **Step 8: Verify container re-binding semantics**

Run: `php -r "require 'app/Kernel/Autoloader.php'; \$r=new ReflectionClass(App\Kernel\Container\Container::class); echo implode(',', array_map(fn(\$m)=>\$m->getName(), \$r->getMethods()));"`
If `singleton` overwrites silently → keep Step 7. If it throws on duplicate key → replace both bindings with a single one that reads the toggle inline:

```php
    $container->singleton(\App\Application\Services\SettingsSectionRegistry::class, function () {
        $providers = [];
        if ((bool) \App\Kernel\Config\Config::get('vertical.modules.marketing', false)) {
            $providers = [
                new \App\Infrastructure\Marketing\Settings\MarketingCorreoSettingsProvider(),
                new \App\Infrastructure\Marketing\Settings\MarketingPaquetesSettingsProvider(),
                new \App\Infrastructure\Marketing\Settings\MarketingTrackingSettingsProvider(),
                new \App\Infrastructure\Marketing\Settings\MarketingContenidoSettingsProvider(),
            ];
        }
        return new \App\Application\Services\SettingsSectionRegistry($providers);
    });
```

- [ ] **Step 9: Run test to verify it passes + lint**

Run: `php tests/run.php Marketing/MarketingSettingsProvidersTest`
Expected: PASS (2 passed).
Run: `php -l config/container.php`
Expected: `No syntax errors detected`.

- [ ] **Step 10: Commit**

```bash
git add app/Infrastructure/Marketing/Settings/ config/container.php tests/Marketing/MarketingSettingsProvidersTest.php
git commit -m "feat(marketing): providers de seccion de ajustes registrados condicionalmente"
```

---

### Task 10: `AjustesController` extensible + render de secciones

**Files:**
- Modify: `app/Presentation/Controllers/Admin/AjustesController.php`
- Modify: `config/container.php` (inyectar `SettingsSectionRegistry` en AjustesController)
- Create: `app/Presentation/Views/admin/ajustes/_provider_section.php`
- Modify: `app/Presentation/Views/admin/ajustes/index.php`
- Test: `tests/Marketing/AjustesExtensibleTest.php`

**Interfaces:**
- Consumes: `SettingsSectionRegistry::visibles()`, `::fieldNames()`; `Session::get('auth_permisos', [])`; `ConfiguracionService::setMultiple()`.
- Produces: `AjustesController::__construct(ConfiguracionService, AdminNavigationMenuService, SettingsSectionRegistry)`. `guardar()` persiste campos de sistema **y** los de providers visibles sin regresión.

- [ ] **Step 1: Write the failing test (regression guard for system fields + provider fields)**

```php
<?php
// tests/Marketing/AjustesExtensibleTest.php
declare(strict_types=1);

use App\Application\Services\SettingsSectionRegistry;
use App\Domain\Interfaces\SettingsSectionProviderInterface;

// Verifica la lógica de combinación de campos sin arrancar HTTP:
// los campos de sistema fijos + los de providers visibles forman el set a persistir.
test('el set de claves a guardar incluye campos de sistema y de providers visibles', function (): void {
    $camposSistema = ['empresa_nombre','menu_layout','primary_color','navbar_color','body_color','empresa_logo'];

    $provider = new class implements SettingsSectionProviderInterface {
        public function clave(): string { return 'marketing_correo'; }
        public function titulo(): string { return 'Correo'; }
        public function icono(): string { return 'bi-envelope'; }
        public function permiso(): string { return 'marketing.gestionar'; }
        public function campos(): array { return [['name'=>'mkt_mail_host','label'=>'Host','type'=>'text']]; }
    };
    $reg = new SettingsSectionRegistry([$provider]);

    $todas = array_merge($camposSistema, $reg->fieldNames(['marketing.gestionar']));
    assert_true(in_array('empresa_nombre', $todas, true), 'conserva campo de sistema');
    assert_true(in_array('mkt_mail_host', $todas, true), 'incluye campo de provider');
});

test('sin permisos del módulo no se cuelan campos de provider', function (): void {
    $provider = new class implements SettingsSectionProviderInterface {
        public function clave(): string { return 'x'; }
        public function titulo(): string { return 'x'; }
        public function icono(): string { return 'bi-x'; }
        public function permiso(): string { return 'marketing.gestionar'; }
        public function campos(): array { return [['name'=>'mkt_x','label'=>'x','type'=>'text']]; }
    };
    $reg = new SettingsSectionRegistry([$provider]);
    assert_same([], $reg->fieldNames(['administracion.ver']));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/AjustesExtensibleTest`
Expected: FAIL only if SettingsSectionRegistry missing — but it exists from Task 8, so this test PASSES immediately. That is fine: it documents the contract. Proceed to wire the controller (the real integration is verified by lint + manual). If it PASSES, continue to Step 3.

- [ ] **Step 3: Modify AjustesController to inject the registry and use it**

Replace the constructor and `index`/`guardar` in `app/Presentation/Controllers/Admin/AjustesController.php`:

```php
use App\Application\Services\SettingsSectionRegistry;
```

```php
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly SettingsSectionRegistry $settingsSections
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        $permisos = Session::get('auth_permisos', []);
        return $this->view('admin/ajustes/index', [
            'titulo'           => 'Ajustes del sistema',
            'configuracion'    => $this->configuracionService->all(),
            'settingsSections' => $this->settingsSections->visibles($permisos),
        ]);
    }
```

In `guardar()`, after the existing `$datos = array_merge($datos, $this->validatedLebytekUiSettings($request));` line and before `$this->configuracionService->setMultiple($datos);`, add:

```php
        // Campos declarados por providers de secciones (módulos activos), filtrados por RBAC.
        $permisos = Session::get('auth_permisos', []);
        foreach ($this->settingsSections->fieldNames($permisos) as $campo) {
            // Los toggles llegan ausentes cuando están desmarcados.
            $datos[$campo] = $request->has($campo) ? (string) $request->input($campo, '1') : '0';
            // Para campos de texto, conservar el valor textual si vino con contenido.
            $valor = $request->input($campo, null);
            if ($valor !== null && $valor !== '' && $valor !== '1') {
                $datos[$campo] = (string) $valor;
            }
        }
```

- [ ] **Step 4: Update the container binding for AjustesController**

In `config/container.php`, update the `AjustesController` binding to pass the registry:

```php
    $container->bind(\App\Presentation\Controllers\Admin\AjustesController::class, function (Container $c) {
        return new \App\Presentation\Controllers\Admin\AjustesController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            $c->get(\App\Application\Services\SettingsSectionRegistry::class)
        );
    });
```

- [ ] **Step 5: Create the provider-section partial**

```php
<?php
// app/Presentation/Views/admin/ajustes/_provider_section.php

use App\Kernel\Helpers\ViewHelper;

/** @var \App\Domain\Interfaces\SettingsSectionProviderInterface $section */
/** @var array $configuracion */
$c = $configuracion ?? [];
ob_start();
?>
<div class="row g-3">
    <?php foreach ($section->campos() as $campo): ?>
        <?php
            $name = $campo['name'];
            $type = $campo['type'] ?? 'text';
            $val  = $c[$name] ?? ($campo['default'] ?? '');
        ?>
        <div class="col-md-6">
            <label for="<?= ViewHelper::e($name) ?>" class="form-label fw-medium small"><?= ViewHelper::e($campo['label']) ?></label>
            <?php if ($type === 'toggle'): ?>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="<?= ViewHelper::e($name) ?>" name="<?= ViewHelper::e($name) ?>"
                           value="1" <?= ((string) $val) === '1' ? 'checked' : '' ?>>
                </div>
            <?php elseif ($type === 'select' && !empty($campo['options'])): ?>
                <select id="<?= ViewHelper::e($name) ?>" name="<?= ViewHelper::e($name) ?>" class="form-select">
                    <?php foreach ($campo['options'] as $ov => $ol): ?>
                        <option value="<?= ViewHelper::e($ov) ?>" <?= ((string) $val) === (string) $ov ? 'selected' : '' ?>><?= ViewHelper::e($ol) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="<?= $type === 'secret' ? 'password' : 'text' ?>" id="<?= ViewHelper::e($name) ?>" name="<?= ViewHelper::e($name) ?>"
                       class="form-control" value="<?= ViewHelper::e((string) $val) ?>">
            <?php endif; ?>
            <?php if (!empty($campo['help'])): ?>
                <div class="form-text"><?= ViewHelper::e($campo['help']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php
$bodyHtml = ob_get_clean();
echo ViewHelper::partial('admin/ajustes_accordion_item', [
    'collapseId' => 'ajustesCollapse' . ucfirst(preg_replace('/[^a-z0-9]/i', '', $section->clave())),
    'headingId'  => 'ajustesHeading' . ucfirst(preg_replace('/[^a-z0-9]/i', '', $section->clave())),
    'title'      => $section->titulo(),
    'iconClass'  => $section->icono(),
    'bodyHtml'   => $bodyHtml,
]);
```

- [ ] **Step 6: Render provider sections in the ajustes index**

In `app/Presentation/Views/admin/ajustes/index.php`, inside the accordion (`<div class="accordion ct-ajustes-accordion" id="ajustesAccordion">`), just before the closing `</div>` of that accordion (after the existing "Login" provider item, around line 266), add:

```php
                <?php foreach (($settingsSections ?? []) as $section): ?>
                    <?= ViewHelper::partial('admin/ajustes/_provider_section', [
                        'section'       => $section,
                        'configuracion' => $configuracion,
                    ]) ?>
                <?php endforeach; ?>
```

- [ ] **Step 7: Run tests + lint**

Run: `php tests/run.php Marketing/AjustesExtensibleTest`
Expected: PASS (2 passed).
Run: `php -l app/Presentation/Controllers/Admin/AjustesController.php && php -l app/Presentation/Views/admin/ajustes/_provider_section.php && php -l app/Presentation/Views/admin/ajustes/index.php`
Expected: `No syntax errors detected` for each.
Run: `php tests/run.php` (full suite — verify no regression elsewhere).
Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add app/Presentation/Controllers/Admin/AjustesController.php config/container.php app/Presentation/Views/admin/ajustes/ tests/Marketing/AjustesExtensibleTest.php
git commit -m "feat(settings): AjustesController renderiza y persiste secciones de providers"
```

---

## Phase 5 — CRUDs de contenido + providers default (Fase 4.5)

### Task 11: Configuraciones CRUD `mkt_*`

**Files:**
- Create: `config/cruds/mkt_leads.json`, `config/cruds/mkt_paquetes.json`, `config/cruds/mkt_bloques.json`, `config/cruds/mkt_plantillas.json`, `config/cruds/mkt_secuencias.json`
- Test: `tests/Marketing/CrudConfigsTest.php`

**Interfaces:**
- Consumes: tablas `dom_mkt_*` (Task 2), permisos `marketing.{ver,crear,editar,eliminar}` (Task 2).
- Produces: 5 configs CRUD válidas. `permission_prefix='marketing'` ⇒ el CRUD Engine exige `marketing.ver/crear/editar/eliminar` (precedente: calendario usó `{prefix}.{accion}`). `mkt_leads` con scope owner sobre `created_by`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/CrudConfigsTest.php
declare(strict_types=1);

test('los 5 CRUD JSON de marketing son válidos y apuntan a tablas dom_mkt_*', function (): void {
    $map = [
        'mkt_leads'      => 'dom_mkt_leads',
        'mkt_paquetes'   => 'dom_mkt_paquetes',
        'mkt_bloques'    => 'dom_mkt_bloques',
        'mkt_plantillas' => 'dom_mkt_plantillas',
        'mkt_secuencias' => 'dom_mkt_secuencias',
    ];
    foreach ($map as $key => $tabla) {
        $path = ROOT_PATH . "/config/cruds/{$key}.json";
        assert_true(is_file($path), "{$key}.json existe");
        $cfg = json_decode((string) file_get_contents($path), true);
        assert_true(is_array($cfg), "{$key}.json es JSON válido");
        assert_same($key, $cfg['resource']['key']);
        assert_same($tabla, $cfg['resource']['table']);
        assert_same('marketing', $cfg['resource']['permission_prefix']);
    }
});

test('mkt_leads usa scope owner sobre created_by', function (): void {
    $cfg = json_decode((string) file_get_contents(ROOT_PATH . '/config/cruds/mkt_leads.json'), true);
    assert_same('owner', $cfg['list']['scope']['type']);
    assert_same('created_by', $cfg['list']['scope']['column']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/CrudConfigsTest`
Expected: FAIL — los JSON no existen.

- [ ] **Step 3: Create `mkt_leads.json`**

```json
{
  "resource": {
    "key": "mkt_leads",
    "title": "Leads",
    "table": "dom_mkt_leads",
    "primary_key": "id",
    "permission_prefix": "marketing"
  },
  "security": { "allow_core_table": false, "mode": "restricted" },
  "list": {
    "columns": [
      { "name": "id", "label": "ID", "sortable": true },
      { "name": "nombre", "label": "Nombre", "searchable": true, "sortable": true },
      { "name": "email", "label": "Correo", "searchable": true, "sortable": true },
      { "name": "telefono", "label": "Teléfono" },
      { "name": "estado", "label": "Estado",
        "badge": { "pendiente": "secondary", "validada": "info", "demo_enviada": "success", "rechazada": "danger" } },
      { "name": "created_at", "label": "Recibido", "format": "datetime", "sortable": true }
    ],
    "filters": [ { "field": "estado", "label": "Estado" } ],
    "actions": ["show", "edit", "delete"],
    "scope": { "type": "owner", "column": "created_by" }
  },
  "form": {
    "fields": [
      { "name": "nombre", "label": "Nombre", "type": "text", "required": true, "col": "col-md-6" },
      { "name": "email", "label": "Correo", "type": "text", "required": true, "col": "col-md-6",
        "validation": { "type": "email", "messages": { "email": "Correo inválido" } } },
      { "name": "telefono", "label": "Teléfono", "type": "text", "col": "col-md-6" },
      { "name": "estado", "label": "Estado", "type": "select", "required": true, "col": "col-md-6",
        "options": { "pendiente": "Pendiente", "validada": "Validada", "demo_enviada": "Demo enviada", "rechazada": "Rechazada" },
        "default": "pendiente" },
      { "name": "mensaje", "label": "Mensaje", "type": "textarea", "col": "col-12" }
    ],
    "validators": []
  },
  "actions": { "row": [ { "name": "show", "type": "builtin" }, { "name": "edit", "type": "builtin" }, { "name": "delete", "type": "builtin" } ], "bulk": [] },
  "detail": { "tabs": [ { "key": "general", "label": "Datos", "type": "fields", "columns": ["nombre","email","telefono","estado","mensaje","created_at"] }, { "key": "historial", "label": "Historial", "type": "history" } ] },
  "uploads": { "enabled": false, "public_path": "uploads/cruds/mkt_leads" },
  "hooks": { "handler": null }
}
```

- [ ] **Step 4: Create `mkt_paquetes.json`**

```json
{
  "resource": {
    "key": "mkt_paquetes",
    "title": "Paquetes",
    "table": "dom_mkt_paquetes",
    "primary_key": "id",
    "permission_prefix": "marketing"
  },
  "security": { "allow_core_table": false, "mode": "restricted" },
  "list": {
    "columns": [
      { "name": "id", "label": "ID", "sortable": true },
      { "name": "nombre", "label": "Nombre", "searchable": true, "sortable": true },
      { "name": "precio_mensual", "label": "Mensual", "sortable": true },
      { "name": "precio_anual", "label": "Anual", "sortable": true },
      { "name": "destacado", "label": "Destacado", "badge": { "1": "primary", "0": "secondary" } },
      { "name": "activo", "label": "Activo", "badge": { "1": "success", "0": "secondary" } }
    ],
    "filters": [ { "field": "activo", "label": "Activo" } ],
    "actions": ["show", "edit", "delete"]
  },
  "form": {
    "fields": [
      { "name": "nombre", "label": "Nombre", "type": "text", "required": true, "col": "col-md-6" },
      { "name": "badge", "label": "Badge", "type": "text", "col": "col-md-6" },
      { "name": "precio_mensual", "label": "Precio mensual", "type": "number", "col": "col-md-6" },
      { "name": "precio_anual", "label": "Precio anual", "type": "number", "col": "col-md-6" },
      { "name": "features", "label": "Features (JSON array)", "type": "textarea", "col": "col-12" },
      { "name": "orden", "label": "Orden", "type": "number", "default": "0", "col": "col-md-4" },
      { "name": "destacado", "label": "Destacado", "type": "select", "options": { "1": "Sí", "0": "No" }, "default": "0", "col": "col-md-4" },
      { "name": "activo", "label": "Activo", "type": "select", "options": { "1": "Sí", "0": "No" }, "default": "1", "col": "col-md-4" }
    ],
    "validators": []
  },
  "actions": { "row": [ { "name": "show", "type": "builtin" }, { "name": "edit", "type": "builtin" }, { "name": "delete", "type": "builtin" } ], "bulk": [] },
  "detail": { "tabs": [ { "key": "general", "label": "Datos", "type": "fields", "columns": ["nombre","precio_mensual","precio_anual","badge","destacado","activo"] } ] },
  "uploads": { "enabled": false, "public_path": "uploads/cruds/mkt_paquetes" },
  "hooks": { "handler": null }
}
```

- [ ] **Step 5: Create `mkt_bloques.json`**

```json
{
  "resource": {
    "key": "mkt_bloques",
    "title": "Bloques de contenido",
    "table": "dom_mkt_bloques",
    "primary_key": "id",
    "permission_prefix": "marketing"
  },
  "security": { "allow_core_table": false, "mode": "restricted" },
  "list": {
    "columns": [
      { "name": "id", "label": "ID", "sortable": true },
      { "name": "pagina", "label": "Página", "searchable": true, "sortable": true },
      { "name": "clave", "label": "Clave", "searchable": true, "sortable": true },
      { "name": "orden", "label": "Orden", "sortable": true },
      { "name": "activo", "label": "Activo", "badge": { "1": "success", "0": "secondary" } }
    ],
    "filters": [ { "field": "pagina", "label": "Página" } ],
    "actions": ["show", "edit", "delete"]
  },
  "form": {
    "fields": [
      { "name": "pagina", "label": "Página", "type": "text", "required": true, "default": "home", "col": "col-md-6" },
      { "name": "clave", "label": "Clave", "type": "text", "required": true, "col": "col-md-6" },
      { "name": "contenido", "label": "Contenido (JSON)", "type": "textarea", "col": "col-12" },
      { "name": "orden", "label": "Orden", "type": "number", "default": "0", "col": "col-md-6" },
      { "name": "activo", "label": "Activo", "type": "select", "options": { "1": "Sí", "0": "No" }, "default": "1", "col": "col-md-6" }
    ],
    "validators": []
  },
  "actions": { "row": [ { "name": "show", "type": "builtin" }, { "name": "edit", "type": "builtin" }, { "name": "delete", "type": "builtin" } ], "bulk": [] },
  "detail": { "tabs": [ { "key": "general", "label": "Datos", "type": "fields", "columns": ["pagina","clave","contenido","orden","activo"] } ] },
  "uploads": { "enabled": false, "public_path": "uploads/cruds/mkt_bloques" },
  "hooks": { "handler": null }
}
```

- [ ] **Step 6: Create `mkt_plantillas.json`**

```json
{
  "resource": {
    "key": "mkt_plantillas",
    "title": "Plantillas de correo",
    "table": "dom_mkt_plantillas",
    "primary_key": "id",
    "permission_prefix": "marketing"
  },
  "security": { "allow_core_table": false, "mode": "restricted" },
  "list": {
    "columns": [
      { "name": "id", "label": "ID", "sortable": true },
      { "name": "clave", "label": "Clave", "searchable": true, "sortable": true },
      { "name": "asunto", "label": "Asunto", "searchable": true },
      { "name": "activo", "label": "Activo", "badge": { "1": "success", "0": "secondary" } }
    ],
    "filters": [],
    "actions": ["show", "edit", "delete"]
  },
  "form": {
    "fields": [
      { "name": "clave", "label": "Clave", "type": "text", "required": true, "col": "col-md-6" },
      { "name": "activo", "label": "Activo", "type": "select", "options": { "1": "Sí", "0": "No" }, "default": "1", "col": "col-md-6" },
      { "name": "asunto", "label": "Asunto", "type": "text", "required": true, "col": "col-12" },
      { "name": "cuerpo", "label": "Cuerpo ({{variables}})", "type": "textarea", "required": true, "col": "col-12" }
    ],
    "validators": []
  },
  "actions": { "row": [ { "name": "show", "type": "builtin" }, { "name": "edit", "type": "builtin" }, { "name": "delete", "type": "builtin" } ], "bulk": [] },
  "detail": { "tabs": [ { "key": "general", "label": "Datos", "type": "fields", "columns": ["clave","asunto","cuerpo","activo"] } ] },
  "uploads": { "enabled": false, "public_path": "uploads/cruds/mkt_plantillas" },
  "hooks": { "handler": null }
}
```

- [ ] **Step 7: Create `mkt_secuencias.json`**

```json
{
  "resource": {
    "key": "mkt_secuencias",
    "title": "Secuencias",
    "table": "dom_mkt_secuencias",
    "primary_key": "id",
    "permission_prefix": "marketing"
  },
  "security": { "allow_core_table": false, "mode": "restricted" },
  "list": {
    "columns": [
      { "name": "id", "label": "ID", "sortable": true },
      { "name": "nombre", "label": "Nombre", "searchable": true, "sortable": true },
      { "name": "activo", "label": "Activo", "badge": { "1": "success", "0": "secondary" } }
    ],
    "filters": [],
    "actions": ["show", "edit", "delete"]
  },
  "form": {
    "fields": [
      { "name": "nombre", "label": "Nombre", "type": "text", "required": true, "col": "col-md-6" },
      { "name": "activo", "label": "Activo", "type": "select", "options": { "1": "Sí", "0": "No" }, "default": "1", "col": "col-md-6" },
      { "name": "pasos", "label": "Pasos (JSON)", "type": "textarea", "col": "col-12" }
    ],
    "validators": []
  },
  "actions": { "row": [ { "name": "show", "type": "builtin" }, { "name": "edit", "type": "builtin" }, { "name": "delete", "type": "builtin" } ], "bulk": [] },
  "detail": { "tabs": [ { "key": "general", "label": "Datos", "type": "fields", "columns": ["nombre","pasos","activo"] } ] },
  "uploads": { "enabled": false, "public_path": "uploads/cruds/mkt_secuencias" },
  "hooks": { "handler": null }
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php tests/run.php Marketing/CrudConfigsTest`
Expected: PASS (2 passed).

- [ ] **Step 9: Commit**

```bash
git add config/cruds/mkt_*.json tests/Marketing/CrudConfigsTest.php
git commit -m "feat(marketing): configuraciones CRUD de contenido (leads, paquetes, bloques, plantillas, secuencias)"
```

---

### Task 12: Providers default de contenido + repositorio PDO + conexión a la landing

**Files:**
- Create: `app/Infrastructure/Repositories/PdoMarketingContentRepository.php`
- Create: `app/Infrastructure/Marketing/CrudLandingContentProvider.php`
- Create: `app/Infrastructure/Marketing/CrudCommercialPackageSource.php`
- Create: `app/Application/Marketing/RenderLandingUseCase.php`
- Modify: `app/Presentation/Controllers/Publico/LandingController.php`
- Modify: `config/container.php`
- Test: `tests/Marketing/ContentProvidersTest.php`

**Interfaces:**
- Consumes: `MarketingContentRepositoryInterface` (Task 6), `LandingContentProviderInterface`, `CommercialPackageSourceInterface`.
- Produces:
  - `CrudLandingContentProvider(MarketingContentRepositoryInterface $repo)` impl de `LandingContentProviderInterface`.
  - `CrudCommercialPackageSource(MarketingContentRepositoryInterface $repo)` impl de `CommercialPackageSourceInterface`.
  - `RenderLandingUseCase(LandingContentProviderInterface, CommercialPackageSourceInterface)`; método `ejecutar(string $pagina='home'): array` ⇒ `['bloques'=>..., 'paquetes'=>...]`.
  - `LandingController` ahora consume `RenderLandingUseCase`.

- [ ] **Step 1: Write the failing test (with fakes — no DB)**

```php
<?php
// tests/Marketing/ContentProvidersTest.php
declare(strict_types=1);

use App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface;
use App\Infrastructure\Marketing\CrudLandingContentProvider;
use App\Infrastructure\Marketing\CrudCommercialPackageSource;
use App\Application\Marketing\RenderLandingUseCase;

function fakeContentRepo(): MarketingContentRepositoryInterface
{
    return new class implements MarketingContentRepositoryInterface {
        public function bloquesPorPagina(string $pagina): array {
            return $pagina === 'home'
                ? ['hero' => ['titulo' => 'Bienvenido', 'subtitulo' => 'Sub', 'cta_texto' => 'Demo', 'cta_url' => '#demo']]
                : [];
        }
        public function paquetesActivos(): array {
            return [['nombre' => 'Plan A', 'precio_mensual' => '299', 'destacado' => 1, 'badge' => 'Popular']];
        }
    };
}

test('CrudLandingContentProvider devuelve bloques de la página', function (): void {
    $p = new CrudLandingContentProvider(fakeContentRepo());
    $bloques = $p->getBloques('home');
    assert_same('Bienvenido', $bloques['hero']['titulo']);
    assert_same([], $p->getBloques('inexistente'));
});

test('CrudCommercialPackageSource lista paquetes activos', function (): void {
    $s = new CrudCommercialPackageSource(fakeContentRepo());
    $paquetes = $s->listarPaquetes();
    assert_same(1, count($paquetes));
    assert_same('Plan A', $paquetes[0]['nombre']);
});

test('RenderLandingUseCase compone bloques y paquetes', function (): void {
    $uc = new RenderLandingUseCase(
        new CrudLandingContentProvider(fakeContentRepo()),
        new CrudCommercialPackageSource(fakeContentRepo())
    );
    $vm = $uc->ejecutar('home');
    assert_same('Bienvenido', $vm['bloques']['hero']['titulo']);
    assert_same('Plan A', $vm['paquetes'][0]['nombre']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/ContentProvidersTest`
Expected: FAIL — clases no encontradas.

- [ ] **Step 3: Create the PDO repository**

```php
<?php
// app/Infrastructure/Repositories/PdoMarketingContentRepository.php
declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface;
use App\Kernel\Database\Connection;

final class PdoMarketingContentRepository implements MarketingContentRepositoryInterface
{
    public function bloquesPorPagina(string $pagina): array
    {
        $pdo  = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT clave, contenido FROM dom_mkt_bloques
             WHERE pagina = :pagina AND activo = 1 AND deleted = 0 ORDER BY orden ASC'
        );
        $stmt->execute(['pagina' => $pagina]);

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $contenido = json_decode((string) ($row['contenido'] ?? '{}'), true);
            $out[(string) $row['clave']] = is_array($contenido) ? $contenido : [];
        }
        return $out;
    }

    public function paquetesActivos(): array
    {
        $pdo  = Connection::getInstance();
        $stmt = $pdo->query(
            'SELECT nombre, precio_mensual, precio_anual, features, destacado, badge
             FROM dom_mkt_paquetes WHERE activo = 1 AND deleted = 0 ORDER BY orden ASC'
        );
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $features = json_decode((string) ($row['features'] ?? '[]'), true);
            $row['features'] = is_array($features) ? $features : [];
            $out[] = $row;
        }
        return $out;
    }
}
```

> Note: `Connection::getInstance(): PDO` is the verified singleton accessor (confirmed in `app/Kernel/Database/Connection.php`).

- [ ] **Step 4: Create the content provider**

```php
<?php
// app/Infrastructure/Marketing/CrudLandingContentProvider.php
declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\LandingContentProviderInterface;
use App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface;

final class CrudLandingContentProvider implements LandingContentProviderInterface
{
    public function __construct(
        private readonly MarketingContentRepositoryInterface $repo
    ) {}

    public function getBloques(string $pagina): array
    {
        return $this->repo->bloquesPorPagina($pagina);
    }
}
```

- [ ] **Step 5: Create the package source**

```php
<?php
// app/Infrastructure/Marketing/CrudCommercialPackageSource.php
declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\CommercialPackageSourceInterface;
use App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface;

final class CrudCommercialPackageSource implements CommercialPackageSourceInterface
{
    public function __construct(
        private readonly MarketingContentRepositoryInterface $repo
    ) {}

    public function listarPaquetes(): array
    {
        return $this->repo->paquetesActivos();
    }
}
```

- [ ] **Step 6: Create the use case**

```php
<?php
// app/Application/Marketing/RenderLandingUseCase.php
declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LandingContentProviderInterface;
use App\Domain\Marketing\Contracts\CommercialPackageSourceInterface;

final class RenderLandingUseCase
{
    public function __construct(
        private readonly LandingContentProviderInterface $contenido,
        private readonly CommercialPackageSourceInterface $paquetes
    ) {}

    /** @return array{bloques: array<string,array<string,mixed>>, paquetes: list<array<string,mixed>>} */
    public function ejecutar(string $pagina = 'home'): array
    {
        return [
            'bloques'  => $this->contenido->getBloques($pagina),
            'paquetes' => $this->paquetes->listarPaquetes(),
        ];
    }
}
```

- [ ] **Step 7: Wire LandingController to the use case**

Replace `LandingController` body:

```php
<?php
// app/Presentation/Controllers/Publico/LandingController.php
declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Application\Services\ConfiguracionService;
use App\Application\Marketing\RenderLandingUseCase;

final class LandingController extends BaseController
{
    public function __construct(
        private readonly ConfiguracionService $configuracionService,
        private readonly RenderLandingUseCase $renderLanding
    ) {}

    public function index(Request $request): Response
    {
        $vm = $this->renderLanding->ejecutar('home');

        return $this->view('publico/landing', [
            'empresaNombre' => $this->configuracionService->empresaNombre(),
            'empresaLogo'   => $this->configuracionService->empresaLogo(),
            'bloques'       => $vm['bloques'],
            'paquetes'      => $vm['paquetes'],
        ], 'publico/layout');
    }
}
```

- [ ] **Step 8: Wire the container (inside marketing toggle block)**

In `config/container.php`, inside the marketing `if` block, add bindings and update the LandingController binding:

```php
        $container->singleton(\App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class,
            fn() => new \App\Infrastructure\Repositories\PdoMarketingContentRepository());

        $container->singleton(\App\Domain\Marketing\Contracts\LandingContentProviderInterface::class,
            fn(Container $c) => new \App\Infrastructure\Marketing\CrudLandingContentProvider(
                $c->get(\App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class)));

        $container->singleton(\App\Domain\Marketing\Contracts\CommercialPackageSourceInterface::class,
            fn(Container $c) => new \App\Infrastructure\Marketing\CrudCommercialPackageSource(
                $c->get(\App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class)));

        $container->singleton(\App\Application\Marketing\RenderLandingUseCase::class,
            fn(Container $c) => new \App\Application\Marketing\RenderLandingUseCase(
                $c->get(\App\Domain\Marketing\Contracts\LandingContentProviderInterface::class),
                $c->get(\App\Domain\Marketing\Contracts\CommercialPackageSourceInterface::class)));

        $container->bind(\App\Presentation\Controllers\Publico\LandingController::class,
            fn(Container $c) => new \App\Presentation\Controllers\Publico\LandingController(
                $c->get(ConfiguracionService::class),
                $c->get(\App\Application\Marketing\RenderLandingUseCase::class)));
```

(Remove the old single-arg LandingController binding from Task 4/7.)

- [ ] **Step 9: Run tests + lint**

Run: `php tests/run.php Marketing/ContentProvidersTest`
Expected: PASS (3 passed).
Run: `php -l app/Infrastructure/Repositories/PdoMarketingContentRepository.php && php -l app/Presentation/Controllers/Publico/LandingController.php && php -l config/container.php`
Expected: `No syntax errors detected`.

- [ ] **Step 10: Commit**

```bash
git add app/Infrastructure/Repositories/PdoMarketingContentRepository.php app/Infrastructure/Marketing/ app/Application/Marketing/RenderLandingUseCase.php app/Presentation/Controllers/Publico/LandingController.php config/container.php tests/Marketing/ContentProvidersTest.php
git commit -m "feat(marketing): providers default de contenido/paquetes y landing conectada a BD"
```

---

## Phase 6 — Captación de leads (Fase 4.6)

### Task 13: Pipeline de captación + formulario público + CSRF + autoresponder

**Files:**
- Create: `app/Infrastructure/Marketing/LeadCapture/PersistLeadHandler.php`, `NotifyInternalHandler.php`, `AutoresponderHandler.php`
- Create: `app/Application/Marketing/CapturarLeadUseCase.php`
- Create: `app/Domain/Marketing/Contracts/LeadRepositoryInterface.php`
- Create: `app/Infrastructure/Repositories/PdoLeadRepository.php`
- Create: `app/Presentation/Controllers/Publico/LeadController.php`
- Modify: `routes/marketing.php` (POST público con CSRF)
- Modify: `app/Presentation/Views/publico/landing.php` (formulario demo)
- Modify: `config/container.php`
- Test: `tests/Marketing/LeadCaptureTest.php`

**Interfaces:**
- Produces:
  - `LeadRepositoryInterface::guardar(LeadDraft $draft): int` (devuelve id).
  - `PersistLeadHandler(LeadRepositoryInterface)` impl `LeadCaptureHandlerInterface` — persiste y rellena `leadId`.
  - `NotifyInternalHandler(MailerInterface, string $destino)` — notifica interno (no-op si destino vacío).
  - `AutoresponderHandler(MailerInterface)` — correo de agradecimiento al lead.
  - `CapturarLeadUseCase(list<LeadCaptureHandlerInterface> $handlers)`; `ejecutar(LeadDraft): LeadResult` recorre la cadena, abortando si un paso devuelve `ok()===false`.
  - `LeadController::capturar(Request): Response` — valida, construye `LeadDraft`, llama al use case, redirige con flash.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/LeadCaptureTest.php
declare(strict_types=1);

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;
use App\Application\Marketing\CapturarLeadUseCase;
use App\Infrastructure\Marketing\LeadCapture\PersistLeadHandler;

test('PersistLeadHandler guarda y rellena leadId', function (): void {
    $repo = new class implements LeadRepositoryInterface {
        public function guardar(LeadDraft $draft): int { return 7; }
    };
    $h = new PersistLeadHandler($repo);
    $res = $h->handle(new LeadDraft('Ana', 'ana@x.com'), new LeadResult(true));
    assert_same(true, $res->ok());
    assert_same(7, $res->leadId());
});

test('CapturarLeadUseCase recorre la cadena en orden', function (): void {
    $marca = [];
    $h1 = new class($marca) implements LeadCaptureHandlerInterface {
        public function __construct(private array &$m) {}
        public function handle(LeadDraft $d, LeadResult $r): LeadResult { $this->m[] = 'a'; return $r->withLeadId(1); }
    };
    $h2 = new class($marca) implements LeadCaptureHandlerInterface {
        public function __construct(private array &$m) {}
        public function handle(LeadDraft $d, LeadResult $r): LeadResult { $this->m[] = 'b'; return $r; }
    };
    $uc = new CapturarLeadUseCase([$h1, $h2]);
    $res = $uc->ejecutar(new LeadDraft('Ana', 'ana@x.com'));
    assert_same(['a','b'], $marca);
    assert_same(true, $res->ok());
    assert_same(1, $res->leadId());
});

test('CapturarLeadUseCase aborta la cadena si un paso falla', function (): void {
    $marca = [];
    $falla = new class implements LeadCaptureHandlerInterface {
        public function handle(LeadDraft $d, LeadResult $r): LeadResult { return new LeadResult(false, null, ['x' => 'no']); }
    };
    $nuncaCorre = new class($marca) implements LeadCaptureHandlerInterface {
        public function __construct(private array &$m) {}
        public function handle(LeadDraft $d, LeadResult $r): LeadResult { $this->m[] = 'corrió'; return $r; }
    };
    $uc = new CapturarLeadUseCase([$falla, $nuncaCorre]);
    $res = $uc->ejecutar(new LeadDraft('Ana', 'ana@x.com'));
    assert_same(false, $res->ok());
    assert_same([], $marca);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/LeadCaptureTest`
Expected: FAIL — clases no encontradas.

- [ ] **Step 3: Create the lead repository interface + PDO impl**

```php
<?php
// app/Domain/Marketing/Contracts/LeadRepositoryInterface.php
declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

use App\Domain\Marketing\ValueObjects\LeadDraft;

interface LeadRepositoryInterface
{
    /** Persiste un lead y devuelve su id. */
    public function guardar(LeadDraft $draft): int;
}
```

```php
<?php
// app/Infrastructure/Repositories/PdoLeadRepository.php
declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Kernel\Database\Connection;

final class PdoLeadRepository implements LeadRepositoryInterface
{
    public function guardar(LeadDraft $draft): int
    {
        $pdo = Connection::getInstance();
        $utm = $draft->utm();
        $stmt = $pdo->prepare(
            'INSERT INTO dom_mkt_leads (nombre, email, telefono, mensaje, estado, utm_source, utm_medium, utm_campaign)
             VALUES (:nombre, :email, :telefono, :mensaje, :estado, :s, :m, :c)'
        );
        $stmt->execute([
            'nombre'   => $draft->nombre(),
            'email'    => $draft->email(),
            'telefono' => $draft->telefono(),
            'mensaje'  => $draft->mensaje(),
            'estado'   => 'pendiente',
            's'        => $utm['utm_source']   ?? null,
            'm'        => $utm['utm_medium']   ?? null,
            'c'        => $utm['utm_campaign'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }
}
```

- [ ] **Step 4: Create the handlers**

```php
<?php
// app/Infrastructure/Marketing/LeadCapture/PersistLeadHandler.php
declare(strict_types=1);

namespace App\Infrastructure\Marketing\LeadCapture;

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;

final class PersistLeadHandler implements LeadCaptureHandlerInterface
{
    public function __construct(private readonly LeadRepositoryInterface $repo) {}

    public function handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult
    {
        $id = $this->repo->guardar($draft);
        return $resultadoPrevio->withLeadId($id);
    }
}
```

```php
<?php
// app/Infrastructure/Marketing/LeadCapture/NotifyInternalHandler.php
declare(strict_types=1);

namespace App\Infrastructure\Marketing\LeadCapture;

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;
use App\Domain\Interfaces\MailerInterface;
use App\Application\DTO\Mail\MensajeCorreo;

final class NotifyInternalHandler implements LeadCaptureHandlerInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $destino = ''
    ) {}

    public function handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult
    {
        if ($this->destino === '') {
            return $resultadoPrevio; // sin destino configurado: paso inerte
        }
        $html = 'Email: ' . htmlspecialchars($draft->email())
              . '<br>Tel: ' . htmlspecialchars($draft->telefono() ?? '-')
              . '<br><br>' . nl2br(htmlspecialchars($draft->mensaje() ?? ''));
        $this->mailer->enviar(new MensajeCorreo(
            $this->destino,
            'Equipo',
            'Nuevo lead: ' . $draft->nombre(),
            $html
        ));
        return $resultadoPrevio;
    }
}
```

```php
<?php
// app/Infrastructure/Marketing/LeadCapture/AutoresponderHandler.php
declare(strict_types=1);

namespace App\Infrastructure\Marketing\LeadCapture;

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;
use App\Domain\Interfaces\MailerInterface;
use App\Application\DTO\Mail\MensajeCorreo;

final class AutoresponderHandler implements LeadCaptureHandlerInterface
{
    public function __construct(private readonly MailerInterface $mailer) {}

    public function handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult
    {
        $cuerpo = str_replace('{{nombre}}', htmlspecialchars($draft->nombre()), 'Hola {{nombre}}, recibimos tu solicitud y te contactaremos pronto.');
        $this->mailer->enviar(new MensajeCorreo(
            $draft->email(),
            $draft->nombre(),
            'Gracias por tu interés',
            $cuerpo
        ));
        return $resultadoPrevio;
    }
}
```

> Note: verified API — `MailerInterface::enviar(MensajeCorreo $mensaje): void` where `MensajeCorreo(string $destinatario, string $nombreDestinatario, string $asunto, string $html)` lives in `app/Application/DTO/Mail/MensajeCorreo.php`. The container already binds `MailerInterface` to `PhpMailerMailer`/`LogMailer`.

- [ ] **Step 5: Create the use case**

```php
<?php
// app/Application/Marketing/CapturarLeadUseCase.php
declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;

final class CapturarLeadUseCase
{
    /** @param list<LeadCaptureHandlerInterface> $handlers */
    public function __construct(private readonly array $handlers) {}

    public function ejecutar(LeadDraft $draft): LeadResult
    {
        $resultado = new LeadResult(true);
        foreach ($this->handlers as $handler) {
            $resultado = $handler->handle($draft, $resultado);
            if (!$resultado->ok()) {
                return $resultado; // aborta la cadena
            }
        }
        return $resultado;
    }
}
```

- [ ] **Step 6: Create the LeadController**

```php
<?php
// app/Presentation/Controllers/Publico/LeadController.php
declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Application\Marketing\CapturarLeadUseCase;
use App\Domain\Marketing\ValueObjects\LeadDraft;

final class LeadController extends BaseController
{
    public function __construct(private readonly CapturarLeadUseCase $capturarLead) {}

    public function capturar(Request $request): Response
    {
        $this->verifyCsrf($request);

        $nombre = trim((string) $request->input('nombre', ''));
        $email  = trim((string) $request->input('email', ''));

        if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Revisa tu nombre y correo.');
            return $this->redirect('/#demo');
        }

        $draft = new LeadDraft(
            $nombre,
            $email,
            trim((string) $request->input('telefono', '')) ?: null,
            trim((string) $request->input('mensaje', '')) ?: null,
            [
                'utm_source'   => (string) $request->input('utm_source', ''),
                'utm_medium'   => (string) $request->input('utm_medium', ''),
                'utm_campaign' => (string) $request->input('utm_campaign', ''),
            ]
        );

        $res = $this->capturarLead->ejecutar($draft);
        Session::flash(
            $res->ok() ? 'success' : 'error',
            $res->ok() ? '¡Gracias! Te contactaremos pronto.' : 'No pudimos registrar tu solicitud.'
        );
        return $this->redirect('/#demo');
    }
}
```

- [ ] **Step 7: Add the public POST route (CSRF)**

In `routes/marketing.php`, replace the `// (Task 13 ...)` comment with:

```php
use App\Presentation\Controllers\Publico\LeadController;

$router->post('/lead', [LeadController::class, 'capturar'], [CsrfMiddleware::class]);
```

- [ ] **Step 8: Add the demo form to the landing view**

In `app/Presentation/Views/publico/landing.php`, append a form section at the end:

```php
<section class="py-5 bg-white" id="demo">
    <div class="container" style="max-width:560px;">
        <h2 class="h4 text-center mb-3">Solicita una demo</h2>
        <?php foreach (($flashAll ?? \App\Kernel\Security\Session::flashAll()) as $tipo => $msg): ?>
            <?php if (in_array($tipo, ['success','error'], true)): ?>
                <div class="alert alert-<?= $tipo === 'success' ? 'success' : 'danger' ?>"><?= ViewHelper::e(is_array($msg) ? implode(' ', $msg) : (string) $msg) ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
        <form method="POST" action="/lead">
            <?= ViewHelper::csrfField() ?>
            <div class="mb-3">
                <input type="text" name="nombre" class="form-control" placeholder="Nombre" required>
            </div>
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="Correo" required>
            </div>
            <div class="mb-3">
                <input type="text" name="telefono" class="form-control" placeholder="Teléfono (opcional)">
            </div>
            <div class="mb-3">
                <textarea name="mensaje" class="form-control" rows="3" placeholder="¿En qué te ayudamos?"></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100">Enviar</button>
        </form>
    </div>
</section>
```

- [ ] **Step 9: Wire the container (inside marketing toggle block)**

In `config/container.php`, inside the marketing block:

```php
        $container->singleton(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class,
            fn() => new \App\Infrastructure\Repositories\PdoLeadRepository());

        $container->singleton(\App\Application\Marketing\CapturarLeadUseCase::class, function (Container $c) {
            $notifDestino = (string) Config::get('vertical.modules.marketing') ? '' : '';
            return new \App\Application\Marketing\CapturarLeadUseCase([
                new \App\Infrastructure\Marketing\LeadCapture\PersistLeadHandler(
                    $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class)),
                new \App\Infrastructure\Marketing\LeadCapture\NotifyInternalHandler(
                    $c->get(\App\Domain\Interfaces\MailerInterface::class),
                    (string) $c->get(ConfiguracionService::class)->get('mkt_mail_from', '')),
                new \App\Infrastructure\Marketing\LeadCapture\AutoresponderHandler(
                    $c->get(\App\Domain\Interfaces\MailerInterface::class)),
            ]);
        });

        $container->bind(\App\Presentation\Controllers\Publico\LeadController::class,
            fn(Container $c) => new \App\Presentation\Controllers\Publico\LeadController(
                $c->get(\App\Application\Marketing\CapturarLeadUseCase::class)));
```

(Remove the dead `$notifDestino` line if not needed; it is illustrative — use the `mkt_mail_from` setting as the internal notification target or a dedicated setting.)

- [ ] **Step 10: Run tests + lint**

Run: `php tests/run.php Marketing/LeadCaptureTest`
Expected: PASS (3 passed).
Run: `php -l routes/marketing.php && php -l app/Presentation/Controllers/Publico/LeadController.php && php -l app/Presentation/Views/publico/landing.php && php -l config/container.php`
Expected: `No syntax errors detected`.

- [ ] **Step 11: Commit**

```bash
git add app/Domain/Marketing/Contracts/LeadRepositoryInterface.php app/Infrastructure/Repositories/PdoLeadRepository.php app/Infrastructure/Marketing/LeadCapture/ app/Application/Marketing/CapturarLeadUseCase.php app/Presentation/Controllers/Publico/LeadController.php routes/marketing.php app/Presentation/Views/publico/landing.php config/container.php tests/Marketing/LeadCaptureTest.php
git commit -m "feat(marketing): pipeline de captacion de leads con formulario publico y autoresponder"
```

---

## Phase 7 — Portal cliente genérico + adaptador de aprovisionamiento (Fase 4.7)

### Task 14: Magic-link genérico + sesión cliente + `ProvisionAdapterInterface` sin implementación

**Files:**
- Create: `app/Domain/Marketing/ValueObjects/MagicLinkToken.php`
- Create: `app/Presentation/Controllers/Publico/PortalClienteController.php`
- Create: `app/Presentation/Views/publico/portal.php`
- Modify: `routes/marketing.php` (rutas de portal)
- Modify: `config/container.php`
- Test: `tests/Marketing/MagicLinkTest.php`

**Interfaces:**
- Consumes: `dom_mkt_provisiones(access_token, expira_en, estado, payload)`, `ProvisionAdapterInterface` (Task 6 — opcional, puede no haber ninguno registrado).
- Produces:
  - `MagicLinkToken`: `MagicLinkToken::generar(): self` (64 hex de `random_bytes(32)`), `valor(): string`, `MagicLinkToken::esFormatoValido(string): bool`.
  - `PortalClienteController::entrar(Request): Response` — valida token de la query, abre sesión cliente, muestra portal; sin adaptador registrado ⇒ portal mínimo (CMS/captación sin aprovisionamiento).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/MagicLinkTest.php
declare(strict_types=1);

use App\Domain\Marketing\ValueObjects\MagicLinkToken;

test('MagicLinkToken genera 64 hex', function (): void {
    $t = MagicLinkToken::generar();
    assert_same(64, strlen($t->valor()));
    assert_same(1, preg_match('/^[0-9a-f]{64}$/', $t->valor()));
});

test('dos tokens generados difieren', function (): void {
    assert_true(MagicLinkToken::generar()->valor() !== MagicLinkToken::generar()->valor());
});

test('esFormatoValido distingue tokens bien formados', function (): void {
    assert_same(true, MagicLinkToken::esFormatoValido(str_repeat('a', 64)));
    assert_same(false, MagicLinkToken::esFormatoValido('corto'));
    assert_same(false, MagicLinkToken::esFormatoValido(str_repeat('Z', 64)));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/MagicLinkTest`
Expected: FAIL — clase no encontrada.

- [ ] **Step 3: Create the MagicLinkToken VO**

```php
<?php
// app/Domain/Marketing/ValueObjects/MagicLinkToken.php
declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class MagicLinkToken
{
    private function __construct(private readonly string $valor) {}

    public static function generar(): self
    {
        return new self(bin2hex(random_bytes(32)));
    }

    public static function desde(string $valor): self
    {
        return new self($valor);
    }

    public static function esFormatoValido(string $valor): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $valor) === 1;
    }

    public function valor(): string
    {
        return $this->valor;
    }
}
```

- [ ] **Step 4: Create the PortalClienteController**

```php
<?php
// app/Presentation/Controllers/Publico/PortalClienteController.php
declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Application\Services\ConfiguracionService;
use App\Domain\Marketing\ValueObjects\MagicLinkToken;
use App\Kernel\Database\Connection;

final class PortalClienteController extends BaseController
{
    public function __construct(private readonly ConfiguracionService $configuracionService) {}

    public function entrar(Request $request): Response
    {
        $token = (string) $request->input('token', '');

        if (!MagicLinkToken::esFormatoValido($token)) {
            return $this->view('publico/portal', $this->vm('Enlace inválido o expirado.', null), 'publico/layout');
        }

        $pdo  = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT id, lead_id, estado, expira_en FROM dom_mkt_provisiones
             WHERE access_token = :t AND deleted = 0 LIMIT 1'
        );
        $stmt->execute(['t' => $token]);
        $prov = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($prov === null || ($prov['expira_en'] !== null && strtotime((string) $prov['expira_en']) < time())) {
            return $this->view('publico/portal', $this->vm('Enlace inválido o expirado.', null), 'publico/layout');
        }

        // Abre sesión cliente (NO toca la sesión admin/auth_user).
        Session::set('portal_cliente', ['provision_id' => (int) $prov['id'], 'lead_id' => $prov['lead_id']]);

        return $this->view('publico/portal', $this->vm('Bienvenido a tu portal.', $prov), 'publico/layout');
    }

    /** @param array<string,mixed>|null $prov */
    private function vm(string $mensaje, ?array $prov): array
    {
        return [
            'empresaNombre' => $this->configuracionService->empresaNombre(),
            'empresaLogo'   => $this->configuracionService->empresaLogo(),
            'mensaje'       => $mensaje,
            'provision'     => $prov,
        ];
    }
}
```

> Note: verified — `Session::set(string $key, mixed $value): void` exists in `app/Kernel/Security/Session.php`. Using a distinct key (`portal_cliente`) keeps the client session separate from the admin `auth_user` session.

- [ ] **Step 5: Create the portal view**

```php
<?php
// app/Presentation/Views/publico/portal.php

use App\Kernel\Helpers\ViewHelper;

$mensaje   = $mensaje ?? '';
$provision = $provision ?? null;
?>
<section class="py-5">
    <div class="container" style="max-width:640px;">
        <div class="card">
            <div class="card-body">
                <h1 class="h4 mb-3">Portal del cliente</h1>
                <p class="text-muted"><?= ViewHelper::e($mensaje) ?></p>
                <?php if ($provision !== null): ?>
                    <p class="small mb-0">Estado de tu provisión: <strong><?= ViewHelper::e((string) ($provision['estado'] ?? 'pendiente')) ?></strong></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 6: Add the portal route**

In `routes/marketing.php`, add:

```php
use App\Presentation\Controllers\Publico\PortalClienteController;

$router->get('/portal', [PortalClienteController::class, 'entrar']);
```

- [ ] **Step 7: Wire the container (inside marketing toggle block)**

```php
        $container->bind(\App\Presentation\Controllers\Publico\PortalClienteController::class,
            fn(Container $c) => new \App\Presentation\Controllers\Publico\PortalClienteController(
                $c->get(ConfiguracionService::class)));
```

(No `ProvisionAdapterInterface` binding: the cimientos ship without a concrete adapter — the portal works as CMS/captación sin aprovisionamiento. A future vertical registers an adapter here.)

- [ ] **Step 8: Run tests + lint**

Run: `php tests/run.php Marketing/MagicLinkTest`
Expected: PASS (3 passed).
Run: `php -l app/Presentation/Controllers/Publico/PortalClienteController.php && php -l app/Presentation/Views/publico/portal.php && php -l routes/marketing.php && php -l config/container.php`
Expected: `No syntax errors detected`.

- [ ] **Step 9: Run the full suite**

Run: `php tests/run.php`
Expected: all green (Marketing tests + existing suite, no regression).

- [ ] **Step 10: Commit**

```bash
git add app/Domain/Marketing/ValueObjects/MagicLinkToken.php app/Presentation/Controllers/Publico/PortalClienteController.php app/Presentation/Views/publico/portal.php routes/marketing.php config/container.php tests/Marketing/MagicLinkTest.php
git commit -m "feat(marketing): portal cliente generico con magic-link (sin adaptador concreto)"
```

---

## Final Verification (criterios de aceptación del spec §10)

### Task 15: Verificación de aceptación end-to-end

**Files:** N/A (verificación + documentación)

- [ ] **Step 1: Toggle OFF — comportamiento idéntico al actual**

Set `config/vertical.php → modules.marketing = false`. Run:
```bash
php -S localhost:8000 -t public
```
Verify (manual/curl): `GET /` ⇒ login; no aparece menú "Marketing"; `GET /admin/ajustes` no muestra secciones de Marketing; `GET /lead` ⇒ 404.
Run: `php tests/run.php`
Expected: todo verde.

- [ ] **Step 2: Toggle ON — módulo activo**

Set `modules.marketing = true`, ejecutar el bootstrap del módulo (instalador/`marketing.sql`) contra la BD de pruebas. Verify:
- `GET /` ⇒ landing pública desde `dom_mkt_bloques` (hero demo visible).
- Login sigue en `/login`.
- Menú "Marketing" aparece (con RBAC de admin); `/admin/crud/mkt_paquetes` lista el paquete demo.
- `GET /admin/ajustes` muestra las 4 secciones de Marketing; guardar persiste claves `mkt_*` y **también** los campos de sistema (sin regresión).
- `POST /lead` con CSRF crea un registro en `dom_mkt_leads` y dispara el autoresponder (vía LogMailer en entorno de pruebas).

- [ ] **Step 3: Verificar desacople**

Run: `grep -RIl "App\\\\.*Marketing" app/Kernel app/Domain/Interfaces app/Application/Services | grep -v SettingsSection`
Expected: sin resultados que indiquen que el núcleo referencia clases concretas de Marketing (solo interfaces transversales en `app/Domain/Interfaces` y el registry genérico están permitidos).

- [ ] **Step 4: Volver el toggle a OFF para el merge**

Set `modules.marketing = false` (estado de entrega de cimientos). Run: `php tests/run.php` ⇒ verde.

- [ ] **Step 5: Commit final + nota de cierre**

```bash
git add config/vertical.php
git commit -m "chore(marketing): cierre de cimientos; toggle off por defecto para entrega"
```

---

## Self-Review

**Spec coverage:**
- §2 decisiones (alcance, front público, empaquetado por capas, descarte WhatsApp, A+B, Ajustes centralizado, raíz condicional) → Tasks 1-14.
- §3 estructura por capas → File Structure + Tasks 4-14.
- §4 contratos de extensión → Task 6 (4 interfaces + repo interface) + Task 12 (defaults).
- §5 Ajustes extensible (`SettingsSectionProviderInterface`, refactor index/guardar, vista) → Tasks 8-10; 4 secciones → Task 9.
- §6 modelo de datos (7 tablas, payload JSON, magic-link, sin FK cross-módulo) → Task 2; CRUDs → Task 11.
- §7 manifiesto, toggle, registro condicional, bootstrap idempotente, RBAC/menú, raíz condicional → Tasks 1, 2, 4, 7.
- §8 plan de refactor (7 hitos) → Phases 1-7; archivos del núcleo afectados (vertical, web, container, AjustesController, ajustes/index) → Tasks 1,4,9,10,12,13,14; riesgos (precedencia `/`, regresión Ajustes, seguridad credenciales, dependencias) → Tasks 3,4,10,15.
- §10 criterios de aceptación → Task 15.

**Placeholder scan:** No quedan TODOs sin código; cada paso de código incluye el código. Tres "Note:" verifican firmas reales antes de implementar (`Connection::pdo()`, `MailerInterface::send()`, `Session::set()`) — son verificaciones obligatorias, no placeholders.

**Type consistency:** `LeadResult::withLeadId()` usado en Tasks 6/13 coincide. `MarketingContentRepositoryInterface::{bloquesPorPagina,paquetesActivos}` consistente en Tasks 6/12. `LeadCaptureHandlerInterface::handle(LeadDraft, LeadResult): LeadResult` consistente Tasks 6/13. `SettingsSectionRegistry::{visibles,fieldNames}` consistente Tasks 8/10. `permission_prefix='marketing'` consistente Task 2 (permisos) ↔ Task 11 (CRUDs).

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-23-modulo-marketing-cimientos.md`.

**Firmas externas (ya verificadas contra el código y reflejadas en el plan):** `Connection::getInstance(): PDO`; `MailerInterface::enviar(MensajeCorreo)` con `MensajeCorreo(destinatario, nombreDestinatario, asunto, html)`; `Session::set(key, value)`. No quedan incógnitas de API pendientes.

**Two execution options:**

**1. Subagent-Driven (recommended)** — despacho un subagente fresco por task, reviso entre tasks, iteración rápida.

**2. Inline Execution** — ejecuto los tasks en esta sesión con executing-plans, por lotes con checkpoints de revisión.

¿Qué enfoque prefieres?
