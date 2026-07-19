# Lebytek Framework

Composer package **`lebytek/framework`** — reusable platform (auth, RBAC, CRUD Engine, Kernel).

**This repo is NOT the deployable lebytek.com site.** The company tenant lives in **`Lebytek_Portal`**. New customer tenants start from **`skeleton/`**, not from Portal or this repo root.

| Path | Role |
|------|------|
| `src/` | Framework package (`Lebytek\Framework\`) |
| `skeleton/` | Minimal consumer template for new tenants |
| `database/`, `scripts/` | Platform SQL and install scripts shipped in the package |
| `tests/` | Platform test harness |
| Root `config/`, `public/`, stub `app/` | **Test harness only — do not deploy** |

## Documentation

| Doc | Topic |
|-----|-------|
| [`docs/PACKAGE-ROOT.md`](docs/PACKAGE-ROOT.md) | What belongs in this repo vs consumers |
| [`docs/ARCHITECTURE-CONSUMER.md`](docs/ARCHITECTURE-CONSUMER.md) | Framework as Composer dependency |
| [`docs/TENANTS.md`](docs/TENANTS.md) | Framework vs Portal vs customer skeleton |
| [`docs/database/SCHEMA-OWNERSHIP.md`](docs/database/SCHEMA-OWNERSHIP.md) | Platform vs business SQL ownership |
| [`docs/ASSETS-PLATFORM.md`](docs/ASSETS-PLATFORM.md) | Platform assets copied to consumer `public/assets/` |
| [`CLAUDE.md`](CLAUDE.md) | Agent quick reference |

## Local setup (package maintainer harness)

```bash
cp .env.example .env
composer install
php tests/run.php              # full harness
php tests/run.php Kernel       # subset
php tests/run.php FpsDocumentation
```

Optional smoke (harness only, not production deploy):

```bash
php scripts/install.php
php scripts/seed.php
php -S localhost:8000 -t public
```

## Consuming the package

Other projects install via Composer (VCS + semver tags). See [`docs/composer-setup.md`](docs/composer-setup.md).

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
],
"require": {
    "lebytek/framework": "^1.0"
}
```

Requirements: version tags on GitHub, auth for private repo (deploy key or `COMPOSER_AUTH`).

**Deploy:** `Lebytek_Portal` or a tenant repo from `skeleton/` + `composer install`. Never use this repo root as document root on VPS.

## Branches

| Branch | Use |
|--------|-----|
| `main` | Stable package releases |
| `consolidation/framework-portal-separation` | FPS consolidation (Framework ↔ Portal split) |

Do not merge integration branches to `main` without explicit team approval.
