# Inventario de deuda técnica — 2026-08-10

**Modo:** degradado — sin spec del día  
**Repo:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Rama:** `automation/spec-2026-08-10`  
**Estado:** artefacto de deuda (AUTOMATION-02) — sin implementación de producto

No existe `docs/superpowers/specs/2026-08-10-audit-*-design.md` en la rama del día. Este archivo consolida deuda verificable contra `origin/main` y reconcilia el inventario anterior (spec `2026-08-06-audit-crud-rbac-router-design.md`, pase deuda @ `ddc55ec`) con la auditoría fuente más reciente mergeada en tip: `docs/audits/2026-08-09-auditoria-tecnica-diaria.md` (PR mergeado @ `487ccd8`).

Specs en pipeline no mergeados a `main` (contexto, no deuda nueva): `2026-08-08-audit-release-semver-tag-design.md` (REL-C1, rama `automation/spec-2026-08-08`); `2026-08-09-audit-crud-uploads-hardening-design.md` (CRUD-C6, rama `automation/spec-2026-08-09`).

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | deuda técnica (degradado) |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `487ccd8132e7c42eabd2a0e3b335b075ccc123e1` |
| SHA Portal inspeccionado | **No verificado** — `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (M6) |
| Rama generada | `automation/spec-2026-08-10` |
| Timestamp UTC | trigger cron `2026-08-10T13:00:24Z` / corrida agente `2026-08-10T13:00:24Z` |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD |
| Modo | **degradado** — sin spec del día |
| Pase deuda | `2026-08-10T13:00:24Z` UTC · modo **degradado** · `origin/main` @ `487ccd8132e7c42eabd2a0e3b335b075ccc123e1` |

### Preflight

```console
$ git fetch origin --prune --tags          → OK
$ git rev-parse --verify origin/main       → 487ccd8132e7c42eabd2a0e3b335b075ccc123e1
$ git merge-base --is-ancestor origin/main HEAD → exit 0
$ git status --porcelain (antes de escribir)    → vacío
$ LEGACY_REF=refs/tags/archive/backoffice-api-integration → ningún commit legacy ancestro de HEAD
```

---

## Deuda técnica

Fuente primaria: `docs/audits/2026-08-09-auditoria-tecnica-diaria.md` @ `487ccd8`; reconciliación con inventario spec `2026-08-06` (pase deuda @ `ddc55ec`) y tip `origin/main` @ `487ccd8` (pase deuda 2026-08-10).

Delta `5bf0863..487ccd8` en `main`: **1 commit** — merge auditoría 2026-08-09 (`docs/audits/2026-08-09-auditoria-tecnica-diaria.md`); **sin cambios de código plataforma**.

### Reconciliación heredada (cerrados)

| ID | Tema | Estado | Resolución |
|----|------|--------|------------|
| **D7** | Sin pipeline GitHub Actions | **Resuelto** | `.github/workflows/platform-tests.yml` presente; `tests/Docs/CiWorkflowPresentTest.php` L8–14 exige workflow; `docs/core/despliegue-y-versionado.md` L225+ § CI/gates PR |
| **M1** (sync trío) | Semver trío sincronizado | **Resuelto en tip** | `composer.json` L6, `config/app.php` L7, `skeleton/config/app.php` L7 → `1.2.7` @ `487ccd8`; tag Composer pendiente → **REL-C1** |
| **M2** | `.env.example` Portal vars | **Resuelto** | #62 — root `.env.example` sin keys Marketing activas; `skeleton/.env.example` sin vars Portal |
| **M7/M8** | Audit lifecycle / ops docs | **Resuelto** | PRs #54–#67, #56/#57 |
| **M9** | dompdf advisories | **Resuelto** | #74 — dompdf v3.1.6 en lock |
| **D1–D5, D13** | Semver harness / env / checklist | **Resuelto** | #62 + #74 |
| **C1** | Scripts `vps-deploy-*` | **Resuelto** | PR #36 |
| **C2** | Stripe subscription (Framework) | **Resuelto** Framework | PR #42 + tags `v1.2.1`…`v1.2.3`; `vertical.payments=false` @ `config/vertical.php` L22 |
| **CRUD-C1/C2/C5** | AuthZ multi-canal | **Resuelto en tip** | PR #95 @ `64a6877` — release bloqueado por REL-C1 |
| **CRUD-C3** | States form lock | **Resuelto en tip** | PR #100 @ `60477dc` — release bloqueado por REL-C1 |

**Cierres desde corrida anterior (2026-08-06 pase deuda @ `ddc55ec`):** **1** — **D7** (CI GitHub Actions implementado en intervalo `ddc55ec..487ccd8`).

**Cierres desde auditoría 2026-08-09 merge (@ `487ccd8`):** **0** — intervalo `5bf0863..487ccd8` sólo añadió artefacto audit.

### Críticos abiertos verificados

| ID | Hallazgo | Evidencia (`main` @ `487ccd8`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **REL-C1** | Tip `1.2.7` sin tags `v1.2.4`…`v1.2.7` | `composer.json` L6 / `config/app.php` L7 / `skeleton/config/app.php` L7 = `1.2.7`; `git tag -l 'v1.2.*'` → sólo hasta `v1.2.3`; `git tag --contains 64a6877` / `60477dc` → vacío | AuthZ (#95) y states (#100) no alcanzables vía Composer tag; consumidores atrapados en `v1.2.3` | Release / Ops | Framework | Merge spec/plan `2026-08-08-audit-release-semver-tag` (rama `automation/spec-2026-08-08`) y publicar tag mínimo `v1.2.7` |
| **CRUD-C4** | Transitions sin CAS / TOCTOU | `CrudTransitionService::apply` L104–108 — `updateRecord` sin predicado `WHERE estado = :from`; lee `$from` de `$record` en memoria L76–77 | Transiciones concurrentes pueden pisar estado | `Application` | Framework | Plan `2026-08-07-crud-p04-cas-bulk-equality` (pendiente crear) |
| **CRUD-C6** | Uploads sin allowlist obligatoria + path sin jail | `UploadValidator::assertValid` L63–68 — allowlist null/vacía no rechaza; test L20–23 «acepta cuando no hay lista blanca»; `FileUploadService::handle` L62–63 concatena `PUBLIC_PATH` sin `realpath`/bloqueo `..`; `CrudDataService::handleUpload` L719 pasa `allowedExtensions: null`; `CrudConfigValidator::validate` L31 — sin reglas `uploads` | Webshell / path traversal / XSS almacenado si tenant habilita uploads mal configurados | `Application` / `Infrastructure` | Framework | Spec `2026-08-09-audit-crud-uploads-hardening-design.md` (rama `automation/spec-2026-08-09`, no en tip) |
| **INV-E1** | Doble timbrado tras timeout post-create | `IssueInvoiceFromSource::handle` L73 — `releaseClaim` en catch cuando create remoto ambiguo | Doble CFDI si se habilita Facturapi en prod | `Application` | Framework | Plan `2026-08-08-invoicing-facturapi-production-hardening.md` (**0/50** checkboxes) |
| **INV-E2** | Fallo dual markIssued/markNeedsReconcile | `IssueInvoiceFromSource::handle` L57–70 — `catch (Throwable)` traga fallo de `markNeedsReconcile`; lanza `InvoiceNeedsReconcile` sin id local persistido | Id remoto irrecuperable localmente | `Application` | Framework | Mismo plan hardening (**0/50**) |

### Medios / backlog Framework verificados

| ID | Hallazgo | Evidencia (`main` @ `487ccd8`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **M3** | CRUD/Calendario sin `RbacMiddleware` router | `routes/web.php` L114–125 — rutas `/crud/{resource}*`, `/calendario/{key}*` sin RBAC router; contraste L127+ (pdf-kit/reportes sí); `skeleton/routes/web.php` espejo idéntico | 403 inconsistentes HTML vs JSON; defensa en profundidad débil | `Presentation` / `routes/` | Framework | Plan `2026-08-06-audit-crud-rbac-router.md` (**0/40** checkboxes) |
| **M4** | `/api/*` sesión; sin health público | `routes/api.php` L14–16 grupo `AuthMiddleware`; L23 `/api/ping` dentro del grupo; `HealthController.php` L13 — sólo `ping()`, sin `health()`; `rg '/health' routes/` → 0 | LB/cron no verifican liveness sin cookie | `Presentation` / `routes/` | Framework | Plan `2026-08-05-audit-api-health-public.md` (**0/38** checkboxes) |
| **M5** | Slug `permisos.gestionar` ausente en seeds | `routes/web.php` L61–65 comentario + workaround `administracion.ver`; `rg permisos.gestionar database/` → 0 | Catálogo RBAC acoplado a permiso amplio | `Domain` RBAC | Framework | Spec futuro CF8 / seed + ruta |
| **M10** | Hueco auditorías 2026-08-03..05 | `docs/audits/` sin `2026-08-0{3,4,5}-auditoria-tecnica-diaria.md`; cadena 06–09 presente | Specs diseñados sin ancla audit diaria esos días | Proceso automation | Ops/automation | Corrida AUTOMATION-00 diaria; `AuditArtifactFreshnessTest` |
| **D6** | `skeleton.lebytek.com` pendiente | `docs/ENVIRONMENTS.md` L7, L13, L34, L43, L66 — «pendiente de implementar» | LAB package puro no desplegado | Ops / Framework | Framework/Ops | Plan `2026-07-26-skeleton-package-staging.md` Tasks 6–8 |

### Planes activos — estado ejecución real

| Plan | Checkboxes | Estado @ `487ccd8` |
|------|------------|-------------------|
| `2026-08-08-audit-release-semver-tag.md` | pendiente | Spec en rama `automation/spec-2026-08-08`, no en tip |
| `2026-08-09-audit-crud-uploads-hardening` (esperado p03) | pendiente | Spec en rama `automation/spec-2026-08-09`, no en tip |
| `2026-08-08-invoicing-facturapi-production-hardening.md` | 0/50 | Pendiente |
| `2026-08-06-audit-crud-rbac-router.md` | 0/40 | Pendiente |
| `2026-08-05-audit-api-health-public.md` | 0/38 | Pendiente |
| `2026-08-04-audit-platform-ci-gates.md` | implementado | **Cerrado** — ver D7 |

### No verificados (declarados explícitamente)

| ID | Hallazgo | Motivo | Acción |
|----|----------|--------|--------|
| **P1** | Portal lock ≥ framework post-tag / `afterListRows` | `gh api repos/Parzival2103/Lebytek_Portal` → HTTP 404 | Verificar tras REL-C1 + resolver M6 |
| **P2** | Portal merge CRUD RBAC | Clone Portal inaccesible | Operador post-release Framework |
| **P3** | Portal CRUD JSON `permission_prefix` | Repo Portal inaccesible | Validar en staging |
| **M6 / D3** | Portal SHA / `composer.lock` | `gh api` → HTTP 404 | Ops: conceder lectura Portal al token automation |
| **D14** | Stripe subscription QA Portal | Portal inaccesible; `vertical.payments=false` @ `config/vertical.php` L22 | Portal: QA checkout antes de `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` |
| **D15** | Bootstrap marketing Portal | Re-scopeado `Lebytek_Portal#4` | Portal issue #4 |
| **H1** | `composer validate` lock content-hash | Composer CLI ausente en agente cloud | Ejecutar en release train humano; CI usa `--no-check-lock` @ `.github/workflows/platform-tests.yml` L25 |

### Verificado sin deuda nueva

- **Migraciones ↔ manifiesto:** 3 archivos `database/migrations/*.sql` ↔ entradas en `config/modules/core.php` L15–17, `crud-engine.php` L14–16, `pdf-kit.php` L16 — sin drift.
- **Bootstrap SQL ↔ manifiesto:** 7 archivos `database/schema/modules/*.sql` ↔ 7 entradas `bootstrap_sql` en `config/modules/{calendario,crud-engine,integrations,invoicing,payments,pdf-kit,reportes}.php` — sin drift.
- **`src/`:** grep `TODO`/`FIXME` → **0**; sin `LebytekApiClient` ni Marketing.
- **Capas:** Domain sin deps Presentation/Infrastructure; hooks en Application.
- **Legacy operativo:** referencias vivas a `feature/backoffice-api-integration` **ausentes** en `scripts/`, `docs/composer-setup.md`, `docs/integration/`, `docs/core/` (históricas bajo `docs/superpowers/` y `docs/CUTOVER-PORTAL.md` = registro, no deuda).
- **Payments bootstrap:** `vertical.payments=false`; `STRIPE_ENABLED=false` @ root `.env.example` L82 — requisitos documentados como gate ops Portal (D14), no auto-fix en `src/`.
- **Invoicing bootstrap:** `vertical.invoicing=false` @ `config/vertical.php` L23; `FACTURAPI_ENABLED=false` @ `.env.example` L89 — mitigación INV-E1/E2 mientras vertical OFF.
- **CI:** workflow + test gate presentes (D7 cerrado); **187** archivos `*Test.php` bajo `tests/`.
- **Doc pre-implementación:** `despliegue-y-versionado.md` tiene § CI (D7); **sin** § Monitoreo/`/api/health` — gap planificado M4, no drift doc↔código.

**Conteo:** **10 abiertos verificados** (REL-C1, CRUD-C4, CRUD-C6, INV-E1, INV-E2, M3, M4, M5, M10, D6) + **M6 abierto entorno**; **7 no verificados** (P1, P2, P3, M6/D3, D14, D15, H1); **1 heredado cerrado** esta corrida (**D7**); **0 cierres** en intervalo audit 09→tip.

---

## Riesgos

| Riesgo | Severidad | Estado / mitigación |
|--------|-----------|---------------------|
| AuthZ/states en tip sin tag Composer | **Alta** | **REL-C1 abierto** — spec/plan en rama `automation/spec-2026-08-08`, no mergeado |
| CRUD-C4 TOCTOU en transiciones | Alta | **Abierto** — plan p04 pendiente |
| CRUD-C6 uploads allowlist/path | Alta | **Abierto** — spec 2026-08-09 en rama, no en tip |
| INV-E1/E2 si se habilita Facturapi | Alta | Mitigado `vertical.invoicing=false`; plan hardening 0/50 |
| Deploy desde rama legacy | Alta (histórico) | **Mitigado** — 53 commits legacy no ancestros de HEAD |
| Stripe sin QA Portal | Alta | Mitigado Framework OFF; D14 no verificable |
| Portal sin bump post-tag AuthZ | Alta (post-release) | Depende REL-C1 + M6 |
| Sin `/api/health` público | Media | M4 — plan 0/38 |
| `skeleton.lebytek.com` sin deploy | Media | D6 |
| Hueco audits 03–05 | Media (proceso) | M10 |
| Portal prod SHA desconocido | Media | M6 |
| Planes M3/M4 con tags semver obsoletos en texto | Baja–Media | Retarget ≥`1.2.8` tras REL-C1 |
| `composer validate` lock warning | Baja | H1 no verificado; CI `--no-check-lock` |

---

## Criterios de aceptación y No-alcance

### Criterios de aceptación (artefacto deuda)

- [ ] **AC-D1:** Inventario lista abiertos verificados (REL-C1, CRUD-C4, CRUD-C6, INV-E1, INV-E2, M3, M4, M5, M10, D6) con evidencia ruta/línea en `main` @ `487ccd8`.
- [ ] **AC-D2:** D7 reconciliado **resuelto**; CRUD-C1/C2/C3/C5 **resueltos en tip** (release bloqueado REL-C1); M1/M2/M7–M9/D1–D5/D13/C1/C2 reconciliados como cerrados o re-scopeados.
- [ ] **AC-D3:** P1, P2, P3, M6/D3, D14, D15, H1 marcados **no verificados** con acción concreta.
- [ ] **AC-D4:** Verificado sin deuda nueva en migraciones, bootstrap, capas, legacy operativo, Payments/Invoicing bootstrap.
- [ ] **AC-D5:** Automation provenance incluye pase deuda, timestamp UTC, SHA `origin/main` y modo **degradado**.

### No-alcance

- Implementación de código en `src/`, `routes/`, `database/`, `skeleton/`, `tests/`.
- Merge/deploy/SSH/`.env`/secretos.
- Merge de `feature/backoffice-api-integration` → `main`.
- Escritura bajo `docs/audits/` (AUTOMATION-00).
- Apertura/cierre de PRs (AUTOMATION-03).
- Negocio Portal (Marketing, leads, membresías, checkout) — repo `Lebytek_Portal`.

---

*Artefacto de deuda (AUTOMATION-02, modo degradado). Ningún archivo de código, config de producto, rutas, migraciones ni tests fue modificado en esta corrida.*
