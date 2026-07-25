# Auditoría técnica diaria — 2026-07-25

**Repositorio:** `Lebytek_Framework` (package source `lebytek/framework`)  
**Rama auditada:** `main` @ `607a3c6` (merge PR #26 — FPS consolidation)  
**Rama de referencia VPS:** `feature/backoffice-api-integration` (delta ~46 commits `main`-only / ~53 feature-only)  
**Automatización:** cron `1cfa9bdd-809a-11f1-ba66-0e7d0216e441`  
**Verificación runtime:** no ejecutada — entorno cloud sin `php`, `composer` ni `vendor/`

---

## Resumen ejecutivo

**Sin cambios de código en `main` en las últimas 24 h** (último commit sigue siendo el merge FPS del 2026-07-21). El repositorio permanece estable como **package source**: sin Marketing, sin `dom_mkt_*` en schema plataforma, gates documentados en `docs/CUTOVER-PORTAL.md` y checklist FPS publicado.

El riesgo dominante sigue siendo **operativo/VPS**, no deuda nueva en `main`: `scripts/vps-deploy-lebytek-com.sh` clona `feature/backoffice-api-integration`, fuerza `marketing => true` y aplica migraciones de negocio que ya no existen en `main`. Producción lebytek.com **no puede alinearse** con el modelo FPS hasta cutover a `Lebytek_Portal`.

Issues abiertos #21 (Stripe subscription) y #23 (bootstrap leads) aplican a la **rama VPS/Portal**, no al paquete post-FPS.

**Recomendación final:** **requiere revisión humana** — decisión de cutover Portal y actualización del pipeline VPS. No merge automático de `feature/backoffice-api-integration` → `main`.

---

## Hallazgos críticos

### C1 — Deploy VPS atado a rama monolito obsoleta

| Campo | Detalle |
|-------|---------|
| **Evidencia** | `scripts/vps-deploy-lebytek-com.sh:6` → `BRANCH=feature/backoffice-api-integration`; línea 23 `sed` fuerza marketing ON |
| **Impacto** | lebytek.com no refleja `main` ni el contrato `docs/PACKAGE-ROOT.md`; imposible validar gates FPS en producción |
| **Acción** | Cutover humano según `docs/CUTOVER-PORTAL.md`. Congelar SHA explícito si se mantiene monolito temporalmente |

### C2 — Bootstrap Marketing incompleto (rama VPS / Portal)

| Campo | Detalle |
|-------|---------|
| **Evidencia** | Issue #23; `marketing.sql` en feature sin columnas lifecycle/churn que sí esperan migraciones `20260701160000+` |
| **Impacto** | Greenfield o `install.php` parcial → fallos SQL en repos de leads/churn |
| **Acción** | Resolver en **Lebytek_Portal** o parche en feature; re-etiquetar #23 como Portal/VPS |

### C3 — Stripe subscription activation (rama VPS)

| Campo | Detalle |
|-------|---------|
| **Evidencia** | Issue #21 — gaps en first-activation, metadata invoice, recover/reactivate |
| **Impacto** | Órdenes `pending_payment` permanentes; activación silenciosamente fallida |
| **Acción** | Mantener `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` / checkout OFF en VPS hasta cierre |

### C4 — CRUD `mkt_ordenes.status` editable (rama VPS)

| Campo | Detalle |
|-------|---------|
| **Evidencia** | Reportes auditorías previas; config CRUD en feature permite editar estado de orden |
| **Impacto** | Bypass del flujo `AutorizarOrdenMembresiaUseCase` vía backoffice |
| **Acción** | Issue en Portal; campo `status` read-only o acción dedicada con RBAC |

---

## Hallazgos medios

### M1 — `.env.example` del harness con variables Portal/Marketing

| Campo | Detalle |
|-------|---------|
| **Evidencia** | Root `.env.example` incluye `LEBYTEK_API_*`, `MKT_*`, URLs waapi; `skeleton/.env.example` está limpio |
| **Impacto** | Confusión para mantenedores del paquete; riesgo de copiar vars obsoletas en harness |
| **Acción** | PR de documentación: podar vars de negocio del harness o mover a comentario “solo Portal” |

### M2 — `INSTALL_TOKEN` ausente en `.env.example`

| Campo | Detalle |
|-------|---------|
| **Evidencia** | `public/install/index.php` y `docs/core/despliegue-y-versionado.md` exigen token en producción; ningún `.env.example` lo documenta |
| **Impacto** | Instalador web bloqueado en producción sin documentación clara |
| **Acción** | PR incluido en esta auditoría (fix bajo riesgo) |

### M3 — `/api/ping` detrás de `AuthMiddleware`

| Campo | Detalle |
|-------|---------|
| **Evidencia** | `routes/api.php:14-17` — grupo `/api` exige sesión; `HealthController::ping` no es público |
| **Impacto** | Health checks externos/load balancer requieren cookie de sesión |
| **Acción** | Issue o PR: ruta pública `/api/health` o ping fuera del grupo autenticado |

### M4 — Slug `permisos.gestionar` inexistente

| Campo | Detalle |
|-------|---------|
| **Evidencia** | `routes/web.php:61-65` — permisos admin usan `administracion.ver` |
| **Impacto** | Granularidad RBAC débil para gestión de permisos |
| **Acción** | Issue de alineación (doc `correccion_alineacion_modulos_v0.1.md` ya lo registra) |

### M5 — PRs draft de auditorías sin consolidar

| Campo | Detalle |
|-------|---------|
| **Evidencia** | PRs #25, #27, #28 en estado DRAFT |
| **Impacto** | Duplicación de reportes; fixes documentados no mergeados |
| **Acción** | Revisión humana: merge o cierre de drafts obsoletos |

### M6 — Migración `auth_login_intentos` archivada en legacy

| Campo | Detalle |
|-------|---------|
| **Evidencia** | `database/migrations_legacy/.../20260612130000_auth_login_intentos.sql`; tabla presente en `database/schema/schema.sql` |
| **Impacto** | Instalaciones fresh OK vía schema; upgrades antiguos dependen de legacy path |
| **Acción** | Verificar en VPS que tabla existe; sin acción en `main` si schema cubre greenfield |

---

## Mejoras rápidas (bajo riesgo)

| # | Mejora | Estado |
|---|--------|--------|
| Q1 | Añadir `INSTALL_TOKEN=` a `.env.example` (root + skeleton) | **Incluido en PR de esta auditoría** |
| Q2 | Podar `MKT_*` / `LEBYTEK_API_*` del `.env.example` del harness | Pendiente — PR docs |
| Q3 | Exponer health check API sin auth | Pendiente — issue M3 |
| Q4 | Consolidar/cerrar PRs draft #25/#27 tras merge de #28 | Pendiente — humano |

---

## Riesgos de deploy en VPS

| Riesgo | Severidad | Notas |
|--------|-----------|-------|
| Script apunta a feature, no a Portal Composer | **Alta** | Cutover pendiente |
| `sed` fuerza marketing ON incompatible con tenants skeleton | **Alta** | Solo lebytek.com monolito |
| Loop migraciones `202606*.sql` / `202607*.sql` en deploy script | **Media** | En `main` solo quedan 3 migraciones plataforma |
| `APP_DEBUG=true` / `SESSION_SECURE=false` en `.env.example` | **Media** | Verificar `.env` real en VPS (no auditado) |
| `migrate.php \|\| true` en deploy — errores silenciados | **Media** | Puede ocultar drift de schema |
| Instalador web sin `INSTALL_TOKEN` documentado | **Baja→Media** | Mitigado con Q1 |

---

## Cambios recientes en Git (ventana 24 h)

**Ningún commit nuevo en `origin/main`** desde la auditoría del 2026-07-24.

Últimos cambios relevantes en `main` (contexto 7 días):

- PR #26 — separación FPS: eliminación Marketing, `composer.json` solo `Lebytek\Framework\`
- Migraciones legacy movidas a `database/migrations_legacy/`
- Docs: `CUTOVER-PORTAL.md`, `ARCHITECTURE-CONSUMER.md`, reglas Cursor package-source
- Tests: `FrameworkRootNotPortalTest`, `FpsPublicationReadinessTest`, `SkeletonPurityTest`

---

## Módulos revisados

| Módulo | Estado en `main` | Observaciones |
|--------|------------------|---------------|
| Auth / RBAC | Estable | Rate limit login vía `auth_login_intentos`; CSRF en POSTs |
| CRUD Engine | Estable | RBAC por recurso en servicio, no en ruta |
| Integrations | Estable | Rutas WA `/wa/activar/{token}` con `SignedToken` + TTL |
| Payments (Stripe) | OFF default | Genérico en `src/`; checkout membresía → Portal |
| Reportes / PDF / Calendario | Estable | RBAC en servicio/controlador |
| Marketing / Portal | **Removido** | Solo en feature branch / Lebytek_Portal |

---

## Migraciones activas (plataforma)

En `database/migrations/` (3 archivos, alineados con skeleton):

- `20260609120000_crud_demo_permisos_modulo_por_recurso.sql`
- `20260612120000_auth_registro_recuperacion.sql`
- `20260614120000_pdf_kit_demo_menu.sql`

Legacy archivado en `database/migrations_legacy/incrementales-2026-06/` (incl. login intentos, menús admin, permisos dom deprecados).

---

## Tests

| Suite | Estado |
|-------|--------|
| Harness completo (`php tests/run.php`) | **No ejecutado** — sin PHP en entorno agent |
| Gates documentados (552 tests @ 2026-07-19) | Referencia en `FPS-publication-manifest-checklist.md` |
| Cobertura Portal/Marketing | **Fuera de scope** — repo Portal |

Tests faltantes sugeridos (no bloqueantes):

- Contrato health API público (cuando se implemente M3)
- Smoke `.env.example` vs vars leídas por `EnvLoader` (INSTALL_TOKEN)

---

## Documentación

| Doc | Estado |
|-----|--------|
| `docs/PACKAGE-ROOT.md` | Actualizado — forbids deploy |
| `docs/CUTOVER-PORTAL.md` | Actualizado — gates + rollback |
| `docs/ARCHITECTURE-CONSUMER.md` | Actualizado post-FPS |
| Root `.env.example` | **Desactualizado** vs modelo package-source (M1) |
| PRs draft auditoría | Pendiente consolidación (M5) |

---

## Archivos involucrados

- `scripts/vps-deploy-lebytek-com.sh`
- `docs/CUTOVER-PORTAL.md`, `docs/PACKAGE-ROOT.md`
- `routes/web.php`, `routes/api.php`, `routes/integrations.php`
- `config/vertical.php`
- `.env.example`, `skeleton/.env.example`
- `database/migrations/*.sql`, `database/schema/schema.sql`
- `public/install/index.php`
- `src/Application/Services/LoginRateLimitService.php`
- `src/Kernel/Security/SignedToken.php`

---

## Recomendación final

**requiere revisión humana**

Prioridades sugeridas:

1. Decisión cutover Portal vs mantener monolito feature (bloqueante producción)
2. Actualizar/cerrar issues #21 y #23 en contexto Portal
3. Merge PR de esta auditoría (reporte + `INSTALL_TOKEN` en `.env.example`)
4. PR follow-up para limpiar `.env.example` harness (M1) y health API (M3)
