# Auditoría técnica diaria — 2026-07-30

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `0ec722bc38258b2e479d30cafd59940aa44d558e` |
| SHA Portal inspeccionado | **No disponible** — `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (repo privado o token sin acceso). Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría consolidada 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `b6c37739983ded445259c038521861ffb5f253c8` @ `main` (2026-07-27; sin commits nuevos visibles vía `gh`) |
| Rama generada | `automation/audit-2026-07-30` |
| Timestamp UTC | trigger cron `2026-07-30T12:02:41Z` / corrida agente `2026-07-30T12:04:26Z` |
| Automation ID | `42e87765-8b95-11f1-b532-320a589b8025` |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
(ok; FETCH_EXIT=0)

$ git rev-parse --verify origin/main
0ec722bc38258b2e479d30cafd59940aa44d558e

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
- `refs/remotes/origin/feature/backoffice-api-integration` también resuelve al mismo SHA; el tag es el primer candidato y la referencia preferida.
- Commits exclusivos del legacy: **53** (`git rev-list origin/main..refs/tags/archive/backoffice-api-integration`).
- Comprobación de ancestros: **ninguno** de los 53 commits es ancestro de `HEAD` (`LEGACY_ANCESTOR_CHECK_FAIL=0`).

---

## Resumen ejecutivo

`origin/main` **no avanzó** desde la auditoría draft del 2026-07-29 ni desde el tip documentado entonces: sigue en `0ec722b` (docs automation hardening, PRs #36–#46 ya en árbol). **No hay hallazgos críticos nuevos de código.**

La novedad operativa de esta corrida es de **proceso de la cadena automation**: el PR draft de auditoría del 29 ([#48](https://github.com/Parzival2103/Lebytek_Framework/pull/48)) fue **cerrado sin merge** por el owner («continúa en #50»). El artefacto `docs/audits/2026-07-29-auditoria-tecnica-diaria.md` **no está en `main`**. La última auditoría consolidada mergeada sigue siendo la del **2026-07-27** (PR #37). Los hallazgos medios de ayer se revalidan aquí y se arrastran.

Fronteras FPS intactas: `SkeletonPurity` 13/13, Payments 21/21, Install 50/50, Docs/deploy guards verdes. Release tip publicado: tag `v1.2.1` @ `fba3e03`.

**Hallazgos nuevos:** 0 críticos, 1 medio (proceso cadena). **Deuda arrastrada:** 5 medios abiertos + ops Portal/Stripe.

---

## Hallazgos críticos

*Ningún hallazgo crítico nuevo en Framework `main` en esta corrida.*

### Deuda crítica arrastrada (estado actualizado)

| ID | Hallazgo | Estado 2026-07-30 | Owner |
|----|----------|-------------------|-------|
| C1 (2026-07-27) | Scripts `vps-deploy-*.sh` destructivos | **RESUELTO** — eliminados PR #36; `DeployScriptsRemovedTest` + suite Docs verdes | Framework |
| C2 (2026-07-27) / #21 | Stripe subscription C1–C6 | **Framework RESUELTO** — PR #42 + tag `v1.2.1` (issue #21 CLOSED). Gate ops `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` **sigue vigente** hasta QA humana en Portal | Framework ✅ / Portal ⏳ QA |
| C3 (2026-07-27) / #23 | Bootstrap marketing + migraciones | **Re-scopeado** — Portal; issue Framework #23 CLOSED. No aplica a Framework `main` | Portal |

---

## Hallazgos medios

### M7 (nuevo) — Artefacto de auditoría diaria cerrado sin merge → cadena sin ancla en `main`

| Campo | Valor |
|-------|-------|
| Evidencia | PR [#48](https://github.com/Parzival2103/Lebytek_Framework/pull/48) (`automation/audit-2026-07-29`) closedAt `2026-07-29T23:41:33Z`, `mergedAt=null`. Comentario owner: «Cerrado: continúa en #50». `docs/audits/` en `main` **no** contiene `2026-07-29-auditoria-tecnica-diaria.md`. |
| Impacto | Etapas posteriores (spec/plan) quedan sin artefacto mergeado canónico; deuda diaria no queda versionada en `main`; riesgo de pérdida de continuidad entre corridas. |
| Owner | Ops / maintainers Framework (proceso automation) |
| Acción | Definir política: mergear drafts `docs(audit):` a `main` tras revisión ligera, **o** documentar explícitamente que el PR draft abierto es la fuente canónica temporal. No cerrar el draft de audit al abrir el spec. |

### M1 — `config/app.php` version desincronizada del release semver (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivo | `config/app.php` |
| Evidencia | `'version' => '1.0.0'` mientras tags publicados llegan a `v1.2.1` (`fba3e03`) |
| Impacto | Dashboard/operadores pueden mostrar versión incorrecta |
| Owner | Framework |
| Estado | **Abierto** — sin cambio en `main` desde 2026-07-29. Relacionado con PR abierto [#50](https://github.com/Parzival2103/Lebytek_Framework/pull/50) (spec semver sync) y [#47](https://github.com/Parzival2103/Lebytek_Framework/pull/47) (inventario deuda) |

### M2 — `.env.example` root conserva variables Portal/Marketing (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivos | `.env.example` (root harness) |
| Evidencia | `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*` presentes; `skeleton/.env.example` limpio (`SkeletonPurityTest` PASS) |
| Impacto | Ruido en harness; confusión para nuevos tenants |
| Owner | Framework (harness) |
| Estado | **Abierto** |

### M3 — CRUD/Calendario sin `RbacMiddleware` a nivel router (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivo | `routes/web.php` L114–125 |
| Evidencia | Rutas `/crud/{resource}` y `/calendario/{key}` sólo heredan `AuthMiddleware` del grupo; RBAC dentro del servicio |
| Impacto | Defensa en profundidad inconsistente; 403 desde servicio, no middleware |
| Owner | Framework |
| Estado | **Abierto** — backlog riesgo bajo |

### M4 — API `/api/*` autenticada por sesión (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivo | `routes/api.php` |
| Evidencia | Grupo con `AuthMiddleware` (sesión); sin token API de plataforma |
| Owner | Framework |
| Estado | **Abierto** — documentar o añadir health público si se necesita |

### M5 — `permisos.gestionar` ausente en seeds (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Comentario en `routes/web.php` / `skeleton/routes/web.php`: se usa `administracion.ver` como workaround |
| Owner | Framework |
| Estado | **Abierto** |

### M6 — Portal SHA no inspeccionable desde agente cloud (arrastrado / entorno)

| Campo | Valor |
|-------|-------|
| Evidencia | `gh repo view` / `gh api` → 404; token `cursor` sin acceso al repo privado |
| Impacto | No se puede verificar SHA producción ni `composer.lock` (¿≥ v1.2.1?) en esta corrida |
| Owner | Ops / credenciales automation |
| Estado | **Abierto** — bloqueador de entorno, no defecto de código |

---

## Deuda arrastrada desde la auditoría anterior

Fuente primaria mergeada: `docs/audits/2026-07-27-auditoria-tecnica-diaria.md` (PR #37).  
Fuente draft no mergeada: rama `origin/automation/audit-2026-07-29` (PR #48 cerrado).

| Ítem | Origen | Estado hoy |
|------|--------|------------|
| C1 deploy scripts | 2026-07-27 | RESUELTO |
| C2 Stripe Framework | 2026-07-27 / #21 | Framework RESUELTO (v1.2.1); ops Portal pendiente |
| C3 marketing bootstrap | 2026-07-27 / #23 | Portal; cerrado en Framework |
| M1 version UI | 2026-07-29 draft | Abierto |
| M2 `.env.example` Marketing | 2026-07-27 | Abierto |
| M3 CRUD RBAC router | 2026-07-27 | Abierto |
| M4 API sesión | 2026-07-27 | Abierto |
| M5 `permisos.gestionar` | 2026-07-27 | Abierto |
| M6 Portal gh 404 | 2026-07-29 draft | Abierto |
| M7 audit PR cerrado sin merge | **nuevo 2026-07-30** | Abierto |

**No hay hallazgos nuevos de código** respecto a `0ec722b` (sin delta funcional).

---

## Ownership por repositorio

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `0ec722b` | Auth, RBAC, CRUD, install, Payments genérico, skeleton |
| Release semver | Tag `v1.2.1` @ `fba3e03` | Contrato Stripe subscription + install fixes |
| App desplegable | `Lebytek_Portal` / `main` | Marketing, leads, membresías, checkout UX, SQL `dom_*` |
| API WhatsApp | `WhatsApiLebytek` / `main` @ `b6c3773` | Green API, lifecycle instancias |
| Legacy (histórico) | `archive/backoffice-api-integration` @ `4789f95` | Evidencia migración — **no** base de trabajo |

**Dependencia cruzada:** habilitar `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` en Portal requiere tag Framework ≥ `v1.2.1` **y** QA del bump/`composer.lock` en Portal (referenciado al cierre de #21). Un fix Portal de subscriptions **depende** de ese tag ya publicado.

---

## Cambios recientes en `main` (desde auditoría consolidada 2026-07-27)

Sin commits nuevos entre 2026-07-29 y 2026-07-30. Intervalo `607a3c6`→`0ec722b` (ya auditado ayer):

| PR | Tema | Relevancia |
|----|------|------------|
| #36 | Delete `vps-deploy-*.sh` | Cierra C1 |
| #37 | Auditoría consolidada + `INSTALL_TOKEN` | Artefacto canónico en `main` |
| #38 | Preflight legacy-ref | Guarda automation |
| #40 | SqlFileRunner literales | Fix install seeds |
| #42 | Install resolve + Stripe v1.2.1 | Cierra Framework #21 |
| #43–#45 | ENVIRONMENTS + archive plans | Ops docs |
| #46 | Cadena diaria 6 etapas | Automation hardening |

PRs abiertos relevantes (no mergeados): [#47](https://github.com/Parzival2103/Lebytek_Framework/pull/47) inventario deuda; [#50](https://github.com/Parzival2103/Lebytek_Framework/pull/50) spec semver sync.

---

## Fronteras del paquete

Verificación estática + suites:

- `src/` — sin módulo Marketing/LebytekApi; `membresiaId` aparece sólo como metadata opcional en contrato Payments (`PaymentEvent` / `StripeGateway`) — frontera genérica aceptable.
- `config/vertical.php` y `skeleton/config/vertical.php` — `marketing=false`, `payments=false`.
- `SkeletonPurityTest` — **13/13 PASS**.
- `scripts/vps-deploy-*.sh` — **0 archivos**; Docs suite PASS.
- Payments — contrato en `src/Domain/Payments/` (`SupportsSubscriptions`, gateway interface); vertical OFF.

**Conclusión:** no se coló negocio Portal en Framework en este intervalo (delta vacío).

---

## Riesgo de deploy / release

| Riesgo | Severidad | Estado |
|--------|-----------|--------|
| Deploy desde rama legacy | Alta (histórico) | **Mitigado** — scripts eliminados; tag archivado; 53 commits no ancestros de HEAD |
| Stripe subscriptions sin QA Portal | Alta | **Mitigado** — checkout OFF; Framework v1.2.1 publicado; bump Portal no verificable aquí |
| Portal prod SHA desconocido | Media | **Bloqueador entorno** — gh 404 |
| Artefactos audit diarios no mergeados | Media | **M7** — cadena frágil |
| `skeleton.lebytek.com` no desplegado | Media | **Abierto** — `ENVIRONMENTS.md` |
| Staging Portal inexistente | Media | **Abierto** |
| Versión UI `1.0.0` vs tag `v1.2.1` | Baja | M1 |
| Fresh install SQL `;` en strings | Media | **Resuelto** PR #40 |

---

## Archivos involucrados

Sin delta de código desde `0ec722b`. Archivos re-inspeccionados para deuda abierta:

- `config/app.php` — version `1.0.0` (M1)
- `.env.example` — vars Marketing/API (M2)
- `routes/web.php` — CRUD/calendario sin RbacMiddleware; comentario `permisos.gestionar` (M3, M5)
- `routes/api.php` — Auth sesión (M4)
- `src/Domain/Payments/*`, `src/Infrastructure/Payments/StripeGateway.php` — contrato v1.2.1
- `config/vertical.php`, `skeleton/config/vertical.php` — flags OFF
- `tests/Docs/*`, `tests/SkeletonPurity*`, `tests/Payments/*`, `tests/Install/*`
- Artefacto nuevo: `docs/audits/2026-07-30-auditoria-tecnica-diaria.md` (este archivo)

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

### Comandos ejecutados

```console
$ php tests/run.php
569 passed, 7 failed
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
13 passed, 0 failed
exit code: 0
```

### Análisis de fallos

Los **7 fallos** son tests Integrations/WhatsApp que requieren MySQL vivo:

- `save + findById conserva datos...`
- `el token se guarda cifrado...`
- `markDefault deja solo una instancia...`
- `recent devuelve los últimos envíos...`
- `recent filtra por canal`
- `IntegrationsFactory::resolveWhatsappConfig...` (×2)

**Clasificación:** bloqueador de entorno (sin daemon MySQL), **no** defecto de código. Comparado con 2026-07-29 (fallos por `pdo_mysql` ausente): hoy el driver está; falta el servidor. Suites críticas de paquete (Kernel, Payments, SkeletonPurity, Install, Docs) **100% verdes**.

---

## Recomendación final

1. **Mergear o retener abierto** este PR draft de audit; no cerrarlo al abrir el spec del día (mitiga M7).
2. **Framework `main`:** estable; sin acción de código urgente. Priorizar M1 vía spec #50 / PR menor de sync semver.
3. **Portal:** operador con acceso debe confirmar SHA + `composer.lock` ≥ `v1.2.1` y QA Stripe antes de levantar `PAYMENTS_SUBSCRIPTION_CHECKOUT`.
4. **Automation:** conceder al token `gh` lectura de `Lebytek_Portal`; preinstalar PHP + Composer + MySQL (o marcar Integrations como skip sin DSN) en la imagen del agente.
5. **Próximo hito operativo:** `skeleton.lebytek.com` según `ENVIRONMENTS.md`.

**Veredicto:** paquete sano; **0 críticos nuevos**; **1 medio nuevo (proceso)**; release `v1.2.1` coherente; deuda de código concentrada en hygiene (version/env/RBAC) y verificación Portal/ops.

---

*Report-only. Ningún archivo de código, config, rutas, migraciones, scripts ni specs fue modificado en esta corrida.*
