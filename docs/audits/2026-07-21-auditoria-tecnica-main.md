# Auditoría técnica diaria — 2026-07-21 (base `main`)

**Repo:** `Parzival2103/Lebytek_Framework`  
**Rama auditada:** `cursor/auditor-a-t-cnica-a0da` (base: `main` @ `2c71d3f`)  
**Auditor:** automatización cron (`1cfa9bdd-809a-11f1-ba66-0e7d0216e441`)  
**Último commit en `main`:** 2026-07-14 — Merge PR #5 (`feature/backoffice-api-integration`)  
**Último commit en feature (VPS):** 2026-07-18 — `4789f95` branding logo  

---

## Resumen ejecutivo

**Sin delta de negocio en ~7 días.** `main` y `origin/feature/backoffice-api-integration` permanecen quietos desde auditorías previas (19–20 jul). El riesgo dominante sigue siendo la **divergencia de lineage** (`main` ≠ deploy VPS) y criticals abiertos no mergeados.

| Lineage | HEAD | Estado vs ayer (2026-07-20) |
|---------|------|------------------------------|
| `main` | `2c71d3f` | Sin commits nuevos |
| `feature/backoffice-api-integration` (script VPS) | `4789f95` | Sin commits nuevos (~53 ahead / 1 behind `main`) |

Drafts de auditoría **aún abiertos:** #12, #14, #17, #18, #19, #20, #22. Issues abiertos: **#21** (Stripe C1–C6), **#23** (bootstrap leads + migraciones `main`). Reaplicar hoy el fix WhatsApp en `main` porque #19/#20 siguen draft.

`vertical.modules.marketing` OFF por defecto en esta base. En VPS (feature): **mantener** `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` y `MKT_MEMBERSHIP_AUTHORIZE_ENABLED=false`.

**Recomendación final:** **crear PR** (fix WhatsApp + `INSTALL_TOKEN` en `.env.example` + este reporte) + **requiere revisión humana** (consolidar/cerrar drafts; merge feature→main; criticals Stripe #21; bootstrap leads).

---

## Hallazgos críticos

> No auto-fix (salvo URL WhatsApp / docs menores). Requieren issue / diseño humano.

| # | Hallazgo | Evidencia | Impacto |
|---|----------|-----------|---------|
| C1 | **`main` ≠ branch de deploy VPS** | `scripts/vps-deploy-lebytek-com.sh` L6: `BRANCH=feature/backoffice-api-integration`; `main` sin Stripe/dunning/landing experiments | Confusión ops; “arreglar en main” no llega a prod |
| C2 | **Bootstrap `marketing.sql` incompleto vs código (main y feature)** | Leads sin `api_instance_public_id`, `api_lifecycle_status`, columnas churn; `PdoLeadRepository` sí las escribe. Feature HEAD tampoco las tiene en bootstrap (PR #22 solo alineó Stripe/membresías en órdenes, draft) | Fresh install / reinstall del módulo rompe provision/lifecycle/churn |
| C3 | **Migraciones no registradas en manifiestos (`main`)** | `config/modules/marketing.php` solo lista 3 archivos Jul; faltan `20260630120000_*`, `20260630180000_*`, `20260701160000_*`, `20260701170000_*`, `20260706120000_*`, `20260706120100_*`, `20260714210000_*`. `reportes.php` `migraciones: []` omite `20260706120200_rep_churn_metrics.sql`. **Feature sí lista** esas migraciones | Installer oficial en `main` omite columnas |
| C4 | *(Lineage feature)* **Stripe subscription — issue #21** | `ConfirmarPagoStripeUseCase` (post-claim swallow; CheckoutCompleted+subscription noop); `StripeGateway` (currency≠mxn→amount 0); recover Checkout vs Portal | No activar `PAYMENTS_SUBSCRIPTION_CHECKOUT` |

---

## Hallazgos medios

| # | Hallazgo | Notas |
|---|----------|-------|
| M1 | CRUD `mkt_ordenes` edita `status` (incl. `paid`) y `api_tenant_public_id` con permiso de edición | Bypass `autorizar` / activación; también en feature |
| M2 | `POST /lead` sin rate limit server-side (sí CSRF) | Spam / abuso captación |
| M3 | Throttle compra solo en sesión (`compra_posts`) | Cookie rotada evita límite |
| M4 | Persistencia Marketing sin `tenant_id`/`empresa_id` | OK single-tenant back-office; no multitenant-ready |
| M5 | Deploy VPS destructivo (`rm` agresivo excepto `.env`) | Puede perder `storage/`, uploads, locks |
| M6 | Wizard `/install/` si `APP_ENV≠production`; `INSTALL_TOKEN` no estaba documentado en `.env.example` | Mitigado parcialmente en esta PR (doc en example) |
| M7 | Docs drift: `MAIL_DRIVER` vs `MAIL_MAILER`; README/CLAUDE presentan feature como trabajo actual | Config incorrecta probable |
| M8 | Capas: Presentation toca integraciones en portal WA | Deuda arquitectónica conocida |
| M9 | **7+ drafts de auditoría duplicados** (#12–#22) | Ruido; mergear uno canónico y cerrar el resto |
| M10 | Logo público ~379 KB en feature | LCP landing |

---

## Mejoras rápidas (bajo riesgo)

1. **Hecho en esta PR:** URL admin WhatsApp lead verificado → `/admin/crud/mkt_leads/{id}`.
2. **Hecho en esta PR:** documentar `INSTALL_TOKEN=` en `.env.example`.
3. Mergear/cerrar drafts #12/#14/#17/#18/#19/#20/#22 según política del equipo.
4. Registrar migraciones faltantes en `marketing.php` / `reportes.php` en `main` + alinear bootstrap leads (API lifecycle + churn) — **issue**, no auto en VPS sin checklist.
5. Quitar `status` editable del form CRUD `mkt_ordenes`.
6. Rate-limit IP en `POST /lead` (preferible `sys_kv`).
7. Optimizar `logo.png` en feature.

---

## Riesgos de deploy (VPS)

| Riesgo | Mitigación |
|--------|------------|
| Desplegar `main` esperando Stripe/dunning | No: viven en feature; plan de merge formal |
| Activar `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` (#21) | **Mantener OFF** |
| Fresh install marketing sin columnas API/churn | No reinstalar módulo hasta alinear bootstrap leads |
| Deploy script borra `storage/` | Backup; recrear lock |
| `APP_ENV=local` en prod | Forzar `production` + `INSTALL_TOKEN` si wizard accesible |
| Crons dunning/churn (feature) | Confirmar crontab / `VPS_CHECKLIST` |

---

## Archivos involucrados

### Estado sin delta reciente
- `main` HEAD `2c71d3f`
- feature HEAD `4789f95` (branding 2026-07-18)

### Críticos / medios (sin auto-fix)
- `database/schema/modules/marketing.sql`
- `config/modules/marketing.php`, `config/modules/reportes.php`
- `config/cruds/mkt_ordenes.json`
- `app/Infrastructure/Marketing/PdoLeadRepository.php`
- `scripts/vps-deploy-lebytek-com.sh`
- Feature: `ConfirmarPagoStripeUseCase.php`, `StripeGateway.php`, `RecoverMembershipPaymentService.php`
- Issue #21

### Fix bajo riesgo (esta PR)
- `app/Infrastructure/Marketing/LeadVerifiedWhatsAppNotifier.php`
- `tests/Marketing/LeadVerifiedWhatsAppNotifierTest.php`
- `.env.example`
- `docs/audits/2026-07-21-auditoria-tecnica-main.md`

---

## Tests / verificación

| Suite | Resultado |
|-------|-----------|
| Entorno agent | PHP 8.3 + Composer ad-hoc; `.env` ausente |
| `LeadVerifiedWhatsAppNotifierTest` | **3/3 PASS** |
| `php tests/run.php Marketing` | **114 passed, 0 failed** |
| Lint | No hay linter CI verificado aquí |

**No verificado:** deploy VPS real, BD de producción, webhooks Stripe live, secretos `.env`.

---

## Recomendación final

**crear PR** + **requiere revisión humana** (consolidar drafts; no mergear criticals bootstrap/Stripe sin checklist).
