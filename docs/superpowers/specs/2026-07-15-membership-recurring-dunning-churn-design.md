# Membresía recurrente, dunning 48h, conversión y churn

**Fecha:** 2026-07-15  
**Estado:** Diseño pendiente de revisión humana  
**Repo:** Lebytek_Framework (Portal / lebytek.com) + impacto en WhatsApiLebytek (api)  
**Rama de trabajo sugerida:** `feature/membership-recurring-dunning` (sobre `feature/backoffice-api-integration`)  
**Predecesor:** `docs/superpowers/specs/2026-07-15-payments-gateway-design.md` (pago único Stripe Checkout — **fase 1, ya implementada**)  
**Companion demo/compra:** `docs/superpowers/specs/2026-07-14-manual-membership-purchase-design.md`  
**Companion api activate-plan:** WhatsApiLebytek `docs/superpowers/specs/2026-07-14-plan-activation-and-package-limits-design.md`

## Problema

1. Tras activar un plan paid, el lead sigue mostrando `plan_slug = demo` y el botón **Dar de baja demo** sigue aplicando a clientes ya convertidos.
2. El dashboard de “churn” mezcla embudo demo con retención de pagadores; las columnas `converted_at` / `cancelled_at` y el KPI `demo_conversion_pct` ya existen pero no están cableados al flujo de activación.
3. La pasarela v1 cobra **una sola vez**. El producto comercial es **membresía recurrente**; hace falta renovación, gracia ante fallo de pago, correos de reintento y baja solo después de la gracia — sin marcar churn en el momento del decline.

## Objetivo

1. **Conversión lead:** al primer activate-plan exitoso, actualizar el lead (`plan_slug` paid, `converted_at`) y ocultar **Dar de baja demo**.
2. **Métricas:** conversión = demo → paid; churn = pagadores que llegan a baja comercial (no demos, no `past_due`).
3. **Recurrencia Stripe:** suscripciones (ciclo mensual/anual) encima del puerto de pagos ya abierto.
4. **Dunning 48h:** fallo de pago → correo #1 + link de reintento con vigencia 48h → sin pago → baja + correo #2 con link permanente de reactivación.

## Non-goals (v1 de esta spec)

- Prorrateo / change-plan mid-cycle / trials pagos.
- Multiplicar reintentos Stripe Smart Retries más allá de alinear webhooks (se puede documentar como fase posterior).
- Borrar instancia Green al cancelar por no pago (reactivación debe reusar el mismo tenant).
- Merge a `main` del Framework.
- Rediseñar el puerto `PaymentGatewayInterface` desde cero (extender; no reemplazar).

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

| # | Trigger | Copy (intención) | Link |
|---|---------|------------------|------|
| 1 | Pago declinado | Aviso de decline; urgencia para mantener la cuenta | Reintento **48h** |
| 2 | Timeout gracia | Cuenta cancelada; puede reactivar pagando | Reintento **permanente** de esa membresía |

Copywriting final en implementación (plantillas); el spec fija intención y datos del link.

### Tokens de link

- **Retry 48h:** un solo uso preferible; invalidar al pagar o al expirar; no renovar por click.
- **Reactivación permanente:** atado a `membresia_id` (o subscription id); rotar si se compromete; al pagar → reactive + nuevo periodo.

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
| Membresía, dunning jobs, correos #1/#2, tokens, lead conversion | Portal (`app/`) |
| Soft-cancel / reactivate tenant | Api (WhatsApiLebytek) |
| Snapshots `rep_churn_monthly` | Portal reportes |

Alineado al split: no meter reglas de membresía Lebytek dentro del puerto genérico.

---

## Secuencia de entrega sugerida

1. **Conversión lead + CRUD** (plan badge, `converted_at`, hide deprovision, demos activas) — valor inmediato sobre el flujo paid ya en prod.
2. **Recálculo / job de snapshots** con definiciones de esta spec (aunque recurrencia aún no exista: churn paid empezará a tener sentido con `cancelled_at` ops).
3. **Subscriptions Stripe** + webhooks invoice.
4. **Dunning 48h** + correos + job de timeout + soft-cancel api.
5. **Reactivación** con link permanente.

No bloquear (1) por (3–5).

---

## Riesgos y guardrails

- **Doble fuente de verdad:** membresía vs orden vs lead — la membresía manda el ciclo; el lead refleja conversión/cancelación; la orden es auditoría de cobros.
- **Idempotencia webhooks:** reusar `pay_events` / claim atómico del gateway actual.
- **PHP 8.1 FPM en VPS:** evitar sintaxis 8.2+ en código que corre en lebytek.com.
- **No merge** `feature/backoffice-api-integration` → `main` sin orden explícita.
- Transferencia recurrente: fuera de dunning automático hasta definir proceso manual.

## Criterios de aceptación (alto nivel)

- [ ] Activate-plan exitoso deja lead con `plan_slug` paid y `converted_at`; sin botón baja demo.
- [ ] Dashboard “demos activas” no cuenta convertidos.
- [ ] Snapshot: conversión usa `converted_at`; churn solo `cancelled_at` de convertidos.
- [ ] `invoice.payment_failed` → `past_due` + correo #1; **sin** `cancelled_at`.
- [ ] A las 48h sin pago → soft-cancel + `cancelled_at` + correo #2 + churn.
- [ ] Link permanente reactiva tenant + membresía `active` tras pago OK.

---

## Referencias

- Columnas churn leads: `database/migrations/20260706120000_mkt_leads_churn_columns.sql`
- Snapshots: `database/migrations/20260706120200_rep_churn_metrics.sql`
- Provider UI: `app/Infrastructure/Marketing/MarketingChurnDashboardProvider.php`
- Activación actual: `ActivateMembershipFromOrderService`, CRUD `mkt_ordenes` / `mkt_leads`
- Pagos fase 1: `2026-07-15-payments-gateway-design.md` (“suscripción = fase 2”)
