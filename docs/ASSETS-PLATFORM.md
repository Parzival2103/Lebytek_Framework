# Platform UI assets (accepted debt A6)

`ViewHelper::asset()` serves URLs under the **consumer** `public/assets/`.
The package does not publish assets via Composer plugin in this cycle.

## Canonical files (must exist in skeleton + Portal)

- css: `app.css`, `lebytek-ui.css`, `crud-engine.css`
- js: `app.js`, `crud-engine.js`, `calendar.js`, `avatar-manager.js`, `reportes-builder.js`
- `icons/app-icon.svg`, `images/logo.png`

## Product-only assets (Portal)

- `public/assets/publico/**` — landing Lebytek; **never** in skeleton

## On framework UI bumps

1. Diff those files in Framework harness / `skeleton/public/assets`
2. Copy into each consumer (`Portal`, other tenants)
3. Bump `config app.asset_version` in the consumer

Follow-up (out of scope): Composer plugin or `scripts/publish-assets.php`.
