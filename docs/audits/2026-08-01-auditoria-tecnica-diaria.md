# Auditoría técnica diaria — 2026-08-01

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `5b03d9e678a7021ce741420ee5c3d8a1a2f19fdc` |
| SHA Portal inspeccionado | **No disponible** — `gh repo view` / `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (repo privado o token sin acceso). No se inventa SHA. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `b6c37739983ded445259c038521861ffb5f253c8` @ `main` (sin commits nuevos vía `gh` desde 2026-07-27) |
| Rama generada | `automation/audit-2026-08-01` |
| Timestamp UTC | trigger cron `2026-08-01T12:02:44Z` / corrida agente `2026-08-01T12:03:26Z` |
| Automation ID | `42e87765-8b95-11f1-b532-320a589b8025` |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
(ok; FETCH_EXIT=0)

$ git rev-parse --verify origin/main
5b03d9e678a7021ce741420ee5c3d8a1a2f19fdc

$ git merge-base --is-ancestor origin/main HEAD
(exit 0)

$ git status --porcelain   # antes de escribir
(vacío)
```

### `<LEGACY_REF>`

Primer candidato que resolvió:

```console
$ git rev-parse --verify --quiet 'refs/tags/archive/backoffice-api-integration^{commit}'
4789f953ef746d17bae2e6b50c85504782d306e3
```

- Tag canónico FQ: `refs/tags/archive/backoffice-api-integration` @ `4789f95`.
- `refs/remotes/origin/feature/backoffice-api-integration` también resuelve al mismo SHA; se usa el tag (primer candidato, FQ — evita ambigüedad con rama local obsoleta).
- Commits exclusivos del legacy: **53** (`git rev-list origin/main..refs/tags/archive/backoffice-api-integration`).
- Comprobación de ancestros sobre **los 53** commits: **ninguno** es ancestro de `HEAD` (`LEGACY_ANCESTOR_CHECK_BAD=0`). Comprobar sólo la punta sería insuficiente.
- Nota de proceso: PR [#59](https://github.com/Parzival2103/Lebytek_Framework/pull/59) endureció AUTOMATION-06/08 para exigir refs FQ en el mismo criterio.

---

## Resumen ejecutivo

`origin/main` **avanzó** desde la auditoría del 2026-07-31 (`e19fa25` → `5b03d9e`): **6 commits** (PRs #48, #55–#59). **Cero cambios en `src/`**, rutas, SQL de plataforma, `config/` de app o skeleton funcional. Release tip sigue en tag `v1.2.1` @ `fba3e03`.

Novedades positivas:

1. **M8 / D5 RESUELTO** — runbooks ops alineados con FPS (`docs/composer-setup.md`, `VPS_CHECKLIST.md`, integration docs) vía [#56](https://github.com/Parzival2103/Lebytek_Framework/pull/56)/[#57](https://github.com/Parzival2103/Lebytek_Framework/pull/57); gate `OpsDocsFpsAlignmentTest` en Docs; plan archivado en `docs/archive/superpowers/plans/2026-07-31-audit-ops-docs-legacy-alignment.md`.
2. Pipeline de ejecución documentado: AUTOMATION-06/07/08 (#58) + preflight legacy FQ (#59).
3. Audit del 31 mergeado (#55); Docs suite **18 → 19** tests.

**No hay hallazgos críticos ni medios nuevos de código.** Fronteras FPS intactas. Deuda abierta concentrada en hygiene (M1–M5), bloqueador Portal gh (M6), y ops de entorno (D6/D7 + QA Stripe Portal).

**Hallazgos nuevos:** 0 críticos, 0 medios. **Deuda arrastrada abierta:** 5 medios código/harness + M6 entorno + D6/D7 + ops Stripe Portal.

---

## Hallazgos críticos

*Ningún hallazgo crítico nuevo en Framework `main` en esta corrida.*

### Deuda crítica arrastrada (estado actualizado)

| ID | Hallazgo | Estado 2026-08-01 | Owner |
|----|----------|-------------------|-------|
| C1 (2026-07-27) | Scripts `vps-deploy-*.sh` destructivos | **RESUELTO** — eliminados PR #36; `DeployScriptsRemovedTest` + Docs verdes; runbooks ya no los recomiendan (M8) | Framework |
| C2 (2026-07-27) / #21 | Stripe subscription C1–C6 | **Framework RESUELTO** — PR #42 + tag `v1.2.1`. Gate ops `PAYMENTS_SUBSCRIPTION_CHECKOUT` es **Portal/VPS** (no en `src/` ni root `.env.example`). Mitigación Framework: `STRIPE_ENABLED=false`, `vertical.payments=false`. QA Portal sigue pendiente / no verificable aquí | Framework ✅ / Portal ⏳ QA |
| C3 (2026-07-27) / #23 | Bootstrap marketing + migraciones | **Re-scopeado** — Portal; issue Framework #23 CLOSED | Portal |

---

## Hallazgos medios

*Ningún hallazgo medio nuevo en esta corrida.*

### M8 / D5 — Docs ops legacy (arrastrado → **RESUELTO**)

| Campo | Valor |
|-------|-------|
| Evidencia resolución | `docs/composer-setup.md` §6 pinnea `^1.2` (sin `dev-feature/backoffice-api-integration`); `VPS_CHECKLIST.md` apunta a `Lebytek_Portal` @ `main` + `ENVIRONMENTS.md`; needles prohibidos ausentes en runbooks operativos; `tests/Docs/OpsDocsFpsAlignmentTest.php` **PASS**; plan archivado post-#57/#58 |
| Estado | **RESUELTO** en `main`. No reabrir salvo regresión del gate Docs. |

### M1 — `config/app.php` version desincronizada del release semver (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivo | `config/app.php` L7 |
| Evidencia | `'version' => '1.0.0'` mientras tags publicados llegan a `v1.2.1` (`fba3e03`). Spec+plan en `main` (`docs/archive/superpowers/specs/2026-07-29-audit-config-version-semver-sync-design.md`) — **diseño sin implementar**. Sin cambios de código en este intervalo. |
| Impacto | Dashboard/operadores pueden mostrar versión incorrecta |
| Owner | Framework |
| Estado | **Abierto** — listo para PR de implementación desde spec #50 |

### M2 — `.env.example` root conserva variables Portal/Marketing (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivos | `.env.example` (root harness) |
| Evidencia | **16** keys activas `MKT_*` / `LEBYTEK_API_*` / `WAAPI_PORTAL_*`; `skeleton/.env.example` limpio (`SkeletonPurityTest` PASS). Sin `PAYMENTS_SUBSCRIPTION_CHECKOUT` en harness (correcto — Portal ops). |
| Impacto | Ruido en harness; confusión para nuevos tenants |
| Owner | Framework (harness) |
| Estado | **Abierto** — cubierto por Fase 1 del spec 2026-07-29 |

### M3 — CRUD/Calendario sin `RbacMiddleware` a nivel router (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivo | `routes/web.php` L114–125 |
| Evidencia | Rutas `/crud/{resource}` y `/calendario/{key}` sólo heredan `AuthMiddleware` del grupo; RBAC dentro del servicio |
| Impacto | Defensa en profundidad inconsistente; 403 desde servicio, no middleware |
| Owner | Framework |
| Estado | **Abierto** — backlog riesgo bajo (D4/D6 inventario) |

### M4 — API `/api/*` autenticada por sesión (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivo | `routes/api.php` |
| Evidencia | Grupo con `AuthMiddleware` (sesión); comentario «Autenticación futura mediante token»; `/api/ping` detrás de auth; sin token API de plataforma |
| Owner | Framework |
| Estado | **Abierto** — D3 inventario |

### M5 — `permisos.gestionar` ausente en seeds (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Comentario en `routes/web.php` / `skeleton/routes/web.php`: se usa `administracion.ver` como workaround; slug ausente en `database/schema/` / seeds (`rg permisos.gestionar database/` → 0) |
| Owner | Framework |
| Estado | **Abierto** — backlog producto |

### M6 — Portal SHA no inspeccionable desde agente cloud (arrastrado / entorno)

| Campo | Valor |
|-------|-------|
| Evidencia | `gh repo view` → GraphQL «Could not resolve…»; `gh api …/commits/main` → HTTP 404 |
| Impacto | No se puede verificar SHA producción ni `composer.lock` (¿≥ v1.2.1?) en esta corrida |
| Owner | Ops / credenciales automation |
| Estado | **Abierto** — bloqueador de entorno, no defecto de código |

### M7 — Artefacto audit cerrado sin merge (histórico → resuelto)

| Campo | Valor |
|-------|-------|
| Evidencia | PR [#51](https://github.com/Parzival2103/Lebytek_Framework/pull/51) MERGED; lifecycle Enfoque B + tests Docs; audit #55 también MERGED `2026-07-31T17:01:39Z` |
| Estado | **RESUELTO** — no reabrir salvo regresión |

---

## Deuda arrastrada desde la auditoría anterior

Fuente mergeada: `docs/audits/2026-07-31-auditoria-tecnica-diaria.md` (PR #55) + inventario `docs/audits/2026-07-28-deuda-tecnica-inventario.md` (PR #47).

| Ítem | Origen | Estado hoy |
|------|--------|------------|
| C1 deploy scripts | 2026-07-27 | RESUELTO |
| C2 Stripe Framework | 2026-07-27 / #21 | Framework RESUELTO (v1.2.1); ops Portal pendiente / no verificable |
| C3 marketing bootstrap | 2026-07-27 / #23 | Portal; cerrado en Framework |
| M1 version UI | 2026-07-29 | **Abierto** — spec/plan en main, sin código |
| M2 `.env.example` Marketing | 2026-07-27 | **Abierto** |
| M3 CRUD RBAC router | 2026-07-27 | **Abierto** |
| M4 API sesión | 2026-07-27 | **Abierto** |
| M5 `permisos.gestionar` | 2026-07-27 | **Abierto** |
| M6 Portal gh 404 | 2026-07-29 | **Abierto** (entorno) |
| M7 audit PR sin merge | 2026-07-30 | RESUELTO (#51 + #54; #55 también mergeado) |
| M8 / D5 docs ops legacy | inventario 2026-07-28 / audit 31 | **RESUELTO** (#56/#57 + `OpsDocsFpsAlignmentTest`) |
| D6 skeleton.lebytek.com | inventario / plan 2026-07-26 | **Abierto** — plan reconciliado (#53), sin implementación |
| D7 CI GitHub Actions | inventario | **Abierto** — `.github/workflows/` ausente |

**Hallazgos nuevos de código en `src/`:** ninguno (delta docs/automation/tests Docs).

---

## Ownership por repositorio

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `5b03d9e` | Auth, RBAC, CRUD, install, Payments genérico, skeleton |
| Release semver | Tag `v1.2.1` @ `fba3e03` | Contrato Stripe subscription + install fixes — tip vigente |
| App desplegable | `Lebytek_Portal` / `main` | Marketing, leads, membresías, checkout UX, SQL `dom_*`, gate `PAYMENTS_SUBSCRIPTION_CHECKOUT` |
| API WhatsApp | `WhatsApiLebytek` / `main` @ `b6c3773` | Green API, lifecycle instancias |
| Legacy (histórico) | `archive/backoffice-api-integration` @ `4789f95` | Evidencia migración — **no** base de trabajo |

**Dependencia cruzada:** habilitar subscription checkout en Portal requiere tag Framework ≥ `v1.2.1` **y** QA del bump/`composer.lock` en Portal. Un fix Portal de subscriptions **depende** de ese tag ya publicado. Esta corrida **no** pudo confirmar el lock de Portal (M6). Los cambios docs/automation de hoy **no** requieren nuevo tag.

---

## Cambios recientes en `main` (desde auditoría 2026-07-31 @ `e19fa25`)

| Commit / PR | Tema | Relevancia |
|-------------|------|------------|
| `61ad2fc` / #48 | Auditoría diaria 2026-07-29 | Artefacto histórico mergeado tarde |
| `f0db8d2` / #55 | Auditoría diaria 2026-07-31 | Ancla audit anterior en `main` |
| `8196d3e` / #56 | Spec alineación docs ops | Diseño M8 |
| `562c6ab` / #57 | Implementación runbooks FPS | **Cierra M8** + test Docs |
| `aa895fa` / #58 | Pipeline AUTOMATION-06/07/08 + archive plan M8 | Ejecución documentada |
| `5b03d9e` / #59 | Preflight legacy refs FQ en 06/08 | Endurece automation |

PRs abiertos al momento de la corrida: **0**. Issues abiertos Framework: **0**.

Delta `f0db8d2..origin/main`: 13 archivos, **solo** docs + `tests/Docs/OpsDocsFpsAlignmentTest.php`. `src/` / `database/` / `routes/` / `skeleton/` / `config/app.php`: sin cambios.

---

## Fronteras del paquete

Verificación estática + suites:

- `src/` — sin módulo Marketing/`LebytekApiClient`; Payments genérico en `src/Domain/Payments/` (`SupportsSubscriptions`, gateways, VOs).
- `config/vertical.php` y `skeleton/config/vertical.php` — `marketing=false`, `payments=false`.
- `SkeletonPurityTest` — **13/13 PASS**.
- `scripts/vps-deploy-*.sh` — **0 archivos**; Docs suite PASS (incl. M8 gate).
- Schema plataforma en `database/schema/` (+ modules calendario/crud/integrations/payments/pdf-kit/reportes) + `database/migrations/`; sin SQL Marketing/`dom_*`.
- Root `.env.example` aún tiene ruido Portal (M2); skeleton limpio.

**Conclusión:** no se coló negocio Portal en Framework en este intervalo (delta docs/automation).

---

## Riesgo de deploy / release

| Riesgo | Severidad | Estado |
|--------|-----------|--------|
| Deploy desde rama legacy | Alta (histórico) | **Mitigado** — scripts deploy eliminados; tag archivado; 53 commits no ancestros de HEAD; runbooks alineados (M8) |
| Stripe subscriptions sin QA Portal | Alta | **Mitigado en Framework** — vertical/STRIPE OFF; bump Portal no verificable (M6) |
| Portal prod SHA desconocido | Media | **Bloqueador entorno** — gh 404 |
| Docs ops con feature/scripts muertos | Media | **Mitigado** — M8 resuelto |
| Cadena audit sin merge | Media | **Mitigado** — M7 + #55 mergeado; pipeline 06–08 documentado |
| `skeleton.lebytek.com` no desplegado | Media | **Abierto** — D6 |
| Staging Portal inexistente | Media | **Abierto** — `ENVIRONMENTS.md` |
| Versión UI `1.0.0` vs tag `v1.2.1` | Baja | M1 — spec listo |
| Sin CI Actions | Media | D7 — gates sólo locales/agente |
| Fresh install SQL `;` en strings | Media | **Resuelto** PR #40 |

---

## Archivos involucrados

Delta `e19fa25..5b03d9e` (docs/automation + test Docs; + audits 29/31 mergeados):

- `docs/audits/2026-07-29-auditoria-tecnica-diaria.md`, `2026-07-31-auditoria-tecnica-diaria.md`
- `docs/composer-setup.md`, `docs/integration/VPS_CHECKLIST.md`, `lebytek-implementation-real.md`, `role-delegation-lebytek-api.md`
- `docs/core/seguridad_secretos_deploy.md`
- `docs/archive/superpowers/specs/2026-07-31-audit-ops-docs-legacy-alignment-design.md`
- `docs/archive/superpowers/plans/2026-07-30-audit-artifact-chain.md`, `2026-07-31-audit-ops-docs-legacy-alignment.md`
- `docs/automation/AUTOMATION-06|07|08-*.md`, `README.md`
- `tests/Docs/OpsDocsFpsAlignmentTest.php`

Re-inspeccionados para deuda abierta (sin cambio de código):

- `config/app.php` — version `1.0.0` (M1)
- `.env.example` — 16 keys Marketing/API (M2); `STRIPE_ENABLED=false`
- `routes/web.php`, `routes/api.php` — M3/M4/M5
- `src/Domain/Payments/*`, `config/vertical.php`, `skeleton/config/vertical.php`
- Artefacto nuevo: `docs/audits/2026-08-01-auditoria-tecnica-diaria.md` (este archivo)

---

## Evidencia de verificación

### Entorno

| Componente | Estado |
|------------|--------|
| PHP CLI | Ausente al inicio → instalado ad-hoc `php8.3-cli` (+ xml, mbstring, curl, zip, sqlite, mysql) |
| Composer / vendor | Ausente → `composer.phar` 2.10.2 + `composer install` ad-hoc; `composer.phar` **eliminado** antes del commit |
| `ext-pdo_mysql` | Presente tras install |
| Servidor MySQL | **Ausente** — 7 tests Integrations fallan con `SQLSTATE[HY000] [2002] Connection refused` |
| Acceso Portal (`gh`) | **404** — bloqueador entorno |
| Issues abiertos Framework | **0** |
| PRs abiertos Framework | **0** |

### Comandos ejecutados

```console
$ php tests/run.php
575 passed, 7 failed
exit code: 1

$ php tests/run.php Kernel
46 passed, 0 failed
exit code: 0

$ php tests/run.php Payments
21 passed, 0 failed
exit code: 0

$ php tests/run.php SkeletonPurity
13 passed, 0 failed
exit code: 0

$ php tests/run.php Install
50 passed, 0 failed
exit code: 0

$ php tests/run.php Docs
19 passed, 0 failed
exit code: 0
```

Contadores: suite completa **575 passed / 7 failed** (ayer 574/7; +1 Docs = `OpsDocsFpsAlignmentTest`). Suites críticas **100% verdes**. Ningún comando descubrió cero tests.

### Análisis de fallos

Los **7 fallos** son tests Integrations/WhatsApp que requieren MySQL vivo:

- `save + findById conserva datos...`
- `el token se guarda cifrado...`
- `markDefault deja solo una instancia...`
- `recent devuelve los últimos envíos...`
- `recent filtra por canal`
- `IntegrationsFactory::resolveWhatsappConfig...` (×2)

**Clasificación:** bloqueador de entorno (sin daemon MySQL), **no** defecto de código.

---

## Recomendación final

1. **Mergear** este PR draft de audit cuando AUTOMATION-03 lo procese (Enfoque B); no cerrarlo sin `mergedAt`.
2. **Implementar** el spec 2026-07-29 (M1 version sync + M2 purga `.env.example` + test gate root) — diseño ya en `main`; candidato natural para AUTOMATION-06→07.
3. **Portal:** operador con acceso debe confirmar SHA + `composer.lock` ≥ `v1.2.1` y QA Stripe antes de habilitar subscription checkout.
4. **Automation:** conceder al token `gh` lectura de `Lebytek_Portal`; preinstalar PHP + Composer + MySQL (o skip Integrations sin DSN) en la imagen del agente.
5. **Próximo hito operativo:** `skeleton.lebytek.com` según plan reconciliado 2026-07-26/#53; considerar CI Actions (D7).

**Veredicto:** paquete sano; **0 críticos nuevos**; **0 medios nuevos**; **M8 resuelto**; release `v1.2.1` coherente; deuda restante = hygiene (M1–M5) + bloqueadores de entorno (M6, MySQL CI); verificación Portal/ops sigue bloqueada por credenciales.

---

*Report-only. Ningún archivo de código, config, rutas, migraciones, scripts ni specs fue modificado en esta corrida.*
