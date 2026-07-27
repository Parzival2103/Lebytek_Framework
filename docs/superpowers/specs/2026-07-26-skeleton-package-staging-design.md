# Publicación de `lebytek/skeleton` y reconstrucción de staging.lebytek.com

Fecha: 2026-07-26
Estado: diseño aprobado, pendiente de plan de implementación

## Problema

`staging.lebytek.com` es hoy una copia literal de `Lebytek_Portal` sin repositorio git,
que consume el framework desde la rama `dev-consolidation/framework-portal-separation`,
apunta a la base de datos de producción `lebytek` con las mismas credenciales que
`lebytek.com`, declara `APP_ENV=production`, y sirve un certificado TLS autofirmado que
lo deja inaccesible desde fuera (HTTP 000; responde 200 en `127.0.0.1:8080`).

El objetivo es que staging pase a ser un **skeleton genérico** que consume el framework
como paquete Composer versionado, aislado de producción.

Adicionalmente se elimina la rama `feature/backoffice-api-integration` de
`Lebytek_Framework`, que ningún despliegue consume.

## Estado de partida verificado (VPS `srv1586067`, 2.24.197.198)

CloudPanel + nginx. Patrón común: nginx 443 → proxy `127.0.0.1:8080` → server interno → php-fpm.

| Dominio | Usuario | Contenido | PHP | HTTP |
|---|---|---|---|---|
| `lebytek.com` | `lebytek` | clone de `Lebytek_Portal@main` (`2718212`), `lebytek/framework v1.1.0` | 8.1 (:16002) | 200 |
| `waapi.lebytek.com` | `lebytek-waapi` | clone de `Lebytek_Portal@main` (`6cdb957`), `lebytek/framework v1.1.0` | 8.1 (:16001) | 200 |
| `staging.lebytek.com` | `lebytek-stg` | copia del Portal sin git, framework `dev-consolidation/...` | 8.4 (:19001) | 000 |
| `api.lebytek.com` | `lebytek-api` | Laravel WhatsApiLebytek + Horizon/worker (supervisor) | 8.5 | — |
| `docs.lebytek.com` | `lebytek-docs` | documentación | 8.3 | — |

Tooling disponible: Composer 2.10.1, PHP 8.4.22 como CLI por defecto, `clpctl`, git 2.43
con `subtree`.

`Lebytek_Framework@main` ya está separado: `src/` es el paquete, `skeleton/` la plantilla
genérica, `app/` sólo un `README.md` stub. Tags publicados: `v1.0.0`, `v1.1.0`,
`pre-split-backup`.

## Decisiones tomadas

| Decisión | Elección | Motivo |
|---|---|---|
| Distribución del skeleton | Paquete `lebytek/skeleton` vía subtree split | `skeleton/` sigue siendo fuente de verdad en el framework, protegido por `SkeletonPurityTest` |
| Base de datos de staging | BD dedicada `lebytek_stg` | Los CRUDs demo deben existir tal cual sin ensuciar producción |
| Certificado | Let's Encrypt vía `clpctl` | Staging hoy es inaccesible desde el navegador |
| Repo espejo | Creado con `gh`, privado | Consistente con Framework y Portal |
| Rama a eliminar | `feature/backoffice-api-integration` | Ningún despliegue la consume |

## Estado objetivo

```
Lebytek_Framework (main)      → paquete lebytek/framework; src/ + skeleton/ (fuente)
      │ git subtree split --prefix=skeleton
      ▼
Lebytek_Skeleton (main, tags) → paquete lebytek/skeleton  ──┐
                                                            │ composer create-project
Lebytek_Portal (main)         ──┐                           │
                                │ git clone                 ▼
lebytek.com          ← Portal ──┘         staging.lebytek.com ← skeleton
   + framework v1.1.0                        + framework v1.1.0
   (ya en estado objetivo)                    + BD lebytek_stg
                                              + Let's Encrypt
```

## Componentes

### 1. `skeleton/composer.json` — invertir el origen del framework

Hoy declara el repositorio `path` del monorepo, que no es desplegable. Pasa a declarar el
consumidor real:

```diff
- "lebytek/framework": "*@dev"
+ "lebytek/framework": "^1.1"
  "repositories": [
-   { "type": "path", "url": "..", "options": { "symlink": true } }
+   { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
  ]
- "minimum-stability": "dev"
+ "minimum-stability": "stable"
```

El harness local del framework recupera el modo monorepo con
`composer config repositories.local path ../`, ejecutado a demanda y **no versionado**.
Se documenta en `docs/composer-setup.md`.

Con este cambio el subtree split sale literal: no hay que transformar archivos al publicar.

### 2. `scripts/publish-skeleton.sh` — publicación del espejo

Sustituye a `scripts/vps-deploy-skeleton.sh`, que pertenece a la era monorepo (clona el
repo completo y usa `composer config repositories.local path ../`) y se elimina.

Responsabilidad única: tomar `skeleton/` de `Lebytek_Framework@main` y publicarlo como
raíz de `Parzival2103/Lebytek_Skeleton`.

```bash
git subtree split --prefix=skeleton -b split/skeleton
git push git@github.com:Parzival2103/Lebytek_Skeleton.git split/skeleton:main
git branch -D split/skeleton
```

El tag `v1.1.0` se aplica en el repo espejo para alinearlo con la versión del framework
que consume.

Interfaz: sin argumentos, lee de `main`, falla si el árbol de trabajo está sucio.
Depende de: git 2.43+ con subtree, acceso push al repo espejo.

### 3. `Parzival2103/Lebytek_Skeleton` — repo espejo

Creado con `gh repo create Parzival2103/Lebytek_Skeleton --private`. Es un artefacto de
publicación: **no se edita directamente**, siempre se regenera desde el framework. Se
documenta esa regla en su `README.md`.

### 4. Despliegue de `staging.lebytek.com`

El directorio actual se archiva, no se borra:
`staging.lebytek.com` → `staging.lebytek.com.portal-copy-20260726`.

Como usuario `lebytek-stg`:

```bash
composer create-project lebytek/skeleton staging.lebytek.com \
  --repository='{"type":"vcs","url":"https://github.com/Parzival2103/Lebytek_Skeleton"}' \
  --no-dev
```

Esto resuelve `lebytek/framework v1.1.0` a `vendor/` a través del `repositories` VCS que
el propio skeleton declara.

**nginx no se toca.** El vhost ya apunta a `.../public` y el pool php-fpm 8.4 (:19001) con
usuario `lebytek-stg` ya existe.

### 5. Base de datos `lebytek_stg`

```bash
clpctl db:add --domainName=staging.lebytek.com --databaseName=lebytek_stg \
  --databaseUserName=<usuario> --databaseUserPassword=<password>
```

Sobre esa BD vacía se ejecuta el instalador desde vendor, que detecta el proyecto
consumidor por `ROOT_PATH`:

```bash
php vendor/lebytek/framework/scripts/install.php
php vendor/lebytek/framework/scripts/seed.php
```

Crea el esquema de plataforma más las tablas demo, dejando los CRUDs `demo_*` del skeleton
funcionales sin ajustes. La BD `lebytek` de producción no se toca en ningún paso.

### 6. `.env` de staging

Generado desde `.env.example` con:

| Clave | Valor |
|---|---|
| `APP_URL` | `https://staging.lebytek.com` |
| `APP_ENV` | `staging` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | aleatorio de 32 caracteres, nuevo |
| `SESSION_SECURE` | `true` |
| `DB_DATABASE` | `lebytek_stg` |
| `DB_USERNAME` / `DB_PASSWORD` | credenciales del paso 5 |

### 7. Certificado TLS

```bash
clpctl lets-encrypt:install:certificate --domainName=staging.lebytek.com
```

Se ejecuta **después** del despliegue, para que el challenge `.well-known` lo sirva la
aplicación nueva. El DNS ya resuelve al VPS y la app responde 200 en `:8080`, de modo que
el challenge no requiere cambios adicionales de nginx.

### 8. Eliminación de la rama

```bash
git push origin --delete feature/backoffice-api-integration
git branch -D feature/backoffice-api-integration
```

## Orden de ejecución

El orden importa: `staging.lebytek.com` consume hoy
`dev-consolidation/framework-portal-separation`, y sólo deja de hacerlo tras el paso 4.

1. `skeleton/composer.json` → VCS + `^1.1` (componente 1)
2. `scripts/publish-skeleton.sh`; eliminar `vps-deploy-skeleton.sh` (componente 2)
3. Crear repo espejo, publicar subtree, taggear `v1.1.0` (componentes 2 y 3)
4. Crear BD `lebytek_stg` (componente 5)
5. Archivar staging actual y `composer create-project` (componente 4)
6. Generar `.env` (componente 6)
7. Ejecutar `install.php` y `seed.php` (componente 5)
8. Emitir certificado Let's Encrypt (componente 7)
9. Verificar (ver abajo)
10. Eliminar `feature/backoffice-api-integration` (componente 8)

## Manejo de errores y rollback

Cada paso del VPS es reversible por separado:

| Falla en | Rollback |
|---|---|
| `create-project` (pasos 5–7) | `mv staging.lebytek.com.portal-copy-20260726` de vuelta |
| Let's Encrypt (paso 8) | El cert autofirmado sigue en su sitio; staging queda como estaba |
| Publicación del espejo (paso 3) | Borrar el repo espejo; el framework no cambia de estado |

`lebytek.com` y `waapi.lebytek.com` no se modifican en ningún paso. El directorio
archivado se conserva hasta que la verificación pase.

## Verificación

| Comprobación | Resultado esperado |
|---|---|
| `curl -I https://staging.lebytek.com/login` | 200 con certificado válido (issuer Let's Encrypt, no CN autofirmado) |
| `composer show lebytek/framework` en staging | `v1.1.0` |
| `ls staging.lebytek.com/routes` | sin `marketing.php`, `marketing_admin.php`, `waapi_portal.php` |
| CRUDs demo en staging | navegables sin error |
| `curl -o /dev/null -w '%{http_code}' https://lebytek.com/` | 200 |
| `curl -o /dev/null -w '%{http_code}' https://waapi.lebytek.com/` | 200 |
| `php tests/run.php` en el framework | verde, incluido `SkeletonPurityTest` |
| `gh api .../branches` | sin `feature/backoffice-api-integration` |

## Fuera de alcance

- **`waapi.lebytek.com` está 2 commits atrás de `lebytek.com`** (`6cdb957` vs `2718212`).
  Es un tema independiente; no se toca aquí.
- **`consolidation/framework-portal-separation`** queda sin consumidores tras el paso 5,
  pero no se elimina en este trabajo.
- **Ramas `cursor/*` y `automation/audit-spec-*`** (~15 acumuladas): fuera de alcance.
- **Directorios de backup del corte del 21-jul**
  (`lebytek.com.monorepo-backup-*`, `waapi.lebytek.com.monorepo-backup-*`,
  `waapi.lebytek.com.prev-*`): fuera de alcance.
- **Auth básica sobre staging**: descartada, ya no aplica al aislar la BD.
