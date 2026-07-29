# Auditoría técnica diaria — 2026-07-29

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `0ec722bc38258b2e479d30cafd59940aa44d558e` |
| SHA Portal inspeccionado | **No disponible** — `gh api repos/Parzival2103/Lebytek_Portal` → HTTP 404 (repo privado o token sin acceso). Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `b6c37739983ded445259c038521861ffb5f253c8` @ `main` (2026-07-27) |
| Rama generada | `automation/audit-2026-07-29` |
| Timestamp UTC | `2026-07-29T13:00:49Z` (trigger cron) / corrida agente `2026-07-29T13:02:25Z` |
| Automation ID | `1cfa9bdd-809a-11f1-ba66-0e7d0216e441` |

---

## Evidencia de preflight

```console
$ git fetch origin --prune --tags
(ok)

$ git rev-parse --verify origin/main
0ec722bc38258b2e479d30cafd59940aa44d558e

$ git merge-base --is-ancestor origin/main HEAD
(exit 0)

$ git status --porcelain   # antes de escribir (rama agente previa)
(vacío en árbol tracked; composer.phar eliminado antes del commit)
```

### `<LEGACY_REF>`

Primer candidato que resolvió:

```console
$ git rev-parse --verify --quiet 'refs/tags/archive/backoffice-api-integration^{commit}'
4789f953ef746d17bae2e6b50c85504782d306e3
```

- Tag: `archive/backoffice-api-integration` @ `4789f95` (mensaje: evidencia histórica de migración).
- Rama remota `origin/feature/backoffice-api-integration` sigue existiendo; el tag es la referencia canónica post-archivo.
- Commits exclusivos del legacy: **53** (`git rev-list origin/main..refs/tags/archive/backoffice-api-integration | wc -l`).
- Divergencia bidireccional: **70** commits sólo en `main` / **53** sólo en legacy.
- Comprobación de ancestros: ninguno de los 53 commits legacy es ancestro de `HEAD` (preflight OK).

---

## Resumen ejecutivo

`main` avanzó **7 commits funcionales** desde la auditoría consolidada del 2026-07-27 (`607a3c6` → `0ec722b`). Los entregables más relevantes son:

1. **Eliminación de scripts VPS destructivos** (PR #36) — ya verificada; guardas de regresión activas.
2. **Fix instalador + Stripe subscription boundary v1.2.1** (PR #42, tag `v1.2.1`) — contrato Framework para issue #21 cerrado.
3. **SqlFileRunner consciente de literales** (PR #40) — corrige partición SQL en seeds con `;` dentro de cadenas.
4. **Documentación de entornos y automatización** (PRs #43–#46) — `ENVIRONMENTS.md`, planes archivados, cadena diaria endurecida.

No se detectaron regresiones de frontera FPS: `src/` sigue sin Marketing/LebytekApi; `SkeletonPurityTest` verde. El paquete está en condiciones de release; el riesgo principal sigue siendo **consumo en Portal** (lock semver, QA Stripe) y **entornos pendientes** (`skeleton.lebytek.com`, staging Portal).

**Hallazgos nuevos:** 0 críticos, 2 medios. **Deuda arrastrada:** 4 ítems (2 críticos operativos mitigados, 2 medios abiertos).

---

## Hallazgos críticos

*Ningún hallazgo crítico nuevo en Framework `main` en esta corrida.*

### Deuda crítica arrastrada (estado actualizado)

| ID | Hallazgo | Estado 2026-07-29 | Owner |
|----|----------|-------------------|-------|
| C1 (2026-07-27) | Scripts `vps-deploy-*.sh` destructivos | **RESUELTO** — eliminados PR #36; `DeployScriptsRemovedTest` verde | Framework |
| C2 (2026-07-27) | Stripe subscription C1–C6 (#21) | **Framework RESUELTO** — PR #42 + tag `v1.2.1`. Portal PR #16 referenciado por owner. Gate ops `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` **sigue vigente** hasta QA humana | Framework ✅ / Portal ⏳ QA |
| C3 (2026-07-27) | Bootstrap marketing + migraciones (#23) | **Re-scopeado** — Portal `Lebytek_Portal#4`. No aplica a Framework `main` | Portal |

---

## Hallazgos medios

### M1 — `config/app.php` version desincronizada del release semver

| Campo | Valor |
|-------|-------|
| Archivo | `config/app.php` |
| Evidencia | `'version' => '1.0.0'` mientras tags publicados llegan a `v1.2.1` |
| Impacto | Dashboard/operadores pueden mostrar versión incorrecta; confusión en soporte |
| Owner | Framework |
| Acción | Alinear `config/app.php` con semver del tag en próximo release o PR dedicado |

### M2 — `.env.example` root conserva variables Portal/Marketing (arrastrado)

| Campo | Valor |
|-------|-------|
| Archivos | `.env.example` (root harness) |
| Evidencia | `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*` presentes; `skeleton/.env.example` limpio |
| Impacto | Ruido en harness local; riesgo de confusión para nuevos tenants |
| Owner | Framework (harness) |
| Estado | **Abierto** — pendiente issue dedicado (identificado 2026-07-27) |

### Deuda media arrastrada (sin cambio)

| ID | Hallazgo | Estado |
|----|----------|--------|
| M3 (2026-07-27) | CRUD/Calendario sin `RbacMiddleware` a nivel router | Abierto — defensa en profundidad aceptable |
| M4 (2026-07-27) | API `/api/*` autenticada por sesión | Abierto — documentar o añadir `/api/health` |
| M5 (2026-07-27) | `permisos.gestionar` ausente en seeds | Abierto — workaround `administracion.ver` |

### M6 (nuevo, bloqueador entorno) — Portal SHA no inspeccionable desde agente cloud

| Campo | Valor |
|-------|-------|
| Evidencia | `gh repo view Parzival2103/Lebytek_Portal` → 404; repo no listado en `gh repo list Parzival2103` |
| Impacto | No se puede verificar SHA de producción Portal ni `composer.lock` en esta corrida |
| Mitigación | Operador con acceso debe confirmar Portal @ lock con `lebytek/framework` ≥ v1.2.1 antes de habilitar subscriptions |
| Owner | Ops / credenciales automation |

---

## Ownership por repositorio

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` @ `0ec722b` | Auth, RBAC, CRUD, install, Payments genérico, skeleton |
| Release semver | Tag `v1.2.1` @ `fba3e03` | Contrato Stripe subscription + install fixes |
| App desplegable | `Lebytek_Portal` / `main` | Marketing, leads, membresías, checkout UX, SQL `dom_*` |
| API WhatsApp | `WhatsApiLebytek` / `main` @ `b6c3773` | Green API, lifecycle instancias |
| Legacy (histórico) | `archive/backoffice-api-integration` @ `4789f95` | Evidencia migración — **no** base de trabajo |

**Dependencia cruzada:** habilitar `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` en Portal requiere tag Framework ≥ v1.2.1 **y** merge/QA de Portal PR #16 (referenciado en cierre #21).

---

## Cambios recientes en `main` (desde auditoría 2026-07-27)

| PR | Tema | Relevancia auditoría |
|----|------|---------------------|
| #36 | Delete `vps-deploy-*.sh` | Cierra C1 deploy destructivo |
| #37 | Auditoría consolidada + `INSTALL_TOKEN` | Artefacto previo |
| #38 | Preflight legacy-ref automation | Guarda regresión preflight |
| #40 | SqlFileRunner literales | Fix install seeds complejos |
| #42 | Install resolve + Stripe v1.2.1 | Cierra Framework side #21 |
| #43–#45 | Docs ENVIRONMENTS + archive plans | Alineación operativa |
| #46 | Cadena diaria 6 etapas | Automation hardening |

Commits no-merge desde baseline anterior: 7 (`9abca84` … `a6eac47`).

---

## Fronteras del paquete

Verificación estática + suites:

- `src/` — sin clases Marketing/LebytekApi; referencias `dom_` sólo en validación CRUD (permitir prefijo tenant).
- `SkeletonPurityTest` — **13/13 PASS** (marketing OFF, sin LebytekApi, sin SQL negocio).
- `scripts/vps-deploy-*.sh` — **0 archivos** (`DeployScriptsRemovedTest` PASS).
- Payments — contrato genérico en `src/Domain/Payments/`; vertical OFF en harness y skeleton.

**Conclusión:** no se coló negocio Portal en Framework en este intervalo.

---

## Riesgo de deploy / release

| Riesgo | Severidad | Estado |
|--------|-----------|--------|
| Deploy desde rama legacy | Alta (histórico) | **Mitigado** — scripts eliminados; tag archivado |
| Fresh install SQL con `;` en strings | Media | **Resuelto** PR #40 |
| Stripe subscriptions sin QA | Alta | **Mitigado** — checkout OFF; Framework v1.2.1 publicado; Portal bump pendiente verificación |
| Portal prod SHA desconocido (este run) | Media | **Bloqueador entorno** — requiere gh con acceso Portal |
| `skeleton.lebytek.com` no desplegado | Media | **Abierto** — plan documentado en `ENVIRONMENTS.md` |
| Staging Portal inexistente | Media | **Abierto** — fase futura |
| Versión UI `1.0.0` vs tag `v1.2.1` | Baja | M1 |

---

## Archivos involucrados (delta relevante post-2026-07-27)

- `src/Application/Install/Installer.php` — mensajes accionables si falta migración/seed
- `src/Infrastructure/Install/SqlFileRunner.php` — parser SQL robusto
- `src/Domain/Payments/*`, `src/Infrastructure/Payments/StripeGateway.php` — v1.2.1 boundary
- `tests/Install/SqlFileRunnerTest.php`, `tests/Payments/*` — cobertura nuevos contratos
- `tests/Docs/DeployScriptsRemovedTest.php`, `tests/Docs/AutomationPreflightRefTest.php`
- `docs/ENVIRONMENTS.md`, `docs/automation/*`, `docs/archive/superpowers/*`
- Eliminados: `scripts/vps-deploy-lebytek-com.sh`, `vps-deploy-waapi.sh`, `vps-deploy-skeleton.sh`

---

## Evidencia de verificación

### Entorno

| Componente | Estado |
|------------|--------|
| PHP CLI | Instalado durante corrida (`php8.3-cli`) — **no** presente al inicio |
| Composer / vendor | Instalado ad-hoc (`composer.phar install`) — **no** presente al inicio |
| `ext-pdo_mysql` | **Ausente** — 7 tests Integrations fallan por `could not find driver` |
| Acceso Portal (`gh`) | **404** — repo privado |

### Comandos ejecutados

```console
$ php tests/run.php
569 passed, 7 failed
exit code: 1

$ php tests/run.php Kernel
46 passed, 0 failed

$ php tests/run.php Payments
21 passed, 0 failed

$ php tests/run.php SkeletonPurity
13 passed, 0 failed

$ php tests/run.php Install
50 passed, 0 failed
```

### Análisis de fallos

Los **7 fallos** son exclusivamente tests de Integrations/WhatsApp que requieren PDO MySQL:

- `save + findById conserva datos...`
- `el token se guarda cifrado...`
- `markDefault deja solo una instancia...`
- `recent devuelve los últimos envíos...`
- `recent filtra por canal`
- `IntegrationsFactory::resolveWhatsappConfig...` (×2)

**Clasificación:** bloqueador de entorno (`pdo_mysql`), **no** defecto de código. Payments (incl. Stripe v1.2.1) y SkeletonPurity completamente verdes.

Comparativa con auditoría 2026-07-27: entonces 552 pass / 3 fail por `stripe/stripe-php` ausente; ahora Stripe resuelto, MySQL driver ausente en cloud agent.

---

## Recomendación final

1. **Framework `main`:** estable para continuar cadena automation (spec/plan). Considerar PR menor para M1 (`config/app.php` version).
2. **Portal:** operador con acceso debe confirmar SHA + `composer.lock` con `lebytek/framework` ≥ v1.2.1 y estado QA PR #16 antes de levantar gate Stripe.
3. **Automation:** otorgar token `gh` acceso lectura a `Lebytek_Portal` para futuras auditorías.
4. **CI cloud agent:** preinstalar `php-cli`, `composer`, `php-mysql` para gate verde completo (569+7).
5. **Próximo hito operativo:** implementar `skeleton.lebytek.com` según plan archivado — validar releases antes de bump Portal.

**Veredicto:** paquete sano; 0 críticos nuevos; release v1.2.1 coherente con código; deuda operativa concentrada en Portal/ops y entorno de verificación.
