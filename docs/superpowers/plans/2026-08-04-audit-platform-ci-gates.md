# Platform CI Gates (GitHub Actions) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir GitHub Actions en `Lebytek_Framework` que ejecute gates reproducibles del harness (`php tests/run.php` fast + Integrations con MySQL) en cada PR y push a `main`, con test gate Docs que impida regresiones silenciosas.

**Architecture:** Infra del repo fuente — el workflow vive en `.github/workflows/platform-tests.yml`, no en `src/` ni en el paquete Composer. Enfoque B del spec: job `platform-fast-gates` (Kernel, Docs, SkeletonPurity, Crud, Payments, Install + `composer validate` + `composer audit`) bloquea merge; job `platform-integration-gates` levanta MySQL 8.0, aplica schema plataforma vía scripts existentes y corre `php tests/run.php Integrations`. Sin secrets de producción; sin CI Portal.

**Tech Stack:** PHP 8.3 (runner `ubuntu-latest`), Composer 2.x, GitHub Actions (`actions/checkout@v4`, `shivammathur/setup-php@v2`), MySQL 8.0 service container, harness `tests/run.php` + `tests/lib/microtest.php`, scripts `scripts/migrate.php` y `scripts/install.php`.

**Source spec:** `docs/superpowers/specs/2026-08-04-audit-platform-ci-gates-design.md`  ·  **Modo:** normal

**Source audit PR:** #67 — https://github.com/Parzival2103/Lebytek_Framework/pull/67 (hallazgo D7 CI ausente; mergeado 2026-08-02)

**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main`; rama de trabajo `feature/platform-ci-gates` (creable desde `main` — verificado `git ls-remote origin refs/heads/main` @ `c78e672`)

## Global Constraints

- **No** editar `src/`, `database/`, `skeleton/`, `app/` salvo que un test existente lo exija (AC9: diff limitado a `.github/`, `tests/Docs/CiWorkflowPresentTest.php`, `docs/core/despliegue-y-versionado.md`).
- **No** secrets de producción (Stripe live, DB prod, SSH VPS) en el workflow — sólo credenciales efímeras del servicio MySQL del runner.
- `EnvLoader::load()` exige archivo `.env` presente; CI debe ejecutar `cp .env.example .env` antes de scripts/tests que bootstrapean BD.
- Variables `DB_*` del bloque `env:` del workflow tienen precedencia sobre `.env.example` (`EnvLoader` no sobrescribe claves ya en `$_ENV`).
- Branch protection en GitHub (**F6**) — documentar para operador; **no** configurar en automation.
- Semver del paquete **no** cambia — infra repo only.

## Requisitos → tareas (matriz)

| Requisito spec | Owner | Tarea | Verificación |
|----------------|-------|-------|--------------|
| F1 workflow fast + integration | Framework | Task 2–3 | workflow YAML + Actions verde |
| F2 suites fast + validate + audit | Framework | Task 2 | job `platform-fast-gates` |
| F3 MySQL + migrate + Integrations | Framework | Task 3 | job `platform-integration-gates` |
| F4 `CiWorkflowPresentTest` | Framework | Task 1 | TDD rojo→verde |
| F5 doc § CI | Framework | Task 4 | sección en `despliegue-y-versionado.md` |
| F6 branch protection | Ops | Task 4 Step 3 | doc operador — manual |
| U1–U6 UX CI | Framework | Task 4 | copy accionable en doc + test messages |
| Portal CI | Portal | **Fuera de alcance** | spec futuro `Lebytek_Portal` |
| M3–M6, D6 | varios | **Fuera de alcance** | carry-forward spec |

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `.github/workflows/platform-tests.yml` | Workflow CI: jobs fast + integration |
| `tests/Docs/CiWorkflowPresentTest.php` | Gate TDD — assert workflow existe y contrato |
| `docs/core/despliegue-y-versionado.md` | Nueva sección «CI / gates PR» (F5, U1, F6) |

**Scripts consumidos (sin modificar):**

- `scripts/migrate.php` — aplica `database/schema/schema.sql` vía `PackagePaths::schema()`
- `scripts/install.php --modules=integrations` — bootstrap `database/schema/modules/integrations.sql` (tablas `int_accounts`, `int_logs`)

**Interfaces producidas:**

- Status checks GitHub: `platform-fast-gates`, `platform-integration-gates`
- Test filter: `php tests/run.php Docs/CiWorkflowPresent` → 4 tests PASS post-implementación

---

### Task 1: Test gate `CiWorkflowPresentTest` (TDD — rojo antes del workflow)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/platform-ci-gates`

**Depends on:** None

**Files:**
- Create: `tests/Docs/CiWorkflowPresentTest.php`
- Test: `tests/Docs/CiWorkflowPresentTest.php`

**Interfaces:**
- Consumes: ausencia de `.github/workflows/platform-tests.yml` (estado pre-implementación @ `c78e672`)
- Produces: test que falla con mensaje accionable citando spec D7 y ruta del workflow esperado

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Docs/CiWorkflowPresentTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$workflowPath = $root . '/.github/workflows/platform-tests.yml';

test('platform CI workflow file exists at .github/workflows/platform-tests.yml', function () use ($workflowPath): void {
    assert_true(
        is_readable($workflowPath),
        'missing .github/workflows/platform-tests.yml — add workflow per spec D7 '
        . '(docs/superpowers/specs/2026-08-04-audit-platform-ci-gates-design.md)'
    );
});

test('platform-tests.yml references php tests/run.php and fast suites', function () use ($workflowPath): void {
    $src = (string) file_get_contents($workflowPath);
    assert_true(str_contains($src, 'php tests/run.php'), 'workflow must run php tests/run.php');
    assert_true(
        str_contains($src, 'Kernel') || str_contains($src, 'Docs'),
        'workflow must run at least Kernel or Docs suite (fast gates)'
    );
});

test('platform-tests.yml declares mysql service and DB migrate for Integrations', function () use ($workflowPath): void {
    $src = (string) file_get_contents($workflowPath);
    assert_true(str_contains($src, 'mysql:8'), 'workflow must use mysql:8.x service container');
    assert_true(
        str_contains($src, 'scripts/migrate.php') || str_contains($src, 'scripts/install.php'),
        'workflow must apply platform schema before Integrations tests'
    );
});

test('platform-tests.yml exposes human-readable job names', function () use ($workflowPath): void {
    $src = (string) file_get_contents($workflowPath);
    assert_true(str_contains($src, 'platform-fast-gates'), 'job name platform-fast-gates required (U3)');
    assert_true(str_contains($src, 'platform-integration-gates'), 'job name platform-integration-gates required (U3)');
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/CiWorkflowPresent` / Expected: **FAIL** — `missing .github/workflows/platform-tests.yml` (test 1); tests 2–4 pueden fallar por `file_get_contents` si el archivo no existe.

- [ ] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Tasks 2–3 crean el workflow.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/CiWorkflowPresent` / Expected: FAIL (TDD rojo confirmado).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs/DeployScriptsRemoved` / Expected: PASS — 3 tests, 0 failed (no regresión en suite Docs existente).

- [ ] **Step 6: Commit** — archivos: `tests/Docs/CiWorkflowPresentTest.php` / mensaje: `test(docs): add CiWorkflowPresentTest gate for platform CI (red)`

---

### Task 2: Workflow job `platform-fast-gates` (F1, F2)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/platform-ci-gates`

**Depends on:** Task 1

**Files:**
- Create: `.github/workflows/platform-tests.yml`
- Test: `tests/Docs/CiWorkflowPresentTest.php`

**Interfaces:**
- Consumes: `composer.json`, `composer.lock`, suites bajo `tests/`
- Produces: job `platform-fast-gates` con PHP 8.3, `composer validate --strict`, `composer audit --no-dev`, suites Kernel/Docs/SkeletonPurity/Crud/Payments/Install

- [ ] **Step 1: Escribir el test que falla** — tests Task 1 ya rojos.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/CiWorkflowPresent` / Expected: FAIL.

- [ ] **Step 3: Implementar el cambio mínimo** — crear `.github/workflows/platform-tests.yml` con al menos el job fast (el job integration se completa en Task 3; incluir stub `platform-integration-gates` comentado o placeholder que Task 3 sustituye):

```yaml
name: Platform tests

on:
  pull_request:
    branches: [main]
  push:
    branches: [main]

jobs:
  platform-fast-gates:
    name: platform-fast-gates
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, curl, zip, sqlite3, pdo_mysql
          coverage: none

      - name: Validate composer.json
        run: composer validate --strict

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Composer audit
        run: composer audit --no-dev

      - name: Prepare harness .env
        run: cp .env.example .env

      - name: Kernel tests
        run: php tests/run.php Kernel

      - name: Docs tests
        run: php tests/run.php Docs

      - name: SkeletonPurity tests
        run: php tests/run.php SkeletonPurity

      - name: Crud tests
        run: php tests/run.php Crud

      - name: Payments tests
        run: php tests/run.php Payments

      - name: Install tests
        run: php tests/run.php Install
```

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/CiWorkflowPresent` / Expected: tests 1–2 y 4 **PASS**; test 3 (**mysql**) **FAIL** hasta Task 3.

- [ ] **Step 5: Regresión relevante** — Run local (con PHP disponible):

```bash
cp .env.example .env
composer validate --strict
composer install --no-interaction
php tests/run.php Kernel
php tests/run.php Docs
```

Expected: exit 0 en cada comando (distinción: si PHP ausente en agente cloud, documentar como bloqueador de entorno — no marcar verde sin evidencia).

- [ ] **Step 6: Commit** — archivos: `.github/workflows/platform-tests.yml` / mensaje: `ci: add platform-fast-gates job for harness suites`

---

### Task 3: Job `platform-integration-gates` con MySQL 8 (F1, F3)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/platform-ci-gates`

**Depends on:** Task 2

**Files:**
- Modify: `.github/workflows/platform-tests.yml` (añadir job integration completo)
- Test: `tests/Docs/CiWorkflowPresentTest.php`, `tests/Integrations/IntegrationAccountRepositoryTest.php`

**Interfaces:**
- Consumes: job fast (opcional `needs: platform-fast-gates`); servicio `mysql:8.0`; `scripts/migrate.php`; `scripts/install.php --modules=integrations`
- Produces: suite `Integrations` verde contra MySQL real; status check `platform-integration-gates`

- [ ] **Step 1: Escribir el test que falla** — test 3 de `CiWorkflowPresentTest` rojo.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/CiWorkflowPresent` / Expected: FAIL en test mysql/DB.

- [ ] **Step 3: Implementar el cambio mínimo** — añadir al final de `.github/workflows/platform-tests.yml`:

```yaml
  platform-integration-gates:
    name: platform-integration-gates
    runs-on: ubuntu-latest
    needs: platform-fast-gates
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: lebytek_ci
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -h 127.0.0.1 -uroot -proot"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5
    env:
      DB_HOST: 127.0.0.1
      DB_PORT: 3306
      DB_DATABASE: lebytek_ci
      DB_USERNAME: root
      DB_PASSWORD: root
      APP_ENV: local
      APP_KEY: ci-test-key-32-characters-min!!
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, curl, zip, sqlite3, pdo_mysql
          coverage: none

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Prepare harness .env
        run: cp .env.example .env

      - name: Apply platform schema
        run: php scripts/migrate.php

      - name: Bootstrap integrations module
        run: php scripts/install.php --modules=integrations

      - name: Integrations tests
        run: php tests/run.php Integrations
```

Si `install.php --modules=integrations` falla por dependencias, usar en su lugar:

```bash
php -r "
require 'vendor/autoload.php';
\$pdo = Lebytek\\Framework\\Kernel\\Database\\Connection::getInstance();
\$pdo->exec(file_get_contents('database/schema/modules/integrations.sql'));
echo 'integrations_sql_ok'.PHP_EOL;
"
```

(sólo si el step anterior falla — preferir `install.php` por alinear tracking `cfg_modulos`).

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/CiWorkflowPresent` / Expected: **PASS** — 4 tests, 0 failed.

- [ ] **Step 5: Regresión relevante** — Con MySQL local (Docker o servicio):

```bash
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=lebytek_ci DB_USERNAME=root DB_PASSWORD=root
mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS lebytek_ci;"
cp .env.example .env
composer install --no-interaction
php scripts/migrate.php
php scripts/install.php --modules=integrations
php tests/run.php Integrations
php tests/run.php
```

Expected: Integrations ≥ 1 test PASS contra BD real (`IntegrationAccountRepositoryTest`); suite completa 0 failed.

- [ ] **Step 6: Commit** — archivos: `.github/workflows/platform-tests.yml` / mensaje: `ci: add platform-integration-gates job with mysql:8.0 service`

---

### Task 4: Documentación § CI en `despliegue-y-versionado.md` (F5, F6, U1, U6)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/platform-ci-gates`

**Depends on:** Task 3

**Files:**
- Modify: `docs/core/despliegue-y-versionado.md` (insertar sección tras «Versionado y actualización», antes de «Checklist pre/post deploy»)
- Test: `tests/Docs/CiWorkflowPresentTest.php`

**Interfaces:**
- Consumes: workflow Task 2–3; comandos locales equivalentes
- Produces: sección «CI / gates PR» con reproducibilidad local (U1) e instrucción branch protection (F6/U6)

- [ ] **Step 1: Escribir el test que falla** — N/A (doc-only; gate ya verde en Task 3).

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — N/A.

- [ ] **Step 3: Implementar el cambio mínimo** — insertar en `docs/core/despliegue-y-versionado.md` antes de `## Checklist pre/post deploy por entorno`:

````markdown
## CI / gates PR

El repositorio Framework ejecuta gates automáticos vía GitHub Actions (`.github/workflows/platform-tests.yml`).

### Jobs

| Job | Qué ejecuta | Bloquea merge |
|-----|-------------|---------------|
| `platform-fast-gates` | `composer validate --strict`, `composer audit --no-dev`, suites Kernel, Docs, SkeletonPurity, Crud, Payments, Install | Recomendado required check |
| `platform-integration-gates` | MySQL 8.0 service → `php scripts/migrate.php` → `php scripts/install.php --modules=integrations` → `php tests/run.php Integrations` | Recomendado required check |

### Reproducir localmente (equivalente al job fast)

```bash
composer validate --strict
composer install --no-interaction
cp .env.example .env
composer audit --no-dev
php tests/run.php Kernel
php tests/run.php Docs
php tests/run.php SkeletonPurity
php tests/run.php Crud
php tests/run.php Payments
php tests/run.php Install
```

### Reproducir Integrations (equivalente al job integration)

Requiere MySQL 8 accesible. Exportar `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (ver `.env.example`), luego:

```bash
cp .env.example .env
php scripts/migrate.php
php scripts/install.php --modules=integrations
php tests/run.php Integrations
```

Si el job integration falla con error PDO, verificar health-check del servicio MySQL y que `DB_*` del workflow coinciden con la base creada (`lebytek_ci` en CI).

### Branch protection (operador humano)

Tras merge del workflow a `main`:

1. GitHub → **Settings** → **Branches** → regla para `main`
2. Marcar **Require status checks to pass**
3. Seleccionar: `platform-fast-gates` y `platform-integration-gates`

La automation no configura branch protection; documentado aquí para el operador (F6).
````

- [ ] **Step 4: Verificación enfocada** — Run: `grep -c 'platform-fast-gates' docs/core/despliegue-y-versionado.md` / Expected: salida ≥ `2`.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs` / Expected: PASS incluyendo `CiWorkflowPresentTest`.

- [ ] **Step 6: Commit** — archivos: `docs/core/despliegue-y-versionado.md` / mensaje: `docs: add CI gates section to despliegue-y-versionado.md`

---

### Task 5: Verificación final, PR y evidencia Actions (AC1–AC9)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/platform-ci-gates`

**Depends on:** Task 4

**Files:**
- Test: suite completa

**Interfaces:**
- Consumes: Tasks 1–4
- Produces: PR hacia `main`; enlace run Actions verde; evidencia AC4 (semver fail reproducible)

- [ ] **Step 1: Escribir el test que falla** — N/A.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — N/A.

- [ ] **Step 3: Implementar el cambio mínimo** — N/A.

- [ ] **Step 4: Verificación enfocada** — Run:

```bash
git diff origin/main...HEAD --name-only
php tests/run.php Docs/CiWorkflowPresent
php tests/run.php Docs
php tests/run.php Kernel
php tests/run.php SkeletonPurity
```

Expected: diff contiene **sólo** `.github/workflows/platform-tests.yml`, `tests/Docs/CiWorkflowPresentTest.php`, `docs/core/despliegue-y-versionado.md` (AC9); tests PASS.

- [ ] **Step 5: Regresión relevante** — Push rama y verificar Actions:

```bash
git push -u origin feature/platform-ci-gates
gh pr create --base main --title "ci: platform CI gates (D7)" \
  --body "Implementa F1–F5 del spec 2026-08-04.

- platform-fast-gates + platform-integration-gates
- CiWorkflowPresentTest gate
- docs/core/despliegue-y-versionado.md § CI

Spec: docs/superpowers/specs/2026-08-04-audit-platform-ci-gates-design.md
Audit: #67 (D7)"
```

Expected: workflow ejecuta en PR; ambos jobs **verde** en tip limpio. Validación manual AC4: rama temporal con `config/app.php` version desincronizada → job Docs **rojo**.

Validación manual AC6 (una vez): pin deliberado `dompdf/dompdf` `<3.1.6` en rama throwaway → `composer audit` **rojo**.

- [ ] **Step 6: Commit / PR** — si quedan cambios pendientes, commit final / mensaje: `chore(ci): finalize platform CI gates evidence`

**Requiere operador humano:** sí — habilitar branch protection post-merge (F6); validaciones AC4/AC6 opcionales en rama throwaway.

---

## Fuera de alcance

| ID | Tema | Motivo |
|----|------|--------|
| Portal CI | `Lebytek_Portal` | Ownership Portal; M6 bloquea verificación |
| Branch protection config | GitHub Settings | F6 — operador manual |
| Tag semver / release paquete | Framework | AC9 — infra repo only |
| M3 RBAC router, M4 API health, M5 permisos | Framework | carry-forward CF6–CF8 |
| D6 skeleton.lebytek.com VPS | Ops | plan `2026-07-26` |
| PHP preinstalado en agentes cloud Cursor | Entorno | mejora separada |
| Matrix PHP 8.1 + 8.3 | Framework | iteración 2 si maintainer lo pide |

## Criterios finales de aceptación

- [ ] `.github/workflows/platform-tests.yml` con jobs `platform-fast-gates` y `platform-integration-gates` (AC1).
- [ ] PR de implementación ejecuta workflow; status verde en tip `main` limpio (AC2).
- [ ] `php tests/run.php Docs/CiWorkflowPresent` PASS (AC3).
- [ ] Desincronización semver deliberate falla job Docs en CI (AC4 — validación manual throwaway).
- [ ] Job integration ejecuta `IntegrationAccountRepositoryTest` contra MySQL real (AC5).
- [ ] `composer audit` falla si dompdf `<3.1.6` (AC6 — validación manual throwaway).
- [ ] § CI documentado con comandos locales equivalentes (AC7).
- [ ] Workflow sin secrets de producción (AC8).
- [ ] Diff no incluye `src/`, `app/`, `database/`, `skeleton/` (AC9).

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| MySQL health-check timeout | `--health-retries=5`; hint en doc § CI (U4) |
| `install.php` falla en CI | fallback SQL directo integrations.sql documentado Task 3 |
| `composer audit` advisory futuro | PR dedicado update deps; no silenciar |
| Fork PRs sin secrets | usar trigger `pull_request` estándar, no `pull_request_target` |

**Rollback:** revertir PR del workflow; deshabilitar workflow en GitHub UI; quitar required checks si se configuraron.

## Evidencia que debe recopilar el ejecutor

- URL run Actions verde (ambos jobs).
- Salida `php tests/run.php Docs/CiWorkflowPresent` local.
- Captura o log de fallo semver deliberate (AC4) si se ejecuta.
- Número PR Framework.

---

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Reconciliación UTC | 2026-08-04T12:40:00Z |
| Plan creado UTC | 2026-08-04T12:40:00Z |
| Framework `origin/main` referencia | `c78e672b73b8259a6cab6a7126aaf45354dded09` |
| Tareas completadas / totales | **0 / 5** |
| Modo fuente | normal (spec Nivel A — `docs/superpowers/specs/2026-08-04-audit-platform-ci-gates-design.md` @ PR #78) |
| Siguiente tarea ejecutable | **Task 1** — `CiWorkflowPresentTest` (TDD rojo) |
| Prerrequisitos | Ninguno — `composer.lock` presente; scripts migrate/install existentes |
| Bloqueos | PHP/MySQL ausentes en agente cloud actual — no impiden plan; ejecutor con PHP 8.3+ y MySQL local o Actions |
| Estado | **Pendiente de implementación** |
