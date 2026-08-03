# Design: consumidor CRM en `crm.lebytek.com`

Fecha: 2026-08-03  
Estado: diseño aprobado; plan en `docs/superpowers/plans/2026-08-03-crm-lebytek-com-consumer.md`  
Producto: tenant CRM Lebytek (nuevo), no Portal ni lab skeleton

## Problema

Tras FPS, el Framework se consume por Composer y el `skeleton/` es la semilla de
nuevos tenants. Portal (`Lebytek_Portal`) es la instancia de negocio en
`lebytek.com` / `waapi.lebytek.com`. Falta un segundo consumidor de producto:
un CRM en subdomain propio, repo propio, BD propia, listo para desarrollar
sobre el skeleton sin mezclar Marketing de Portal ni el lab
`skeleton.lebytek.com` (aún pendiente).

## Decisiones

| Decisión | Elección |
|----------|----------|
| Enfoque | Portal twin: copiar `skeleton/`, pin semver de Framework vía Composer |
| Hostname | `crm.lebytek.com` |
| Repo GitHub | Privado `Parzival2103/Lebytek_CRM` |
| Ruta local | `...\sistemas\Lebytek_CRM` |
| Composer name | `lebytek/crm` |
| Framework | `lebytek/framework: ^1.2.3` + VCS `Lebytek_Framework`; `composer.lock` committed |
| Rama canónica | `main` |
| CloudPanel user | `lebytek-crm` |
| App path VPS | `/home/lebytek-crm/htdocs/crm.lebytek.com/` |
| Document root | `public/` |
| PHP | 8.1 |
| TLS | Let's Encrypt (`clpctl`) |
| Base de datos | Dedicada `lebytek_crm` (nunca BD Portal/prod) |
| Alcance go-live | Repo + Composer + sitio + TLS + install/migrate + login admin |
| Fuera de alcance | Features CRM, cambios Portal, cambios `src/` Framework, deploy `skeleton.lebytek.com` |

## Arquitectura

```text
Lebytek_Framework (package source)
  skeleton/  ──copy──►  Lebytek_CRM (app consumidora)
                              │
                              ├── app/ config/ routes/ database/ public/ tests/
                              └── vendor/lebytek/framework  (Composer, read-only)

VPS CloudPanel
  crm.lebytek.com  →  lebytek-crm  →  htdocs/crm.lebytek.com/public
  MySQL lebytek_crm (credenciales solo en .env del VPS)
```

Misma disciplina que Portal:

- Plataforma solo en `vendor/lebytek/framework`.
- Negocio CRM futuro solo en `app/`, SQL `dom_*`, config/rutas del repo CRM.
- Bugs de plataforma → spec/plan en `Lebytek_Framework`, nunca parche en `vendor/`.
- No path-autoload del Framework en VPS; solo tag/lock semver.

Distinción de entornos (actualizar `docs/ENVIRONMENTS.md` en implementación):

| Host | Rol |
|------|-----|
| `skeleton.lebytek.com` | Lab de plataforma (pendiente) |
| `crm.lebytek.com` | Producto CRM (este diseño) |
| `lebytek.com` / `waapi` | Producto Portal |
| `api.lebytek.com` | WhatsApiLebytek |

## Componentes

### 1. Repositorio `Lebytek_CRM`

- Semilla: árbol de `Lebytek_Framework/skeleton/` (sin código Portal).
- `composer.json`: `lebytek/crm`, require `lebytek/framework: ^1.2.3`, repositorio VCS Framework, autoload `App\` → `app/`, stability stable.
- Sustituir path/`*@dev` del skeleton harness.
- Adaptar `CLAUDE.md` / `.cursor/rules` / `AGENTS.md` mínimo para CRM (sin Marketing/api Portal).
- Commit `composer.lock` tras `composer update` local.
- Repo privado; deploy key dedicada en VPS (patrón Portal).

### 2. Identidad de app

- `.env.example`: `APP_URL=https://crm.lebytek.com`, sin `MKT_*` / `LEBYTEK_API_*`.
- `config/app.php` version alineada al pin de plataforma documentado (skeleton ya en `1.2.3`).
- `vertical.php` y demos skeleton por defecto; Payments OFF salvo decisión futura.

### 3. VPS

- Crear sitio CloudPanel `crm.lebytek.com`, usuario `lebytek-crm`, PHP 8.1, document root `public`.
- DNS A/AAAA `crm` → mismo VPS que `lebytek.com`.
- Certificado Let's Encrypt.
- MySQL database name `lebytek_crm`; DB user created by CloudPanel with grants
  only on that database (credentials only in VPS `.env`).
- Clone `main`, `composer install --no-dev`, `.env` en servidor (nunca en git).
- `php scripts/install.php` (y/o instalador web) + `php scripts/migrate.php`.
- Admin inicial vía instalador; secretos fuera del repo.

### 4. Documentación

- En CRM: runbook corto `docs/DEPLOY-VPS.md` (pull → composer install → migrate → smoke).
- En Framework: entrada en `docs/ENVIRONMENTS.md` para `crm.lebytek.com` como consumidor de producto.

## Flujo de entrega (esta pasada)

1. Crear repo GitHub privado + árbol local desde skeleton + Composer pin/lock + push `main`.
2. Provisionar CloudPanel + DNS + TLS + BD.
3. Deploy key + clone + install Composer + `.env` + install/migrate plataforma.
4. Smoke HTTPS + login.
5. Actualizar `ENVIRONMENTS.md`; dejar CRM listo para features en sesión siguiente.

## Criterios de aceptación

1. Existe `Parzival2103/Lebytek_CRM` (privado) con `main` basado en skeleton y `composer.lock` con `lebytek/framework` ^1.2.3.
2. Clone local en `...\sistemas\Lebytek_CRM` editable.
3. `https://crm.lebytek.com` responde con TLS válido.
4. Login admin funciona contra BD `lebytek_crm` únicamente.
5. Sin código Marketing/Portal en el árbol CRM.
6. Runbook de deploy en el repo CRM.
7. `docs/ENVIRONMENTS.md` del Framework lista el host CRM.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Confundir con lab skeleton | Documentar rol distinto en ENVIRONMENTS; repo/host/BD propios |
| Reusar BD prod | Crear solo `lebytek_crm`; verificar `.env` antes de migrate |
| Path repo Composer en VPS | Solo VCS + lock; prohibido path en producción |
| Deploy key / repo privado | Alias SSH dedicado como Portal; no embeber tokens en git |
| DNS aún no apunta | Provisionar sitio; smoke externo solo tras DNS/TLS |

## No hacer

- Merge `feature/backoffice-api-integration` → `main` en Framework.
- Editar `vendor/` en CRM o Portal.
- Clonar Framework monorepo como document root del CRM.
- Apuntar CRM a BD `lebytek` / Portal.
- Implementar dominio CRM de negocio en esta pasada.
