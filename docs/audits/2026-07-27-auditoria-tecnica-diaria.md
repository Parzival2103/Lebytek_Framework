# Auditoría técnica diaria — 2026-07-27

**Repositorio:** `Lebytek_Framework` (package source `lebytek/framework`)  
**Rama auditada:** `main` @ `607a3c6` (merge PR #26 FPS, 2026-07-21)  
**Rama de trabajo agente:** `cursor/auditor-a-t-cnica-aaa2`  
**Ventana Git:** sin commits en `main` en las últimas 24 h; último cambio hace 6 días  
**Entorno verificación:** agente cloud — PHP/Composer no disponibles; tests no ejecutados

---

## Resumen ejecutivo

El repositorio está **estable en `main`** tras la consolidación FPS (Framework/Portal separados). No hubo actividad de código en la última semana. La plataforma en `src/` mantiene auth, RBAC, CRUD Engine, integraciones, reportes y payments genérico (OFF por defecto). Marketing y checkout viven fuera de este repo.

El **riesgo operativo principal** sigue siendo la **divergencia entre `main` y producción VPS**: `scripts/vps-deploy-lebytek-com.sh` despliega `feature/backoffice-api-integration` @ `4789f95` (~458 archivos de diff vs `main`). Issues abiertos #21 (Stripe subscription) y #23 (bootstrap marketing / migraciones) siguen vigentes en el contexto VPS/feature, no en el package source limpio de `main`.

**Recomendación final:** **requiere revisión humana** — cutover Portal/VPS antes de alinear deploy con `main`. Fix menor aplicado en esta corrida: `INSTALL_TOKEN` documentado en `.env.example`.

---

## Hallazgos críticos

### C1 — Deploy VPS desacoplado de `main` (FPS)

| Campo | Valor |
|-------|-------|
| Archivo | `scripts/vps-deploy-lebytek-com.sh` |
| Evidencia | `BRANCH=feature/backoffice-api-integration`; `sed` fuerza `marketing => true` |
| Impacto | lebytek.com corre monolito pre-FPS; no refleja package source ni arquitectura Portal |
| Acción | Cutover documentado en `docs/CUTOVER-PORTAL.md`; **orden explícita humana** |

### C2 — Issue #21: Stripe subscription activation (feature/Portal)

6 criticals documentados: first-activation gap, `invoice.paid` metadata, retry crea nuevo checkout, post-claim swallow (webhook 200), recover desync, amount bypass con currency ≠ mxn.

| Campo | Valor |
|-------|-------|
| Archivos (feature) | `ConfirmarPagoStripeUseCase.php`, `RecoverMembershipPaymentService.php`, `StripeGateway.php` |
| Mitigación VPS | Mantener `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` / checkout OFF hasta cierre |
| Acción | **crear issue** — ya abierto #21; no auto-fix |

### C3 — Issue #23: bootstrap marketing + migraciones (feature/VPS)

En `main` post-FPS **no aplica** el bootstrap marketing (eliminado). En VPS/feature persiste:

- `database/schema/modules/marketing.sql` sin columnas API lifecycle/churn
- Manifiestos con migraciones Jul no registradas en algunos paths
- CRUD `mkt_ordenes` permite editar `status` → bypass authorize

| Acción | Re-scope a **Lebytek_Portal** + checklist VPS; issue #23 abierto |

---

## Hallazgos medios

### M1 — `INSTALL_TOKEN` ausente en `.env.example`

El wizard `public/install/index.php` exige token en producción (`docs/core/despliegue-y-versionado.md`), pero `.env.example` (root y skeleton) no lo documentaba → riesgo de instalador bloqueado en prod.

**Fix aplicado:** `INSTALL_TOKEN=` añadido en ambos `.env.example` (PR de esta corrida).

### M2 — `.env.example` root conserva variables Portal/Marketing post-FPS

`MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*` permanecen en el harness root pese a que Marketing fue extraído a Portal. Skeleton `.env.example` ya está limpio (validado por `SkeletonPurityTest`).

**Acción:** **crear issue** o limpiar en PR dedicado tras confirmar que el harness root no las necesita.

### M3 — Rutas CRUD/Calendario sin `RbacMiddleware` a nivel router

`/admin/crud/{resource}` y `/admin/calendario/{key}` solo tienen `AuthMiddleware` en grupo; RBAC se aplica en `CrudResourceService` / `CalendarViewModelBuilder` vía `RbacService::verificar()`.

Defensa en profundidad aceptable pero inconsistente con usuarios/roles/reportes. Usuario autenticado sin permiso recibe 403 vía servicio, no vía middleware.

**Acción:** backlog bajo riesgo; opcional alinear rutas con patrón RBAC explícito.

### M4 — API `/api/*` autenticada por sesión, no token

`routes/api.php` comenta "Autenticación futura mediante token". `/api/ping` requiere sesión activa — no sirve como health check externo sin cookie.

**Acción:** documentar o exponer `/api/health` público si se necesita monitoreo VPS.

### M5 — `permisos.gestionar` inexistente en seeds

Rutas de permisos usan `administracion.ver` como workaround (comentado en `routes/web.php`). Deuda RBAC conocida desde `docs/audits/correccion_alineacion_modulos_v0.1.md`.

### M6 — Divergencia branch feature vs main

```
git rev-list --left-right --count main...origin/feature/backoffice-api-integration
→ 46 commits main-only / 53 feature-only (~458 archivos)
```

---

## Mejoras rápidas (bajo riesgo)

| # | Mejora | Estado |
|---|--------|--------|
| Q1 | Añadir `INSTALL_TOKEN` a `.env.example` (root + skeleton) | ✅ aplicado |
| Q2 | Publicar reporte en `docs/audits/` | ✅ este archivo |
| Q3 | Limpiar `MKT_*` / `LEBYTEK_API_*` del `.env.example` root | pendiente — issue |
| Q4 | Añadir nota en `vps-deploy-lebytek-com.sh` header: "DEPRECATED — ver CUTOVER-PORTAL.md" | pendiente — PR docs |
| Q5 | Ejecutar suite local post-merge: `php tests/run.php` | pendiente — requiere PHP en CI/local |

---

## Riesgos de deploy en VPS

| Riesgo | Severidad | Detalle |
|--------|-----------|---------|
| Branch obsoleta en auto-deploy | **Alta** | Feature monolito vs FPS package + Portal |
| `install.php` + migraciones Jul en script legacy | **Alta** | SQL marketing aplicado manualmente; puede fallar silenciosamente (`\|\| true`) |
| Marketing ON via `sed` en deploy | **Alta** | Contradice `vertical.php` del package (`marketing => false`) |
| Wizard sin `INSTALL_TOKEN` en prod | **Media** | Mitigado con fix `.env.example`; operador debe generar token |
| `migrate.php` solo aplica `schema.sql` | **Media** | Incrementales vía manifiestos `config/modules/*.php` + installer |
| `APP_DEBUG=true` / `SESSION_SECURE=false` en `.env.example` | **Baja** | Documentado; checklist pre-prod en `despliegue-y-versionado.md` |
| Payments OFF en package | **Info** | Correcto; Stripe business rules en Portal/feature |

**Deploy skeleton (`vps-deploy-skeleton.sh`):** apunta a `main` y document root en `skeleton/public` — alineado con FPS.

---

## Cambios recientes en Git (7 días)

| Fecha | Commit | Tipo |
|-------|--------|------|
| 2026-07-21 | `607a3c6` | Merge PR #26 — FPS consolidation |
| 2026-07-21 | `67b5911` … `84025ad` | Docs Plan 08, reglas IA, cutover checklist |
| 2026-07-21 | `eea36eb`, `8ac5680`, `13e752c` | Fixes: orphans install, drop Portal routes/schema |

Sin commits en `main` desde 2026-07-21.

---

## Módulos afectados (estado actual en `main`)

| Módulo | Manifiesto | Migraciones activas | Vertical |
|--------|------------|---------------------|----------|
| core | `config/modules/core.php` | `20260612120000_auth_registro_recuperacion.sql` | ON |
| crud-engine | `config/modules/crud-engine.php` | `20260609120000_crud_demo_permisos_modulo_por_recurso.sql` | demo |
| pdf-kit | `config/modules/pdf-kit.php` | `20260614120000_pdf_kit_demo_menu.sql` | ON |
| integrations | `config/modules/integrations.php` | — | ON |
| calendario | `config/modules/calendario.php` | — | ON |
| reportes | `config/modules/reportes.php` | — | ON |
| payments | `config/modules/payments.php` | — | **OFF** |
| marketing | **eliminado** | — | **OFF** |

---

## Rutas, middleware y permisos

- **Auth:** `AuthMiddleware` en grupo `/admin` y `/api`
- **CSRF:** mutaciones web excepto `/api/*`
- **RBAC:** explícito en dashboard, administración, reportes, integraciones, pdf-kit; implícito en CRUD/calendario vía servicios
- **Público:** login, registro (gated `REGISTRO_HABILITADO`), recuperación, `/wa/activar/{token}` (SignedToken — OK)
- **Rate limit login:** `LoginRateLimitService` wired en `LoginUseCase`

---

## Validaciones

- CRUD: `CrudFieldValidationService`, `CrudConfigValidator` (incl. `security.mode=strict` → solo `dom_*`)
- Auth: DTO + `ValidationException` en registro/recuperación
- Upload: `UploadValidatorTest`, `FileUploadService` con nombres seguros
- IDOR: `CrudScopeResolver::assertOwnedBy` cubierto por `CrudActionOwnershipTest`

---

## Tests

- **151** archivos `*Test.php` en harness
- Gates FPS documentados: `PackageAutoloadBoundary`, `SkeletonPurity`, `FrameworkRootNotPortal`, `FpsPublicationReadiness`
- **No ejecutados** en este entorno (sin PHP CLI)
- Cobertura débil en: auth registro/recuperación E2E, middleware RBAC a nivel router, payments webhook integration

---

## Documentación

| Doc | Estado |
|-----|--------|
| `docs/CUTOVER-PORTAL.md` | Vigente — cutover deferred |
| `docs/ARCHITECTURE-CONSUMER.md` | Vigente post-FPS |
| `docs/superpowers/FPS-publication-manifest-checklist.md` | Existe; checklist humano pendiente |
| `.env.example` root | Parcialmente desactualizado (vars Portal) |
| Issues #21, #23 | Abiertos; siguen siendo fuente de verdad VPS/feature |

---

## Archivos involucrados

```
scripts/vps-deploy-lebytek-com.sh
scripts/vps-deploy-skeleton.sh
routes/web.php
routes/api.php
routes/integrations.php
config/vertical.php
config/modules/*.php
database/migrations/*.sql
public/install/index.php
.env.example
skeleton/.env.example
docs/CUTOVER-PORTAL.md
docs/ARCHITECTURE-CONSUMER.md
src/Application/Services/CrudResourceService.php
src/Application/Services/CrudActionService.php
src/Presentation/Middlewares/*.php
```

---

## Recomendación final

**requiere revisión humana**

Prioridades:

1. Aprobar/ejecutar cutover Portal (`docs/CUTOVER-PORTAL.md`) — no desplegar `main` a lebytek.com sin plan
2. Mantener checkout/subscription OFF en VPS hasta cerrar #21
3. Migrar negocio marketing a `Lebytek_Portal` y retirar deploy feature (#23)
4. Merge PR menor de esta auditoría (`INSTALL_TOKEN` + reporte)
5. Ejecutar `php tests/run.php` en entorno con PHP antes del próximo release
