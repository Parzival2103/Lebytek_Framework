# Diseño — Cimientos del módulo "Marketing y Contenido Público"

- **Fecha:** 2026-06-23
- **Estado:** Aprobado (diseño) — pendiente de plan de implementación
- **Alcance:** Cimientos del módulo desacoplable (esqueleto + registro + integración con Ajustes + contratos de extensión). Los subsistemas funcionales (CMS, paquetes, leads, email marketing, portal cliente) se listan como roadmap y tendrán su propio spec cada uno.
- **Origen:** Análisis de la carpeta `nuevo_modulo/` (app WhatsApp SaaS en PHP plano) para portarla al framework Lebytek.

---

## 1. Contexto y análisis de origen

`nuevo_modulo/` es una aplicación PHP **plana y procedural**, autocontenida, con su propio `vendor/` (PHPMailer). No comparte infraestructura con el framework y duplica casi todo lo que el framework ya provee.

| Aspecto | `nuevo_modulo` | Framework Lebytek |
|---|---|---|
| Routing | `public/index.php` con `switch($_GET['page'])` (~25 rutas) | Router con grupos + middleware, `routes/web.php` |
| Estilo | Funciones globales, sin namespaces, `$config` array | Onion/MVC, namespaces, DI container |
| Auth | `auth.php` (admin) + `client_auth.php` (magic-link) | RBAC + `AuthMiddleware` + sesión |
| CSRF / rate-limit | `security.php` propio | `CsrfMiddleware`, `LoginRateLimitService` |
| Config | overlay propio: tabla `settings` (kv) sobre `config.php` | `cfg_*` + `ConfiguracionService` + `AjustesController` |
| Correo | `mailer.php` (PHPMailer directo) | `PhpMailerMailer` + `config/mail.php` (ENV) |
| BD | `get_db()` PDO propio; tablas sin prefijo | PDO singleton; prefijos obligatorios (`dom_*`, `cfg_*`, `log_*`) |

### Flujos de negocio a preservar (valor reutilizable)
- **Captación:** landing pública → formulario demo → tabla de leads → correo de agradecimiento automático + notificación interna.
- **CRM de leads:** panel admin con estados (`pendiente → validada → demo_enviada → rechazada`).
- **Aprovisionamiento + portal cliente:** admin crea un recurso aprovisionado → genera magic-link token (64 hex) → correo con enlace → cliente entra sin password.
- **Gestión de contenido:** hero, paquetes/precios (mensual/anual), testimonios — hoy **hardcodeados** en `content.php`. Editarlos desde admin estaba marcado como "fuera de alcance".
- **Plantillas de correo editables:** tabla `email_templates` con variables `{{nombre}}`, etc.

### Acoplamiento al ejemplo WhatsApp (NO genérico)
`green_api.php`, vistas `client/whatsapp.php`, `whatsapp.js`, columnas `id_instance/api_token_instance/whatsapp_estado`. Es el dominio del ejemplo: provisión de instancias de WhatsApp. **Se descarta del núcleo** y queda como referencia documentada.

### Hallazgo de seguridad
`nuevo_modulo/config.example.php` contiene **credenciales reales** (password BD/SMTP `Qazzaqwerrew1B` y un hash bcrypt de admin). Acción requerida: rotar esas credenciales y eliminar/limpiar el archivo de ejemplo del módulo legado.

---

## 2. Decisiones de diseño aprobadas

1. **Alcance:** cimientos primero (esqueleto del módulo desacoplable).
2. **Front público:** el framework sirve el sitio público (capa de rutas públicas + layouts públicos en la misma base de código).
3. **Empaquetado:** se sigue la convención existente (`reportes`/`calendario`): código distribuido por capas con sub-namespace por módulo + manifiesto + toggle. **No** se crea `app/Modules/`.
4. **Ejemplo WhatsApp:** se descarta del núcleo; se diseñan puntos de extensión genéricos; WhatsApp queda como referencia documentada.
5. **Enfoque arquitectónico:** A + B → esqueleto/contratos de extensión + apoyo en el CRUD Engine para gestión de contenido.
6. **Configuración centralizada:** la config del módulo se integra **dentro** de la pantalla de Ajustes existente mediante un patrón de "proveedores de sección de Ajustes".
7. **Ruta raíz:** con el módulo activo, `GET /` → landing pública; con el módulo inactivo, `GET /` → login (comportamiento actual).

---

## 3. Arquitectura y estructura del módulo

**Identidad:** clave `marketing`; nombre "Marketing y Contenido Público"; manifiesto `config/modules/marketing.php`; toggle `config/vertical.php → modules.marketing`; requiere `['core','crud-engine']`.

**Distribución por capas:**
```
app/Domain/Marketing/            ← interfaces de extensión + entidades/VOs
app/Application/Marketing/       ← UseCases (render landing, capturar lead, secciones de ajustes)
app/Infrastructure/Repositories/ ← Pdo*Repository (implementan interfaces Domain)
app/Presentation/Controllers/Publico/           ← controladores de rutas PÚBLICAS (sin auth)
app/Presentation/Controllers/Admin/Marketing/   ← gestión admin
app/Presentation/Views/publico/  ← layout + vistas públicas
app/Presentation/Views/admin/marketing/         ← pantallas admin del módulo
config/cruds/mkt_*.json          ← recursos de contenido vía CRUD Engine
config/marketing/                ← config declarativa (páginas, embudos)
database/schema/modules/marketing.sql           ← bootstrap (tablas, permisos, menú, demo)
routes/web.php                   ← bloque condicional al toggle (rutas públicas + admin)
```

**Capa de rutas públicas (nuevo en el framework):** grupo público sin `AuthMiddleware`, con layout público, registrado solo si `modules.marketing` está activo. Los POST públicos llevan `CsrfMiddleware`.

**Regla de desacople:** ningún archivo del núcleo referencia clases de Marketing. El acoplamiento es siempre inverso (Marketing implementa interfaces del núcleo) o por registro declarativo (manifiesto, binding condicional, providers). Toggle off ⇒ módulo inerte.

---

## 4. Contratos de extensión (`app/Domain/Marketing/`)

**1. `LandingContentProviderInterface`** — gestión de contenido público.
```
getBloques(string $pagina): array   // hero, features, CTA, FAQ, testimonios…
```
Default: `CrudLandingContentProvider` que lee de `dom_mkt_bloques`. Hace el contenido editable desde admin (resuelve el hero hardcodeado del original).

**2. `CommercialPackageSourceInterface`** — paquetes comerciales.
```
listarPaquetes(): array   // nombre, precios (mensual/anual), features[], destacado, badge, comparativa
```
Default sobre CRUD (`dom_mkt_paquetes`). Sustituye el array hardcodeado de `content.php`.

**3. `LeadCaptureHandlerInterface`** — captación / embudo (pipeline de handlers).
```
capturar(LeadDraft $draft): LeadResult   // valida, persiste, dispara correos/secuencia, tracking
```
Cadena: captura → notificación interna → autoresponder → enganche a secuencia. Cada paso opcional y desactivable.

**4. `ProvisionAdapterInterface`** — punto de enchufe del dominio específico (WhatsApp u otro; el núcleo no lo trae).
```
aprovisionar(Lead $lead, array $credenciales): ProvisionResult
estado(Provision $p): array
```
El portal cliente con magic-link es capacidad **genérica** del módulo; *qué se aprovisiona* lo decide el adaptador. Sin adaptador registrado, el módulo funciona como CMS + captación sin portal.

**Registro:** implementaciones atadas en `config/container.php` con binding **condicional al toggle** y/o lista declarativa (estilo `config/dashboard.php → providers`). El núcleo de Marketing consume interfaces, nunca implementaciones.

---

## 5. Configuración centralizada (integración con Ajustes)

**Patrón `SettingsSectionProviderInterface`** (transversal, en `app/Domain/`, no en Marketing):
```
clave(): string                 // 'marketing_correo', 'marketing_paquetes'…
titulo(): string                // "Correo y automatizaciones"
permiso(): string               // RBAC requerido para ver/editar
campos(): array                 // definiciones declarativas (label, type, group, secret…)
```

**Conexión con lo existente:**
- `AjustesController::index()` recoge además los providers registrados (filtrados por toggle de módulo + RBAC) y los pasa a la vista como secciones adicionales.
- `AjustesController::guardar()` deja de usar lista fija de campos: recorre también los campos declarados por los providers y los persiste vía `ConfiguracionService::setMultiple()` con claves `cfg_*` namespaced (p.ej. `mkt_mail_host`).
- `admin/ajustes/index.php` renderiza dinámicamente cada sección de provider (tabs/acordeón) tras los ajustes del sistema.

**Secciones que Marketing registra** (mapeadas a la Fase 3):

| Sección | Config |
|---|---|
| Correo / Automatizaciones | SMTP override del módulo, remitente, link a plantillas (CRUD), toggles de secuencias/embudos |
| Paquetes comerciales | Moneda, toggle mensual/anual, paquete destacado por defecto, link al CRUD de paquetes |
| Marketing / Tracking | IDs de analytics/píxel, CTA por defecto, toggles de captación, endpoint de formularios |
| Contenido | Página de inicio activa, toggle de blog/FAQs/testimonios, slug base público |

**SMTP:** Marketing **reutiliza** `PhpMailerMailer` + `config/mail.php`. La sección "Correo" solo guarda *overrides opcionales* (vacío ⇒ usa SMTP global). No se duplica infraestructura de correo.

**Plantillas y secuencias** son contenido editable → recursos CRUD (`dom_mkt_plantillas`, `dom_mkt_secuencias`), no campos sueltos. Desde Ajustes solo hay enlaces y toggles globales.

---

## 6. Modelo de datos

Prefijo `dom_mkt_*`; settings en `cfg_*` namespaced con prefijo `mkt_`. Bootstrap en `database/schema/modules/marketing.sql`.

| Tabla | Origen | Propósito |
|---|---|---|
| `dom_mkt_leads` | `demo_requests` | Captación; estados configurables; `created_by` (scope CRUD); campos UTM/tracking |
| `dom_mkt_provisiones` | `demos` | Genérica: `lead_id`, `access_token` (magic-link), `expira_en`, `estado`, **`payload` JSON** (credenciales/datos del adaptador) |
| `dom_mkt_paquetes` | array `content.php` | nombre, precios, features (JSON), destacado, badge, orden |
| `dom_mkt_bloques` | array `content.php` | Bloques de contenido por página/clave (JSON flexible) |
| `dom_mkt_plantillas` | `email_templates` | Plantillas de correo con variables `{{...}}` |
| `dom_mkt_secuencias` | nuevo | Secuencias/embudos: pasos, retardos, plantilla por paso |
| `dom_mkt_paginas` | nuevo (opcional) | Páginas públicas CMS (slug, título, layout, publicada) |

**Decisiones clave:**
- Credenciales del adaptador en `payload` JSON, no columnas fijas → cualquier dominio sin migración de esquema. Nunca expuestas al cliente.
- Magic-link genérico: token 64 hex (`random_bytes(32)`), expiración configurable (se conserva del original, ya agnóstico).
- CRUD Engine gestiona `paquetes/bloques/plantillas/secuencias/leads` (cada uno con `config/cruds/mkt_*.json`), heredando permisos/scope/forms.
- Sin FKs cross-módulo hacia tablas del núcleo (salvo patrón `created_by`). Toggle off no rompe integridad referencial.
- Se descarta la tabla `settings` del original → se usa `cfg_*` existente.

---

## 7. Registro, toggle y arranque

**Manifiesto** `config/modules/marketing.php`:
```php
return [
  'clave' => 'marketing',
  'nombre' => 'Marketing y Contenido Público',
  'descripcion' => 'CMS público, captación de leads, paquetes y automatizaciones de correo.',
  'version' => '1.0.0',
  'obligatorio' => false,
  'requiere' => ['core', 'crud-engine'],
  'bootstrap_sql' => 'database/schema/modules/marketing.sql',
  'cruds' => ['mkt_leads','mkt_paquetes','mkt_bloques','mkt_plantillas','mkt_secuencias'],
  'permisos' => ['marketing.ver','marketing.gestionar','marketing.leads','marketing.publicar'],
  'menu' => [ /* entradas core_menu_items bajo "Marketing" */ ],
  'providers' => [ /* SettingsSectionProviders + LeadCaptureHandlers */ ],
];
```

**Toggle:** `config/vertical.php → modules.marketing => true`.

**Registro condicional** (corazón del desacople):
```php
if (config('vertical.modules.marketing')) {
    require __DIR__ . '/marketing.php';   // rutas públicas + admin del módulo
}
```
Igual para bindings del container y registro de providers. Toggle off ⇒ cero rutas, cero bindings, cero menú/ajustes.

**Bootstrap idempotente:** `marketing.sql` crea tablas `dom_mkt_*` (`IF NOT EXISTS`), inserta permisos RBAC, entradas de menú bajo padre "Marketing", y datos demo genéricos (un paquete, un bloque hero, una plantilla) — patrón análogo a `reportes`.

**RBAC y menú:** slugs `marketing.*`; el menú dinámico ya filtra por RBAC + toggle, así que aparece/desaparece automáticamente.

### Regla de ruta raíz (resolución condicional)
- **Marketing activo:** `GET /` → `Publico\LandingController` (página principal desde `dom_mkt_bloques` / página de inicio configurada). Admin sigue en `/login`.
- **Marketing inactivo:** `GET /` → `AuthController::showLogin` (sin cambios).

Implementación: la ruta `/` se registra dentro del bloque condicional `marketing.php` con precedencia sobre el `/` por defecto (o el `/` por defecto solo se registra si el módulo está apagado). El plan de implementación debe fijar la precedencia en el router sin ambigüedad.

---

## 8. Plan de refactor (Fase 4)

**Orden recomendado** (cada hito desplegable y deja el sistema verde):

1. **Andamiaje inerte:** manifiesto + toggle (`false`) + `marketing.sql` (tablas, permisos, menú, demo) + permisos RBAC. *Riesgo bajo.*
2. **Rutas públicas + layout público + ruta raíz condicional.** *Riesgo medio* (toca `/`; mitigado por toggle default `false`).
3. **Contratos de extensión** (`app/Domain/Marketing/*Interface.php`) + bindings condicionales. *Riesgo bajo.*
4. **Ajustes extensible:** `SettingsSectionProviderInterface` + refactor `AjustesController::index/guardar` + vista. *Riesgo medio* (pantalla central; cubrir con tests).
5. **CRUDs de contenido** (`config/cruds/mkt_*.json`) + providers default de contenido/paquetes. *Riesgo bajo.*
6. **Captación de leads** (pipeline + formulario público + CSRF + rate-limit + autoresponder vía `PhpMailerMailer`). *Riesgo bajo.*
7. **Portal cliente genérico** (magic-link, sesión cliente) + `ProvisionAdapterInterface` sin implementación concreta. *Riesgo bajo.*

**Archivos del núcleo afectados** (resto es aditivo):
- `config/vertical.php` — +1 línea toggle.
- `routes/web.php` — include condicional + precedencia de `/`.
- `config/container.php` — bindings condicionales.
- `app/Presentation/Controllers/Admin/AjustesController.php` — índice/guardar extensibles.
- `app/Presentation/Views/admin/ajustes/index.php` — render de secciones de providers.

**Riesgos transversales y mitigaciones:**
- *Precedencia de `/`* → test: toggle off ⇒ `/` = login; toggle on ⇒ `/` = landing.
- *Regresión en Ajustes* → test: los campos de sistema actuales siguen guardándose.
- *Seguridad* → rutas públicas POST con CSRF + rate-limit; credenciales de adaptador solo server-side; **rotar credenciales filtradas en `config.example.php`** y eliminar el archivo legado.
- *Dependencias* → `requiere: [core, crud-engine]`; sin crud-engine el manifiesto falla explícito en bootstrap.

**Qué se descarta del original:** `vendor/` propio, `src/db.php`, `src/auth.php`, `src/client_auth.php` (reescrito genérico), `src/security.php`, `src/settings.php`, `src/mailer.php`, el `switch` de `index.php`, `green_api.php` y vistas WhatsApp (referencia documentada, no portadas).

---

## 9. Roadmap de subsistemas (specs posteriores)

Cada uno tendrá su propio ciclo spec → plan → implementación sobre estos cimientos:
1. CMS de contenido público / landing (bloques, páginas, FAQs, testimonios, blog).
2. Paquetes comerciales (precios, promociones, comparativas).
3. Captación de leads + embudo + tracking/conversión.
4. Email marketing / automatizaciones (plantillas, secuencias, embudos).
5. Portal cliente + aprovisionamiento (adaptador de referencia, p.ej. WhatsApp).

---

## 10. Criterios de aceptación (cimientos)

- Con `modules.marketing = false`: el sistema se comporta exactamente como hoy (`/` = login; sin menú/ajustes/rutas de Marketing). Tests verdes.
- Con `modules.marketing = true`: `/` sirve la landing pública desde BD; aparece la sección "Marketing" en menú (según RBAC); la pantalla de Ajustes muestra las secciones del módulo; los CRUDs `mkt_*` funcionan.
- `AjustesController` guarda tanto campos de sistema como campos de providers sin regresión.
- Ningún archivo del núcleo referencia directamente clases de Marketing.
- Bootstrap `marketing.sql` es idempotente y crea permisos/menú/demo.
