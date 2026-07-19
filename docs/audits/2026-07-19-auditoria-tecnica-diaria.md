# Auditoría técnica diaria — 2026-07-19

**Repo:** `Parzival2103/Lebytek_Framework`  
**Rama auditada:** `cursor/auditor-a-t-cnica-diaria-5813` (base: lineage `feature/backoffice-api-integration` + payments/dunning)  
**HEAD pre-fix:** `4789f95` (`chore(branding): refresh site logo asset`)  
**Auditor:** automatización diaria (cron 14:00 UTC)

---

## Resumen ejecutivo

Delta desde la auditoría 2026-07-18 (`5b6e880`): **solo branding** — dos commits de `public/assets/images/logo.png` (pico ~2.0 MB luego reducido a ~379 KB). **No hay lógica nueva de negocio.**

Los **5 criticals de payments/subscription** siguen abiertos en código y **no deben auto-fijarse**. El fix bajo riesgo de bootstrap Stripe/membresías (**PR #17**, aún draft/no mergeado) se **reaplicó** en esta rama vía cherry-pick `f755865` → `ba46854`. PRs duplicados **#12/#14/#17** deben cerrarse al mergear este o #17.

`vertical.modules.marketing` y `payments` siguen **OFF** por defecto. Mantener `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` y `MKT_MEMBERSHIP_AUTHORIZE_ENABLED=false` en VPS.

**Recomendación final:** crear PR (bootstrap + este reporte) + **crear issue / requiere revisión humana** para criticals de subscription activation.

---

## Hallazgos críticos

> No auto-fix. Requieren issue + diseño/implementación humana.

| # | Hallazgo | Evidencia | Impacto |
|---|----------|-----------|---------|
| C1 | **Subscription first-activation gap**: `CheckoutCompleted` con `subscriptionId` es no-op | `ConfirmarPagoStripeUseCase` L81–84 | Orden Stripe subscription puede quedar forever `pending_payment` |
| C2 | **invoice.paid** depende de `metadata.order_public_id` en Invoice; Stripe **no copia** metadata de subscription → Invoice | `StripeGateway::extractExternalRef` + `resolveOrder`/`resolveMembresia` | Misma orden no se activa; membresía no se bindea |
| C3 | **Retry/reactivate crea NEW Checkout subscription** (`external_ref=membresia-{tenant}`) en lugar de Billing Portal | `RecoverMembershipPaymentService::checkoutUrlForMembresia` | Desync plan vs implementación; webhooks pueden no resolver orden |
| C4 | **post-claim swallow**: `catch (\Throwable)` + log; HTTP 200 | `ConfirmarPagoStripeUseCase::ejecutar` L54–61 | Stripe no reintenta; activación fallida queda silenciosa |
| C5 | **recover cancelled marca `active` local** aunque `reactivateCommercial` falle (catch vacío) | `RecoverMembershipPaymentService` L42–54 | Desync api vs back-office |
| C6 | **Amount check bypass**: currency ≠ `mxn` → amount 0; Confirmar solo valida si `amountMinor()>0` | `StripeGateway` L126–130 + Confirmar L158 | Pago en otra moneda podría activar sin check de monto |

---

## Hallazgos medios

| # | Hallazgo | Notas |
|---|----------|-------|
| M1 | Bootstrap `marketing.sql` sin columnas Stripe / `dom_mkt_membresias` | **Corregido en esta PR** (cherry-pick #17). Fresh install roto hasta merge. |
| M2 | CRUD `mkt_ordenes` form edita `status` libremente (incl. `paid`) | `config/cruds/mkt_ordenes.json` form.fields; transitions del CRUD no gatean el edit builtin |
| M3 | Email→tenant auto-link en `CrearOrdenMembresiaUseCase` | Riesgo de asociar tenant equivocado por email |
| M4 | DI latente: recover/pago requieren `PaymentGatewayRegistry` si marketing ON y payments OFF | Lazy OK hasta hit `/membresia/*` |
| M5 | `POST /lead` sin rate limit (sí CSRF) | `routes/marketing.php` |
| M6 | Timestamps migración duplicados `20260715120000_*` | No renombrar sin plan ops |
| M7 | Deploy scripts hardcodean `feature/backoffice-api-integration`; `main` está **muy detrás** del lineage feature | `scripts/vps-deploy-lebytek-com.sh`; `VPS_CHECKLIST` aún cita esa branch |
| M8 | Logo público ~379 KB (882×799 PNG) tras refresh | Mejor que el pico 2 MB del commit intermedio; aún pesado para LCP landing |
| M9 | PRs auditoría abiertos duplicados: **#12, #14, #17** | Cerrar al mergear el fix de bootstrap |
| M10 | Deuda payments: cola async webhook, purge `pay_events`, TTL `pending_payment`, renewal order rows | Spec/plan existentes; no bloquea deploy si flags OFF |

---

## Mejoras rápidas (bajo riesgo)

1. **Mergear bootstrap** (esta PR / #17) y cerrar #12/#14/#17.
2. Optimizar `logo.png` (WebP/SVG o PNG ≤80 KB) sin cambiar layout.
3. Quitar `status` editable del form CRUD `mkt_ordenes` (solo transitions + acciones Autorizar/Activar).
4. Actualizar `VPS_CHECKLIST` / deploy scripts al branch real de producción cuando se defina (hoy el trabajo vive fuera de `main`).
5. Rate-limit ligero en `POST /lead` (mismo patrón sys_kv que compra).

---

## Riesgos de deploy (VPS)

| Riesgo | Mitigación |
|--------|------------|
| Activar `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` con C1–C6 abiertos | **No activar** hasta issue + fix |
| Fresh install / reinstall sin bootstrap mergeado | Mergear esta PR antes de installer limpio |
| `main` ≠ lineage feature (payments/dunning/branding) | No desplegar desde `main` esperando Stripe/membresías |
| Deploy script branch hardcodeada | Verificar branch en `vps-deploy-lebytek-com.sh` antes de pull |
| Crons dunning/churn sin instalar | Checklist actualizado; confirmar crontab |
| Logo 379 KB en landing | Impacto LCP; no rompe funcionalidad |
| Módulos marketing/payments OFF en skeleton | OK para skeleton; Portal/VPS deben encender explícitamente |

---

## Archivos involucrados

### Delta 24–48h (pre-auditoría)
- `public/assets/images/logo.png` — `e957be9`, `4789f95`

### Criticals (sin cambio)
- `app/Application/Marketing/ConfirmarPagoStripeUseCase.php`
- `app/Application/Marketing/RecoverMembershipPaymentService.php`
- `src/Infrastructure/Payments/StripeGateway.php`
- `app/Presentation/Controllers/Publico/MembresiaPagoController.php`

### Fix bajo riesgo (esta PR)
- `database/schema/modules/marketing.sql`
- `docs/integration/VPS_CHECKLIST.md`
- `tests/Marketing/SchemaBootstrapTest.php`
- `docs/audits/2026-07-19-auditoria-tecnica-diaria.md`

### Referencia ops / flags
- `config/vertical.php`, `config/payments.php`, `.env.example`
- `config/cruds/mkt_ordenes.json`
- `routes/marketing.php`
- `scripts/vps-deploy-lebytek-com.sh`, `scripts/expire-membership-grace.php`

---

## Tests / verificación

| Suite | Resultado |
|-------|-----------|
| Entorno agent | PHP 8.3 + Composer instalados ad-hoc; `.env` ausente |
| `php tests/run.php Marketing` (SchemaBootstrap + resto) | *ver log del agente en la misma sesión* |
| Lint | No hay linter CI dedicado verificado en este entorno |

**No verificado aquí:** deploy real VPS, webhooks Stripe live, crons en producción, secretos `.env`.

---

## Recomendación final

| Acción | Motivo |
|--------|--------|
| **crear PR** | Bootstrap schema + checklist + reporte diario (bajo riesgo, verificable) |
| **crear issue** | Criticals C1–C6 subscription/dunning (no auto-fix) |
| **requiere revisión humana** | Estrategia Billing Portal vs new Checkout; política amount/currency; merge a `main` vs follow feature branch en VPS |
| ~~sin acción~~ | No aplica — hay fix pendiente de merge y criticals abiertos |

**Prioridad ops inmediata:** mergear bootstrap; **no** habilitar subscription checkout en VPS.
