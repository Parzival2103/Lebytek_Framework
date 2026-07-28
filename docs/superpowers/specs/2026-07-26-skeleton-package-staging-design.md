# Publicación de `lebytek/skeleton` y despliegue de skeleton.lebytek.com

Fecha: 2026-07-26  
Estado: diseño aprobado; plan de implementación en `plans/2026-07-26-skeleton-package-staging.md` (dominio objetivo: **skeleton.lebytek.com**)

## Problema

`skeleton.lebytek.com` es hoy una copia literal de `Lebytek_Portal` sin repositorio git,
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
| `skeleton.lebytek.com` | `lebytek-stg` | copia del Portal sin git, framework `dev-consolidation/...` | 8.4 (:19001) | 000 |
| `api.lebytek.com` | `lebytek-api` | Laravel WhatsApiLebytek + Horizon/worker (supervisor) | 8.5 | — |
| `docs.lebytek.com` | `lebytek-docs` | documentación | 8.3 | — |

Tooling disponible: Composer 2.10.1, PHP 8.4.22 como CLI por defecto, `clpctl`, git 2.43
con `subtree`.

### Mecanismo de despliegue de `lebytek.com`

**No existe auto-pull.** Verificado: sin cron de despliegue (crontab de `lebytek` y de root,
`/etc/cron.d`), sin systemd timers, sin hooks de git (`.git/hooks` vacío), sin endpoint
webhook en `public/`. Los tres crons del usuario `lebytek` son de negocio
(`expire-membership-grace.php`, `expire-api-demos.php`, `lebytek-api-health.php`).

El despliegue es **manual**. El clone está en `main` con upstream `origin/main`, remoto
`git@github.com-portal:Parzival2103/Lebytek_Portal.git` (alias SSH `github.com-portal` con
deploy key dedicada en `~/.ssh/config`), árbol de trabajo limpio y sincronizado.

### Scripts de despliegue obsoletos — bloqueante

`scripts/vps-deploy-lebytek-com.sh` y `scripts/vps-deploy-waapi.sh` existen **en ambos
repos** (Portal y Framework) y son de la era monorepo:

```bash
REPO=https://github.com/Parzival2103/Lebytek_Framework.git
BRANCH=feature/backoffice-api-integration
...
find "$APP_DIR" -mindepth 1 -maxdepth 1 ! -name '.env' -exec rm -rf {} +
cp -a /tmp/lebytek-deploy/. "$APP_DIR/"
```

Borran el directorio del sitio y lo repueblan clonando **la rama que este trabajo elimina**.
Consecuencias:

- Ejecutados hoy, revierten `lebytek.com` al monorepo y deshacen la separación.
- Ejecutados tras borrar la rama, el `rm -rf` corre igual y el `git clone` falla acto
  seguido: **el sitio queda vacío**.

Por eso su limpieza es prerequisito de la eliminación de la rama, no un extra.

`Lebytek_Framework@main` ya está separado: `src/` es el paquete, `skeleton/` la plantilla
genérica, `app/` sólo un `README.md` stub. Tags publicados: `v1.0.0`, `v1.1.0`,
`pre-split-backup`.

## Decisiones tomadas

| Decisión | Elección | Motivo |
|---|---|---|
| Distribución del skeleton | Paquete `lebytek/skeleton` vía subtree split | `skeleton/` sigue siendo fuente de verdad en el framework, protegido por `SkeletonPurityTest` |
| Base de datos de staging | BD dedicada `lebytek_stg` | Los CRUDs demo deben existir tal cual sin ensuciar producción |
| Credenciales de `lebytek_stg` | Las mismas que producción | Decisión del responsable del proyecto |
| Certificado | Let's Encrypt vía `clpctl` | Staging hoy es inaccesible desde el navegador |
| Repo espejo | Creado con `gh`, privado | Consistente con Framework y Portal |
| Rama a eliminar | `feature/backoffice-api-integration` | Ningún despliegue automático la consume; los scripts obsoletos que la referencian se limpian antes |

## Estado objetivo

```
Lebytek_Framework (main)      → paquete lebytek/framework; src/ + skeleton/ (fuente)
      │ git subtree split --prefix=skeleton
      ▼
Lebytek_Skeleton (main, tags) → paquete lebytek/skeleton  ──┐
                                                            │ composer create-project
Lebytek_Portal (main)         ──┐                           │
                                │ git clone                 ▼
lebytek.com          ← Portal ──┘         skeleton.lebytek.com ← skeleton
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

### 4. Despliegue de `skeleton.lebytek.com`

El directorio actual se archiva, no se borra:
`skeleton.lebytek.com` → `skeleton.lebytek.com.portal-copy-20260726`.

Como usuario `lebytek-stg`:

```bash
composer create-project lebytek/skeleton skeleton.lebytek.com \
  --repository='{"type":"vcs","url":"https://github.com/Parzival2103/Lebytek_Skeleton"}' \
  --no-dev
```

Esto resuelve `lebytek/framework v1.1.0` a `vendor/` a través del `repositories` VCS que
el propio skeleton declara.

**nginx no se toca.** El vhost ya apunta a `.../public` y el pool php-fpm 8.4 (:19001) con
usuario `lebytek-stg` ya existe.

### 5. Base de datos `lebytek_stg`

```bash
clpctl db:add --domainName=skeleton.lebytek.com --databaseName=lebytek_stg \
  --databaseUserName=lebytek --databaseUserPassword=<misma que producción>
```

Por decisión del responsable, staging reutiliza **usuario y password de producción**; lo
que cambia es la base de datos (`lebytek_stg` en vez de `lebytek`). El aislamiento es a
nivel de esquema, no de credencial: el `.env` de staging da acceso también a la BD de
producción. Consecuencia asumida — un error de configuración que apunte `DB_DATABASE` a
`lebytek` no sería rechazado por permisos.

Si `clpctl db:add` rechaza reutilizar un usuario existente, la alternativa es crear la BD y
otorgar el grant al usuario `lebytek` directamente sobre `lebytek_stg`.

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
| `APP_URL` | `https://skeleton.lebytek.com` |
| `APP_ENV` | `staging` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | aleatorio de 32 caracteres, nuevo |
| `SESSION_SECURE` | `true` |
| `DB_DATABASE` | `lebytek_stg` |
| `DB_USERNAME` / `DB_PASSWORD` | credenciales del paso 5 |

### 7. Certificado TLS

```bash
clpctl lets-encrypt:install:certificate --domainName=skeleton.lebytek.com
```

Se ejecuta **después** del despliegue, para que el challenge `.well-known` lo sirva la
aplicación nueva. El DNS ya resuelve al VPS y la app responde 200 en `:8080`, de modo que
el challenge no requiere cambios adicionales de nginx.

### 8. Limpieza de scripts de despliegue obsoletos — prerequisito de §9

En **ambos** repos (`Lebytek_Framework` y `Lebytek_Portal`):

| Script | Acción | Motivo |
|---|---|---|
| `scripts/vps-deploy-lebytek-com.sh` | eliminar | Despliega el monorepo desde la rama a borrar; revierte la separación o vacía el sitio |
| `scripts/vps-deploy-waapi.sh` | eliminar | Idéntico, sobre `waapi.lebytek.com` |
| `scripts/vps-deploy-skeleton.sh` (sólo Framework) | eliminar | Sustituido por `publish-skeleton.sh` + `create-project` |

Se eliminan en vez de reescribirse: el despliegue real hoy es `git pull` + `composer install`
sobre un clone del Portal, que no necesita script.

`Lebytek_Portal/docs/DEPLOY-VPS.md` requiere tres correcciones, no sólo añadir el
procedimiento:

| Sección | Estado actual | Corrección |
|---|---|---|
| "Composer switch (staging first)" | Documenta el pin a `dev-consolidation/framework-portal-separation` | Reemplazar por `^1.1` (Portal) y remitir a este spec para staging |
| "Deploy sequence (human executes on VPS)" | Describe el clone inicial del cutover | Reemplazar por el ciclo real: `git pull` + `composer install --no-dev` + smoke |
| "Forbidden without explicit user order" | Prohíbe mergear `feature/backoffice-api-integration` a `main` | Eliminar la línea: la rama deja de existir |

Los scripts `vps-finalize-lebytek.sh`, `vps-fix-lebytek-db.sh`, `vps-setup-lebytek-db.sh`,
`vps-fix-lebytek-ssl.sh` y `vps-restore-lebytek-nginx-ssl.sh` no referencian la rama y
quedan fuera de alcance.

### 9. Eliminación de la rama

Sólo después de §8, en los dos repos y sin dejar copias locales:

```bash
git push origin --delete feature/backoffice-api-integration
git branch -D feature/backoffice-api-integration
```

## Orden de ejecución

Dos dependencias de orden, ambas obligatorias:

- `skeleton.lebytek.com` consume hoy `dev-consolidation/framework-portal-separation`, y sólo
  deja de hacerlo tras el paso 6.
- Los scripts obsoletos (§8) deben desaparecer **antes** de borrar la rama (§9), o el
  siguiente que los ejecute vacía `lebytek.com`.

1. Eliminar scripts obsoletos en ambos repos y actualizar `DEPLOY-VPS.md` (componente 8)
2. `skeleton/composer.json` → VCS + `^1.1` (componente 1)
3. `scripts/publish-skeleton.sh` (componente 2)
4. Crear repo espejo, publicar subtree, taggear `v1.1.0` (componentes 2 y 3)
5. Crear BD `lebytek_stg` (componente 5)
6. Archivar staging actual y `composer create-project` (componente 4)
7. Generar `.env` (componente 6)
8. Ejecutar `install.php` y `seed.php` (componente 5)
9. Emitir certificado Let's Encrypt (componente 7)
10. Verificar (ver abajo)
11. Eliminar `feature/backoffice-api-integration` (componente 9)

El paso 1 va primero de forma deliberada: neutraliza el riesgo destructivo antes de que
cualquier otro paso toque el VPS.

## Manejo de errores y rollback

Cada paso del VPS es reversible por separado:

| Falla en | Rollback |
|---|---|
| `create-project` (pasos 5–7) | `mv skeleton.lebytek.com.portal-copy-20260726` de vuelta |
| Let's Encrypt (paso 8) | El cert autofirmado sigue en su sitio; staging queda como estaba |
| Publicación del espejo (paso 3) | Borrar el repo espejo; el framework no cambia de estado |

`lebytek.com` y `waapi.lebytek.com` no se modifican en ningún paso. El directorio
archivado se conserva hasta que la verificación pase.

## Verificación

| Comprobación | Resultado esperado |
|---|---|
| `curl -I https://skeleton.lebytek.com/login` | 200 con certificado válido (issuer Let's Encrypt, no CN autofirmado) |
| `composer show lebytek/framework` en staging | `v1.1.0` |
| `ls skeleton.lebytek.com/routes` | sin `marketing.php`, `marketing_admin.php`, `waapi_portal.php` |
| CRUDs demo en staging | navegables sin error |
| `curl -o /dev/null -w '%{http_code}' https://lebytek.com/` | 200 |
| `curl -o /dev/null -w '%{http_code}' https://waapi.lebytek.com/` | 200 |
| `php tests/run.php` en el framework | verde, incluido `SkeletonPurityTest` |
| `grep -rn "feature/backoffice-api-integration"` en ambos repos | sin resultados |
| `ls scripts/vps-deploy-*.sh` en ambos repos | no existen |
| `gh api .../branches` en ambos repos | sin `feature/backoffice-api-integration` |
| `git -C /home/lebytek/htdocs/lebytek.com status` | limpio, en `main`, sincronizado |

## Fuera de alcance

- **`waapi.lebytek.com` está 2 commits atrás de `lebytek.com`** (`6cdb957` vs `2718212`).
  Dominio aún no en uso — portal de clientes para consulta de uso de su instancia API.
  Confirmado fuera de alcance. Su script de despliegue obsoleto **sí** se elimina en §8,
  porque referencia la rama a borrar; el dominio en sí no se toca.
- **`consolidation/framework-portal-separation`** queda sin consumidores tras el paso 5,
  pero no se elimina en este trabajo.
- **Ramas `cursor/*` y `automation/audit-spec-*`** (~15 acumuladas): fuera de alcance.
- **Directorios de backup del corte del 21-jul**
  (`lebytek.com.monorepo-backup-*`, `waapi.lebytek.com.monorepo-backup-*`,
  `waapi.lebytek.com.prev-*`): fuera de alcance.
- **Auth básica sobre staging**: descartada, ya no aplica al aislar la BD.
