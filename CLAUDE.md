# CLAUDE.md — Lebytek Framework (package source)

**This repo ships the Composer library `lebytek/framework`. It is NOT the deployable lebytek.com site.**

| Path | Role |
|------|------|
| `src/` | Framework package (`Lebytek\Framework\`) |
| `skeleton/` | Minimal consumer template for new tenants |
| `database/`, `scripts/` | Platform SQL shipped in the package |
| `tests/` | Platform test harness |
| Root `config/`, `public/`, stub `app/` | **Test harness only — no deploy** |
| Portal Lebytek | Separate repo `Lebytek_Portal` |

## Commands

```bash
composer install
php tests/run.php
php tests/run.php Kernel
php tests/run.php Payments
php tests/run.php SkeletonPurity
```

## Architecture

- Platform changes → `src/`, platform SQL, `skeleton/` template
- **No** Marketing / LebytekApi / Publico in this repo (Portal owns that)
- Payments generic → `src/Domain/Payments/` (OFF by default via vertical)
- Consumers install via Composer; never path-autoload `src/` from Portal

## Branches

- `main` — canonical base for Framework audits, specs, plans, implementation and releases
- `consolidation/framework-portal-separation` — historical FPS consolidation branch
- `feature/backoffice-api-integration` — frozen legacy monolith reference; never use as the base for new work
- **Do not merge** `feature/backoffice-api-integration` → `main` without explicit user order

The deployed application is `Lebytek_Portal` on its `main` branch. Consumers
receive Framework through a tagged Composer release and `composer.lock`.
Canonical automation prompts live in `docs/automation/`.

## Docs

- `docs/ENVIRONMENTS.md` — skeleton vs staging vs prod (canónico)
- `docs/ARCHITECTURE-CONSUMER.md`
- `docs/PACKAGE-ROOT.md`
- `docs/database/SCHEMA-OWNERSHIP.md`
