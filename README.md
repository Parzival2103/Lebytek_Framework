# Lebytek Framework + Back-office

Monorepo desplegable en **lebytek.com** (hosting con document root en `public/`).

| Ruta | Contenido |
|------|-----------|
| `src/` | Framework (`Lebytek\Framework\`) — paquete Composer `lebytek/framework` |
| `app/` | Dominio de la app (`App\`) — marketing, leads, integración api |
| `config/`, `routes/`, `public/`, `storage/` | Capa aplicación |
| `database/schema/` | Schema del framework |
| `database/migrations/`, `database/seeds/` | Migraciones y seeds de la app |
| `tests/` | Tests del framework + dominio (Marketing, Integrations) |
| `docs/integration/` | Contrato api ↔ back-office (espejo de WhatsApiLebytek) |

## Setup local

```bash
cp .env.example .env
composer install
php scripts/install.php    # primera vez
php scripts/seed.php
php -S localhost:8000 -t public
php tests/run.php          # arnés completo
```

## Composer (repo privado en GitHub)

**No hace falta Packagist** para un paquete privado. Composer resuelve el repo vía VCS:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
],
"require": {
    "lebytek/framework": "^1.0"
}
```

Requisitos:

1. **Tag de versión** en GitHub (`v1.0.0`, `v1.1.0`, …) — Composer usa tags para `^1.0`.
2. **Auth** para repo privado: deploy key SSH o token en `auth.json` / `COMPOSER_AUTH`.
3. Ver guía completa: [`docs/composer-setup.md`](composer-setup.md).

Este repo se despliega **como aplicación** (clone + `composer install` en la raíz). Otros proyectos pueden consumir solo `lebytek/framework` vía Composer; el autoload del paquete carga `src/`, no `app/`.

## Integración api.lebytek.com

- Contrato: `docs/integration/waapi-api-contract.md`
- Guía de implementación: `docs/integration/waapi-implementation-real.md`
- Variables: `LEBYTEK_API_URL`, `LEBYTEK_API_TOKEN` en `.env`
- Desarrollo en branch `feature/backoffice-api-integration` (no mergear a `main` hasta cerrar Fase 2)

## Branches

| Branch | Uso |
|--------|-----|
| `main` | Estable; VPS auto-pull cuando esté listo |
| `feature/backoffice-api-integration` | Skeleton en raíz + cliente HTTP api + provisioning leads |
