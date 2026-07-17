# FPS Plan 00 — Inventario y rama de consolidación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Crear la rama `consolidation/framework-portal-separation` desde `main`, registrar SHAs de referencia y publicar un manifiesto de frontera Framework↔Portal **sin cambios runtime**.

**Architecture:** `main` ya contiene código Portal tras el merge del PR #5 (`2c71d3f`). La consolidación **no** fusiona `feature/backoffice-api-integration` → `main`. Los ~47 commits posteriores de la feature se trasladarán selectivamente por paths en planes 01–06. Este plan solo fija el punto de partida seguro y la clasificación de paths.

**Tech Stack:** Git, PowerShell, microtest (`php tests/run.php`), paths bajo `c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework`.

**Reemplaza para ejecución:** `docs/superpowers/plans/2026-07-15-framework-portal-separation.md` (referencia histórica D1–D11).

**Roadmap:** `docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md`

**Predecesor:** ninguno. **Sucesor obligatorio:** Plan 01 (`2026-07-17-fps-01-generic-payments-stripe.md`).

## Global Constraints

- Rama de consolidación: **`consolidation/framework-portal-separation`**, creada desde **`main`** (no desde `feature/backoffice-api-integration`).
- SHA fuente congelado para copia Portal posterior (Plan 05): **`dad059056d26b6eb527815f85cf71ecd507a57fe`** (`feature/backoffice-api-integration`).
- SHA `main` al registrar baseline: **`2c71d3f7f75eea2ee746bc271b9a3907dbbdd9cd`** (merge PR #5 — incluye Portal).
- SHA merge-base (`main` ∩ `feature`): **`f4d82ffa7413035040643a5b6b32137b33f49112`**.
- **Prohibido** merge `feature/backoffice-api-integration` → `main` salvo orden explícita del usuario.
- **Prohibido** deploy, SSH, `git push` a remotes, edición de `vendor/`, secretos en commits.
- Este plan es **docs only** en runtime: no modifica PHP, SQL, config ni tests de plataforma (salvo restaurar archivos de documentación FPS desde la rama docs).
- Comando de verificación baseline (solo lectura): `php tests/run.php` — anotar `N passed, M failed`; no es gate de este plan.

## Deuda técnica D1–D11 (referencia inline)

Separar repos **sin** pagar estas deudas produce un framework “puro en paper” que no se puede instalar ni bootstrapear un tenant.

### Bloqueantes (cierran en planes FPS)

| ID | Deuda hoy | Por qué duele post-split | Plan FPS |
|----|-----------|--------------------------|----------|
| D1 | `composer.json` Framework autoload-ea `App\\` + `Lebytek\\Framework\\` | `composer require` contamina o exige monorepo | 06 |
| D2 | `migrate.php` / `seed.php` leen `ROOT_PATH/database/schema/...` | En consumidor el SQL está en `vendor/…`; copiar = drift | 03 |
| D3 | `skeleton/` contiene Marketing completo | Nuevos tenants arrancan con negocio Lebytek | 04 |
| D4 | Bootstrap tests skeleton: path vendor asume monorepo anidado | Smoke standalone falla | 04 |
| D5 | `marketing*.sql` en repo Framework como si fuera plataforma | Frontera borrosa | 06 |
| D6 | Docs/README enseñan deploy del monorepo | Equipo/IA siguen modelo viejo | 07, 08 |
| D7 | Assets UI solo en `public/assets` del árbol; consumidor sin copia → admin roto | Skeleton/Portal deben shippear lista canónica | 04, 07 |
| D8 | `install.php` + `Installer` + `bootstrap_sql` resuelven bajo `ROOT_PATH` | Módulos plataforma no encuentran SQL en vendor | 03 |
| D9 | Skeleton duplica `database/seeds` y migrations de plataforma | Drift silencioso vs paquete | 04 |
| D10 | Skeleton `config/container.php` + `cruds/mkt_*` + `modules/marketing.php` cablean Marketing | `vertical.marketing=false` no basta | 04 |
| D11 | Tras copiar Portal, la raíz Framework sigue siendo un “sitio a medias” | Dos fuentes de verdad; cutover imposible | 06 |

### Aceptada conscientemente (documentar, no pagar en FPS 00–08)

| ID | Deuda | Mitigación |
|----|-------|------------|
| A1 | No hay `lebytek/module-marketing` | YAGNI; Marketing solo en Portal |
| A2 | Tag `v1.0.0` aún no es paquete puro | Portal local usa path/`*@dev` |
| A3 | `integrations` base en Framework + flag vertical | `LebytekApiClient` va al Portal |
| A4 | Thin wrappers `scripts/*.php` en Portal/skeleton | Preferible a invocar vendor a mano |
| A5 | Cursor rules hablan de monorepo hasta Plan 07 | Actualizar en Plan 07 |
| A6 | Sin Composer plugin publish assets | Copia manual + `docs/ASSETS-PLATFORM.md` |
| A7 | Harness mínimo en raíz Framework para tests | Documentado no-deploy (`docs/PACKAGE-ROOT.md`) |
| A8 | Demos CRUD en skeleton OFF en vertical | Demo plataforma, no Marketing Lebytek |

Detalle histórico ampliado: `docs/superpowers/plans/2026-07-15-framework-portal-separation.md` (SUPERSEDED — no ejecutar monolíticamente).

---

### Task 1: Registrar baseline Git y crear rama de consolidación

**Files:**
- Create: `docs/superpowers/FPS-git-baseline.md`
- Modify: none (runtime)

**Interfaces:**
- Consumes: repos `Lebytek_Framework` en disco; refs `main`, `feature/backoffice-api-integration`.
- Produces: rama local `consolidation/framework-portal-separation`; archivo `FPS-git-baseline.md` con SHAs y conteos de delta.

- [ ] **Step 1: Registrar SHAs y conteo de delta**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git fetch origin main feature/backoffice-api-integration 2>&1
$mainSha = git rev-parse main
$featureSha = git rev-parse feature/backoffice-api-integration
$mergeBase = git merge-base main feature/backoffice-api-integration
$deltaCount = (git diff --name-only main..feature/backoffice-api-integration | Measure-Object -Line).Lines
Write-Output "main=$mainSha feature=$featureSha merge-base=$mergeBase delta_files=$deltaCount"
```

Expected: salida con tres SHAs de 40 caracteres y `delta_files` ≈ 194 (±5 según estado del repo).

- [ ] **Step 2: Baseline de tests en main (solo anotar)**

Run:

```powershell
git stash push -u -m "fps-00-wip" 2>$null
git checkout main
php tests/run.php 2>&1 | Select-Object -Last 3
```

Expected: última línea `N passed, M failed`. Anotar `N` y `M` en el doc del Step 4 (no corregir fallos aquí).

- [ ] **Step 3: Crear rama de consolidación desde main**

Run:

```powershell
git checkout -b consolidation/framework-portal-separation
git branch --show-current
```

Expected: `consolidation/framework-portal-separation`.

- [ ] **Step 4: Restaurar spec + planes FPS desde una ref disponible (sin merge feature→main)**

Los planes FPS viven inicialmente en **`docs/framework-portal-separation-plans`**. Tras el PR pueden estar en esa rama, en `feature/backoffice-api-integration` o, únicamente si el equipo decidió integrar documentación allí, en `main`. La rama de consolidación se crea desde `main`; restaurar solo estos archivos desde otra ref **no** implica merge feature→main.

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git fetch origin 2>&1
$roadmap = "docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md"
$candidateRefs = @(
  "docs/framework-portal-separation-plans",
  "origin/docs/framework-portal-separation-plans",
  "feature/backoffice-api-integration",
  "origin/feature/backoffice-api-integration",
  "main",
  "origin/main"
)
$docsRef = $null
$docsSha = $null
$planPaths = @()
foreach ($candidate in $candidateRefs) {
  git rev-parse --verify $candidate 2>$null | Out-Null
  if ($LASTEXITCODE -ne 0) { continue }
  $candidateSha = git rev-parse $candidate
  git cat-file -e "${candidateSha}:${roadmap}" 2>$null
  if ($LASTEXITCODE -ne 0) { continue }
  $candidatePlans = @(git ls-tree -r --name-only $candidateSha -- docs/superpowers/plans/ |
    Where-Object { $_ -match '2026-07-17-fps-[0-9]{2}-' })
  if ($candidatePlans.Count -ge 9) {
    $docsRef = $candidate
    $docsSha = $candidateSha
    $planPaths = $candidatePlans
    break
  }
}
if ($null -eq $docsRef) {
  Write-Error "No ref contains roadmap plus fps-00..08; fetch the documentation PR branch or merge that PR into feature first"
  exit 1
}
Write-Output "docs_source_ref=$docsRef"
Write-Output "docs_source_sha=$docsSha"
$planPaths = git ls-tree -r --name-only $docsSha -- docs/superpowers/plans/ |
  Where-Object { $_ -match '2026-07-17-fps-[0-9]{2}-' }
if ($planPaths.Count -lt 9) { Write-Error "Expected fps-00..08 plans on $docsRef, got $($planPaths.Count)"; exit 1 }
git checkout $docsSha -- $roadmap
foreach ($p in $planPaths) { git checkout $docsSha -- $p }
git status --short docs/superpowers/plans/ docs/superpowers/specs/
```

Expected: `docs_source_ref` indica la primera ref válida, `docs_source_sha` tiene 40 caracteres y `git status` muestra planes `2026-07-17-fps-00` … `fps-08` más el roadmap en la rama de consolidación. Funciona aunque la rama docs remota haya sido eliminada después de fusionar el PR en la feature.

- [ ] **Step 5: Escribir `docs/superpowers/FPS-git-baseline.md`**

Create `docs/superpowers/FPS-git-baseline.md`:

```markdown
# FPS — Baseline Git (Framework ↔ Portal)

**Registrado:** 2026-07-17  
**Repo:** `Lebytek_Framework`

## SHAs de referencia

| Ref | SHA | Notas |
|-----|-----|-------|
| `main` | `2c71d3f7f75eea2ee746bc271b9a3907dbbdd9cd` | Merge PR #5 — **ya contiene código Portal** |
| `feature/backoffice-api-integration` | `dad059056d26b6eb527815f85cf71ecd507a57fe` | SHA congelado para copia Portal (Plan 05) |
| merge-base | `f4d82ffa7413035040643a5b6b32137b33f49112` | Ancestro común |
| `docs/framework-portal-separation-plans` | `<docs_source_sha>` | Fuente spec + planes FPS 00–08 (resolver con `git rev-parse docs/framework-portal-separation-plans`) |

## Delta feature exclusivo

- Comando: `git diff --name-only main..feature/backoffice-api-integration`
- Aproximadamente **194 archivos** (plataforma genérica + Marketing + landing + membresías).
- **No** se transfiere con merge; los planes 01–06 usan `git checkout <sha> -- <path>` selectivo.

## Rama de trabajo FPS

| Rama | Base | Rol |
|------|------|-----|
| `consolidation/framework-portal-separation` | `main` | Consolidación incremental Plans 01–06 |
| `docs/framework-portal-separation-plans` | feature o `main` | PR documentación; **no** base de runtime |

## Continuidad documentación → consolidación

1. Merge del PR de docs (opcional para el equipo) deja planes en `feature` o `main`; antes del merge siguen en la rama docs.
2. Plan 00 Step 4 busca, en orden, la rama docs, la feature y `main`, y copia solo spec + planes a `consolidation/framework-portal-separation`.
3. **No** mergear `feature/backoffice-api-integration` → `main` para obtener docs.

## Tests baseline en `main` (anotación)

- Comando: `php tests/run.php`
- Resultado al registrar: `<N> passed, <M> failed` — rellenar con valores reales del Step 2.

## Política operativa

- **Nunca** merge `feature/backoffice-api-integration` → `main` sin orden explícita.
- Deploy VPS sigue pull de feature hasta nuevo aviso; la consolidación es local/package-first.
```

- [ ] **Step 6: Commit**

```powershell
git add docs/superpowers/FPS-git-baseline.md docs/superpowers/plans/ docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md
git commit -m "docs(fps): register Git baseline and restore FPS roadmap plans from docs branch"
```

---

### Task 2: Manifiesto de frontera y clasificación de paths

**Files:**
- Create: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`
- Modify: none (runtime)

**Interfaces:**
- Consumes: `FPS-git-baseline.md`; salida de `git diff --name-only main..feature/backoffice-api-integration`.
- Produces: manifiesto con paths permitidos (Plan 01), prohibidos y clasificación plataforma/Portal/mixto/descartado.

- [ ] **Step 1: Exportar lista de delta para clasificación**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git diff --name-only main..feature/backoffice-api-integration | Out-File -Encoding utf8 docs/superpowers/FPS-delta-paths-main-to-feature.txt
(Get-Content docs/superpowers/FPS-delta-paths-main-to-feature.txt | Measure-Object -Line).Lines
```

Expected: archivo UTF-8 con ~194 líneas.

- [ ] **Step 2: Escribir manifiesto de frontera**

Create `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`:

```markdown
# Boundary FPS: Framework vs Portal

**Fuente diseño:** `docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md`  
**Baseline Git:** `docs/superpowers/FPS-git-baseline.md`  
**Plan histórico (no ejecutar monolítico):** `docs/superpowers/plans/2026-07-15-framework-portal-separation.md`

## Hecho crítico sobre `main`

El commit `2c71d3f` (PR #5) fusionó `feature/backoffice-api-integration` en `main` y dejó en `main` **tanto plataforma como código Portal** (`app/Domain/Marketing`, landing, LebytekApi, etc.). La rama `consolidation/framework-portal-separation` parte de ese estado. El objetivo final es que el paquete `lebytek/framework` **no** autoload-e `App\\` ni contenga CRM; eso se logra en Plans 04–06, no revirtiendo `main`.

## Roles finales

| Rol | Repo / path | Composer |
|-----|-------------|----------|
| Plataforma | `Lebytek_Framework/src/`, SQL plataforma, `skeleton/` mínimo | `lebytek/framework` library |
| Portal Lebytek | `Lebytek_Portal/` (sibling) | `lebytek/portal` project |
| Tenant nuevo | copia de `skeleton/` | project propio |

## Clasificación de paths del delta (`main..feature`)

Reglas aplicadas al archivo `FPS-delta-paths-main-to-feature.txt`:

| Clase | Criterio | Acción en consolidación |
|-------|----------|-------------------------|
| **plataforma** | `src/`, SQL módulos plataforma (`payments.sql`, `integrations.sql`, …), tests plataforma (`tests/Payments`, `tests/Kernel`, …) | Trasladar selectivamente (Plan 01+) |
| **portal** | `app/**`, `tests/Marketing/**`, `database/migrations/*mkt*`, `database/schema/modules/marketing*.sql`, `config/cruds/mkt_*`, `routes/marketing.php`, `public/assets/publico/**` | Copiar en Plan 05 desde SHA congelado; retirar de Framework en Plan 06 |
| **mixto** | Archivos que mezclan plataforma y negocio | Reimplementar solo la parte genérica; **no** `git checkout` del archivo completo |
| **descartado** | Worktrees, logs, `.superpowers/sdd/*` locales | Ignorar |

### Paths plataforma — allowlist Plan 01 (Payments genérico)

Trasladar desde `dad0590` con `git checkout dad0590 -- <path>`:

```text
src/Domain/Payments/
src/Application/Payments/
src/Infrastructure/Payments/
database/schema/modules/payments.sql
config/modules/payments.php
config/payments.php
tests/Payments/
composer.json          # solo añadir stripe/stripe-php
.env.example             # solo bloque STRIPE_* / PAYMENTS_*
skeleton/config/payments.php
```

Ediciones manuales permitidas en Plan 01:

- `config/vertical.php` — añadir `'payments' => false` en `modules`
- `skeleton/config/vertical.php` — `'payments' => false`
- `config/container.php` — **solo** bloque plataforma (registry + repo), líneas equivalentes a:

```php
if ((bool) Config::get('vertical.modules.payments', false)) {
    $container->singleton(
        \Lebytek\Framework\Application\Payments\PaymentGatewayRegistry::class,
        static fn () => \Lebytek\Framework\Application\Payments\PaymentsFactory::registry()
    );
    $container->singleton(
        \Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface::class,
        static fn () => new \Lebytek\Framework\Infrastructure\Payments\PdoPaymentEventLogRepository()
    );
}
```

### Paths prohibidos en Plan 01

```text
app/**
database/migrations/*mkt*
database/schema/modules/marketing*.sql
config/cruds/mkt_*.json
config/modules/marketing.php
routes/marketing.php
app/Presentation/Controllers/Publico/StripeWebhookController.php
app/Application/Marketing/IniciarPagoStripeUseCase.php
app/Application/Marketing/ConfirmarPagoStripeUseCase.php
config/container.php   # bindings Marketing/Stripe de negocio (ConfirmarPago, CompraController stripe, etc.)
```

### Paths portal — inventario representativo (Plan 05)

```text
app/Application/Marketing/
app/Domain/Marketing/
app/Infrastructure/Marketing/
app/Infrastructure/Integrations/LebytekApi/
app/Presentation/Controllers/Publico/
app/Presentation/Views/publico/
tests/Marketing/
database/migrations/20260714200000_mkt_membership_orders.sql
database/migrations/20260715120000_mkt_ordenes_stripe.sql
database/schema/modules/marketing.sql
config/cruds/mkt_leads.json
config/cruds/mkt_ordenes.json
docs/integration/
```

### Archivos mixtos documentados

| Archivo | Parte plataforma | Parte Portal | Resolución |
|---------|------------------|--------------|------------|
| `config/container.php` | bloque `PaymentGatewayRegistry` + `PaymentEventLogRepositoryInterface` | bloque Marketing + Stripe use cases | Plan 01: solo plataforma; Plan 05/06: Portal posee bindings negocio |
| `config/vertical.php` | flags `payments`, `integrations` | flag `marketing` | Cada plan edita solo sus claves |
| `composer.json` | `stripe/stripe-php` en require | — | Plan 01 añade dependencia; no tocar autoload `App\\` hasta Plan 06 |

## Explicit NO (todos los planes FPS)

- **Nunca** merge `feature/backoffice-api-integration` → `main` sin orden explícita del usuario
- Deploy / SSH / push remoto sin orden explícita
- Editar `vendor/`
- Copiar `schema.sql` plataforma al Portal como SoT
- Clonar Portal para bootstrapping de cliente externo
- Extraer `lebytek/module-marketing` (YAGNI)

## Deuda bloqueante (D1–D11)

Ver tabla completa en Plan 00 (`2026-07-17-fps-00-inventory-consolidation-branch.md`, sección *Deuda técnica D1–D11*). Resumen: D1/D5/D11 → Plan 06; D2/D8 → Plan 03; D3/D4/D7/D9/D10 → Plan 04; D6 → Planes 07–08.
```

- [ ] **Step 3: Verificar que el manifiesto no contradice el roadmap**

Run:

```powershell
Select-String -Path docs/superpowers/BOUNDARY-framework-vs-portal-fps.md -Pattern 'Nunca.*merge `feature/backoffice-api-integration`'
Select-String -Path docs/superpowers/BOUNDARY-framework-vs-portal-fps.md -Pattern "'payments'\s*=>\s*false"
```

Expected: primera búsqueda encuentra la prohibición de merge; segunda encuentra `'payments' => false` (regex, **sin** `-SimpleMatch`).

- [ ] **Step 4: Commit**

```powershell
git add docs/superpowers/BOUNDARY-framework-vs-portal-fps.md docs/superpowers/FPS-delta-paths-main-to-feature.txt
git commit -m "docs(fps): boundary manifest and delta path classification"
```

---

### Task 3: Gate de completitud Plan 00 (docs-only)

**Files:**
- Modify: `.superpowers/sdd/progress.md` (crear si no existe)

**Interfaces:**
- Consumes: Tasks 1–2 commits en `consolidation/framework-portal-separation`.
- Produces: registro SDD; confirmación de cero cambios runtime.

- [ ] **Step 1: Confirmar ausencia de cambios runtime**

Run:

```powershell
git diff --name-only HEAD~2..HEAD
git diff --name-only HEAD~2..HEAD | Select-String -Pattern '\.(php|sql|json)$' -NotMatch | ForEach-Object { $_ }
git status --short
```

Expected: solo archivos bajo `docs/superpowers/`; `git status` limpio tras commits.

- [ ] **Step 2: Registrar progreso SDD**

Append to `.superpowers/sdd/progress.md`:

```markdown
## Plan 00 — Inventario y rama consolidación (2026-07-17)

- [x] Rama `consolidation/framework-portal-separation` desde `main`
- [x] `FPS-git-baseline.md` con SHAs
- [x] `BOUNDARY-framework-vs-portal-fps.md` + delta paths
- [x] Sin cambios runtime
- Gate: N/A (docs only)
- Siguiente: Plan 01 generic payments
```

- [ ] **Step 3: Commit progreso**

```powershell
git add .superpowers/sdd/progress.md
git commit -m "docs(fps): record Plan 00 completion in SDD progress"
```

---

## Self-review (author)

| Requisito roadmap Plan 00 | Task |
|---------------------------|------|
| Registrar SHAs main/feature/merge-base/docs | Task 1 Step 1 + Step 5 + FPS-git-baseline.md |
| Restaurar planes FPS desde rama docs | Task 1 Step 4 |
| Crear rama consolidación desde main | Task 1 Step 3 |
| Tabla D1–D11 inline | Sección *Deuda técnica* + BOUNDARY resumen |
| Clasificar delta plataforma/Portal/mixto | Task 2 BOUNDARY doc |
| Enumerar paths permitidos/prohibidos Plan 01 | Task 2 allowlist/prohibitlist |
| Documentar main ya contiene Portal (PR5) | BOUNDARY header + FPS-git-baseline |
| Sin cambios runtime | Task 3 gate |
| No merge feature→main | Global Constraints + BOUNDARY Explicit NO |

Placeholder scan: ningún TBD/TODO/"similar a".  
Type consistency: SHAs de 40 chars; rama `consolidation/framework-portal-separation`; SHA Portal `dad0590`.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-17-fps-00-inventory-consolidation-branch.md`. Two execution options:

**1. Subagent-Driven (recommended)** — fresh subagent per task, review between tasks

**2. Inline Execution** — executing-plans with checkpoints

**Which approach?**
