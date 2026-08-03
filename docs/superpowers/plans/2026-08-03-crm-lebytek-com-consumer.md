# CRM `crm.lebytek.com` Consumer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create private repo `Lebytek_CRM` from Framework `skeleton/`, pin `lebytek/framework` ^1.2.3 via Composer, and go live on `crm.lebytek.com` with dedicated DB and working admin login.

**Architecture:** Portal-twin consumer: copy `Lebytek_Framework/skeleton/` into a new app repo, replace path/`*@dev` with VCS + semver (same pattern as `Lebytek_Portal`), deploy to a new CloudPanel site/user. Platform stays in `vendor/`; CRM business code will come later in `app/`. Independent of the still-pending `skeleton.lebytek.com` lab.

**Tech Stack:** PHP 8.1, Composer 2, `lebytek/framework` v1.2.3, CloudPanel `clpctl`, nginx, MySQL/MariaDB, git + deploy key, `gh` CLI, harness `php tests/run.php` (microtest).

**Spec:** [`docs/superpowers/specs/2026-08-03-crm-lebytek-com-consumer-design.md`](../specs/2026-08-03-crm-lebytek-com-consumer-design.md)

## Global Constraints

- GitHub repo: **private** `Parzival2103/Lebytek_CRM`, branch **`main`**
- Local path: `C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_CRM`
- Composer package name: **`lebytek/crm`**
- Framework constraint: **`lebytek/framework: ^1.2.3`** (VCS `https://github.com/Parzival2103/Lebytek_Framework`)
- Hostname: **`crm.lebytek.com`**
- CloudPanel site user: **`lebytek-crm`**
- VPS app path: **`/home/lebytek-crm/htdocs/crm.lebytek.com/`**
- Document root: **`.../public`**
- PHP pool: **8.1**
- Database name: **`lebytek_crm`** (never `lebytek` / Portal DBs)
- `.env` and secrets: **never committed**
- Do **not** modify `lebytek.com`, `waapi.lebytek.com`, `api.lebytek.com`, or Portal/Framework `src/`
- Do **not** merge `feature/backoffice-api-integration` → `main`
- Do **not** implement CRM business features in this plan
- SSH alias for ops: **`lebytek-vps`** (or `root@2.24.197.198`)

## Desviaciones del spec (leer antes de empezar)

1. **`skeleton/tests/run.php` apunta al harness del monorepo.** Línea `require dirname(__DIR__, 2) . '/tests/lib/microtest.php'` solo funciona dentro de `Lebytek_Framework`. En CRM hay que copiar `microtest.php` al consumidor y usar el patrón Portal (`require __DIR__ . '/lib/microtest.php'`). Sin esto, `php tests/run.php` falla al abrir el repo.

2. **`skeleton/` no trae `.gitignore`.** Hay que crear uno (modelo Portal) antes del primer commit o se suben `vendor/`, `.env` y logs.

3. **DNS ya resuelve.** Verificado 2026-08-03: `dig +short crm.lebytek.com A` → `2.24.197.198`. Task de DNS es verificación, no creación desde cero (si falla en ejecución, crear el A record).

4. **Framework es público; CRM es privado.** En el VPS, `composer install` del Framework no necesita token. El `git clone` de `Lebytek_CRM` sí necesita deploy key (patrón `github.com-portal`).

5. **`scripts/install.php` CLI no crea admin ni `install.lock`.** Igual que el plan skeleton: crear admin con script temporal + escribir `storage/install.lock` antes de exponer TLS, porque con `APP_ENV=production` el wizard exige `INSTALL_TOKEN`, pero el lock debe existir para cerrar el asistente.

## File Structure

**Nuevo repo `Lebytek_CRM`** (raíz = copia de `skeleton/`)

| Archivo | Responsabilidad |
|---|---|
| `composer.json` | `lebytek/crm` + VCS Framework + `^1.2.3` |
| `composer.lock` | Pin exacto instalado en VPS |
| `.gitignore` | Excluir `vendor/`, `.env`, logs |
| `.env.example` | Identidad CRM (`APP_URL`, sin vars Portal) |
| `tests/run.php` | Runner standalone (como Portal) |
| `tests/lib/microtest.php` | Copiado del harness |
| `tests/Consumer/CrmPurityTest.php` | Guarda: sin Marketing/path repo |
| `CLAUDE.md`, `AGENTS.md` | Guardrails consumidor CRM |
| `docs/DEPLOY-VPS.md` | Runbook pull → composer → migrate → smoke |
| `README.md` | Qué es el repo y cómo arrancar local |

**`Lebytek_Framework`**

| Archivo | Responsabilidad |
|---|---|
| `docs/ENVIRONMENTS.md` | Añadir `crm.lebytek.com` como producto consumidor |
| Spec/plan ya escritos bajo `docs/superpowers/` | Referencia |

**VPS**

| Ruta | Responsabilidad |
|---|---|
| `/home/lebytek-crm/` | Usuario CloudPanel del sitio |
| `/home/lebytek-crm/htdocs/crm.lebytek.com/` | Checkout `main` |
| `/home/lebytek-crm/.ssh/` | Deploy key + `Host github.com-crm` |
| MySQL `lebytek_crm` | BD aislada |
| `/etc/nginx/sites-enabled/crm.lebytek.com.conf` | Creado por `clpctl`; root → `.../public` |

---

### Task 1: Scaffold local `Lebytek_CRM` desde skeleton

**Files:**
- Create: `C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_CRM\` (árbol completo)
- Create: `Lebytek_CRM/composer.json`
- Create: `Lebytek_CRM/.gitignore`
- Modify: `Lebytek_CRM/.env.example`
- Modify: `Lebytek_CRM/tests/run.php`
- Create: `Lebytek_CRM/tests/lib/microtest.php` (copy)

**Interfaces:**
- Consumes: `Lebytek_Framework/skeleton/` @ `main` tip con Framework tag `v1.2.3`
- Produces: árbol local sin `vendor/` aún; `composer.json` listo para Task 2

- [ ] **Step 1: Copiar skeleton excluyendo basura local**

En PowerShell:

```powershell
$src = "C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework\skeleton"
$dst = "C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_CRM"
if (Test-Path $dst) { throw "Lebytek_CRM already exists: $dst" }
robocopy $src $dst /E /XD storage\logs .git /XF "*.log" "error_log" /NFL /NDL /NJH /NJS
New-Item -ItemType Directory -Force -Path "$dst\storage\logs" | Out-Null
# keep .gitkeep if present in skeleton storage
Get-ChildItem $dst -Recurse -Filter .gitkeep | Select-Object -First 5 FullName
```

Expected: `Lebytek_CRM` existe con `app/`, `config/`, `public/`, `routes/`, `scripts/`, `tests/`, `composer.json`.

- [ ] **Step 2: Reemplazar `composer.json` por pin Portal-style**

Escribir `Lebytek_CRM/composer.json` con este contenido exacto:

```json
{
    "name": "lebytek/crm",
    "description": "Lebytek CRM - tenant app for crm.lebytek.com (consumes lebytek/framework)",
    "type": "project",
    "license": "proprietary",
    "require": {
        "php": ">=8.1",
        "lebytek/framework": "^1.2.3"
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

- [ ] **Step 3: Crear `.gitignore`**

Escribir `Lebytek_CRM/.gitignore`:

```gitignore
/vendor/
.env
public/uploads/
public/error_log
storage/logs/*.log
!storage/logs/.gitkeep
!storage/cache/.gitkeep
!storage/sessions/.gitkeep
composer.phar
.DS_Store
Thumbs.db
```

- [ ] **Step 4: Ajustar `.env.example` a identidad CRM**

Cambiar (como mínimo) estas claves en `Lebytek_CRM/.env.example`:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_NAME="Lebytek CRM"
APP_URL=http://localhost:8000
APP_KEY=cambiar_por_clave_aleatoria_de_32_chars
DB_DATABASE=lebytek_crm
DB_USERNAME=root
DB_PASSWORD=
SESSION_SECURE=false
INSTALL_TOKEN=
```

No añadir `MKT_*`, `LEBYTEK_API_*`, ni `WAAPI_*`.

- [ ] **Step 5: Hacer el test runner standalone (como Portal)**

Copiar microtest:

```powershell
Copy-Item `
  "C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal\tests\lib\microtest.php" `
  "C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_CRM\tests\lib\microtest.php"
```

Reemplazar el contenido de `Lebytek_CRM/tests/run.php` por:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/microtest.php';

$filter = $argv[1] ?? null;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)
);

$files = [];
foreach ($iterator as $entry) {
    if (!$entry->isFile()) {
        continue;
    }
    $path = $entry->getPathname();
    if (!str_ends_with($path, 'Test.php')) {
        continue;
    }
    if ($filter !== null && !str_contains(str_replace('\\', '/', $path), $filter)) {
        continue;
    }
    $files[] = $path;
}

sort($files);
foreach ($files as $file) {
    require $file;
}

microtest_summary();
```

- [ ] **Step 6: Verificar que no hay rastros de Portal en el árbol**

```powershell
cd C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_CRM
rg -i "lebytek/portal|Marketing|LEBYTEK_API_|MKT_|waapi_portal" --glob '!vendor/**' .
```

Expected: sin matches de negocio Portal (el nombre `Lebytek` en reglas/docs del skeleton está bien).

---

### Task 2: Composer lock + test de pureza del consumidor

**Files:**
- Create: `Lebytek_CRM/composer.lock`
- Create: `Lebytek_CRM/tests/Consumer/CrmPurityTest.php`
- Create: `Lebytek_CRM/vendor/` (local only; gitignored)

**Interfaces:**
- Consumes: `composer.json` de Task 1
- Produces: `composer.lock` con `lebytek/framework` 1.2.x; test verde que Task 3/4 deben mantener

- [ ] **Step 1: Escribir el test de pureza (falla sin lock / con path)**

Crear `Lebytek_CRM/tests/Consumer/CrmPurityTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('composer.json is lebytek/crm with VCS framework pin', function () use ($root): void {
    $json = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    assert_same('lebytek/crm', $json['name']);
    assert_same('^1.2.3', $json['require']['lebytek/framework'] ?? null);
    assert_same('vcs', $json['repositories'][0]['type'] ?? null);
    assert_same(
        'https://github.com/Parzival2103/Lebytek_Framework',
        $json['repositories'][0]['url'] ?? null
    );
    assert_true(($json['minimum-stability'] ?? '') === 'stable');
    foreach ($json['repositories'] as $repo) {
        assert_true(($repo['type'] ?? '') !== 'path', 'must not use path repository');
    }
});

test('tree has no Portal marketing modules', function () use ($root): void {
    $forbidden = [
        $root . '/app/Domain/Marketing',
        $root . '/app/Application/Marketing',
        $root . '/app/Infrastructure/Integrations/LebytekApi',
        $root . '/routes/marketing.php',
        $root . '/routes/marketing_admin.php',
        $root . '/routes/waapi_portal.php',
    ];
    foreach ($forbidden as $path) {
        assert_true(!file_exists($path), 'forbidden Portal path present: ' . $path);
    }
});

test('composer.lock pins lebytek/framework 1.2.x', function () use ($root): void {
    $lockPath = $root . '/composer.lock';
    assert_true(is_file($lockPath), 'composer.lock missing');
    $lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);
    $pkg = null;
    foreach ($lock['packages'] as $p) {
        if (($p['name'] ?? '') === 'lebytek/framework') {
            $pkg = $p;
            break;
        }
    }
    assert_true($pkg !== null, 'lebytek/framework missing from lock');
    assert_true(
        (bool) preg_match('/^v?1\.2\.\d+/', (string) ($pkg['version'] ?? '')),
        'expected framework 1.2.x, got ' . ($pkg['version'] ?? '')
    );
});
```

- [ ] **Step 2: Ejecutar el test y confirmar fallo por lock ausente**

```powershell
cd C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_CRM
composer install --no-interaction
# Si composer install aún no se corrió, el require de bootstrap puede fallar;
# primero solo validar que el archivo de test existe, luego:
php tests/run.php Consumer/CrmPurity
```

Si `vendor/` aún no existe, ejecutar primero Step 3 y luego este test. Orden preferido TDD: crear test → `composer update` → test PASS.

Expected antes de lock: fallo en `composer.lock pins…` o en autoload si no hay vendor.

- [ ] **Step 3: Generar lock e instalar Framework**

```powershell
cd C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_CRM
composer update lebytek/framework --no-interaction
composer show lebytek/framework
```

Expected: versión `1.2.3` (o parche `1.2.x` compatible con `^1.2.3`); `vendor/lebytek/framework` presente; `composer.lock` creado.

- [ ] **Step 4: Ejecutar pureza + tests skeleton existentes**

```powershell
cd C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_CRM
php tests/run.php Consumer/CrmPurity
php tests/run.php
```

Expected: `CrmPurity` 3 PASS; suite existente sin fallos nuevos (si algún test skeleton asume monorepo path, documentar y ajustar solo ese test en CRM — no tocar Framework).

- [ ] **Step 5: Commit inicial local (aún sin remote)**

```powershell
cd C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_CRM
git init -b main
git add .
git status
git commit -m "$(cat <<'EOF'
chore: bootstrap Lebytek CRM consumer from framework skeleton

Seed crm.lebytek.com app with Composer-pinned lebytek/framework ^1.2.3.
EOF
)"
```

En PowerShell sin HEREDOC bash, usar:

```powershell
git commit -m "chore: bootstrap Lebytek CRM consumer from framework skeleton"
```

Expected: commit en `main`; `vendor/` y `.env` no staged.

---

### Task 3: Docs de agente + runbook deploy

**Files:**
- Create: `Lebytek_CRM/CLAUDE.md`
- Create: `Lebytek_CRM/AGENTS.md`
- Create: `Lebytek_CRM/README.md`
- Create: `Lebytek_CRM/docs/DEPLOY-VPS.md`
- Keep: `Lebytek_CRM/.cursor/rules/*` (ya copiados del skeleton)

**Interfaces:**
- Consumes: identidad Task 1–2
- Produces: docs que el VPS runbook y agentes usarán en Tasks 6–10

- [ ] **Step 1: Escribir `CLAUDE.md`**

```markdown
# CLAUDE.md — Lebytek CRM

Tenant CRM en `crm.lebytek.com`. El framework vive en `vendor/lebytek/framework` (**solo lectura**).

| Área | Path |
|------|------|
| Negocio CRM (futuro) | `app/`, `config/`, `routes/`, `database/` (`dom_*`) |
| Tests | `tests/` |
| Plataforma | `vendor/lebytek/framework` según `composer.lock` |
| Cambio de plataforma | `Lebytek_Framework/main` → release semver → `composer update lebytek/framework` |

Reglas:
- Nunca editar `vendor/`
- No copiar código Marketing/Portal aquí
- No clonar este repo para lab `skeleton.lebytek.com` (lab distinto)
- Deploy: `docs/DEPLOY-VPS.md`
```

- [ ] **Step 2: Escribir `AGENTS.md`**

```markdown
# AGENTS.md — Lebytek CRM

| Campo | Valor |
|-------|--------|
| Producto | CRM Lebytek |
| Dominio | `crm.lebytek.com` |
| GitHub | `Parzival2103/Lebytek_CRM` |
| Rama deploy | `main` |
| Framework | `lebytek/framework` vía Composer + `composer.lock` |

## Ownership

- Código CRM: este repo (`app/`, `config/`, `routes/`, business SQL).
- Plataforma: `Lebytek_Framework` → tag semver → `composer update` aquí.
- `vendor/` siempre read-only.

## Verification

```bash
composer install
php tests/run.php
php -S localhost:8000 -t public
```
```

- [ ] **Step 3: Escribir `README.md`**

```markdown
# Lebytek CRM

Aplicación consumidora del [Lebytek Framework](https://github.com/Parzival2103/Lebytek_Framework) para **crm.lebytek.com**.

## Setup local

```bash
composer install
cp .env.example .env
# editar DB_* y APP_KEY
php scripts/install.php
php -S localhost:8000 -t public
```

## Deploy

Ver [docs/DEPLOY-VPS.md](docs/DEPLOY-VPS.md).
```

- [ ] **Step 4: Escribir `docs/DEPLOY-VPS.md`**

```markdown
# VPS deploy — crm.lebytek.com

**Authority:** `Parzival2103/Lebytek_CRM` branch `main`.  
Framework = Composer package only (never clone Framework onto this host as the site).

## Layout

```text
/home/lebytek-crm/htdocs/crm.lebytek.com/
  app/ config/ routes/ public/ database/ storage/ .env
  composer.json / composer.lock
  vendor/   ← lebytek/framework
```

Document root: `public/`.

## Deploy sequence

```bash
ssh lebytek-vps
sudo -u lebytek-crm -H bash -lc '
  cd /home/lebytek-crm/htdocs/crm.lebytek.com &&
  git pull origin main &&
  composer install --no-dev --no-interaction --optimize-autoloader &&
  php scripts/migrate.php
'
# reload php-fpm 8.1 if needed
curl -sI https://crm.lebytek.com/login | head -5
```

## Forbidden

- Edit production `.env` via git
- `git push --force`
- Point `DB_DATABASE` at Portal DBs (`lebytek`, etc.)
- Edit `vendor/`
- Deploy from Framework feature branches
```

- [ ] **Step 5: Commit docs**

```powershell
cd C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_CRM
git add CLAUDE.md AGENTS.md README.md docs/DEPLOY-VPS.md
git commit -m "docs: add CRM agent guides and VPS deploy runbook"
```

---

### Task 4: Crear repo GitHub privado y push `main`

**Files:**
- Remote: `Parzival2103/Lebytek_CRM`

**Interfaces:**
- Consumes: commits locales Tasks 1–3
- Produces: `origin/main` remoto para clone en VPS (Task 8)

- [ ] **Step 1: Crear el repo privado**

```powershell
gh repo create Parzival2103/Lebytek_CRM --private `
  --description "Lebytek CRM - tenant for crm.lebytek.com (consumes lebytek/framework)" `
  --source "C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_CRM" `
  --remote origin `
  --push
```

Si `--source/--push` falla porque ya hay `origin`, usar Steps 2–3.

Expected: `gh repo view Parzival2103/Lebytek_CRM --json name,isPrivate` → `isPrivate: true`.

- [ ] **Step 2: Verificar remoto y contenido**

```powershell
cd C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_CRM
git remote -v
gh api repos/Parzival2103/Lebytek_CRM/contents/composer.json --jq .name
gh api repos/Parzival2103/Lebytek_CRM --jq "{private: .private, default_branch: .default_branch}"
```

Expected: privado, `main`, `composer.json` presente en root.

- [ ] **Step 3: Verificar que vendor no está en GitHub**

```powershell
gh api repos/Parzival2103/Lebytek_CRM/contents/vendor 2>&1
```

Expected: 404 (no `vendor/` en el repo).

---

### Task 5: Actualizar `ENVIRONMENTS.md` en Framework

**Files:**
- Modify: `Lebytek_Framework/docs/ENVIRONMENTS.md`

**Interfaces:**
- Consumes: decisión de host/repo del spec
- Produces: mapa de entornos con CRM como producto (no lab)

- [ ] **Step 1: Añadir CRM al mapa de capas**

En `docs/ENVIRONMENTS.md`, dentro del bloque `Mapa de capas`, añadir tras el bloque Portal:

```text
crm.lebytek.com        →  Lebytek_CRM main + composer.lock
                           PRODUCCIÓN producto CRM (skeleton + framework)
```

- [ ] **Step 2: Añadir fila a la tabla «Producción — autoridad actual»**

| Sitio | Repo | Rama | Framework |
|-------|------|------|-----------|
| crm.lebytek.com | `Lebytek_CRM` | `main` | `lebytek/framework` vía `composer.lock` |

- [ ] **Step 3: Añadir sección corta «crm.lebytek.com»**

Después de la sección skeleton (o antes de staging), insertar:

```markdown
## crm.lebytek.com — producto CRM

### Propósito

Tenant de producto CRM: semilla `skeleton/` + `lebytek/framework` semver.
Negocio CRM vive en `Parzival2103/Lebytek_CRM`. **No** es el lab de plataforma
(`skeleton.lebytek.com`) ni Portal.

### Reglas

- BD propia `lebytek_crm`
- Sin Marketing/Portal
- Framework solo por Composer + lock
```

- [ ] **Step 4: Commit en rama Framework y PR a `main`**

```powershell
cd C:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework
git checkout main
git pull origin main
git checkout -b docs/crm-lebytek-environments
git add docs/ENVIRONMENTS.md docs/superpowers/specs/2026-08-03-crm-lebytek-com-consumer-design.md docs/superpowers/plans/2026-08-03-crm-lebytek-com-consumer.md
git commit -m "docs: add crm.lebytek.com consumer to environments map"
git push -u origin HEAD
gh pr create --base main --title "docs: add crm.lebytek.com to ENVIRONMENTS" --body "## Summary
- Document crm.lebytek.com as CRM product consumer (not skeleton lab)
- Add design + implementation plan artifacts

## Test plan
- [ ] Read ENVIRONMENTS.md map includes crm.lebytek.com
- [ ] No instruction to merge legacy Framework feature branch
"
```

Expected: PR abierto; merge cuando el usuario lo autorice (docs-only).

---

### Task 6: Crear sitio PHP CloudPanel `crm.lebytek.com`

**Files:**
- VPS: usuario `lebytek-crm`, vhost nginx, pool PHP 8.1

**Interfaces:**
- Consumes: DNS A → `2.24.197.198` (verificar)
- Produces: home + htdocs vacíos listos para clone (Task 8)

- [ ] **Step 1: Verificar DNS y que el sitio no existe**

```bash
ssh lebytek-vps 'dig +short crm.lebytek.com A; ls /home/lebytek-crm 2>&1; ls /etc/nginx/sites-enabled/crm.lebytek.com.conf 2>&1'
```

Expected: IP `2.24.197.198`; usuario/sitio aún no existen (`No such file`).

- [ ] **Step 2: Generar password del site user (no commitear)**

```bash
ssh lebytek-vps 'SITE_PASS=$(LC_ALL=C tr -dc "A-Za-z0-9" </dev/urandom | head -c 24); echo "SAVE_SITE_USER_PASS=$SITE_PASS"'
```

Guardar el valor en el gestor de secretos del operador.

- [ ] **Step 3: Crear sitio PHP 8.1**

```bash
ssh lebytek-vps "clpctl site:add:php \
  --domainName=crm.lebytek.com \
  --phpVersion=8.1 \
  --vhostTemplate='Generic' \
  --siteUser=lebytek-crm \
  --siteUserPassword='<SITE_PASS_FROM_STEP_2>'"
```

Expected: comando OK; existe `/home/lebytek-crm/htdocs/crm.lebytek.com`.

- [ ] **Step 4: Forzar document root a `public` si hace falta**

```bash
ssh lebytek-vps 'grep -E "^\s*root " /etc/nginx/sites-enabled/crm.lebytek.com.conf'
```

Expected: `root /home/lebytek-crm/htdocs/crm.lebytek.com/public;`

Si el root apunta al directorio del sitio **sin** `/public`, editar el conf (backup primero) y `nginx -t && systemctl reload nginx`.

- [ ] **Step 5: Smoke loopback pre-app (placeholder CloudPanel OK)**

```bash
ssh lebytek-vps "curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: crm.lebytek.com' http://127.0.0.1:8080/"
```

Expected: `200` o `403`/`404` del placeholder — no `000`. Confirma que el vhost existe.

---

### Task 7: Crear base de datos `lebytek_crm`

**Files:**
- VPS MySQL: database + user grants

**Interfaces:**
- Consumes: sitio `crm.lebytek.com` (Task 6) — `db:add` exige `--domainName`
- Produces: BD vacía; credenciales para `.env` (Task 8)

- [ ] **Step 1: Baseline de no-regresión sobre BD Portal**

```bash
ssh lebytek-vps "mysql -N -e \"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='lebytek';\""
```

Anotar `TABLAS_PROD`.

- [ ] **Step 2: Generar password DB**

```bash
ssh lebytek-vps 'DB_PASS=$(LC_ALL=C tr -dc "A-Za-z0-9" </dev/urandom | head -c 24); echo "SAVE_DB_PASS=$DB_PASS"'
```

- [ ] **Step 3: Crear DB + user con `clpctl`**

```bash
ssh lebytek-vps "clpctl db:add \
  --domainName=crm.lebytek.com \
  --databaseName=lebytek_crm \
  --databaseUserName=lebytek_crm \
  --databaseUserPassword='<DB_PASS_FROM_STEP_2>'"
```

- [ ] **Step 4: Verificar BD vacía y acceso**

```bash
ssh lebytek-vps "mysql -u lebytek_crm -p'<DB_PASS>' -e \"SHOW DATABASES LIKE 'lebytek_crm'; SELECT COUNT(*) AS tablas FROM information_schema.tables WHERE table_schema='lebytek_crm';\""
```

Expected: DB listada; `tablas = 0`.

- [ ] **Step 5: Verificar Portal intacto**

```bash
ssh lebytek-vps "mysql -N -e \"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='lebytek';\""
```

Expected: igual a `TABLAS_PROD` del Step 1.

---

### Task 8: Deploy key, clone, Composer, `.env`

**Files:**
- VPS: `/home/lebytek-crm/.ssh/id_ed25519_crm`, `config` Host `github.com-crm`
- VPS: checkout git + `vendor/` + `.env`

**Interfaces:**
- Consumes: repo GitHub (Task 4), BD creds (Task 7), sitio (Task 6)
- Produces: app files + Framework en vendor; listo para install (Task 9)

- [ ] **Step 1: Generar deploy key en el usuario del sitio**

```bash
ssh lebytek-vps "sudo -u lebytek-crm -H bash -lc '
  mkdir -p ~/.ssh && chmod 700 ~/.ssh
  ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519_crm -N \"\" -C \"crm.lebytek.com-deploy\"
  printf \"%s\n\" \
    \"Host github.com-crm\" \
    \"  HostName github.com\" \
    \"  User git\" \
    \"  IdentityFile /home/lebytek-crm/.ssh/id_ed25519_crm\" \
    \"  IdentitiesOnly yes\" \
    > ~/.ssh/config
  chmod 600 ~/.ssh/config ~/.ssh/id_ed25519_crm
  cat ~/.ssh/id_ed25519_crm.pub
'"
```

- [ ] **Step 2: Registrar la public key como Deploy Key (read-only) en GitHub**

```powershell
# pegar la pubkey del Step 1 en KEY
gh repo deploy-key add -R Parzival2103/Lebytek_CRM -t "crm.lebytek.com" - <<< "KEY"
```

En PowerShell:

```powershell
gh api repos/Parzival2103/Lebytek_CRM/keys -f title="crm.lebytek.com" -f key="ssh-ed25519 AAAA..." -F read_only=true
```

- [ ] **Step 3: Vaciar placeholder CloudPanel y clonar**

```bash
ssh lebytek-vps "sudo -u lebytek-crm -H bash -lc '
  APP=/home/lebytek-crm/htdocs/crm.lebytek.com
  # backup placeholder if any
  mkdir -p /home/lebytek-crm/backups
  tar -czf /home/lebytek-crm/backups/crm-placeholder-$(date +%Y%m%d%H%M%S).tgz -C \"$APP\" . 2>/dev/null || true
  find \"$APP\" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
  git clone git@github.com-crm:Parzival2103/Lebytek_CRM.git \"$APP\"
  cd \"$APP\" && git rev-parse --abbrev-ref HEAD && git log -1 --oneline
'"
```

Expected: rama `main`; último commit docs/bootstrap.

- [ ] **Step 4: `composer install --no-dev`**

```bash
ssh lebytek-vps "sudo -u lebytek-crm -H bash -lc '
  cd /home/lebytek-crm/htdocs/crm.lebytek.com
  composer install --no-dev --no-interaction --optimize-autoloader
  composer show lebytek/framework
'"
```

Expected: `lebytek/framework` **1.2.3** (o 1.2.x del lock).

- [ ] **Step 5: Crear `.env` de producción**

```bash
ssh lebytek-vps "sudo -u lebytek-crm -H bash -lc '
  cd /home/lebytek-crm/htdocs/crm.lebytek.com
  cp .env.example .env
  APP_KEY=$(LC_ALL=C tr -dc \"A-Za-z0-9\" </dev/urandom | head -c 32)
  INSTALL_TOKEN=$(LC_ALL=C tr -dc \"A-Za-z0-9\" </dev/urandom | head -c 32)
  # edit with sed — replace placeholders
  sed -i \
    -e \"s|^APP_ENV=.*|APP_ENV=production|\" \
    -e \"s|^APP_DEBUG=.*|APP_DEBUG=false|\" \
    -e \"s|^APP_NAME=.*|APP_NAME=\\\"Lebytek CRM\\\"|\" \
    -e \"s|^APP_URL=.*|APP_URL=https://crm.lebytek.com|\" \
    -e \"s|^APP_KEY=.*|APP_KEY=${APP_KEY}|\" \
    -e \"s|^DB_HOST=.*|DB_HOST=127.0.0.1|\" \
    -e \"s|^DB_DATABASE=.*|DB_DATABASE=lebytek_crm|\" \
    -e \"s|^DB_USERNAME=.*|DB_USERNAME=lebytek_crm|\" \
    -e \"s|^DB_PASSWORD=.*|DB_PASSWORD=<DB_PASS>|\" \
    -e \"s|^SESSION_SECURE=.*|SESSION_SECURE=true|\" \
    -e \"s|^INSTALL_TOKEN=.*|INSTALL_TOKEN=${INSTALL_TOKEN}|\" \
    .env
  chmod 600 .env
  echo \"INSTALL_TOKEN_SAVE=$INSTALL_TOKEN\"
  grep -E \"^(APP_ENV|APP_DEBUG|APP_URL|SESSION_SECURE|DB_DATABASE|DB_USERNAME)=\" .env
'"
```

Guardrail:

```bash
ssh lebytek-vps "sudo -u lebytek-crm awk -F= '/^DB_DATABASE=/ && \$2 != \"lebytek_crm\" { print \"FATAL\"; exit 1 }' /home/lebytek-crm/htdocs/crm.lebytek.com/.env && echo db_ok"
```

Expected: `db_ok`; `APP_URL=https://crm.lebytek.com`; `DB_DATABASE=lebytek_crm`.

---

### Task 9: Install plataforma, admin, `install.lock`

**Files:**
- VPS BD poblada; `storage/install.lock`; script temporal borrado

**Interfaces:**
- Consumes: `.env` + vendor (Task 8)
- Produces: login admin usable para smoke (Task 10)

- [ ] **Step 1: Dry-run del instalador**

```bash
ssh lebytek-vps "sudo -u lebytek-crm -H bash -lc 'cd /home/lebytek-crm/htdocs/crm.lebytek.com && php scripts/install.php --dry-run'"
```

Expected: plan de instalación nueva; módulos plataforma listados; `(dry-run: no se ejecutó nada)`.

- [ ] **Step 2: Ejecutar install + seed**

```bash
ssh lebytek-vps "sudo -u lebytek-crm -H bash -lc '
  cd /home/lebytek-crm/htdocs/crm.lebytek.com
  php scripts/install.php
  php scripts/seed.php
'"
```

Expected: `=== Instalación completada ===` (o equivalente) y bootstrap OK.

- [ ] **Step 3: Verificar esquema en `lebytek_crm`**

```bash
ssh lebytek-vps "mysql -u lebytek_crm -p'<DB_PASS>' lebytek_crm -e \"SHOW TABLES;\" | head -40
mysql -u lebytek_crm -p'<DB_PASS>' lebytek_crm -e \"SELECT COUNT(*) AS auth_users FROM auth_usuarios; SELECT clave FROM cfg_modulos LIMIT 10;\""
```

Expected: tablas `auth_*`, `cfg_*`; `cfg_modulos` con `core` (y módulos skeleton).

- [ ] **Step 4: Crear admin con script temporal**

```bash
ssh lebytek-vps "sudo -u lebytek-crm -H bash -lc '
cd /home/lebytek-crm/htdocs/crm.lebytek.com
cat > crear-admin-crm.php <<\"PHP\"
<?php
declare(strict_types=1);
define(\"ROOT_PATH\", __DIR__);
define(\"APP_PATH\", ROOT_PATH . \"/app\");
define(\"STORAGE_PATH\", ROOT_PATH . \"/storage\");
require ROOT_PATH . \"/vendor/autoload.php\";
use Lebytek\\Framework\\Kernel\\Config\\Config;
use Lebytek\\Framework\\Kernel\\Database\\Connection;
use Lebytek\\Framework\\Kernel\\EnvLoader;
use Lebytek\\Framework\\Kernel\\Security\\Hash;
EnvLoader::load(ROOT_PATH . \"/.env\");
Config::init(ROOT_PATH . \"/config\");
Connection::configure([
    \"host\" => Config::get(\"database.host\"),
    \"port\" => Config::get(\"database.port\"),
    \"database\" => Config::get(\"database.database\"),
    \"username\" => Config::get(\"database.username\"),
    \"password\" => Config::get(\"database.password\"),
    \"charset\" => \"utf8mb4\",
]);
\$nombre = \$argv[1] ?? \"Admin\";
\$apellido = \$argv[2] ?? \"CRM\";
\$email = \$argv[3] ?? \"admin@crm.lebytek.com\";
\$password = \$argv[4] ?? null;
if (\$password === null || strlen(\$password) < 12) {
    fwrite(STDERR, \"Uso: php crear-admin-crm.php <nombre> <apellido> <email> <password 12+>\\n\");
    exit(1);
}
if (Config::get(\"database.database\") !== \"lebytek_crm\") {
    fwrite(STDERR, \"ABORTA: DB must be lebytek_crm\\n\");
    exit(1);
}
\$pdo = Connection::getInstance();
\$exists = \$pdo->prepare(\"SELECT id FROM auth_usuarios WHERE email = ? LIMIT 1\");
\$exists->execute([\$email]);
if (\$exists->fetchColumn()) { echo \"exists\\n\"; exit(0); }
\$stmt = \$pdo->prepare(\"INSERT INTO auth_usuarios (nombre, apellido, email, password, activo, created_at) VALUES (?, ?, ?, ?, 1, NOW())\");
\$stmt->execute([\$nombre, \$apellido, \$email, Hash::make(\$password)]);
\$id = (int) \$pdo->lastInsertId();
\$rolId = \$pdo->query(\"SELECT id FROM auth_roles WHERE slug = \\\"administrador\\\" LIMIT 1\")->fetchColumn();
if (!\$rolId) { fwrite(STDERR, \"no rol administrador\\n\"); exit(1); }
\$pdo->prepare(\"INSERT IGNORE INTO auth_usuarios_roles (usuario_id, rol_id) VALUES (?, ?)\")->execute([\$id, (int) \$rolId]);
echo \"Usuario administrador creado. ID={\$id} email={\$email}\\n\";
PHP
ADMIN_PASS=$(LC_ALL=C tr -dc \"A-Za-z0-9\" </dev/urandom | head -c 20)
php crear-admin-crm.php Admin CRM admin@crm.lebytek.com \"$ADMIN_PASS\"
echo \"SAVE_ADMIN_PASS=$ADMIN_PASS\"
rm -f crear-admin-crm.php
'"
```

Expected: admin creado; password guardada fuera del servidor/chat logs si es posible.

- [ ] **Step 5: Escribir `install.lock`**

```bash
ssh lebytek-vps "sudo -u lebytek-crm -H bash -lc '
  cd /home/lebytek-crm/htdocs/crm.lebytek.com
  printf \"Installed via scripts/install.php at %s\n\" \"$(date -Iseconds)\" > storage/install.lock
  test -f storage/install.lock && echo lock_ok
'"
```

- [ ] **Step 6: Loopback login page**

```bash
ssh lebytek-vps "curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: crm.lebytek.com' http://127.0.0.1:8080/login"
```

Expected: `200`.

---

### Task 10: TLS Let's Encrypt + smoke HTTPS

**Files:**
- VPS: certificado `crm.lebytek.com`

**Interfaces:**
- Consumes: sitio + app instalada (Tasks 6–9); DNS A OK
- Produces: aceptación del spec (HTTPS + login)

- [ ] **Step 1: Emitir certificado**

```bash
ssh lebytek-vps "clpctl lets-encrypt:install:certificate --domainName=crm.lebytek.com"
```

Expected: éxito; nginx recargado por `clpctl`.

- [ ] **Step 2: Smoke HTTPS externo**

```bash
curl -sI https://crm.lebytek.com/login | head -15
curl -s -o /dev/null -w '%{http_code}\n' https://crm.lebytek.com/login
```

Expected: HTTP `200`; certificado válido (sin warning SSL en `curl -v` / navegador).

- [ ] **Step 3: Smoke login (CSRF + POST)**

```bash
cd /tmp
rm -f crm.jar
TOKEN=$(curl -s -c crm.jar https://crm.lebytek.com/login | grep -oP 'name="csrf_token" value="\K[^"]+' | head -1)
curl -s -b crm.jar -c crm.jar -o /tmp/crm-login-out.html -w '%{http_code} %{redirect_url}\n' \
  -X POST https://crm.lebytek.com/login \
  -d "email=admin@crm.lebytek.com&password=<ADMIN_PASS>&csrf_token=$TOKEN"
```

Expected: `302` hacia dashboard/home (no vuelta a login con error). Confirmar en navegador con la misma cuenta.

- [ ] **Step 4: Verificaciones finales de aceptación**

```bash
# Framework version on VPS
ssh lebytek-vps "sudo -u lebytek-crm -H bash -lc 'cd /home/lebytek-crm/htdocs/crm.lebytek.com && composer show lebytek/framework | head -5'"

# No Portal paths
ssh lebytek-vps "sudo -u lebytek-crm -H bash -lc 'test ! -e /home/lebytek-crm/htdocs/crm.lebytek.com/app/Domain/Marketing && echo no_marketing'"

# DB isolation
ssh lebytek-vps "sudo -u lebytek-crm grep '^DB_DATABASE=' /home/lebytek-crm/htdocs/crm.lebytek.com/.env"
```

Expected checklist del spec:

1. Repo privado + lock ^1.2.3 — OK  
2. Clone local editable — OK  
3. HTTPS — OK  
4. Login contra `lebytek_crm` — OK  
5. Sin Marketing — OK  
6. `docs/DEPLOY-VPS.md` — OK  
7. `ENVIRONMENTS.md` actualizado (Task 5 PR mergeado) — OK  

- [ ] **Step 5: Entregar credenciales al operador (fuera de git)**

Entregar por canal seguro (no commit):

- URL: `https://crm.lebytek.com`
- Admin: `admin@crm.lebytek.com` + password generada
- Recordar que site-user password y DB password viven solo en VPS/gestor de secretos

---

## Spec coverage (self-review)

| Spec requirement | Task |
|---|---|
| Repo privado `Lebytek_CRM` | 4 |
| Local path skeleton-based | 1 |
| Composer `lebytek/crm` + `^1.2.3` VCS + lock | 1–2 |
| Sin path/`*@dev` | 1–2 |
| Docs agente + DEPLOY | 3 |
| CloudPanel user/site PHP 8.1 + public root | 6 |
| TLS LE | 10 |
| DB `lebytek_crm` dedicada | 7 |
| Deploy key + clone + composer --no-dev | 8 |
| install/migrate + admin + lock | 9 |
| Smoke HTTPS + login | 10 |
| ENVIRONMENTS.md | 5 |
| No Portal/Marketing / no Framework src / no legacy merge | Constraints + Task 2 test |

## Placeholder scan

Sin TBD/TODO. Secrets se generan en runtime con placeholders `<SITE_PASS>`, `<DB_PASS>`, `<ADMIN_PASS>` que el ejecutor sustituye — no son requisitos incompletos del plan.
