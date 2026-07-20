# Auditoría técnica diaria — 2026-07-20 (base `main`)

**Repo:** `Parzival2103/Lebytek_Framework`  
**Rama auditada:** `cursor/auditor-a-t-cnica-7968` (base: `main` @ `2c71d3f`)  
**Auditor:** automatización cron (`1cfa9bdd-809a-11f1-ba66-0e7d0216e441`)  
**Último commit en `main`:** 2026-07-14 — Merge PR #5 (`feature/backoffice-api-integration`)  
**Último commit en feature (VPS):** 2026-07-18 — `4789f95` branding logo

---

## Resumen ejecutivo

**Sin delta de negocio en ~24–48 h.** `main` y `origin/feature/backoffice-api-integration` están quietos desde la auditoría del 2026-07-19. El riesgo dominante sigue siendo la **divergencia de lineage** y los criticals ya abiertos (bootstrap/migraciones en `main`; Stripe subscription C1–C6 en feature).

| Lineage | HEAD | Estado vs ayer |
|---------|------|----------------|
| `main` | `2c71d3f` | Sin commits nuevos |
| `feature/backoffice-api-integration` (script VPS) | `4789f95` | Sin commits nuevos (~53 ahead de `main`) |

Drafts de auditoría **aún abiertos / no mergeados:** PR **#12, #14, #17, #18** (bootstrap Stripe/feature) y **#19** (fix WhatsApp + audit `main`). Reaplicar hoy el fix WhatsApp porque #19 sigue draft.

`vertical.modules.marketing` / `payments` OFF por defecto. **Mantener** `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` y `MKT_MEMBERSHIP_AUTHORIZE_ENABLED=false` en VPS.

**Recomendación final:** **crear PR** (fix WhatsApp + este reporte) + **crear issue** (bootstrap/migraciones + CRUD `status`) + **requiere revisión humana** (merge feature→main; criticals Stripe; cerrar drafts duplicados).

---

## Hallazgos críticos

> No auto-fix (salvo URL WhatsApp). Requieren issue / diseño humano.

| # | Hallazgo | Evidencia | Impacto |
|---|----------|-----------|---------|
| C1 | **`main` ≠ branch de deploy VPS** | `scripts/vps-deploy-lebytek-com.sh` L6: `BRANCH=feature/backoffice-api-integration`; `main` sin Stripe/dunning/landing v2 | Confusión ops; “arreglar en main” no llega a prod |
| C2 | **Bootstrap `marketing.sql` incompleto vs código** | Leads sin `api_instance_public_id`, `api_lifecycle_status`, columnas churn (`demo_expires_at`, etc.); `PdoLeadRepository` sí las escribe. **También en feature HEAD** (PR #18 solo alineó Stripe/membresías en órdenes) | Fresh install / reinstall del módulo rompe provision/lifecycle/churn |
| C3 | **Migraciones no registradas en manifiestos (`main`)** | `config/modules/marketing.php` solo 3 archivos; faltan `20260630120000_*`, `20260701160000_*`, `20260701170000_*`, `20260706120000_*`, `20260706120100_*`, `20260714210000_*`; `reportes.php` `migraciones: []` omite `20260706120200_rep_churn_metrics.sql`. Feature sí lista más migraciones | Installer oficial omite columnas; mitigado parcialmente por loop ad-hoc del deploy |
| C4 | *(Lineage feature)* **Stripe subscription C1–C6** | `ConfirmarPagoStripeUseCase` (CheckoutCompleted+subscription = noop; post-claim swallow); `StripeGateway` (invoice metadata / currency≠mxn→amount 0); `RecoverMembershipPaymentService` (new Checkout vs Portal; markActive tras catch vacío) | No activar `PAYMENTS_SUBSCRIPTION_CHECKOUT` |

---

## Hallazgos medios

| # | Hallazgo | Notas |
|---|----------|-------|
| M1 | CRUD `mkt_ordenes` edita `status` (incl. `paid`) y `api_tenant_public_id` con `marketing.editar` | Bypass `autorizar_pago` / activación; también en feature (transitions no cubren el form builtin) |
| M2 | `POST /lead` sin rate limit server-side (sí CSRF) | Spam / abuso captación |
| M3 | Throttle compra solo en sesión | Cookie rotada evita límite |
| M4 | Authorize marca `paid` antes de confirmar entrega del mail de credenciales | Flash de éxito aunque falle el mail |
| M5 | Persistencia Marketing sin `tenant_id`/`empresa_id` | OK single-tenant back-office; no multitenant-ready |
| M6 | `LEBYTEK_API_URL` sin allowlist host/scheme | Misconfig → SSRF / token a host indebido |
| M7 | Deploy VPS destructivo (`find … rm -rf` excepto `.env`) | Puede perder `storage/`, uploads, `install.lock` |
| M8 | Wizard `/install/` si `APP_ENV≠production`; sin `INSTALL_TOKEN` en `.env.example` | Riesgo residual en misconfig |
| M9 | Docs drift: `MAIL_DRIVER` vs `MAIL_MAILER`; README/CLAUDE aún presentan feature como “trabajo actual” | Config incorrecta probable |
| M10 | Capas: Presentation toca DB/API en portal WA | `PortalClienteController`, `WaapiPortalController` |
| M11 | Drafts auditoría duplicados #12/#14/#17/#18/#19 | Ruido; mergear uno y cerrar el resto |
| M12 | Logo público ~379 KB en feature | LCP landing; no rompe funcionalidad |

---

## Mejoras rápidas (bajo riesgo)

1. **Hecho en esta PR:** corregir URL admin en alerta WhatsApp de lead verificado → `/admin/crud/mkt_leads/{id}` (alineado con `PurchaseWhatsAppNotifier` / menú).
2. Mergear/cerrar drafts #12/#14/#17/#18/#19 según política del equipo (bootstrap feature + WhatsApp main).
3. Registrar migraciones faltantes en `marketing.php` / `reportes.php` + alinear bootstrap leads (API lifecycle + churn) — **issue**, no auto en VPS sin checklist.
4. Quitar `status` editable del form CRUD `mkt_ordenes` (solo Autorizar/Activar + transitions).
5. Rate-limit IP en `POST /lead` (preferible `sys_kv`, no solo sesión).
6. Añadir `INSTALL_TOKEN=` a `.env.example`.
7. Optimizar `logo.png` (WebP/SVG o PNG ≤80 KB) en feature.

---

## Riesgos de deploy (VPS)

| Riesgo | Mitigación |
|--------|------------|
| Desplegar `main` esperando Stripe/dunning/landing v2 | No: viven en feature; o plan de merge formal |
| Activar `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` con C1–C6 | **Mantener OFF** |
| Fresh install marketing sin columnas API/churn (main y feature) | No reinstalar módulo hasta alinear bootstrap leads |
| Deploy script borra `storage/` | Backup; recrear lock; no correr a ciegas |
| Migraciones solo vía loop `apply-sql-migration.php` | Verificar columnas en prod; registrar en manifiestos |
| `APP_ENV=local` en prod | Forzar `production` + `INSTALL_TOKEN` |
| Crons dunning/churn (feature) sin crontab | Confirmar `VPS_CHECKLIST` / crontab |

---

## Archivos involucrados

### Estado sin delta reciente
- `main` HEAD `2c71d3f`
- feature HEAD `4789f95` (branding 2026-07-18)

### Críticos / medios
- `database/schema/modules/marketing.sql`
- `config/modules/marketing.php`, `config/modules/reportes.php`
- `database/migrations/202606*.sql`, `202607*.sql`
- `config/cruds/mkt_ordenes.json`
- `app/Infrastructure/Marketing/PdoLeadRepository.php`
- `scripts/vps-deploy-lebytek-com.sh`
- Feature: `ConfirmarPagoStripeUseCase.php`, `StripeGateway.php`, `RecoverMembershipPaymentService.php`

### Fix bajo riesgo (esta PR)
- `app/Infrastructure/Marketing/LeadVerifiedWhatsAppNotifier.php`
- `tests/Marketing/LeadVerifiedWhatsAppNotifierTest.php`
- `docs/audits/2026-07-20-auditoria-tecnica-main.md`

---

## Tests / verificación

| Suite | Resultado |
|-------|-----------|
| Entorno agent | PHP 8.3 + Composer instalados ad-hoc; `.env` ausente |
| `LeadVerifiedWhatsAppNotifierTest` | **3/3 PASS** |
| `php tests/run.php Marketing` | **114 passed, 0 failed** |
| Lint | No hay linter CI verificado aquí |

**No verificado:** deploy VPS real, BD de producción, webhooks Stripe live, secretos `.env`.

---

## Recomendación final

| Acción | Motivo |
|--------|--------|
| **crear PR** | Fix URL WhatsApp + reporte 2026-07-20 |
| **crear issue** | C2/C3 bootstrap+migraciones; M1 CRUD `status`; rate-limit leads; cerrar drafts duplicados |
| **requiere revisión humana** | Política merge feature→main; criticals Stripe C1–C6; branch hardcodeada en deploy |
| ~~sin acción~~ | No aplica |

**Prioridad ops:** no reinstalar marketing desde bootstrap actual; no activar subscription checkout; mergear un solo PR de bootstrap feature y el fix WhatsApp de `main`; alinear fuente de verdad del VPS.
