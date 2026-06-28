# Servicio B — "API sola" (WhatsApp / Green API) — Design Spec

> **Estado:** diseño aprobado, pendiente de plan de implementación (writing-plans).
> **Contexto:** primero de los 4 servicios de dominio (A–D) descritos en
> `docs/superpowers/specs/2026-06-27-separacion-framework-v1-dominio-design.md` §7.
> Servicio **B = Producto "API sola"** (`dom_apiwa_*`), apoyado en `int_accounts` +
> `NotificationDispatcher` del framework.

---

## 1. Objetivo y alcance

Vender **envío programático de WhatsApp** como producto. Un administrador da de alta
clientes, a cada uno le asigna una instancia Green API existente (`int_accounts`) y un
plan, y le emite API keys. El cliente consume una **API REST pública autenticada por
key**: cada envío se valida contra su cuota, se reenvía **a través de la fachada del
framework** a SU instancia, y se mide.

### Alcance MVP (este spec)
- Gestión (admin) de **clientes, planes y API keys**.
- Asignación **manual** (admin) de una `int_account` a cada cliente (1 cliente → 1 instancia).
- **4 endpoints REST públicos**: enviar mensaje, estado de mensaje, uso/cuota, ping.
- **Medición de uso** por cliente y **corte duro** de cuota mensual.

### Fuera de alcance (iteraciones posteriores)
- Pagos / facturación / cobro de overage.
- Auto-registro self-service y auto-aprovisionamiento de instancia por el cliente.
- Webhooks entrantes (recepción de mensajes), plantillas, multimedia.
- Rotación automatizada de planes / prorrateo.

### Regla de separación respetada
Ningún `dom_apiwa_*` toca Green API directo: **todo envío pasa por
`NotificationDispatcher`** (fachada framework). El framework provee la plomería de
instancias (`int_accounts`, token cifrado); el dominio agrega la capa comercial
(quién la posee, qué plan, API key, uso). `dom_apiwa_clientes.int_account_id` es
referencia **blanda** (sin FK dura a tablas framework).

---

## 2. Prerrequisito de framework (P0): envío con instancia explícita

**Problema detectado.** Hoy `GreenApiWhatsappChannel` se construye con un `$config`
fijo (`base_url`, `instance_id`, `token`) y `ChannelRegistry` resuelve la clave de
canal `whatsapp` → **una sola** instancia de canal construida desde
`config/integrations.php` (la cuenta por defecto). `MessageRequest` no tiene selector
de cuenta. Por tanto, `NotificationDispatcher::send()` **solo puede enviar desde la
cuenta por defecto** — insuficiente para B, donde cada cliente envía desde SU propia
`int_account`.

**Solución (cambio framework, módulo integrations).** Añadir un camino de envío
**con cuenta explícita**, manteniendo la fachada como único punto de envío:

```php
// App\Application\Integrations\NotificationDispatcher (módulo framework integrations)
public function sendVia(int $accountId, MessageRequest $request): MessageResult;
```

Comportamiento de `sendVia`:
1. Carga la `int_account` por `id` vía `IntegrationAccountRepositoryInterface`
   (instancia + token **descifrado** con la utilidad de cifrado del framework).
2. Construye un `GreenApiWhatsappChannel` efímero con esas credenciales (mismo
   `ApiConnectorInterface` HTTP que hoy).
3. Aplica rate-limit, delega el envío y **registra en `int_logs`** con destinatario
   enmascarado, igual que `send()`. Nunca propaga excepción: degrada a `failed`.
4. Si la cuenta no existe / está inactiva → `MessageResult::failed('account_unavailable')`.

> Es un cambio **genérico** (cualquier futuro `dom_*` multi-instancia lo aprovecha) y
> vive en el módulo framework `integrations`. Es el **único** cambio de framework que B
> necesita. Debe quedar cubierto por tests del arnés con un connector/repositorio fake.
>
> **Nota de secuencia con la separación v1.0:** este P0 toca código que será del
> paquete `lebytek/framework`. Si la separación (plan `2026-06-27-separacion-...`) ya
> se ejecutó, el cambio se hace en `src/Application/Integrations/` del paquete y se
> taggea (p. ej. v1.1.0); si B se construye antes de la separación, se hace en
> `app/Application/Integrations/` del monolito. El resto de B (capas `dom_apiwa_*`) es
> agnóstico a ese timing.

---

## 3. Modelo de datos (`dom_apiwa_*`)

Prefijo `dom_*` (dominio), nunca en el schema base del framework. Bootstrap idempotente
en `database/schema/modules/apiwa.sql` (o migración `dom_*` según convención del repo
destino).

### 3.1 `dom_apiwa_planes`
| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `clave` | VARCHAR(40) UNIQUE | p. ej. `free`, `pro`, `business` |
| `nombre` | VARCHAR(120) | |
| `limite_mensual` | INT UNSIGNED | mensajes `sent` permitidos por mes calendario |
| `activo` | TINYINT(1) DEFAULT 1 | |
| `created_at` | DATETIME DEFAULT CURRENT_TIMESTAMP | |

### 3.2 `dom_apiwa_clientes`
| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `nombre` | VARCHAR(190) | |
| `email` | VARCHAR(190) | contacto comercial |
| `lead_id` | BIGINT UNSIGNED NULL | referencia blanda a `mkt_leads` (origen) |
| `int_account_id` | BIGINT UNSIGNED NULL | referencia blanda a `int_accounts` (su instancia) |
| `plan_id` | BIGINT UNSIGNED | FK lógica a `dom_apiwa_planes` |
| `estado` | VARCHAR(20) DEFAULT 'activo' | `activo` \| `suspendido` |
| `created_at` | DATETIME | |
| `created_by` | BIGINT UNSIGNED NULL | admin que lo creó |

> `int_account_id` y `lead_id` son **soft refs** (sin FK dura cruzando hacia framework/otros módulos).

### 3.3 `dom_apiwa_keys`
| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `cliente_id` | BIGINT UNSIGNED | |
| `key_prefix` | VARCHAR(16) | parte visible (p. ej. `wak_ab12`) para identificar la key en UI/logs |
| `key_hash` | CHAR(64) | **SHA-256** del secreto completo; el secreto en claro NUNCA se persiste |
| `label` | VARCHAR(120) NULL | nombre descriptivo |
| `last_used_at` | DATETIME NULL | |
| `revoked_at` | DATETIME NULL | NULL = activa |
| `created_at` | DATETIME | |

- El secreto completo se muestra **una sola vez** al emitir (no recuperable).
- MVP: una key activa por cliente, pero la tabla soporta varias (rotación = emitir
  nueva + revocar la anterior).
- Índice `UNIQUE(key_hash)` y `KEY(cliente_id, revoked_at)`.

### 3.4 `dom_apiwa_mensajes` (ledger del cliente)
| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | id de dominio expuesto al cliente |
| `cliente_id` | BIGINT UNSIGNED | |
| `provider_message_id` | VARCHAR(190) NULL | `idMessage` de Green API (correlación) |
| `recipient_masked` | VARCHAR(190) | enmascarado (nunca en claro) |
| `status` | VARCHAR(20) | `sent` \| `failed` |
| `periodo` | CHAR(6) | `YYYYMM` del envío (clave de conteo de cuota) |
| `created_at` | DATETIME | |

- Índices: `KEY(cliente_id, periodo, status)` (conteo de cuota) y `KEY(provider_message_id)`.
- Es el **registro autoritativo** del dominio para **cuota** y **estado**. `int_logs`
  (framework) sigue registrando el intento a bajo nivel; no se duplica lógica: el
  ledger guarda `provider_message_id` para correlacionar si se necesita.

### 3.5 Cuota
```
usados   = COUNT(*) FROM dom_apiwa_mensajes
           WHERE cliente_id = :id AND periodo = :YYYYMM_actual AND status = 'sent'
permitido si usados < plan.limite_mensual   (corte DURO → 429 al alcanzar el límite)
```
- Mes **calendario** (reinicio el día 1). Sin prorrateo.
- Solo cuentan los `sent` (relays exitosos); un `failed` no consume cuota.

---

## 4. API REST pública (`/api/v1`)

- Sin sesión ni CSRF; autenticación **solo por API key**.
- Cabecera: `Authorization: Bearer <api_key>`.
- Respuestas JSON, `Content-Type: application/json`. Claves JSON en camelCase.
- Sobre de error uniforme: `{ "error": { "code": "<slug>", "message": "<texto>" } }`.

### 4.1 Endpoints

| Método | Ruta | Cuerpo / params | Respuesta OK |
|---|---|---|---|
| POST | `/api/v1/mensajes` | `{ "to": "<tel>", "message": "<texto>" }` | `201 { "id", "providerMessageId", "status" }` |
| GET | `/api/v1/mensajes/{id}` | — | `200 { "id", "status", "providerMessageId", "createdAt" }` |
| GET | `/api/v1/uso` | — | `200 { "plan", "limite", "usados", "restante", "periodo" }` |
| GET | `/api/v1/ping` | — | `200 { "ok": true, "instance": "connected|unknown" }` |

### 4.2 Flujo de `POST /api/v1/mensajes`
1. `ApiKeyAuthMiddleware` resuelve la key → cliente + plan + `int_account_id`.
2. Validación de entrada (`to` con dígitos, `message` no vacío) → `422` si falla.
3. `QuotaService`: si `usados >= limite_mensual` → `429 { code:"quota_exceeded" }`.
4. `NotificationDispatcher::sendVia(cliente.int_account_id, new MessageRequest('whatsapp', to, message, meta))`.
5. Registrar fila en `dom_apiwa_mensajes` con `status` del `MessageResult`, `periodo`
   actual, `recipient_masked`, `provider_message_id`.
6. Si `MessageResult::ok` → `201` con el id de dominio; si `failed` → `502 { code:"relay_failed" }`
   (el ledger igual registra el intento `failed`; no consume cuota).

### 4.3 Códigos de error
| HTTP | code | Cuándo |
|---|---|---|
| 401 | `unauthorized` | key ausente, mal formada, no encontrada o revocada |
| 403 | `customer_suspended` | `cliente.estado = 'suspendido'` |
| 422 | `validation_error` | `to`/`message` inválidos |
| 429 | `quota_exceeded` | cuota mensual agotada |
| 502 | `relay_failed` | la fachada devolvió `failed` (proveedor/instancia) |
| 404 | `not_found` | `GET /mensajes/{id}` inexistente o de otro cliente |

> El dispatcher **nunca lanza** (degrada a `failed`); por eso el `502` se mapea desde
> `MessageResult::failed`, no desde una excepción.

---

## 5. Componentes (Onion, lado dominio `App\…`)

### 5.1 Domain (`app/Domain/Apiwa/`)
- Entidades: `Cliente`, `Plan`, `ApiKey`, `Mensaje`.
- Value Objects: `ApiKeyToken` (genera secreto aleatorio de alta entropía, expone
  `prefix()` y `hash()`); `Periodo` (YYYYMM).
- Interfaces de repositorio: `ClienteRepositoryInterface`, `PlanRepositoryInterface`,
  `ApiKeyRepositoryInterface`, `MensajeRepositoryInterface`.
- Policy: `QuotaPolicy::permite(int $usados, Plan $plan): bool`.

### 5.2 Application (`app/Application/Apiwa/`)
- UseCases:
  - `EnviarMensajeApiUseCase` — orquesta cuota → `sendVia` → registro del ledger.
  - `ConsultarUsoUseCase` — calcula `{limite, usados, restante, periodo}`.
  - `ConsultarEstadoMensajeUseCase` — lee el ledger por id + cliente.
  - `EmitirApiKeyUseCase` — genera token, persiste hash+prefix, devuelve el secreto **una vez**.
  - `RevocarApiKeyUseCase` — marca `revoked_at`.
- Services: `QuotaService` (conteo + decisión), `ApiKeyAuthenticator` (hash→lookup→carga cliente).

### 5.3 Infrastructure (`app/Infrastructure/Apiwa/`)
- Repos PDO: `PdoClienteRepository`, `PdoPlanRepository`, `PdoApiKeyRepository`,
  `PdoMensajeRepository`.
- Reutiliza del framework: `IntegrationAccountRepositoryInterface` (cargar la cuenta)
  y `NotificationDispatcher::sendVia` (envío). No implementa nada de Green API.

### 5.4 Presentation
- **API pública** (`app/Presentation/Controllers/Api/Apiwa/`):
  `MensajesController` (POST + GET {id}), `UsoController`, `PingController`.
- **Middleware**: `ApiKeyAuthMiddleware` — extrae Bearer, hashea, valida (activa, no
  revocada, cliente no suspendido), inyecta el cliente resuelto en el request; corta
  con `401/403` en JSON.
- **Admin** (RBAC, `/admin/apiwa/...`): se apoya en el **CRUD Engine** para
  `dom_apiwa_clientes` y `dom_apiwa_planes` (configs en `config/cruds/`). Las API keys
  necesitan un **handler/acción custom**: "emitir" (muestra el secreto una sola vez) y
  "revocar"; el listado solo muestra `prefix`, `label`, `last_used_at`, estado. Vista
  de **uso** por cliente: solo lectura (consume `ConsultarUsoUseCase`).

### 5.5 Rutas y registro
- Públicas: `routes/apiwa_api.php` (incluido en el arranque de la API), grupo
  `/api/v1` con `ApiKeyAuthMiddleware` (excepto detalles que requieran solo key válida).
- Admin: entradas en `routes/web.php` (o `routes/apiwa.php`) bajo el grupo admin con
  `RbacMiddleware`.
- Manifiesto del módulo `config/modules/apiwa.php` (`clave: apiwa`, `requiere:
  ['core','crud-engine','integrations']`, `bootstrap_sql`, `cruds`, `permisos`, `menu`).
- Toggle `modules.apiwa` en `config/vertical.php`.
- Bindings DI en el `container.php` del proyecto (zona de dominio), no en el
  `FrameworkServiceProvider`.

### 5.6 RBAC
Permisos de **gestión admin** (no confundir con la auth pública por key):
`apiwa.ver`, `apiwa.gestionar`, `apiwa.keys`. Asignados al rol `administrador` en el
`bootstrap_sql`. La **API pública** NO usa RBAC: su control de acceso es la API key.

---

## 6. Manejo de errores y seguridad

- **Secreto de key**: solo se persiste `key_hash` (SHA-256) + `key_prefix`. El secreto
  completo se entrega una vez al emitir; irrecuperable. Lookup por hash en tiempo
  constante a nivel de índice.
- **Token de instancia**: lo descifra el framework dentro de `sendVia`; el dominio
  nunca ve el token Green API en claro.
- **Enmascarado**: el ledger guarda `recipient_masked`; el teléfono en claro no se
  persiste en dominio (consistente con `int_logs`).
- **Rate-limit**: se reutiliza el `RateLimiter` del framework dentro de la fachada
  (por cuenta/canal). La cuota mensual es un control **adicional** de negocio.
- **Aislamiento por cliente**: `GET /mensajes/{id}` y `/uso` siempre filtran por el
  `cliente_id` resuelto de la key; un cliente nunca ve datos de otro (`404` si el id
  no le pertenece).

---

## 7. Estrategia de pruebas (arnés `php tests/run.php`, sin Green API real)

Dobles/fakes: `FakeNotificationDispatcher` (registra `accountId` recibido y devuelve un
`MessageResult` programado), repos en memoria o sobre la DB de test según el patrón del
repo. Casos:

1. **Auth**: key válida pasa; ausente/mal formada/desconocida → `401`; key **revocada**
   → `401`; cliente **suspendido** → `403`.
2. **Cuota**: por debajo del límite envía; **al alcanzar** `limite_mensual` → `429`; un
   `failed` no consume cuota; el reinicio de `periodo` (mes nuevo) restablece el conteo.
3. **Envío**: éxito registra fila `sent` en el ledger con `provider_message_id` y
   decrementa `restante`; `sendVia` recibe **el `int_account_id` correcto del cliente**
   (aserción central del enrutado por cuenta).
4. **Estado**: `GET /mensajes/{id}` propio → datos; ajeno/inexistente → `404`.
5. **Uso**: `GET /uso` devuelve `{limite, usados, restante, periodo}` coherente con el ledger.
6. **Emisión de key**: `EmitirApiKeyUseCase` devuelve el secreto una sola vez; persiste
   solo hash+prefix; `hash(secreto) == key_hash`.
7. **Prerrequisito P0 (framework)**: test del arnés para `sendVia` con repo+connector
   fake: carga la cuenta correcta, construye el canal con sus credenciales, registra en
   `int_logs`, y degrada a `failed` si la cuenta no existe.

---

## 8. Entregables y secuencia

1. **P0 framework** — `NotificationDispatcher::sendVia` + tests (módulo integrations).
2. **Datos** — `dom_apiwa_*` schema/bootstrap + manifiesto + toggle + RBAC.
3. **Dominio/Aplicación** — entidades, repos, UseCases, `QuotaService`, `ApiKey` VO.
4. **API pública** — controllers + `ApiKeyAuthMiddleware` + rutas `/api/v1` + errores JSON.
5. **Admin** — CRUD configs de `clientes`/`planes` + handler de keys + vista de uso.
6. **Pruebas** — arnés verde con los 7 grupos de casos.

### Fuera de alcance (próximas iteraciones)
Pagos/facturación, auto-registro y auto-aprovisionamiento de instancia, webhooks
entrantes, plantillas/multimedia, overage cobrado. Cada uno: su propio spec → plan.

---

## 9. Decisiones registradas
- **MVP** = core API + medición de uso (sin pagos ni self-service).
- **4 endpoints**: enviar, estado, uso, ping.
- **Cuota** = mensual por mes calendario, **corte duro** (`429`).
- **Auth pública** = `Authorization: Bearer <key>`, key hasheada (SHA-256) en reposo.
- **Admin** reutiliza el **CRUD Engine** (no pantallas a medida), salvo el handler de keys.
- **Enrutado por cuenta** vía nuevo `sendVia` en la fachada (cambio framework P0), no
  tocando Green API desde el dominio.
