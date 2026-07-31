# Auditoría técnica diaria — 2026-07-31

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `e19fa25c7c96560462f60c31b56b99c8d7eaf619` |
| SHA Portal inspeccionado | **No disponible** — `gh repo view` / `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (repo privado o token sin acceso). No se inventa SHA. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `b6c37739983ded445259c038521861ffb5f253c8` @ `main` (sin commits nuevos vía `gh` desde 2026-07-27) |
| Rama generada | `automation/audit-2026-07-31` |
| Timestamp UTC | trigger cron `2026-07-31T12:00:49Z` / corrida agente `2026-07-31T12:02:20Z` |
| Automation ID | `42e87765-8b95-11f1-b532-320a589b8025` |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
(ok; FETCH_EXIT=0)

$ git rev-parse --verify origin/main
e19fa25c7c96560462f60c31b56b99c8d7eaf619

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

- Tag canónico: `archive/backoffice-api-integration` @ `4789f95`.
- `refs/remotes/origin/feature/backoffice-api-integration` también resuelve al mismo SHA; se usa el tag (primer candidato, FQ).
- Commits exclusivos del legacy: **53** (`git rev-list origin/main..refs/tags/archive/backoffice-api-integration`).
- Comprobación de ancestros sobre **los 53** commits: **ninguno** es ancestro de `HEAD` (`ANCESTOR_FAIL=0`). Comprobar sólo la punta sería insuficiente.

---

## Resumen ejecutivo

`origin/main` **avanzó** desde la auditoría del 2026-07-30 (`0ec722b` → `e19fa25`): **6 commits**, todos de documentación/automation (PRs #47, #50–#54). **Cero cambios en `src/`**, rutas, SQL de plataforma o skeleton funcional. Release tip sigue en tag `v1.2.1` @ `fba3e03`.

Novedad positiva de proceso: el PR audit del 30 ([#51](https://github.com/Parzival2103/Lebytek_Framework/pull/51)) **sí se mergeó**; aterrizaron el inventario D1–D11 (#47), specs/planes de semver sync y artifact chain (#50/#52/#53), y el lifecycle Enfoque B con tests (`AuditArtifactFreshnessTest`, `AutomationPromptInvariantTest`) vía [#54](https://github.com/Parzival2103/Lebytek_Framework/pull/54). **M7 queda resuelto en política + evidencia en `main`.**

**No hay hallazgos críticos nuevos de código.** Fronteras FPS intactas. Deuda de hygiene (M1–M6) y docs ops obsoletos (D5) siguen abiertas; el spec de sync semver (#50) está en `main` pero **sin implementación**.

**Hallazgos nuevos:** 0 críticos, 1 medio (docs ops D5 elevado a tracking diario). **Deuda arrastrada:** 5 medios de código/harness + M6 entorno + ops Stripe Portal.

---

## Hallazgos críticos

*Ningún hallazgo crítico nuevo en Framework `main` en esta corrida.*

### Deuda crítica arrastrada (estado actualizado)

| ID | Hallazgo | Estado 2026-07-31 | Owner |
|----|----------|-------------------|-------|
| C1 (2026-07-27) | Scripts `vps-deploy-*.sh` destructivos | **RESUELTO** — eliminados PR #36; `DeployScriptsRemovedTest` + Docs suite verdes | Framework |
| C2 (2026-07-27) / #21 | Stripe subscription C1–C6 | **Framework RESUELTO** — PR #42 + tag `v1.2.1`. Gate ops `PAYMENTS_SUBSCRIPTION_CHECKOUT` es **Portal/VPS** (no aparece en `src/` ni en root `.env.example` del harness; evidencia D8 del inventario era imprecisa). Mitigación Framework: `STRIPE_ENABLED=false`, `vertical.payments=false`. QA Portal sigue pendiente / no verificable aquí | Framework ✅ / Portal ⏳ QA |
| C3 (2026-07-27) / #23 | Bootstrap marketing + migraciones | **Re-scopeado** — Portal; issue Framework #23 CLOSED | Portal |

---

## Hallazgos medios

### M8 (nuevo / elevado desde D5) — Docs ops aún apuntan a legacy branch y scripts eliminados

| Campo | Valor |
|-------|-------|
| Archivos | `docs/integration/VPS_CHECKLIST.md` L13, L89; `docs/composer-setup.md` L127 |
| Evidencia | Checklist: «Deploy lebytek ≥ … `vps-deploy-lebytek-com.sh`» y `Branch: feature/backoffice-api-integration (until merge)`; `composer-setup.md` § pin `"lebytek/framework": "dev-feature/backoffice-api-integration"`. Scripts `vps-deploy-*` **no existen**; verdad de producto = Portal `main` + tag semver. Inventario D5 ya lo documentó (PR #47); **sin fix** en el intervalo auditado. |
| Impacto | Operador/nuevo consumidor puede apuntar a monolito legacy o a scripts borrados |
| Owner | Framework (docs) |
| Estado | **Abierto** — elevar a PR docs menor; alinear con `docs/ENVIRONMENTS.md` |

### M1 — `config/app.php` version desincronizada del release semver (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivo | `config/app.php` L7 |
| Evidencia | `'version' => '1.0.0'` mientras tags publicados llegan a `v1.2.1` (`fba3e03`). Spec+plan en `main` (`docs/superpowers/specs/2026-07-29-audit-config-version-semver-sync-design.md`, plan homónimo) — **diseño sin implementar**. |
| Impacto | Dashboard/operadores pueden mostrar versión incorrecta |
| Owner | Framework |
| Estado | **Abierto** — listo para PR de implementación desde spec #50 |

### M2 — `.env.example` root conserva variables Portal/Marketing (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivos | `.env.example` (root harness) |
| Evidencia | **16** keys activas `MKT_*` / `LEBYTEK_API_*` / `WAAPI_PORTAL_*`; `skeleton/.env.example` limpio (`SkeletonPurityTest` PASS) |
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
| Evidencia | Grupo con `AuthMiddleware` (sesión); sin token API de plataforma; sin `/api/health` público |
| Owner | Framework |
| Estado | **Abierto** — D3 inventario |

### M5 — `permisos.gestionar` ausente en seeds (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Comentario en `routes/web.php` / `skeleton/routes/web.php`: se usa `administracion.ver` como workaround; slug ausente en `database/schema/schema.sql` / seeds |
| Owner | Framework |
| Estado | **Abierto** — backlog producto (D8 inventario / D4) |

### M6 — Portal SHA no inspeccionable desde agente cloud (arrastrado / entorno)

| Campo | Valor |
|-------|-------|
| Evidencia | `gh repo view` / `gh api` → 404; token automation sin acceso al repo privado |
| Impacto | No se puede verificar SHA producción ni `composer.lock` (¿≥ v1.2.1?) en esta corrida |
| Owner | Ops / credenciales automation |
| Estado | **Abierto** — bloqueador de entorno, no defecto de código |

### M7 — Artefacto audit cerrado sin merge (arrastrado → resuelto)

| Campo | Valor |
|-------|-------|
| Evidencia resolución | PR [#51](https://github.com/Parzival2103/Lebytek_Framework/pull/51) **MERGED** `2026-07-30T16:09:42Z` (`docs/audits/2026-07-30-…` en `main`). Lifecycle Enfoque B documentado en `docs/automation/README.md` + prompts; tests Docs: `AUTOMATION-03 requires gh pr merge…`, `forbids closing audit PR without merge`, `AuditArtifactFreshnessTest` — **18/18 PASS**. |
| Estado | **RESUELTO** en `main` (política + tests + merge exitoso del audit 2026-07-30). Incidente histórico #48 permanece como lección; no reabrir salvo regresión. |

---

## Deuda arrastrada desde la auditoría anterior

Fuente mergeada: `docs/audits/2026-07-30-auditoria-tecnica-diaria.md` (PR #51) + inventario `docs/audits/2026-07-28-deuda-tecnica-inventario.md` (PR #47).

| Ítem | Origen | Estado hoy |
|------|--------|------------|
| C1 deploy scripts | 2026-07-27 | RESUELTO |
| C2 Stripe Framework | 2026-07-27 / #21 | Framework RESUELTO (v1.2.1); ops Portal pendiente / no verificable |
| C3 marketing bootstrap | 2026-07-27 / #23 | Portal; cerrado en Framework |
| M1 version UI | 2026-07-29 | Abierto — spec/plan en main, sin código |
| M2 `.env.example` Marketing | 2026-07-27 | Abierto |
| M3 CRUD RBAC router | 2026-07-27 | Abierto |
| M4 API sesión | 2026-07-27 | Abierto |
| M5 `permisos.gestionar` | 2026-07-27 | Abierto |
| M6 Portal gh 404 | 2026-07-29 | Abierto (entorno) |
| M7 audit PR sin merge | 2026-07-30 | **RESUELTO** (#51 + #54) |
| M8 / D5 docs ops legacy | inventario 2026-07-28 | **Abierto** — reconfirmado |
| D6 skeleton.lebytek.com | inventario / plan 2026-07-26 | Abierto — plan reconciliado (#53), sin implementación |
| D7 CI GitHub Actions | inventario | Abierto — `.github/workflows/` ausente |

**Hallazgos nuevos de código en `src/`:** ninguno (delta docs-only).

---

## Ownership por repositorio

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `e19fa25` | Auth, RBAC, CRUD, install, Payments genérico, skeleton |
| Release semver | Tag `v1.2.1` @ `fba3e03` | Contrato Stripe subscription + install fixes |
| App desplegable | `Lebytek_Portal` / `main` | Marketing, leads, membresías, checkout UX, SQL `dom_*`, gate `PAYMENTS_SUBSCRIPTION_CHECKOUT` |
| API WhatsApp | `WhatsApiLebytek` / `main` @ `b6c3773` | Green API, lifecycle instancias |
| Legacy (histórico) | `archive/backoffice-api-integration` @ `4789f95` | Evidencia migración — **no** base de trabajo |

**Dependencia cruzada:** habilitar subscription checkout en Portal requiere tag Framework ≥ `v1.2.1` **y** QA del bump/`composer.lock` en Portal. Un fix Portal de subscriptions **depende** de ese tag ya publicado. Esta corrida **no** pudo confirmar el lock de Portal (M6).

---

## Cambios recientes en `main` (desde auditoría 2026-07-30 @ `0ec722b`)

| Commit / PR | Tema | Relevancia |
|-------------|------|------------|
| `e6ec2b3` / #51 | Auditoría diaria 2026-07-30 | Ancla audit mergeada (cierra M7 operativo) |
| `7f9d350` / #47 | Inventario deuda D1–D11 | Carry-forward estructurado |
| `82d8b58` / #53 | Plan skeleton staging reconciliado | Ops/docs; sin código |
| `1ada9a1` / #52 | Spec artifact chain | Input a #54 |
| `1f2c41d` / #50 | Spec semver sync | Diseño M1/M2 — **sin implementar** |
| `e19fa25` / #54 | Lifecycle F1–F6 + tests Docs | Guarda M7; Docs 13→18 tests |

PRs abiertos al momento de la corrida: **0**.

---

## Fronteras del paquete

Verificación estática + suites:

- `src/` — sin módulo Marketing/LebytekApi; `membresiaId` sólo como metadata opcional en contrato Payments (`PaymentEvent` / `StripeGateway`) — frontera genérica aceptable.
- `config/vertical.php` y `skeleton/config/vertical.php` — `marketing=false`, `payments=false`.
- `SkeletonPurityTest` — **13/13 PASS**.
- `scripts/vps-deploy-*.sh` — **0 archivos**; Docs suite PASS.
- Payments — contrato en `src/Domain/Payments/` (`SupportsSubscriptions`); vertical OFF; `STRIPE_ENABLED=false` en `.env.example`.
- Schema plataforma en `database/schema/` + `database/migrations/`; seeds legacy bajo `seeds_legacy/` — sin SQL Marketing.

**Conclusión:** no se coló negocio Portal en Framework en este intervalo (delta docs-only).

---

## Riesgo de deploy / release

| Riesgo | Severidad | Estado |
|--------|-----------|--------|
| Deploy desde rama legacy | Alta (histórico) | **Mitigado** — scripts deploy eliminados; tag archivado; 53 commits no ancestros de HEAD |
| Stripe subscriptions sin QA Portal | Alta | **Mitigado en Framework** — vertical/STRIPE OFF; bump Portal no verificable (M6) |
| Portal prod SHA desconocido | Media | **Bloqueador entorno** — gh 404 |
| Docs ops con feature/scripts muertos | Media | **M8/D5** — confusión deploy |
| Cadena audit sin merge | Media | **Mitigado** — M7 resuelto (#51/#54) |
| `skeleton.lebytek.com` no desplegado | Media | **Abierto** — plan reconciliado, sin publish script |
| Staging Portal inexistente | Media | **Abierto** — `ENVIRONMENTS.md` |
| Versión UI `1.0.0` vs tag `v1.2.1` | Baja | M1 — spec listo |
| Sin CI Actions | Media | D7 — gates sólo locales/agente |
| Fresh install SQL `;` en strings | Media | **Resuelto** PR #40 |

---

## Archivos involucrados

Delta `0ec722b..e19fa25` (13 archivos, docs/tests Docs):

- `docs/audits/2026-07-28-deuda-tecnica-inventario.md`
- `docs/audits/2026-07-30-auditoria-tecnica-diaria.md`
- `docs/automation/*` (README, AUTOMATION-01/03, INCIDENT lineage)
- `docs/superpowers/specs/2026-07-29-…`, `2026-07-30-…`
- `docs/superpowers/plans/2026-07-26-…`, `2026-07-29-…`, `2026-07-30-…`
- `tests/Docs/AuditArtifactFreshnessTest.php`
- `tests/Docs/AutomationPromptInvariantTest.php`

Re-inspeccionados para deuda abierta (sin cambio):

- `config/app.php` — version `1.0.0` (M1)
- `.env.example` — vars Marketing/API (M2); sin `PAYMENTS_SUBSCRIPTION_CHECKOUT`
- `routes/web.php`, `routes/api.php` — M3/M4/M5
- `docs/integration/VPS_CHECKLIST.md`, `docs/composer-setup.md` — M8/D5
- `src/Domain/Payments/*`, `config/vertical.php`, `skeleton/config/vertical.php`
- Artefacto nuevo: `docs/audits/2026-07-31-auditoria-tecnica-diaria.md` (este archivo)

---

## Evidencia de verificación

### Entorno

| Componente | Estado |
|------------|--------|
| PHP CLI | Ausente al inicio → instalado ad-hoc `php8.3-cli` (+ xml, mbstring, curl, zip, sqlite, mysql) |
| Composer / vendor | Ausente → `composer.phar` + `composer install` ad-hoc; `composer.phar` eliminado antes del commit |
| `ext-pdo_mysql` | Presente tras install |
| Servidor MySQL | **Ausente** — 7 tests Integrations fallan con `SQLSTATE[HY000] [2002] Connection refused` |
| Acceso Portal (`gh`) | **404** — bloqueador entorno |
| Issues abiertos Framework | **0** (`gh issue list --state open`) |

### Comandos ejecutados

```console
$ php tests/run.php
574 passed, 7 failed
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
18 passed, 0 failed
exit code: 0
```

Contadores: suite completa **574 passed / 7 failed** (ayer 569/7; +5 Docs por guards M7). Suites críticas **100% verdes**. Ningún comando descubrió cero tests.

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
2. **Implementar** el spec 2026-07-29 (M1 version sync + M2 purga `.env.example` + test gate root) — diseño ya en `main`.
3. **PR docs menor** para M8/D5: reescribir `VPS_CHECKLIST.md` y `composer-setup.md` §6 según `ENVIRONMENTS.md` (Portal `main` + semver).
4. **Portal:** operador con acceso debe confirmar SHA + `composer.lock` ≥ `v1.2.1` y QA Stripe antes de habilitar subscription checkout.
5. **Automation:** conceder al token `gh` lectura de `Lebytek_Portal`; preinstalar PHP + Composer + MySQL (o skip Integrations sin DSN) en la imagen del agente.
6. **Próximo hito operativo:** `skeleton.lebytek.com` según plan reconciliado 2026-07-26/#53.

**Veredicto:** paquete sano; **0 críticos nuevos**; **1 medio nuevo (docs ops)**; M7 resuelto; release `v1.2.1` coherente; deuda de código concentrada en hygiene (version/env/RBAC) con specs listos; verificación Portal/ops sigue bloqueada por credenciales.

---

*Report-only. Ningún archivo de código, config, rutas, migraciones, scripts ni specs fue modificado en esta corrida.*
