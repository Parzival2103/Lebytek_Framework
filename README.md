# Lebytek Framework

Paquete Composer `lebytek/framework` — plataforma PHP para sistemas administrativos (RBAC, CRUD Engine, dashboard, módulos genéricos).

El código ejecutable de una aplicación vive en `skeleton/` (semilla para Repo 2). Este repositorio es el **paquete**, no una app desplegable por sí sola.

## Desarrollo local (monorepo)

```bash
composer install
php tests/run.php          # tests del paquete (framework)
php skeleton/tests/run.php # tests de dominio (Marketing, etc.)
```

Para probar la app en el monorepo:

```bash
cd skeleton
composer config repositories.local path ../
composer require lebytek/framework:@dev --no-interaction
php -S localhost:8000 -t public
```

## Consumo como paquete (privado)

Un proyecto lo consume con Composer vía repositorio VCS privado:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
],
"require": { "lebytek/framework": "^1.0" }
```

En el VPS, Composer necesita auth para el repo privado: configurar una **deploy key**
SSH (o un token de GitHub en `auth.json`). El deploy hace `composer install`.
El antiguo soporte de "hosting compartido sin Composer" queda DESCARTADO: el VPS
ya usa Composer (dompdf/phpmailer se instalan así).

## Estructura

| Ruta | Contenido |
|------|-----------|
| `src/` | Código del framework (`Lebytek\Framework\`) |
| `database/schema/` | Schema baseline del paquete |
| `tests/` | Arnés de tests del framework |
| `skeleton/` | Esqueleto de aplicación (config, rutas, dominio Marketing) |

Ver `docs/core/arquitectura.md` y `CLAUDE.md` para más detalle.
