# CLAUDE.md

Guidance for working in **Lebytek_Framework** — monorepo desplegable en lebytek.com.

## What this repo is

| Ruta | Rol |
|------|-----|
| `src/` | Framework (`Lebytek\Framework\`) — paquete Composer `lebytek/framework` |
| `app/` | Back-office (`App\`) — marketing, leads, integración con api.lebytek.com |
| `public/` | Document root |
| `docs/integration/` | Contrato api ↔ back-office |

**Modelo monorepo:** el framework se edita en `src/`. La app se edita en `app/`, `config/`, `routes/`. Un solo `composer install` en la raíz autoloada ambos. No hay `vendor/lebytek/framework` en desarrollo local de este repo.

Integración api:
- Contrato API: `docs/integration/waapi-api-contract.md`
- Delegación roles (lebytek.com ↔ api): `docs/integration/role-delegation-lebytek-api.md`
- Guía implementación back-office: `docs/integration/lebytek-implementation-real.md`
- Spec alineación docs (repo api): ver `WhatsApiLebytek/docs/superpowers/specs/2026-06-30-integration-docs-alignment-design.md`

## Commands

```bash
composer install
cp .env.example .env
php scripts/install.php
php scripts/seed.php
php -S localhost:8000 -t public
php tests/run.php
php tests/run.php Marketing
```

## Architecture

**Request flow:** `public/index.php` → `Lebytek\Framework\Kernel\Bootstrap` → Router → Controller → UseCase → Domain → Repository

| Layer | App (`app/`) | Framework (`src/`) |
|-------|--------------|---------------------|
| Presentation | Controllers, Views, Middlewares | Base controllers, view helpers |
| Application | UseCases, Services | CRUD engine, dashboard builders |
| Domain | Marketing, leads, entities | Interfaces, policies base |
| Infrastructure | PDO repos, LebytekApiClient | Framework persistence, mail |
| Kernel | — | Bootstrap, Router, DI, Session |

## Branches

- `main` — estable; VPS auto-pull cuando esté listo.
- `feature/backoffice-api-integration` — skeleton en raíz + contratos api (trabajo actual).

## Rules

- Cambios de plataforma → `src/`.
- Cambios de negocio / integración api → `app/`, `config/`, `routes/`.
- **Never** commit `.env`, tokens, or `vendor/`.
- Contrato api vive también en WhatsApiLebytek; mantener sincronizados al cambiar endpoints.

## Composer / deploy

Ver `docs/composer-setup.md`. Repo privado GitHub + tags semver; Packagist no requerido.

Variables api en `.env`: `LEBYTEK_API_URL`, `LEBYTEK_API_TOKEN`.
