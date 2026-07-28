# Design: Purga de variables Portal en harness `.env.example` y señales de deploy legacy

**Fecha:** 2026-07-27  
**Repo:** `Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación; deuda técnica + pase UX auditados 2026-07-27 (agentes cron)  
**Auditoría fuente:** `docs/audits/2026-07-27-auditoria-tecnica-diaria.md` (versión **consolidada y corregida**)  
**Rama base de trabajo:** `main` @ `607a3c6` (package FPS post-PR #26)  
**Rama spec:** `automation/audit-spec-2026-07-27`  

> ## ⚠️ Premisa corregida — leer antes que el resto del documento
>
> Este spec fue redactado por un agente cloud **sin acceso al VPS**, y su premisa
> de partida —«el ecosistema sigue en riesgo operativo alto por la divergencia
> VPS/feature vs package source», con `feature/backoffice-api-integration` @
> `4789f95` como rama desplegada— es **falsa**.
>
> Verificado por SSH el 2026-07-27: `lebytek.com` y `waapi.lebytek.com` corren
> ambos `Parzival2103/Lebytek_Portal@main` @ `a79d3ad`, árbol limpio, consumiendo
> `lebytek/framework` **v1.1.0 como paquete Composer**. El cutover ya ocurrió y
> ninguna rama de Framework se despliega en ningún host.
>
> **Qué sigue siendo válido:** el análisis de deuda técnica del harness
> `.env.example` (D1–D14) y el pase UX (K1–K7, U1–U9, R1–R7). Esos hallazgos son
> sobre el contenido del repositorio y no dependen de qué corre en el servidor.
>
> **Qué queda invalidado:** toda afirmación sobre el estado de producción, el
> «cutover estancado», y las secciones que tratan `feature/backoffice-api-integration`
> como base de trabajo o destino de PR. Los `scripts/vps-deploy-*.sh` que el
> documento analiza **fueron eliminados** en
> [PR #36](https://github.com/Parzival2103/Lebytek_Framework/pull/36).
>
> Se conserva el documento por su análisis de deuda técnica, no como descripción
> del estado operativo.

**Specs relacionados:**  
- `docs/superpowers/specs/2026-07-24-audit-vps-cutover-fps-design.md` (cutover objetivo)  
- `docs/superpowers/specs/2026-07-25-audit-harness-env-health-design.md` (`INSTALL_TOKEN`, health API)  
- `docs/superpowers/specs/2026-07-26-audit-portal-cutover-stale-design.md` (estancamiento cutover + hardening M1)

---

## Problema

La auditoría del 2026-07-27 confirma que **`main` permanece estable** tras la consolidación FPS (6 días sin commits; merge PR #26 el 2026-07-21), pero el ecosistema sigue en **riesgo operativo alto** por la divergencia VPS/feature vs package source. En el ámbito **implementable en Framework `main` sin cutover**, el hallazgo principal accionable es la **inconsistencia del harness root `.env.example` post-FPS**:

| ID | Hallazgo | Evidencia | Impacto |
|----|----------|-----------|---------|
| **M2** | Root `.env.example` conserva vars Portal/Marketing | `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*` en L53–100; `skeleton/.env.example` ya limpio | Mantenedores del harness copian vars obsoletas; confunde package source con Portal |
| **M1** | `INSTALL_TOKEN` ausente (parcialmente resuelto) | PR #33 añade `INSTALL_TOKEN=` en root + skeleton | Q1 cerrado en PR auditoría; operador aún debe generar token en prod |
| **Q4** | Script deploy legacy sin deprecación explícita | `scripts/vps-deploy-lebytek-com.sh` → `BRANCH=feature/backoffice-api-integration` | Ops puede creer que feature es target final |
| **M4** | `/api/ping` requiere sesión | `routes/api.php` — no health check externo | Monitoreo VPS/skeleton sin cookie no funciona |
| **M3** | RBAC implícito en CRUD/calendario | `CrudResourceService` / `CalendarViewModelBuilder` vs middleware explícito en otras rutas | Defensa en profundidad aceptable; inconsistencia documental |
| **M5** | Slug `permisos.gestionar` inexistente | `routes/web.php` usa `administracion.ver` como workaround | Deuda RBAC conocida |

**Contexto de riesgo no resuelto (no auto-fix — issues abiertos):**

| ID | Hallazgo | Issue | Acción |
|----|----------|-------|--------|
| **C1** | VPS despliega monolito feature, no FPS | — | Cutover humano `docs/CUTOVER-PORTAL.md` |
| **C2** | Stripe subscription gaps | **#21** | Mantener checkout subscription OFF en VPS |
| **C3** | Bootstrap marketing + migraciones incompletas | **#23** | Re-scope a `Lebytek_Portal` + feature pinneada |

**Gap de tests:** `SkeletonPurityTest` ya exige que `skeleton/.env.example` **no** contenga `LEBYTEK_API_*`, pero **`FrameworkRootNotPortalTest` no valida el root `.env.example`**. El drift M2 puede reintroducirse sin fallo en CI.

---

## Comportamiento esperado

### Fase 1 — Purga harness `.env.example` (prioridad inmediata)

1. **Root `.env.example`** alinea con `skeleton/.env.example` en vars de plataforma: conserva DB, mail, auth, seguridad, integraciones Green API genéricas, payments OFF; **elimina** bloques Portal/Marketing:
   - `MKT_EMAIL_*`, `MKT_ALERT_*`, `MKT_PURCHASE_*`, `MKT_BANK_*`, `MKT_MEMBERSHIP_*`
   - `LEBYTEK_API_*` (contrato waapi — vive en Portal)
   - `WAAPI_PORTAL_*`
2. Vars eliminadas se **documentan por referencia** en comentario breve apuntando a `Lebytek_Portal/.env.example` (sin copiar valores).
3. **`INSTALL_TOKEN`** permanece documentado (ya en PR #33); no regresionar.
4. **`FrameworkRootNotPortalTest`** (o test hermano `HarnessEnvExamplePurityTest`) falla si root `.env.example` contiene prefijos prohibidos: `MKT_`, `LEBYTEK_API_`, `WAAPI_PORTAL_`.
5. **`php tests/run.php FrameworkRootNotPortal`** (y suite completa) pasa en entorno con PHP.

### Fase 2 — Señales deploy legacy (docs, bajo riesgo)

1. Header de `scripts/vps-deploy-lebytek-com.sh` incluye banner **`DEPRECATED — ver docs/CUTOVER-PORTAL.md`** y fecha FPS.
2. `docs/integration/VPS_CHECKLIST.md` (si existe en rama) marca feature branch como **interino**, no target final.
3. No cambiar `BRANCH=` ni lógica de deploy en esta fase — solo señalización humana.

### Fase 3 — Observabilidad API (opcional, spec 2026-07-25 Fase 2)

1. Ruta pública **`GET /api/health`** responde `200` JSON mínimo (`{"status":"ok"}`) sin sesión.
2. `/api/ping` mantiene comportamiento actual (auth por sesión) por compatibilidad harness.
3. Documentar en `docs/core/despliegue-y-versionado.md` cuál usar para monitoreo externo.

### Estado interino VPS (sin cambio)

- Cutover Portal **deferred**; issues **#21** / **#23** siguen siendo gates duros.
- `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` en VPS hasta cierre #21.
- Ningún merge `feature/backoffice-api-integration` → `main` sin orden explícita.

---

## Enfoques considerados

### Enfoque A — Purga directa + test gate (recomendado)

Eliminar vars Portal del root `.env.example`, extender `FrameworkRootNotPortalTest` con aserciones de prefijos, banner deprecated en script VPS.

| Pros | Contras |
|------|---------|
| Alinea harness con FPS; cierra gap vs `SkeletonPurityTest` | Requiere confirmar que ningún test harness root depende de vars `MKT_*` |
| Diff pequeño, reversible | Mantenedores con `.env` local deben migrar manualmente vars a Portal |
| Automatizable en un PR único | No resuelve cutover VPS |

### Enfoque B — Sección comentada “MOVED TO PORTAL” sin eliminar keys

Mantener keys pero comentadas con `# PORTAL ONLY — see Lebytek_Portal`.

| Pros | Contras |
|------|---------|
| Menos sorpresa para quien busca una var | Sigue contaminando plantilla; tests deben ignorar comentarios |
| Documentación inline | Contradice espíritu FPS “root not portal” |

### Enfoque C — Archivo separado `.env.example.portal-legacy`

Mover vars a `.env.example.portal-legacy` fuera del harness default.

| Pros | Contras |
|------|---------|
| Preserva referencia histórica | Nuevo archivo fuera de convención; riesgo de uso accidental |
| Root limpio | Más superficie de mantenimiento |

**Recomendación: Enfoque A.** Es consistente con `SkeletonPurityTest`, `FrameworkRootNotPortalTest` y `docs/PACKAGE-ROOT.md`. La referencia a Portal vive en comentario + doc, no en plantilla activa.

---

## Diseño técnico (implementación futura)

### 1. Inventario de vars a eliminar del root `.env.example`

```
MKT_EMAIL_DOCS_URL
MKT_EMAIL_DASHBOARD_URL
LEBYTEK_API_URL
LEBYTEK_API_TOKEN
LEBYTEK_API_TIMEOUT
LEBYTEK_API_RETRY_MAX
LEBYTEK_API_RETRY_DELAY_MS
WAAPI_PORTAL_ENABLED
MKT_ALERT_WHATSAPP_NUMBERS
MKT_PURCHASE_ALERT_WHATSAPP_NUMBERS
MKT_BANK_NAME
MKT_BANK_BENEFICIARY
MKT_BANK_CLABE
MKT_BANK_ACCOUNT
MKT_BANK_PROOF_GUIDE
MKT_MEMBERSHIP_AUTHORIZE_ENABLED
```

**Conservar en root:** `GREEN_API_*`, `INTEGRATIONS_API_DOCS_URL`, `PAYMENTS_*` (plataforma OFF), `INSTALL_TOKEN`, auth/mail/DB estándar.

### 2. Test gate propuesto

Añadir en `tests/Kernel/FrameworkRootNotPortalTest.php`:

```php
test('framework root .env.example does not ship Portal or Marketing env vars', function () use ($root): void {
    $envExample = (string) file_get_contents($root . '/.env.example');
    foreach (['MKT_', 'LEBYTEK_API_', 'WAAPI_PORTAL_'] as $prefix) {
        assert_true(!str_contains($envExample, $prefix), ".env.example must not contain {$prefix}");
    }
});
```

### 3. Comentario de redirección (ejemplo)

```dotenv
# Variables de Marketing, Lebytek API client y portal waapi:
# ver Lebytek_Portal/.env.example (no aplican al package source / harness).
```

### 4. Banner script VPS (ejemplo)

```bash
# DEPRECATED (FPS 2026-07): despliegue interino monolito feature.
# Target producción: Lebytek_Portal + composer lebytek/framework.
# Ver docs/CUTOVER-PORTAL.md — NO usar para nuevos tenants skeleton.
```

### 5. `/api/health` (Fase 3 — sketch)

- Ruta en `routes/api.php` fuera del grupo con `AuthMiddleware`.
- Controlador mínimo o closure en router; sin DB ping en v1 (YAGNI).
- Test feature: `GET /api/health` → 200 sin cookie.

---

## Alcance

### Incluido

- Diseño de purga M2/Q3 en root `.env.example`.
- Test gate en `FrameworkRootNotPortalTest` (o test dedicado).
- Señalización deprecated en `vps-deploy-lebytek-com.sh` (Q4).
- Diseño opcional `/api/health` (M4) como Fase 3.
- Referencias cruzadas a PR #33, issues #21/#23, specs 2026-07-24/25/26.

### Fuera de alcance (no-alcance)

- Implementación de código en `app/` o `src/` en esta automatización.
- Cutover VPS, deploy, SSH, DNS, migraciones prod, `.env`/secretos en servidores.
- Fixes Stripe (#21), bootstrap leads (#23), CRUD `mkt_ordenes.status` — Portal/feature.
- Purga de vars en `.env` real de operadores (solo plantilla `.env.example`).
- Cierre del draft de auditoría ni apertura del PR spec final (automation UX posterior).
- Merge `feature/backoffice-api-integration` → `main`.
- RBAC middleware explícito en CRUD/calendario (M3) ni slug `permisos.gestionar` (M5) — backlog separado.
- Desactivar RBAC, CSRF, rate limits, firmas webhook, ni tests de seguridad.
- CI GitHub Actions nuevo — documentar como follow-up si no existe runner PHP.
- Purga de vars en `.env.example` sobre rama **`feature/backoffice-api-integration`** — esa rama aún tiene tests Marketing que **exigen** `MKT_*` en plantilla (`tests/Marketing/RoutesWiringTest.php` L62–72); la purga aplica solo a **`main`** post-FPS.
- Path traversal en config loaders (`CrudConfigLoader`, `CalendarConfigLoader`, `ReporteConfigLoader`) — backlog spec 2026-07-26 D4; no mezclar con PR de purga env.
- Fail-fast en scripts deploy (`|| true`, migraciones silenciadas) — spec cutover 2026-07-26; solo banner deprecated en Fase 2 aquí.

---

## Deuda técnica

Inventario verificado en rama `automation/audit-spec-2026-07-27` (base `feature/backoffice-api-integration` @ `4789f95`) contra auditoría [PR #33](https://github.com/Parzival2103/Lebytek_Framework/pull/33) (`docs/audits/2026-07-27-auditoria-tecnica-diaria.md`) y delta con `main` @ `607a3c6`. **Ningún ítem se auto-fixea en esta automatización** — queda documentado como requisito del spec/PR/issue.

### D1 — Drift harness `.env.example` root vs skeleton (M2 — foco del spec)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| Root `.env.example` L54–100: **16 keys activas** con prefijos `MKT_*` (L54–55, L90–100), `LEBYTEK_API_*` (L73–77), `WAAPI_PORTAL_*` (L80) | Mantenedores del harness copian contrato Portal/waapi al package source | Fase 1: purga Enfoque A + comentario redirección a `Lebytek_Portal/.env.example` |
| `skeleton/.env.example` **sin** esos prefijos; `SkeletonPurityTest` L45–49 en `main` lo valida | Asimetría root↔skeleton; FPS “root not portal” incumplido en plantilla | Extender `FrameworkRootNotPortalTest` con aserción `.env.example` (ver §D2) |
| `main` @ `607a3c6`: mismo drift que feature en root `.env.example`; PR #33 añade `INSTALL_TOKEN` pero **no** purga M2 | Drift persiste tras merge parcial de auditoría | PR implementación Fase 1 hacia `main`; no esperar cutover VPS |

### D2 — Gap test gate `.env.example` root (hallazgo nuevo 2026-07-27)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `tests/Kernel/SkeletonPurityTest.php` (main) — aserción `LEBYTEK_API_*` solo en **skeleton** | Skeleton protegido; root desprotegido | Añadir test hermano en `FrameworkRootNotPortalTest.php` |
| `tests/Kernel/FrameworkRootNotPortalTest.php` (main) — valida dirs/rutas/`vertical.php`/`PACKAGE-ROOT.md`; **no** lee `.env.example` | Reintroducción M2 no falla CI | Test propuesto en §Diseño técnico §2 del spec |
| Prefijos prohibidos propuestos: `MKT_`, `LEBYTEK_API_`, `WAAPI_PORTAL_` | Debe alinearse con inventario §1 | Excluir comentarios de redirección (solo keys `NAME=` activas) |

### D3 — `INSTALL_TOKEN` documentado pero no en `main` (M1 / Q1)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `public/install/index.php` L56–60 (main) exige `INSTALL_TOKEN` en producción | Wizard bloqueado si operador no conoce la var | Merge humano PR #33 (añade `INSTALL_TOKEN=` root + skeleton) |
| PR #33 **draft** — root `.env.example` aún contiene bloques Portal post-fix | Q1 cerrado en rama auditoría; M2 sigue abierto | Separar merge doc Q1 de PR purga M2 |
| Root `.env.example` en rama agente (`4789f95`): **sin** `INSTALL_TOKEN` | Harness local sin guía de token | No regresionar al implementar Fase 1 |

### D4 — Cutover VPS estancado — segunda semana (C1)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `main`: **0 commits** en ventana 24 h (2026-07-27); **6 días** desde merge FPS PR #26 | Estabilidad package source OK; ops sin avance cutover | Escalación humana; fecha revisión en checklist |
| `git rev-list --left-right --count main...feature/backoffice-api-integration` → **46** main-only / **53** feature-only | Drift crece; confusión sobre SoT | `docs/CUTOVER-PORTAL.md`; no merge feature→main |
| `scripts/vps-deploy-lebytek-com.sh` L6 — `BRANCH=feature/backoffice-api-integration`; L23 — `sed` fuerza `marketing => true` | lebytek.com = monolito pre-FPS | Fase 2: banner deprecated; pin SHA documentado |

### D5 — Bootstrap / schema drift (#23 — feature/VPS, no `main`)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `database/schema/modules/marketing.sql` (feature) — `dom_mkt_leads` sin columnas API lifecycle/churn que escriben repos PHP | Fresh install / bootstrap → fallos en provisioning | Re-scope **`Lebytek_Portal`** + feature pinneada; issue **#23** |
| `config/modules/marketing.php` (feature) — manifiesto 15 migraciones; **`20260706120200_rep_churn_metrics.sql` ausente** | `scripts/compute-churn-snapshot.php` sin DDL garantizado | Añadir al manifiesto Portal/feature |
| Colisión timestamp: `20260715120000_mkt_landing_experiments.sql` + `20260715120000_mkt_ordenes_stripe.sql` | Orden apply no determinístico | Renumerar una migración pre-cutover |
| `main`: marketing eliminado (`FrameworkRootNotPortalTest`); **3** migraciones plataforma vs **14+** en feature | Confusión sobre qué SQL aplica al harness | Ninguna acción marketing en `main` |

### D6 — Scripts deploy silencian fallos (Q4 contexto, heredado spec 2026-07-26)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `scripts/vps-deploy-lebytek-com.sh` L56 — `migrate.php 2>/dev/null \|\| true` | Errores migración invisibles | Fail-fast en spec cutover; aquí solo banner Fase 2 |
| L58–71 — bucle `202606*.sql` / `202607*.sql` con `\|\| echo "migration skipped"` | Schema parcial persistente | Checklist VPS post-deploy: `\d dom_mkt_leads` |
| `scripts/vps-fix-lebytek-db.sh` — apply SQL con `\|\| true` (si existe en rama) | Parches ops tragan errores | Documentar en checklist; no auto-fix spec env |

### D7 — API health / monitoreo (M4)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `routes/api.php` L14–24 — grupo `/api` con `AuthMiddleware`; `/api/ping` → `HealthController::ping` | Monitoreo externo sin cookie **no funciona** | Fase 3 opcional: `GET /api/health` fuera del grupo auth |
| `src/Presentation/Controllers/Api/HealthController.php` — solo método `ping` | Reutilizable para health público con ruta separada | Sketch en §Diseño técnico §5 |
| Comentario L11: “Autenticación futura mediante token” | Deuda API sin diseño token | Fuera de alcance Fase 1–2 |

### D8 — RBAC inconsistencias (M3, M5)

| Evidencia | Impacto | Capa | Acción requerida |
|-----------|---------|------|------------------|
| `/admin/crud/{resource}`, `/admin/calendario/{key}` — solo `AuthMiddleware` en router; RBAC en `CrudResourceService` / `CalendarViewModelBuilder` | 403 vía servicio, no middleware — defensa aceptable pero inconsistente | Application | Backlog; no bloqueante purga env |
| `routes/web.php` — slug `permisos.gestionar` **inexistente**; rutas permisos usan `administracion.ver` | Roles amplios gestionan permisos RBAC | Presentation | Issue alineación; ref. `docs/audits/correccion_alineacion_modulos_v0.1.md` |

### D9 — Checklist VPS / docs ops incompletos

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `docs/integration/VPS_CHECKLIST.md` L89 — `Branch: feature/backoffice-api-integration (until merge)` sin fecha ni SHA | Ops trata feature como target final | Fase 2: marcar **interino/deferred** + `@4789f95` + fecha revisión |
| L16–17, L118 — cron health cada 5 min **pendiente confirmar crontab** | Monitorización incompleta pese smoke E2E 2026-07-01 | Confirmar crontab operador |
| L96–97, L109 — checklist asume `LEBYTEK_API_*` en `.env` lebytek.com | Correcto para VPS **actual**; contradice FPS `main` post-purga | Nota: vars viven en Portal `.env.example` post-cutover |
| `docs/composer-setup.md` L101, L118 — pin `feature/backoffice-api-integration` / `dev-feature/...` | Consumidores nuevos instalan monolito legacy | Actualizar post-cutover; bloque “solo interino VPS” |

### D10 — Tests / CI ausentes en pipeline cloud

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| Auditoría 2026-07-27: `php tests/run.php` **no ejecutado** (cloud sin PHP CLI) | Gates harness no verificados | CI GitHub Actions PHP 8.x — **no existe** `.github/workflows/` |
| `main`: ~170 archivos `*Test.php`; feature: ~227 (incl. `tests/Marketing/*`) | Rama VPS sin gates FPS (`FrameworkRootNotPortal`, `SkeletonPurity`, `PackageAutoloadBoundary`) | No confundir verde feature con package source |
| Cobertura débil (auditoría): auth registro/recuperación E2E, RBAC router-level, payments webhook | Regresiones no detectadas | Follow-up PRs Framework |

### D11 — Stripe subscription (#21) — requisito documentado

6 criticals en issue **#21** (first-activation no-op, `invoice.paid` metadata, recover nuevo checkout, post-claim swallow, desync cancelled, amount bypass). Clases afectadas (**feature/Portal**): `ConfirmarPagoStripeUseCase.php`, `RecoverMembershipPaymentService.php`, `StripeGateway.php`, `MembresiaPagoController.php`.

**Gate ops:** `STRIPE_ENABLED=false`, `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` en VPS hasta cierre #21. Este spec **no** toca `src/Domain/Payments/` ni `app/Application/Marketing/`.

### D12 — CRUD state machine bypass (#23 contexto)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `config/cruds/mkt_ordenes.json` (feature) L55–63 — campo `status` editable incl. `"paid"` | Bypass authorize/activar sin Stripe | Issue Portal; `status` readonly o enforcement transiciones |
| `CrudTransitionService` — aplica a acciones `type: transition`, no guardado directo form | Patrón general CRUD | Follow-up Framework opcional |

### D13 — Conflicto purga env en rama feature (scope boundary)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `tests/Marketing/RoutesWiringTest.php` L62–68 — exige `MKT_MEMBERSHIP_AUTHORIZE_ENABLED=false` en root `.env.example` | Purga en **feature** rompe tests Marketing | Implementar Fase 1 solo en PR hacia **`main`** |
| `tests/Marketing/LandingVariantsConfigTest.php` — lee `.env.example` para `LANDING_VARIANT` | Idem | Tests Marketing no existen en `main` post-FPS |
| `tests/lib/bootstrap.php` L36–37 — carga `.env.example` si no hay `.env` | Harness tests plataforma usan vars restantes en plantilla | Tras purga: confirmar gates Kernel/Payments/Integrations verdes |

### D14 — Pipeline specs / PRs auditoría

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| PR #33 **draft** — informe 2026-07-27 + `INSTALL_TOKEN`; M2 sin purga | Spec asume auditoría accesible; fix parcial | Merge humano PR #33; purga M2 en PR Framework separado |
| Specs audit `2026-07-24`–`2026-07-26` en ramas `automation/audit-spec-*`; **no** en feature desplegada | Gates cutover/harness ilegibles desde VPS | Fetch docs desde ramas audit o `main` post-merge specs |
| PR #32 (spec 2026-07-26) **draft** — pase UX cutover | Progreso paralelo specs; no bloquea Fase 1 env | Coordinar merges humanos |

---

## Riesgos

| Riesgo | Severidad | Mitigación en diseño |
|--------|-----------|----------------------|
| **Stripe #21** — subscription checkout inseguro (6 criticals) | Alta | Gate duro: `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` en VPS; no habilitar en purga env; clases en `app/Application/Marketing/` fuera de alcance |
| **Bootstrap #23** — schema marketing incompleto en feature/VPS | Alta | Re-scope Portal; no reintroducir `MKT_*` en Framework `main`; manifiesto + colisión migraciones documentados en D5 |
| **VPS cutover estancado (C1)** — 6+ días sin avance | Alta | Banner deprecated Fase 2; no cambiar `BRANCH=` en script sin orden humana; SHA pinneado en checklist |
| **Purga env en rama incorrecta (feature)** | Alta | Fase 1 **solo** PR hacia `main`; feature conserva tests Marketing que exigen `MKT_*` (D13) |
| **Harness tests dependen de vars MKT_*** en bootstrap | Media | En `main`: grep `tests/` confirma cero aserciones `.env.example` Portal; en feature: no purgar |
| **INSTALL_TOKEN merge vs purga M2** en mismo PR | Media | Separar merge PR #33 (Q1) de PR purga (M2) para revisión incremental |
| **Operadores con `.env` legacy** copiado de plantilla antigua | Baja | Comunicar en CHANGELOG/docs; Portal tiene su `.env.example` |
| **Falso positivo test** (comentario redirección menciona `MKT_`) | Baja | Test busca keys activas `PREFIX`+`NAME=`; comentario `# ver Lebytek_Portal` permitido |
| **Deploy silencioso** (`\|\| true` migraciones) en VPS actual | Alta (ops) | Documentado D6; fail-fast fuera de alcance Fase 1–2 |
| **Monitoreo VPS** sin `/api/health` público | Media | Fase 3 opcional; hasta entonces no usar `/api/ping` como health externo |
| **Agente sin PHP** | Info | Verificación en CI/local; no bloquea merge spec-only |

---

## Compatibilidad (pase UX — PHP, navegadores, admin, móvil)

Inventario derivado de revisión estática en rama `automation/audit-spec-2026-07-27` (base `feature/backoffice-api-integration` @ `4789f95`), delta `main` @ `607a3c6`, auditoría [PR #33](https://github.com/Parzival2103/Lebytek_Framework/pull/33), specs 2026-07-24/25/26, `docs/core/ui_ux.md`, install wizard, `.env.example` root vs `skeleton/.env.example`, `routes/api.php` y checklist VPS. **Solo requisitos de diseño** — sin implementación en este pipeline.

**Contexto:** el foco del spec es harness post-FPS (`main`); VPS sigue en monolito feature con vars `MKT_*` activas (D13). Los requisitos K/U/R distinguen **package source objetivo** vs **VPS interino pinneado**.

### K1 — Runtime PHP y extensiones (install + tests harness)

| Ítem | Evidencia | Requisito |
|------|-----------|-----------|
| PHP mínimo | `composer.json`: `"php": ">=8.1"` | Harness, skeleton tenants y VPS interino deben ejecutar **PHP ≥ 8.1** antes de merge Fase 1 |
| Extensiones install | `public/install/views/paso_requisitos.php` — `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo` | Fallo en requisitos debe bloquear wizard (comportamiento actual OK); documentar en `VPS_CHECKLIST.md` |
| Cloud agent sin PHP | Auditoría 2026-07-27; D10 | Gates `FrameworkRootNotPortal` / purga env **no verificados** en cron — CI local o runner PHP antes de merge implementación |
| Tests Marketing en feature | `tests/Marketing/RoutesWiringTest.php` L62–68 exige `MKT_*` en root `.env.example` | Purga Fase 1 **solo** hacia `main`; feature @ `4789f95` conserva plantilla Marketing hasta cutover (D13) |

### K2 — Navegadores soportados (install vs admin vs health JSON)

| Superficie | Stack | Compatibilidad esperada |
|------------|-------|-------------------------|
| Install wizard | Bootstrap 5.3 local; contenedor **720px** (`_layout.php` L12) | Chrome/Firefox/Safari/Edge **últimas 2 versiones**; iOS Safari ≥ 15 |
| Admin harness / skeleton | Bootstrap 5.3 + jQuery + DataTables Responsive (CDN) | Baseline `docs/core/ui_ux.md` §542 — breakpoint **992px (`lg`)** |
| Health API (Fase 3) | JSON puro — sin HTML | curl, UptimeRobot, nginx LB; **no** redirect 302 a `/login` |
| Marketing VPS (interino) | Landing v1 Bootstrap / v2 standalone | Validar en feature pinneada; fuera de alcance purga `main` |

**Gap K2a — iconos Bootstrap Icons ausentes en install:** `_layout.php` del wizard no carga `bootstrap-icons.css` (admin sí en `base.php`); clases `bi-*` en `paso_requisitos`, `paso_error`, `ya_instalado` pueden renderizar sin icono — heredado spec 2026-07-25 K2a.

**Requisito Fase 1.5 (opcional):** cargar `bootstrap-icons` en `public/install/views/_layout.php` y `skeleton/public/install/views/_layout.php`.

### K3 — `/api/ping` autenticado vs `/api/health` público (M4, D7)

Estado actual: `routes/api.php` L14–24 — grupo `/api` con `AuthMiddleware`; `/api/ping` → `HealthController::ping`. Sin sesión, `AuthMiddleware` responde **302 HTML** a `/login`.

| Cliente | Comportamiento `/api/ping` | Riesgo compat |
|---------|---------------------------|---------------|
| LB HTTP health check | 302 → login (200 HTML) | **Healthy falso** — checklist VPS L16–17 asume cron cada 5 min |
| `curl -f` post-deploy | Redirect o HTML | Smoke inconsistente entre ops |
| Monitoreo móvil (apps ops) | HTML en lugar de JSON | Alertas silenciosas |

**Requisito Fase 3:** `GET /api/health` **fuera** del grupo auth; contrato liveness JSON `{"status":"ok"}`. Documentar que `/api/ping` **no** es endpoint de monitoreo externo.

### K4 — Asimetría plantillas `.env.example` (M2, D1, D13)

| Plantilla | Vars Portal/MKT | Test gate |
|-----------|-----------------|-----------|
| `skeleton/.env.example` | **Ausentes** | `SkeletonPurityTest` L45–49 |
| Root `.env.example` | **16 keys activas** L54–100 | Sin gate en `FrameworkRootNotPortalTest` (D2) |
| Feature root (VPS) | Idem + tests Marketing exigen `MKT_MEMBERSHIP_AUTHORIZE_ENABLED` | Purga rompe CI feature |

**Requisito compat configuración:** tras Fase 1 en `main`, operador que copia root `.env.example` obtiene plantilla **idéntica en espíritu** a skeleton (solo plataforma); vars waapi/Marketing viven en `Lebytek_Portal/.env.example`.

### K5 — `INSTALL_TOKEN` y entornos (M1, D3)

| Ítem | Evidencia | Requisito |
|------|-----------|-----------|
| Prod exige token | `public/install/index.php` L54–61 | Merge humano PR #33 (`INSTALL_TOKEN=` en root + skeleton) |
| Harness @ `4789f95` | Root `.env.example` **sin** `INSTALL_TOKEN` | No regresionar al implementar purga M2 |
| Respuesta 403/419 | L59–60, L73–74 — **texto plano** sin layout | Ver U1–U2; incompatible con ops móvil |
| Token en query `?token=` | Historial navegador | Runbook: rotar token si URL compartida |

### K6 — Divergencia VPS feature vs package `main` (C1, D4)

Mientras VPS despliega `feature/backoffice-api-integration` @ `4789f95`:

| Superficie | Compat esperada interino | Post-cutover (Portal + vendor) |
|------------|-------------------------|--------------------------------|
| Vars `.env` lebytek.com | `MKT_*`, `LEBYTEK_API_*` **requeridas** | Vars en Portal `.env.example` |
| Admin Marketing CRUD | Schema drift #23 → 500 en cualquier navegador | Bootstrap Portal completo |
| Harness local desde `main` | Sin Marketing; install + CRUD plataforma | Referencia canónica FPS |

**Requisito:** checklist VPS (D9) distingue explícitamente vars **interino VPS** vs **harness post-purga**; no copiar plantilla `main` purgada al VPS hasta cutover.

### K7 — Stripe, subscription y gates externos (#21, D11)

| Condición | Evidencia | Impacto compat/UX |
|-----------|-----------|-------------------|
| `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` | Issue #21 — 6 criticals | Retorno Checkout "éxito" engañoso en **cualquier** navegador |
| Purga env no toca `PAYMENTS_*` | Inventario §1 conserva bloque plataforma | `STRIPE_ENABLED=false` permanece en plantilla harness |

**Gate:** subscription checkout OFF en VPS y en `.env.example` hasta cierre #21; purga M2 no altera gates Stripe.

---

## UX (pase UX — flujos, copy, estados error/vacío)

Requisitos para **harness/package `main`** (Fase 1) y **VPS interino** (señales documentales Fase 2). No implementar Marketing en Framework root.

### U1 — Install wizard: 403 token sin guía visual (M1, PR #33)

`public/install/index.php` L59–60:

```text
Instalador protegido. Proporcione ?token=INSTALL_TOKEN (definido en .env).
```

| Problema UX | Impacto |
|-------------|---------|
| Respuesta texto plano sin `_layout.php` | Ops en móvil no distingue error config vs 404 nginx |
| Placeholder literal `INSTALL_TOKEN` en mensaje | Riesgo de copiar literal en URL |
| Sin enlace a doc | Operador no sabe generar token ni dónde pegarlo en `.env` |

**Requisito:** vista `install_token_denied.php` con layout wizard, pasos numerados (1. definir `INSTALL_TOKEN` en `.env` — ver PR #33, 2. abrir `https://{dominio}/install/?token={valor}`), enlace `docs/core/despliegue-y-versionado.md`. Paridad harness + skeleton.

### U2 — Install wizard: CSRF 419 sin layout

L73–74: `Token CSRF inválido. Recargue el asistente.` — mismo anti-patrón que U1.

**Requisito:** alert Bootstrap danger dentro de `_layout.php` + botón "Recargar asistente" preservando `token` en query si producción.

### U3 — Root `.env.example`: copy Portal activo confunde mantenedores (M2 — foco del spec)

Evidencia root L7–10, L53–55, L69–118:

| Copy problemático | Por qué confunde |
|-------------------|------------------|
| L10: CTA `APP_URL/?compras=1#paquetes` | Flujo compra membresía no existe en harness FPS |
| L53–55: `MKT_EMAIL_*` → lebytek.com / waapi | Parece requerido para tenant genérico skeleton |
| L117–118: `LANDING_VARIANT` v1/v2 | Landing Marketing ausente en `main` post-FPS |
| Comentario L91–98 transferencia bancaria | Ops copia datos bancarios Lebytek al instalar cliente |

**Requisito Fase 1 (Enfoque A):** eliminar keys activas; comentario único de redirección a `Lebytek_Portal/.env.example`; `APP_URL` comentado como URL del tenant desplegado. Alinear copy con `skeleton/.env.example` (genérico "Sistema Administrativo").

### U4 — `INSTALL_TOKEN` documentado sin guía de generación

PR #33 añade `INSTALL_TOKEN=` vacío; no indica cómo generar valor seguro.

**Requisito UX documental:** comentario inline en `.env.example` con ejemplo `openssl rand -hex 32` o equivalente; advertencia "no commitear valor real"; referencia runbook despliegue. Aplicar root + skeleton.

### U5 — Monitoreo health: ops sin distinción ping vs health (M4, D7)

Checklist VPS asume cron health cada 5 min pero `/api/ping` exige sesión.

| Estado | Experiencia ops |
|--------|-----------------|
| Cron apunta a `/api/ping` | Alertas verdes falsas o errores opacos |
| Sin `/api/health` | No hay URL copy-paste para panel hosting móvil |

**Requisito Fase 3:** doc `despliegue-y-versionado.md` con tabla "endpoint / uso / auth"; hasta entonces checklist marca `/api/ping` como **no válido** para LB externo.

### U6 — Checklist VPS: lenguaje "until merge" sin fecha (D9, Q4)

`docs/integration/VPS_CHECKLIST.md` L89 — feature como target implícito; L96–97 asume `LEBYTEK_API_*` en `.env` (correcto interino, contradice harness post-purga).

**Requisito Fase 2:** banner **interino/deferred** con SHA `@4789f95` y fecha revisión; nota al pie "vars Marketing — ver Portal `.env.example` post-cutover"; enlace a este spec final.

### U7 — Script deploy legacy: ops sin señal de deprecación (Q4, D6)

`scripts/vps-deploy-lebytek-com.sh` — `BRANCH=feature/backoffice-api-integration` sin banner; migraciones L56 `|| true`.

**Requisito Fase 2:** header deprecated con link `CUTOVER-PORTAL.md`; primer stdout del script visible en log deploy advierte "interino — no usar para nuevos tenants skeleton".

### U8 — Empty-state mantenedor: skeleton limpio vs root sucio

Nuevo maintainer clona repo, compara `skeleton/.env.example` (limpio) con root (Portal vars) → cree que skeleton está incompleto o que debe añadir `MKT_*` al tenant.

**Requisito:** Fase 1 cierra gap; README/`PACKAGE-ROOT.md` menciona explícitamente "root `.env.example` = harness local; skeleton = plantilla tenant".

### U9 — Pipeline specs y PR auditoría draft (D14)

| Problema | Impacto UX ops/dev |
|----------|-------------------|
| PR #33 **draft** — `INSTALL_TOKEN` no en `main` | Wizard prod bloqueado sin doc mergeada |
| Specs en `automation/audit-spec-*` | Gates ilegibles desde clone feature/VPS |
| PR #32 (2026-07-26) draft paralelo | Confusión sobre spec "canónico" del día |

**Requisito:** PR #33 cerrado con enlace al PR spec final (este pipeline); spec final mergeable desde `automation/audit-spec-2026-07-27`.

---

## Responsive (pase UX — breakpoints, layout admin/público)

Referencia admin: `docs/core/ui_ux.md` §542 — breakpoint único **992px (`lg`)**. Install wizard: **720px**. Este spec no introduce vistas Marketing nuevas en `main`; responsive Marketing aplica solo a **VPS interino** (referencia specs 2026-07-26 R2–R4).

### R1 — Install wizard (720px) — token, CSRF y pasos

| Vista / estado | Verificación |
|----------------|--------------|
| `_layout.php` `max-width: 720px` | 375px, 768px, 1280px — card legible |
| 403/419 post U1–U2 | Error **con layout** en 375×812 |
| `paso_requisitos` — lista extensiones | Scroll si viewport corto; botón continuar visible |
| `paso_bd`, `paso_admin` | Inputs no desbordan; teclado virtual iOS no oculta submit |
| `paso_revision` — lista migraciones | `max-height` + scroll (spec 2026-07-25 R2–R3) |

### R2 — Admin harness post-install (992px)

| Componente | Comportamiento | Smoke |
|------------|----------------|-------|
| Sidebar offcanvas | `< 992px` offcanvas; `≥ 992px` fijo | Login → dashboard en 375px y 1280px |
| CRUD plataforma | `table-responsive` obligatorio (`ui_ux.md`) | Lista usuarios/roles sin scroll horizontal roto |
| Bottombar móvil | `d-lg-none` | Acciones primarias no bajo barra inferior |

### R3 — `.env.example` y docs ops (sin UI — legibilidad móvil)

Maintainers revisan diff spec/PR desde móvil (GitHub).

**Requisito:** tablas inventario vars (§Diseño técnico §1) legibles en markdown GitHub móvil; checklist VPS con pasos numerados cortos, no párrafos densos.

### R4 — Health check desde panel hosting móvil

Ops configura URL health en app móvil del proveedor (SiteGround, cPanel, etc.).

**Requisito Fase 3:** URL corta `/api/health` sin query; respuesta JSON ≤ 200 bytes; documentar screenshot-placeholder en checklist.

### R5 — Skeleton tenant: paridad install responsive

Cambios install (U1–U2, K2a) deben replicarse en `skeleton/public/install/` — smoke 375px en tenant recién generado desde skeleton, no solo harness root.

### R6 — Smoke responsive harness (pre-merge Fase 1)

| # | Viewport | Flujo |
|---|----------|-------|
| 1 | 375×812 | `/install/?token=inválido` → layout error U1 |
| 2 | 375×812 | Install paso requisitos → BD (local/staging) |
| 3 | 375×812 | Admin login post-install → dashboard |
| 4 | 1280×800 | Mismo flujo — sidebar fijo |
| 5 | CLI + 375px browser | `curl /api/health` (post Fase 3) vs `/api/ping` sin cookie |
| 6 | 320px | Install paso admin — botón submit visible |

### R7 — VPS interino (feature pinneada) — no regresión pre-cutover

Purga `main` **no** debe usarse como checklist responsive Marketing en VPS actual.

**Requisito:** smoke R6/R7 spec 2026-07-26 (landing, CRUD órdenes) sigue aplicando a **feature @ SHA pinneado** hasta cutover; este spec solo añade R6 harness arriba para package source.

---

## Criterios de aceptación

### Fase 1 (obligatoria)

- [ ] Root `.env.example` no contiene keys activas con prefijos `MKT_`, `LEBYTEK_API_`, `WAAPI_PORTAL_`.
- [ ] Comentario de redirección a `Lebytek_Portal/.env.example` presente.
- [ ] `INSTALL_TOKEN` documentado en root y skeleton (heredado de PR #33).
- [ ] Test `FrameworkRootNotPortal` (o equivalente) falla si se reintroduce drift.
- [ ] `php tests/run.php FrameworkRootNotPortal` pasa.
- [ ] `php tests/run.php SkeletonPurity` sigue pasando (sin regresión).

### Fase 2 (docs)

- [ ] `vps-deploy-lebytek-com.sh` header marca script como deprecated con link a `CUTOVER-PORTAL.md`.
- [ ] Checklist VPS actualizado indicando feature = interino.

### Fase 3 (opcional)

- [ ] `GET /api/health` público retorna 200 JSON sin autenticación.
- [ ] Documentación de monitoreo actualizada.

### Gates externos (humanos — no bloquean PR Fase 1)

- [ ] Cutover Portal según `docs/CUTOVER-PORTAL.md`.
- [ ] Issue #21 cerrado antes de habilitar subscription checkout.
- [ ] Issue #23 cerrado o re-scoped a Portal con checklist VPS.

### Compatibilidad / UX / Responsive (pase UX — este spec)

- [ ] Secciones **Compatibilidad** (K1–K7), **UX** (U1–U9) y **Responsive** (R1–R7) revisadas por maintainer.
- [ ] Install wizard 403/419 con layout/guía ops o documentación equivalente mergeada (U1, U2, K5, R1).
- [ ] Root `.env.example` sin copy Portal activo confuso; alineado con espíritu skeleton (U3, U8, K4).
- [ ] `INSTALL_TOKEN` con guía generación en comentario `.env.example` (U4, K5); merge PR #33 o equivalente.
- [ ] Checklist VPS distingue vars interino vs harness post-purga; banner deprecated en script deploy (U6, U7, K6).
- [ ] Doc monitoreo distingue `/api/ping` (auth) vs `/api/health` (público) — o Fase 3 implementada (U5, K3, R4).
- [ ] Smoke responsive R6 ejecutado en harness staging; R7 Marketing deferred a VPS feature hasta cutover.
- [ ] PR #33 **cerrado** con enlace al PR spec final; spec en `automation/audit-spec-2026-07-27` (U9, D14).

### Deuda técnica — verificación post-implementación

- [ ] **D1:** Root `.env.example` sin keys activas `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*`; comentario redirección a `Lebytek_Portal/.env.example`.
- [ ] **D2:** `FrameworkRootNotPortalTest` (o `HarnessEnvExamplePurityTest`) falla si se reintroduce drift en root `.env.example`.
- [ ] **D3:** `INSTALL_TOKEN` presente en root + skeleton (merge PR #33 o equivalente en `main`).
- [ ] **D4:** `VPS_CHECKLIST.md` L89 marca feature como **interino/deferred** con SHA pinneado y fecha revisión.
- [ ] **D5:** Issue #23 actualizado con scope Portal/VPS; manifiesto incluye `rep_churn_metrics`; colisión `20260715120000` resuelta.
- [ ] **D6:** Banner deprecated en `vps-deploy-lebytek-com.sh`; fail-fast migraciones documentado como follow-up cutover.
- [ ] **D7:** Si Fase 3 implementada: `GET /api/health` público; doc monitoreo en `despliegue-y-versionado.md`.
- [ ] **D8:** RBAC permisos — issue `permisos.gestionar` abierto o aceptado explícitamente.
- [ ] **D9:** `docs/composer-setup.md` distingue pin interino VPS vs paquete FPS post-cutover.
- [ ] **D10:** Workflow CI con `php tests/run.php` en `main` (follow-up; fuera de alcance spec-only).
- [ ] **D11:** Checkout subscription OFF en prod; evidencia issue #21 antes de habilitar.
- [ ] **D13:** Purga verificada en **`main`**; rama feature no afectada hasta cutover.
- [ ] **D14:** PR #33 cerrado por pase UX con enlace al PR spec final; spec mergeado desde rama audit.

---

## Referencias

- **PR auditoría:** [#33](https://github.com/Parzival2103/Lebytek_Framework/pull/33) — `cursor/auditor-a-t-cnica-aaa2`
- **Reporte:** `docs/audits/2026-07-27-auditoria-tecnica-diaria.md` (en PR #33; pendiente merge a `main`)
- **Issues:** [#21](https://github.com/Parzival2103/Lebytek_Framework/issues/21) Stripe criticals, [#23](https://github.com/Parzival2103/Lebytek_Framework/issues/23) bootstrap marketing
- **Tests existentes:** `tests/Kernel/SkeletonPurityTest.php` L45–49, `tests/Kernel/FrameworkRootNotPortalTest.php`
- **Cutover:** `docs/CUTOVER-PORTAL.md`, `docs/PACKAGE-ROOT.md`
