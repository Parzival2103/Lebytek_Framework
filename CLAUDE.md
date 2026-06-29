# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Lebytek Framework** — A PHP 8.1+ platform framework for building administrative systems. It provides authentication, RBAC, a dynamic admin menu, an extensible dashboard, and a CRUD Engine. The codebase is intentionally free of domain logic; business modules are added as verticals on top.

The local clone lives at `C:\Users\User\OneDrive\Desktop\sistemas\contraste` and tracks `https://github.com/Parzival2103/Lebytek_Framework`. A VPS auto-pulls from `main` (testing environment), so pushes go directly without PRs.

> **Note:** This is the framework **source** repo. The framework is consumed (via Composer,
> from `vendor/`) by separate application repos — e.g. the skeleton in `skeleton/`, which
> carries its own `CLAUDE.md` and `.cursor/rules` describing the read-only-`vendor/`
> working model. Here in the source repo, the framework code under `src/` is edited
> directly through the usual spec → plan → change flow.

---

## Common Commands

```bash
# Install dependencies
composer install

# Start local dev server (entry point is public/)
php -S localhost:8000 -t public

# Run database seeds (executed in lexicographic order)
php scripts/seed.php

# Run tests
./vendor/bin/phpunit
```

---

## Architecture: MVC + Onion (5 layers)

**Request flow:** `public/index.php` → `Bootstrap.php` → Router → Controller → UseCase → Domain → Repository → Response

### The 5 Layers (inside `/app`)

| Layer | Location | Responsibility |
|---|---|---|
| **Presentation** | `app/Presentation/` | Controllers, Views (PHP/Bootstrap 5), Middlewares (Auth, CSRF, RBAC), Requests, Responses |
| **Application** | `app/Application/` | UseCases, Services, DTOs, Validators, Mappers |
| **Domain** | `app/Domain/` | Entities, ValueObjects, Interfaces (Repositories), Policies, Rules, Exceptions |
| **Infrastructure** | `app/Infrastructure/` | Repository implementations (PDO), Database connection, Logging, Storage |
| **Kernel** | `app/Kernel/` | Bootstrap, Autoloader, EnvLoader, Config, Container (DI), Router, Session, BaseClasses |

**Dependency rule:** outer layers depend inward. Domain has zero external dependencies. Infrastructure implements Domain interfaces. Kernel is transversal but imported only by Presentation/Bootstrap.

### Key Kernel Components

- **`Bootstrap.php`** — Init sequence: Autoloader → ENV → Config → Error handlers → Session → DB → Container → Router → dispatch
- **`Container/Container.php`** — Simple singleton/bind DI container; all bindings in `config/container.php`
- **`Http/Router.php`** — Custom router with group support and per-route middleware; routes defined in `routes/web.php` and `routes/api.php`
- **`Database/Connection.php`** — Lazy PDO singleton; configured from `config/database.php`

---

## Configuration Files

| File | Purpose |
|---|---|
| `.env` | Runtime secrets (copy from `.env.example`) |
| `config/app.php` | App name, env, debug, timezone, locale |
| `config/database.php` | DB connection params |
| `config/container.php` | All DI bindings (100+ entries) |
| `config/vertical.php` | Toggle platform modules (dashboard, administracion) |
| `config/dashboard.php` | Dashboard providers list |
| `config/crud_handlers.php` | Resource → custom handler map |
| `config/cruds/{resource}.php` | Per-resource CRUD Engine config |

---

## Platform Modules

### RBAC
- Permissions use slug format `modulo.accion` (e.g., `dashboard.ver`, `usuarios.gestionar`)
- Enforced by `RbacMiddleware` and `RbacPolicy`
- Tables: `auth_usuarios`, `auth_roles`, `auth_permisos`, `auth_roles_permisos`, `auth_usuarios_roles`

### CRUD Engine
A generic CRUD system driven by config files in `config/cruds/`. To add a new resource:
1. Create `config/cruds/{resource}.php` with field definitions
2. Register a hook handler in `config/crud_handlers.php` (optional)
3. Routes are auto-handled by `CrudController`
4. See `docs/modules/crud/` for full spec

### Dashboard Providers
- Implement `DashboardContributionProviderInterface`
- Register in `config/dashboard.php`
- The `BuildDashboardViewModelUseCase` aggregates all providers (KPIs, activity, quick access, status, and `widgets`)

### Calendar Module (optional)
- Read-only layer over the CRUD Engine: renders a CRUD resource as calendar views (month/week/day/table) plus a dashboard mini-calendar widget
- A calendar is defined in `config/calendars/{key}.json` and references a CRUD resource by `key`; it inherits table, permissions, scope and forms
- Read path only — all editing reuses existing `/admin/crud/...` endpoints (CSRF + global `#confirmModal`)
- Routes: `/admin/calendario/{key}` (shell) and `/admin/calendario/{key}/eventos` (JSON feed, scoped + filtered)
- Module manifest: `config/modules/calendario.php`; toggle `modules.calendario` in `config/vertical.php`
- See `docs/modules/modulo-calendario.md` for the full spec

### Dynamic Admin Menu
- Driven by `core_menu_items` table (seeded)
- Controlled per-module via `config/vertical.php`
- Filtered by user RBAC at render time

---

## Database Conventions

Table prefixes are mandatory:
- `auth_*` — authentication and authorization
- `cfg_*` — system configuration
- `log_*` — audit logs and bitácora
- `core_*` — menu items, module metadata
- `dom_*` — reserved for business domain modules (never in base schema)
- `int_*, rep_*, tmp_*, sys_*` — integrations, reports, jobs, key-value

Schema baseline: `database/schema/schema.sql`. Seeds run via `php scripts/seed.php`.

Default admin: `admin@sistema.local` (change password before any deployment).

---

## Naming Conventions

- **PHP classes:** PascalCase; methods camelCase; constants UPPER_SNAKE_CASE
- **DB tables/columns:** plural snake_case; foreign keys `tabla_id`
- **API routes:** `/api/recurso`; JSON keys camelCase
- **View/route files:** snake_case.php
- **Permission slugs:** `modulo.accion`

---

## Adding a New Business Module (Vertical)

Follow the checklist in `docs/modules/uso-de-modulo-dominio.md`. The short version:
1. Add `dom_*` tables in a new migration file
2. Create Domain entities and interfaces
3. Create Infrastructure repository implementation
4. Register in `config/container.php`
5. Add Application UseCases and Services
6. Add Presentation Controller and Views
7. Register routes in `routes/web.php`
8. Add RBAC permissions (seed SQL) and menu entry (`core_menu_items`)
9. Toggle module in `config/vertical.php`

---

## Documentation

Full architecture and module docs live in `/docs/core/` and `/docs/modules/`. Start with:
- `docs/core/arquitectura.md` — layer rules and principles
- `docs/core/convenciones_nombres.md` — naming reference
- `docs/core/auth_rbac_seguridad_v0.1.md` — security model
