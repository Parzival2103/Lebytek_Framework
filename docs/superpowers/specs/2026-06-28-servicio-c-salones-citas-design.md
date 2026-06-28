# Servicio C — Vertical salones/citas (`dom_salon_*`) — Design Spec

> **Estado:** diseño aprobado, pendiente de plan de implementación (writing-plans).
> **Contexto:** uno de los 4 servicios de dominio (A–D) descritos en
> `docs/superpowers/specs/2026-06-27-separacion-framework-v1-dominio-design.md` §7.
> Servicio **C = Vertical salones/citas** (`dom_salon_*`): producto multi-salón
> donde cada salón gestiona sus clientes, servicios y citas, con comunicación
> WhatsApp automática (confirmación + recordatorio). Se apoya en los módulos
> **calendario** + **integrations** del framework y en `int_accounts`.

---

## 0. ⚠️ Prerrequisito de verificación antes de planear (LEER PRIMERO)

**Este spec NO se ejecuta todavía.** Al momento de escribirlo, el plan de
**Separación Framework v1.0 / Dominio** (`docs/superpowers/plans/2026-06-27-separacion-framework-v1-dominio.md`)
está **en curso** (rename `App\` → `Lebytek\Framework\`, partición de
`container.php`, movimiento de archivos al esqueleto, creación de Repo 2). Ya movió
`integrations` al paquete (`src/Application/Integrations/NotificationDispatcher.php`,
namespace `Lebytek\Framework\…`), pero **aún no termina ni está verificado**.

**Antes de generar el plan de implementación de C es obligatorio re-verificar
contra el código real**:

1. **Namespaces.** Confirmar el namespace final de cada pieza que C consume:
   `NotificationDispatcher` (+ `sendVia`, ver §2), `MessageRequest`/`MessageResult`,
   `IntegrationAccountRepositoryInterface`, módulo **calendario**
   (`CalendarConfigLoader`, controller, JS), `CrudResourceService`/`CrudDataService`,
   middlewares, `ConfiguracionService`. Marketing/calendario/integrations pueden
   haber cambiado de ubicación (paquete vs Repo 2).
2. **Prerrequisito P0 de B (`sendVia`).** C **depende** de
   `NotificationDispatcher::sendVia(int $accountId, MessageRequest $request)`
   (envío con instancia explícita), definido en el spec del Servicio B
   (`2026-06-28-servicio-b-api-sola-whatsapp-design.md` §2). Verificar si ya está
   implementado en el paquete; si no, es **dependencia dura** de C (no se reimplementa
   aquí).
3. **Módulo calendario disponible.** Confirmar que el módulo `calendario` existe y es
   togglable en el repo destino (C lo declara como `requiere`).
4. **Datos "verificados (2026-06-28)".** Re-confirmar firmas y rutas: el terreno se
   mueve.

> Regla operativa: **no escribir el plan de implementación de C hasta que la
> separación esté terminada y verde** (`php tests/run.php` pasando en el repo destino)
> y `sendVia` exista. Entonces re-leer las piezas listadas y ajustar el plan.

---

## 1. Objetivo y alcance

Vender **gestión de citas con comunicación WhatsApp** como producto multi-salón.
Cada **salón** es un cliente Lebytek con su propia instancia Green API
(`int_accounts`). El salón gestiona su catálogo de **servicios**, sus **clientes
finales** y su agenda de **citas**, y el sistema mantiene comunicación automática
con esos clientes: **confirmación** al agendar y **recordatorio** antes de la cita.

### Alcance MVP (este spec)
- Modelo multi-salón con **scope por `salon_id`**.
- CRUDs admin de `salones`, `clientes`, `servicios`, `citas`.
- **Calendario** de citas reusando el módulo `calendario` (cero código nuevo de
  calendario): vistas mes/semana/día/tabla + widget dashboard.
- **Confirmación WhatsApp** al pasar la cita a `confirmada` (decisión §5.3).
- **Recordatorio WhatsApp** automático vía **comando CLI idempotente** corrible por
  cron, dentro de una **ventana configurable** (global, con override por salón).
- Plantillas de mensaje por salón (con default global).

### Fuera de alcance (iteraciones / otros specs)
- **Recurrencia de citas** (series, RRULE, edición serie-vs-ocurrencia).
- **Login del dueño de salón** (usuario-tenant que ve solo su salón): MVP usa admin
  central Lebytek con scope por `salon_id`.
- Pagos / cobros / facturación.
- Seguimiento post-cita (gracias / reagendar), multimedia, plantillas enriquecidas.
- **Webhooks entrantes** (respuestas del cliente por WhatsApp): diferido como en los
  specs de integrations.
- Auto-aprovisionamiento de la instancia por el salón (asignación manual por admin).

### Regla de separación respetada
Ningún `dom_salon_*` toca Green API directo: **todo envío pasa por
`NotificationDispatcher::sendVia`** (fachada framework), que carga la instancia del
salón y descifra su token. `dom_salon_salones.int_account_id` y `.lead_id` son
referencias **blandas** (sin FK dura hacia tablas framework/otros módulos). Ningún
archivo del núcleo/framework referencia clases de `App\…Salon`.

---

## 2. Dependencia de framework: `sendVia` (P0 de B)

C reutiliza el camino de **envío con instancia explícita** definido en el Servicio B:

```php
// Lebytek\Framework\Application\Integrations\NotificationDispatcher
public function sendVia(int $accountId, MessageRequest $request): MessageResult;
```

Comportamiento (de B §2): carga `int_account` por id → construye canal efímero con
sus credenciales descifradas → aplica rate-limit → envía → registra en `int_logs`
con destinatario enmascarado → **nunca lanza** (degrada a `MessageResult::failed`);
cuenta inexistente/inactiva → `failed('account_unavailable')`.

> C **no implementa nada de Green API ni de `sendVia`**: es dependencia. Si B aún no
> lo entregó, el plan de C debe ordenar P0 primero (es cambio de paquete framework).

---

## 3. Modelo de datos (`dom_salon_*`)

Prefijo `dom_*` (dominio), nunca en el schema base del framework. Bootstrap
idempotente en `database/schema/modules/salon.sql`. Todas las tablas de operación
llevan `salon_id` para el scope.

### 3.1 `dom_salon_salones` (el cliente Lebytek = tenant)
| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `nombre` | VARCHAR(190) | nombre comercial del salón |
| `email` | VARCHAR(190) | contacto |
| `telefono` | VARCHAR(40) NULL | |
| `lead_id` | BIGINT UNSIGNED NULL | soft ref a `dom_mkt_leads` (origen comercial) |
| `int_account_id` | BIGINT UNSIGNED NULL | soft ref a `int_accounts` (su instancia WhatsApp) |
| `ventana_recordatorio_horas` | INT UNSIGNED NULL | override opcional de la ventana global (§5.4) |
| `estado` | VARCHAR(20) DEFAULT 'activo' | `activo` \| `suspendido` |
| `created_at` | DATETIME | |

> 1 salón → 1 instancia (`int_account_id`). Asignación **manual** por admin.

### 3.2 `dom_salon_clientes` (clientes finales del salón)
| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `salon_id` | BIGINT UNSIGNED | scope |
| `nombre` | VARCHAR(190) | |
| `telefono` | VARCHAR(40) | destino de WhatsApp (requerido para mensajería) |
| `email` | VARCHAR(190) NULL | |
| `notas` | TEXT NULL | |
| `created_at` | DATETIME | |

- Índice `KEY(salon_id)`.

### 3.3 `dom_salon_servicios` (catálogo del salón)
| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `salon_id` | BIGINT UNSIGNED | scope |
| `nombre` | VARCHAR(190) | |
| `duracion_min` | INT UNSIGNED | usado para sugerir `fecha_fin` |
| `precio` | DECIMAL(10,2) NULL | informativo |
| `activo` | TINYINT(1) DEFAULT 1 | |

- Índice `KEY(salon_id, activo)`.

### 3.4 `dom_salon_citas` (agenda; recurso del calendario)
| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `salon_id` | BIGINT UNSIGNED | scope |
| `cliente_id` | BIGINT UNSIGNED | FK lógica a `dom_salon_clientes` |
| `servicio_id` | BIGINT UNSIGNED | FK lógica a `dom_salon_servicios` |
| `fecha_inicio` | DATETIME | mapping `start` del calendario |
| `fecha_fin` | DATETIME NULL | mapping `end` (sugerido por `duracion_min`) |
| `estado` | VARCHAR(20) DEFAULT 'pendiente' | `pendiente` \| `confirmada` \| `cancelada` \| `completada` |
| `confirmacion_message_id` | VARCHAR(190) NULL | `providerMessageId` de la confirmación |
| `recordatorio_enviado_at` | DATETIME NULL | NULL = recordatorio pendiente (clave de idempotencia del job) |
| `notas` | TEXT NULL | |
| `created_by` | BIGINT UNSIGNED NULL | usuario admin que la creó |
| `created_at` | DATETIME | |

- Índices: `KEY(salon_id, fecha_inicio)` (calendario + barrido), `KEY(estado)`,
  `KEY(recordatorio_enviado_at)`.

### 3.5 `dom_salon_plantillas` (texto de mensajes)
| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `salon_id` | BIGINT UNSIGNED NULL | NULL = plantilla **default global**; con valor = override del salón |
| `tipo` | VARCHAR(30) | `confirmacion` \| `recordatorio` |
| `cuerpo` | TEXT | texto con placeholders `{cliente} {servicio} {fecha} {salon}` |
| `activo` | TINYINT(1) DEFAULT 1 | |

- Resolución: plantilla del salón para el `tipo` si existe y activa; si no, la global.
- Índice `KEY(salon_id, tipo, activo)`.

---

## 4. Calendario (reuso total del módulo `calendario`)

C **no escribe código de calendario**: declara un recurso CRUD + una definición de
calendario, y hereda vistas, scope, permisos e interacción.

- `config/cruds/salon_citas.json` — recurso CRUD de `dom_salon_citas`
  (`permission_prefix: salon`), con columnas/forms de cita; relaciones a cliente y
  servicio como selects.
- `config/calendars/salon_citas.json` — mapeo:
  ```json
  {
    "calendar": { "key": "salon_citas", "title": "Agenda de Citas", "resource": "salon_citas", "icon": "bi-calendar-event" },
    "mapping": {
      "start": "fecha_inicio", "end": "fecha_fin", "all_day": false,
      "title": "{cliente} — {servicio}",
      "color": { "by": "estado", "map": { "pendiente": "warning", "confirmada": "success", "cancelada": "secondary", "completada": "info" } }
    },
    "views": { "default": "week", "enabled": ["month", "week", "day", "table"] },
    "interaction": { "create_on_slot": true, "open_detail": true, "edit_from_event": true },
    "filters": [ { "field": "estado", "label": "Estado" } ],
    "dashboard_widget": true
  }
  ```
- Toda edición (crear/editar/cancelar) reusa los endpoints `/admin/crud/salon_citas/...`
  con CSRF + `#confirmModal`, exactamente como `demo_citas`.
- El **scope por `salon_id`** lo aplica el mecanismo de scope del CRUD Engine
  (compartido por `list()` y `eventsInRange()`); el calendario nunca arma SQL. (Ver
  §6 sobre cómo se ata el scope a `salon_id`.)

---

## 5. Comunicación WhatsApp (lo nuevo de C)

Todo envío va por `NotificationDispatcher::sendVia(salon.int_account_id, MessageRequest('whatsapp', cliente.telefono, $texto))`.
El texto sale de `RenderPlantillaService` (resuelve plantilla por `(salon_id, tipo)`
y sustituye placeholders). Si el salón no tiene `int_account_id` o el cliente no
tiene teléfono → no se intenta enviar (se registra y se omite, sin romper flujo).

### 5.1 `RenderPlantillaService`
`render(string $tipo, Cita $cita): string` — resuelve la plantilla (salón → global),
sustituye `{cliente}`, `{servicio}`, `{fecha}` (formateada), `{salon}`. Sin plantilla
activa → texto fallback mínimo del framework.

### 5.2 `EnviarConfirmacionCitaUseCase`
Entrada: `citaId`. Carga cita+cliente+salón → `sendVia` con plantilla
`confirmacion` → guarda `confirmacion_message_id` del `MessageResult` (si `ok`). Si
`failed`, registra y **no** rompe la transición de la cita (el dispatcher nunca lanza).

### 5.3 Disparo de la confirmación — **al pasar a `confirmada`**
La confirmación se envía cuando la cita **transiciona a `estado = confirmada`** (no
al crear un borrador `pendiente`), para no mensajear citas no confirmadas. Se engancha
vía **hook del CRUD** de `salon_citas` (handler en `config/crud_handlers.php`) que
detecta el cambio de estado `* → confirmada` e invoca el UseCase. Respaldo: acción de
fila admin "Enviar confirmación" (manual) para reenvíos.

> Idempotencia de confirmación: si `confirmacion_message_id` ya existe, la acción
> automática no reenvía (el botón manual sí permite forzar reenvío).

### 5.4 `EnviarRecordatoriosUseCase` + comando CLI (recordatorio automático)
- **Ventana:** `salon_recordatorio_horas` global en `cfg_*` (default 24); cada salón
  puede sobrescribir con `dom_salon_salones.ventana_recordatorio_horas`.
- **Barrido (idempotente, sin colas):** selecciona citas con
  `estado IN ('confirmada','pendiente')`,
  `recordatorio_enviado_at IS NULL`, y `fecha_inicio` dentro de
  `[ahora, ahora + ventana]` (por salón, usando su ventana efectiva). Para cada una:
  `sendVia` con plantilla `recordatorio`; si `ok`, marca
  `recordatorio_enviado_at = NOW()`. Si `failed`, **no** marca (reintenta en la
  próxima corrida).
- **Ejecución:** `scripts/salon_recordatorios.php`, pensado para **cron del VPS**
  (p. ej. cada 15 min). Stateless salvo la marca en BD → re-ejecutable sin duplicar.
  El comando reporta conteo de enviados/fallidos (apto para log de cron).

> Este comando **define el contrato de scheduling del dominio sin asumir
> infraestructura de colas**: la idempotencia es la marca `recordatorio_enviado_at` +
> la ventana. Citas canceladas/completadas o ya recordadas no entran al barrido.

---

## 6. Componentes (Onion, lado dominio `App\…`, en Repo 2)

### 6.1 Domain (`app/Domain/Salon/`)
- Entidades: `Salon`, `ClienteSalon`, `Servicio`, `Cita`, `Plantilla`.
- Value Objects: `VentanaRecordatorio` (horas efectivas = override de salón ?? global).
- Interfaces repo: `SalonRepositoryInterface`, `ClienteSalonRepositoryInterface`,
  `ServicioRepositoryInterface`, `CitaRepositoryInterface`, `PlantillaRepositoryInterface`.
- Policy: `CitaPolicy` — validación mínima: `fecha_fin > fecha_inicio` (si hay fin);
  cliente y servicio pertenecen al **mismo `salon_id`** que la cita. (Sin
  detección de solapamiento en MVP; se puede añadir después.)

### 6.2 Application (`app/Application/Salon/`)
- UseCases: `EnviarConfirmacionCitaUseCase`, `EnviarRecordatoriosUseCase`.
- Services: `RenderPlantillaService` (resolución + placeholders).
- Reusa: `IntegrationAccountRepositoryInterface` (vía la fachada) y
  `NotificationDispatcher::sendVia`.

### 6.3 Infrastructure (`app/Infrastructure/Salon/`)
- Repos PDO: `PdoSalonRepository`, `PdoClienteSalonRepository`,
  `PdoServicioRepository`, `PdoCitaRepository`, `PdoPlantillaRepository`.
- No implementa nada de Green API.

### 6.4 Presentation
- **Admin vía CRUD Engine** (RBAC, `/admin/crud/...`): `salon_salones`,
  `salon_clientes`, `salon_servicios`, `salon_citas` (configs en `config/cruds/`).
  El CRUD de `salon_citas` declara el **handler** de confirmación (§5.3) y la acción
  manual de reenvío.
- **Calendario** (`/admin/calendario/salon_citas`): del módulo `calendario`.
- **CLI:** `scripts/salon_recordatorios.php` (entrada del barrido).
- Sin pantallas a medida adicionales en MVP.

### 6.5 Scope por `salon_id`
MVP: admin central Lebytek gestiona todos los salones. El scope por `salon_id` se
declara en los `config/cruds/salon_*.json` como **filtro/scope de columna**
(mecanismo de scope del CRUD Engine), de modo que `list()`, el detalle y el feed del
calendario queden acotados por salón seleccionado. La atadura usuario→salón
(login de dueño de salón) se **difiere**; el plan debe fijar cómo se elige el
`salon_id` activo en el admin central (p. ej. filtro de salón en el listado).

### 6.6 RBAC
Permisos: `salon.ver`, `salon.gestionar`, `salon.citas`. Asignados al rol
`administrador` en el `bootstrap_sql`. La mensajería WhatsApp no expone endpoint
público (no usa API key); se dispara desde admin/cron.

---

## 7. Registro, toggle y arranque

- **Módulo nuevo** `salon`: manifiesto `config/modules/salon.php`:
  ```php
  return [
    'clave' => 'salon',
    'nombre' => 'Salones y Citas',
    'requiere' => ['core', 'crud-engine', 'calendario', 'integrations'],
    'bootstrap_sql' => 'database/schema/modules/salon.sql',
    'cruds' => ['salon_salones','salon_clientes','salon_servicios','salon_citas'],
    'calendars' => ['salon_citas'],
    'permisos' => ['salon.ver','salon.gestionar','salon.citas'],
    'menu' => [ /* entradas bajo "Salones" + acceso al calendario */ ],
    'providers' => [ /* crud handler de confirmación; se ata según mecanismo vigente */ ],
  ];
  ```
- Toggle `modules.salon` en `config/vertical.php`.
- **Bootstrap idempotente** (`salon.sql`): tablas `dom_salon_*` (`IF NOT EXISTS`),
  permisos RBAC, menú, plantillas **default globales** (`salon_id=NULL`) de
  `confirmacion` y `recordatorio`, y **datos demo** (1 salón, 2 servicios, 2
  clientes, citas en el mes actual) con patrón `WHERE NOT EXISTS`.
- Binding del CLI + handler en la zona de **dominio** del proyecto (no en el
  `FrameworkServiceProvider`).

---

## 8. Manejo de errores y seguridad

- **Token de instancia:** lo descifra el framework dentro de `sendVia`; el dominio
  nunca ve el token Green API en claro.
- **Enmascarado:** el envío se registra en `int_logs` (framework) con destinatario
  enmascarado; el dominio guarda el teléfono del cliente (necesario para la
  operación) pero no lo expone en logs propios.
- **Degradación:** `sendVia` nunca lanza; confirmación/recordatorio fallidos no
  rompen la cita ni el barrido. El recordatorio fallido **no** marca
  `recordatorio_enviado_at` → reintento natural en la próxima corrida.
- **Aislamiento por salón:** todo `list`/detalle/feed/envío filtra por `salon_id`; un
  salón nunca ve ni mensajea datos de otro.
- **Rate-limit:** el del framework dentro de la fachada (por cuenta/canal).

---

## 9. Estrategia de pruebas (`php tests/run.php`, sin Green API real)

Dobles: `FakeNotificationDispatcher` (registra `accountId` y devuelve `MessageResult`
programado), repos en memoria o sobre DB de test. Casos:

1. **Confirmación:** al transicionar cita a `confirmada`, `sendVia` recibe **el
   `int_account_id` correcto del salón** y el texto renderizado de la plantilla;
   guarda `confirmacion_message_id`; un borrador `pendiente` **no** dispara envío.
2. **Idempotencia de confirmación:** con `confirmacion_message_id` ya presente, la vía
   automática no reenvía.
3. **Recordatorio/job:** solo entran citas dentro de la ventana, `confirmada/pendiente`
   y `recordatorio_enviado_at IS NULL`; tras envío `ok` se marca; un envío `failed`
   **no** marca (reintenta); citas fuera de ventana o canceladas se ignoran.
4. **Ventana:** override de salón gana sobre la global (`VentanaRecordatorio`).
5. **Plantillas:** plantilla de salón gana sobre la global; placeholders sustituidos;
   sin plantilla → fallback.
6. **Scope:** `clientes/servicios/citas` de un salón no aparecen en el scope de otro;
   `CitaPolicy` rechaza cliente/servicio de otro salón.
7. **Calendario:** el feed de `salon_citas` respeta el scope por salón (reuso del
   mecanismo del módulo calendario; test de humo).
8. **Desacople:** ningún archivo del núcleo/framework referencia `App\…Salon`; toggle
   off deja el módulo inerte.

---

## 10. Entregables y secuencia

> Precondición global: separación v1.0 terminada y verde + `sendVia` existente (§0, §2).

1. **Datos** — `dom_salon_*` schema/bootstrap + plantillas default + demo +
   manifiesto + toggle + RBAC.
2. **Dominio/Aplicación** — entidades, VO `VentanaRecordatorio`, repos, `CitaPolicy`,
   `RenderPlantillaService`, `EnviarConfirmacionCitaUseCase`, `EnviarRecordatoriosUseCase`.
3. **Admin CRUD** — configs `salon_*` + handler de confirmación + acción manual.
4. **Calendario** — `config/cruds/salon_citas.json` + `config/calendars/salon_citas.json`.
5. **CLI** — `scripts/salon_recordatorios.php` (barrido idempotente, apto para cron).
6. **Pruebas** — arnés verde con los 8 grupos de casos.

### Fuera de alcance (próximas iteraciones)
Recurrencia, login de dueño de salón, pagos, seguimiento post-cita, webhooks
entrantes, multimedia. Cada uno: su propio spec → plan.

---

## 11. Decisiones registradas
- **Multi-salón**: salón = cliente Lebytek; scope por `salon_id`; 1 salón → 1
  instancia (`int_account_id` soft ref).
- **Recurrencia diferida**: MVP solo citas sueltas sobre el módulo calendario tal cual.
- **WhatsApp** = confirmación al pasar a `confirmada` + recordatorio por **comando
  CLI idempotente** (cron), con marca `recordatorio_enviado_at` + ventana.
- **Ventana de recordatorio** = config global (default 24h) con **override por salón**.
- **Calendario** = reuso total del módulo `calendario` (cero código nuevo).
- **Envío** = siempre vía `NotificationDispatcher::sendVia` (P0 de B); ningún
  `dom_salon_*` toca Green API.
- **Login de dueño de salón diferido**: MVP = admin central + scope por salón.
- **Spec agnóstico al timing de la separación**, con prerrequisito explícito (§0).
