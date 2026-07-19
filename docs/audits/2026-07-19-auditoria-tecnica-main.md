# Auditoría técnica diaria — 2026-07-19 (base `main`)

**Repo:** `Parzival2103/Lebytek_Framework`  
**Rama auditada:** `cursor/auditor-a-t-cnica-06a6` (base: `main` @ `2c71d3f`)  
**Auditor:** automatización cron (`1cfa9bdd-809a-11f1-ba66-0e7d0216e441`)  
**Último commit en `main`:** 2026-07-14 — Merge PR #5 (`feature/backoffice-api-integration`)

---

## Resumen ejecutivo

No hay commits nuevos en `main` en las últimas ~24–120 h (HEAD estable desde 2026-07-14). El riesgo principal **no es el delta diario**, sino la **divergencia de lineage**:

| Lineage | HEAD | Contenido |
|---------|------|-----------|
| `main` (esta auditoría) | `2c71d3f` | Marketing transfer/authorize, email verify, API leads |
| `feature/backoffice-api-integration` (VPS script) | `4789f95` | + ~40 commits: Stripe/payments, dunning, landing experiments, branding |

El script `scripts/vps-deploy-lebytek-com.sh` clona **`feature/backoffice-api-integration`**, no `main`. Desplegar o auditar solo `main` **no refleja producción**.

Auditoría paralela del lineage feature (payments/dunning): PR draft **#18** (`docs/audits/2026-07-19-auditoria-tecnica-diaria.md`) — criticals C1–C6 de subscription **siguen abiertos** y no deben auto-fijarse.

En `main`, el hallazgo más urgente es **bootstrap/schema incompleto** vs código que ya escribe columnas API/churn, más el bypass CRUD de órdenes.

**Recomendación final:** crear PR (fix menor WhatsApp + este reporte) + **crear issue** (migraciones/bootstrap + CRUD órdenes) + **requiere revisión humana** (estrategia `main` vs feature en VPS; criticals Stripe del lineage feature).

---

## Hallazgos críticos

> No auto-fix (excepto el enlace WhatsApp, bajo riesgo). Requieren issue / diseño humano.

| # | Hallazgo | Evidencia | Impacto |
|---|----------|-----------|---------|
| C1 | **`main` ≠ branch de deploy VPS** | `scripts/vps-deploy-lebytek-com.sh` L6: `BRANCH=feature/backoffice-api-integration`; `main` no contiene Stripe/dunning | Confusión ops; checklist/`main` desactualizados; riesgo de “arreglar en main” y no desplegar |
| C2 | **Bootstrap `marketing.sql` incompleto vs código** | Schema leads sin `api_instance_public_id`, `api_lifecycle_status`, columnas churn (`demo_expires_at`, etc.); `PdoLeadRepository` sí las escribe | Fresh install / reinstall del módulo rompe provision/lifecycle/churn |
| C3 | **Migraciones marketing/reportes no registradas en manifiestos** | `config/modules/marketing.php` solo lista 3 archivos; faltan p.ej. `20260630120000_*`, `20260701160000_*`, `20260701170000_*`, `20260706120000_*`, `20260706120100_*`, `20260714210000_*`; `reportes.php` `migraciones: []` omite `20260706120200_rep_churn_metrics.sql` | `Installer` solo aplica lo listado; upgrades “oficiales” omiten columnas usadas en código (mitigado parcialmente por loop ad-hoc del deploy script) |
| C4 | *(Lineage feature, no en `main`)* Criticals Stripe C1–C6 | Documentados en PR #18 / auditoría feature del mismo día | No activar `PAYMENTS_SUBSCRIPTION_CHECKOUT` en VPS |

---

## Hallazgos medios

| # | Hallazgo | Notas |
|---|----------|-------|
| M1 | CRUD `mkt_ordenes` permite editar `status` → `paid` y `api_tenant_public_id` con `marketing.editar` | `config/cruds/mkt_ordenes.json`; bypassa flujo `autorizar_pago` (`marketing.ordenes`) y activación API |
| M2 | `POST /lead` sin rate limit server-side (sí CSRF) | `LeadController`; abuso de captación / spam email |
| M3 | Throttle de compra solo en sesión | `CompraController`; rotar cookie evita límite |
| M4 | Authorize marca `paid` antes de confirmar entrega del mail de credenciales | `AutorizarOrdenMembresiaUseCase`; fallo de mail se traga con flash de éxito |
| M5 | Persistencia Marketing sin `tenant_id`/`empresa_id` | Aceptable solo single-tenant back-office; no multitenant-ready |
| M6 | `LEBYTEK_API_URL` sin allowlist de host/scheme | Misconfig → SSRF / token a host indebido |
| M7 | Deploy VPS destructivo | `find … rm -rf` borra todo excepto `.env` (puede perder `storage/install.lock`, uploads) |
| M8 | Wizard `/install/` si `APP_ENV≠production` | Sin `INSTALL_TOKEN` en `.env.example` |
| M9 | Docs drift: `MAIL_DRIVER` vs `MAIL_MAILER`; README/CLAUDE aún hablan de feature branch como “trabajo actual” tras merge parcial a `main` | Riesgo de configuración incorrecta |
| M10 | Capas: Presentation toca DB/API en portal WA | `PortalClienteController`, `WaapiPortalController` |

---

## Mejoras rápidas (bajo riesgo)

1. **Hecho en esta PR:** corregir URL admin en alerta WhatsApp de lead verificado (`/admin/crud/mkt_leads/{id}`).
2. Registrar migraciones faltantes en `config/modules/marketing.php` y `reportes.php` + alinear `marketing.sql` bootstrap (issue dedicado; no auto-merge sin checklist VPS).
3. Quitar `status` editable del form CRUD `mkt_ordenes` (solo acción Autorizar + transitions).
4. Actualizar branch del deploy script / checklist al lineage real cuando se estabilice merge a `main`.
5. Rate-limit IP en `POST /lead` (patrón similar a compra, preferible `sys_kv` no solo sesión).
6. Añadir `INSTALL_TOKEN=` a `.env.example` y documentar obligación en producción.
7. Mergear/cerrar drafts de auditoría duplicados del lineage feature (#12/#14/#17/#18) según política del equipo.

---

## Riesgos de deploy (VPS)

| Riesgo | Mitigación |
|--------|------------|
| Desplegar `main` esperando Stripe/membresías recurrentes | No: viven solo en feature; o mergear feature→main con plan |
| Deploy script borra `storage/` / `install.lock` | Backup storage; recrear lock; no correr a ciegas |
| Migraciones solo vía loop `apply-sql-migration.php` (sin `cfg_migraciones`) | Registrar en manifiestos; verificar columnas en prod |
| Fresh install marketing sin columnas API/churn | No reinstalar módulo hasta alinear bootstrap |
| `APP_ENV=local` en prod | Forzar `production` + `INSTALL_TOKEN` |
| Activar subscription checkout (feature) | Mantener OFF hasta issues C1–C6 |

---

## Archivos involucrados

### Estado `main` (sin delta reciente de negocio)
- HEAD `2c71d3f` — Merge PR #5 backoffice-api-integration

### Críticos / medios
- `database/schema/modules/marketing.sql`
- `config/modules/marketing.php`, `config/modules/reportes.php`
- `database/migrations/202606*.sql`, `202607*.sql` (no registradas)
- `config/cruds/mkt_ordenes.json`
- `app/Infrastructure/Marketing/PdoLeadRepository.php`
- `app/Application/Marketing/AutorizarOrdenMembresiaUseCase.php`
- `app/Presentation/Controllers/Publico/LeadController.php`
- `scripts/vps-deploy-lebytek-com.sh`
- `public/install/index.php`, `.env.example`

### Fix bajo riesgo (esta PR)
- `app/Infrastructure/Marketing/LeadVerifiedWhatsAppNotifier.php`
- `tests/Marketing/LeadVerifiedWhatsAppNotifierTest.php`
- `docs/audits/2026-07-19-auditoria-tecnica-main.md`

### Referencia lineage feature (fuera de este HEAD)
- PR #18 + `ConfirmarPagoStripeUseCase`, `StripeGateway`, `RecoverMembershipPaymentService`

---

## Tests / verificación

| Suite | Resultado |
|-------|-----------|
| Entorno agent | **Sin PHP/Composer/vendor** en este run — no se pudieron ejecutar tests |
| Cambio WhatsApp | Verificación estática + assert de test actualizado |
| Lint | No hay linter CI verificado aquí |

**No verificado:** deploy VPS real, BD de producción, webhooks Stripe (feature), secretos `.env`.

---

## Recomendación final

| Acción | Motivo |
|--------|--------|
| **crear PR** | Fix URL WhatsApp + reporte de auditoría sobre `main` |
| **crear issue** | C2/C3 bootstrap+migraciones; M1 CRUD `status`; rate-limit leads |
| **requiere revisión humana** | Política merge `feature`→`main`; criticals Stripe (PR #18); branch hardcodeada en deploy |
| ~~sin acción~~ | No aplica |

**Prioridad ops:** no reinstalar marketing desde bootstrap actual de `main`; no activar subscription checkout en feature; alinear qué branch es “fuente de verdad” del VPS.
