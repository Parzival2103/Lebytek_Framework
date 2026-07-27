# Auditoría técnica diaria — 2026-07-27 (consolidada)

**Repositorio:** `Lebytek_Framework` (package source `lebytek/framework`)
**Rama auditada:** `main` @ `607a3c6` (merge PR #26 FPS, 2026-07-21)
**Entorno de verificación:** local, con PHP CLI y acceso SSH al VPS.

> **Este documento consolida y reemplaza** los artefactos de auditoría de los PR
> #31 (2026-07-26) y #33 (2026-07-27), cerrados por duplicidad. El objetivo es
> mantener **un** artefacto de auditoría vigente, no cuatro PRs draft paralelos.

---

## Corrección de la auditoría original (leer primero)

El reporte del PR #33 fue generado por un agente cloud **sin PHP CLI ni acceso al
VPS**, y dedujo el estado de producción leyendo `scripts/vps-deploy-lebytek-com.sh`.
Esa deducción era **incorrecta**: el script existía en el repo pero **nadie lo
ejecutaba**. Su hallazgo crítico C1 («lebytek.com corre el monolito pre-FPS») es
falso.

Estado real, verificado por SSH el 2026-07-27:

```console
$ cd /home/lebytek/htdocs/lebytek.com && git rev-parse HEAD
a79d3ad8d44958c2978e82d7eceee4f413e464fd      # Portal main, merge de PR #6
$ composer show lebytek/framework | grep versions
versions : * v1.1.0

$ cd /home/lebytek-waapi/htdocs/waapi.lebytek.com && git rev-parse HEAD
a79d3ad8d44958c2978e82d7eceee4f413e464fd      # idéntico
$ git status --porcelain
                                              # vacío: árbol limpio
```

**El cutover ya ocurrió.** Ambos hosts corren `Parzival2103/Lebytek_Portal@main`
consumiendo `lebytek/framework` v1.1.0 **como paquete Composer**, no como rama
clonada. La lección metodológica: *la presencia de un script de deploy en el
repositorio no es evidencia de lo que corre en el servidor.*

---

## Resumen ejecutivo

`main` está **estable**. Tras la consolidación FPS, `src/` mantiene auth, RBAC,
CRUD Engine, integraciones, reportes y payments genérico (OFF por defecto);
Marketing y checkout viven en Portal.

El riesgo operativo que dominaba las auditorías anteriores —la divergencia entre
`main` y producción— **está cerrado**. Lo que quedaba era el propio
`scripts/vps-deploy-*.sh`, eliminado en esta tanda (ver C1 abajo).

---

## Hallazgos críticos

### C1 — Scripts de deploy destructivos en el repo — **RESUELTO**

| Campo | Valor |
|-------|-------|
| Archivos | `scripts/vps-deploy-lebytek-com.sh`, `vps-deploy-waapi.sh`, `vps-deploy-skeleton.sh` |
| Evidencia | `find "$APP_DIR" -mindepth 1 -maxdepth 1 ! -name '.env' -exec rm -rf {} +` seguido de `git clone --branch feature/backoffice-api-integration` |
| Impacto real | **No** describían el estado de producción. Eran una bomba: ejecutados, borraban `lebytek.com` / `waapi.lebytek.com` vivos y los repoblaban con código pre-cutover. Tras borrar la rama legacy, el `rm -rf` corre igual y el `clone` falla → sitio vacío. |
| Gate verificado | Ningún crontab (`lebytek`, `lebytek-waapi`, `root`), entrada `/etc/cron*` ni timer systemd los invocaba (2026-07-27) |
| Acción | **Eliminados en [PR #36](https://github.com/Parzival2103/Lebytek_Framework/pull/36)**, con guarda de regresión `tests/Docs/DeployScriptsRemovedTest.php`. Portal eliminó sus dos copias en `23e32a7`. |

### C2 — Issue #21: Stripe subscription activation — **abierto**

Seis criticals documentados: first-activation gap, metadata en `invoice.paid`,
retry que crea nuevo checkout, post-claim swallow (webhook 200), desincronización
en recover, y bypass de importe con `currency ≠ mxn`.

Mitigación vigente en VPS: `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` hasta el cierre.
La implementación va en `src/Domain/Payments/` → release semver → bump del lock en
Portal. Ver [#21](https://github.com/Parzival2103/Lebytek_Framework/issues/21).

### C3 — Issue #23: bootstrap marketing + migraciones — **re-scopeado**

Ya no es responsabilidad de Framework `main`: Marketing salió a Portal con el
carve-out (Portal PR #5). El trabajo real vive en
[`Lebytek_Portal#4`](https://github.com/Parzival2103/Lebytek_Portal/issues/4)
(reconciliation runner). Ver el comentario de cierre en #23.

---

## Hallazgos medios

### M1 — `INSTALL_TOKEN` ausente en `.env.example` — **RESUELTO**

`public/install/index.php` exige token cuando `APP_ENV=production`, pero
`.env.example` (root y skeleton) no lo documentaba → riesgo de instalador
bloqueado en producción sin pista de por qué.

**Fix aplicado en este PR:** `INSTALL_TOKEN=` añadido en ambos archivos.

> Nota de seguridad relacionada, fuera del alcance de este PR: esa exigencia sólo
> aplica con `APP_ENV === 'production'`. Un despliegue con `APP_ENV=staging`
> expone `/install/` **sin token**. El plan skeleton lo cubre escribiendo
> `storage/install.lock` antes de exponer el sitio (Task 7).

### M2 — `.env.example` root conserva variables Portal/Marketing post-FPS

`MKT_*`, `LEBYTEK_API_*` y `WAAPI_PORTAL_*` permanecen en el harness root pese a
que Marketing fue extraído a Portal. El `skeleton/.env.example` ya está limpio
(validado por `SkeletonPurityTest`).

**Acción:** issue dedicado. No se purga aquí para no mezclar una limpieza de
superficie con la consolidación de auditoría.

### M3 — Rutas CRUD/Calendario sin `RbacMiddleware` a nivel router

`/admin/crud/{resource}` y `/admin/calendario/{key}` sólo llevan `AuthMiddleware`
en el grupo; el RBAC se aplica dentro de `CrudResourceService` /
`CalendarViewModelBuilder` vía `RbacService::verificar()`.

Es defensa en profundidad aceptable, pero inconsistente con usuarios/roles/reportes.
Un usuario autenticado sin permiso recibe 403 desde el servicio, no desde el
middleware. **Backlog, riesgo bajo.**

### M4 — API `/api/*` autenticada por sesión, no por token

`routes/api.php` comenta «autenticación futura mediante token». `/api/ping` exige
sesión activa, así que no sirve como health check externo sin cookie.

**Acción:** documentarlo, o exponer un `/api/health` público si se quiere
monitoreo desde el VPS.

### M5 — `permisos.gestionar` inexistente en seeds

Las rutas de permisos usan `administracion.ver` como workaround (comentado en
`routes/web.php`). Deuda RBAC conocida desde
`docs/audits/correccion_alineacion_modulos_v0.1.md`.

---

## Mejoras rápidas

| # | Mejora | Estado |
|---|--------|--------|
| Q1 | `INSTALL_TOKEN` en `.env.example` (root + skeleton) | ✅ este PR |
| Q2 | Un único artefacto de auditoría vigente en `docs/audits/` | ✅ este archivo |
| Q3 | Limpiar `MKT_*` / `LEBYTEK_API_*` del `.env.example` root | pendiente — issue (M2) |
| Q4 | ~~Nota «DEPRECATED» en `vps-deploy-lebytek-com.sh`~~ | ✅ superado — el script **se borró** |
| Q5 | Ejecutar la suite local | ✅ `php tests/run.php` — ver abajo |

---

## Verificación ejecutada

A diferencia de las corridas #31 y #33, esta auditoría **sí ejecutó la suite**:

```console
$ php tests/run.php          # baseline en origin/main
549 passed, 3 failed

$ php tests/run.php          # con la limpieza de scripts + guarda de regresión
552 passed, 3 failed
```

Los 3 fallos son **preexistentes y ajenos** a estos cambios: `StripeGateway` falla
con `Class "Stripe\Stripe" not found` porque `vendor/stripe` no está instalado en
el entorno local, aunque `composer.json` declara `stripe/stripe-php: ^16.0`. No es
un defecto del repositorio; es una dependencia ausente del checkout local.

---

## Riesgos de deploy — estado corregido

| Riesgo | Severidad previa | Estado real 2026-07-27 |
|--------|------------------|------------------------|
| Branch obsoleta en auto-deploy | Alta | **Inexistente.** Nunca hubo auto-deploy: sin cron, sin timer, sin webhook. El deploy es `git pull` manual sobre Portal `main`. |
| Marketing forzado a ON vía `sed` en deploy | Alta | **Eliminado** con los scripts |
| `install.php` + SQL marketing en script legacy | Alta | **Eliminado** con los scripts |
| Wizard sin `INSTALL_TOKEN` en prod | Media | Mitigado (M1); el operador debe generar el token |
| Esquema Marketing sin runner de reconciliación | **Alta** | **Abierto** — `migrate-marketing.php` sale `2` con `SCHEMA_RECONCILIATION_REQUIRED` (fail-closed deliberado). Ver `Lebytek_Portal#4`. |
| Stripe subscriptions | **Alta** | **Abierto** — `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` hasta cerrar #21 |
| `APP_DEBUG=true` / `SESSION_SECURE=false` en `.env.example` | Baja | Documentado; checklist en `despliegue-y-versionado.md` |
| Payments OFF en el package | Info | Correcto — las reglas de negocio Stripe viven en Portal |

---

## Estado de la rama legacy

```console
$ git rev-list --left-right --count main...origin/feature/backoffice-api-integration
46      53          # 46 commits sólo en main / 53 sólo en la rama
```

La divergencia es real pero **ya no es un riesgo de deploy**, porque ningún host
consume esa rama. Se archivará como tag `archive/backoffice-api-integration`
**antes** de borrarla (Task 10 del plan skeleton), de modo que los commits que cita
`docs/superpowers/FPS-git-baseline.md` no queden inalcanzables y sujetos a GC.

Precondición dura de ese borrado: la eliminación de los `vps-deploy-*.sh` en ambos
repos — completada (C1).
