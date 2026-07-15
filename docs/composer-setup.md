# Composer — publicar y consumir `lebytek/framework`

El paquete **no necesita Packagist público** si el repo es privado en GitHub. Composer instala directamente desde VCS.

## 1. Publicar versiones (maintainer)

En el repo `Parzival2103/Lebytek_Framework`:

```bash
# Tras merge a main de una release estable:
git tag -a v1.0.0 -m "Framework Lebytek v1.0"
git push origin v1.0.0
```

Composer resuelve `^1.0` contra tags semver (`v1.0.0`, `v1.0.1`, `v1.1.0`).

El `composer.json` raíz declara:

```json
"name": "lebytek/framework",
"type": "library",
"autoload": {
    "psr-4": {
        "Lebytek\\Framework\\": "src/",
        "App\\": "app/"
    }
}
```

- Consumidores externos que solo quieren el framework: autoload efectivo en `src/`.
- Este monorepo desplegable: clone completo + `composer install` en raíz.

### ¿Packagist?

| Opción | Cuándo |
|--------|--------|
| **Solo GitHub + tags** | Repo privado, un equipo — **recomendado ahora** |
| **Packagist privado** | Varios equipos, mirrors, CI centralizado |
| **Packagist público** | Open source — no aplica |

## 2. Consumir en otro proyecto PHP

`composer.json` del proyecto consumidor:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Parzival2103/Lebytek_Framework"
        }
    ],
    "require": {
        "lebytek/framework": "^1.0"
    }
}
```

```bash
composer install
```

## 3. Autenticación (repo privado)

### Opción A — Token GitHub (hosting compartido / CI)

Archivo `auth.json` (nunca en git):

```json
{
    "github-oauth": {
        "github.com": "ghp_XXXXXXXXXXXXXXXXXXXX"
    }
}
```

O variable de entorno:

```bash
export COMPOSER_AUTH='{"github-oauth":{"github.com":"ghp_..."}}'
```

Permiso mínimo del token: **repo** (read).

### Opción B — Deploy key SSH (VPS)

1. Generar par SSH en el servidor.
2. Añadir clave pública como **Deploy key** (read-only) en GitHub → repo settings.
3. En `composer.json` del servidor, URL SSH:

```json
"url": "git@github.com:Parzival2103/Lebytek_Framework.git"
```

## 4. Desplegar lebytek.com (este monorepo)

No uses `composer create-project` para producción si ya tienes el repo clonado:

```bash
git clone git@github.com:Parzival2103/Lebytek_Framework.git .
git checkout feature/backoffice-api-integration
cp .env.example .env
composer install --no-dev
php scripts/migrate.php
php scripts/seed.php
```

Document root del hosting: **`public/`**.

Subida manual FTP: sincronizar todo el repo **excepto** `.env`, `vendor/` (regenerar con `composer install` en servidor si hay Composer), y `storage/logs/*`.

## 5. Pin a branch de feature (desarrollo)

Mientras la integración api no esté en `main`:

```json
"require": {
    "lebytek/framework": "dev-feature/backoffice-api-integration"
}
```

O en clone directo del monorepo: `git checkout feature/backoffice-api-integration`.

## 6. Checklist antes de implementar contratos api

- [ ] Tag `v1.0.0` en `main` (si aún no existe en GitHub remoto)
- [ ] `.env` con `LEBYTEK_API_URL` y `LEBYTEK_API_TOKEN` (token emitido en api VPS)
- [ ] `composer install` OK en local
- [ ] `php tests/run.php` verde
- [ ] `docs/integration/waapi-api-contract.md` revisado en este repo
