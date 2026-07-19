# Schema ownership

| Layer | Owner | Apply with |
|-------|-------|------------|
| Platform `auth_*`, `cfg_*`, `core_*`, … | Framework package | Consumer wrapper → package scripts (`PackagePaths`) |
| Platform modules calendario, pdf-kit, reportes, crud-engine, integrations, **payments** | Framework package | `resolveDataFile` / `moduleSchema` |
| Marketing / `dom_mkt_*` / `*mkt*` migrations | **Portal** | `php scripts/migrate-marketing.php` + Portal `database/migrations/*mkt*` |

Installer resolves migration/seed **filenames** package-first, then ROOT_PATH.

Portal must **not** vendor a fork of platform `schema.sql` as source of truth.
