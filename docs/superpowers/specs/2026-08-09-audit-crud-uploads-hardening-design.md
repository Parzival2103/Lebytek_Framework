# Design: Endurecimiento de uploads CRUD (CRUD-C6)

**Fecha:** 2026-08-09  
**Repo spec:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel B)

**Auditoría fuente:** `docs/audits/2026-08-09-auditoria-tecnica-diaria.md` (rama `origin/automation/audit-2026-08-09` @ `e5ec12003b1f840926b700f2f258a25a8b8e1463`; **PR de auditoría ausente** — nivel B)  
**Hallazgo principal:** **CRUD-C6** — uploads del CRUD Engine permiten extensiones arbitrarias cuando falta `allowed_extensions`, concatenan `uploads.public_path` sin jail bajo `PUBLIC_PATH`, y el validador de config no inspecciona el bloque `uploads`. Carry-forward desde auditoría crítica `#90`; **0 hallazgos críticos nuevos** en la corrida del día; prioridad operativa inmediata tras **REL-C1** (release tag `v1.2.7`, spec/plan en PR `#105`, aún no en `main`).

**Specs/planes relacionados (no duplicar):**

- Release semver (REL-C1): PR `#105` — `docs/superpowers/specs/2026-08-08-audit-release-semver-tag-design.md` · plan `docs/superpowers/plans/2026-08-08-audit-release-semver-tag.md` (**pendiente merge**)
- CRUD AuthZ (C1+C2+C5, **resuelto en tip**): plan `docs/superpowers/plans/2026-08-07-crud-p01-authz-multi-canal.md` · PR `#95` @ `64a6877`
- CRUD states (C3+G15+G6, **resuelto en tip**): plan `docs/superpowers/plans/2026-08-07-crud-p02-states-form-options.md` · PR `#100` @ `60477dc`
- CRUD CAS/bulk (C4+G13+G1+G14, **siguiente lote**): plan esperado `docs/superpowers/plans/2026-08-07-crud-p04-cas-bulk-equality.md` (**no existe**)
- RBAC router (M3/G4): `docs/superpowers/specs/2026-08-06-audit-crud-rbac-router-design.md` · plan `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` (**0/40**)
- Avatares (patrón allowlist explícita): `docs/archive/superpowers/specs/2026-06-11-uploads-avatares-perfil-design.md` — `SubirAvatarUseCase` ya pasa `allowedExtensions: ['jpg','jpeg','png','webp']`
- Auditoría crítica origen: `docs/audits/2026-08-07-auditoria-critica-crud-engine.md` § C6 · programa § punto **3**
- Estructura programa: `docs/superpowers/plans/2026-08-07-crud-engine-remediacion-estructura.md`
- Frontera FPS: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `5bf0863f45116b3e574a085c0dca2bed46ed983a` |
| SHA Portal inspeccionado | **No verificado** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde auditoría 2026-08-02) |
| Rama generada | `automation/spec-2026-08-09` |
| Timestamp UTC | trigger cron `2026-08-09T12:10:00Z` / corrida agente `2026-08-09T12:10:00Z` |
| Nivel de fuente | **B** — no existe PR abierto `docs(audit):*` (`gh pr list --search "docs(audit):"` → vacío). Rama `origin/automation/audit-2026-08-09` con único diff `docs/audits/2026-08-09-auditoria-tecnica-diaria.md`; ancestry limpia; ningún commit legacy ancestro del head. **PR de auditoría faltaba.** |
| PR auditoría fuente | *(ninguno — rama sin PR al momento de la corrida)* |
| headRefOid fuente | `e5ec12003b1f840926b700f2f258a25a8b8e1463` (rama audit; **no heredada**) |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD ni del head audit |
| Issues abiertos Framework | **0** (`gh issue list --repo Parzival2103/Lebytek_Framework --state open` → vacío) |
| Issues abiertos Portal | **No verificable** — mismo bloqueador gh 404 (M6) |
| PRs abiertos Framework (contexto) | `#105` REL-C1 spec/plan (`MERGEABLE`, no en tip `main`) |

---

## Problema

La auditoría del 2026-08-09 confirma **CRUD-C6** abierto en tip `5bf0863`: el pipeline de uploads compartido (`UploadValidator` → `FileUploadService` → `CrudDataService::handleUpload`) deja una **superficie de escritura en disco** explotable si un recurso CRUD habilita uploads con JSON mal configurado.

**Evidencia verificada en tip `main` @ `5bf0863`:**

| Comprobación | Resultado |
|--------------|-----------|
| `UploadValidator::assertValid` L63–68 | Si `$allowedExtensions` es `null` o `[]`, **no** rechaza ninguna extensión |
| Test explícito de regresión invertida | `tests/Security/UploadValidatorTest.php` — «acepta cuando no hay lista blanca declarada» (**PASS hoy**) |
| `FileUploadService::handle` L62–63 | `$publicAbsolute = PUBLIC_PATH . '/' . trim($cfg->directorio, '/')` — sin `realpath`, sin bloqueo de `..` |
| `CrudDataService::handleUpload` L705–719 | Pasa `allowedExtensions: is_array($allowed) ? $allowed : null` — campo `file` sin `validation.allowed_extensions` → allowlist nula |
| `CrudConfigValidator::validate` | **Sin** método `uploadsBlockErrors`; bloque `uploads` no validado al cargar JSON |
| `UploadValidator::MIME_BY_EXT` L31 | Incluye `svg` con MIME `image/svg+xml` — riesgo stored XSS si el archivo se sirve inline desde `public/` |
| Demos harness/skeleton | `uploads.enabled=false` en todos los JSON demo inspeccionados (`demo_productos`, `demo_clientes`, …) — mitigación **accidental**, no garantía del motor |
| `SubirAvatarUseCase` | Allowlist explícita `['jpg','jpeg','png','webp']` — **no** afectado por el hueco CRUD, pero comparte `FileUploadService` |
| Baseline p01/p02 | AuthZ (#95) y states (#100) **mergeados** en tip — prerrequisito del programa punto 3 cumplido |
| Tag Composer publicado | Sólo hasta `v1.2.3`; tip declara `1.2.7` (**REL-C1 abierto**) |

**Consecuencia operativa:** un tenant o consumidor que habilite `"uploads": {"enabled": true}` y declare un campo `type: "file"` sin `allowed_extensions` puede aceptar `.php`, `.svg` con script, u otras extensiones peligrosas. Un `public_path` malicioso (`../storage`, `uploads/../../etc`) puede intentar escapar del árbol previsto bajo `public/`. El validador online no impide desplegar configs inseguras.

**Clasificación:** crítico de plataforma (vector webshell / path traversal / XSS almacenado). **Owner:** Framework. Portal no implementa el motor; puede ser **impactado** si sus JSON CRUD en `Lebytek_Portal` habilitan uploads sin allowlist.

---

## Brainstorm y recomendación de diseño

### Contexto, propósito, restricciones y criterios de éxito

| Dimensión | Detalle |
|-----------|---------|
| Contexto | Punto **3/12** del programa CRUD; C1/C2/C5/C3 ya cerrados en tip; REL-C1 bloquea consumo Composer pero no este fix de código |
| Propósito | Fail-closed en config + runtime para uploads CRUD y path jail compartido en `FileUploadService` |
| Restricciones | Sin negocio Portal en `src/`; no editar `vendor/`; conservar contrato de ruta relativa (`/uploads/.../file.ext`) y mensajes `ValidationException` existentes salvo nuevos errores de config; no desactivar RBAC/tests; producción fuera de esta corrida |
| Criterios de éxito | Config insegura rechazada al load; extensión no allowlisted rechazada en runtime; path fuera de jail rechazado; tests nuevos fallan **antes** del fix por el motivo documentado; semver PATCH consumible tras REL-C1 |

### Enfoques evaluados

| # | Enfoque | Trade-offs | Veredicto |
|---|---------|------------|-----------|
| **A** | **Defensa en tres capas:** (1) `CrudConfigValidator` exige allowlist por campo `file` si `uploads.enabled`; (2) `UploadValidator` rechaza `null`/`[]`; (3) `FileUploadService` resuelve destino con jail `realpath` bajo `PUBLIC_PATH` + prefijo `uploads/` | Cambio breaking para configs legacy inseguras (deseable); toca avatares solo si se unifica allowlist obligatoria — ya cumplen | **Recomendado** |
| **B** | Allowlist obligatoria **solo** en ruta CRUD (`CrudDataService`); `FileUploadService` sigue permisivo para otros callers | Menor blast radius inmediato | **Rechazado** — deja futuros callers sin protección; duplica reglas |
| **C** | Mover uploads a disco `private` + descarga autenticada | Cierra XSS servido inline | **Fuera de alcance C6** — cambia contrato HTTP público de rutas ya persistidas; candidato a programa posterior |

### Recomendación

Implementar **Enfoque A** como release PATCH **`1.2.8`** (después de publicar tag `v1.2.7` vía REL-C1). Política SVG: **no** incluir `svg` en allowlists por defecto de demos; si un consumidor lo necesita explícitamente, documentar riesgo XSS y exigir entrega con `Content-Disposition: attachment` (fase 2 opcional fuera de C6 mínimo — C6 mínimo = rechazar `svg` en validator salvo allowlist explícita **y** quitar `svg` del mapa MIME por defecto o tratarlo como extensión de alto riesgo con MIME estricto).

---

## Comportamiento esperado

### Plataforma Framework (`Lebytek_Framework` @ `main`)

1. **Validación de config (load time):** si `uploads.enabled === true`, el validador exige:
   - `uploads.public_path` no vacío, sin `\`, sin segmentos `..`, prefijo canónico `uploads/` (regex: `^uploads/[a-z0-9_/]+$`).
   - Por cada campo `form.fields[]` con `type === 'file'`: `validation.allowed_extensions` presente, array no vacío, entradas alfanuméricas lowercase sin punto, sin duplicados, sin extensiones en denylist global (`php`, `phtml`, `phar`, `htaccess`, `svg` salvo opt-in documentado).
2. **Runtime upload:** `UploadValidator::assertValid` lanza `ValidationException` si `$allowedExtensions` es `null` o `[]`.
3. **Path jail:** `FileUploadService` resuelve `$destDir = realpath(PUBLIC_PATH) . …` y verifica que el directorio destino final permanece bajo `realpath(PUBLIC_PATH)/uploads` (crear subdirectorios solo dentro del jail).
4. **CRUD:** `CrudDataService::handleUpload` sin cambio de contrato de salida (`$archivo->ruta()` relativa); errores de config imposibles tras validator.
5. **Avatares:** sin cambio funcional — ya cumplen allowlist; tests existentes siguen verdes.
6. **Mensajes:** conservar textos actuales para extensión/tamaño/MIME; nuevos errores de config en español alineados al estilo del validator CRUD (`El recurso … tiene uploads habilitados pero …`).

### Consumidor Portal (`Lebytek_Portal` @ `main`) — **no verificado**

1. Tras consumir Framework `>=1.2.8`, revisar JSON CRUD bajo `config/cruds/` (negocio `dom_*`): cualquier recurso con uploads ON debe declarar `allowed_extensions` por campo file.
2. Bump `composer.lock` **después** de tag Framework publicado (depende REL-C1 + este tag).
3. **Staging:** desplegar lock nuevo, ejecutar smoke de carga de configs CRUD (`php scripts/…` o arranque app) antes de producción.
4. **Producción:** fuera de alcance de corrida desatendida — operador humano valida que no hay configs rechazadas.

### Contratos públicos ausentes (no asumir)

- No existe API HTTP de uploads fuera de flujos CRUD/form y avatares ya cableados.
- No existe flag `vertical` que desactive uploads CRUD — mitigación es config + validator.
- Legacy `feature/backoffice-api-integration` (**histórico**, tag `archive/backoffice-api-integration` @ `4789f95`) puede contener reglas distintas; **no** es base de implementación ni producción actual.

---

## Alcance

| ID | Entregable Framework |
|----|----------------------|
| C6 | Allowlist obligatoria uploads+file (config + runtime) |
| C6 | Jail/normalización de `public_path` / `directorio` en `FileUploadService` |
| C6 | Validación bloque `uploads` en `CrudConfigValidator` |
| C6 | Endurecimiento SVG / denylist extensiones ejecutables |
| C6 | Tests de regresión (Security + Archivos + Crud Upload) |
| C6 | Espejo `skeleton/config/cruds/` si cambian demos |
| C6 | Plan de implementación `docs/superpowers/plans/2026-08-07-crud-p03-uploads-hardening.md` (AUTOMATION-04, fuera de este spec) |
| Doc | Actualizar `docs/modules/crud/modulo-crud-engine.md` § uploads (allowlist obligatoria, formato `public_path`) |

**Semver / release:** PATCH **`1.2.8`** en trío `composer.json` / `config/app.php` / `skeleton/config/app.php`; tag Git **`v1.2.8`** publicado desde tip tras merge. **Prerrequisito:** tag **`v1.2.7`** (REL-C1) publicado primero — no saltar la cadena de consumo.

---

## No-alcance

- Punto 4 CAS/TOCTOU (CRUD-C4, G13, G1, G14).
- RBAC router CRUD (M3 / spec 2026-08-06).
- API health público (M4).
- Invoicing Facturapi hardening (INV-E1/E2).
- Migrar uploads a disco private + controller de descarga (Enfoque C).
- Cambios en `Lebytek_Portal` JSON de negocio (solo documentar checklist consumidor).
- Operaciones de producción (deploy VPS, bump lock Portal prod, SSH).
- Merge o referencia a `feature/backoffice-api-integration` como base.

---

## Ownership map

| Requisito | Repositorio | Rama base | Capa / ruta |
|-----------|-------------|-----------|-------------|
| Validator config uploads | `Lebytek_Framework` | `main` | `src/Application/Services/CrudConfigValidator.php` |
| Allowlist runtime | `Lebytek_Framework` | `main` | `src/Application/Services/UploadValidator.php` |
| Path jail | `Lebytek_Framework` | `main` | `src/Application/Services/FileUploadService.php` |
| Wiring CRUD | `Lebytek_Framework` | `main` | `src/Application/Services/CrudDataService.php` |
| Tests plataforma | `Lebytek_Framework` | `main` | `tests/Security/`, `tests/Archivos/`, `tests/Crud/Upload/` |
| Demos/espejo | `Lebytek_Framework` | `main` | `config/cruds/`, `skeleton/config/cruds/` |
| Docs módulo CRUD | `Lebytek_Framework` | `main` | `docs/modules/crud/modulo-crud-engine.md` |
| Tag Composer `v1.2.8` | `Lebytek_Framework` | `main` | release ops (post-merge plan) |
| JSON CRUD negocio `dom_*` | `Lebytek_Portal` | `main` | `config/cruds/` (**no verificado**) |
| Bump `composer.lock` Portal | `Lebytek_Portal` | `main` | post-tag Framework (**no verificado**) |
| QA staging Portal | `Lebytek_Portal` | staging | operador humano |

---

## Dependencias y compatibilidad

### Orden de release

```text
main (AuthZ #95 + states #100 ya mergeados)
  → tag v1.2.7 (REL-C1, PR #105)
  → implementar C6 → tag v1.2.8
  → consumidores bump lock
```

### Compatibilidad hacia atrás

| Escenario | Impacto |
|-----------|---------|
| Recurso con `uploads.enabled=false` | Sin cambio |
| Recurso uploads ON + allowlist completa | Sin cambio funcional |
| Recurso uploads ON + campo file **sin** allowlist | **Breaking:** config deja de cargar (validator) o upload rechazado (runtime) — corrección requerida en JSON del consumidor |
| `public_path` inválido (`..`, fuera de `uploads/`) | **Breaking:** config rechazada |
| Avatares | Compatible — allowlist ya explícita |
| Portal en `v1.2.3` lock | No recibe fix hasta bump; no es regresión del tag viejo |
| PHP | `>=8.2` sin cambio |

### Migración segura

**Base nueva (skeleton / tenant greenfield):** demos ya con `uploads.enabled=false`; al habilitar uploads, seguir checklist del módulo con allowlist por campo.

**Base Portal existente (no verificada):**

1. Auditar localmente `config/cruds/**/*.json` buscando `"enabled": true` en bloque `uploads` y campos `type":"file"`.
2. Añadir `validation.allowed_extensions` mínimo viable (`pdf`, `jpg`, …) antes del bump.
3. Staging: `composer update lebytek/framework` a `^1.2.8`, smoke load configs.
4. Producción: ventana operador — **no** automatizar en esta corrida.

---

## Riesgos

| Riesgo | Severidad | Mitigación en diseño |
|--------|-----------|----------------------|
| Config Portal legacy rechazada tras bump | Alta (disponibilidad) | Documentar checklist; fallo en validator es explícito con mensaje de campo |
| REL-C1 no publicado antes de C6 | Media (cadena semver) | Plan p03 exige tag `v1.2.7` publicado; consumidores en `v1.2.3` no mezclan |
| Regresión avatares por allowlist global | Media | Avatares ya pasan allowlist; test `AvatarUseCasesTest` en regresión |
| `realpath` falla si `PUBLIC_PATH` no existe | Baja | Crear jail root en install; test con `PUBLIC_PATH` temporal |
| Falso positivo MIME (docx/zip) | Baja | Mantener mapa MIME existente; no ampliar denylist MIME agresiva en C6 |
| Portal SHA desconocido (M6) | Media (visibilidad) | Marcar requisitos Portal como **no verificados** |

---

## Rollback

| Ámbito | Procedimiento |
|--------|---------------|
| Framework código | Revert merge del PR de implementación p03; tag `v1.2.8` no publicar o yank nota en release notes si ya publicado (Composer no borra tags — preferir PATCH `1.2.9` revert) |
| Consumidor | Mantener `composer.lock` en versión anterior (`1.2.7` o anterior); restaurar JSON si se añadieron allowlists |
| Config | Si validator bloquea deploy, deshabilitar temporalmente `uploads.enabled` (mitigación operativa, no sustituto del fix) |
| Producción | Solo operador — fuera de automation |

---

## Criterios de aceptación

### Tests que deben **fallar antes** de implementar (TDD)

| Test (nuevo o invertido) | Fallo esperado pre-fix | Motivo |
|--------------------------|------------------------|--------|
| `UploadValidator rechaza allowlist null o vacía` | PASS hoy en test «acepta cuando no hay lista blanca» | Allowlist opcional (C6) |
| `FileUploadService rechaza directorio con .. fuera de jail` | PASS hoy — escribe bajo path manipulado | Sin normalización path |
| `CrudConfigValidator rechaza uploads enabled sin allowed_extensions en campo file` | Validator no tiene regla — config pasa | Hueco config |
| `CrudConfigValidator rechaza public_path ../evil` | Idem | Hueco config |

Cada test debe **existir** en el plan p03 y ejecutarse con `php tests/run.php Security` / `Archivos` / suite Crud Upload — ningún comando debe reportar «0 tests».

### Tests post-implementación

- Suites `Security`, `Archivos`, `Crud` Upload: **0 failed**.
- `php tests/run.php SkeletonPurity`: **13/13 PASS**.
- Demos harness/skeleton: cargan sin error de validator.
- Trío semver sincronizado en **`1.2.8`**.

### Aceptación operativa (staging — humano)

- Portal staging (cuando accesible): bump lock + arranque sin `CrudConfigException` en recursos productivos.
- **Producción:** criterio documentado; no exigido en corrida desatendida.

---

## Diseño técnico (esbozo)

### 1. `CrudConfigValidator` — `uploadsBlockErrors`

Nuevo método estático (patrón `statesBlockErrors`):

- Invocado desde `validate()` antes de persistir config en memoria.
- Reglas enumeradas en § Comportamiento esperado.
- Errores acumulados en array; lanzar `CrudConfigException` agregada existente.

### 2. `UploadValidator`

- Tras calcular `$extension`, si `$allowedExtensions === null || $allowedExtensions === []` → `ValidationException('Extensión de archivo no permitida para ' . $label . '.')` (reutilizar mensaje existente para consistencia UX).
- Denylist global de extensiones peligrosas incluso si aparecen en allowlist (`php`, `phtml`, …) — fail-closed adicional.
- Revisar entrada `svg`: rechazar por defecto o exigir MIME estricto sin `text/plain`/`text/xml` amplios.

### 3. `FileUploadService` — path jail

Pseudoflujo:

```text
$root = realpath(PUBLIC_PATH) ?: throw
$relative = normalize(trim(directorio), reject .. segments)
$absolute = $root . DIRECTORY_SEPARATOR . $relative
ensureDirectory($absolute)
$resolved = realpath($absolute) ?: throw
assert str_starts_with($resolved, $root . '/uploads')
```

Conservar generación de nombre seguro y ledger `core_archivos` sin cambio de schema.

### 4. Documentación

- § uploads en `modulo-crud-engine.md`: ejemplo mínimo seguro.
- Referencia cruzada al plan p03 y al punto 3 del programa.

---

## Operaciones por entorno

| Operación | Implementación | Staging | Producción |
|-----------|----------------|---------|------------|
| Merge código p03 | Automation / dev | — | — |
| Publicar tag `v1.2.8` | Release ops post-merge | Opcional pre-prod | Operador |
| Bump Portal lock | — | CI/staging manual | Operador |
| Auditar JSON CRUD Portal | — | Recomendado | Obligatorio antes de bump |
| Deploy VPS | — | — | **Fuera de corrida desatendida** |

---

*Report-only spec. Ningún archivo de código, config de producto, rutas, migraciones ni tests fue modificado en esta corrida.*
