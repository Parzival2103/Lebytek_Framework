# Auditoría técnica diaria — 2026-07-20

**Repo:** `Parzival2103/Lebytek_Framework`  
**Rama auditada:** `cursor/auditor-a-t-cnica-diaria-6513` (base: `feature/backoffice-api-integration` @ `4789f95`)  
**HEAD pre-fix:** `4789f95` (`chore(branding): refresh site logo asset`)  
**Auditor:** automatización diaria (cron 14:00 UTC)

---

## Resumen ejecutivo

Sin commits de lógica de negocio desde la auditoría 2026-07-19: el lineage feature sigue en branding (`logo.png` ~379 KB). Los **6 criticals de payments/subscription** se **reconfirmaron en código** y permanecen abiertos (no auto-fix).

Fix bajo riesgo aplicado en esta corrida:

1. **Bootstrap** `marketing.sql`: columnas Stripe + `dom_mkt_membresias` (reaplicación; drafts #12/#14/#17/#18 aún no mergeados).
2. **Deep-link WhatsApp** lead verificado: `/crud/mkt_leads/{id}` → `/admin/crud/mkt_leads/{id}` (alineado con `PurchaseWhatsAppNotifier`; también en draft #20 sobre `main`).
3. Checklist VPS payments/dunning + reporte diario.

`vertical.modules.marketing` y `payments` siguen **OFF** por defecto. Mantener `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` en VPS.

**Recomendación final:** **crear PR** (fixes bajo riesgo + reporte) + **crear issue** / **requiere revisión humana** para criticals C1–C6.

---

## Hallazgos críticos

> No auto-fix. Requieren issue + diseño/implementación humana.

| # | Hallazgo | Evidencia | Impacto |
|---|----------|-----------|---------|
| C1 | **Subscription first-activation gap**: `CheckoutCompleted` con `subscriptionId` es no-op | `ConfirmarPagoStripeUseCase` L81–84 | Orden subscription puede quedar forever `pending_payment` |
| C2 | **invoice.paid** resuelve orden vía `metadata.order_public_id`; Stripe **no copia** metadata subscription→invoice | `StripeGateway::extractExternalRef` + `resolveOrder` | Misma orden no se activa; membresía no se bindea en first pay |
| C3 | **Retry/reactivate crea NEW Checkout subscription** (`external_ref=membresia-{tenant}`) vs Billing Portal del plan | `RecoverMembershipPaymentService::checkoutUrlForMembresia` | Desync plan/impl; webhooks pueden no resolver orden |
| C4 | **post-claim swallow**: `catch (\Throwable)` + log; HTTP 200 | `ConfirmarPagoStripeUseCase::ejecutar` L54–61 | Stripe no reintenta; activación fallida silenciosa |
| C5 | **recover cancelled marca `active` local** aunque `reactivateCommercial` falle (catch vacío) | `RecoverMembershipPaymentService` L42–54 | Desync api vs back-office |
| C6 | **Amount check bypass**: currency ≠ `mxn` → amount 0; Confirmar solo valida si `amountMinor()>0` | `StripeGateway` L126–130 + Confirmar L158 | Pago otra moneda podría activar sin check de monto |

---

## Hallazgos medios

| # | Hallazgo | Notas |
|---|----------|-------|
| M1 | Bootstrap `marketing.sql` sin Stripe/`dom_mkt_membresias` en HEAD base | **Corregido en esta PR** (reaplicación #18). Fresh install roto hasta merge. |
| M2 | Deep-link WA lead verificado sin `/admin` | **Corregido en esta PR**; draft #20 hace lo mismo sobre `main` |
| M3 | CRUD `mkt_ordenes` form edita `status` libremente (incl. `paid`) | `config/cruds/mkt_ordenes.json` |
| M4 | Email→tenant auto-link en `CrearOrdenMembresiaUseCase` | Riesgo tenant equivocado por email |
| M5 | DI latente: recover/pago exigen `PaymentGatewayRegistry` si marketing ON y payments OFF | Lazy OK hasta hit `/membresia/*` |
| M6 | `POST /lead` sin rate limit (sí CSRF) | `routes/marketing.php` |
| M7 | Timestamps migración duplicados `20260715120000_*` | No renombrar sin plan ops |
| M8 | Deploy scripts / checklist hardcodean `feature/backoffice-api-integration`; `main` << lineage feature | No desplegar Stripe/membresías desde `main` |
| M9 | Logo público ~379 KB | LCP landing; no funcional |
| M10 | PRs auditoría duplicados abiertos: **#12, #14, #17, #18** (+ #19/#20 sobre `main`) | Cerrar al mergear bootstrap; unificar WA fix feature↔main |
| M11 | Deuda payments: cola async webhook, purge `pay_events`, TTL `pending_payment`, renewal rows | Spec/plan; no bloquea si flags OFF |

---

## Mejoras rápidas (bajo riesgo)

1. Mergear esta PR (o #18) y cerrar drafts bootstrap duplicados #12/#14/#17/#18.
2. Coordinar WA URL: merge feature y/o #20 (`main`) para no divergir.
3. Quitar `status` editable del form CRUD `mkt_ordenes` (solo transitions + Autorizar/Activar).
4. Optimizar `logo.png` (WebP/SVG o PNG ≤80 KB).
5. Rate-limit ligero en `POST /lead`.
6. Crear **un** issue GitHub agrupando C1–C6 (no auto-fix).

---

## Riesgos de deploy (VPS)

| Riesgo | Mitigación |
|--------|------------|
| Activar `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` con C1–C6 abiertos | **No activar** hasta issue + fix |
| Fresh install sin bootstrap mergeado | Mergear esta PR antes de installer limpio |
| `main` ≠ lineage feature | No desplegar desde `main` esperando Stripe/membresías |
| Deploy script branch hardcodeada | Verificar `scripts/vps-deploy-lebytek-com.sh` |
| Crons dunning/churn sin instalar | Checklist actualizado; confirmar crontab |
| Alertas WA con link roto (pre-fix) | Mergear deep-link `/admin/crud/...` |
| Módulos marketing/payments OFF en skeleton | OK; Portal/VPS deben encender explícitamente |

---

## Archivos involucrados

### Delta 24–48h (pre-auditoría)

- `public/assets/images/logo.png` — sin cambios nuevos vs 2026-07-19 (`4789f95`)

### Criticals (sin cambio)

- `app/Application/Marketing/ConfirmarPagoStripeUseCase.php`
- `app/Application/Marketing/RecoverMembershipPaymentService.php`
- `src/Infrastructure/Payments/StripeGateway.php`
- `app/Presentation/Controllers/Publico/MembresiaPagoController.php`

### Fix bajo riesgo (esta PR)

- `database/schema/modules/marketing.sql`
- `docs/integration/VPS_CHECKLIST.md`
- `tests/Marketing/SchemaBootstrapTest.php`
- `app/Infrastructure/Marketing/LeadVerifiedWhatsAppNotifier.php`
- `tests/Marketing/LeadVerifiedWhatsAppNotifierTest.php`
- `docs/audits/2026-07-20-auditoria-tecnica-diaria.md`

### Referencia ops / flags

- `config/vertical.php`, `config/payments.php`, `.env.example`
- `config/cruds/mkt_ordenes.json`
- `routes/marketing.php`
- `scripts/vps-deploy-lebytek-com.sh`, `scripts/expire-membership-grace.php`

---

## Tests / verificación

| Suite | Resultado |
|-------|-----------|
| Entorno agent | PHP 8.3.6 + Composer; `.env` ausente |
| `LeadVerifiedWhatsAppNotifierTest` | **3/3 PASS** |
| `SchemaBootstrap` (incl. Stripe columns) | **12/12 PASS** |
| `php tests/run.php Marketing` | **252 passed, 2 failed** (MySQL connection refused — entorno, no regresión del fix) |
| `php tests/run.php Payments` | **20/20 PASS** |
| Lint | No hay linter CI dedicado verificado aquí |

**No verificado aquí:** deploy real VPS, webhooks Stripe live, crons en producción, secretos `.env`.

---

## Recomendación final

| Acción | Motivo |
|--------|--------|
| **crear PR** | Bootstrap + WA admin URL + checklist + reporte (bajo riesgo, verificable) |
| **crear issue** | Criticals C1–C6 subscription/dunning (no auto-fix) |
| **requiere revisión humana** | Billing Portal vs new Checkout; amount/currency; merge feature→main / branch VPS |
| ~~sin acción~~ | No aplica |

**Prioridad ops inmediata:** mergear bootstrap + WA URL; **no** habilitar subscription checkout en VPS; cerrar drafts auditoría duplicados.
