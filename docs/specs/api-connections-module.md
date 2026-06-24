# SPEC — Módulo `integrations` (motor de conexiones e integraciones)

> Estado: **diseño aprobado, sin implementar**. Fecha: 2026-06-24.
> Tipo: módulo opcional del framework (vertical desacoplable), compatible con la arquitectura Onion + CRUD Engine existente.
> Primer caso de uso: envío de WhatsApp vía **Green API**.

---

## 1. Resumen ejecutivo

`integrations` es un módulo del framework que provee una **capa intermedia desacoplada** para que cualquier módulo de negocio envíe mensajes, consuma APIs externas y reciba webhooks **sin acoplarse a un proveedor concreto**.

El módulo replica el patrón que el framework ya usa para correo (`MailerInterface` → implementación en Infrastructure → binding en `config/container.php`) y lo eleva a un concepto genérico de **canal de mensajería** (`MessageChannelInterface`) coordinado por una **fachada** (`NotificationDispatcher`).

Un módulo de negocio nunca conoce Green API. Solo construye un `MessageRequest` (*"envía este cuerpo por el canal `whatsapp` al destinatario X"*) y la fachada resuelve el canal, ejecuta el envío, registra el resultado en `int_logs` y **nunca propaga errores** hacia el flujo principal.

El primer caso real es **enviar WhatsApp desde una acción de fila del CRUD Engine** reutilizando el tipo de acción `handler` ya existente — **cero cambios al núcleo del CRUD Engine**.

---

## 2. Problema que resuelve

Hoy, si un módulo de negocio quisiera mandar un WhatsApp, tendría que:

- conocer la URL y el formato de Green API,
- guardar credenciales en su propio código/config,
- duplicar manejo de errores, timeouts y logging en cada módulo,
- quedar acoplado a un proveedor (migrar a Twilio implicaría tocar todos los módulos).

Esto rompe la regla del framework de mantener los módulos **desacoplados y configurables**, y contradice la arquitectura Onion (el dominio de negocio dependería de un detalle de infraestructura).

`integrations` resuelve esto centralizando:

- **el "cómo se envía"** (proveedor, HTTP, credenciales, reintentos, logging) en un solo lugar,
- **el "qué se envía"** (destinatario, cuerpo, datos) como un contrato simple que cualquier módulo construye,
- **la recepción de eventos externos** (webhooks) bajo un contrato uniforme de validación y distribución.

---

## 3. Objetivos

1. Permitir el envío saliente de mensajes desde cualquier punto del sistema (CRUD, calendario, procesos, futuros módulos) a través de una sola fachada.
2. Mantener a Green API **100% encapsulado** detrás de un canal intercambiable.
3. No introducir infraestructura nueva pesada en Fase 1 (sin colas ni cron runner, que hoy no existen).
4. Registrar **siempre** cada intento de envío con resultado y sin exponer secretos.
5. **Nunca romper el flujo de negocio**: un fallo de integración degrada con elegancia y se reporta como resultado, no como excepción propagada.
6. Dejar diseñado (no implementado) el camino hacia multi-proveedor, plantillas, cola de envíos, webhooks y un módulo general de notificaciones.

---

## 4. Alcance Fase 1

**Incluye:**

- Contratos de dominio: `MessageRequest`, `MessageResult`, `MessageChannelInterface`, `ApiConnectorInterface`.
- Fachada `NotificationDispatcher` + `ChannelRegistry` (resolución de canal por clave).
- Conector HTTP genérico `HttpApiConnector` (cURL, timeouts, manejo de error uniforme).
- Canal `GreenApiWhatsappChannel` (envío de texto).
- Canal `EmailChannel` que **adapta el `MailerInterface` existente** (prueba multi-canal real desde el día 1).
- Tabla **`int_logs`** (única tabla nueva en Fase 1).
- Manifiesto de módulo, toggle en `vertical.php`, permisos RBAC, configuración `config/integrations.php`.
- Integración con CRUD Engine vía un **handler delgado por módulo** (patrón `AutoresponderHandler`).
- Credenciales por `.env`.

**No incluye en Fase 1** (diseñado, no implementado): cola de envíos, reintentos automáticos, recepción real de webhooks, plantillas en DB, multi-cuenta/credenciales cifradas en DB, recordatorios programados, UI de configuración de proveedores.

---

## 5. Alcance futuro (fases posteriores)

| Fase | Entregable |
|---|---|
| **F2 — Webhooks** | Endpoint real `POST /webhooks/integrations/{provider}`, validación de firma, tabla `int_webhook_events`, despacho a listeners. |
| **F2 — Cola + reintentos** | `int_message_queue`, worker CLI (`scripts/`), backoff exponencial, estados `pending/sending/sent/failed`. |
| **F3 — Plantillas** | `int_message_templates` (o reuso de `dom_mkt_plantillas`), render con placeholders `{{campo}}`, selección de plantilla desde la acción. |
| **F3 — Recordatorios** | Enganche desde calendario/recordatorios (comando programado que arma `MessageRequest`). |
| **F4 — Multi-cuenta / secretos** | `int_accounts`, `int_credentials` cifradas, selección de cuenta por canal/tenant. |
| **F4 — Módulo Notificaciones** | Capa de preferencias por usuario, multi-canal con fallback, centro de notificaciones in-app sobre la misma fachada. |
| **Proveedores** | Nuevos canales: WhatsApp Cloud API, Twilio, SendGrid, Telegram, Slack, APIs internas — cada uno una clase nueva en Infra, sin tocar negocio. |

---

## 6. Arquitectura propuesta

### 6.1 Capas de abstracción (de alto a bajo nivel)

```
Módulo de negocio (CRUD handler, calendario, proceso, futuro Notificaciones)
      │  construye MessageRequest y pide "enviar"
      ▼
NotificationDispatcher            ← FACHADA pública (Application)
      │  resuelve canal por clave vía ChannelRegistry
      ▼
MessageChannelInterface           ← PUERTO de canal (Domain)   ej. "whatsapp", "email"
      │  implementado por
      ▼
GreenApiWhatsappChannel | EmailChannel | (futuros)  (Infrastructure)
      │  los canales HTTP usan
      ▼
HttpApiConnector (ApiConnectorInterface)            ← cliente HTTP genérico (Infrastructure)
```

### 6.2 Regla Onion respetada

- **Domain** (`app/Domain/Integrations/`): VOs y puertos. Cero dependencias externas.
- **Application** (`app/Application/Integrations/`): `NotificationDispatcher`, `ChannelRegistry`. Orquesta; no conoce Green API.
- **Infrastructure** (`app/Infrastructure/Integrations/`): conector HTTP, canales concretos, repositorio de logs. Implementa los puertos.
- **Kernel/Config**: `config/integrations.php` mapea clave de canal → clase y resuelve credenciales desde `.env`. El binding vive en `config/container.php`.

### 6.3 Qué NO debe hacer este módulo (evitar acoplamiento)

- **No** contener lógica de negocio (qué texto enviar, a quién, bajo qué regla). Eso vive en el módulo de negocio.
- **No** conocer la forma de las tablas `dom_*` de negocio.
- **No** propagar excepciones de proveedor hacia el caller (las captura y devuelve `MessageResult::failed`).
- **No** decidir *cuándo* enviar (eso lo decide el CRUD/calendario/proceso que lo invoca).
- **No** exponer FQCN de canales en JSON de CRUD ni guardar secretos en DB/código en Fase 1.
- **No** gestionar sus tablas `int_*` a través del CRUD Engine (el Engine es para `dom_*`); las gestiona con repositorios propios.

---

## 7. Flujo de integración con Green API

Green API expone (para una instancia con `idInstance` + `apiTokenInstance`):

```
POST https://api.green-api.com/waInstance{idInstance}/sendMessage/{apiTokenInstance}
Body: { "chatId": "<phone>@c.us", "message": "<texto>" }
```

**Flujo de envío (Fase 1):**

1. El caller construye `MessageRequest(channel: "whatsapp", recipient: "<telefono>", body: "<texto>", meta: [...])`.
2. `NotificationDispatcher::send($request)`:
   1. resuelve el canal `whatsapp` vía `ChannelRegistry` (definido en `config/integrations.php`);
   2. **valida** destinatario (normaliza teléfono a formato E.164 → `chatId`);
   3. aplica **rate-limit** básico (patrón `LoginRateLimitService`);
   4. delega en `GreenApiWhatsappChannel::send($request)`.
3. `GreenApiWhatsappChannel` usa `HttpApiConnector` para el POST con timeout, y traduce la respuesta a `MessageResult` (`ok`, `providerMessageId` = `idMessage`, `rawResponse`).
4. El dispatcher **siempre** persiste un registro en `int_logs` (con teléfono **enmascarado**, status, provider_message_id o error).
5. Devuelve `MessageResult` al caller. **Si algo falla**, captura la excepción, registra `failed` y devuelve `MessageResult::failed(...)` — el flujo de negocio continúa.

**Normalización de destinatario:** la conversión `telefono → chatId` (`<digitos>@c.us`) vive **dentro** de `GreenApiWhatsappChannel`, no en el caller. El caller solo pasa un teléfono.

---

## 8. Integración con CRUD Engine

### 8.1 Declaración de la acción (sin acoplar Green API)

Se reutiliza el tipo de acción `handler` (CRUD Engine Fase 1, ya en producción). En el JSON del recurso de negocio:

```json
"actions": {
  "row": [
    { "name": "show", "type": "builtin" },
    { "name": "edit", "type": "builtin" },
    {
      "name": "confirmar_wa",
      "type": "handler",
      "handler": "enviar_whatsapp_cita",
      "label": "Confirmar por WhatsApp",
      "icon": "bi-whatsapp",
      "permission": "integrations.enviar",
      "confirm": "¿Enviar confirmación por WhatsApp?",
      "visible_when": { "estado": "pendiente" }
    }
  ]
}
```

- `type: handler` → el motor ejecuta una clase whitelisteada en `config/crud_handlers.php`.
- `permission` → gating RBAC re-validado en servidor.
- `visible_when` / `confirm` → ya soportados por el Engine (modal global `#confirmModal`, CSRF).
- **El JSON no menciona Green API ni WhatsApp como proveedor**: solo declara una acción y un handler por clave.

### 8.2 El handler delgado (vive en el módulo de negocio)

Patrón idéntico a `AutoresponderHandler`. Mapea el registro → `MessageRequest` y llama a la fachada:

```php
final class EnviarWhatsappCitaHandler implements CrudActionHandlerInterface
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(CrudActionContext $ctx): void
    {
        $record = $ctx->record() ?? [];
        $telefono = (string) ($record['telefono'] ?? '');
        if ($telefono === '') {
            return; // nada que enviar; el handler decide la regla de negocio
        }

        $body = sprintf(
            'Hola %s, confirmamos tu cita para el %s.',
            (string) ($record['nombre'] ?? ''),
            (string) ($record['fecha'] ?? '')
        );

        $this->dispatcher->send(new MessageRequest(
            channel: 'whatsapp',
            recipient: $telefono,
            body: $body,
            meta: ['source' => 'crud:dom_citas', 'record_id' => $ctx->recordId()]
        ));
    }
}
```

Registro en `config/crud_handlers.php`:

```php
'enviar_whatsapp_cita' => \App\Application\Crud\Handlers\EnviarWhatsappCitaHandler::class,
```

**Reparto de responsabilidades:**

- *Qué texto y a quién* → handler del módulo de negocio (lógica de dominio).
- *Cómo se envía (Green API, HTTP, credenciales, logging)* → módulo `integrations`.
- *Cuándo se ejecuta* → la acción CRUD (clic del usuario, con permiso y confirmación).

### 8.3 Evolución futura (acción declarativa parametrizada)

En una fase posterior se puede extender la definición de acción del Engine para llevar `params` (`{ "channel": "whatsapp", "recipient_field": "telefono", "template": "confirmacion_cita" }`) y un **handler genérico único** `send_message` provisto por `integrations`, eliminando el handler por módulo. Requiere un cambio menor en el core del Engine (pasar `params` al `CrudActionContext`), por eso se deja como mejora, no Fase 1.

---

## 9. Integración con calendario / recordatorios

La fachada `NotificationDispatcher` es invocable desde cualquier contexto, no solo CRUD. Casos previstos (Fase 3, recordatorios automáticos):

- Recordatorio 24h antes de una cita.
- Aviso de pago próximo / vencimiento.
- Confirmación automática de evento.
- Mensaje posterior a una visita/servicio.

**Diseño del enganche (sin construir cron en Fase 1):**

- Un comando CLI en `scripts/` (ej. `scripts/integrations_recordatorios.php`), ejecutable por el cron del VPS, consulta los registros del recurso de calendario que cumplen la condición temporal y, por cada uno, arma un `MessageRequest` y llama a `NotificationDispatcher::send()`.
- La lógica de "qué citas notificar" vive en el módulo de calendario/negocio; `integrations` solo envía.
- Idempotencia: marcar `recordatorio_enviado_at` en el registro de negocio (o registrar en `int_logs` con `meta.dedupe_key`) para no reenviar.

Esto reutiliza exactamente la misma fachada del flujo CRUD; el calendario no introduce un segundo camino de envío.

---

## 10. Diseño para webhooks (Fase 2)

**Ruta** (incluida condicionalmente como las rutas de marketing, solo si `modules.integrations`):

```
POST /webhooks/integrations/{provider}
```

- **Sin CSRF** (origen externo), **con validación de firma/token** específica del proveedor.
- Para Green API: validar el token configurado / cabecera esperada antes de aceptar el payload.

**Flujo:**

1. `WebhookController::receive($provider)` valida la fuente vía `WebhookValidatorInterface` del proveedor (firma/token/IP allowlist).
2. Si es válido, guarda el evento **crudo** en `int_webhook_events` (provider, tipo, payload JSON, recibido_at, status `received`).
3. Responde `200` rápido (no procesa en línea pesada).
4. Un despachador (`WebhookDispatcher`) distribuye el evento a **listeners suscritos** (`WebhookListenerInterface`) registrados por otros módulos — p. ej. actualizar el estado de un mensaje saliente cruzando `providerMessageId` con `int_logs`.

**Usos previstos (Green API):** estado de mensajes enviados (entregado/leído), mensajes entrantes, confirmaciones, errores.

**Seguridad webhook:** validación obligatoria de firma/token, límite de tamaño de payload, rechazo de proveedores no registrados (404), registro de intentos inválidos sin volcar el cuerpo completo en logs de aplicación.

---

## 11. Configuración JSON / PHP propuesta

### 11.1 `config/integrations.php`

```php
<?php
use App\Kernel\EnvLoader;

return [
    // Canal por defecto y mapa clave → clase (resuelto por ChannelRegistry).
    'channels' => [
        'whatsapp' => [
            'driver'   => 'green_api',
            'class'    => \App\Infrastructure\Integrations\Channels\GreenApiWhatsappChannel::class,
            'enabled'  => (bool) EnvLoader::get('GREEN_API_ENABLED', false),
            'config'   => [
                'base_url'    => EnvLoader::get('GREEN_API_BASE_URL', 'https://api.green-api.com'),
                'instance_id' => EnvLoader::get('GREEN_API_INSTANCE', ''),
                'token'       => EnvLoader::get('GREEN_API_TOKEN', ''),
                'timeout'     => (int) EnvLoader::get('GREEN_API_TIMEOUT', 15),
            ],
        ],
        'email' => [
            'driver'  => 'mailer_adapter',
            'class'   => \App\Infrastructure\Integrations\Channels\EmailChannel::class,
            'enabled' => true, // adapta el MailerInterface ya configurado
            'config'  => [],
        ],
    ],

    // Rate limit básico por canal (envíos por ventana).
    'rate_limit' => [
        'whatsapp' => ['max' => 30, 'window_seconds' => 60],
    ],

    // Webhooks (Fase 2): proveedores que aceptan eventos entrantes.
    'webhooks' => [
        'green_api' => [
            'validator' => \App\Infrastructure\Integrations\Webhooks\GreenApiWebhookValidator::class,
            'token'     => EnvLoader::get('GREEN_API_WEBHOOK_TOKEN', ''),
        ],
    ],
];
```

### 11.2 `.env` (Fase 1)

```dotenv
GREEN_API_ENABLED=true
GREEN_API_BASE_URL=https://api.green-api.com
GREEN_API_INSTANCE=1101000001
GREEN_API_TOKEN=xxxxxxxxxxxxxxxxxxxx
GREEN_API_TIMEOUT=15
# Fase 2:
GREEN_API_WEBHOOK_TOKEN=
```

### 11.3 `config/modules/integrations.php` (manifiesto)

```php
<?php
declare(strict_types=1);

return [
    'clave'         => 'integrations',
    'nombre'        => 'Integraciones y Conectores',
    'descripcion'   => 'Capa desacoplada para enviar mensajes, consumir APIs y recibir webhooks (Green API, correo, futuros proveedores).',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/integrations.sql',
    'cruds'         => [],            // sus tablas int_* no se exponen por el CRUD Engine
    'permisos'      => ['integrations.ver', 'integrations.enviar', 'integrations.configurar'],
    'menu'          => [],            // opcional: bandeja de logs en fase futura
    'providers'     => [],
];
```

---

## 12. Clases / interfaces sugeridas

### 12.1 Domain — `app/Domain/Integrations/`

```php
// Value Object — lo único que un módulo de negocio construye.
final class MessageRequest {
    public function __construct(
        public readonly string $channel,     // "whatsapp" | "email" | ...
        public readonly string $recipient,   // teléfono / email / id según canal
        public readonly string $body,
        public readonly array  $meta = []     // source, record_id, dedupe_key, subject, etc.
    ) {}
}

// Value Object — resultado uniforme de un envío.
final class MessageResult {
    private function __construct(
        public readonly bool    $ok,
        public readonly ?string $providerMessageId,
        public readonly ?string $error,
        public readonly array   $rawResponse = []
    ) {}
    public static function sent(string $id, array $raw = []): self { return new self(true, $id, null, $raw); }
    public static function failed(string $error, array $raw = []): self { return new self(false, null, $error, $raw); }
}

interface MessageChannelInterface {
    public function key(): string;                       // "whatsapp"
    public function send(MessageRequest $request): MessageResult;
}

interface ApiConnectorInterface {
    /** @return array{status:int, body:string, json:array} */
    public function request(string $method, string $url, array $payload = [], array $headers = []): array;
}

interface IntegrationLogRepositoryInterface {
    public function record(string $channel, string $driver, string $recipientMasked,
                           string $status, ?string $providerMessageId, ?string $error, array $meta): void;
}

// Fase 2:
interface WebhookValidatorInterface { public function isValid(array $payload, array $headers): bool; }
interface WebhookListenerInterface  { public function handle(string $provider, array $payload): void; }
```

### 12.2 Application — `app/Application/Integrations/`

```php
final class ChannelRegistry {
    // Resuelve clave → MessageChannelInterface a partir de config/integrations.php (vía container).
    public function get(string $channelKey): MessageChannelInterface;
    public function has(string $channelKey): bool;
}

final class NotificationDispatcher {
    public function __construct(
        private ChannelRegistry $channels,
        private IntegrationLogRepositoryInterface $logs,
        private RateLimiter $rateLimiter            // reutiliza patrón LoginRateLimitService
    ) {}

    public function send(MessageRequest $request): MessageResult {
        // 1) canal habilitado? 2) rate-limit? 3) channel->send()
        // 4) SIEMPRE log (recipient enmascarado) 5) nunca propaga: try/catch → MessageResult::failed
    }
}
```

### 12.3 Infrastructure — `app/Infrastructure/Integrations/`

```php
final class HttpApiConnector implements ApiConnectorInterface { /* cURL, timeout, errores uniformes */ }

final class GreenApiWhatsappChannel implements MessageChannelInterface {
    public function __construct(private ApiConnectorInterface $http, private array $config) {}
    public function key(): string { return 'whatsapp'; }
    public function send(MessageRequest $r): MessageResult {
        // normaliza recipient → chatId, POST sendMessage, mapea respuesta → MessageResult
    }
}

final class EmailChannel implements MessageChannelInterface {
    public function __construct(private MailerInterface $mailer) {}     // adapta el correo existente
    public function key(): string { return 'email'; }
    public function send(MessageRequest $r): MessageResult {
        // construye MensajeCorreo (subject desde meta) y delega en $this->mailer->enviar()
    }
}

final class IntegrationLogRepository implements IntegrationLogRepositoryInterface { /* PDO → int_logs */ }
```

### 12.4 Bindings (`config/container.php`)

- `IntegrationLogRepositoryInterface` → `IntegrationLogRepository`.
- `ApiConnectorInterface` → `HttpApiConnector`.
- `ChannelRegistry` construido desde `config/integrations.php` (instancia canales habilitados; `EmailChannel` recibe el `MailerInterface` ya registrado).
- `NotificationDispatcher` singleton con sus dependencias.

---

## 13. Tablas sugeridas

### 13.1 Fase 1 — `int_logs` (única tabla nueva)

```sql
CREATE TABLE IF NOT EXISTS `int_logs` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel`             VARCHAR(40)     NOT NULL,            -- whatsapp | email | ...
  `driver`              VARCHAR(60)     NOT NULL,            -- green_api | mailer_adapter | ...
  `recipient_masked`    VARCHAR(190)    NOT NULL,            -- teléfono/email enmascarado
  `status`              VARCHAR(20)     NOT NULL,            -- sent | failed | skipped
  `provider_message_id` VARCHAR(190)    DEFAULT NULL,
  `error`               VARCHAR(500)    DEFAULT NULL,        -- mensaje saneado, sin secretos
  `meta`                JSON            DEFAULT NULL,         -- source, record_id, dedupe_key
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`          BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_int_logs_channel` (`channel`, `status`),
  KEY `idx_int_logs_provider_msg` (`provider_message_id`),
  KEY `idx_int_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> Nota: prefijo `int_*` (reservado para integraciones según `CLAUDE.md`). Estas tablas **no** se exponen por el CRUD Engine (que es para `dom_*`); las gestionan los repositorios del módulo.

### 13.2 Fases futuras (diseñadas, no implementadas)

| Tabla | Fase | Propósito |
|---|---|---|
| `int_webhook_events` | F2 | Eventos entrantes crudos (provider, tipo, payload, status). |
| `int_message_queue` | F2 | Cola de envíos (`pending/sending/sent/failed`, `attempts`, `next_attempt_at`). |
| `int_message_templates` | F3 | Plantillas reutilizables con placeholders (o reuso de `dom_mkt_plantillas`). |
| `int_accounts` | F4 | Cuentas/instancias por proveedor (multi-cuenta / multi-tenant). |
| `int_credentials` | F4 | Credenciales **cifradas** por cuenta (reemplaza `.env` cuando haga falta multi-cuenta). |

Las tablas existentes del módulo marketing (`dom_mkt_plantillas`, `dom_mkt_secuencias`) son el precedente directo para plantillas/secuencias y pueden inspirar `int_message_templates` o reutilizarse.

---

## 14. Riesgos técnicos

| Riesgo | Mitigación |
|---|---|
| **Envío síncrono bloquea la petición** si Green API tarda. | Timeout corto configurable (`GREEN_API_TIMEOUT`); cola asíncrona en F2; el dispatcher nunca propaga el fallo. |
| **Fuga de secretos en logs.** | `int_logs` guarda destinatario **enmascarado**, nunca tokens; `error` saneado; tokens solo en `.env`. |
| **Acoplamiento accidental** (un módulo usa Green API directo). | Única vía pública es `NotificationDispatcher`; canales viven en Infra; revisión de PR. |
| **Acción CRUD ejecutada sin permiso / manipulada.** | RBAC re-validado en servidor + CSRF (ya provistos por el Engine); `permission` obligatorio en la acción. |
| **Webhook falsificado** (F2). | Validación de firma/token obligatoria, allowlist, rechazo 404 de proveedores no registrados. |
| **Reenvío duplicado** en recordatorios (F3). | `dedupe_key` en `meta` + marca temporal en el registro de negocio; idempotencia en el comando. |
| **Rate limit del proveedor.** | Rate-limit local por canal (`config/integrations.php`) antes de llamar a la API. |
| **Número de teléfono mal formado.** | Normalización a E.164 dentro del canal; `MessageResult::failed` + log `skipped` si es inválido. |

---

## 15. Decisiones recomendadas (confirmadas)

1. **Nombre del módulo: `integrations`** — genérico, futuro-proof, alineado con el prefijo `int_*`.
2. **Despacho síncrono + `int_logs`** en Fase 1; cola/reintentos en Fase 2 con la misma interfaz.
3. **Credenciales en `.env`** en Fase 1; `int_credentials` cifrada solo cuando se requiera multi-cuenta (F4).
4. **Webhooks: solo diseño** en Fase 1; implementación en Fase 2.
5. **Handler delgado por módulo** para disparar envíos desde CRUD (cero cambios al core); acción declarativa parametrizada como mejora futura.
6. **Incluir `EmailChannel`** adaptando el `MailerInterface` actual desde Fase 1, para demostrar que la abstracción es realmente multi-canal y preparar el módulo de notificaciones.
7. **Tablas `int_*` gestionadas por repositorios del módulo**, fuera del CRUD Engine.

---

## 16. Plan de implementación por fases

### Fase 1 — Núcleo de envío saliente (este SPEC)
1. Domain: `MessageRequest`, `MessageResult`, `MessageChannelInterface`, `ApiConnectorInterface`, `IntegrationLogRepositoryInterface`.
2. Infra: `HttpApiConnector`, `GreenApiWhatsappChannel`, `EmailChannel`, `IntegrationLogRepository`.
3. Application: `ChannelRegistry`, `NotificationDispatcher`, `RateLimiter` (reuso de patrón existente).
4. Config: `config/integrations.php`, bindings en `config/container.php`, `.env.example`.
5. Módulo: `config/modules/integrations.php`, toggle en `vertical.php`, `database/schema/modules/integrations.sql` (`int_logs` + permisos RBAC, idempotente).
6. Demo de integración con CRUD: un recurso `dom_*` con acción `handler` + handler delgado de ejemplo.
7. Tests (arnés `php tests/run.php`): canal fake, dispatcher (éxito/fallo/rate-limit), enmascarado de logs, no-propagación de errores.

### Fase 2 — Webhooks + cola
8. `WebhookController`, validadores por proveedor, `int_webhook_events`, `WebhookDispatcher` + listeners.
9. `int_message_queue`, worker CLI, reintentos con backoff; `NotificationDispatcher` puede encolar en vez de enviar directo.

### Fase 3 — Plantillas + recordatorios
10. `int_message_templates` (o reuso de `dom_mkt_plantillas`), render de placeholders, selección desde acción/handler.
11. Comando CLI de recordatorios enganchado a calendario, con idempotencia.

### Fase 4 — Multi-cuenta + Notificaciones
12. `int_accounts`, `int_credentials` cifradas, selección de cuenta por canal/tenant.
13. Módulo Notificaciones (preferencias por usuario, fallback multi-canal, centro in-app) sobre la misma fachada.

---

## 17. Criterios de aceptación (Fase 1)

1. Un módulo de negocio puede enviar un WhatsApp construyendo **solo** un `MessageRequest` y llamando a `NotificationDispatcher::send()`; **no** referencia Green API en ningún punto.
2. La misma fachada envía por **email** (vía `EmailChannel` → `MailerInterface`) cambiando únicamente `channel`.
3. Una acción de fila del CRUD (`type: handler`) dispara el envío con permiso `integrations.enviar`, CSRF y confirmación, **sin cambios al core del CRUD Engine**.
4. **Todo** intento de envío queda en `int_logs` con estado (`sent`/`failed`/`skipped`), destinatario **enmascarado** y **sin** secretos.
5. Un fallo del proveedor (timeout, 4xx/5xx, número inválido) **no** lanza excepción al flujo de negocio: devuelve `MessageResult::failed` y se registra.
6. Las credenciales se leen **solo** de `.env`; no hay tokens en código ni en DB.
7. Agregar un proveedor nuevo (ej. Twilio) requiere **solo** una clase de canal nueva en Infra + entrada en `config/integrations.php`, sin tocar módulos de negocio ni el dispatcher.
8. El módulo es **desactivable** desde `config/vertical.php` sin romper el resto del sistema.
9. El bootstrap SQL (`int_logs` + permisos) es **idempotente** (re-ejecutable en cada despliegue).

---

## Apéndice A — Mapa de reuso (qué ya existe en el framework)

| Necesidad del módulo | Patrón/clase existente que se reutiliza |
|---|---|
| Puerto desacoplado de envío | `MailerInterface` (Domain) + binding por config en `container.php`. |
| Disparar acción desde CRUD | Acción `type: handler` + `config/crud_handlers.php` + `CrudActionHandlerInterface` + `CrudActionContext`. |
| Enviar mensaje desde un evento | `AutoresponderHandler` (envía correo desde captura de lead). |
| Estructura de módulo opcional | Manifiesto `config/modules/*.php` + toggle `vertical.php` + `routes/{modulo}.php` condicional + `schema/modules/*.sql`. |
| Plantillas con placeholders | `dom_mkt_plantillas` (`{{nombre}}`), `dom_mkt_secuencias` (pasos JSON). |
| Ruta pública con seguridad | `POST /lead` (CSRF) — webhooks análogos sin CSRF + firma. |
| Rate limiting | `LoginRateLimitService`. |
| Permisos / menú / bootstrap idempotente | Patrón `database/schema/modules/marketing.sql`. |
