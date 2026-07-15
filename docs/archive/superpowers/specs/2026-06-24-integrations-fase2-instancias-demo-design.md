# SPEC — `integrations` Fase 2: gestión de instancias y provisión de demos WhatsApp

> Estado: **diseño aprobado, sin implementar**. Fecha: 2026-06-24.
> Continúa el módulo `integrations` (ver `docs/superpowers/specs/api-connections-module.md`, cuya Fase 1 **ya está implementada en código** pese a que ese documento diga "sin implementar").
> Esta "Fase 2 de mejora" **no es** la "F2 (webhooks + cola)" del SPEC original: es un eje distinto centrado en **gestión de instancias Green API y provisión de demos por cliente**.

---

## 0. Estado verificado de la Fase 1 (punto de partida)

La Fase 1 **está implementada** (verificado en código, 2026-06-24):

- Dominio: `MessageRequest`, `MessageResult`, `MessageChannelInterface`, `ApiConnectorInterface`, `IntegrationLogRepositoryInterface` (`app/Domain/Integrations/`).
- Application: `NotificationDispatcher`, `ChannelRegistry`, `RateLimiter`, `IntegrationsFactory` (`app/Application/Integrations/`).
- Infra: `GreenApiWhatsappChannel`, `EmailChannel`, `HttpApiConnector`, `IntegrationLogRepository` (`app/Infrastructure/Integrations/`).
- Config: `config/integrations.php`, `.env.example` (`GREEN_API_*`), manifiesto `config/modules/integrations.php`, toggle `integrations => true` en `config/vertical.php`.
- DB: `int_logs` + permisos RBAC idempotentes (`database/schema/modules/integrations.sql`).
- CRUD: acción de fila demo `confirmar_wa` (`type: handler` → `EnviarWhatsappDemoHandler`) en `config/cruds/demo_clientes.json`, registrada en `config/crud_handlers.php` como `enviar_whatsapp_demo`.

**Qué se ve hoy en pantalla:** solo el botón **"Confirmar por WhatsApp"** en cada fila de `/admin/crud/demo_clientes`. No hay UI de configuración, ni visor de logs, ni entrada de menú del módulo.

**Por qué probablemente aún no envía:**
1. `.env.example` trae `GREEN_API_ENABLED=false`; hace falta `=true` **y** `GREEN_API_INSTANCE` + `GREEN_API_TOKEN` reales.
2. La instancia Green API debe estar **autorizada** (QR vinculado a un WhatsApp) o la API responde error.

Estos dos puntos los resuelve la UI de esta Fase 2 (configuración + test de conexión + activación por QR).

---

## 1. Resumen ejecutivo

La Fase 2 añade al módulo `integrations`:

1. **`int_accounts`**: fuente única de instancias Green API (la interna del sistema y una por cliente), con **token cifrado en reposo**.
2. **Credenciales desde DB**: los envíos internos usan la instancia marcada `is_default` en `int_accounts` (con `.env` como fallback) — sin tocar el canal ni el dispatcher.
3. **Provisión de demos por cliente** desde una acción de fila en `dom_mkt_leads`: crea/registra una instancia, la liga al lead y le **envía por correo** las credenciales + un **link público de activación (QR)**.
4. **Partner API** (`GreenApiPartnerConnector`) para crear instancias automáticamente, **con fallback manual garantizado** (pegar `instance_id` + `token`) cuando la Partner API no esté disponible.
5. **UI en Ajustes** (sección custom) para configurar la instancia interna, el token de Partner API, probar la conexión y ver los `int_logs`.
6. **Vista pública de activación QR** para que el cliente vincule su WhatsApp.

Principio rector intacto: ningún módulo de negocio conoce Green API; todo envío pasa por `NotificationDispatcher`. La Fase 2 solo cambia **de dónde salen las credenciales** y **cómo se da de alta una instancia**.

---

## 2. Decisiones confirmadas (brainstorming 2026-06-24)

| # | Decisión |
|---|---|
| 1 | Almacenamiento: **tabla `int_accounts`** en DB (fuente única; adelanta la F4 del SPEC original de forma ligera). |
| 2 | Token **cifrado en reposo** (openssl AES-256-GCM con `APP_KEY`); `.env` como fallback de la instancia interna. |
| 3 | Config UI: **sección en Ajustes** (`SettingsSectionRegistry`) con **cuerpo custom** (postea a `IntegrationsController`) para soportar cifrado, test de conexión y estado. |
| 4 | Provisión de demo: **acción de fila en `dom_mkt_leads`** (`type: link` → controlador con modal/form). |
| 5 | Fallback sin Partner API: **modal para pegar `instance_id` + `token`**; cada lead puede tener su propia instancia. |
| 6 | La demo entrega al cliente: **credenciales + link de activación (QR)** por correo (canal `email` de Fase 1). |
| 7 | **Incluye** vista pública de QR e **incluye** visor de `int_logs` en esta fase. |

---

## 3. Alcance Fase 2

**Incluye:**

- Tabla `int_accounts` (añadida al bootstrap idempotente del módulo).
- Helper `Crypto` (Kernel) para cifrar/descifrar tokens con `APP_KEY`.
- `IntegrationAccountRepositoryInterface` + `IntegrationAccountRepository` (PDO, cifra/descifra).
- `PartnerConnectorInterface` + `GreenApiPartnerConnector` (crea instancias; opcional según `.env`).
- Resolución de credenciales internas desde `int_accounts` (`is_default`) con fallback a `.env`, dentro de `IntegrationsFactory`.
- `IntegrationsController` (Presentation): guardar config interna, probar conexión, provisión (auto/manual) ligada a lead, envío del correo de demo, visor de `int_logs`, vista pública de QR.
- `IntegrationsWhatsappSettingsProvider` (sección de Ajustes con cuerpo custom).
- Vista pública `/wa/activar/{token-firmado}` (QR vía `getQRCode`).
- Acción de fila `provisionar_demo_wa` (`type: link`) en `config/cruds/dom_mkt_leads.json`.
- `routes/integrations.php` (incluida solo si `modules.integrations`).
- `.env.example`: `GREEN_API_PARTNER_TOKEN=`.
- Tests del arnés (`php tests/run.php`).

**No incluye** (sigue diferido a fases posteriores del SPEC original): webhooks reales, cola + reintentos, plantillas en DB, recordatorios programados, multi-tenant avanzado, módulo de notificaciones.

---

## 4. Modelo de datos

### 4.1 `int_accounts` (tabla nueva)

```sql
CREATE TABLE IF NOT EXISTS `int_accounts` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`         VARCHAR(40)     NOT NULL DEFAULT 'green_api',
  `label`            VARCHAR(190)    NOT NULL,                 -- "Instancia interna" | "Demo - Juan Pérez"
  `instance_id`      VARCHAR(190)    NOT NULL,
  `token_encrypted`  TEXT            NOT NULL,                 -- AES-256-GCM (APP_KEY); nunca en claro
  `is_default`       TINYINT(1)      NOT NULL DEFAULT 0,        -- 1 = instancia de envíos internos
  `lead_id`          BIGINT UNSIGNED DEFAULT NULL,             -- set solo en instancias de demo por cliente
  `status`           VARCHAR(20)     NOT NULL DEFAULT 'manual',-- manual|provisioning|active|authorized|error
  `provisioned_via`  VARCHAR(20)     NOT NULL DEFAULT 'manual',-- partner_api|manual
  `meta`             JSON            DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`       BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_int_accounts_default` (`is_default`),
  KEY `idx_int_accounts_lead` (`lead_id`),
  KEY `idx_int_accounts_provider` (`provider`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Reglas:**

- A lo sumo **una** fila con `is_default=1` por proveedor (lo garantiza el repositorio: al marcar una, desmarca las demás).
- `lead_id` NULL ⇒ instancia interna/operativa; NOT NULL ⇒ instancia de demo de ese lead.
- `token_encrypted` **nunca** se devuelve en claro a la vista (se descifra solo al construir el canal o al mostrar el QR).
- Sin FK dura a `dom_mkt_leads` (el módulo `integrations` no debe acoplarse a tablas `dom_*`); `lead_id` es una referencia blanda.

### 4.2 `int_logs` (sin cambios)

Se mantiene tal cual (Fase 1). El visor de logs lee de aquí.

---

## 5. Cifrado de tokens — `Crypto` (Kernel)

Helper nuevo en `app/Kernel/Security/Crypto.php` (o `app/Kernel/Helpers/`):

```php
final class Crypto {
    public static function encrypt(string $plain): string;  // AES-256-GCM, devuelve base64(iv|tag|cipher)
    public static function decrypt(string $payload): string; // inverso; lanza si APP_KEY ausente/inválida
}
```

- Clave derivada de `APP_KEY` en `.env` (si no existe, se documenta como requisito de despliegue; el bootstrap puede avisar).
- Formato auto-contenido (IV + tag embebidos) para no necesitar columnas extra.
- Único punto de cifrado/descifrado; lo usa `IntegrationAccountRepository`.

---

## 6. Resolución de credenciales internas (cambio en `IntegrationsFactory`)

Hoy `IntegrationsFactory::dispatcher()` arma `GreenApiWhatsappChannel` con la config de `config/integrations.php` (que lee `.env`). Cambio mínimo:

1. Antes de construir el canal `whatsapp`, consultar `IntegrationAccountRepository::findDefault('green_api')`.
2. Si existe ⇒ usar su `instance_id` + token descifrado (sobrescribe `instance_id`/`token` del array `config`).
3. Si no existe ⇒ usar los valores de `.env` (comportamiento Fase 1 intacto).
4. `base_url`, `timeout` y `enabled` siguen viniendo de `config/integrations.php`/`.env`.

**No cambian** `GreenApiWhatsappChannel`, `ChannelRegistry` ni `NotificationDispatcher`. El canal sigue recibiendo un array `config`; solo cambian sus valores.

> Nota de cache: `IntegrationsFactory` cachea el dispatcher en estático. Tras guardar una instancia nueva desde la UI, el cambio aplica en el siguiente request (aceptable; no hay proceso de larga vida).

---

## 7. Partner API (auto-provisión) con fallback manual

### 7.1 Contrato

```php
interface PartnerConnectorInterface {
    /** Crea una instancia y devuelve sus credenciales. @return array{instance_id:string, token:string} */
    public function createInstance(string $label): array;
    public function isAvailable(): bool; // true si hay GREEN_API_PARTNER_TOKEN
}
```

### 7.2 `GreenApiPartnerConnector` (Infra)

- Usa el `HttpApiConnector` existente contra el endpoint de partner de Green API.
- `isAvailable()` ⇔ `GREEN_API_PARTNER_TOKEN` no vacío.
- Si la llamada falla, lanza una excepción capturada por el controlador → se ofrece el modo manual y se registra en `int_logs` (status `failed`, sin volcar el token).

### 7.3 Flujo de decisión en la UI de provisión

```
Acción "Provisionar demo WhatsApp" sobre un lead
        │
        ▼
¿PartnerConnector::isAvailable()?
   ├─ sí  → botón "Crear instancia automáticamente"
   │          → createInstance() → guarda int_accounts(lead_id, provisioned_via=partner_api)
   └─ no  → form manual: pegar instance_id + token (creados a mano en console.green-api.com)
              → guarda int_accounts(lead_id, provisioned_via=manual)
        │
        ▼
Enviar correo de demo (credenciales + link de activación QR) vía EmailChannel
        │
        ▼
Registrar resultado en int_logs (destinatario enmascarado)
```

La demo **funciona sin Partner API** desde el día 1 (modo manual).

---

## 8. UI 1 — Sección en Ajustes "Integraciones / WhatsApp" (cuerpo custom)

`IntegrationsWhatsappSettingsProvider implements SettingsSectionProviderInterface`:

- `clave = 'integrations_whatsapp'`, `titulo = 'Integraciones / WhatsApp'`, `icono = 'bi-whatsapp'`, `permiso = 'integrations.configurar'`.
- A diferencia de las secciones declarativas de marketing, su panel **no** se limita a `campos()`: renderiza una **vista custom** que postea a `IntegrationsController`. (Si el `SettingsSectionRegistry`/`AjustesController` actual solo soporta campos declarativos, se extiende mínimamente para permitir secciones con `vista` propia; ver §13.)

Contenido del panel:

1. **Instancia interna** (la fila `is_default`): `instance_id`, `token` (campo secret; al guardar se cifra), toggle `enabled`. Botón **"Guardar"** → `IntegrationsController::saveInternal`.
2. **Partner API**: `GREEN_API_PARTNER_TOKEN` (secret) — informativo/estado; el valor real vive en `.env` (no se guarda token de partner en DB en esta fase). Muestra si está disponible o no.
3. **Probar conexión** → `IntegrationsController::testConnection` → llama `getStateInstance` de Green API y muestra `authorized` / `notAuthorized` / `error`.
4. **Visor de `int_logs`** (ver §10): tabla de últimos envíos.

Permiso `integrations.configurar` para todo el panel; `integrations.ver` para el visor de logs si se quiere separar.

---

## 9. UI 2 — Acción de fila en `dom_mkt_leads` + controlador

### 9.1 Declaración (CRUD Engine, sin acoplar Green API)

En `config/cruds/dom_mkt_leads.json`, dentro de `actions.row`:

```json
{
  "name": "provisionar_demo_wa",
  "type": "link",
  "label": "Provisionar demo WhatsApp",
  "icon": "bi-whatsapp",
  "route": "/admin/integraciones/provision?lead_id={id}",
  "permission": "integrations.enviar"
}
```

Se usa `type: link` (no `handler`) porque el flujo necesita una pantalla/modal para el modo automático vs manual. RBAC y CSRF los aporta el Engine y el controlador re-valida en servidor.

### 9.2 `IntegrationsController` (Presentation)

Acciones (todas bajo `routes/integrations.php`, incluidas solo si `modules.integrations`):

| Método/Ruta | Propósito | Permiso |
|---|---|---|
| `GET /admin/integraciones/provision?lead_id=` | Pantalla/modal de provisión (auto si Partner; form manual si no). | `integrations.enviar` |
| `POST /admin/integraciones/provision` | Crea/registra instancia (`int_accounts`), liga al lead, envía correo, loguea. | `integrations.enviar` |
| `POST /admin/integraciones/config/internal` | Guarda la instancia interna (`is_default`, token cifrado). | `integrations.configurar` |
| `POST /admin/integraciones/test` | Prueba `getStateInstance` de la instancia interna. | `integrations.configurar` |
| `GET /admin/integraciones/logs` | Fragmento/tabla de `int_logs` (para el panel de Ajustes). | `integrations.ver` |
| `GET /wa/activar/{token}` | **Público** (sin login, sin CSRF): muestra QR de activación. | — (token firmado) |

El controlador construye el `MessageRequest` de correo (canal `email`) con las credenciales y el link de activación, y lo envía vía `NotificationDispatcher` (fachada de Fase 1).

---

## 10. UI 3 — Visor de `int_logs`

- Lectura del repositorio de logs de Fase 1 (se añade al repo un método `recent(int $limit, ?string $channel)` si no existe).
- Tabla read-only: fecha, canal, driver, destinatario **enmascarado**, status (`sent`/`failed`/`skipped`), `provider_message_id`.
- Se embebe en el panel de Ajustes (sección WhatsApp). Sin edición.

---

## 11. Vista pública de activación QR

- Ruta pública `GET /wa/activar/{token}` (fuera del grupo admin; sin CSRF; sin auth).
- `{token}` = HMAC firmado (con `APP_KEY`) que codifica `int_accounts.id` + expiración. Se valida firma y vencimiento; si falla ⇒ 404/expirado.
- La vista llama `getQRCode` de Green API para la instancia y muestra el QR (imagen base64) + instrucciones de escaneo. Refresca el QR / estado con polling ligero a `getStateInstance` (opcional).
- El correo de demo contiene este link. El cliente abre, escanea con su WhatsApp y queda `authorized`.
- **Seguridad:** el token de URL no expone el token de Green API; el QR se obtiene server-side. Límite de expiración corto. Registrar accesos inválidos sin volcar payloads.

---

## 12. Correo de demo

- Se envía con el canal `email` de Fase 1 (`EmailChannel` → `MailerInterface`), reusando la plantilla/vista de correo del sistema (`app/Presentation/Views/emails/`).
- Contenido: saludo al lead, explicación de la demo, **link de activación QR** (`/wa/activar/{token}`) y, opcionalmente, las credenciales (`instance_id`) para referencia. El `token` de Green API **no** se incluye en el correo salvo decisión explícita (se prefiere el flujo por QR).
- Todo envío queda en `int_logs` (Fase 1).

---

## 13. Cambios a piezas existentes (mínimos)

| Pieza | Cambio |
|---|---|
| `database/schema/modules/integrations.sql` | Añadir `CREATE TABLE int_accounts` (idempotente). |
| `app/Application/Integrations/IntegrationsFactory.php` | Resolver creds del canal `whatsapp` desde `int_accounts` (`is_default`), fallback `.env`. |
| `config/settings_sections.php` (o equivalente) | Registrar `IntegrationsWhatsappSettingsProvider` (condicional al módulo). |
| `SettingsSectionRegistry` / `AjustesController` | Soportar secciones con **vista custom** además de campos declarativos (extensión mínima; verificar API actual antes de implementar). |
| `config/cruds/dom_mkt_leads.json` | Añadir acción de fila `provisionar_demo_wa` (`type: link`). |
| `config/container.php` | Bindings: `IntegrationAccountRepositoryInterface`, `PartnerConnectorInterface`, `IntegrationsController`. |
| `routes/web.php` o `routes/integrations.php` | Incluir rutas del controlador (admin) y la ruta pública `/wa/activar/{token}`, condicionadas a `modules.integrations`. |
| `.env.example` | Añadir `GREEN_API_PARTNER_TOKEN=`. |

> El SPEC original dice que las tablas `int_*` **no** se exponen por el CRUD Engine; `int_accounts` se gestiona vía `IntegrationAccountRepository` y el `IntegrationsController`, **no** como recurso CRUD. ✔ respetado.

---

## 14. Clases / interfaces nuevas (resumen)

**Domain — `app/Domain/Integrations/`**
- `IntegrationAccountRepositoryInterface` (`findDefault`, `findByLead`, `save`, `markDefault`, `findById`).
- `PartnerConnectorInterface` (`createInstance`, `isAvailable`).

**Infrastructure — `app/Infrastructure/Integrations/`**
- `Repositories/IntegrationAccountRepository` (PDO; cifra/descifra con `Crypto`).
- `Partner/GreenApiPartnerConnector` (sobre `HttpApiConnector`).

**Application — `app/Application/Integrations/`**
- Ajuste en `IntegrationsFactory` (creds desde DB).

**Presentation — `app/Presentation/`**
- `Controllers/IntegrationsController`.
- `Views/admin/integraciones/provision.php`, `Views/admin/integraciones/_logs.php`, `Views/publico/wa_activar.php`.
- `Infrastructure/Integrations/Settings/IntegrationsWhatsappSettingsProvider` (sección de Ajustes).

**Kernel**
- `Security/Crypto`.

---

## 15. Riesgos técnicos

| Riesgo | Mitigación |
|---|---|
| `APP_KEY` ausente ⇒ no se puede cifrar/descifrar. | Documentar como requisito; el bootstrap/test avisa; fallback a `.env` si no hay fila default. |
| Token filtrado desde DB. | Cifrado en reposo (AES-256-GCM); nunca se devuelve en claro a vistas; logs enmascarados. |
| Partner API no disponible / falla. | `isAvailable()` + modo manual garantizado; excepción capturada → `MessageResult`/log, sin romper flujo. |
| Link público de QR abusado. | Token HMAC firmado con expiración; QR server-side; rechazo silencioso de tokens inválidos. |
| Cache estático del dispatcher tras cambiar instancia. | Aplica al siguiente request; documentado; sin proceso de larga vida. |
| Múltiples `is_default`. | El repositorio desmarca las demás al marcar una (transacción). |
| Acoplamiento a `dom_mkt_leads`. | `lead_id` es referencia blanda sin FK; el controlador lee el email del lead, no el módulo `integrations`. |
| Instancia no autorizada ⇒ envío falla. | Botón "Probar conexión" (`getStateInstance`) + flujo QR; el dispatcher degrada con `MessageResult::failed`. |

---

## 16. Criterios de aceptación (Fase 2)

1. Existe `int_accounts` con token **cifrado**; al consultar la fila nunca aparece el token en claro en logs ni vistas.
2. Los envíos internos del CRUD usan la instancia `is_default` de `int_accounts`; si no hay fila default, usan `.env` (Fase 1 intacta).
3. Desde Ajustes (sección "Integraciones / WhatsApp") el operador guarda `instance_id` + `token` de la instancia interna y **prueba la conexión** (estado authorized/notAuthorized).
4. Una acción de fila en `dom_mkt_leads` provisiona una instancia para el lead: **automática** si hay Partner API, **manual** (pegar instance+token) si no.
5. Tras provisionar, el lead **recibe un correo** con el link de activación QR; el envío queda en `int_logs`.
6. La vista pública `/wa/activar/{token}` muestra el QR de la instancia y permite vincular el WhatsApp del cliente; el token es firmado y expira.
7. El visor de `int_logs` muestra los últimos envíos con destinatario enmascarado.
8. Sin Partner API configurada, **todo el flujo de demo funciona** en modo manual.
9. Añadir/quitar instancias no requiere tocar `GreenApiWhatsappChannel`, `ChannelRegistry` ni `NotificationDispatcher`.
10. El módulo sigue siendo **desactivable** desde `config/vertical.php`; el bootstrap SQL es **idempotente**.

---

## 17. Plan de implementación (orden sugerido)

1. **Kernel `Crypto`** + tests (encrypt/decrypt round-trip, fallo sin APP_KEY).
2. **`int_accounts`** en `integrations.sql` (idempotente) + `IntegrationAccountRepositoryInterface`/`IntegrationAccountRepository` (cifra/descifra, `markDefault` transaccional) + tests.
3. **`IntegrationsFactory`**: creds internas desde DB con fallback `.env` + tests (default vs env).
4. **`PartnerConnectorInterface` + `GreenApiPartnerConnector`** (`isAvailable`, `createInstance`) + `.env.example` (`GREEN_API_PARTNER_TOKEN`).
5. **`IntegrationsController`** + rutas (`routes/integrations.php`, condicional): config interna, test, provisión auto/manual, logs, QR público.
6. **Sección de Ajustes** `IntegrationsWhatsappSettingsProvider` + extensión mínima del registry/AjustesController para vista custom + vistas.
7. **Acción de fila** `provisionar_demo_wa` en `dom_mkt_leads.json` + correo de demo (reuso `EmailChannel`).
8. **Vista pública QR** `/wa/activar/{token}` (token HMAC firmado).
9. **Visor de `int_logs`** (método `recent` en el repo + fragmento de vista).
10. Tests de integración del arnés: provisión manual, fallback sin partner, no-propagación de errores, enmascarado de logs.

---

## Apéndice A — Endpoints Green API usados en Fase 2

| Acción | Endpoint |
|---|---|
| Enviar mensaje (Fase 1) | `POST /waInstance{id}/sendMessage/{token}` |
| Estado de instancia | `GET /waInstance{id}/getStateInstance/{token}` |
| QR de activación | `GET /waInstance{id}/qr/{token}` (o `getQRCode`) |
| Crear instancia (Partner) | endpoint de la Partner API con `GREEN_API_PARTNER_TOKEN` |

> Verificar nombres/paths exactos contra la documentación vigente de Green API al implementar.
