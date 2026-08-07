# Inventario de deuda técnica D1–D11 — 2026-07-28

**Repositorio:** `Parzival2103/Lebytek_Framework`
**Rama base inspeccionada:** `main` @ `e728474`
**Artefacto:** inventario de deuda técnica con evidencia por archivo y línea (D1–D11)
**Contexto pipeline:** AUTOMATION-02 hizo **SKIP** el 2026-07-28 (sin PR de auditoría elegible); este documento preserva el inventario verificado
**Agente deuda técnica:** corrida 2026-07-28T14:00Z — inventario sobre `main` @ `e728474`
**Agente compatibilidad / UX / responsive:** corrida 2026-07-28T14:30Z — **SKIP** (sin spec del día)

---

## Motivo

No existe un PR de auditoría **abierto y usable** que cumpla los criterios de
AUTOMATION-02 para la corrida del 2026-07-28.

### Búsqueda realizada (2026-07-28T13:35Z)

| Criterio | Resultado |
|----------|-----------|
| PRs abiertos en el repo | **0** (lista vacía vía `gh pr list --state open`) |
| PR draft con título `docs(audit):` sobre `main` | **Ninguno** |
| Rama `cursor/auditor-*` con PR abierto hoy | **Ninguna** |
| Reporte nuevo en `docs/audits/` con fecha 2026-07-28 | **No existe** |

### Último artefacto de auditoría disponible

| Campo | Valor |
|-------|-------|
| PR draft fuente | [#33](https://github.com/Parzival2103/Lebytek_Framework/pull/33) — `docs(audit): auditoría técnica 2026-07-27 + INSTALL_TOKEN` |
| Rama | `cursor/auditor-a-t-cnica-aaa2` |
| Estado | **CLOSED** (2026-07-28T00:09:22Z) |
| Consolidación | [#37](https://github.com/Parzival2103/Lebytek_Framework/pull/37) merged — reporte vigente en `docs/audits/2026-07-27-auditoria-tecnica-diaria.md` |
| Spec derivado (2026-07-27) | `docs/archive/superpowers/specs/2026-07-27-audit-harness-portal-env-purge-design.md` en rama `automation/audit-spec-2026-07-27` (archivado en PR #43) |

La auditoría diaria de las **06:00 UTC** del 2026-07-28 (AUTOMATION-01) **no
dejó** un PR draft elegible antes de esta corrida de spec.

---

## Acción tomada

- **No** se creó `docs/superpowers/specs/2026-07-28-*-design.md` (evitar spec vacío o inventado).
- Este reporte documenta el skip para trazabilidad del pipeline.
- Rama de automation: `automation/audit-spec-2026-07-28` (este archivo + pase deuda técnica + registro pase UX).
- **Spec activo más reciente (no audit):** `docs/superpowers/specs/2026-07-26-skeleton-package-staging-design.md` + plan `docs/superpowers/plans/2026-07-26-skeleton-package-staging.md`.
- **Pase UX:** no se editó spec ni se abrió PR final (sin `docs/superpowers/specs/2026-07-28-*-design.md`; restricción «no inventar PR vacío»).

---

## Pase compatibilidad / UX / responsive (AUTOMATION-02 UX — SKIP)

**Corrida:** 2026-07-28T14:30Z  
**Rama inspeccionada:** `automation/audit-spec-2026-07-28` @ `main` ancestry  
**Spec objetivo:** `docs/superpowers/specs/2026-07-28-*-design.md` — **no existe**

### Verificación pre-pase

| Criterio | Resultado |
|----------|-----------|
| Spec design del día bajo `docs/superpowers/specs/` | **Ausente** |
| PR draft `docs(audit):` abierto sobre `main` (2026-07-28) | **Ninguno** (`gh pr list --state open` vacío) |
| PR auditoría del día a cerrar | **N/A** — AUTOMATION-01 no produjo draft elegible |
| Rama `automation/audit-spec-2026-07-28` | **Presente** — contiene skip + deuda D1–D11 |

### Acción tomada (UX)

- **No** se creó ni editó design spec (evitar artefacto inventado).
- **No** se abrió PR final `automation/audit-spec-2026-07-28` → `main` (solo skip-report; no cumple entregable «spec final»).
- **No** se cerró PR de auditoría (no hay PR draft del 2026-07-28).

### Carry-forward UX (referencia para próximo spec audit)

Cuando AUTOMATION-01/02 produzcan el spec del **2026-07-29** (o reactivación de deuda D1–D3 del archivado 2026-07-27), el pase UX debería cubrir al menos:

| Área | Ítems sugeridos | Contexto |
|------|-----------------|----------|
| **Compatibilidad (K)** | PHP 8.1–8.4 en VPS; install wizard vía `vendor/`; health API sin cookie; `.env.example` sin vars Portal activas | D1–D3, D7 |
| **UX (U)** | Copy install/seed accionable (`resolveInstallFile`); estados vacío/error en CRUD demo skeleton; banner LAB en staging vs prod | PR #42, spec 2026-07-26 |
| **Responsive (R)** | Login/admin dashboard en móvil (320–768px); tablas CRUD scroll horizontal; `APP_URL` HTTPS en staging | skeleton.lebytek.com target |

Spec activo **2026-07-26** (skeleton staging) es infra/Ops — no incluye secciones K/U/R; no se modificó (fuera de alcance «spec del día»).

---

## Deuda técnica

Inventario verificado en rama `automation/audit-spec-2026-07-28` contra `main` @
`e728474` (post PR #37–#45). **Ningún ítem se auto-fixea en esta automatización**
— queda documentado como requisito para el próximo spec o PR humano.

### D1 — Drift harness `.env.example` root vs skeleton (M2 — abierto)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| Root `.env.example` L54–55, L75–82, L92–102: **16 keys activas** con prefijos `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*` | Mantenedores del harness copian contrato Portal/waapi al package source | Purga Enfoque A del spec archivado 2026-07-27; comentario redirección a `Lebytek_Portal/.env.example` |
| `skeleton/.env.example` **sin** esos prefijos; `SkeletonPurityTest` L47–48 lo valida | Asimetría root↔skeleton; FPS «root not portal» incumplido en plantilla | Extender `FrameworkRootNotPortalTest` (ver D2) |
| `INSTALL_TOKEN=` **presente** en root L68 y skeleton L71 (PR #37) | M1/Q1 resuelto; purga M2 sigue pendiente | PR Framework separado de purga env; no regresionar token |

### D2 — Gap test gate `.env.example` root

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `tests/Kernel/SkeletonPurityTest.php` L47–48 — aserción `LEBYTEK_API_*` solo en **skeleton** | Skeleton protegido; root desprotegido | Añadir test en `FrameworkRootNotPortalTest.php` |
| `tests/Kernel/FrameworkRootNotPortalTest.php` — valida dirs/`vertical.php`/`PACKAGE-ROOT.md`; **no** lee `.env.example` | Reintroducción M2 no falla CI | Test: falla si root `.env.example` contiene keys activas `MKT_`, `LEBYTEK_API_`, `WAAPI_PORTAL_` |
| `tests/lib/bootstrap.php` L34–35 carga root `.env.example` si no hay `.env` | Tests plataforma heredan vars Portal del harness | Tras purga: confirmar suite Kernel/Payments/Integrations verde |

### D3 — API health / monitoreo (M4)

| Evidencia | Impacto | Capa | Acción requerida |
|-----------|---------|------|------------------|
| `routes/api.php` L14–24 — grupo `/api` con `AuthMiddleware`; `/api/ping` → `HealthController::ping` | Monitoreo externo sin cookie **no funciona** | Presentation | `GET /api/health` fuera del grupo auth (Fase 3 spec 2026-07-27) |
| Comentario L11: «Autenticación futura mediante token» | Deuda API sin diseño token | Presentation | Backlog; doc distingue ping vs health |

### D4 — RBAC inconsistencias (M3, M5)

| Evidencia | Impacto | Capa | Acción requerida |
|-----------|---------|------|------------------|
| `/admin/crud/{resource}`, `/admin/calendario/{key}` — solo `AuthMiddleware`; RBAC en servicios | 403 vía Application, no middleware | Application | Backlog; no bloqueante |
| `routes/web.php` L58–65 — slug `permisos.gestionar` **inexistente**; rutas permisos usan `administracion.ver` | Roles amplios gestionan permisos RBAC | Presentation | Issue alineación; ref. `docs/archive/audits/correccion_alineacion_modulos_v0.1.md` |

### D5 — Checklist VPS / docs ops obsoletos (drift docs↔producción)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `docs/integration/VPS_CHECKLIST.md` L89 — `Branch: feature/backoffice-api-integration (until merge)` | Ops cree que feature es target; **falso** desde cutover 2026-07-27 | Reescribir sección lebytek.com: `Lebytek_Portal@main` + `composer.lock` |
| L13, L93–98 — referencias a `vps-deploy-lebytek-com.sh` | Script **eliminado** en PR #36 | Sustituir por runbook manual `git pull` Portal |
| L16–17, L118 — cron health cada 5 min **pendiente confirmar crontab** | Monitorización incompleta | Confirmar crontab operador en VPS |
| `docs/composer-setup.md` L121–128 — §6 pin `dev-feature/backoffice-api-integration` | Consumidores nuevos instalan monolito legacy | Actualizar a semver `^1.1` / harness `path` local; marcar §6 histórico |
| `docs/ENVIRONMENTS.md` — canónico post PR #44; prod @ Framework **v1.2.1** | Coherente con PR #42 | VPS_CHECKLIST debe alinearse con ENVIRONMENTS |

### D6 — Plan skeleton.lebytek.com sin implementar (spec 2026-07-26)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `scripts/publish-skeleton.sh` — **no existe** en `main` | No hay publicación `lebytek/skeleton` | Task 4–5 del plan 2026-07-26 |
| `skeleton.lebytek.com` — copia Portal sin git, TLS autofirmado (spec L12) | No hay LAB plataforma aislado | Tasks 6–8 del plan |
| `feature/backoffice-api-integration` — aún no archivada/borrada | 46/53 commits divergentes; GC risk post-borrado | Task 10 plan: tag `archive/backoffice-api-integration` antes de borrar |
| `tests/Docs/DeployScriptsRemovedTest.php` — **existe** (PR #36) | Guarda vps-deploy OK | Verificar en CI cuando exista |

### D7 — Tests / CI ausentes

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `.github/workflows/` — **no existe** | Gates harness no corren en PR | Workflow `php tests/run.php` en push/PR |
| Cloud agent sin PHP CLI | Suite no verificada en cron deuda | CI local o runner PHP antes de merge |
| Auditoría 2026-07-27: 3 fallos preexistentes por `stripe/stripe-php` ausente en checkout sin `composer install` | Falsos rojos en entornos mínimos | Documentar `composer install` en harness README |

### D8 — Stripe subscription (#21) — cerrado con gates documentados

Issue **#21** **CLOSED** 2026-07-27T23:29Z — fix boundary en PR #42 (`v1.2.1`):
`SupportsSubscriptions`, `resolveInstallFile` accionable, contrato genérico en
`src/Domain/Payments/`.

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| Spec archivado `2026-07-27-stripe-subscription-boundary-design.md` | Contrato Framework genérico | Portal bump `composer.lock` a `^1.2` antes de habilitar checkout |
| Reglas negocio Marketing (ConfirmarPago, RecoverMembership) — **Portal** `app/Application/Marketing/` | Fuera de alcance Framework | Validar en `Lebytek_Portal` post-bump |
| `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` en `.env.example` harness | Gate OFF por defecto | Mantener OFF hasta QA Portal con lock actualizado |

**Este spec/deuda no toca** `src/Domain/Payments/` ni código Marketing Portal.

### D9 — Bootstrap marketing / schema (#23) — cerrado, re-scopeado

Issue **#23** **CLOSED** 2026-07-27T19:38Z — re-scopeado a
[`Lebytek_Portal#4`](https://github.com/Parzival2103/Lebytek_Portal/issues/4)
(reconciliation runner).

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `main`: sin `database/schema/modules/marketing.sql` (`FrameworkRootNotPortalTest`) | Marketing no vive en Framework | Ninguna acción marketing en `src/` |
| Portal: `migrate-marketing.php` exit 2 `SCHEMA_RECONCILIATION_REQUIRED` (auditoría 2026-07-27) | Fail-closed deliberado | Resolver en Portal #4, no Framework |
| Manifiesto migraciones / colisión timestamps — histórico feature | Solo relevante si alguien clona feature | No reintroducir marketing en Framework `main` |

### D10 — Spec archivado 2026-07-27 con premisa VPS obsoleta

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| `docs/archive/superpowers/specs/2026-07-27-audit-harness-portal-env-purge-design.md` — banner corrección cutover | D1–D2 del spec siguen válidos; D4–D6 sobre VPS scripts **obsoletos** | Usar spec solo para purga env + health; ignorar Fase 2 banner en scripts borrados |
| PR #34 (pase UX spec 2026-07-27) **CLOSED** sin merge | Deuda UX documentada pero no mergeada | Reabrir o nuevo PR desde items D1–D3 vigentes |

### D11 — Pipeline specs / auditoría (meta-deuda)

| Evidencia | Impacto | Acción requerida |
|-----------|---------|------------------|
| SKIP 2026-07-28 — sin PR audit elegible | Sin spec del día; deuda carry-forward | AUTOMATION-01 debe crear PR draft 2026-07-29 |
| Specs audit archivados en PR #43 | Historial en `docs/archive/superpowers/` | Spec activo = skeleton staging 2026-07-26 |
| Ramas `automation/audit-spec-*` huérfanas | Trazabilidad fragmentada | Merge o referencia cruzada en README superpowers |

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| **Pipeline skip** — sin auditoría diaria 2026-07-28 | Media | Verificar AUTOMATION-01 cron 06:00 UTC; no inventar spec |
| **M2 drift `.env.example`** — vars Portal en harness root | Media | Purga + test gate D2; spec archivado 2026-07-27 §Fase 1 |
| **VPS_CHECKLIST obsoleto** — feature branch, scripts borrados | Media | Actualizar según `docs/ENVIRONMENTS.md`; riesgo confusión ops |
| **Stripe post-#21** — Portal sin bump lock a v1.2.x | Media | Gate `PAYMENTS_SUBSCRIPTION_CHECKOUT=false` hasta QA Portal |
| **Schema Portal #4** — reconciliation runner pendiente | Alta (Portal) | Fail-closed en `migrate-marketing.php`; no Framework |
| **Skeleton staging no implementado** | Media | Host copia Portal sin BD aislada; plan 2026-07-26 Tasks 1–10 |
| **Sin CI PHP** | Media | Regresiones M2/reintroducción marketing no detectadas en PR |
| **Monitoreo `/api/ping` como health** | Baja–Media | 302 a login = falso healthy; implementar `/api/health` o doc |
| **Legacy branch sin archivar** | Baja | Tag antes de borrar (plan Task 10); scripts destructivos ya eliminados |
| **composer-setup §6 pin feature** | Media | Nuevo consumidor podría seguir instrucciones obsoletas |

---

## Criterios de aceptación

### Pipeline (próxima corrida AUTOMATION-02)

- [ ] PR draft `docs(audit):` abierto sobre `main`, mergeable, diff = un solo reporte en `docs/audits/`.
- [ ] Se genera `docs/superpowers/specs/2026-07-29-*-design.md` en rama `automation/audit-spec-2026-07-29`.
- [ ] Spec separa requisitos Framework vs Portal; incluye tests fail-first y semver boundary si aplica.

### Deuda carry-forward (desde skip 2026-07-28 — no bloquean skip)

- [ ] **D1:** Root `.env.example` sin keys activas `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*`.
- [ ] **D2:** `FrameworkRootNotPortalTest` falla si se reintroduce drift en root `.env.example`.
- [ ] **D3:** `GET /api/health` público o doc explícita de que `/api/ping` no es health externo.
- [ ] **D5:** `VPS_CHECKLIST.md` refleja Portal `main` + composer; sin referencias a `vps-deploy-*.sh`.
- [ ] **D5:** `docs/composer-setup.md` §6 actualizado (sin pin feature como vigente).
- [ ] **D6:** Al menos Task 1–2 del plan skeleton completados (`publish-skeleton.sh`, `SkeletonPurityTest` VCS).
- [ ] **D7:** Workflow CI con `composer install && php tests/run.php`.
- [ ] **D8:** Portal bump lock + QA antes de `PAYMENTS_SUBSCRIPTION_CHECKOUT=true`.
- [ ] **D9:** Cierre verificado de `Lebytek_Portal#4` (schema reconciliation).

### Gates resueltos (verificación 2026-07-28)

- [x] Cutover VPS — Portal `main` @ Framework v1.2.1 (auditoría 2026-07-27 SSH).
- [x] `INSTALL_TOKEN` en root + skeleton `.env.example` (PR #37).
- [x] Scripts `vps-deploy-*.sh` eliminados + `DeployScriptsRemovedTest` (PR #36).
- [x] Issues #21 y #23 cerrados con re-scope documentado.

---

## No-alcance

- Implementación de código en `app/` o `src/` en esta automatización.
- Cutover VPS, deploy, SSH, DNS, migraciones prod, `.env`/secretos en servidores.
- Crear design spec inventado para 2026-07-28 (pipeline SKIP legítimo).
- Abrir, cerrar o mergear PRs (spec, auditoría, implementación).
- Merge `feature/backoffice-api-integration` → `main`.
- Fixes Marketing/Stripe en Portal — consumidor vía `composer.lock`.
- Purga vars en `.env` real de operadores (solo plantilla `.env.example`).
- Desactivar RBAC, CSRF, rate limits, firmas webhook, ni tests de seguridad.
- Reimplementar lógica archivada del spec 2026-07-27 sobre scripts VPS borrados (Fase 2 deprecated).

---

## Próximo paso esperado

1. Verificar que AUTOMATION-01 (06:00 UTC) cree el PR draft de auditoría del
   2026-07-29 con un único reporte bajo `docs/audits/`.
2. Re-ejecutar AUTOMATION-02 sobre ese PR abierto y mergeable sobre `main`.
3. Priorizar deuda D1/D2 (purga env) y D5 (VPS_CHECKLIST) en PR Framework humano
   independiente del spec del día.

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | `deuda-tecnica-inventario` (contexto skip AUTOMATION-02 documentado arriba) |
| Repository | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| Inspected `origin/main` SHA | `e728474226a4d39ff6bc3b43ab3ab3edb4a77220` |
| Generated branch | `automation/audit-spec-2026-07-28` |
| UTC timestamp (skip) | 2026-07-28T13:35Z |
| UTC timestamp (deuda) | 2026-07-28T14:00Z |
| UTC timestamp (UX) | 2026-07-28T14:30Z |
| UX agent result | SKIP — sin spec del día |
| Final PR (UX) | *(none — skip legítimo)* |
| Source audit PR | *(none eligible)* |
| Spec referencia deuda | `docs/archive/superpowers/specs/2026-07-27-audit-harness-portal-env-purge-design.md` (D1–D3 vigentes) |
| Spec activo plataforma | `docs/superpowers/specs/2026-07-26-skeleton-package-staging-design.md` |
