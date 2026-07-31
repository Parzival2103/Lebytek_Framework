# Design: Alineación de documentación operativa post-FPS (M8 / D5–D12)

**Fecha:** 2026-07-31  
**Repo:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño (AUTOMATION-01) — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel B)

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `e19fa25c7c96560462f60c31b56b99c8d7eaf619` |
| SHA Portal inspeccionado | **No verificado** — `gh repo view Parzival2103/Lebytek_Portal` → HTTP 404; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `b6c37739983ded445259c038521861ffb5f253c8` @ `main` (sin delta desde 2026-07-27) |
| Rama generada | `automation/spec-2026-07-31` |
| Timestamp UTC | `2026-07-31T12:30:00Z` |
| Nivel de fuente | **B** — rama `origin/automation/audit-2026-07-31` @ `af0c5dfa5957558940cecef4c42ec91658819448`; diff único `docs/audits/2026-07-31-auditoria-tecnica-diaria.md`; ancestry limpia desde `origin/main`; **sin PR de auditoría abierto** (Nivel A: `gh pr list --search "docs(audit):" --base main` → vacío) |
| PR auditoría fuente | N/A (PR pendiente de AUTOMATION-03) |
| headRefOid fuente | `af0c5dfa5957558940cecef4c42ec91658819448` |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD ni del head de auditoría |

---

## Problema

La auditoría del 2026-07-31 confirma que `origin/main` @ `e19fa25` avanzó **6 commits docs-only** desde la auditoría anterior (PRs #47, #50–#54). **Cero cambios en `src/`**, rutas, SQL o skeleton funcional. M7 (cadena audit sin merge) quedó **resuelto** (#51 + #54). Release tip sigue en tag `v1.2.1` @ `fba3e03`.

El **hallazgo medio nuevo** es **M8** (elevado desde inventario D5): la documentación operativa que un mantenedor u operador sigue hoy **sigue apuntando al monolito legacy** y a scripts de deploy eliminados, contradiciendo la verdad canónica en `docs/ENVIRONMENTS.md`.

| ID | Archivo | Evidencia verificada | Impacto |
|----|---------|----------------------|---------|
| **M8 / D5** | `docs/integration/VPS_CHECKLIST.md` | L13: `vps-deploy-lebytek-com.sh`; L89: `Branch: feature/backoffice-api-integration (until merge)`; L93: «Clone/pull Lebytek_Framework feature branch» | Operador despliega monorepo obsoleto o ejecuta script inexistente |
| **D10** | `docs/composer-setup.md` | §6 L121–128: `"lebytek/framework": "dev-feature/backoffice-api-integration"` con texto «Mientras la integración api no esté en main» | Consumidor nuevo instala paquete pre-FPS desde branch congelada |
| **D11** | `docs/integration/VPS_CHECKLIST.md` | Sección `lebytek.com` trata Framework como app desplegable | Confunde package source con app Portal |
| **D12** | `docs/integration/lebytek-implementation-real.md` | L3: «Guía operativa para Lebytek_Framework (lebytek.com VPS). Repo: branch feature/backoffice-api-integration» | Runbook E2E desalineado de FPS |
| **D12** | `docs/integration/role-delegation-lebytek-api.md` | L195: «Repo back-office: Parzival2103/Lebytek_Framework, branch feature/backoffice-api-integration» | Delegación de roles apunta al repo equivocado para producción |

**Contexto positivo:** `scripts/vps-deploy-*.sh` **no existen** (PR #36); `DeployScriptsRemovedTest` **PASS**; tag `archive/backoffice-api-integration` @ `4789f95` congela el legacy; `docs/ENVIRONMENTS.md` ya define el mapa correcto (Portal `main` + `composer.lock`, Framework como paquete semver).

**Gap de tests:** ningún test Docs valida que los runbooks **operativos** (fuera de registro histórico) no referencien la rama congelada ni scripts eliminados. `DeployScriptsRemovedTest` cubre `scripts/*.sh` pero **no** `docs/integration/` ni `docs/composer-setup.md`.

### Deuda arrastrada relevante (contexto, no alcance principal)

| ID | Tema | Owner | Spec/plan existente | Relación con M8 |
|----|------|-------|---------------------|-----------------|
| M1 | `config/app.php` version `1.0.0` vs tag `v1.2.1` | Framework | Spec `2026-07-29-audit-config-version-semver-sync-design.md` — **sin implementar** | Independiente; Fase 2b del spec 2026-07-29 **se desacopla** en este spec |
| M2 | Root `.env.example` vars Portal/Marketing | Framework harness | Spec 2026-07-29 Fase 2 | Independiente |
| M6 | Portal SHA no inspeccionable | Ops | Bloqueador entorno | Impide verificar que prod sigue docs corregidos |
| C2 ops | Stripe QA Portal | Portal | Framework v1.2.1 publicado | No verificable aquí |
| D6 | skeleton.lebytek.com pendiente | Framework/Ops | Plan `2026-07-26-skeleton-package-staging.md` | Complementario — ENVIRONMENTS.md ya lo documenta |

Este spec **no implementa** M1/M2 semver ni purge `.env.example`; se centra en M8 y cierra D5/D10/D11/D12 en rutas operativas.

---

## Comportamiento esperado

Tras implementar este diseño:

1. Un operador que lea `docs/composer-setup.md`, `docs/integration/VPS_CHECKLIST.md`, `lebytek-implementation-real.md` o `role-delegation-lebytek-api.md` obtiene instrucciones alineadas con `docs/ENVIRONMENTS.md`: **lebytek.com / waapi.lebytek.com → `Lebytek_Portal` @ `main` + `composer.lock`**; **Framework → paquete Composer semver**, no repo clonado como sitio.
2. **Ninguna** instrucción operativa vigente referencia `vps-deploy-*.sh` (eliminados PR #36) ni `dev-feature/backoffice-api-integration` como target de deploy o Composer.
3. Referencias históricas a la rama congelada permanecen **solo** bajo rutas de registro (`docs/superpowers/`, `docs/automation/`, `docs/CUTOVER-PORTAL.md`, tag `archive/backoffice-api-integration`) — no como guía de acción.
4. Un test Docs (`OpsDocsFpsAlignmentTest`) **falla** antes del fix si algún runbook operativo contiene cadenas prohibidas; **pasa** tras el PR de implementación.
5. Operadores humanos en VPS **no** reciben instrucciones de redeploy desde este spec — sólo documentación en repo.

---

## Alcance

### Framework — documentación operativa (`Parzival2103/Lebytek_Framework`, base `main`)

| # | Entregable | Ruta |
|---|------------|------|
| D1 | Reescribir §6 `docs/composer-setup.md` — pin semver (`^1.2` o tag concreto) en lugar de `dev-feature/backoffice-api-integration`; enlace a `ENVIRONMENTS.md` | `docs/` |
| D2 | Actualizar sección `lebytek.com` en `docs/integration/VPS_CHECKLIST.md` — repo `Lebytek_Portal`, rama `main`, deploy manual/git pull (sin scripts eliminados); marcar checks E2E 2026-07-01 como históricos | `docs/integration/` |
| D3 | Corregir cabecera y referencias de deploy en `docs/integration/lebytek-implementation-real.md` — target Portal, no Framework feature branch | `docs/integration/` |
| D4 | Corregir L195 (y contexto adyacente si aplica) en `docs/integration/role-delegation-lebytek-api.md` — back-office = Portal | `docs/integration/` |
| D5 | Añadir nota en `docs/core/seguridad_secretos_deploy.md` (si existe sección deploy) o párrafo corto: distinguir secretos Portal vs package source; enlace `ENVIRONMENTS.md` | `docs/core/` |
| T1 | Crear `tests/Docs/OpsDocsFpsAlignmentTest.php` — gate runbooks operativos | `tests/Docs/` |

### Rutas operativas bajo gate T1 (lista cerrada)

```
docs/composer-setup.md
docs/integration/VPS_CHECKLIST.md
docs/integration/lebytek-implementation-real.md
docs/integration/role-delegation-lebytek-api.md
```

### Staging / implementación (AUTOMATION-04+, no producción)

- Rama de implementación sugerida: `feat/ops-docs-fps-alignment` desde `origin/main`.
- PR título sugerido: `docs(ops): align integration runbooks with FPS environments`.
- Archivos tocados: rutas D1–D5 + T1 únicamente.
- Validación local: `php tests/run.php Docs` y `php tests/run.php OpsDocsFpsAlignment` (o suite Docs completa).

### Ops — verificación manual post-merge (fuera de corrida desatendida)

| # | Acción | Owner |
|---|--------|-------|
| O1 | Confirmar en VPS que `lebytek.com` corre `Lebytek_Portal` @ `main`, no clone de Framework | Operador con SSH |
| O2 | Conceder lectura `Lebytek_Portal` al token automation (M6) | Ops |
| O3 | Verificar `composer.lock` Portal ≥ `v1.2.1` antes de QA Stripe | Portal maintainer |

---

## No-alcance

- Implementación M1 (semver UI en `config/app.php`), M2 (purge root `.env.example`), M3–M5 (RBAC/router/API/permisos).
- Cambios en `src/`, `skeleton/`, `routes/`, `database/`, `config/app.php` o `.env.example`.
- Merge de `feature/backoffice-api-integration` → `main` (legacy archivado @ `4789f95` — evidencia histórica).
- Deploy, SSH, scp, migraciones producción, cambios `.env` en VPS.
- Edición masiva de referencias históricas en `docs/superpowers/plans/`, `docs/superpowers/specs/`, `docs/CUTOVER-PORTAL.md`, `docs/automation/` — registro de migración, no instrucciones vigentes.
- Marketing, membresías, checkout Portal (`Parzival2103/Lebytek_Portal`) — negocio fuera de este repo.
- Publicación `skeleton.lebytek.com` (plan 2026-07-26) — spec hermano.
- Cierre/merge del PR de auditoría 2026-07-31 (AUTOMATION-03).
- Apertura del PR de este spec (AUTOMATION-03).
- Configuración CI GitHub Actions (D7 inventario).

---

## Ownership map

| Requisito | Repositorio | Rama base | Consumidor |
|-----------|-------------|-----------|------------|
| Runbooks integration/composer | `Lebytek_Framework` | `main` | Operadores, maintainers Framework |
| Verdad canónica entornos | `Lebytek_Framework` | `main` (`docs/ENVIRONMENTS.md`) | Todos los runbooks |
| Test gate ops docs | `Lebytek_Framework` | `main` | `php tests/run.php Docs` |
| App desplegable lebytek.com | `Lebytek_Portal` | `main` | Ops VPS — **no verificado esta corrida** |
| Paquete plataforma | `Lebytek_Framework` | tag semver `v1.2.1` | Portal vía `composer.lock` |
| Legacy monolito | tag `archive/backoffice-api-integration` | — | Solo evidencia histórica |
| Token gh Portal | Cursor cloud / GitHub | — | Automations M6 |

### Separación Framework vs Portal

| Capacidad | Owner | Contrato público |
|-----------|-------|------------------|
| Instrucciones Composer consumidor | Framework docs | Semver tag en `composer.json`; **no** branch dev legacy |
| Deploy lebytek.com / waapi | Portal repo + ops | Git pull Portal `main`; lock commiteado |
| Integración api ↔ back-office | Portal + WhatsApi | URLs/tokens en `.env` Portal — **no** en package source harness |
| Scripts deploy VPS | **Eliminados** (PR #36) | `DeployScriptsRemovedTest` — no reintroducir |

**Semver / release:** este spec es **docs-only** — **no** requiere tag semver nuevo de `lebytek/framework` ni bump en Portal. Portal ya puede consumir `v1.2.1`; la corrección es documental.

---

## Enfoques considerados

### Enfoque A — PR quirúrgico en 4 runbooks + test gate (recomendado)

Actualizar únicamente las rutas operativas listadas en § Alcance D1–D4, añadir T1 con lista cerrada de paths, una línea de enlace a `ENVIRONMENTS.md` donde falte contexto.

| Ventaja | Desventaja |
|---------|------------|
| Diff pequeño, revisable en móvil | No barre otros docs integration menores |
| Alineado con plan 2026-07-26 Task 9 (históricos excluidos) | Requiere disciplina al añadir nuevos runbooks |
| Test falla rápido en CI local | — |

### Enfoque B — Deprecar `docs/integration/*` y centralizar todo en ENVIRONMENTS.md

Marcar integration docs como «deprecated» y redirigir lectores solo a ENVIRONMENTS + ARCHITECTURE-CONSUMER.

| Ventaja | Desventaja |
|---------|------------|
| Una sola fuente de verdad | Rompe enlaces profundos en specs/issues históricos |
| Menos mantenimiento futuro | Pérdida de detalle E2E (provisioning, tokens api) aún útil |

### Enfoque C — Barrido `git grep` en todo `docs/` excluyendo nada

Eliminar toda mención de `feature/backoffice-api-integration` incluyendo superpowers y CUTOVER.

| Ventaja | Desventaja |
|---------|------------|
| Cero referencias en repo | Borra registro histórico de migración FPS |
| — | Viola política documentada en plan 2026-07-26 y `FpsPublicationReadinessTest` |

**Recomendación: Enfoque A.** Cierra M8 con riesgo mínimo, respeta registro histórico, y el test gate evita regresión sin sobrescan.

---

## Diseño técnico

### D1 — `docs/composer-setup.md` §6

**Estado actual:** sección «Pin a branch de feature (desarrollo)» con `dev-feature/backoffice-api-integration`.

**Estado objetivo:**

- Renombrar sección a «Versión semver en consumidores».
- Ejemplo canónico:

```json
"require": {
    "lebytek/framework": "^1.2"
}
```

- Nota: para desarrollo local del paquete, mantener bloque `path` repository (ya presente L131–137) — **no** branch VCS del monolito.
- Enlace: «Ver mapa de entornos en `docs/ENVIRONMENTS.md`».

### D2 — `docs/integration/VPS_CHECKLIST.md`

**Cambios:**

| Ubicación | De | A |
|-----------|----|----|
| L13 (E2E histórico) | Referencia activa a `vps-deploy-lebytek-com.sh` | Nota «histórico 2026-07-01 — script eliminado PR #36; deploy actual vía Portal git pull» |
| L85–93 sección lebytek.com | Framework feature branch | `Parzival2103/Lebytek_Portal`, rama `main`, ruta VPS sin cambio |
| L93 | Clone Lebytek_Framework | `git pull origin main` en checkout Portal existente |
| Deploy checks | `DEPLOY_DONE health_rc=0` vía script | Checklist manual: `curl -sf https://lebytek.com/up` o ruta health documentada en Portal |

Preservar sección `api.lebytek.com` (WhatsApiLebytek) — sigue vigente.

### D3 — `lebytek-implementation-real.md`

- L3: «Guía operativa para **Lebytek_Portal** (`lebytek.com` VPS). Repo: `Parzival2103/Lebytek_Portal`, rama `main`.»
- Revisar secciones que asuman árbol `app/Domain/Marketing` en Framework — marcar «vive en Portal» donde aparezca path Framework.
- Banner corto al inicio: «Package source: `lebytek/framework` vía Composer lock — no clonar Lebytek_Framework como sitio web.»

### D4 — `role-delegation-lebytek-api.md`

- L195: «Repo back-office: `Parzival2103/Lebytek_Portal`, branch `main`.»
- Sin cambiar contrato api (WhatsApi) — solo target de consumidor.

### D5 — `docs/core/seguridad_secretos_deploy.md`

- Párrafo: secretos de deploy Portal (`.env` en VPS lebytek.com) ≠ secretos del harness Framework en este repo.
- Enlace cruzado `ENVIRONMENTS.md` § producción.

### T1 — Test gate (TDD)

**Archivo:** `tests/Docs/OpsDocsFpsAlignmentTest.php`

**Comportamiento:**

```php
// Pseudocódigo — implementación exacta en AUTOMATION-04
$operationalPaths = [
    'docs/composer-setup.md',
    'docs/integration/VPS_CHECKLIST.md',
    'docs/integration/lebytek-implementation-real.md',
    'docs/integration/role-delegation-lebytek-api.md',
];
$forbidden = [
    'dev-feature/backoffice-api-integration',
    'feature/backoffice-api-integration',
    'vps-deploy-lebytek-com.sh',
    'vps-deploy-waapi.sh',
    'vps-deploy-skeleton.sh',
];
foreach ($operationalPaths as $rel) {
    $src = file_get_contents($root . '/' . $rel);
    foreach ($forbidden as $needle) {
        assert_true(!str_contains($src, $needle), "$rel must not reference $needle");
    }
}
```

**Estado pre-implementación:** test **debe fallar** — grep confirma cadenas presentes en los cuatro archivos (2026-07-31).

**Registro en `tests/run.php`:** añadir grupo `OpsDocsFpsAlignment` o incluir en suite `Docs` existente.

---

## Dependencias y compatibilidad

| Dependencia | Estado | Notas |
|-------------|--------|-------|
| `docs/ENVIRONMENTS.md` en `main` | ✅ Presente, canónico | Fuente de verdad para redacción D1–D4 |
| PR #36 scripts eliminados | ✅ Mergeado | Precondición cumplida |
| Tag `archive/backoffice-api-integration` | ✅ Publicado | Referencia histórica permitida fuera de gate |
| Spec 2026-07-29 Fase 2b | ⏳ Supersedido parcialmente | Este spec implementa 2b de forma independiente |
| Portal `main` SHA | ❌ No verificado | O1/O2 pendientes ops |
| `FpsPublicationReadinessTest` | ✅ PASS | Exige cadena en CUTOVER — no tocar |
| Semver Framework `v1.2.1` | ✅ Publicado | Sin nuevo release por este spec |

**Migración segura:**

| Escenario | Acción |
|-----------|--------|
| Operador con runbook impreso obsoleto | Tras merge PR docs, diff visible en GitHub; opcional anuncio interno |
| VPS ya en Portal `main` | Sin cambio runtime — solo docs |
| Consumidor nuevo leyendo composer-setup | Pasa a instalar semver — comportamiento deseado |
| Base nueva desde skeleton | Sin impacto — skeleton ya limpio |

**Compatibilidad hacia atrás:** eliminar pin legacy **no** rompe consumidores que ya usan semver en lock; solo corrige documentación errónea.

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Operador sigue runbook cacheado offline | Media | Banner «actualizado 2026-07-31» en cabecera VPS_CHECKLIST; test gate |
| Confundir edición Portal con Framework | Baja | Ownership map + ENVIRONMENTS.md |
| Test demasiado amplio (falsos positivos en históricos) | Baja | Lista cerrada de 4 paths operativos |
| Portal prod desalineado de docs (lock antiguo) | Media | O3 manual — **no verificado** |
| Regresión: reintroducir pin legacy en composer-setup | Media | T1 en suite Docs |
| Stripe habilitado sin QA | Alta (Portal) | Fuera de alcance — C2 ops |

---

## Rollback

| Acción | Rollback |
|--------|----------|
| PR docs D1–D5 | `git revert` del merge commit — restaura textos legacy (no recomendado salvo error factual) |
| Test T1 | Revert incluye test; suite Docs vuelve a conteo anterior |

**Producción:** ninguna operación VPS automatizada en esta corrida. Rollback operativo = no aplicar cambios en servidores (docs-only).

---

## Criterios de aceptación

### Documentación (Framework)

- [ ] `docs/composer-setup.md` §6 no contiene `dev-feature/backoffice-api-integration`; referencia semver `^1.2` o equivalente documentado.
- [ ] `docs/integration/VPS_CHECKLIST.md` sección lebytek.com apunta a `Lebytek_Portal` @ `main`; sin `vps-deploy-*.sh` como instrucción vigente.
- [ ] `docs/integration/lebytek-implementation-real.md` L3+ identifica Portal como app desplegable.
- [ ] `docs/integration/role-delegation-lebytek-api.md` L195 identifica Portal @ `main`.
- [ ] `docs/core/seguridad_secretos_deploy.md` distingue deploy Portal vs harness Framework (si archivo existe y es legible).

### Tests (Framework)

- [ ] `tests/Docs/OpsDocsFpsAlignmentTest.php` existe.
- [ ] Antes del fix de docs, el test **falla** con mensaje que cita archivo y cadena prohibida.
- [ ] Tras el fix, `php tests/run.php Docs` pasa (18+ tests, conteo actual + T1).
- [ ] `DeployScriptsRemovedTest` y `FpsPublicationReadinessTest` siguen verdes sin modificación.

### Proceso

- [ ] Diff del PR de implementación toca **solo** rutas D1–D5 + T1 (+ registro en `tests/run.php` si aplica).
- [ ] Ningún commit en rama de implementación hereda `automation/audit-*` ni legacy ancestry.

### Verificación cross-repo (manual — no verificado esta corrida)

- [ ] Portal `main` SHA confirmado por operador con acceso gh/SSH.
- [ ] `composer.lock` Portal referencia `lebytek/framework` ≥ `1.2.1`.
- [ ] Token automation lee Portal (cierra M6).

### Fuera de criterios de este spec

- [ ] M1 semver UI — spec 2026-07-29 Fase 1.
- [ ] M2 purge `.env.example` — spec 2026-07-29 Fase 2.
- [ ] M3–M5 RBAC/API/permisos — backlog.
- [ ] skeleton.lebytek.com deploy — plan 2026-07-26.
- [ ] CI GitHub Actions — D7.

---

## Issues abiertos (contexto de riesgo)

| Repo | Issues abiertos | Notas |
|------|-----------------|-------|
| `Lebytek_Framework` | **0** (`gh issue list --state open` → vacío) | Sin tracking GitHub para M8; deuda en inventario D5–D12 |
| `Lebytek_Portal` | **No verificado** — repo inaccessible vía `gh` | C2 ops Stripe QA pendiente según auditorías previas |

---

*Design-only. Ningún archivo de código, config, rutas, migraciones ni tests de producto modificado en la corrida AUTOMATION-01.*
