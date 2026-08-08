# Design: Release semver tag y cadena consumidor (REL-C1)

**Fecha:** 2026-08-08  
**Repo spec:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel B)

**Auditoría fuente:** `docs/audits/2026-08-08-auditoria-tecnica-diaria.md` (rama `automation/audit-2026-08-08` @ `bf4b7fccf2a93c4b65394638c8df5cc68f30bbe2`; **PR de auditoría ausente**)  
**Hallazgo principal:** **REL-C1** — el tip de `main` declara semver **`1.2.7`** (trío sincronizado) pero el **último tag publicado es `v1.2.3`** @ `041e402`. Los fixes AuthZ CRUD-C1/C2/C5 (#95 @ `64a6877`) y states CRUD-C3 (#100 @ `60477dc`) están mergeados en tip pero **no son instalables** vía Composer tag + `composer.lock` en Portal/CRM/skeleton.

**Specs/planes relacionados (no duplicar):**

- Invoicing scaffold: `docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md` · plan `2026-08-07-invoicing-facturapi.md` (implementado en tip)
- Invoicing hardening INV-E1/E2: plan `docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md` (**0/50** tareas) — **bloquea** habilitación Facturapi; **no** bloquea tag de AuthZ/states
- CRUD RBAC router M3: `docs/superpowers/specs/2026-08-06-audit-crud-rbac-router-design.md` · plan `2026-08-06-audit-crud-rbac-router.md` (**0/5**; target obsoleto `1.2.5`)
- API health M4: `docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md` · plan `2026-08-05-audit-api-health-public.md` (**0/5**; target obsoleto `1.2.4`)
- Portal afterListRows: `docs/superpowers/specs/2026-08-03-audit-mkt-leads-after-list-rows-design.md` · plan `2026-08-02-audit-mkt-leads-after-list-rows.md` (**0/5**)
- Release checklist operativo: `docs/core/despliegue-y-versionado.md` § «Checklist release de plataforma»
- Frontera FPS: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `89a1a8901d3f868ac869a60e3c6a0f1d34f73136` |
| SHA Portal inspeccionado | **No verificado** — `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404; `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository». Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde auditoría 2026-08-02) |
| Rama generada | `automation/spec-2026-08-08` |
| Timestamp UTC | trigger cron `2026-08-08T12:10:00Z` / corrida agente `2026-08-08T12:10:00Z` |
| Nivel de fuente | **B** — rama `origin/automation/audit-2026-08-08` @ `bf4b7fc`; **no** hubo PR abierto `docs(audit):` elegible (Nivel A vacío). Verificaciones: `merge-base --is-ancestor origin/main bf4b7fc` → exit 0; diff `origin/main...bf4b7fc` → único archivo `docs/audits/2026-08-08-auditoria-tecnica-diaria.md`; ningún commit legacy ancestro del head audit. |
| PR auditoría fuente | **Ausente** — rama audit sin PR al momento de la corrida |
| headRefOid fuente | `bf4b7fccf2a93c4b65394638c8df5cc68f30bbe2` (rama audit; no heredada) |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD ni del head audit |
| Issues abiertos Framework | **0** (`gh issue list --repo Parzival2103/Lebytek_Framework --state open` → vacío) |
| Issues abiertos Portal | **No verificable** — mismo bloqueador gh 404 (M6) |
| PRs abiertos Framework (contexto) | **0** |

---

## Problema

La auditoría del 2026-08-08 documenta una **rotura en la cadena release → consumidor**: el código mergeado en `main` ya incluye parches de seguridad y UX del CRUD Engine (AuthZ multi-canal y lock de columna states), pero la **publicación semver del paquete Composer quedó atrás**.

**Evidencia verificada en tip `main` @ `89a1a89`:**

| Comprobación | Resultado |
|--------------|-----------|
| `composer.json` `"version"` | `1.2.7` |
| `config/app.php` `'version'` | `1.2.7` |
| `skeleton/config/app.php` `'version'` | `1.2.7` |
| `git tag -l 'v1.2.*'` | `v1.2.0` … `v1.2.3` únicamente |
| `git tag --contains 64a6877` (#95 AuthZ) | **vacío** — commit no alcanzable por tag |
| `git tag --contains 60477dc` (#100 states) | **vacío** |
| Último tag publicado | `v1.2.3` @ `041e402` |
| CI tip | **success** @ `89a1a89` (run `31235093747`) |
| `php tests/run.php Docs` (incl. `PlatformVersionSemverTest`) | **33/33 PASS** en auditoría — valida trío interno, **no** existencia de tag Git |
| Test gate tag publicado | **Ausente** — `rg 'ReleaseTag\|tag.*published' tests/` → 0 |

**Consecuencia operativa:**

1. **Consumidores atrapados en `v1.2.3`** — Portal/CRM/skeleton que instalan `lebytek/framework` por tag semver + lock **no reciben** fixes CRUD-C1/C2/C5/C3 aunque el código exista en `main`.
2. **Product truth rota** — operadores ven `1.2.7` en docs/tip pero Composer resuelve `v1.2.3`; smoke de release no puede validarse end-to-end.
3. **Planes M3/M4 desalineados** — reservaban tags `v1.2.4`/`v1.2.5` aún **0/5**; los bumps AuthZ/states ya consumieron números declarados (`1.2.6`, `1.2.7`) sin tag correspondiente.
4. **Riesgo de habilitación prematura de Invoicing** — tip incluye vertical Facturapi (#99) pero auditorías #101 elevan INV-E1/E2; el tag de release **no debe** interpretarse como «listo para producción fiscal» (vertical OFF by default).

**Deuda carry-forward registrada (fuera de alcance inmediato de este spec salvo coordinación semver):**

| ID | Hallazgo | Estado tip | Owner |
|----|----------|------------|-------|
| CRUD-C4 | Transitions sin CAS/TOCTOU | Abierto | Framework |
| CRUD-C6 | Uploads sin allowlist obligatoria + path sin normalizar | Abierto | Framework |
| INV-E1/E2 | Doble timbrado / id irrecuperable | Abierto; plan hardening 0/50 | Framework |
| M3 | CRUD/calendario sin `RbacMiddleware` router | Abierto; plan 0/5 | Framework |
| M4 | `/api/health` público ausente | Abierto; plan 0/5 | Framework |
| M5 | `permisos.gestionar` seeds | Abierto | Framework |
| M6 | Portal gh 404 | Abierto (entorno) | Ops |
| M10 | Hueco audits 2026-08-03..05 | Abierto (proceso) | Ops |
| D6 | `skeleton.lebytek.com` pendiente | Abierto | Ops |

---

## Brainstorm y recomendación de diseño

### Contexto, propósito, restricciones y criterios de éxito

- **Propósito:** restablecer la cadena **código mergeado → tag Git `vX.Y.Z` → `composer update lebytek/framework` → bump lock consumidor**, de forma que los fixes AuthZ/states sean instalables sin parchear `vendor/` ni checkout de rama.
- **Restricciones:** legacy `archive/backoffice-api-integration` sólo evidencia histórica; operaciones VPS/producción fuera de automation desatendida; Portal no inspeccionable vía gh (M6); Invoicing sigue OFF y hardening 0/50; no mergear legacy → `main`; semver del paquete es contrato público — consumidores en PHP 8.1 deben saber que `1.2.7` exige PHP ≥8.2 (Facturapi SDK).
- **Éxito Framework:** tag `v1.2.7` (o política documentada equivalente) publicado desde commit verificado; test gate TDD que falle pre-tag y pase post-tag; checklist release ejecutado; notas de release documentan contenido AuthZ/states/invoicing-scaffold-OFF.
- **Éxito consumidor (post-tag, manual):** bump `composer.lock` a tag publicado; smoke AuthZ/states en staging; **no** habilitar `vertical.invoicing` / `FACTURAPI_ENABLED` hasta tag post-hardening futuro.
- **Éxito operativo:** planes M3/M4 retarget a `1.2.8+`; reservas `1.2.4`/`1.2.5` marcadas como saltadas en docs de plan.

### Enfoques evaluados

| Enfoque | Descripción | Pros | Contras |
|---------|-------------|------|---------|
| **A — Tag único `v1.2.7` en tip `89a1a89`** | Publicar un tag desde el commit actual que ya declara `1.2.7`; documentar skip explícito de `v1.2.4`–`v1.2.6` | Alineado con trío semver actual; un solo bump consumidor; incluye invoicing scaffold OFF + docs hardening | Saltos numéricos; consumidores no pueden tomar sólo AuthZ sin invoicing (mismo tag) |
| **B — Tags históricos `v1.2.6` @ #95 + `v1.2.7` @ #100** | Retro-tag en commits de merge individuales | Trazabilidad commit↔tag | Tip actual (`89a1a89`) ≠ `60477dc`; requiere tags adicionales o mover tip semver; invoicing (#99) quedaría fuera de `v1.2.7` o exige `v1.2.8` inmediato — confuso |
| **C — Bajar tip semver a `1.2.3` y re-release** | Revertir bumps en `composer.json`/configs | Evita saltos | **Rechazado** — regresión semver; niega trabajo mergeado; rompe `PlatformVersionSemverTest` y honestidad del changelog |

**Recomendación:** **A** — publicar **`v1.2.7`** desde tip `89a1a89` (o el commit release dedicado si el operador prefiere commit vacío de release notes). Documentar en CHANGELOG/release notes que **`v1.2.4` y `v1.2.5` no se publicaron** (planes M4/M3 no implementados); **`v1.2.6` no tiene tag** (bump intermedio absorbido en historial de commits #95). **Rechazar C**. **Rechazar B** como camino principal salvo necesidad audit forense excepcional — duplica trabajo consumidor.

### Esbozo del diseño

```
main tip (89a1a89, declara 1.2.7)
  │
  ├─► [Implementación] ReleaseTagPublishedTest (TDD gate)
  ├─► [Implementación] Ejecutar checklist § despliegue-y-versionado
  ├─► [Ops humano/staging] git tag v1.2.7 && git push origin v1.2.7
  │
  └─► Consumidores (Portal/CRM/skeleton)
        composer require lebytek/framework:1.2.7  (o update lock)
        smoke: CRUD AuthZ + states form lock
        NO habilitar invoicing hasta tag post-hardening
```

**Contenido del release `v1.2.7` (evidencia commits):**

| Área | PR / commit | Notas consumidor |
|------|-------------|------------------|
| AuthZ CRUD C1/C2/C5 | #95 @ `64a6877` | Fix IDOR scope_handler, acciones fail-closed, Reportes `{prefix}.ver` |
| States form C3 | #100 @ `60477dc` | Lock columna states, allowlist select, sin demo toggle |
| Invoicing scaffold | #99 @ `21edf26` | Vertical **OFF**; incluye SQL `inv_*`; PHP ≥8.2 |
| Docs hardening | #101, #103 | Plan 0/50 — **no** implica prod fiscal |

---

## Comportamiento esperado

### Framework (post-implementación del plan derivado)

1. Existe tag anotado **`v1.2.7`** cuyo commit es ancestro de (o igual a) tip `main` y **contiene** `64a6877` y `60477dc`.
2. El valor `"version"` / `'version'` en el trío semver del commit taggeado es **`1.2.7`** (sin prefijo `v`).
3. `ReleaseTagPublishedTest` pasa: para cada release publicado, `git tag -l "v{version}"` no está vacío y el tag apunta a un commit que contiene el tip semver declarado.
4. `PlatformVersionSemverTest` sigue pasando (trío sync).
5. Release notes / CHANGELOG entry listan AuthZ, states, invoicing-scaffold-OFF, PHP ≥8.2, y **explicitan** que Facturapi producción requiere hardening plan 0/50.
6. Planes M3/M4 actualizados: target semver **`1.2.8`** (o siguiente patch libre), no `1.2.4`/`1.2.5`.

### Consumidor Portal/CRM (post-tag, operación manual)

1. Bump `composer.lock` referencia **`lebytek/framework` v1.2.7** (tag, no `dev-main`).
2. Tras deploy, rutas CRUD aplican AuthZ multi-canal; formularios CRUD respetan lock de columna states.
3. `vertical.invoicing` permanece `false`; `FACTURAPI_ENABLED=false` en `.env`.
4. Si el consumidor sobrescribe `routes/web.php`, mergear cambios skeleton si aplica (AuthZ no requiere rutas nuevas — cambio en servicios).

### Staging / producción

- **Staging (Framework skeleton):** smoke post-tag en `skeleton.lebytek.com` cuando exista (D6) — fuera de automation.
- **Producción Portal:** bump lock + QA AuthZ + regresión CRUD — **fuera de automation**; requiere M6 resuelto o acceso SSH documentado.

---

## Alcance

| ID | Requisito | Owner | Repo / rama base |
|----|-----------|-------|------------------|
| F1 | Test gate `ReleaseTagPublishedTest` en suite Docs | Framework | `Lebytek_Framework` / `main` |
| F2 | Ejecutar checklist release § `despliegue-y-versionado.md` (trío sync ya en tip; validar tests) | Framework / release ops | idem |
| F3 | Publicar tag Git **`v1.2.7`** desde commit acordado | Framework / release ops | idem |
| F4 | Release notes: contenido #95/#100/#99, skip 1.2.4–1.2.6, PHP ≥8.2, invoicing OFF | Framework | idem |
| F5 | Retarget semver en planes M3/M4 a **`1.2.8+`** | Framework (docs plan) | idem |
| F6 | Documentar en `docs/PACKAGE-ROOT.md` o release notes la política «tip semver = tag publicado» | Framework | idem |
| P1 | Bump `composer.lock` Portal a `v1.2.7` | Portal | `Lebytek_Portal` / `main` — **no verificado** |
| P2 | Smoke AuthZ/states en staging Portal | Portal/Ops | idem — **no verificado** |
| O1 | Tag push + verificación Packagist/VCS (si aplica) | Ops | manual |
| O2 | Confirmar `/admin/sistema/estado` muestra `v1.2.7` post-deploy skeleton | Ops | manual |

---

## No-alcance

- Implementación CRUD-C4 (CAS/TOCTOU) o CRUD-C6 (uploads allowlist) — specs/planes futuros del programa CRUD #90.
- Ejecución plan hardening Facturapi (**0/50**) — spec/plan invoicing separado; **prohibido** habilitar Facturapi prod en esta release.
- Implementación M3 (CRUD RBAC router) o M4 (`/api/health`) — specs existentes; retarget semver sólo.
- Bump Portal lock, deploy VPS, SSH, `.env` producción — operaciones humanas (M6 bloquea verificación automation).
- Publicar tags retroactivos `v1.2.4`–`v1.2.6` salvo decisión excepcional documentada fuera de este spec.
- Merge `feature/backoffice-api-integration` → `main`.
- Cierre del PR/rama de auditoría — responsabilidad AUTOMATION-03.

---

## Ownership map

| Ámbito | Repo / rama | Responsabilidad |
|--------|-------------|-----------------|
| Paquete plataforma | `Lebytek_Framework` / `main` | Tag semver, test gate, release notes, retarget planes |
| Release ops | `Lebytek_Framework` tags | `git tag` + push; validar CI green en commit taggeado |
| App desplegable | `Lebytek_Portal` / `main` | Bump lock, smoke negocio, **no** habilitar invoicing |
| CRM | `Lebytek_CRM` (doc) | Bump lock equivalente — **no verificado** |
| API WhatsApp | `WhatsApiLebytek` / `main` | Sin dependencia directa del tag Framework en esta corrida |
| Legacy | `archive/backoffice-api-integration` @ `4789f95` | Histórico — no base |

**Dependencias cruzadas:**

- REL-C1 **desbloquea** consumo de AuthZ/states; **no** sustituye hardening invoicing.
- M3/M4 dependen de REL-C1 para numeración semver coherente (`1.2.8+`).
- Portal afterListRows (plan 0/5) ya compatible con Framework ≥ `v1.2.2`; confirmación lock bloqueada por M6.

---

## Dependencias y compatibilidad

### Contrato público Composer

- Consumidores instalan vía **`"lebytek/framework": "^1.2"`** o pin `"1.2.7"` — el tag **`v1.2.7`** debe existir en el remoto VCS.
- **No existe hoy** tag que exponga AuthZ #95 ni states #100 — **no asumir** APIs/fixes hasta publicación.
- PHP **`>=8.2`** en `composer.json` desde invoicing #99 — consumidores en 8.1 **deben** subir runtime antes de bump a `1.2.7` (documentar en release notes; ref. `docs/ARCHITECTURE-CONSUMER.md`).

### Migración segura

**Base nueva (skeleton / greenfield):**

1. Instalar tag `v1.2.7` vía Composer.
2. `php scripts/install.php` — incluye SQL invoicing si módulo activo; por defecto `vertical.invoicing=false`.
3. Verificar `PlatformVersionSemverTest` / estado sistema.

**Base Portal existente (en lock `v1.2.3` o anterior):**

1. Backup BD + `composer.lock`.
2. Bump a `1.2.7`; `composer update lebytek/framework --with-dependencies`.
3. Ejecutar `php scripts/install.php` (migraciones pendientes).
4. Smoke CRUD AuthZ (usuario sin `{prefix}.ver` → 403) y states (columna locked no editable).
5. **No** activar `vertical.invoicing` ni `FACTURAPI_ENABLED`.
6. Rollback: restaurar lock anterior + redeploy tag previo (ver § Rollback).

### Semver / release frontera

| Capacidad | Primer tag que la incluye | Consumidor |
|-----------|---------------------------|------------|
| AuthZ C1/C2/C5 | **`v1.2.7`** (propuesto) | Portal/CRM bump lock |
| States C3 | **`v1.2.7`** | idem |
| Invoicing scaffold OFF | **`v1.2.7`** | No activar hasta hardening |
| `/api/health` público (M4) | **`v1.2.8+`** (futuro) | Plan 2026-08-05 |
| CRUD RBAC router (M3) | **`v1.2.8+`** (futuro) | Plan 2026-08-06 |
| Facturapi prod-safe | **Tag TBD post-hardening** | Plan 0/50 |

---

## Tests (TDD — fallar antes de implementar)

| Test propuesto | Suite | Comportamiento pre-implementación | Comportamiento post-tag |
|----------------|-------|-----------------------------------|-------------------------|
| **`ReleaseTagPublishedTest`** | Docs | **FAIL** — lee `composer.json` version `1.2.7`, ejecuta `git tag -l v1.2.7` (o verifica anotación via `git rev-parse v1.2.7^{commit}`), assert tag existe y commit tagged es ancestro de HEAD | **PASS** |
| `PlatformVersionSemverTest` | Docs | PASS (trío sync) | PASS |
| `ReleaseChecklistDocTest` | Docs | PASS | PASS |

**Descubrimiento verificado:** `PlatformVersionSemverTest` existe y pasa, pero **no** valida tag Git — el gap REL-C1 es invisible al gate actual. El nuevo test cierra ese hueco.

**Ejemplo assert (pseudocódigo):**

```php
$version = json_decode(file_get_contents('composer.json'), true)['version'];
$tag = 'v' . $version;
exec('git rev-parse --verify ' . escapeshellarg($tag . '^{commit}'), $out, $code);
assert_same(0, $code, "Published release must have git tag {$tag} matching composer.json version");
```

---

## Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Consumidor bump sin subir PHP 8.2 | Alta | Release notes + `composer.json` require; fallo explícito en `composer update` |
| Operador confunde tag con «Facturapi listo prod» | Alta | Release notes + vertical OFF; INV-E1/E2 en plan 0/50 |
| Saltos semver 1.2.3 → 1.2.7 confunden operadores | Media | Documentar skip 1.2.4–1.2.6 en CHANGELOG y retarget planes |
| Portal lock bump sin QA AuthZ | Alta | P2 smoke manual; no automation prod |
| Publicar tag desde commit no verde CI | Alta | Exigir CI success en commit taggeado (evidencia run `31235093747` en tip) |
| M6 impide verificar lock Portal real | Media | Marcar P1/P2 no verificados; no asumir estado prod |
| Retro-tag múltiple (enfoque B) | Media | Rechazado — usar enfoque A |

---

## Rollback

| Escenario | Acción |
|-----------|--------|
| Tag publicado erróneo | **No** force-push tags en producción; publicar tag patch `v1.2.8` con fix forward; documentar yank policy si aplica |
| Consumidor regressión post-bump | Restaurar `composer.lock` previo (`v1.2.3`); redeploy; `composer install` |
| AuthZ rompe tenant custom | Rollback lock; workaround temporal vía RBAC seeds — **no** parchear `vendor/` |

Operaciones rollback producción — **fuera de automation**.

---

## Criterios de aceptación

### Framework

- [ ] **AC-F1:** `ReleaseTagPublishedTest` existe, falla en tip pre-tag, pasa tras publicar `v1.2.7`.
- [ ] **AC-F2:** Tag `v1.2.7` publicado en remoto; `git rev-parse v1.2.7^{commit}` resuelve commit con trío `1.2.7`.
- [ ] **AC-F3:** Tag commit contiene `64a6877` y `60477dc` (`git merge-base --is-ancestor` para ambos).
- [ ] **AC-F4:** `php tests/run.php Docs` verde incluyendo nuevo test.
- [ ] **AC-F5:** Release notes documentan AuthZ, states, invoicing-OFF, PHP ≥8.2, skip 1.2.4–1.2.6.
- [ ] **AC-F6:** Planes M3/M4 retarget `1.2.8+` (docs only).

### Consumidor (manual, post-tag)

- [ ] **AC-P1:** Portal lock referencia `1.2.7` — **no verificado** (M6).
- [ ] **AC-P2:** Smoke AuthZ + states en staging — **no verificado** (M6).

### Operaciones (manual, fuera de automation)

- [ ] **AC-O1:** CI green en commit taggeado.
- [ ] **AC-O2:** `/admin/sistema/estado` muestra `v1.2.7` en entorno skeleton/staging desplegado.

### Deuda carry-forward (registro, no cierre en este spec)

- [ ] **AC-D1:** CRUD-C4, CRUD-C6 permanecen abiertos — tracked.
- [ ] **AC-D2:** INV-E1/E2 permanecen abiertos; invoicing OFF — tracked.
- [ ] **AC-D3:** M3/M4/M5/M6/M10/D6 permanecen abiertos — tracked.

---

## Operaciones por entorno

| Operación | Implementación (automation/plan) | Staging | Producción |
|-----------|----------------------------------|---------|------------|
| Escribir `ReleaseTagPublishedTest` | Sí | — | — |
| Ejecutar suite Docs | Sí (CI) | Opcional | — |
| `git tag v1.2.7 && git push origin v1.2.7` | Plan / ops humano | Recomendado primero | Tras smoke staging |
| Bump Portal `composer.lock` | No (M6) | Manual | Manual post-QA |
| Habilitar Facturapi | **Prohibido** | Tras hardening futuro | Tras hardening futuro |
| Deploy VPS Portal | No | Manual | Manual |

---

*Design-only. Ningún archivo de código, config, rutas, migraciones, scripts ni tests fue modificado en esta corrida.*
