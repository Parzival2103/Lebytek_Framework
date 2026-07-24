# Auditoría técnica diaria — 2026-07-24

**Repositorio:** `Lebytek_Framework` (package source `lebytek/framework`)  
**Rama auditada:** `main` @ `607a3c6` (merge PR #26 — FPS consolidation)  
**Rama de referencia VPS:** `feature/backoffice-api-integration` @ `4789f95`  
**Automatización:** cron `1cfa9bdd-809a-11f1-ba66-0e7d0216e441`  
**Verificación runtime:** no ejecutada — entorno sin `php`, `composer` ni `vendor/`

---

## Resumen ejecutivo

Desde la auditoría del 2026-07-21 no hubo commits nuevos en `main` ni en la rama VPS. El hito relevante sigue siendo el **merge de PR #26** (separación Framework ↔ Portal): `main` ya es código de paquete sin Marketing ni landing pública.

El riesgo principal ya no es deuda interna de `main`, sino **desalineación operativa**: el script de deploy VPS (`scripts/vps-deploy-lebytek-com.sh`) sigue clonando `feature/backoffice-api-integration` (~239 archivos de delta, 46↔53 commits divergentes). Producción en lebytek.com **no refleja** el modelo FPS ni los gates documentados en `docs/CUTOVER-PORTAL.md`.

En la rama VPS persisten hallazgos abiertos (#21 Stripe, bootstrap leads incompleto en `marketing.sql`) que afectan estabilidad multitenant si se despliega greenfield sin migraciones manuales.

**Recomendación final:** **requiere revisión humana** (decisión de cutover Portal + actualización deploy VPS). Issues existentes #21 y #23 deben **re-etiquetarse/actualizarse** al nuevo contexto FPS; no abrir PR de código de negocio en este repo.

---

## Hallazgos críticos

### C1 — VPS desplegado desde rama monolito obsoleta

| Campo | Detalle |
|-------|---------|
| **Evidencia** | `scripts/vps-deploy-lebytek-com.sh` línea 6: `BRANCH=feature/backoffice-api-integration`; `main` @ `607a3c6` vs feature @ `4789f95` |
| **Impacto** | lebytek.com no consume el paquete FPS; mezcla código Portal eliminado de `main`; imposible alinear con `docs/PACKAGE-ROOT.md` y `docs/CUTOVER-PORTAL.md` |
| **Acción** | Cutover humano: Portal repo + Composer pin, o congelar SHA explícito hasta cutover. **No auto-merge** feature → main |

### C2 — Bootstrap `marketing.sql` (rama VPS) sin columnas API lifecycle / churn

| Campo | Detalle |
|-------|---------|
| **Evidencia** | En `origin/feature/backoffice-api-integration`, `database/schema/modules/marketing.sql` no define `api_instance_public_id`, `api_lifecycle_status` ni columnas churn; migraciones sí listadas en `config/modules/marketing.php` (`20260701160000`, `20260701170000`, `20260706120000_*`) |
| **Impacto** | Instalación greenfield o `install.php` sin `migrate.php` completo → errores SQL en repos PHP (`PdoLeadRepository`, reportes churn) |
| **Acción** | Issue en repo **Portal** o parche en feature hasta cutover; issue #23 en Framework debe acotarse a “VPS/Portal”, no `main` |

### C3 — Suscripciones Stripe: activación / recover / amount bypass (issue #21 abierto)

| Campo | Detalle |
|-------|---------|
| **Evidencia** | GitHub issue #21 OPEN (2026-07-20) |
| **Impacto** | Con `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` en VPS, riesgo de activación incorrecta o bypass de monto |
| **Acción** | Mantener `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` en VPS hasta cierre #21; no habilitar en producción |

---

## Hallazgos medios

### M1 — CRUD `mkt_ordenes`: campo `status` editable manualmente (rama VPS)

En `config/cruds/mkt_ordenes.json` (feature), el formulario expone `status` como `select` con opción `paid`, permitiendo bypass del flujo `Autorizar pago` / `Activar plan`. Las transiciones JSON restringen algunos estados, pero la edición directa del campo sigue siendo vector de abuso operativo.

### M2 — `INSTALL_TOKEN` ausente en `.env.example`

`public/install/index.php` y `docs/core/despliegue-y-versionado.md` exigen `INSTALL_TOKEN` en producción, pero ningún `.env.example` (root ni `skeleton/`) documenta la variable. Riesgo de instalador expuesto en VPS si ops no conoce el requisito.

**Fix aplicado en este PR:** documentar `INSTALL_TOKEN` en ambos `.env.example`.

### M3 — Documentación de deploy desactualizada vs realidad VPS

- `docs/core/seguridad_secretos_deploy.md` afirma “VPS hace auto-pull de `main`”; el script real usa `feature/backoffice-api-integration`.
- `docs/integration/VPS_CHECKLIST.md` referencia commits de julio 2026-07-01; cron health pendiente de confirmación operador.
- `.env.example` del package aún lista `MKT_*`, `LEBYTEK_API_*` tras FPS — confunde consumidores skeleton (skeleton `.env.example` sí está limpio).

### M4 — Tests no verificados en esta corrida

Sin PHP/Composer en el agente cloud: **no se pudo ejecutar** `php tests/run.php`, `composer validate`, ni suite Portal. Último gate documentado verde en FPS Plan 06/07 (2026-07-21 en `.superpowers/sdd/progress.md`).

### M5 — Rutas CRUD sin `RbacMiddleware` explícito en `routes/web.php`

Las rutas `/admin/crud/*` dependen de `AuthMiddleware` a nivel grupo y de `CrudResourceService` → `RbacService::verificar()` por recurso. Patrón válido pero **no listado** en `config/rbac_route_permissions.php` — deuda de trazabilidad RBAC (heredada).

### M6 — PR draft #25 y #16 sin consolidar

PR #25 (auditoría 2026-07-21, DRAFT) y #16 (docs FPS plans, OPEN) siguen abiertos; riesgo de confusión sobre qué cambios están en `main`.

---

## Mejoras rápidas (bajo riesgo)

| # | Mejora | Estado |
|---|--------|--------|
| Q1 | Añadir `INSTALL_TOKEN=` a `.env.example` (root + skeleton) | **Incluido en PR de esta auditoría** |
| Q2 | Actualizar issue #23: scope “main bootstrap” → “Portal/VPS feature branch” | Pendiente humano |
| Q3 | Alinear `seguridad_secretos_deploy.md` con rama real de deploy o marcar script legacy | Pendiente humano |
| Q4 | Limpiar `MKT_*` / `LEBYTEK_API_*` del `.env.example` del package harness | Pendiente PR pequeño |
| Q5 | Cerrar/archivar PR draft #25 tras revisar diff vs `main` actual | Pendiente humano |

---

## Riesgos de deploy en VPS

1. **Rama incorrecta:** deploy script no usa `main` ni Portal Composer — riesgo alto de drift y código duplicado.
2. **Migraciones marketing:** script aplica SQL 202606/202607 con fallback manual y `|| true` — fallos silenciosos posibles.
3. **Marketing forzado ON:** `sed` en deploy activa `marketing => true` en `vertical.php` — coherente con monolito, incoherente post-FPS.
4. **Payments/subscriptions:** no habilitar checkout recurrente hasta #21; `STRIPE_ENABLED=false` por defecto en package es correcto.
5. **Cutover FPS:** `docs/CUTOVER-PORTAL.md` marca VPS cutover como **deferred** — deploy automático contradice la política.
6. **Sin verificación hoy:** no se confirmó estado real del VPS (SSH/cron/backups fuera de alcance).

---

## Cambios recientes en Git (ventana 7 días)

| Fecha | Commit | Ámbito |
|-------|--------|--------|
| 2026-07-21 | `607a3c6` | Merge PR #26 — FPS: elimina Marketing/Portal de package, añade Payments genérico OFF, PackagePaths, skeleton purity, docs cutover |
| 2026-07-21 | `67b5911`–`84025ad` | Docs FPS, reglas Cursor, ARCHITECTURE-CONSUMER, SCHEMA-OWNERSHIP |
| 2026-07-18 | `4789f95` | Último commit feature VPS (branding logo) |

**Sin commits en las últimas 24 h** en `main` ni `feature/backoffice-api-integration`.

---

## Módulos afectados (FPS merge)

| Módulo | Estado en `main` |
|--------|------------------|
| Marketing / Publico / LebytekApi | **Eliminado** del package → Portal |
| Payments (genérico) | Presente, **OFF** (`config/vertical.php`, `config/payments.php`) |
| Integrations | Plataforma, toggle ON en harness |
| CRUD Engine, RBAC, Reportes, PDF, Calendario | Estables |
| Install / PackagePaths | Mejorado (resolución SQL package-first) |

---

## Migraciones

**En `main` (plataforma):** 3 migraciones en `database/migrations/` (+ espejo skeleton): auth registro, crud demo permisos, pdf-kit demo menú. Sin migraciones `202606*`/`202607*` marketing (correcto post-FPS).

**En feature VPS:** 15+ migraciones marketing listadas en manifiesto; bootstrap desalineado (ver C2).

---

## Rutas, middleware y permisos

- Auth + CSRF en mutaciones; RBAC granular en dashboard, administración, reportes, PDF, integraciones.
- CRUD: permisos vía `permission_prefix` en JSON + `CrudResourceService` (no middleware de ruta).
- Login rate limit: `LOGIN_RATE_LIMIT_ENABLED=true` en `.env.example`.
- Registro público: gated por `REGISTRO_HABILITADO=false` por defecto.

---

## Seguridad

| Tema | Estado |
|------|--------|
| Secretos en repo | `.env` no versionado; `.env.example` sin secretos reales |
| CSRF | Middleware en POST/PUT/DELETE admin |
| Install wizard | Requiere token en production — **documentación incompleta** (M2) |
| Session | `SESSION_SECURE=false` en example (ok dev; prod debe ser true) |
| CRUD ownership | `CrudScopeResolver` + tests en `tests/Security/` |
| Stripe webhooks | Solo en rama feature; package tiene gateway genérico con tests unitarios |

---

## Tests faltantes / no ejecutados

- Suite completa `php tests/run.php` — **no corrida** (sin runtime PHP).
- Marketing suite — movida a Portal (no aplica en `main`).
- Gates FPS: `SkeletonPurity`, `FrameworkRootNotPortal`, `PackageAutoloadBoundary`, `PlatformSqlResolve` — existen; requieren CI local.

---

## Archivos involucrados

```
scripts/vps-deploy-lebytek-com.sh
config/vertical.php
routes/web.php
.env.example
skeleton/.env.example
docs/CUTOVER-PORTAL.md
docs/PACKAGE-ROOT.md
docs/core/seguridad_secretos_deploy.md
public/install/index.php
database/schema/modules/* (main — sin marketing)
origin/feature/.../database/schema/modules/marketing.sql
origin/feature/.../config/cruds/mkt_ordenes.json
origin/feature/.../config/modules/marketing.php
tests/Kernel/SkeletonPurityTest.php
tests/Kernel/FrameworkRootNotPortalTest.php
```

---

## Recomendación final

| Acción | Prioridad |
|--------|-----------|
| **requiere revisión humana** | Decisión cutover Portal vs mantener monolito VPS |
| **crear issue** (o actualizar #23) | Bootstrap marketing + alineación deploy script post-FPS |
| **sin acción automática en código de negocio** | Issues #21, C1, C3 |
| **crear PR** | Solo documentación auditoría + `INSTALL_TOKEN` en `.env.example` (este PR) |
