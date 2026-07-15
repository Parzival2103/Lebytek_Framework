# CLAUDE.md

Guidance for Claude Code (claude.ai/code) when working in this repository.

## What this is

This is a **consuming application** built on top of the **Lebytek Framework**. The framework
is pulled in via Composer (`lebytek/framework`) and lives under `vendor/lebytek/framework/`.
Your own code lives in `app/` (namespace `App\`, PSR-4) plus `config/`, `routes/`,
`database/`, `public/`, `tests/`.

The framework provides the platform: authentication, RBAC, dynamic admin menu, dashboard,
the CRUD Engine, and the Kernel (bootstrap, router, DI container, session, security, DB).
This app adds business modules (`dom_*`) as verticals on top.

---

## Working Model: framework consumed from `vendor/` (READ THIS FIRST)

The framework under `vendor/lebytek/framework/` is **read-only here** — exactly like any
Laravel package under `vendor/`. Day-to-day work happens on the app, never on `vendor/`.

**Hard rules:**

- **Never edit, create, or delete files under `vendor/`** — not even temporarily, not even
  to debug. Such changes are lost on the next `composer update` and silently diverge from
  the framework source.
- The framework **can** be modified, but the change is made in **another repository** — the
  framework source repo (`Lebytek_Framework`,
  https://github.com/Parzival2103/Lebytek_Framework) — never as a `vendor/` patch.
- Modifying the framework follows a formal flow: **spec → plan → change** in the framework
  repo, then pull it back here with `composer update lebytek/framework`.

**When a diagnosed bug lives inside the framework (`vendor/`), always:**

1. State it plainly: *"This lives in the framework (vendor), not in the app."*
2. Remind the model: the framework is modifiable, but the change goes through the
   `Lebytek_Framework` repo via spec + plan — not by editing `vendor/`.
3. Offer to draft that spec/plan (problem, affected layer/class, proposed change, impact,
   acceptance criteria) instead of touching `vendor/`.
4. If urgent, propose an **app-side workaround** without touching `vendor/`: DI override in
   `config/container.php`, a decorator/subclass in `app/`, a config change, or a custom
   middleware/handler. Mark it temporary; the real fix is the framework spec.

The same discipline will apply to Laravel later: `vendor/` is never edited — it's extended.

---

## Common Commands

```bash
# Install / update the framework dependency
composer install
composer update lebytek/framework   # pull a new framework version after a source change

# Local dev server (entry point is public/)
php -S localhost:8000 -t public

# Database seeds
php scripts/seed.php   # if present

# Tests
./vendor/bin/phpunit
```

---

## Architecture (app side)

The app reuses the framework's MVC + Onion layering. Your code is organized in four layers
under `app/` — the **Kernel is provided by the framework**, not recreated here:

| Layer | Location | Responsibility |
|---|---|---|
| Presentation | `app/Presentation/` | Controllers, Views, Middlewares, Requests, Responses |
| Application | `app/Application/` | UseCases, Services, DTOs, Validators, Mappers |
| Domain | `app/Domain/` | Entities, ValueObjects, Interfaces, Policies, Rules |
| Infrastructure | `app/Infrastructure/` | Repository implementations (PDO), external services |

Dependency rule: outer layers depend inward; Domain has zero external dependencies. Any
layer may use the framework's Kernel/base classes from `vendor/`, but never edits them.

Modules group by domain inside each layer (e.g. `app/Domain/Marketing`,
`app/Application/Marketing`, `app/Infrastructure/Marketing`).

---

## Configuration

| File | Purpose |
|---|---|
| `.env` | Runtime secrets (copy from `.env.example`) |
| `config/container.php` | DI bindings — the place to override framework services |
| `config/vertical.php` | Toggle platform/business modules |
| `config/cruds/{resource}.php` | Per-resource CRUD Engine config |

To override or extend framework behavior, bind your own implementation in
`config/container.php` or subclass/decorate in `app/` — do not edit `vendor/`.

---

## Conventions

- DB table prefixes: `auth_*`, `cfg_*`, `log_*`, `core_*` are framework-owned; business
  domain modules use `dom_*`.
- Permission slugs: `modulo.accion` (e.g. `clientes.ver`).
- PHP: PascalCase classes, camelCase methods, UPPER_SNAKE_CASE constants.
- App namespace `App\`; framework namespace `Lebytek\Framework\`.
