# Membresía recurrente, dunning 48h, conversión y churn

**Fecha:** 2026-07-15  
**Estado:** Diseño actualizado (plantillas + control de deuda) — pendiente de re-aprobación humana  
**Repo:** Lebytek_Framework (Portal / lebytek.com) + impacto en WhatsApiLebytek (api)  
**Rama de trabajo sugerida:** `feature/membership-recurring-dunning` (sobre `feature/backoffice-api-integration`)  
**Predecesor:** `docs/superpowers/specs/2026-07-15-payments-gateway-design.md` (pago único Stripe Checkout — **fase 1, ya implementada**)  
**Companion demo/compra:** `docs/superpowers/specs/2026-07-14-manual-membership-purchase-design.md`  
**Companion api activate-plan:** WhatsApiLebytek `docs/superpowers/specs/2026-07-14-plan-activation-and-package-limits-design.md`

## Problema

1. Tras activar un plan paid, el lead sigue mostrando `plan_slug = demo` y el botón **Dar de baja demo** sigue aplicando a clientes ya convertidos.
2. El dashboard de “churn” mezcla embudo demo con retención de pagadores; las columnas `converted_at` / `cancelled_at` y el KPI `demo_conversion_pct` ya existen pero no están cableados al flujo de activación.
3. La pasarela v1 cobra **una sola vez**. El producto comercial es **membresía recurrente**; hace falta renovación, gracia ante fallo de pago, correos de reintento y baja solo después de la gracia — sin marcar churn en el momento del decline.
4. El CRUD **Plantillas de correo** (`mkt_plantillas` → `dom_mkt_plantillas`) está vacío / desconectado: ops no puede editar copy, y los correos transaccionales viven solo en vistas PHP. Añadir dunning (#1/#2) sin cablear plantillas **duplica deuda**.

## Objetivo

1. **Conversión lead:** al primer activate-plan exitoso, actualizar el lead (`plan_slug` paid, `converted_at`) y ocultar **Dar de baja demo**.
2. **Métricas:** conversión = demo → paid; churn = pagadores que llegan a baja comercial (no demos, no `past_due`).
3. **Recurrencia Stripe:** suscripciones (ciclo mensual/anual) encima del puerto de pagos ya abierto.
4. **Dunning 48h:** fallo de pago → correo #1 + link de reintento con vigencia 48h → sin pago → baja + correo #2 con link permanente de reactivación.
5. **Plantillas de correo operables:** un solo camino de render desde `dom_mkt_plantillas` (CRUD editable); seed del catálogo completo; dunning y membresía usan claves, no HTML hardcodeado nuevo.

## Non-goals (v1 de esta spec)

- Prorrateo / change-plan mid-cycle / trials pagos.
- Multiplicar reintentos Stripe Smart Retries más allá de alinear webhooks (se puede documentar como fase posterior).
- Borrar instancia Green al cancelar por no pago (reactivación debe reusar el mismo tenant).
- Merge a `main` del Framework.
- Rediseñar el puerto `PaymentGatewayInterface` desde cero (extender; no reemplazar).
- Editor WYSIWYG avanzado / versionado de plantillas / A/B de asuntos (el CRUD textarea + `{{vars}}` basta).
- Migrar correos de **auth del framework** (`verificacion`, `recuperacion` en `src/`) a `dom_mkt_plantillas` (siguen siendo plataforma, no marketing).

## Decisiones (brainstorming)

| Tema | Elección |
|------|----------|
| Modelo de cobro | **Stripe Subscriptions** (fase 2). Checkout one-shot de fase 1 sigue válido para transferencia y como puente; el path tarjeta recurrente usa Subscription + Customer. |
| Fallo de pago | **No es churn.** Entra `past_due` / gracia 48h. |
| Gracia | **48 horas fijas** desde el evento de fallo. Clickear el link **sin pagar no extiende** la ventana. |
| Tras 48h sin pago | Baja comercial + `cancelled_at` + **sí cuenta como churn** + correo #2. |
| Reactivación | Link **permanente** (o de larga vida) atado a la membresía/suscripción; pago OK → vuelve `active`, limpia cancelación. |
| Lead `estado` | v1: puede permanecer `demo_enviada` si `plan_slug` + `converted_at` son la verdad comercial; recomendado a corto plazo añadir estado `convertido` (opcional, no bloqueante). |
| Baja demo vs baja paid | **Dar de baja demo** solo si no convertido. Baja paid = flujo dunning/ops, no deprovision demo. |
| Baja API post-gracia | **Soft cancel:** revocar tokens / marcar commercial cancelled o equivalente; **conservar** tenant + instancia Green para reactivar. |
| Churn event | `(c)` ambos caminos: `cancelled_at` por dunning timeout **o** baja ops explícita. Decline solo no marca churn. |
| Correos marketing | **Fuente de verdad = `dom_mkt_plantillas`**. Un `MarketingMailRenderer` (o equivalente) carga por `clave`, sustituye `{{var}}`, envía vía `MailerInterface`. Vistas PHP actuales = seed/fallback de migración, no camino paralelo permanente. |
| Dunning vs plantillas | **No** implementar correos #1/#2 como nuevas vistas PHP sueltas. Primero catálogo + renderer; luego dunning consume claves. |

---

## Modelo de datos (reuso + extensiones)

### Ya existe en `dom_mkt_leads` (migración `20260706120000_mkt_leads_churn_columns`)

| Columna | Uso en esta spec |
|--------|------------------|
| `plan_slug` | `demo` en provision; al convertir → `starter`\|`business`\|`empresa` |
| `converted_at` | NOW() en el primer activate-plan exitoso ligado al lead |
| `cancelled_at` | NOW() cuando la membresía paid se da de baja (post-gracia u ops) |
| `demo_expires_at` | Solo relevante mientras `converted_at IS NULL` |
| `paquete_id` | Opcional alinear al paquete de la orden al convertir |

### Ya existe en reportes

- `rep_churn_monthly`: `churn_rate_pct`, `demos_started`, `demos_converted`, `demo_conversion_pct`, …
- `rep_risk_signals`: señales abiertas (p. ej. `payment_failed` / `past_due` durante gracia)

### Nuevo / a extender (Portal)

**Membresía recurrente** (nombre tentativo `dom_mkt_membresias` o columnas en orden + tabla de suscripción):

| Campo | Notas |
|-------|--------|
| `lead_id`, `api_tenant_public_id` | Vínculo |
| `plan_slug`, `ciclo` | Snapshot comercial |
| `status` | `active` \| `past_due` \| `cancelled` |
| `stripe_customer_id`, `stripe_subscription_id` | IDs Stripe |
| `current_period_end` | Para UX y jobs |
| `grace_started_at`, `grace_ends_at` | Ventana 48h |
| `cancelled_at` | Espejo / fuente para churn |
| `reactivation_token` (hash) | Link permanente post-baja |
| `retry_token` + `retry_expires_at` | Link 48h del correo #1 |

Órdenes (`dom_mkt_ordenes`) siguen siendo el comprobante de **cada cobro** (alta o renovación); la membresía es el **estado vivo** del ciclo.

### Api (WhatsApiLebytek)

- Tras activate-plan: tenant `commercial_status=active` (ya).
- Nuevo (o reusar): operación **suspend / cancel commercial** sin borrar instancia (platform token), y **reactivate** al cobrar de nuevo.
- Detalle exacto de endpoints → spec companion api en el plan de implementación; no inventar límites aquí más allá de soft-cancel + keep instance.

---

## Flujo A — Conversión (demo → paid)

Disparador: `ActivateMembershipFromOrderService` éxito (authorize manual **o** Stripe confirm **o** Activar plan retry).

1. Resolver `lead_id` de la orden (si null, no-op en lead; log ops).
2. Lead:
   - `plan_slug` ← `orden.paquete_slug`
   - `converted_at` ← NOW() si estaba null (idempotente)
   - `demo_expires_at` ← NULL (o ignorar en queries)
   - opcional: `estado` ← `convertido`
3. CRUD leads:
   - Badge Plan muestra el slug paid.
   - Acción **Dar de baja demo**: `visible_when` solo si `converted_at` vacío **y** `plan_slug = demo` (o estado demo).
4. Demos activas / por vencer: excluir `converted_at IS NOT NULL` (parcialmente ya en “por vencer”).

```
demo_enviada + plan=demo
  → pago + activate-plan OK
  → plan=starter|business|empresa, converted_at set
  → sin botón Dar de baja demo
```

---

## Flujo B — Recurrencia (alta)

1. Cliente elige tarjeta + ciclo en checkout.
2. Framework crea (o reutiliza) Stripe Customer + Subscription al Price del plan/ciclo.
3. Primer `invoice.paid` / `checkout.session.completed` (modo subscription) → misma activación de plan que hoy + marcar membresía `active` + conversión lead.
4. Transferencia bancaria: sin subscription Stripe; renovación manual/ops fuera de este dunning automático (documentar gap).

El puerto de pagos ya admite “pago único y suscripción” en diseño; esta fase **implementa** el path subscription en app + webhooks.

---

## Flujo C — Dunning 48h (no churn aún)

```
invoice.payment_failed (u evento equivalente)
  → membresía.status = past_due
  → grace_started_at = now, grace_ends_at = now+48h
  → risk signal abierta (payment_failed)
  → correo #1 (copy: pago declinado; mantener cuenta)
  → URL reintento firmada, expira en 48h
  → cancelled_at NO se toca; churn NO

  Si paga dentro de 48h (invoice.paid / checkout retry OK):
  → status = active, limpia gracia, resuelve risk signal
  → correo opcional de confirmación

  Si clickea el link y no paga:
  → no se extiende la gracia; el reloj sigue

  Job / scheduler a grace_ends_at sin pago OK:
  → soft-cancel API (tokens, commercial cancelled)
  → status = cancelled, cancelled_at = now
  → churn SÍ (entra en clients_lost del periodo)
  → correo #2: cuenta cancelada; enlace PERMANENTE de reactivación
```

### Correos

| # | Trigger | Copy (intención) | Link | Clave plantilla (propuesta) |
|---|---------|------------------|------|------------------------------|
| 1 | Pago declinado | Aviso de decline; urgencia para mantener la cuenta | Reintento **48h** | `membership_payment_failed` |
| 2 | Timeout gracia | Cuenta cancelada; puede reactivar pagando | Reintento **permanente** | `membership_cancelled_reactivate` |

Copywriting editable en **Plantillas de correo** (`dom_mkt_plantillas`), no en commits de vistas PHP nuevas. Variables mínimas: `{{nombre}}`, `{{plan}}`, `{{ciclo}}`, `{{retry_url}}`, `{{grace_hours}}`, `{{cuenta}}` / marca.

### Tokens de link

- **Retry 48h:** un solo uso preferible; invalidar al pagar o al expirar; no renovar por click.
- **Reactivación permanente:** atado a `membresia_id` (o subscription id); rotar si se compromete; al pagar → reactive + nuevo periodo.

---

## Plantillas de correo (`mkt_plantillas`) — diagnóstico y habilitación

### Por qué el CRUD aparece vacío / inútil hoy

| Hecho | Implicación |
|-------|-------------|
| Tabla `dom_mkt_plantillas` y CRUD `mkt_plantillas.json` **existen** (menú “Plantillas correo”). | No falta “crear el módulo”; falta **contenido + consumidores**. |
| Seed en `marketing.sql` inserta **una sola** fila stub `lead_autoresponder` **solo si la tabla está vacía** (`WHERE NOT EXISTS (SELECT 1 FROM dom_mkt_plantillas)`). | Si el install falló a medias, o la fila se borró, o el entorno nunca corrió ese INSERT → listado vacío. |
| `marketing_demo.sql` solo hace `UPDATE` de esa clave (asunto/cuerpo plano). | No siembra el catálogo de membresía / credenciales. |
| Envíos reales usan **`ViewHelper::render('emails/…')`** en PHP: `lead_welcome`, `lead_api_credentials`, `membership_activated`. | El CRUD **no alimenta** el mailer. Editar “Plantillas” no cambia nada en producción. |
| El stub `cuerpo` ni siquiera es el HTML real (apunta al path del archivo PHP). | Doble fuente de verdad ya rota. |

**Conclusión:** habilitar “correctamente” = (1) seed idempotente del catálogo, (2) renderer único por `clave`, (3) migrar consumidores existentes, (4) dunning solo vía claves nuevas.

### Diseño del renderer (Portal)

```
MarketingMailRenderer::send(clave, toEmail, toName, vars[])
  → repo findByClave(clave) WHERE activo=1 AND deleted=0
  → si no hay fila: fallback opcional a vista PHP mapeada (solo durante migración) + log warning
  → sustituir {{var}} en asunto y cuerpo (escape HTML en vars de usuario)
  → MailerInterface::enviar(MensajeCorreo)
```

- **Sin** motor Twig/Blade nuevo: reemplazo `{{clave}}` simple (ya anticipado en el form del CRUD).
- HTML rico: el `cuerpo` puede ser HTML completo (ops edita en textarea); seed inicial puede copiar el markup de las vistas actuales.
- Claves **inmutables** en código; asunto/cuerpo editables. Prohibido borrar claves del sistema o marcar `activo=0` sin fallback documentado (guard CRUD o soft-protect).

### Catálogo mínimo a sembrar (migración `*mkt_plantillas_seed*`)

| Clave | Uso actual / futuro |
|-------|---------------------|
| `lead_welcome` | Autoresponder lead (hoy `emails/lead_welcome`) — alinear o reemplazar stub `lead_autoresponder` |
| `lead_api_credentials` | Credenciales demo post-provision |
| `membership_activated` | Membresía + token (email #3 actual) |
| `membership_payment_failed` | Dunning correo #1 (nuevo) |
| `membership_cancelled_reactivate` | Dunning correo #2 (nuevo) |

Seed: `INSERT … ON DUPLICATE KEY` / `WHERE NOT EXISTS` **por clave**, no “tabla vacía”. Tras seed, el listado CRUD deja de estar vacío en VPS.

Opcional corto plazo: columna `descripcion` o `variables_help` (texto) para documentar `{{vars}}` en el form — reduce errores de ops; no bloqueante.

### Consumidores a migrar (antes o junto con dunning)

1. `AutoresponderHandler` → `lead_welcome` (o renombrar stub → misma clave).
2. `LeadApiProvisioningService` → `lead_api_credentials`.
3. `ActivateMembershipFromOrderService::sendMembershipEmail` → `membership_activated`.
4. Dunning (nuevo) → solo claves `membership_payment_failed` / `membership_cancelled_reactivate`.

Auth framework (`src/…/emails/verificacion|recuperacion`) **fuera** de este catálogo.

---

## Control de deuda técnica

### Evitar

| Anti-patrón | Por qué duele |
|-------------|----------------|
| Nuevas vistas PHP + plantillas BD en paralelo “para después” | N correos × 2 sitios; ops edita el CRUD y no ve cambios. |
| Tabla `dom_mkt_membresias` + reimplementar todo el estado en el lead | Tres verdades (lead / orden / membresía) sin dueño claro. |
| Copiar lógica dunning dentro de `StripeGateway` | Contamina el puerto genérico; bloquea el split. |
| Soft-cancel = deprovision Green | Impide reactivación; contradice activate-plan “same instance”. |
| Churn = demos expiradas | Ensucia KPIs y decisiones de producto. |
| Gracia “flexible” por click | Estados impredecibles; soporte no sabe cuándo baja. |

### Preferir (bajo acoplamiento)

| Práctica | Cómo |
|----------|------|
| Reusar columnas lead ya migradas | `plan_slug`, `converted_at`, `cancelled_at` — no inventar flags nuevos sin necesidad. |
| Un renderer + claves | Todo correo marketing pasa por ahí. |
| Entrega por capas | Conversión lead → plantillas → snapshots → subscriptions → dunning → reactivate. Cada capa shippable. |
| Membresía = estado vivo; orden = cobro | Una fila de suscripción; N órdenes/invoices. |
| Extender webhooks / `pay_events` | No segundo ledger. |
| Api soft-cancel mínimo | Un endpoint (o reuso) documentado en companion; Portal no habla SQL a api. |

### Deuda aceptada a corto plazo (explícita)

- Fallback PHP → plantilla durante la migración de los 3 correos existentes (con log; borrar fallback en el mismo epic cuando los 3 pasen tests).
- Lead `estado` aún `demo_enviada` tras convertir hasta que se añada `convertido` (UI usa `plan_slug`/`converted_at`).
- Transferencia sin subscription automática (ops manual) hasta un follow-up.

---

## Métricas

### Conversión demo → pago

\[
\mathrm{demo\_conversion\_pct} = 100 \times \frac{\mathrm{demos\_converted}}{\mathrm{demos\_started}}
\]

- **demos_started:** leads que entraron a demo en el periodo (`demo_enviada` / provision).
- **demos_converted:** leads con `converted_at` en el periodo (pasaron a plan paid).

Independiente de churn.

### Churn (pagadores)

\[
\mathrm{churn\_rate\_pct} = 100 \times \frac{\mathrm{clients\_lost}}{\mathrm{clients\_start}}
\]

- **clients_start:** membresías/leads convertidos activos al inicio del mes (`converted_at` set, `cancelled_at` null al corte).
- **clients_lost:** pasan a `cancelled_at` en el periodo (dunning timeout u ops).
- **Excluir:** demos que expiran sin comprar; leads en `past_due` dentro de gracia.

### KPIs dashboard (ajuste semántico)

| KPI actual | Debe significar |
|------------|-----------------|
| Demos activas | `demo_enviada` + tenant + `converted_at IS NULL` |
| Por vencer (7d) | demos no convertidas cerca de `demo_expires_at` |
| Conv. demo→pago | `demo_conversion_pct` del snapshot |
| Churn mes ant. | solo pagadores perdidos (`cancelled_at`) |
| En riesgo | señales abiertas incl. `past_due` / payment_failed |

---

## Ownership (framework vs portal)

| Pieza | Lado |
|-------|------|
| Extensiones Stripe Subscription en `StripeGateway` / eventos webhook | Framework (`src/`) si el puerto lo generaliza; parsing de invoice events |
| Membresía, dunning jobs, tokens, lead conversion | Portal (`app/`) |
| `dom_mkt_plantillas` + CRUD + `MarketingMailRenderer` + seeds | Portal (`app/`, `config/cruds`, migraciones `*mkt*`) |
| Soft-cancel / reactivate tenant | Api (WhatsApiLebytek) |
| Snapshots `rep_churn_monthly` | Portal reportes |
| Correos auth (`verificacion` / `recuperacion`) | Framework (`src/`) — no mezclar |

Alineado al split: no meter reglas de membresía Lebytek dentro del puerto genérico.

---

## Secuencia de entrega sugerida

1. **Conversión lead + CRUD** (plan badge, `converted_at`, hide deprovision, demos activas) — valor inmediato sobre el flujo paid ya en prod.
2. **Plantillas de correo:** seed catálogo por clave + `MarketingMailRenderer` + migrar los 3 envíos existentes; verificar listado CRUD no vacío en VPS.
3. **Recálculo / job de snapshots** con definiciones de esta spec (churn paid + conversión).
4. **Subscriptions Stripe** + webhooks invoice.
5. **Dunning 48h** + job timeout + soft-cancel api — correos **solo** vía plantillas `membership_payment_failed` / `membership_cancelled_reactivate`.
6. **Reactivación** con link permanente (misma plantilla #2 / flujo de pago).

No bloquear (1)–(2) por (4)–(6). **Sí** bloquear (5) hasta que (2) esté verde (evitar HTML hardcodeado de dunning).

---

## Riesgos y guardrails

- **Doble fuente de verdad:** membresía vs orden vs lead — la membresía manda el ciclo; el lead refleja conversión/cancelación; la orden es auditoría de cobros.
- **Doble fuente de correo:** plantilla BD vs vista PHP — tolerada solo en la ventana de migración; criterio de done de (2) = consumidores marketing sin `ViewHelper::render('emails/…')` (salvo auth framework).
- **Idempotencia webhooks:** reusar `pay_events` / claim atómico del gateway actual.
- **PHP 8.1 FPM en VPS:** evitar sintaxis 8.2+ en código que corre en lebytek.com.
- **No merge** `feature/backoffice-api-integration` → `main` sin orden explícita.
- Transferencia recurrente: fuera de dunning automático hasta definir proceso manual.
- Ops puede romper HTML en `cuerpo`; mitigar con preview opcional o plantilla `activo=0` + fallback — no bloquear v1.

## Criterios de aceptación (alto nivel)

- [ ] Activate-plan exitoso deja lead con `plan_slug` paid y `converted_at`; sin botón baja demo.
- [ ] Dashboard “demos activas” no cuenta convertidos.
- [ ] Snapshot: conversión usa `converted_at`; churn solo `cancelled_at` de convertidos.
- [ ] CRUD `/admin/crud/mkt_plantillas` lista al menos el catálogo sembrado; editar asunto/cuerpo de una clave afecta el próximo envío.
- [ ] Autoresponder, credenciales demo y membresía activada envían vía renderer + clave (no vistas PHP sueltas).
- [ ] `invoice.payment_failed` → `past_due` + correo #1 desde plantilla; **sin** `cancelled_at`.
- [ ] A las 48h sin pago → soft-cancel + `cancelled_at` + correo #2 desde plantilla + churn.
- [ ] Link permanente reactiva tenant + membresía `active` tras pago OK.

---

## Referencias

- Columnas churn leads: `database/migrations/20260706120000_mkt_leads_churn_columns.sql`
- Snapshots: `database/migrations/20260706120200_rep_churn_metrics.sql`
- Provider UI: `app/Infrastructure/Marketing/MarketingChurnDashboardProvider.php`
- Activación actual: `ActivateMembershipFromOrderService`, CRUD `mkt_ordenes` / `mkt_leads`
- Plantillas: `config/cruds/mkt_plantillas.json`, `dom_mkt_plantillas` en `database/schema/modules/marketing.sql` (seed stub)
- Vistas PHP a migrar: `app/Presentation/Views/emails/{lead_welcome,lead_api_credentials,membership_activated}.php`
- Pagos fase 1: `2026-07-15-payments-gateway-design.md` (“suscripción = fase 2”)
