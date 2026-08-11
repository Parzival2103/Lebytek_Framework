# Architecture: Framework as a Composer dependency

## Roles

| Role | Repo | Composer |
|------|------|----------|
| Platform | `Lebytek_Framework` | library `lebytek/framework` |
| Company tenant | `Lebytek_Portal` | project `lebytek/portal` |
| Other tenant | new repo from `skeleton/` | project |

## Path contract

- `ROOT_PATH` → consumer (env, config, app, public, storage)
- `PackagePaths::root()` → package checkout / `vendor/lebytek/framework`
- Platform SQL → `PackagePaths::schema()` / `resolveDataFile()`
- Business SQL → consumer `database/`
- Platform UI assets → consumer `public/assets/` (copied; see ASSETS-PLATFORM.md)

## Ownership: framework modules vs consumer business

| Concern | Owner | Namespace / path |
|---------|-------|------------------|
| Payments generic (Stripe gateway, event log, registry) | **Framework** | `Lebytek\Framework\Domain\Payments\`, toggled OFF by default |
| Checkout, orders, memberships, Stripe business rules | **Portal** | `App\Application\Marketing\`, `*mkt*` SQL |
| Invoicing generic (Facturapi provider, CFDI I ports, `inv_*` ledger, reconcile) | **Framework** | `Lebytek\Framework\Domain\Invoicing\`, toggled OFF by default |
| Invoice source data, fiscal CRM, invoice timing and domain UI | **Consumer** | `App\` implements `InvoiceableSourceInterface`; business SQL stays in `dom_*` |
| Invoicing HTTP routes, RBAC checks and Facturapi webhook endpoint | **Consumer** | Routes call Framework use cases/provider; webhook uses `FacturapiInvoiceProvider::parseWebhook` and no fiscal payload logs |

## Release compatibility notes

- Invoicing raises the package runtime to PHP `>=8.2` because
  `facturapi/facturapi-php` requires it. Consumers should take that tag as a
  major or otherwise explicitly documented breaking release before upgrading
  shared environments.
- The Facturapi SDK is required by Composer in the same dependency style as the
  Stripe gateway for Payments; consumers enable it only through
  `vertical.modules.invoicing` and `FACTURAPI_ENABLED`.

## Build a system

1. Copy `skeleton/` to a new git repo
2. `composer require lebytek/framework` (path locally; VCS+tag in shared envs)
3. `php scripts/migrate.php` / `seed.php` / `install.php` (wrappers)
4. Implement `App\` modules; toggle `config/vertical.php`
5. Never edit `vendor/`; never clone Portal to start a customer; **never deploy Framework root**

## Anti-patterns

- Dual git pull into one web root
- Copying platform `schema.sql` into the consumer as SoT
- Shipping Lebytek marketing inside skeleton
- Path-autoloading Framework `src/` from the consumer composer.json
- Reintroducing Marketing into the framework package to fix integration tests
