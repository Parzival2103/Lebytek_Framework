# Package root layout

Este repositorio es la **fuente del paquete** `lebytek/framework`, no el sitio lebytek.com.

| Path | Uso |
|------|-----|
| `src/` | Código del paquete |
| `skeleton/` | Plantilla mínima para nuevos tenants |
| `database/`, `scripts/` | SQL/scripts de plataforma shippeados en el paquete |
| `docs/core/`, `docs/modules/`, contratos raíz | Guías de uso que **sí** viajan en el dist Composer |
| `config/`, `public/`, stub `app/` | **Harness de tests / smoke local del mantenedor** |
| Portal | Repo hermano `Lebytek_Portal` |

**Política:** este árbol **no se despliega** en VPS. Deploy = Portal (o tenant desde skeleton) + `composer install`.

## Qué NO entra en `vendor/lebytek/framework`

`.gitattributes` marca `export-ignore` (Composer dist / `git archive`) para ruido
interno del mantenedor. Sigue en GitHub en el clone del repo fuente:

| Excluido del dist | Motivo |
|-------------------|--------|
| `docs/superpowers/`, `docs/audits/`, `docs/automation/`, `docs/automation-reports/` | Cadena agents / audits |
| `docs/archive/` | Historial cerrado |
| `docs/integration/` | Ops VPS / runbooks internos |
| `tests/`, `.cursor/`, `.agents/`, `AGENTS.md`, `CLAUDE.md` | Harness y tooling |

Tras un tag nuevo, `composer update lebytek/framework` en el consumidor deja de
traer esas carpetas. Un `prefer-source` (clone completo) sí las verá — usar dist.

Deuda aceptada A7: el harness permanece hasta CI contra `skeleton/` instalado.

## Release policy (REL-C1)

The semver in `composer.json` / `config/app.php` / `skeleton/config/app.php` is the **declared**
platform version. Consumers installing via Composer tags must use a **published** Git tag
`vX.Y.Z` matching that declaration. `PlatformVersionSemverTest` validates internal sync only;
`ReleaseTagPublishedTest` validates the tag exists before release. Do not merge semver bumps
without scheduling tag publication in the same release train.
