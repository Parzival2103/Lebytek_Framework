# Auditoría técnica diaria — 2026-07-26

**Repo:** `Parzival2103/Lebytek_Framework` (package source post-FPS)  
**Rama auditada:** `main` @ `607a3c6` (merge PR #26, 2026-07-21)  
**Rama de trabajo auditoría:** `cursor/auditor-a-t-cnica-9b26`  
**VPS producción (referencia):** `feature/backoffice-api-integration` @ `4789f95`  
**Trigger:** cron automation `1cfa9bdd-809a-11f1-ba66-0e7d0216e441`  
**Verificación runtime:** PHP/Composer **no disponibles** en el entorno cloud (sin `sudo` para instalar). Tests no ejecutados.

---

## Resumen ejecutivo

**Sin cambios de código en `main` desde la auditoría del 2026-07-25** (0 commits en las últimas 24 h). El estado del ecosistema sigue siendo **estable en el package source** pero **crítico en el corte VPS/Portal**: producción despliega una rama feature obsoleta del monolito mientras `main` ya eliminó todo el negocio Marketing/Portal tras la separación FPS.

| Área | Estado |
|------|--------|
| Package source (`main`) | ✅ FPS consolidado; marketing/payments OFF |
| Cambios recientes | ℹ️ Ninguno (última semana: solo docs + carve-out FPS) |
| Migraciones plataforma | ✅ 3 activas; sin nuevas esta semana |
| Tests harness | ⚠️ No verificados (sin PHP) |
| VPS lebytek.com / waapi | 🔴 Branch hardcodeada ≠ arquitectura objetivo |
| Issues abiertos | #21 (Stripe), #23 (bootstrap/migraciones — re-scope Portal) |

**Recomendación final:** **requiere revisión humana** — cutover Portal pendiente; no merge feature→main; mantener checkout Stripe/subscription OFF en VPS hasta cerrar #21.

---

## Hallazgos críticos

### C1 — Divergencia VPS vs arquitectura FPS (persistente)

| Item | Detalle |
|------|---------|
| **Scripts** | `scripts/vps-deploy-lebytek-com.sh:6`, `scripts/vps-deploy-waapi.sh:6` → `BRANCH=feature/backoffice-api-integration` |
| **Main** | Sin `marketing.sql`, sin `app/Domain/Marketing`, sin migraciones `mkt_*` |
| **Divergencia** | 46 commits solo en `main` / 53 solo en feature (~239 archivos) |
| **Riesgo** | Deploy accidental de `main` en lebytek.com **rompe el sitio**; el monolito feature queda congelado en SHA distinto al package publicado |

**Acción:** Ejecutar cutover documentado en `docs/CUTOVER-PORTAL.md` hacia `Lebytek_Portal` + Composer; no auto-fix.

### C2 — Bootstrap marketing.sql incompleto (rama feature / VPS)

En `origin/feature/backoffice-api-integration`, `database/schema/modules/marketing.sql` **no incluye** columnas añadidas por migraciones incrementales:

- `api_instance_public_id` (`20260701160000_mkt_leads_api_instance_public_id.sql`)
- `api_lifecycle_status` (`20260701170000_mkt_leads_api_lifecycle_status.sql`)
- Columnas churn (`20260706120000_mkt_leads_churn_columns.sql`)
- Stripe en órdenes / `dom_mkt_membresias` (`20260715*`)

Fresh install vía bootstrap + `install.php` → schema incompatible con PHP de feature.

**Issue relacionado:** #23 (contexto pre-FPS; en `main` ya no aplica bootstrap — re-scope a Portal/VPS).

### C3 — CRUD bypass `mkt_ordenes.status = paid` (feature)

`config/cruds/mkt_ordenes.json` (solo feature) expone `status` como select editable incluyendo `"paid"`. La máquina de estados **no aplica** a guardado de formulario; solo a acciones `type: transition`. Un admin puede marcar pagada → **Activar plan** sin Stripe ni Autorizar pago.

**Acción:** Issue en Portal/feature; no auto-fix en package source.

### C4 — Stripe subscription activation gaps (feature)

Issue **#21** abierto: first-activation no-op, metadata invoice.paid, recover crea nuevo checkout, post-claim swallow, desync cancelled.

**Política ops:** Mantener `STRIPE_ENABLED=false` / checkout subscription OFF hasta cierre.

---

## Hallazgos medios

### M1 — Path traversal en loaders de config CRUD/Calendar (`main`)

`src/Application/Services/CrudConfigLoader.php:33` concatena `{resource}` sin allowlist → usuario autenticado puede probar paths bajo `config/`. Mismo patrón en `CalendarConfigLoader`.

**Recomendación:** Allowlist `^[a-z0-9_]+$` antes de `file_get_contents`. Fix en `src/` → spec/PR framework.

### M2 — AuthMiddleware no revalida usuario activo

Sesión válida persiste aunque `auth_usuarios.activo = 0` hasta expiración. Verificación solo en login.

### M3 — Instalador web sin token fuera de production

`public/install/index.php:53-64` — `INSTALL_TOKEN` solo si `APP_ENV=production`. Staging/local sin lock = wizard abierto.

### M4 — `.env.example` harness con drift Portal post-FPS

Root `.env.example` conserva `MKT_*`, `LEBYTEK_API_*` (L53-100); `skeleton/.env.example` está limpio (gate `SkeletonPurityTest`). Confusión para operadores del harness.

### M5 — `vps-deploy-lebytek-com.sh` traga errores de migración

L56-61, L64-70: `|| true` / `migration skipped` → schema parcial silencioso.

### M6 — `vps-deploy-waapi.sh` sin paso de migraciones

Solo clone + composer + nginx; cero SQL post-deploy.

### M7 — RBAC: permisos CRUD usa `administracion.ver` en lugar de slug dedicado

`routes/web.php` — gestión de permisos accesible con permiso amplio de administración.

### M8 — Documentación VPS desactualizada

`docs/integration/VPS_CHECKLIST.md` — muchos ítems sin marcar desde 2026-07-01; cron health pendiente; branch feature documentada como target permanente sin fecha de cutover.

---

## Mejoras rápidas (bajo riesgo)

| # | Mejora | Estado |
|---|--------|--------|
| 1 | Documentar `INSTALL_TOKEN` en `.env.example` + `skeleton/.env.example` | ✅ En PR de esta auditoría |
| 2 | Comentario en deploy scripts: "NO cambiar a main hasta cutover Portal" | Pendiente issue |
| 3 | Purga vars `MKT_*` del harness `.env.example` | Pendiente PR docs |
| 4 | Test unitario path traversal en `CrudConfigLoader` | Pendiente spec |

---

## Riesgos de deploy (VPS)

| Escenario | Severidad | Mitigación |
|-----------|-----------|------------|
| Deploy `main` en lebytek.com | 🔴 Crítico | Scripts siguen en feature; validar SHA antes de pull |
| Fresh install feature sin migraciones Jul | 🔴 Crítico | Post-deploy: `\d dom_mkt_leads`, verificar columnas lifecycle/churn |
| Migración fallida silenciada (lebytek script) | 🟠 Alto | Revisar logs; quitar `\|\| true` en fix dedicado |
| waapi deploy sin migrate | 🟠 Alto | Añadir runner SQL en script (issue) |
| APP_KEY placeholder en .env VPS | 🟠 Alto | Verificar no sea literal del example |
| Cron membresía/churn no programado (feature) | 🟡 Medio | Scripts existen; no en deploy |
| DNS lebytek.com → VPS antes E2E | 🟡 Medio | Checklist § DNS aún abierto |

---

## Cambios recientes en Git (ventana 7 días)

**`main`:** Último commit `607a3c6` (2026-07-21) — merge FPS PR #26. Actividad previa: docs Plan 08, reglas package source, eliminación Portal de `src/` y `database/schema/modules/marketing.sql`.

**Últimas 24 h:** 0 commits.

**Módulos afectados (semana):** Documentación FPS, installer orphans, skeleton purity — **ningún módulo runtime nuevo**.

---

## Migraciones

### Plataforma (`main`)

Activas en `database/migrations/`:

- `20260609120000_crud_demo_permisos_modulo_por_recurso.sql`
- `20260612120000_auth_registro_recuperacion.sql`
- `20260614120000_pdf_kit_demo_menu.sql`

Legacy archivado en `database/migrations_legacy/`. **Sin migraciones nuevas esta semana.**

### Feature / VPS (referencia)

11+ migraciones `mkt_*` / churn / Stripe — **solo en feature**, no en `main`.

---

## Rutas, middleware, permisos (package `main`)

| Componente | Evaluación |
|------------|------------|
| Auth + CSRF + RBAC | ✅ Mayoría rutas admin protegidas |
| CRUD `/admin/crud/*` | Auth en ruta; RBAC en servicio (defense-in-depth gap) |
| `/wa/activar/{token}` | Público por diseño (token firmado) |
| `/api/ping` | Requiere sesión autenticada |
| Payments webhooks | No expuestos en package source |
| `config/vertical.php` | `marketing: false`, `payments: false` ✅ |

---

## Validaciones formularios/API

- Login: rate limit + `LoginValidator` ✅
- CRUD: validadores por config; gap en enforcement de transiciones en update directo (patrón que afecta feature en `mkt_ordenes`)
- Uploads: `UploadValidator` + finfo ✅; SVG permitido si config CRUD lo habilita (XSS potencial)
- Integraciones save: validación mínima de credenciales Green API

---

## Tests faltantes / no verificados

| Suite | Notas |
|-------|-------|
| `php tests/run.php` | **No ejecutado** — sin PHP en cloud agent |
| Marketing / Stripe / Portal | Eliminados de `main`; viven en feature/Portal |
| Path traversal loaders | Sin test dedicado |
| Session revalidation | Sin test |
| FPS gates | Documentados en `docs/CUTOVER-PORTAL.md` — requieren PHP local/CI |

**Recomendación CI:** Asegurar workflow con PHP 8.x ejecutando `php tests/run.php` en cada push a `main`.

---

## Documentación desactualizada

| Documento | Problema |
|-----------|----------|
| `docs/integration/VPS_CHECKLIST.md` | Ítems pendientes sin fecha; branch feature como permanente |
| `docs/composer-setup.md` | Referencia `dev-feature/backoffice-api-integration` |
| Root `.env.example` | Vars Portal post-FPS |
| Issue #23 | Contexto `main @ 2c71d3f` — **re-scope a Portal/VPS** |

Actualizados y alineados: `docs/CUTOVER-PORTAL.md`, reglas `.cursor/rules/*`, `CLAUDE.md`.

---

## Archivos involucrados

```
scripts/vps-deploy-lebytek-com.sh
scripts/vps-deploy-waapi.sh
scripts/vps-deploy-skeleton.sh
docs/CUTOVER-PORTAL.md
docs/integration/VPS_CHECKLIST.md
.env.example
skeleton/.env.example
config/vertical.php
src/Application/Services/CrudConfigLoader.php
src/Application/Services/CalendarConfigLoader.php
src/Presentation/Middlewares/AuthMiddleware.php
public/install/index.php
routes/web.php
database/migrations/*.sql

# Solo feature (VPS):
config/cruds/mkt_ordenes.json
database/schema/modules/marketing.sql
database/migrations/202607*.sql
```

---

## Acciones recomendadas

1. **Humano/Ops:** Planificar cutover `Lebytek_Portal` según `docs/CUTOVER-PORTAL.md`; no merge feature→main sin orden explícita.
2. **Issue (existente):** Mantener #21 y #23; actualizar #23 para reflejar post-FPS (main sin marketing).
3. **Issue (nuevo sugerido):** CRUD form save debe respetar state machine / `status` readonly en órdenes pagadas (Portal).
4. **PR framework (futuro):** Sanitización `{resource}`/`{key}` en config loaders.
5. **CI:** Pipeline con `php tests/run.php` en `main`.

---

## Recomendación final

**requiere revisión humana**

No hay regresiones nuevas en `main` esta semana. Los riesgos críticos son **operacionales y de cutover VPS**, no del package source recién consolidado. Se entrega PR menor con documentación de `INSTALL_TOKEN` en templates de entorno.
