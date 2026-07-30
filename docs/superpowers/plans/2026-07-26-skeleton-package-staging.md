# Skeleton Package Publication & skeleton.lebytek.com Implementation Plan

> **Contexto entornos:** [`docs/ENVIRONMENTS.md`](../../ENVIRONMENTS.md) — skeleton (este plan) vs staging Portal (futuro) vs prod.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publicar `skeleton/` como paquete Composer `lebytek/skeleton` y desplegar **skeleton.lebytek.com** sobre ese paquete (framework puro + plantilla base para pruebas del paquete), con BD propia aislada de producción, eliminando antes los scripts de despliegue destructivos y después la rama `feature/backoffice-api-integration` (solo con orden explícita).

**Architecture:** `Lebytek_Framework@main` sigue siendo la fuente de verdad: `src/` es el paquete `lebytek/framework` y `skeleton/` la plantilla genérica protegida por `SkeletonPurityTest`. Un `git subtree split --prefix=skeleton` publica esa plantilla como raíz del repo espejo `Parzival2103/Lebytek_Skeleton`, que se instala en el VPS con `composer create-project`. El skeleton deja de declarar el repositorio `path` del monorepo y pasa a declarar el repositorio VCS del framework con `^1.1`, de modo que el split sale literal y no hay que transformar archivos al publicar.

**Tech Stack:** PHP 8.1+ (VPS: CLI 8.4.22, pool php-fpm 8.4 en `:19001`), Composer 2.10.1, git 2.43 con `subtree`, `gh` CLI, CloudPanel `clpctl`, nginx, MariaDB/MySQL, harness de tests propio (`php tests/run.php`, `tests/lib/microtest.php`).

## Global Constraints

- Repo espejo: `Parzival2103/Lebytek_Skeleton`, **privado**, creado con `gh repo create`.
- Versión del framework consumida en todas partes: **`v1.1.0`**; constraint declarada: **`^1.1`**.
- URL VCS del framework en `skeleton/composer.json`: **`https://github.com/Parzival2103/Lebytek_Framework`**.
- `skeleton/composer.json` versionado **nunca** declara un repositorio de tipo `path`; el modo monorepo se activa a demanda con `composer config repositories.local path ../` y no se commitea.
- Directorio archivado de staging: **`skeleton.lebytek.com.portal-copy-20260726`** (renombrar, nunca borrar).
- BD de staging: **`lebytek_stg`**; usuario y password: **los mismos de producción** (decisión del responsable del proyecto).
- `.env` de staging: `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://skeleton.lebytek.com`, `SESSION_SECURE=true`, `APP_KEY` nuevo de 32 caracteres.
- **`lebytek.com` y `waapi.lebytek.com` no se modifican en ningún paso.** La BD `lebytek` de producción no se toca.
- **`nginx` no se toca**: el vhost de staging ya apunta a `.../public` y el pool php-fpm 8.4 (`:19001`) con usuario `lebytek-stg` ya existe.
- Orden obligatorio: limpieza de scripts obsoletos (Task 1) **antes** de la eliminación de la rama (Task 10).
- Ningún paso del VPS se ejecuta sin que el anterior haya verificado; cada uno es reversible por separado.

## Desviaciones del spec (leer antes de empezar)

Cinco puntos que el spec no cubre y que este plan resuelve explícitamente. Ninguno cambia el objetivo; los cinco son necesarios para que el trabajo sea ejecutable y seguro.

1. **`SkeletonPurityTest` fija hoy el repositorio `path`.** `tests/Kernel/SkeletonPurityTest.php:111-118` afirma `assert_same('path', $repos[0]['type'])`. El componente 1 del spec invierte exactamente eso, así que el test se actualiza en la misma tarea (Task 2). Sin ese cambio, el spec deja la suite en rojo.

2. **La verificación `grep -rn "feature/backoffice-api-integration"` → sin resultados no es alcanzable tal cual.** Hay 24 archivos en Framework y 9 en Portal que nombran la rama, casi todos planes y specs históricos; además `tests/Docs/FpsPublicationReadinessTest.php:22` **exige** que `docs/CUTOVER-PORTAL.md` contenga esa cadena. Este plan limpia las referencias **operativas** (scripts ejecutables, `CLAUDE.md` de ambos repos, `docs/DEPLOY-VPS.md`, `docs/composer-setup.md` §6) y conserva las **históricas** bajo `docs/superpowers/plans/`, `docs/superpowers/specs/`, `docs/superpowers/FPS-*` y `docs/CUTOVER-PORTAL.md`, que son registro de lo ocurrido, no instrucciones vigentes. La verificación se redefine en consecuencia (Task 9).

3. **La rama sólo existe en `Lebytek_Framework`.** Verificado con `gh api repos/.../branches`: en `Lebytek_Portal` no existe ni local ni en `origin`. En Portal, Task 10 es un no-op comprobado; lo que sí se limpia allí son las **referencias** (Task 1).

4. **`composer create-project` necesita autenticación en el VPS.** Ambos repos (`Lebytek_Skeleton` y `Lebytek_Framework`) son privados, y el usuario `lebytek-stg` no tiene credencial. Task 6 configura `auth.json` antes del `create-project`; sin eso el comando falla con 404 de GitHub.

5. **`install.php` por CLI no crea admin ni cierra el asistente web.** El instalador CLI no escribe `storage/install.lock` (sólo lo hace `public/install/steps.php:112`) y no crea usuario. Además `public/install/index.php:53-60` sólo exige `INSTALL_TOKEN` cuando `APP_ENV === 'production'`: con `APP_ENV=staging` el asistente quedaría **público y sin token** en cuanto el certificado sea válido. Task 7 crea el admin y escribe el lock antes de que Task 8 exponga el sitio.

Añadido de seguridad, fuera del texto del spec pero dentro de su intención: Task 10 crea el tag `archive/backoffice-api-integration` antes de borrar la rama, para que los commits congelados que citan `docs/superpowers/FPS-git-baseline.md` (SHA `dad0590…`) no queden inalcanzables y sujetos a GC. Un tag no es una rama y ningún despliegue lo consume, así que la razón del spec para borrar («ningún despliegue la consume») se mantiene intacta.

## File Structure

**`Lebytek_Framework`** (rama base: `main` — Task 1 Framework mergeada vía PR #36; rama `docs/skeleton-package-staging-design` eliminada post-merge 2026-07-27)

| Archivo | Responsabilidad |
|---|---|
| `scripts/vps-deploy-lebytek-com.sh` | **eliminar** — despliegue monorepo destructivo desde la rama a borrar |
| `scripts/vps-deploy-waapi.sh` | **eliminar** — idéntico, sobre `waapi.lebytek.com` |
| `scripts/vps-deploy-skeleton.sh` | **eliminar** — sustituido por `publish-skeleton.sh` + `create-project` |
| `scripts/publish-skeleton.sh` | **crear** — única responsabilidad: `skeleton/` → raíz del repo espejo |
| `skeleton/composer.json` | **modificar** — repo VCS del framework + `^1.1` + `minimum-stability: stable` |
| `skeleton/README.md` | **crear** — se convierte en el README del espejo vía split; declara la regla «artefacto generado» |
| `docs/composer-setup.md` | **modificar** §6 — el pin a la rama borrada pasa a ser el harness local `path` no versionado |
| `tests/Kernel/SkeletonPurityTest.php` | **modificar** — el test del repo `path` pasa a exigir el repo VCS |
| `tests/Docs/DeployScriptsRemovedTest.php` | **crear** — guarda de regresión: no vuelven los `vps-deploy-*.sh` ni pins a la rama en `scripts/` |
| `tests/Docs/PublishSkeletonScriptTest.php` | **crear** — guarda de contrato del script de publicación y del README del skeleton |

**`Lebytek_Portal`** (rama de trabajo: `chore/deploy-scripts-cleanup`, a crear desde `main` — **no verificada** desde Framework; token gh sin acceso Portal M6/D3)

| Archivo | Responsabilidad |
|---|---|
| `scripts/vps-deploy-lebytek-com.sh` | **eliminar** |
| `scripts/vps-deploy-waapi.sh` | **eliminar** |
| `docs/DEPLOY-VPS.md` | **modificar** — tres secciones obsoletas (pin, secuencia, prohibiciones) |
| `tests/Kernel/DeployScriptsRemovedTest.php` | **crear** — misma guarda + contrato del runbook |

**`Parzival2103/Lebytek_Skeleton`** — repo espejo, sin edición manual: su contenido es siempre la salida del split.

**VPS `srv1586067` (2.24.197.198)** — `/home/lebytek-stg/htdocs/skeleton.lebytek.com` (nuevo), `.../skeleton.lebytek.com.portal-copy-20260726` (archivo), BD `lebytek_stg`.

---

### Task 1: Eliminar los scripts de despliegue destructivos y corregir el runbook

Primera tarea de forma deliberada: `scripts/vps-deploy-lebytek-com.sh` hace `find "$APP_DIR" -mindepth 1 -maxdepth 1 ! -name '.env' -exec rm -rf {} +` y repuebla clonando `feature/backoffice-api-integration`. Ejecutado hoy revierte la separación Framework/Portal; ejecutado tras borrar la rama, el `rm -rf` corre igual y el clone falla: `lebytek.com` queda vacío. Neutralizar eso va antes de tocar nada más.

**Files:**
- Delete: `Lebytek_Framework/scripts/vps-deploy-lebytek-com.sh`
- Delete: `Lebytek_Framework/scripts/vps-deploy-waapi.sh`
- Delete: `Lebytek_Framework/scripts/vps-deploy-skeleton.sh`
- Delete: `Lebytek_Portal/scripts/vps-deploy-lebytek-com.sh`
- Delete: `Lebytek_Portal/scripts/vps-deploy-waapi.sh`
- Modify: `Lebytek_Portal/docs/DEPLOY-VPS.md:24-37` (Composer switch), `:39-48` (Deploy sequence), `:67-73` (Forbidden)
- Test: `Lebytek_Framework/tests/Docs/DeployScriptsRemovedTest.php` (crear)
- Test: `Lebytek_Portal/tests/Kernel/DeployScriptsRemovedTest.php` (crear)

**Interfaces:**
- Consumes: nada de tareas previas.
- Produces: la ausencia de `scripts/vps-deploy-*.sh` en ambos repos, precondición dura de Task 10. Los tests `DeployScriptsRemovedTest` quedan como guarda permanente.

- [x] **Step 1: Escribir el test que falla en Framework** — evidencia: `tests/Docs/DeployScriptsRemovedTest.php` @ `origin/main`, PR #36

Crear `Lebytek_Framework/tests/Docs/DeployScriptsRemovedTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('framework ships no monorepo-era vps deploy scripts', function () use ($root): void {
    $found = glob($root . '/scripts/vps-deploy-*.sh') ?: [];
    $names = array_map('basename', $found);
    assert_same([], $names, 'scripts/vps-deploy-*.sh must not exist: ' . implode(', ', $names));
});

test('no shell script pins the frozen backoffice branch', function () use ($root): void {
    foreach (glob($root . '/scripts/*.sh') ?: [] as $script) {
        $src = (string) file_get_contents($script);
        assert_true(
            !str_contains($src, 'feature/backoffice-api-integration'),
            basename($script) . ' must not pin feature/backoffice-api-integration'
        );
    }
});

test('no shell script wipes a site directory before repopulating it', function () use ($root): void {
    foreach (glob($root . '/scripts/*.sh') ?: [] as $script) {
        $src = (string) file_get_contents($script);
        assert_true(
            !str_contains($src, "-exec rm -rf {} +"),
            basename($script) . ' must not bulk-delete a deployed site directory'
        );
    }
});
```

- [x] **Step 2: Ejecutar el test y confirmar que falla** — evidencia: ciclo TDD completado pre-merge PR #36

Run: `cd Lebytek_Framework && php tests/run.php Docs/DeployScriptsRemoved`
Expected: FAIL — los tres tests fallan (`vps-deploy-lebytek-com.sh, vps-deploy-skeleton.sh, vps-deploy-waapi.sh` presentes; dos de ellos con el pin a la rama y el `rm -rf`).

- [x] **Step 3: Borrar los tres scripts en Framework** — evidencia: `scripts/vps-deploy-*.sh` ausentes en `origin/main`

```bash
cd Lebytek_Framework
git rm scripts/vps-deploy-lebytek-com.sh scripts/vps-deploy-waapi.sh scripts/vps-deploy-skeleton.sh
```

- [x] **Step 4: Ejecutar el test y confirmar que pasa** — evidencia: PR #36 mergeado; test presente en `origin/main`

Run: `cd Lebytek_Framework && php tests/run.php Docs/DeployScriptsRemoved`
Expected: PASS — 3 tests, 0 failed.

- [ ] **Step 5: Escribir el test que falla en Portal**

Crear `Lebytek_Portal/tests/Kernel/DeployScriptsRemovedTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('portal ships no monorepo-era vps deploy scripts', function () use ($root): void {
    $found = glob($root . '/scripts/vps-deploy-*.sh') ?: [];
    $names = array_map('basename', $found);
    assert_same([], $names, 'scripts/vps-deploy-*.sh must not exist: ' . implode(', ', $names));
});

test('no shell script pins the frozen backoffice branch', function () use ($root): void {
    foreach (glob($root . '/scripts/*.sh') ?: [] as $script) {
        $src = (string) file_get_contents($script);
        assert_true(
            !str_contains($src, 'feature/backoffice-api-integration'),
            basename($script) . ' must not pin feature/backoffice-api-integration'
        );
    }
});

test('DEPLOY-VPS runbook documents the real pull-based deploy', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/DEPLOY-VPS.md');
    assert_true(
        str_contains($src, '"lebytek/framework": "^1.1"'),
        'runbook must pin the published package constraint ^1.1'
    );
    assert_true(
        !str_contains($src, 'dev-consolidation/framework-portal-separation'),
        'runbook must not pin the consolidation branch'
    );
    assert_true(
        !str_contains($src, 'feature/backoffice-api-integration'),
        'runbook must not reference the deleted branch'
    );
    assert_true(
        str_contains($src, 'git pull origin main'),
        'runbook must document the pull-based update cycle'
    );
});
```

- [ ] **Step 6: Ejecutar el test de Portal y confirmar que falla**

Run: `cd Lebytek_Portal && php tests/run.php Kernel/DeployScriptsRemoved`
Expected: FAIL — scripts presentes y el runbook aún pinea `dev-consolidation/framework-portal-separation`.

- [ ] **Step 7: Borrar los dos scripts en Portal**

```bash
cd Lebytek_Portal
git checkout -b chore/deploy-scripts-cleanup
git rm scripts/vps-deploy-lebytek-com.sh scripts/vps-deploy-waapi.sh
```

- [ ] **Step 8: Corregir la sección «Composer switch» de `docs/DEPLOY-VPS.md`**

Reemplazar íntegramente el bloque `## Composer switch (staging first)` (líneas 24-37) por:

````markdown
## Framework dependency (Composer)

Portal consumes `lebytek/framework` as a published, tagged package — never a branch pin:

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
],
"require": {
  "lebytek/framework": "^1.1"
}
```

`lebytek.com` and `waapi.lebytek.com` both run `v1.1.0`. Local day-to-day work may point at
a Framework checkout with `composer config repositories.local path ../Lebytek_Framework`;
that override is applied on demand and **never committed**.

`skeleton.lebytek.com` is **not** a Portal deployment. It runs the generic `lebytek/skeleton`
package — see `Lebytek_Framework/docs/superpowers/specs/2026-07-26-skeleton-package-staging-design.md`.
````

- [ ] **Step 9: Corregir la sección «Deploy sequence» de `docs/DEPLOY-VPS.md`**

Reemplazar íntegramente el bloque `## Deploy sequence (human executes on VPS)` (líneas 39-48) por:

````markdown
## Deploy sequence (human executes on VPS)

`lebytek.com` is a git clone of `Parzival2103/Lebytek_Portal@main` at
`/home/lebytek/htdocs/lebytek.com`, updated **manually**. There is no auto-pull: no deploy
cron, no systemd timer, no git hook, no webhook endpoint. The update cycle is:

```bash
cd /home/lebytek/htdocs/lebytek.com
git status                      # must be clean and on main
git pull origin main
composer install --no-dev --no-interaction --optimize-autoloader
php scripts/migrate.php         # platform SQL via package
php scripts/migrate-marketing.php
```

Then run the smoke tests below.

Never `rm -rf` the site directory and never re-clone over it: `.env`, `storage/` and
uploaded files exist only on the server. The monorepo-era `scripts/vps-deploy-*.sh` did
exactly that and were deleted for this reason.
````

- [ ] **Step 10: Corregir la sección «Forbidden» de `docs/DEPLOY-VPS.md`**

Reemplazar íntegramente el bloque `## Forbidden without explicit user order` (líneas 67-73) por:

```markdown
## Forbidden without explicit user order

- Any deploy step that deletes the site directory (`rm -rf`, `find … -exec rm -rf {} +`)
- `migrate --force` on production without backup
- Disabling RBAC, webhook signatures, or idempotency guards
- Editing production `.env` in git
- Pinning `lebytek/framework` to a branch on any deployed site
```

- [ ] **Step 11: Ejecutar el test de Portal y confirmar que pasa**

Run: `cd Lebytek_Portal && php tests/run.php Kernel/DeployScriptsRemoved`
Expected: PASS — 3 tests, 0 failed.

- [ ] **Step 12: Ejecutar ambas suites completas**

Run: `cd Lebytek_Framework && php tests/run.php`
Expected: 0 failed.

Run: `cd Lebytek_Portal && php tests/run.php`
Expected: 0 failed.

- [x] **Step 13: Commit en ambos repos** — evidencia Framework: PR #36 @ `23946f4`; Portal: **pendiente verificación**

```bash
cd Lebytek_Framework
git add scripts tests/Docs/DeployScriptsRemovedTest.php
git commit -m "chore: delete monorepo-era vps deploy scripts

Los tres scripts clonaban feature/backoffice-api-integration sobre el
directorio del sitio tras un rm -rf. Prerequisito de la eliminacion de
la rama. Guarda de regresion en tests/Docs/DeployScriptsRemovedTest.php.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

```bash
cd Lebytek_Portal
git add scripts docs/DEPLOY-VPS.md tests/Kernel/DeployScriptsRemovedTest.php
git commit -m "chore: delete vps deploy scripts and fix DEPLOY-VPS runbook

Elimina los dos scripts destructivos y corrige las tres secciones
obsoletas del runbook: pin ^1.1 en vez de rama, ciclo real git pull +
composer install, y prohibiciones actualizadas.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 2: Apuntar el skeleton al paquete publicado del framework

Hoy `skeleton/composer.json` declara el repositorio `path` del monorepo, que no es desplegable: publicado tal cual, `composer create-project` intentaría resolver `lebytek/framework` desde un directorio padre inexistente. Invertir el origen hace que el subtree split salga literal, sin transformar archivos al publicar.

**Files:**
- Modify: `Lebytek_Framework/skeleton/composer.json:6-26`
- Modify: `Lebytek_Framework/tests/Kernel/SkeletonPurityTest.php:111-118`
- Modify: `Lebytek_Framework/docs/composer-setup.md:121-137` (sección 6)

**Interfaces:**
- Consumes: nada de tareas previas.
- Produces: `skeleton/composer.json` con `require."lebytek/framework" === "^1.1"`, `repositories[0] === {"type":"vcs","url":"https://github.com/Parzival2103/Lebytek_Framework"}` y `minimum-stability === "stable"`. Task 4 publica ese archivo tal cual; Task 6 lo resuelve en el VPS.

- [ ] **Step 1: Reescribir el test de `SkeletonPurityTest` que fija el repo `path`**

En `Lebytek_Framework/tests/Kernel/SkeletonPurityTest.php`, sustituir el último test (líneas 111-118) por:

```php
test('skeleton composer.json consumes the published framework package', function () use ($skeleton): void {
    $data = json_decode((string) file_get_contents($skeleton . '/composer.json'), true);
    assert_same('lebytek/skeleton', $data['name'] ?? null);
    assert_same('project', $data['type'] ?? null);
    assert_same('^1.1', $data['require']['lebytek/framework'] ?? null);
    assert_same('stable', $data['minimum-stability'] ?? null);

    $repos = $data['repositories'] ?? [];
    assert_same(1, count($repos), 'skeleton must declare exactly one repository');
    assert_same('vcs', $repos[0]['type'] ?? null);
    assert_same('https://github.com/Parzival2103/Lebytek_Framework', $repos[0]['url'] ?? null);

    foreach ($repos as $repo) {
        assert_true(
            ($repo['type'] ?? '') !== 'path',
            'skeleton must not ship a monorepo path repo (local harness only, never committed)'
        );
    }
});
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `cd Lebytek_Framework && php tests/run.php Kernel/SkeletonPurity`
Expected: FAIL en el nuevo test — `expected '^1.1', got '*@dev'` (o el primer `assert_same` que rompa).

- [ ] **Step 3: Reescribir `skeleton/composer.json`**

Contenido completo del archivo:

```json
{
    "name": "lebytek/skeleton",
    "description": "Esqueleto minimo de aplicacion Lebytek (consume lebytek/framework)",
    "type": "project",
    "license": "proprietary",
    "require": {
        "php": ">=8.1",
        "lebytek/framework": "^1.1"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Parzival2103/Lebytek_Framework"
        }
    ],
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    },
    "config": {
        "sort-packages": true
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

- [ ] **Step 4: Ejecutar el test y confirmar que pasa**

Run: `cd Lebytek_Framework && php tests/run.php Kernel/SkeletonPurity`
Expected: PASS — 13 tests, 0 failed.

- [ ] **Step 5: Actualizar la sección 6 de `docs/composer-setup.md`**

Reemplazar íntegramente `## 6. Pin a branch de feature (desarrollo)` (líneas 121-137) por:

````markdown
## 6. Harness local del skeleton (desarrollo del framework)

`skeleton/composer.json` está versionado apuntando al **paquete publicado**: repositorio
VCS de `Lebytek_Framework` y constraint `"lebytek/framework": "^1.1"`. Ese archivo es lo
que se publica como `lebytek/skeleton` (ver `scripts/publish-skeleton.sh`) y lo que
consume `skeleton.lebytek.com`, así que no puede declarar rutas del monorepo.

Para probar contra el skeleton un cambio del framework aún no taggeado, añade el repo
local **a demanda y sin versionarlo**:

```bash
cd skeleton
composer config repositories.local path ../
composer update lebytek/framework
```

Al terminar, revertir el override antes de commitear o publicar:

```bash
cd skeleton
composer config --unset repositories.local
git checkout -- composer.json
```

`SkeletonPurityTest` falla si `skeleton/composer.json` llega a declarar un repositorio de
tipo `path`, de modo que el override nunca puede colarse en una publicación.
````

- [ ] **Step 6: Ejecutar la suite completa**

Run: `cd Lebytek_Framework && php tests/run.php`
Expected: 0 failed.

- [ ] **Step 7: Commit**

```bash
cd Lebytek_Framework
git add skeleton/composer.json tests/Kernel/SkeletonPurityTest.php docs/composer-setup.md
git commit -m "feat(skeleton): consume lebytek/framework ^1.1 via VCS repo

Invierte el origen del framework en skeleton/composer.json para que el
subtree split salga literal. SkeletonPurityTest pasa a prohibir el repo
path; el modo monorepo queda documentado como override local no versionado.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 3: Script de publicación del espejo y README del skeleton

`scripts/publish-skeleton.sh` tiene una única responsabilidad: tomar `skeleton/` de `Lebytek_Framework@main` y publicarlo como raíz de `Parzival2103/Lebytek_Skeleton`. El README del skeleton se crea aquí, no en el espejo: al viajar por el split se convierte en el README del espejo sin romper el fast-forward de futuras publicaciones.

**Files:**
- Create: `Lebytek_Framework/scripts/publish-skeleton.sh`
- Create: `Lebytek_Framework/skeleton/README.md`
- Test: `Lebytek_Framework/tests/Docs/PublishSkeletonScriptTest.php` (crear)

**Interfaces:**
- Consumes: `skeleton/composer.json` en su forma final (Task 2).
- Produces: `bash scripts/publish-skeleton.sh` — sin argumentos, exige `main` limpio y sincronizado con `origin/main`, publica `skeleton/` en `main` del espejo. Variables de entorno opcionales: `TAG` (si se define, empuja además ese tag al espejo), `MIRROR_URL` (por defecto `git@github.com:Parzival2103/Lebytek_Skeleton.git`). Task 4 lo invoca como `TAG=v1.1.0 bash scripts/publish-skeleton.sh`.

- [ ] **Step 1: Escribir el test que falla**

Crear `Lebytek_Framework/tests/Docs/PublishSkeletonScriptTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('publish-skeleton.sh splits the skeleton prefix into the mirror', function () use ($root): void {
    $path = $root . '/scripts/publish-skeleton.sh';
    assert_true(is_readable($path), 'missing scripts/publish-skeleton.sh');
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'set -euo pipefail'), 'must fail fast');
    assert_true(str_contains($src, 'git subtree split --prefix='), 'must use git subtree split');
    assert_true(str_contains($src, 'Parzival2103/Lebytek_Skeleton'), 'must target the mirror repo');
    assert_true(str_contains($src, 'git status --porcelain'), 'must refuse to publish a dirty tree');
    assert_true(str_contains($src, 'origin/main'), 'must refuse to publish an out-of-sync main');
});

test('publish-skeleton.sh never force-pushes the mirror', function () use ($root): void {
    $src = (string) file_get_contents($root . '/scripts/publish-skeleton.sh');
    assert_true(!str_contains($src, '--force'), 'force push must stay a manual, deliberate act');
    assert_true(!str_contains($src, 'push -f '), 'force push must stay a manual, deliberate act');
});

test('skeleton README declares the mirror is generated, not edited', function () use ($root): void {
    $path = $root . '/skeleton/README.md';
    assert_true(is_readable($path), 'missing skeleton/README.md');
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'publish-skeleton.sh'), 'README must name the publishing script');
    assert_true(str_contains($src, 'Lebytek_Framework'), 'README must point back to the source repo');
    assert_true(str_contains($src, 'composer create-project'), 'README must document consumption');
});
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `cd Lebytek_Framework && php tests/run.php Docs/PublishSkeletonScript`
Expected: FAIL — `missing scripts/publish-skeleton.sh` y `missing skeleton/README.md`.

- [ ] **Step 3: Crear `scripts/publish-skeleton.sh`**

```bash
#!/usr/bin/env bash
# publish-skeleton.sh — publica skeleton/ como raiz de Parzival2103/Lebytek_Skeleton.
#
# Uso:
#   bash scripts/publish-skeleton.sh              # publica main del espejo
#   TAG=v1.1.0 bash scripts/publish-skeleton.sh   # publica y ademas empuja el tag
#
# Sin argumentos posicionales. Lee siempre de main y falla si el arbol de trabajo
# esta sucio o si main difiere de origin/main.
#
# El repo espejo es un ARTEFACTO: nunca se edita a mano, siempre se regenera aqui.
# Requisitos: git 2.43+ con subtree, acceso push al repo espejo.

set -euo pipefail

MIRROR_URL="${MIRROR_URL:-git@github.com:Parzival2103/Lebytek_Skeleton.git}"
TAG="${TAG:-}"
PREFIX=skeleton
SPLIT_BRANCH=split/skeleton

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

if [[ -n "$(git status --porcelain)" ]]; then
  echo "ERROR: arbol de trabajo sucio. Commitea o descarta antes de publicar." >&2
  exit 1
fi

CURRENT="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$CURRENT" != "main" ]]; then
  echo "ERROR: publish-skeleton.sh solo se ejecuta desde main (HEAD=$CURRENT)." >&2
  exit 1
fi

git fetch origin main
if [[ "$(git rev-parse HEAD)" != "$(git rev-parse origin/main)" ]]; then
  echo "ERROR: main local difiere de origin/main. Sincroniza antes de publicar." >&2
  exit 1
fi

if [[ ! -f "$PREFIX/composer.json" ]]; then
  echo "ERROR: no existe $PREFIX/composer.json." >&2
  exit 1
fi

if grep -q '"type": *"path"' "$PREFIX/composer.json"; then
  echo "ERROR: $PREFIX/composer.json declara un repositorio path (override local sin revertir)." >&2
  exit 1
fi

echo "==> git subtree split --prefix=$PREFIX"
git branch -D "$SPLIT_BRANCH" 2>/dev/null || true
git subtree split --prefix="$PREFIX" -b "$SPLIT_BRANCH"

echo "==> push $SPLIT_BRANCH -> $MIRROR_URL main"
git push "$MIRROR_URL" "$SPLIT_BRANCH:main"

if [[ -n "$TAG" ]]; then
  echo "==> push tag $TAG -> $MIRROR_URL"
  git push "$MIRROR_URL" "$SPLIT_BRANCH:refs/tags/$TAG"
fi

echo "==> limpieza de $SPLIT_BRANCH"
git branch -D "$SPLIT_BRANCH"

echo ""
echo "Publicado. El espejo es un artefacto: no editarlo, regenerarlo desde aqui."
```

Si el push falla por no-fast-forward, alguien editó el espejo a mano: hay que decidir explícitamente si descartar esa edición (`git push --force …`) — el script nunca lo hace por su cuenta.

- [ ] **Step 4: Crear `skeleton/README.md`**

`````markdown
# lebytek/skeleton

Aplicación mínima que consume el paquete `lebytek/framework`. Autenticación, RBAC, menú
admin, dashboard, CRUD Engine y Kernel vienen del framework bajo `vendor/`; el código
propio vive en `app/`.

## Este repositorio es un artefacto generado

`Parzival2103/Lebytek_Skeleton` **no se edita directamente**. Es la publicación del
directorio `skeleton/` de
[`Parzival2103/Lebytek_Framework`](https://github.com/Parzival2103/Lebytek_Framework),
generada con:

```bash
# en Lebytek_Framework, sobre main limpio y sincronizado
bash scripts/publish-skeleton.sh
```

Cualquier commit hecho aquí a mano se pierde en la siguiente publicación y rompe el
fast-forward del push. Los cambios se hacen en `Lebytek_Framework/skeleton/`, donde
`SkeletonPurityTest` los valida.

## Crear un proyecto nuevo

```bash
composer create-project lebytek/skeleton mi-proyecto \
  --repository='{"type":"vcs","url":"https://github.com/Parzival2103/Lebytek_Skeleton"}' \
  --no-dev
```

Después:

```bash
cd mi-proyecto
cp .env.example .env       # editar APP_URL, APP_KEY, APP_ENV, DB_*
php scripts/install.php    # esquema de plataforma + modulos activos de config/vertical.php
```

Document root del vhost: `public/`. El asistente web `/install/` queda bloqueado en cuanto
existe `storage/install.lock`; si se instala por CLI, crear ese archivo a mano.

## Módulos

`config/vertical.php` decide qué módulos se activan. Por defecto `marketing` y `payments`
están **apagados**: son verticales de negocio, no plataforma.
`````

- [ ] **Step 5: Verificar la sintaxis del script y ejecutar el test**

Run: `cd Lebytek_Framework && bash -n scripts/publish-skeleton.sh`
Expected: sin salida (sintaxis válida).

Run: `cd Lebytek_Framework && php tests/run.php Docs/PublishSkeletonScript`
Expected: PASS — 3 tests, 0 failed.

- [ ] **Step 6: Comprobar que el script rechaza un árbol sucio**

```bash
cd Lebytek_Framework
echo "scratch" > .publish-guard-probe
bash scripts/publish-skeleton.sh ; echo "exit=$?"
rm .publish-guard-probe
```
Expected: `ERROR: arbol de trabajo sucio...` y `exit=1`. El repo no se toca.

- [ ] **Step 7: Ejecutar la suite completa**

Run: `cd Lebytek_Framework && php tests/run.php`
Expected: 0 failed (incluye `SkeletonPurityTest`, que sigue verde con el README nuevo).

- [ ] **Step 8: Commit**

```bash
cd Lebytek_Framework
git add scripts/publish-skeleton.sh skeleton/README.md tests/Docs/PublishSkeletonScriptTest.php
git commit -m "feat: add publish-skeleton.sh and skeleton README

Publica skeleton/ como raiz del espejo Lebytek_Skeleton via subtree split,
con guardas de arbol limpio, main sincronizado y ausencia de repo path.
El README viaja por el split y declara el espejo como artefacto generado.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 4: Crear el repo espejo y publicar `lebytek/skeleton` v1.1.0

Primera tarea que sale del disco local. Requiere que las Tasks 1-3 estén **mergeadas en `main`**: `publish-skeleton.sh` se niega a publicar desde otra rama o con `main` desincronizado, y el espejo debe contener el `composer.json` con el repo VCS, no el `path`.

**Files:**
- Ninguno en los repos locales. Crea `Parzival2103/Lebytek_Skeleton` (remoto).

**Interfaces:**
- Consumes: `scripts/publish-skeleton.sh` y `skeleton/composer.json` de Task 2 y Task 3, ya en `main`.
- Produces: `https://github.com/Parzival2103/Lebytek_Skeleton` privado, rama `main` = contenido de `skeleton/`, tag `v1.1.0`. Task 6 lo instala con `composer create-project`.

**Rollback de la tarea:** `gh repo delete Parzival2103/Lebytek_Skeleton --yes` y volver a ejecutar desde el Step 3. El framework no cambia de estado en ningún paso: el split es una rama temporal que el script borra, y el VPS todavía no consume nada.

- [ ] **Step 1: Mergear las Tasks 1-3 a `main` en Framework y Portal**

```bash
cd Lebytek_Framework
git push -u origin docs/skeleton-package-staging-design
gh pr create --base main --title "feat: publish lebytek/skeleton and drop monorepo deploy scripts" \
  --body "Componentes 1, 2, 3 y 8 del spec 2026-07-26-skeleton-package-staging-design.

- Elimina los tres scripts vps-deploy-*.sh (clonaban feature/backoffice-api-integration tras un rm -rf del sitio)
- skeleton/composer.json consume lebytek/framework ^1.1 via repo VCS
- scripts/publish-skeleton.sh + skeleton/README.md
- Guardas de regresion en tests/

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

```bash
cd Lebytek_Portal
git push -u origin chore/deploy-scripts-cleanup
gh pr create --base main --title "chore: delete vps deploy scripts and fix DEPLOY-VPS runbook" \
  --body "Componente 8 del spec 2026-07-26-skeleton-package-staging-design (repo Framework).

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

Mergear ambos PR antes de continuar.

- [ ] **Step 2: Sincronizar `main` local en Framework**

```bash
cd Lebytek_Framework
git checkout main
git pull origin main
git status --porcelain   # debe salir vacio
ls scripts/vps-deploy-*.sh 2>/dev/null ; echo "glob_exit=$?"
```
Expected: `git status` sin salida; `glob_exit=2` (no existen).

- [ ] **Step 3: Crear el repo espejo**

```bash
gh repo create Parzival2103/Lebytek_Skeleton --private \
  --description "Artefacto generado: skeleton/ de Lebytek_Framework. No editar directamente."
gh api repos/Parzival2103/Lebytek_Skeleton --jq '{name: .name, private: .private}'
```
Expected: `{"name":"Lebytek_Skeleton","private":true}`.

- [ ] **Step 4: Publicar el skeleton con el tag `v1.1.0`**

```bash
cd Lebytek_Framework
TAG=v1.1.0 bash scripts/publish-skeleton.sh
```
Expected: split ejecutado, push a `main` del espejo, push del tag `v1.1.0`, rama `split/skeleton` borrada, mensaje final «Publicado».

- [ ] **Step 5: Verificar el contenido del espejo**

```bash
gh api repos/Parzival2103/Lebytek_Skeleton/contents/composer.json --jq '.name'
gh api repos/Parzival2103/Lebytek_Skeleton/tags --jq '[.[].name]'
gh api repos/Parzival2103/Lebytek_Skeleton/contents --jq '[.[].name] | sort'
gh api repos/Parzival2103/Lebytek_Skeleton/contents/routes --jq '[.[].name] | sort'
```
Expected:
- `composer.json`
- `["v1.1.0"]`
- incluye `app`, `composer.json`, `config`, `database`, `public`, `routes`, `scripts`, `storage`, `tests`, `README.md`, `.env.example`; **no** incluye `src` ni `skeleton`
- `["api.php","integrations.php","web.php"]` — sin `marketing.php`, `marketing_admin.php`, `waapi_portal.php`

- [ ] **Step 6: Verificar que el `composer.json` publicado apunta al paquete, no al monorepo**

```bash
gh api repos/Parzival2103/Lebytek_Skeleton/contents/composer.json --jq '.content' \
  | base64 -d | python -c "import json,sys; d=json.load(sys.stdin); print(d['require']['lebytek/framework'], d['repositories'][0]['type'], d['minimum-stability'])"
```
Expected: `^1.1 vcs stable`

- [ ] **Step 7: Confirmar que el framework no cambió de estado**

```bash
cd Lebytek_Framework
git status --porcelain          # vacio
git branch --list 'split/*'     # vacio
git rev-parse HEAD; git rev-parse origin/main   # identicos
```

---

### Task 5: Crear la base de datos `lebytek_stg`

Primera tarea sobre el VPS. Aísla staging a nivel de esquema. **La BD `lebytek` de producción no se toca en ningún comando de esta tarea**: se lee su `.env` sólo para reutilizar la credencial, por decisión del responsable del proyecto.

Consecuencia asumida y documentada: el `.env` de staging da acceso también a la BD de producción; un error de configuración que apunte `DB_DATABASE` a `lebytek` no sería rechazado por permisos. Por eso Task 6 incluye una comprobación explícita del valor.

**Files:**
- Ninguno en repos. VPS: nueva BD `lebytek_stg`.

**Interfaces:**
- Consumes: nada de tareas previas.
- Produces: BD `lebytek_stg` vacía, accesible por el usuario `lebytek` con la password de producción. Task 6 la escribe en `.env`; Task 7 la puebla.

- [ ] **Step 1: Registrar el estado inicial de producción (línea base de no-regresión)**

```bash
ssh root@2.24.197.198
mysql -e "SELECT COUNT(*) AS tablas_prod FROM information_schema.tables WHERE table_schema='lebytek';"
mysql -e "SHOW DATABASES LIKE 'lebytek%';"
```
Anotar `tablas_prod`. Se vuelve a comprobar en Task 9.

- [ ] **Step 2: Leer la credencial de producción**

```bash
grep -E '^DB_(USERNAME|PASSWORD|HOST|PORT)=' /home/lebytek/htdocs/lebytek.com/.env
```
Expected: `DB_USERNAME=lebytek` y la password. No copiar ese valor a ningún archivo fuera del `.env` de staging.

- [ ] **Step 3: Crear la BD con `clpctl`**

```bash
clpctl db:add --domainName=skeleton.lebytek.com --databaseName=lebytek_stg \
  --databaseUserName=lebytek --databaseUserPassword='<password de produccion>'
```

Si `clpctl` rechaza reutilizar un usuario existente, usar la alternativa prevista en el spec:

```bash
mysql -e "CREATE DATABASE IF NOT EXISTS lebytek_stg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "GRANT ALL PRIVILEGES ON lebytek_stg.* TO 'lebytek'@'localhost'; FLUSH PRIVILEGES;"
```

- [ ] **Step 4: Verificar que la BD existe, está vacía y es accesible con esa credencial**

```bash
mysql -u lebytek -p'<password de produccion>' -e "SHOW DATABASES LIKE 'lebytek_stg';"
mysql -u lebytek -p'<password de produccion>' -e "SELECT COUNT(*) AS tablas_stg FROM information_schema.tables WHERE table_schema='lebytek_stg';"
```
Expected: `lebytek_stg` listada, `tablas_stg = 0`.

- [ ] **Step 5: Verificar que producción sigue intacta**

```bash
mysql -e "SELECT COUNT(*) AS tablas_prod FROM information_schema.tables WHERE table_schema='lebytek';"
```
Expected: el mismo número anotado en el Step 1.

---

### Task 6: Archivar staging, instalar el skeleton y generar el `.env`

El directorio actual se archiva, no se borra: es el rollback completo de las Tasks 6-8. `nginx` no se toca — el vhost ya apunta a `.../public` y el pool php-fpm 8.4 (`:19001`) del usuario `lebytek-stg` ya existe, de modo que el sitio nuevo queda servido en cuanto el directorio recupera su nombre.

**Files:**
- VPS: `/home/lebytek-stg/htdocs/skeleton.lebytek.com` (nuevo, vía `create-project`)
- VPS: `/home/lebytek-stg/htdocs/skeleton.lebytek.com.portal-copy-20260726` (archivo del actual)
- VPS: `/home/lebytek-stg/.config/composer/auth.json` (nuevo)
- VPS: `/home/lebytek-stg/htdocs/skeleton.lebytek.com/.env` (nuevo)

**Interfaces:**
- Consumes: el espejo publicado con tag `v1.1.0` (Task 4); la BD `lebytek_stg` y su credencial (Task 5).
- Produces: árbol del skeleton con `vendor/lebytek/framework v1.1.0` y `.env` apuntando a `lebytek_stg`. Task 7 ejecuta el instalador sobre él.

- [ ] **Step 1: Registrar el estado de partida y archivar el directorio actual**

```bash
ssh root@2.24.197.198
STG=/home/lebytek-stg/htdocs
ls -la $STG
curl -s -o /dev/null -w 'staging_pre=%{http_code}\n' -H 'Host: skeleton.lebytek.com' http://127.0.0.1:8080/
mv $STG/skeleton.lebytek.com $STG/skeleton.lebytek.com.portal-copy-20260726
ls -la $STG
```
Expected: el directorio pasa a llamarse `skeleton.lebytek.com.portal-copy-20260726` y `skeleton.lebytek.com` deja de existir.

**Rollback de toda la tarea:** `mv $STG/skeleton.lebytek.com.portal-copy-20260726 $STG/skeleton.lebytek.com`.

- [ ] **Step 2: Configurar la autenticación de Composer para los repos privados**

Ambos repos (`Lebytek_Skeleton` y `Lebytek_Framework`) son privados; sin credencial el `create-project` falla con 404 de GitHub. Usar un token con permiso `repo` (read) o la deploy key ya existente.

```bash
sudo -u lebytek-stg mkdir -p /home/lebytek-stg/.config/composer
sudo -u lebytek-stg tee /home/lebytek-stg/.config/composer/auth.json >/dev/null <<'JSON'
{
    "github-oauth": {
        "github.com": "ghp_REEMPLAZAR_POR_TOKEN_READ"
    }
}
JSON
chmod 600 /home/lebytek-stg/.config/composer/auth.json
chown lebytek-stg:lebytek-stg /home/lebytek-stg/.config/composer/auth.json
```

Comprobar que la credencial resuelve ambos repos:

```bash
sudo -u lebytek-stg composer show lebytek/skeleton --all \
  --repository='{"type":"vcs","url":"https://github.com/Parzival2103/Lebytek_Skeleton"}' 2>&1 | head -5
```
Expected: metadatos del paquete, con `v1.1.0` entre las versiones. Si sale 404, la credencial no es válida — no continuar.

- [ ] **Step 3: Instalar el skeleton con `composer create-project`**

```bash
cd /home/lebytek-stg/htdocs
sudo -u lebytek-stg composer create-project lebytek/skeleton skeleton.lebytek.com \
  --repository='{"type":"vcs","url":"https://github.com/Parzival2103/Lebytek_Skeleton"}' \
  --no-dev --no-interaction
```
Expected: crea `skeleton.lebytek.com/` y resuelve `lebytek/framework` a `vendor/` a través del repositorio VCS que el propio skeleton declara.

- [ ] **Step 4: Verificar la versión del framework y la pureza del árbol**

```bash
cd /home/lebytek-stg/htdocs/skeleton.lebytek.com
sudo -u lebytek-stg composer show lebytek/framework | head -5
ls routes/
ls public/
test -f public/index.php && echo "index_ok"
```
Expected:
- `versions : * v1.1.0`
- `routes/` contiene sólo `api.php  integrations.php  web.php`
- `public/` contiene `assets  index.php  install  sw.js`
- `index_ok`

- [ ] **Step 5: Crear los directorios de `storage` y ajustar permisos**

```bash
cd /home/lebytek-stg/htdocs/skeleton.lebytek.com
sudo -u lebytek-stg mkdir -p storage/logs storage/cache storage/uploads storage/temp storage/exports storage/imports
chown -R lebytek-stg:lebytek-stg /home/lebytek-stg/htdocs/skeleton.lebytek.com
chmod -R ug+rwX storage
```

- [ ] **Step 6: Generar el `.env`**

```bash
cd /home/lebytek-stg/htdocs/skeleton.lebytek.com
sudo -u lebytek-stg cp .env.example .env

APP_KEY_NEW="$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32)"
INSTALL_TOKEN_NEW="$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 40)"
DB_PASS_PROD="$(grep -E '^DB_PASSWORD=' /home/lebytek/htdocs/lebytek.com/.env | cut -d= -f2-)"

sudo -u lebytek-stg sed -i \
  -e "s|^APP_ENV=.*|APP_ENV=staging|" \
  -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
  -e "s|^APP_NAME=.*|APP_NAME=\"Lebytek Staging\"|" \
  -e "s|^APP_URL=.*|APP_URL=https://skeleton.lebytek.com|" \
  -e "s|^APP_KEY=.*|APP_KEY=${APP_KEY_NEW}|" \
  -e "s|^SESSION_SECURE=.*|SESSION_SECURE=true|" \
  -e "s|^DB_DATABASE=.*|DB_DATABASE=lebytek_stg|" \
  -e "s|^DB_USERNAME=.*|DB_USERNAME=lebytek|" \
  -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS_PROD}|" \
  .env

printf '\n# Asistente de instalacion: token exigido si APP_ENV pasara a production.\nINSTALL_TOKEN=%s\n' "$INSTALL_TOKEN_NEW" \
  | sudo -u lebytek-stg tee -a .env >/dev/null

chmod 600 .env
chown lebytek-stg:lebytek-stg .env
```

- [ ] **Step 7: Verificar el `.env` — en particular que no apunta a producción**

```bash
cd /home/lebytek-stg/htdocs/skeleton.lebytek.com
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|SESSION_SECURE|DB_DATABASE|DB_USERNAME)=' .env
grep -c '^APP_KEY=cambiar_por' .env
awk -F= '/^DB_DATABASE=/ && $2 != "lebytek_stg" { print "FATAL: DB_DATABASE=" $2; exit 1 }' .env && echo "db_ok"
```
Expected:
```
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://skeleton.lebytek.com
SESSION_SECURE=true
DB_DATABASE=lebytek_stg
DB_USERNAME=lebytek
```
más `0` (APP_KEY ya no es el placeholder) y `db_ok`.

- [ ] **Step 8: Comprobar que PHP arranca la aplicación**

```bash
curl -s -o /dev/null -w 'staging_loopback=%{http_code}\n' -H 'Host: skeleton.lebytek.com' http://127.0.0.1:8080/login
tail -20 /home/lebytek-stg/htdocs/skeleton.lebytek.com/storage/logs/*.log 2>/dev/null
```
Expected: `staging_loopback=200` (o `302` hacia `/login`). Un `500` aquí es fallo de esta tarea: revisar logs antes de seguir; el rollback es el `mv` del Step 1.

---

### Task 7: Instalar el esquema, crear el administrador y cerrar el asistente

`install.php` detecta el proyecto consumidor por `ROOT_PATH` y crea el esquema de plataforma más las tablas demo (`demo_clientes`, `demo_productos`, `demo_categorias`, `demo_pedidos` vienen del bootstrap del módulo `crud-engine`, activo por manifiesto). El wrapper `scripts/install.php` del skeleton define `ROOT_PATH` explícitamente y delega en el del paquete; es equivalente a la forma `php vendor/lebytek/framework/scripts/install.php` del spec, que autodetecta el consumidor en `scripts/install.php:26-34`.

Dos huecos que el instalador CLI no cubre y esta tarea sí:
- **no crea usuario**: sin admin no hay forma de verificar «CRUDs demo navegables»;
- **no escribe `storage/install.lock`**: sólo lo hace el asistente web (`public/install/steps.php:112`), y su exigencia de `INSTALL_TOKEN` sólo aplica cuando `APP_ENV === 'production'` (`public/install/index.php:53-60`). Con `APP_ENV=staging`, dejar el asistente sin lock lo expone sin autenticación en cuanto Task 8 emita el certificado.

**Files:**
- VPS: BD `lebytek_stg` poblada
- VPS: `/home/lebytek-stg/htdocs/skeleton.lebytek.com/storage/install.lock` (nuevo)
- VPS: `/home/lebytek-stg/htdocs/skeleton.lebytek.com/crear-admin-staging.php` (temporal, se borra en el Step 6)

**Interfaces:**
- Consumes: árbol y `.env` de Task 6; BD de Task 5.
- Produces: esquema instalado, usuario `admin@skeleton.lebytek.com` con rol `administrador`, asistente `/install/` bloqueado. Task 8 puede exponer el sitio sin abrir un instalador público.

- [ ] **Step 1: Ver el plan del instalador antes de aplicarlo**

```bash
cd /home/lebytek-stg/htdocs/skeleton.lebytek.com
sudo -u lebytek-stg php scripts/install.php --dry-run
```
Expected: `Plan (instalación nueva)`, migraciones y módulos listados (`core`, `crud-engine`, `dashboard`, `calendario`, `pdf-kit`, `reportes`, `integrations`), y `(dry-run: no se ejecutó nada)`.

- [ ] **Step 2: Aplicar el instalador y el bootstrap SQL**

```bash
cd /home/lebytek-stg/htdocs/skeleton.lebytek.com
sudo -u lebytek-stg php scripts/install.php
sudo -u lebytek-stg php scripts/seed.php
```
Expected: `=== Instalación completada ===` y `=== Bootstrap completado ===`. `seed.php` re-ejecuta `schema.sql`, que es idempotente (`CREATE TABLE IF NOT EXISTS`); se mantiene porque el spec lo indica.

- [ ] **Step 3: Verificar el esquema creado**

```bash
mysql -u lebytek -p'<password de produccion>' lebytek_stg -e "SHOW TABLES;" | head -40
mysql -u lebytek -p'<password de produccion>' lebytek_stg -e "SELECT COUNT(*) AS demo_tables FROM information_schema.tables WHERE table_schema='lebytek_stg' AND table_name LIKE 'demo\_%';"
mysql -u lebytek -p'<password de produccion>' lebytek_stg -e "SELECT clave, version FROM cfg_modulos;"
```
Expected: tablas `auth_*`, `cfg_*`, `core_*`, `log_*` presentes; `demo_tables >= 4`; `cfg_modulos` con `core` y `crud-engine` registrados.

- [ ] **Step 4: Crear el usuario administrador**

El script `scripts/crear_usuario.php` del paquete **no** sirve desde `vendor/`: define `ROOT_PATH` como `dirname(__DIR__)` sin guarda, de modo que apuntaría al paquete y no al consumidor. Se usa un script temporal en la raíz del proyecto, con la misma lógica:

```bash
cd /home/lebytek-stg/htdocs/skeleton.lebytek.com
sudo -u lebytek-stg tee crear-admin-staging.php >/dev/null <<'PHP'
<?php

declare(strict_types=1);

define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require ROOT_PATH . '/vendor/autoload.php';

use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Security\Hash;

EnvLoader::load(ROOT_PATH . '/.env');
Config::init(ROOT_PATH . '/config');

Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
    'charset'  => 'utf8mb4',
]);

$nombre   = $argv[1] ?? 'Admin';
$apellido = $argv[2] ?? 'Staging';
$email    = $argv[3] ?? 'admin@skeleton.lebytek.com';
$password = $argv[4] ?? null;

if ($password === null || strlen($password) < 12) {
    fwrite(STDERR, "Uso: php crear-admin-staging.php <nombre> <apellido> <email> <password de 12+ chars>\n");
    exit(1);
}

$pdo = Connection::getInstance();

echo 'BD destino: ' . Config::get('database.database') . "\n";
if (Config::get('database.database') !== 'lebytek_stg') {
    fwrite(STDERR, "ABORTA: este script solo corre contra lebytek_stg.\n");
    exit(1);
}

$exists = $pdo->prepare('SELECT id FROM auth_usuarios WHERE email = ? LIMIT 1');
$exists->execute([$email]);
if ($exists->fetchColumn()) {
    echo "El correo ya esta registrado; nada que hacer.\n";
    exit(0);
}

$stmt = $pdo->prepare(
    'INSERT INTO auth_usuarios (nombre, apellido, email, password, activo, created_at)
     VALUES (?, ?, ?, ?, 1, NOW())'
);
$stmt->execute([$nombre, $apellido, $email, Hash::make($password)]);
$id = (int) $pdo->lastInsertId();

$rolId = $pdo->query("SELECT id FROM auth_roles WHERE slug = 'administrador' LIMIT 1")->fetchColumn();
if (!$rolId) {
    fwrite(STDERR, "ABORTA: no existe el rol 'administrador'; revisa install.php.\n");
    exit(1);
}
$pdo->prepare('INSERT IGNORE INTO auth_usuarios_roles (usuario_id, rol_id) VALUES (?, ?)')
    ->execute([$id, (int) $rolId]);

echo "Usuario administrador creado. ID={$id} email={$email}\n";
PHP

ADMIN_PASS="$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 20)"
sudo -u lebytek-stg php crear-admin-staging.php Admin Staging admin@skeleton.lebytek.com "$ADMIN_PASS"
echo "GUARDAR ESTA PASSWORD EN EL GESTOR DE SECRETOS: $ADMIN_PASS"
```
Expected: `BD destino: lebytek_stg` y `Usuario administrador creado. ID=1 …`. Guardar la password antes de cerrar la sesión.

- [ ] **Step 5: Bloquear el asistente de instalación**

```bash
cd /home/lebytek-stg/htdocs/skeleton.lebytek.com
sudo -u lebytek-stg sh -c 'printf "Instalado por CLI (scripts/install.php) el %s\n" "$(date -Iseconds)" > storage/install.lock'
curl -s -H 'Host: skeleton.lebytek.com' http://127.0.0.1:8080/install/ | grep -c -i 'ya fue instalado'
```
Expected: `1` — el asistente responde la vista `ya_instalado` y no permite reinstalar.

- [ ] **Step 6: Borrar el script temporal**

```bash
cd /home/lebytek-stg/htdocs/skeleton.lebytek.com
rm -f crear-admin-staging.php
ls crear-admin-staging.php 2>&1 | grep -c 'No such file'
```
Expected: `1`.

- [ ] **Step 7: Verificar login y CRUDs demo sobre loopback**

```bash
cd /tmp
curl -s -c stg.jar -H 'Host: skeleton.lebytek.com' http://127.0.0.1:8080/login \
  | grep -oP 'name="csrf_token" value="\K[^"]+' | head -1
```
Usar el token devuelto para autenticar y recorrer un CRUD demo:

```bash
CSRF='<token del comando anterior>'
curl -s -b stg.jar -c stg.jar -o /dev/null -w 'login=%{http_code}\n' \
  -H 'Host: skeleton.lebytek.com' \
  -d "csrf_token=$CSRF" -d 'email=admin@skeleton.lebytek.com' -d "password=$ADMIN_PASS" \
  http://127.0.0.1:8080/login
curl -s -b stg.jar -o /dev/null -w 'dashboard=%{http_code}\n' -H 'Host: skeleton.lebytek.com' http://127.0.0.1:8080/admin/dashboard
curl -s -b stg.jar -o /dev/null -w 'demo_clientes=%{http_code}\n' -H 'Host: skeleton.lebytek.com' http://127.0.0.1:8080/admin/demo_clientes
curl -s -b stg.jar -o /dev/null -w 'demo_pedidos=%{http_code}\n' -H 'Host: skeleton.lebytek.com' http://127.0.0.1:8080/admin/demo_pedidos
rm -f stg.jar
```
Expected: `login=302` (redirección post-login), `dashboard=200`, `demo_clientes=200`, `demo_pedidos=200`. Si el nombre del campo CSRF difiere, tomarlo del HTML del formulario en vez de asumirlo.

- [ ] **Step 8: Verificar que producción sigue intacta**

```bash
mysql -e "SELECT COUNT(*) AS tablas_prod FROM information_schema.tables WHERE table_schema='lebytek';"
curl -s -o /dev/null -w 'lebytek=%{http_code}\n' https://lebytek.com/
```
Expected: el mismo conteo anotado en Task 5 Step 1, y `lebytek=200`.

---

### Task 8: Emitir el certificado Let's Encrypt

Se ejecuta **después** del despliegue, para que el challenge `.well-known` lo sirva la aplicación nueva. El DNS ya resuelve al VPS y la app responde 200 en `:8080`, así que el challenge no requiere cambios de nginx. Hasta aquí staging servía un certificado autofirmado que lo dejaba inaccesible desde el navegador (HTTP 000).

**Files:**
- VPS: certificado del vhost `skeleton.lebytek.com` (gestionado por CloudPanel).

**Interfaces:**
- Consumes: sitio funcional y con el asistente bloqueado (Task 7).
- Produces: `https://skeleton.lebytek.com` accesible con certificado válido. Task 9 lo verifica desde fuera.

- [ ] **Step 1: Registrar el estado del certificado actual**

```bash
ssh root@2.24.197.198
echo | openssl s_client -connect skeleton.lebytek.com:443 -servername skeleton.lebytek.com 2>/dev/null \
  | openssl x509 -noout -issuer -subject -dates
curl -s -o /dev/null -w 'staging_pre_tls=%{http_code}\n' https://skeleton.lebytek.com/
```
Expected: issuer autofirmado y `staging_pre_tls=000`. Éste es el estado al que se vuelve si el paso falla.

- [ ] **Step 2: Comprobar que el DNS resuelve al VPS**

```bash
dig +short skeleton.lebytek.com
```
Expected: `2.24.197.198`.

- [ ] **Step 3: Emitir el certificado**

```bash
clpctl lets-encrypt:install:certificate --domainName=skeleton.lebytek.com
```
Expected: emisión correcta y recarga de nginx por parte de `clpctl`.

- [ ] **Step 4: Verificar issuer y respuesta pública**

```bash
echo | openssl s_client -connect skeleton.lebytek.com:443 -servername skeleton.lebytek.com 2>/dev/null \
  | openssl x509 -noout -issuer -subject -dates
curl -s -o /dev/null -w 'staging_login=%{http_code}\n' https://skeleton.lebytek.com/login
curl -sI https://skeleton.lebytek.com/login | head -1
```
Expected: `issuer=... O = Let's Encrypt ...` (no CN autofirmado), fechas vigentes, y `staging_login=200`.

- [ ] **Step 5: Verificar que el asistente sigue bloqueado ya expuesto públicamente**

```bash
curl -s https://skeleton.lebytek.com/install/ | grep -c -i 'ya fue instalado'
```
Expected: `1`.

---

### Task 9: Verificación completa

Barrido único con todas las comprobaciones del spec, más las que este plan añade. No modifica nada. Si algo falla aquí, el directorio archivado sigue en su sitio y el rollback está disponible.

**Files:**
- Ninguno. Sólo lectura.

**Interfaces:**
- Consumes: el resultado de las Tasks 1-8.
- Produces: la luz verde que habilita Task 10 (eliminación de la rama).

- [ ] **Step 1: Verificar staging**

```bash
curl -s -o /dev/null -w 'staging_login=%{http_code}\n' https://skeleton.lebytek.com/login
echo | openssl s_client -connect skeleton.lebytek.com:443 -servername skeleton.lebytek.com 2>/dev/null \
  | openssl x509 -noout -issuer
ssh root@2.24.197.198 "cd /home/lebytek-stg/htdocs/skeleton.lebytek.com && sudo -u lebytek-stg composer show lebytek/framework | grep versions"
ssh root@2.24.197.198 "ls /home/lebytek-stg/htdocs/skeleton.lebytek.com/routes"
ssh root@2.24.197.198 "test -f /home/lebytek-stg/htdocs/skeleton.lebytek.com/storage/install.lock && echo lock_ok"
```
Expected: `staging_login=200`; issuer Let's Encrypt; `versions : * v1.1.0`; `api.php integrations.php web.php` (sin `marketing.php`, `marketing_admin.php`, `waapi_portal.php`); `lock_ok`.

- [ ] **Step 1b: Verificar los CRUDs demo desde el navegador**

Con `admin@skeleton.lebytek.com` y la password guardada en Task 7 Step 4, entrar en `https://skeleton.lebytek.com/login` y recorrer los cuatro recursos demo:

| Ruta | Esperado |
|---|---|
| `/admin/dashboard` | carga sin error |
| `/admin/demo_clientes` | listado, alta y edición funcionan |
| `/admin/demo_productos` | listado navegable |
| `/admin/demo_categorias` | listado navegable |
| `/admin/demo_pedidos` | listado navegable, relación con clientes resuelta |

Los CRUDs deben funcionar **sin ajustes**: sus definiciones (`config/cruds/demo_*.json`) y sus tablas (bootstrap del módulo `crud-engine`) vienen ambas del paquete.

- [ ] **Step 2: Verificar que producción no se movió**

```bash
curl -s -o /dev/null -w 'lebytek=%{http_code}\n' https://lebytek.com/
curl -s -o /dev/null -w 'waapi=%{http_code}\n' https://waapi.lebytek.com/
ssh root@2.24.197.198 "git -C /home/lebytek/htdocs/lebytek.com status -sb | head -3"
ssh root@2.24.197.198 "git -C /home/lebytek/htdocs/lebytek.com rev-parse HEAD"
ssh root@2.24.197.198 "mysql -e \"SELECT COUNT(*) AS tablas_prod FROM information_schema.tables WHERE table_schema='lebytek';\""
```
Expected: ambos `200`; el clone limpio, en `main`, sincronizado con `origin/main` y en el mismo SHA `2718212…` del inicio; conteo de tablas de producción idéntico al de Task 5 Step 1.

- [ ] **Step 3: Verificar el directorio archivado**

```bash
ssh root@2.24.197.198 "ls -d /home/lebytek-stg/htdocs/skeleton.lebytek.com.portal-copy-20260726"
```
Expected: existe. Se conserva hasta que el responsable confirme que ya no hace falta; no se borra en este plan.

- [ ] **Step 4: Verificar las suites de tests**

```bash
cd Lebytek_Framework && git checkout main && git pull origin main && php tests/run.php
cd Lebytek_Portal    && git checkout main && git pull origin main && php tests/run.php
```
Expected: 0 failed en ambas, incluidos `SkeletonPurityTest`, `DeployScriptsRemovedTest` y `PublishSkeletonScriptTest`.

- [ ] **Step 5: Verificar la ausencia de los scripts destructivos**

```bash
cd Lebytek_Framework && ls scripts/vps-deploy-*.sh 2>&1
cd Lebytek_Portal    && ls scripts/vps-deploy-*.sh 2>&1
```
Expected: `No such file or directory` en ambos.

- [ ] **Step 6: Verificar las referencias vivas a la rama**

La comprobación es sobre referencias **operativas**, no sobre el registro histórico (ver «Desviaciones del spec», punto 2):

```bash
cd Lebytek_Framework
git grep -n "feature/backoffice-api-integration" -- scripts/ CLAUDE.md docs/composer-setup.md docs/integration/ ; echo "fw_exit=$?"
cd ../Lebytek_Portal
git grep -n "feature/backoffice-api-integration" -- scripts/ CLAUDE.md docs/DEPLOY-VPS.md docs/integration/ ; echo "portal_exit=$?"
```
Expected: sin líneas y `exit=1` en ambos (git grep devuelve 1 cuando no hay coincidencias).

Si aparecen coincidencias en `CLAUDE.md` o en `docs/integration/`, limpiarlas aquí: son instrucciones vigentes que dejan de ser ciertas cuando la rama desaparece. Las referencias en `docs/superpowers/plans/`, `docs/superpowers/specs/`, `docs/superpowers/FPS-*` y `docs/CUTOVER-PORTAL.md` **se conservan**: son registro histórico, y `tests/Docs/FpsPublicationReadinessTest.php:22` exige explícitamente la de `CUTOVER-PORTAL.md`.

- [ ] **Step 7: Verificar el espejo**

```bash
gh api repos/Parzival2103/Lebytek_Skeleton --jq '{private: .private, default_branch: .default_branch}'
gh api repos/Parzival2103/Lebytek_Skeleton/tags --jq '[.[].name]'
gh api repos/Parzival2103/Lebytek_Skeleton/commits --jq 'length'
```
Expected: `{"private":true,"default_branch":"main"}`, `["v1.1.0"]`, y un histórico de commits (el split conserva la historia del prefijo).

---

### Task 10: Eliminar `feature/backoffice-api-integration`

Última tarea, sólo con Task 9 en verde. La rama existe **únicamente en `Lebytek_Framework`**: en `Lebytek_Portal` no está ni local ni en `origin`, así que allí la operación es una comprobación, no un borrado.

Antes de borrar se crea el tag `archive/backoffice-api-integration`. Los planes FPS citan el SHA congelado `dad059056d26b6eb527815f85cf71ecd507a57fe` como origen de la copia Portal; borrar la rama sin tag deja esos commits inalcanzables y sujetos a GC. Un tag no es una rama, ningún despliegue lo consume y `composer` no lo resuelve como constraint, de modo que la razón del spec para borrar se mantiene.

**Files:**
- Ninguno en el árbol de trabajo. Refs remotas y locales de ambos repos.

**Interfaces:**
- Consumes: Task 1 (scripts eliminados) y Task 9 (verificación en verde).
- Produces: `feature/backoffice-api-integration` inexistente; tag `archive/backoffice-api-integration` publicado en Framework.

- [ ] **Step 1: Confirmar la precondición de Task 1**

```bash
cd Lebytek_Framework && ls scripts/vps-deploy-*.sh 2>&1
cd ../Lebytek_Portal && ls scripts/vps-deploy-*.sh 2>&1
```
Expected: `No such file or directory` en ambos. **Si alguno existe, detenerse**: borrar la rama con esos scripts vivos deja `lebytek.com` vacío en la siguiente ejecución.

- [ ] **Step 2: Registrar el SHA de la rama antes de tocarla**

```bash
cd Lebytek_Framework
git fetch origin --prune
git rev-parse origin/feature/backoffice-api-integration
```
Expected: un SHA (esperado `dad0590…` o un descendiente). Anotarlo.

- [ ] **Step 3: Crear y publicar el tag de archivo**

```bash
cd Lebytek_Framework
git tag -a archive/backoffice-api-integration origin/feature/backoffice-api-integration \
  -m "Archivo de feature/backoffice-api-integration (monolito congelado, era monorepo).

Rama eliminada el 2026-07-26 por el spec 2026-07-26-skeleton-package-staging-design.
Referenciada como SHA congelado en docs/superpowers/FPS-git-baseline.md."
git push origin archive/backoffice-api-integration
gh api repos/Parzival2103/Lebytek_Framework/tags --jq '[.[].name]'
```
Expected: el tag aparece junto a `v1.0.0`, `v1.1.0`, `pre-split-backup`.

- [ ] **Step 4: Comprobar que el preflight de las automatizaciones resuelve el tag**

Con el tag ya publicado y la rama **todavía viva**, el preflight de `docs/automation/` debe resolver ya por su primer candidato (`refs/tags/archive/…`). Si esto no está en verde, **no continuar**: en cuanto se borre la rama, `git rev-list origin/main..<LEGACY_REF>` abortaría con `invalid object name` en el primer paso y las tres automatizaciones morirían antes de llegar a sus comprobaciones útiles.

```bash
cd Lebytek_Framework
git fetch origin --prune --tags
git rev-parse --verify --quiet 'refs/tags/archive/backoffice-api-integration^{commit}'
php tests/run.php Docs/AutomationPreflightRef
```

Expected: el `rev-parse` imprime el SHA anotado en el Step 2, y el test `4 passed, 0 failed`. La guarda permanente vive en `tests/Docs/AutomationPreflightRefTest.php`.

- [ ] **Step 5: Eliminar la rama en Framework**

```bash
cd Lebytek_Framework
git push origin --delete feature/backoffice-api-integration
git branch -D feature/backoffice-api-integration
```

- [ ] **Step 6: Comprobar Portal (no-op esperado)**

```bash
cd Lebytek_Portal
git fetch origin --prune
gh api repos/Parzival2103/Lebytek_Portal/branches --jq '[.[].name]'
git branch --list feature/backoffice-api-integration
```
Expected: la lista de ramas remotas no incluye `feature/backoffice-api-integration` y `git branch --list` no devuelve nada. Si apareciera, borrarla con los mismos dos comandos del Step 5.

- [ ] **Step 7: Verificar la eliminación en ambos repos**

```bash
gh api repos/Parzival2103/Lebytek_Framework/branches --jq '[.[].name] | map(select(. == "feature/backoffice-api-integration"))'
gh api repos/Parzival2103/Lebytek_Portal/branches   --jq '[.[].name] | map(select(. == "feature/backoffice-api-integration"))'
cd Lebytek_Framework && git branch -a | grep -c 'feature/backoffice-api-integration' || echo "fw_local_clean"
cd ../Lebytek_Portal && git branch -a | grep -c 'feature/backoffice-api-integration' || echo "portal_local_clean"
```
Expected: `[]` en ambas consultas `gh`, y `fw_local_clean` / `portal_local_clean`.

Nota: el clone de Framework tiene además un remoto local `no-mistakes` (`C:\Users\User\.no-mistakes\repos\…`) con su propia copia de la rama. Es un espejo de herramienta local, no un origen de despliegue; queda fuera de alcance.

- [ ] **Step 8: Verificación final de no-regresión**

```bash
curl -s -o /dev/null -w 'lebytek=%{http_code}\n' https://lebytek.com/
curl -s -o /dev/null -w 'waapi=%{http_code}\n' https://waapi.lebytek.com/
curl -s -o /dev/null -w 'staging=%{http_code}\n' https://skeleton.lebytek.com/login
cd Lebytek_Framework && php tests/run.php | tail -3
```
Expected: `200` en los tres dominios y la suite del framework en 0 failed.

---

## Fuera de alcance (confirmado en el spec)

- `waapi.lebytek.com` está 2 commits atrás de `lebytek.com` (`6cdb957` vs `2718212`); el dominio no se toca. Sí se elimina su script de despliegue obsoleto (Task 1), porque referencia la rama a borrar.
- `consolidation/framework-portal-separation` queda sin consumidores tras Task 6, pero no se elimina.
- Ramas `cursor/*` y `automation/audit-spec-*` (~18 acumuladas en el remoto).
- Directorios de backup del corte del 21-jul (`lebytek.com.monorepo-backup-*`, `waapi.lebytek.com.monorepo-backup-*`, `waapi.lebytek.com.prev-*`).
- Auth básica sobre staging: descartada al aislar la BD.
- Referencias históricas a `feature/backoffice-api-integration` en `docs/superpowers/plans/`, `docs/superpowers/specs/`, `docs/superpowers/FPS-*` y `docs/CUTOVER-PORTAL.md`.

---

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Reconciliación UTC | 2026-07-30T14:00:00Z |
| SHA `origin/main` verificado | `0ec722bc38258b2e479d30cafd59940aa44d558e` |
| Tareas completadas / totales | **0.5 / 10** (Task 1 Framework verificada; Portal y Tasks 2–10 pendientes) |
| Siguiente tarea ejecutable | **Task 1 Steps 5–11 (Portal)** o **Task 2 (Framework)** — skeleton `composer.json` VCS |
| Prerrequisitos Task 1 Portal | Acceso `gh`/`git` a `Parzival2103/Lebytek_Portal` (bloqueado M6/D3); rama `chore/deploy-scripts-cleanup` desde Portal `main` |
| Prerrequisitos Task 2 | Ninguno en Framework; independiente de Portal |
| Bloqueos | (1) Token automation sin lectura Portal — Steps 5–11 no verificables desde este repo. (2) Tasks 4–8 requieren VPS/credenciales (`Requiere operador humano`). (3) Task 10 requiere Task 9 en verde. (4) Rama `docs/skeleton-package-staging-design` ya no existe — corregida a `main`. (5) Repo espejo `Lebytek_Skeleton` no existe aún (Task 4). |
| Evidencia Task 1 Framework | PR #36 — scripts `vps-deploy-*.sh` eliminados; `tests/Docs/DeployScriptsRemovedTest.php` presente en `origin/main` |
| Plan activo | **Incompleto** — permanece en `docs/superpowers/plans/` |
