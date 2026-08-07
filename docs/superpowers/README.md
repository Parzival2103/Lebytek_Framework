# Superpowers — Framework (activo)

Documentación **vigente** para agents. Artefactos cerrados:
[`../archive/superpowers/`](../archive/superpowers/).

Esta carpeta **no** se incluye en el dist Composer (`export-ignore`); solo vive
en el clone del repo fuente.

## Autoridad

| Ámbito | Fuente |
|--------|--------|
| Paquete `lebytek/framework` | `main`, tags semver |
| App desplegable Lebytek | `Lebytek_Portal/main` |
| Rama legacy (solo lectura) | `feature/backoffice-api-integration` — no merge a `main` sin orden explícita |

## Planes / specs en curso

| Archivo | Objetivo |
|---------|----------|
| [`plans/2026-07-26-skeleton-package-staging.md`](plans/2026-07-26-skeleton-package-staging.md) | Publicar skeleton + **skeleton.lebytek.com** |
| [`specs/2026-07-26-skeleton-package-staging-design.md`](specs/2026-07-26-skeleton-package-staging-design.md) | Diseño del entorno skeleton |
| [`specs/2026-08-04-audit-platform-ci-gates-design.md`](specs/2026-08-04-audit-platform-ci-gates-design.md) · [plan](plans/2026-08-04-audit-platform-ci-gates.md) | CI gates plataforma |
| [`specs/2026-08-05-audit-api-health-public-design.md`](specs/2026-08-05-audit-api-health-public-design.md) · [plan](plans/2026-08-05-audit-api-health-public.md) | API health público |
| [`specs/2026-08-06-audit-crud-rbac-router-design.md`](specs/2026-08-06-audit-crud-rbac-router-design.md) · [plan](plans/2026-08-06-audit-crud-rbac-router.md) | CRUD RBAC router |
| [`specs/2026-08-03-audit-mkt-leads-after-list-rows-design.md`](specs/2026-08-03-audit-mkt-leads-after-list-rows-design.md) · [plan](plans/2026-08-02-audit-mkt-leads-after-list-rows.md) | Portal `afterListRows` (repo Portal) |

## Referencia FPS (mantener)

| Archivo | Uso |
|---------|-----|
| [`BOUNDARY-framework-vs-portal-fps.md`](BOUNDARY-framework-vs-portal-fps.md) | Frontera plataforma vs negocio |
| [`FPS-git-baseline.md`](FPS-git-baseline.md) | Baseline Git congelado |
| [`FPS-publication-manifest-checklist.md`](FPS-publication-manifest-checklist.md) | Checklist publicación (test) |
| [`FPS-legacy-archival-decision.md`](FPS-legacy-archival-decision.md) | Decisión legacy seeds/migrations |
| [`LEGACY-seeds-migrations-inventory.md`](LEGACY-seeds-migrations-inventory.md) | Inventario legacy |
| [`FPS-skeleton-platform-assets.md`](FPS-skeleton-platform-assets.md) | Assets plataforma en skeleton |
| [`FPS-remote-repo-proposal.md`](FPS-remote-repo-proposal.md) | Propuesta repos espejo |
| [`PENDIENTE-promocion-modulos-providers.md`](PENDIENTE-promocion-modulos-providers.md) | Backlog módulos |

Inventarios one-shot (delta paths, listas seeds/migrations):
[`../archive/superpowers/fps-artifacts/`](../archive/superpowers/fps-artifacts/).

Automatizaciones: [`../automation/`](../automation/).  
Entornos: [`../ENVIRONMENTS.md`](../ENVIRONMENTS.md).
