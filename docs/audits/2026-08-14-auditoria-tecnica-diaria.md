# Auditoría técnica diaria — 2026-08-14

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `cea890e34d53ad2e400237136bc691a98e030511` |
| SHA Portal inspeccionado | **No disponible** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (repo privado o token sin acceso). No se inventa SHA. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde 2026-08-02) |
| Rama generada | `automation/audit-2026-08-14` |
| Timestamp UTC | trigger cron `2026-08-14T12:00:16Z` / corrida agente `2026-08-14T12:01:42Z` |
| Automation ID | `42e87765-8b95-11f1-b532-320a589b8025` |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
(ok; FETCH_EXIT=0; origin/main resuelve)

$ git rev-parse --verify origin/main
cea890e34d53ad2e400237136bc691a98e030511

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
- `refs/remotes/origin/feature/backoffice-api-integration` también resuelve al mismo SHA; no se usó (primer candidato ya resolvió).
- Commits exclusivos del legacy: **53** (`git rev-list --count origin/main..refs/tags/archive/backoffice-api-integration`).
- Comprobación de ancestros sobre **los 53** commits: **ninguno** es ancestro de `HEAD` (`LEGACY_CHECK=PASS`).

---

## Resumen ejecutivo

`origin/main` avanzó **1 commit** desde la auditoría diaria 2026-08-13 (que inspeccionó tip `dc587b9`): **PR `#122`** — merge del artefacto audit 2026-08-13. **Sin cambios de código de plataforma** en el delta.

Tip declara **`1.2.11`** (trío `composer.json` / `config/app.php` / `skeleton/config/app.php` sincronizado). Tag **`v1.2.11`** publicado @ `fe6adec`; los únicos archivos en `v1.2.11..origin/main` son `docs/audits/2026-08-12-auditoria-tecnica-diaria.md` y `docs/audits/2026-08-13-auditoria-tecnica-diaria.md` (avance docs-only; no exige tag nuevo). Árbol de código ≡ `v1.2.11`.

**Sin hallazgos nuevos** (0 críticos nuevos / 0 medios nuevos). Críticos de código en tip: **ninguno** (CRUD-C4 sigue cerrado vía `#118` / `v1.2.11`). Deuda abierta arrastrada: M5, M6, M10, M11, D6 (+ hygiene documental de planes M3/M4, `docs/release/v1.2.8.md` ausente, PRs residuales).

Verticals `marketing`/`payments`/`invoicing` = `false`; `STRIPE_ENABLED=false`, `FACTURAPI_ENABLED=false`. Suites aisladas verdes; suite completa local **802/9** (7 Integrations PDO MySQL env + 2 M11 session pollution). CI tip `main` **success** (run `31701756139` @ `#122`).

Cadena post-audit: PRs **`#121`** y **`#123`** (specs deuda post-v1.2.11 M11/M5/P-LOCK) OPEN — input de etapas siguientes, no defectos nuevos de plataforma.

---

## Hallazgos críticos

### Sin hallazgos críticos nuevos

No hay defectos críticos nuevos en tip `cea890e`.

### Críticos históricos — sin regresión

| ID | Estado 2026-08-14 |
|----|-------------------|
| CRUD-C4 CAS/TOCTOU | **RESUELTO** `#118` / `v1.2.11` |
| REL-C1 tags | **RESUELTO** — `v1.2.7`…`v1.2.11`; tip código ≡ tag |
| CRUD-C6 uploads | **RESUELTO** `v1.2.8`+ |
| INV-E1 / INV-E2 | **RESUELTO** `#109`/`#112` |
| CRUD-C1 / C2 / C5 / C3 | **RESUELTO** `#95`/`#100` |
| C1 deploy scripts / C2 Stripe FW / C3 marketing | sin cambio (C3 = Portal) |

**Críticos de código abiertos en Framework tip:** **ninguno**.

---

## Hallazgos medios

### Sin hallazgos medios nuevos

No se abren IDs medios nuevos en esta corrida. Se arrastra la deuda media abierta (abajo).

### M11 — Contaminación de sesión en `php tests/run.php` monolítico (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia tip `cea890e` | Suite completa: `AuthMiddleware blocks unauthenticated /api/ping` → expected 302 got 200; `Router dispatch… /api/ping` falla con sesión residual. `php tests/run.php Kernel` → **61/0 PASS**; CI `platform-fast-gates` success @ tip. Spec remedición abierto: `#121` / `#123`. |
| Impacto | Falso negativo local en suite monolítica; **no** regresión prod de M4. |
| Owner | Framework / test harness |
| Estado | **Abierto** |

### M5 — `permisos.gestionar` ausente en seeds (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Workaround `administracion.ver` en `routes/web.php` / skeleton; comentario explícito; `rg permisos.gestionar database/` → 0. Spec remedición: `#121` / `#123`. |
| Owner | Framework |
| Estado | **Abierto** |

### M6 — Portal SHA no inspeccionable (arrastrado / entorno)

| Campo | Valor |
|-------|-------|
| Evidencia | `gh` → 404 / GraphQL fail sobre `Lebytek_Portal` (reconfirmado 2026-08-14) |
| Owner | Ops / credenciales automation |
| Estado | **Abierto** |

### M10 — Huecos de auditorías diarias (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | Ausentes `2026-08-0{3,4,5}-auditoria-tecnica-diaria.md` y `2026-08-10`; cadena 06–09 + 11–**14** presente. Sin hueco nuevo hoy. |
| Owner | Ops / automation |
| Estado | **Abierto** |

### D6 — `skeleton.lebytek.com` pendiente (arrastrado)

| Campo | Valor |
|-------|-------|
| Evidencia | `docs/ENVIRONMENTS.md` — skeleton pendiente; crm.lebytek.com documentado live |
| Owner | Ops |
| Estado | **Abierto** |

### M3 / M4 — resueltos (sin regresión; hygiene de plan)

| ID | Estado |
|----|--------|
| M3 `CrudRbacMiddleware` | **RESUELTO** `#114` / `v1.2.10` — plan checkboxes siguen 0/N |
| M4 `/api/health` | **RESUELTO** `#114` / `v1.2.10` — plan checkboxes siguen 0/N |

### M1 / M2 / M7 / M8 / M9 / D7 — históricos resueltos (sin regresión)

| ID | Estado |
|----|--------|
| M1 semver trío | **RESUELTO** — tip `1.2.11` sync; Docs `PlatformVersionSemverTest` PASS |
| M2 / M7 / M8 / M9 / D7 | **RESUELTO** — sin regresión; `composer audit --no-dev` limpio |

Hygiene: `composer validate --no-check-publish` exit **2** (lock-not-up-to-date); CI usa `--no-check-lock`. Falta `docs/release/v1.2.8.md` — baja. PRs abiertos: `#121`/`#123` (specs post-audit), `#119` (docs evaluación), `#117`/`#116` (specs residuales post-`#118`) — proceso, no defectos de plataforma.

**Medios nuevos esta corrida:** ninguno.

---

## Deuda arrastrada desde la auditoría anterior

Fuente mergeada: `docs/audits/2026-08-13-auditoria-tecnica-diaria.md` (PR `#122` @ `cea890e`) + audit crítica CRUD `#90`.

| Ítem | Origen | Estado hoy |
|------|--------|------------|
| C1 deploy scripts | 2026-07-27 | RESUELTO |
| C2 Stripe Framework | 2026-07-27 / #21 | Framework RESUELTO; ops Portal N/V |
| C3 marketing bootstrap | 2026-07-27 / #23 | Portal |
| CRUD-C1 / C2 / C5 AuthZ | #90 | **RESUELTO** tip + tags |
| CRUD-C3 states form | #90 | **RESUELTO** tip + tags |
| CRUD-C4 CAS/TOCTOU | #90 | **RESUELTO** `#118` / `v1.2.11` |
| CRUD-C6 uploads | #90 | **RESUELTO** `#111` / `v1.2.8`+ |
| REL-C1 tags release | 2026-08-08 | **RESUELTO** — `v1.2.7`…`v1.2.11` |
| INV-E1 / INV-E2 | #101 | **RESUELTO** `#109`/`#112` |
| M1–M2 / M7–M9 / D7 | previos | RESUELTOS |
| M3 CRUD RBAC router | 2026-07-27 | **RESUELTO** `#114` / `v1.2.10` (plan checkboxes sin marcar) |
| M4 API health | 2026-07-27 | **RESUELTO** `#114` / `v1.2.10` (plan checkboxes sin marcar) |
| M5 `permisos.gestionar` | 2026-07-27 | **Abierto** (spec `#121`/`#123`) |
| M6 Portal gh 404 | 2026-07-29 | **Abierto** (entorno) |
| M10 hueco audits | 2026-08-06 | **Abierto** — 03–05 + 10 |
| D6 skeleton.lebytek.com | inventario | **Abierto** |
| M11 suite sesión | 2026-08-11 | **Abierto** (spec `#121`/`#123`) |

---

## Ownership por repositorio

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `cea890e` | Auth, RBAC, CRUD Engine (incl. CAS), Invoicing vertical, Payments genérico, install, skeleton, CI |
| Release semver | Tags `v1.2.7`…`v1.2.11`; tip declara `1.2.11` (código ≡ tag; tip tree + audit docs) | Mantener tip↔tag al siguiente patch (p. ej. `v1.2.12` si se shippea M5) |
| App desplegable | `Lebytek_Portal` / `main` | Marketing, leads, membresías, checkout UX, SQL `dom_*`, wiring Facturapi routes/RBAC, gate `PAYMENTS_SUBSCRIPTION_CHECKOUT`, bump `composer.lock` |
| CRM | `Lebytek_CRM` (doc) | Consume Framework vía lock |
| API WhatsApp | `WhatsApiLebytek` / `main` @ `f3f3ec7` | Green API / lifecycle |
| Legacy | `archive/backoffice-api-integration` @ `4789f95` | Histórico — no base |

**Dependencias cruzadas:**

- AuthZ/states/C6/M3/M4/Facturapi hardening/CAS (C4): **ya tagueados** hasta `v1.2.11`. Portal/CRM **dependen** de bump `composer.lock` ≥ **`v1.2.11`** (P-LOCK en spec `#121`). Confirmación del lock Portal bloqueada por M6.
- Invoicing: plataforma lista; habilitación + `InvoiceableSource` + rutas RBAC = consumidor. No activar en prod sin wiring Portal.
- `mkt_leads` afterListRows: Portal (plan 0/5) — Framework ≥ `v1.2.2` ya superado por tags actuales.
- Stripe QA: Portal/VPS.
- M11/M5: fix Framework (spec `#121`/`#123`); M5 implica tag consumidor `≥ v1.2.12` cuando shippee.

---

## Cambios recientes en `main` (desde auditoría 2026-08-13 @ tip `dc587b9`)

| Commit / PR | Tema | Relevancia |
|-------------|------|------------|
| `cea890e` / #122 | Auditoría diaria 2026-08-13 | Único avance; docs-only; cierra cadena audit previa |

PRs abiertos Framework: **5** (`#123` / `#121` specs post-v1.2.11, `#119` docs evaluación, `#117` draft dup, `#116` spec residual). Issues abiertos Framework: **0**.

---

## Fronteras del paquete

- `src/` — sin `LebytekApiClient`, sin `App\Domain\Marketing`, sin módulos PHP de negocio Portal.
- `config/vertical.php` / skeleton — `marketing=false`, `payments=false`, `invoicing=false`; `STRIPE_ENABLED=false`, `FACTURAPI_ENABLED=false`.
- Payments genérico: `src/Domain/Payments/` intacto (`PaymentGatewayInterface`, `SupportsSubscriptions`, VOs, event log). Campos opcionales `membresiaId` en `PaymentEvent` / Stripe metadata son contrato genérico de gateway, no UI/checkout Portal.
- Invoicing: capas Domain/Application/Infrastructure + SQL módulo — plataforma; consumidor aporta source/RBAC/UI.
- Referencias `mkt_leads` residuales solo en docs/specs y scripts ops históricos `scripts/vps-{setup,fix,finalize}-lebytek*.sh` (no son `vps-deploy-*`; C1 deploy scripts siguen ausentes). No hay código Marketing en `src/` ni seed de negocio Portal.
- `SkeletonPurityTest` — **13/13 PASS**.
- `scripts/vps-deploy-*.sh` — **0 archivos**.
- Root `.env.example`: sin `MKT_*` / `LEBYTEK_API_*` / `PAYMENTS_SUBSCRIPTION_CHECKOUT`.

**Conclusión:** no se coló negocio Portal. El delta `#122` es solo el reporte de auditoría. **Sin hallazgos nuevos de frontera.**

---

## Riesgo de deploy / release

| Riesgo | Severidad | Estado |
|--------|-----------|--------|
| Tip sin tag Composer | Alta | **Mitigado** — `v1.2.11` publicado; código tip ≡ tag (delta tip = audit docs) |
| CRUD-C4 TOCTOU transitions | Alta | **Mitigado** — `#118` / `v1.2.11` |
| CRUD-C6 uploads | Alta | **Mitigado** — `v1.2.8`+ |
| Doble timbrado Facturapi | Alta si se habilita | **Mitigado en código** + vertical OFF |
| Portal/CRM sin bump a ≥`v1.2.11` | Alta (consumo) | Depende ops consumidores + M6 — **riesgo dominante** (P-LOCK) |
| Deploy desde rama legacy | Alta (histórico) | **Mitigado** — 53 commits no ancestros |
| Stripe sin QA Portal | Alta | Mitigado Framework OFF |
| Suite monolítica local engañosa (M11) | Media (DX) | Abierto; CI por jobs OK; specs `#121`/`#123` |
| `skeleton.lebytek.com` | Media | D6 |
| Huecos audits 03–05 + 10 | Media (proceso) | M10 |
| Portal prod SHA desconocido | Media | M6 |
| Planes M3/M4 checkboxes en 0 | Baja | Drift documental |
| Falta `docs/release/v1.2.8.md` | Baja | Hygiene |
| `composer validate` lock warning (exit 2) | Baja | CI `--no-check-lock` |
| PRs draft residuales `#116`/`#117` | Baja | Cerrar/abandonar tras ship `#118` |
| Specs `#121`/`#123` pendientes de merge | Baja (proceso) | Cadena plan/executor |

---

## Archivos involucrados

Delta `dc587b9..cea890e` (PR `#122`):

- Artefacto previo: `docs/audits/2026-08-13-auditoria-tecnica-diaria.md`
- Artefacto nuevo: `docs/audits/2026-08-14-auditoria-tecnica-diaria.md` (este archivo)

Re-inspeccionados sin hallazgo de frontera ni regresión: trío semver `1.2.11`, tag `v1.2.11`, `config/vertical.php` / skeleton vertical, `.env.example`, `src/Domain/Payments/*`, `routes/web.php` (M5 workaround), `docs/ENVIRONMENTS.md` (D6), CI tip green.

---

## Evidencia de verificación

### Entorno

| Componente | Estado |
|------------|--------|
| PHP CLI | Ausente al inicio → instalado ad-hoc `php8.3-cli` (+ xml, mbstring, curl, sqlite) — **PHP 8.3.6** |
| Composer / vendor | Ausente → `/tmp/composer.phar` 2.10.2 + `composer install`; **sin** `composer.phar` en el árbol del repo |
| `ext-pdo_mysql` | **Ausente** en esta corrida (`php -m` → PDO + pdo_sqlite solamente) |
| Servidor MySQL / driver | **Bloqueador de entorno** — 7 tests Integrations fallan con `could not find driver` |
| Acceso Portal (`gh`) | **404** — bloqueador entorno (M6) |
| GitHub Actions tip | **success** @ `cea890e` (run `31701756139`; jobs `platform-fast-gates` + `platform-integration-gates`) |
| Issues / PRs abiertos Framework | **0** / **5** (`#123`, `#121`, `#119`, `#117`, `#116`) |

### Comandos ejecutados

```console
$ php tests/run.php
802 passed, 9 failed
exit code: 1

$ php tests/run.php Kernel
61 passed, 0 failed
exit code: 0

$ php tests/run.php Payments
21 passed, 0 failed
exit code: 0

$ php tests/run.php SkeletonPurity
13 passed, 0 failed
exit code: 0

$ php tests/run.php Install
52 passed, 0 failed
exit code: 0

$ php tests/run.php Docs
51 passed, 0 failed
exit code: 0

$ php tests/run.php Crud
251 passed, 0 failed
exit code: 0

$ php tests/run.php Invoicing
112 passed, 0 failed
exit code: 0

$ php tests/run.php Security
38 passed, 0 failed
exit code: 0

$ php tests/run.php Reporte
56 passed, 0 failed
exit code: 0

$ php tests/run.php Archivos
22 passed, 0 failed
exit code: 0

$ php tests/run.php Auth
52 passed, 0 failed
exit code: 0

$ php tests/run.php Integrations
46 passed, 7 failed
exit code: 1

$ php /tmp/composer.phar audit --no-dev
No security vulnerability advisories found.
exit code: 0

$ php /tmp/composer.phar validate --no-check-publish
./composer.json is valid, but with a few warnings
# Lock file errors — The lock file is not up to date…
exit code: 2
```

Contadores: suite completa **802 passed / 9 failed** (sin cambio vs 2026-08-13). Suites Kernel/Payments/SkeletonPurity/Install/Docs/Crud/Invoicing/Security/Reporte/Archivos/Auth **verdes** en aislamiento. Ningún comando descubrió cero tests.

### Análisis de fallos

**7 fails de entorno (Integrations / `ext-pdo_mysql` ausente):**

- `save + findById…`, token cifrado, `markDefault…`, `recent…` (×2), `IntegrationsFactory::resolveWhatsappConfig…` (×2) — todos `Error de conexión a la base de datos: could not find driver`

**2 fails de aislamiento (M11) solo en suite monolítica:**

- `AuthMiddleware blocks unauthenticated /api/ping` — expected 302 got 200
- `Router dispatch does not return 200 JSON ok for /api/ping without session`

**Clasificación:** bloqueadores de entorno local (PDO MySQL) + hallazgo medio de harness arrastrado (M11). CI tip green cubre Kernel/Docs/Integrations por jobs separados. **No** son PASS de código ni fallos de regresión prod.

---

## Recomendación final

1. **Mergear** este PR draft de audit cuando AUTOMATION-03 lo procese (Enfoque B); no cerrarlo sin `mergedAt`.
2. **Portal/CRM ops:** bump `composer.lock` a **`v1.2.11`** (o ≥) para absorber CAS/C4 + AuthZ/states/C6/Facturapi/M3/M4 (P-LOCK). Verificación del SHA Portal sigue bloqueada por M6 — conceder lectura `gh` al token de automation.
3. **Cadena specs `#121`/`#123`:** continuar plan/executor para M11 + M5 (+ tag `v1.2.12` si M5 shippea); no duplicar issues; consolidar specs duplicados si ambos siguen abiertos.
4. **Harness:** resetear sesión entre tests/archivos (M11) para que `php tests/run.php` monolítico no mienta.
5. **Docs/proceso hygiene:** marcar checkboxes de planes M3/M4 ya shippeados; añadir `docs/release/v1.2.8.md` si se quiere paridad; cerrar o abandonar drafts `#116`/`#117` tras ship `#118`.
6. **M5:** seed `permisos.gestionar` o documentar el workaround como permanente (vía `#121`/`#123`).
7. **No habilitar** Facturapi en prod hasta wiring + QA Portal (código Framework listo; vertical sigue OFF).
8. **Automation:** no omitir paso 00 (M10 — huecos 03–05 + 10 siguen); instalar `php-mysql` / MySQL local o confiar en CI integration.

**Veredicto:** día **quieto / sin hallazgos nuevos**. Único avance en `main` = merge del audit `#122`. Tip código sigue en **`1.2.11`** con críticos cerrados. Deuda abierta = medios/proceso/entorno (M5/M6/M10/M11/D6). Fronteras FPS sanas. Riesgo dominante de release: **consumidores sin bump de lock a ≥`v1.2.11`**. Portal SHA no inspeccionable.

---

*Report-only. Ningún archivo de código, config, rutas, migraciones, scripts ni specs fue modificado en esta corrida.*
