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
| Timestamp UTC | trigger cron `2026-08-09T12:10:00Z` / corrida agente `2026-08-09T12:10:00Z` / pase ux `2026-08-09T12:30:00Z` (modo **normal**) |
| Nivel de fuente | **B** — no existe PR abierto `docs(audit):*` (`gh pr list --search "docs(audit):"` → vacío). Rama `origin/automation/audit-2026-08-09` con único diff `docs/audits/2026-08-09-auditoria-tecnica-diaria.md`; ancestry limpia; ningún commit legacy ancestro del head. **PR de auditoría faltaba.** |
| PR auditoría fuente | #106 — https://github.com/Parzival2103/Lebytek_Framework/pull/106 (abierto al pase ux) |
| headRefOid fuente | `e5ec12003b1f840926b700f2f258a25a8b8e1463` (rama audit; **no heredada**) |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD ni del head audit |
| Issues abiertos Framework | **0** (`gh issue list --repo Parzival2103/Lebytek_Framework --state open` → vacío) |
| Issues abiertos Portal | **No verificable** — mismo bloqueador gh 404 (M6) |
| PRs abiertos Framework (contexto) | `#105` REL-C1 spec/plan (`MERGEABLE`, no en tip `main`) |
| Pase deuda | `2026-08-09T13:02:06Z` — modo **normal** — `origin/main` @ `487ccd8132e7c42eabd2a0e3b335b075ccc123e1` |

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

- Punto 4 CAS/TOCTOU (CRUD-C4, G13, G1, G14) — backlog verificado § Deuda técnica; plan p04 pendiente.
- RBAC router CRUD (M3 / spec 2026-08-06) — plan `2026-08-06-audit-crud-rbac-router.md` **0/40**.
- API health público (M4) — plan `2026-08-05-audit-api-health-public.md` **0/38**.
- Release semver REL-C1 (tag `v1.2.7`) — spec/plan PR `#105` pendiente merge; prerrequisito de tag `v1.2.8` C6.
- Invoicing Facturapi hardening (INV-E1/E2) — plan `2026-08-08-invoicing-facturapi-production-hardening.md` **0/50**; vertical OFF.
- Deploy LAB `skeleton.lebytek.com` (D6) — plan humano `2026-07-26-skeleton-package-staging.md`.
- Retarget semver obsoleto en planes M3/M4 (`1.2.4`/`1.2.5`) — acción AUTOMATION-07, no bloquea C6.
- Migrar uploads a disco private + controller de descarga (Enfoque C).
- Cambios en `Lebytek_Portal` JSON de negocio (solo documentar checklist consumidor; P1–P3 **no verificados** M6).
- Operaciones de producción (deploy VPS, bump lock Portal prod, SSH).
- Merge o referencia a `feature/backoffice-api-integration` como base.
- Auto-fix de deuda documentada (Payments bootstrap, Portal marketing) en `app/`/`src/` — requisitos quedan como gates ops consumidor.

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
| CRUD-C4 TOCTOU sin CAS | Alta (integridad estado) | Fuera alcance C6; plan p04 CAS/bulk — § Deuda técnica |
| INV-E1/E2 si se habilita Facturapi | Alta (producción) | Vertical `invoicing=false`; no activar hasta plan hardening **0/50** |
| Config Portal legacy sin allowlist post-bump | Alta (disponibilidad) | Checklist § Migración segura; U4/U5 UX |
| Planes M3/M4 citan tags saltados (`1.2.4`/`1.2.5`) | Baja–Media (release train) | Retarget ≥`1.2.8` al implementar; no bloquea diseño C6 |
| Hueco auditorías 2026-08-03..05 (M10) | Media (proceso) | Cadena specs 03–09 sin ancla audit diaria esos días |
| `composer validate` lock content-hash (H1) | Baja (hygiene) | CI usa `--no-check-lock`; corregir en release train humano |

---

## Rollback

| Ámbito | Procedimiento |
|--------|---------------|
| Framework código | Revert merge del PR de implementación p03; tag `v1.2.8` no publicar o yank nota en release notes si ya publicado (Composer no borra tags — preferir PATCH `1.2.9` revert) |
| Consumidor | Mantener `composer.lock` en versión anterior (`1.2.7` o anterior); restaurar JSON si se añadieron allowlists |
| Config | Si validator bloquea deploy, deshabilitar temporalmente `uploads.enabled` (mitigación operativa, no sustituto del fix) |
| Producción | Solo operador — fuera de automation |

---

## Compatibilidad, UX y responsive

### Modo del pase: normal

Este spec endurece **uploads CRUD** (allowlist, path jail, validación de config). Superficie UI
verificable: campos `type: "file"` en formularios admin (`form.php` L100–105), mensajes
`ValidationException` bajo el campo (`invalid-feedback`), y errores de config al cargar JSON CRUD
(`CrudConfigException` — operador/despliegue). No modifica login, dashboard nav ni listados; esas
superficies permanecen carry-forward.

### Compatibilidad (verificado vs carry-forward)

| Área | Este spec (C6) | Evidencia / carry-forward |
|------|----------------|---------------------------|
| PHP soportado | Sin cambio runtime | `composer.json` exige `>=8.2`; VPS documentado PHP **8.4.22** CLI/pool (`2026-07-26-skeleton-package-staging-design.md`) — compatible; `realpath`/`str_starts_with` disponibles en 8.2+. |
| Instalación vía `vendor/` | Contrato paquete semver | Consumidores obtienen fix tras tag **`v1.2.8`** + bump lock — **no** parche en `vendor/`. Prerrequisito tag **`v1.2.7`** (REL-C1, PR `#105`). Portal JSON `dom_*` con uploads ON debe declarar `allowed_extensions` antes del bump (**no verificado** M6). |
| Health sin cookie de sesión | Carry-forward **M4** | `routes/api.php` — `/api/ping` exige sesión; smoke LB: **no** usar ping. Backlog `GET /api/health` público (spec 2026-08-05, plan 0/5). |
| `.env.example` sin vars Portal | **Resuelto (M2)** — sin alcance | Root `.env.example` L55 remite `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` a Portal; uploads CRUD no introducen env vars nuevas. |
| Navegadores objetivo | Superficie formulario file + errores | Baseline `docs/core/ui_ux.md`: admin breakpoint **992px (`lg`)**. Chrome, Firefox, Safari, Edge últimas 2 versiones + iOS Safari ≥ 15; sin IE11. Input `type="file"` nativo debe ser usable en **320–768px** sin overflow horizontal del form card. |

### UX — flujos admin CRUD uploads (C6)

| Requisito | Criterio | Deuda |
|-----------|----------|-------|
| **U1** | Rechazo por extensión no allowlisted: mensaje existente «Extensión de archivo no permitida para {label}.» se muestra bajo el campo (`invalid-feedback`) — **no** error genérico de página | C6 runtime |
| **U2** | Copy accionable post-C6: cuando el validador conoce la allowlist, el mensaje indica extensiones permitidas (p. ej. «Usa: pdf, jpg, png») — qué falló + qué hacer; conservar tono español del CRUD Engine | C6 runtime, CF10 parcial |
| **U3** | Rechazo MIME/contenido: mensajes actuales («El contenido del archivo… no coincide con su extensión», «supera el tamaño máximo») permanecen bajo el campo — operador distingue extensión vs MIME vs tamaño | C6 runtime |
| **U4** | Error de config (`CrudConfigException` al load): mensaje en español indica recurso, campo file y acción («añade validation.allowed_extensions con al menos una extensión» o «corrige uploads.public_path — debe empezar por uploads/») — **no** sólo «config inválida» | C6 validator |
| **U5** | Distinción config vs runtime: fallo en arranque/deploy por JSON mal configurado **no** se confunde con error de upload en formulario POST — operador sabe si editar JSON o reintentar archivo | C6 validator + runtime |
| **U6** | `help_text` en JSON CRUD para campos file: doc § uploads recomienda listar extensiones permitidas visibles al usuario final (p. ej. «Solo PDF, máx. 5 MB») — alineado a allowlist declarada | Doc C6 |
| **U7** | Tests Security/Archivos/Crud Upload: mensaje de fallo pre-fix cita spec C6, archivo bajo test y acción («implementar allowlist obligatoria» / «registrar uploadsBlockErrors») | C6 tests |
| **U8** | Avatares (`avatar_manager.php`): sin regresión — mismos mensajes de rechazo bajo input; allowlist explícita ya cumple C6 | C6 compat avatares |

### UX — instalación y operaciones (Portal / staging)

| Requisito | Criterio | Estado |
|-----------|----------|--------|
| **U9** | Checklist migración Portal (§ Migración segura): operador audita JSON antes de bump — mensaje CLI/log al detectar config rechazada indica archivo y regla violada | P1–P3 |
| **U10** | Bump Framework a `1.2.8` fallido por semver: Composer indica versión mínima y secuencia (`1.2.7` tag REL-C1 primero) | REL-C1 carry-forward |

### Responsive — smoke en superficies tocadas

Referencia: `docs/core/ui_ux.md` §542 — breakpoint admin **992px (`lg`)**; formularios apilan campos en móvil (§12).

| Superficie | Verificación post-merge | Rango |
|------------|-------------------------|-------|
| Formulario CRUD con campo file | `form-control` file input ancho completo; label + `help_text` + `invalid-feedback` legibles; botones acción en `flex-column flex-sm-row` sin solapamiento | **320–768px** |
| Mensajes de error upload | Texto de error no desborda card; sin scroll horizontal en `.ct-form-card` | **320–768px** |
| Listado CRUD (sin cambio directo) | Sin regresión: `table-responsive` en listados con columna de archivo/adjunto | **320–768px** |
| Login / dashboard nav (sin alcance directo) | Carry-forward CF3–CF4 — smoke opcional post-merge | **320–768px** |

### Carry-forward UX — próximo spec con superficie UI más amplia

Ítems derivados de deuda abierta; **C6 uploads queda cubierto por este spec** — no arrastrar como hueco
allowlist/path. CF6 (RBAC router), CF1 parcial (REL-C1 tag) y CF5 parcial (`mkt_leads`) tampoco se
arrastran (specs 2026-08-06, 2026-08-08, 2026-08-03).

| # | Ítem | Origen | Requisito concreto |
|---|------|--------|-------------------|
| CF3 | Login responsive 320–768px | `ui_ux.md` | `.ct-login-page`, `.ct-login-card` sin overflow horizontal; tap targets ≥44px; sin scroll lateral en 320px. |
| CF4 | Dashboard admin responsive 320–768px | layouts side/top/bottom | Nav colapsable; KPI grid legible; topbar sin solapamiento de acciones. |
| CF5′ | Tablas CRUD restantes | módulo CRUD, D6 | `table-responsive` + `list.columns[].priority` en recursos distintos de `mkt_leads`; toolbar móvil. |
| CF7 | Health endpoint público | M4 | `GET /api/health` 200 sin cookie; body `{ "status": "ok" }`; checklist VPS — spec/plan 2026-08-05 (**0/5**). |
| CF8 | Permisos admin catálogo | M5 | Slug `permisos.gestionar` en seeds; UI permisos sin workaround `administracion.ver`. |
| CF9 | Estados vacío / error / carga (global) | `ui_ux.md` §8 | Unificar empty states en CRUDs sin hook; spinners list; validación con hint de corrección — más allá de U2. |
| CF10 | Copy errores accionables (transversal) | transversal | Auth, wizard install, CRUD save: qué falló + qué hacer — extiende U2 fuera de uploads. |
| CF11 | Pantalla estado sistema post-tag | O2, D6 | `/admin/sistema/estado` muestra semver legible en 320–768px tras deploy skeleton/staging — verificación manual bloqueada por D6/M6. |

---

## Criterios de aceptación

### Compatibilidad, UX y responsive

- [ ] **AC-UX1:** Sección **Compatibilidad, UX y responsive** declara modo **normal** con requisitos K/U/R verificables para C6 (formularios file admin, mensajes upload/config).
- [ ] **AC-UX2:** Requisitos U1–U8 (mensajes bajo campo, copy accionable con allowlist, distinción config vs runtime, help_text, hints test gate, avatares sin regresión) incluidos como criterios del spec.
- [ ] **AC-UX3:** Carry-forward CF3–CF4, CF5′, CF7–CF11 documentado; C6 no arrastrado (cubierto por este spec); CF6, CF1 parcial REL-C1, CF5 parcial y D7 no arrastrados (cubiertos en specs previos).
- [ ] **AC-UX4:** Smoke responsive en **320–768px** para formulario CRUD con campo file y mensajes de error post-implementación (sin regresión `table-responsive` en listados).

### Deuda técnica (inventario)

- [ ] **AC-D1:** Sección **Deuda técnica** lista abiertos verificados (CRUD-C6, REL-C1, CRUD-C4, INV-E1/E2, M3–M5, D6, M10) con evidencia ruta/línea en `main` @ `487ccd8`.
- [ ] **AC-D2:** M1, M2, M7–M9, D7, CRUD-C1/C2/C3/C5 reconciliados como **resueltos en tip** (release AuthZ/states pendiente REL-C1); no re-listados como abiertos.
- [ ] **AC-D3:** P1–P3, M6/D3, D14, D15, H1 marcados **no verificados**; acción concreta documentada.
- [ ] **AC-D4:** Verificado sin deuda nueva — migraciones 3 SQL ↔ 3 entradas manifiesto; `src/` sin `TODO`/`FIXME`; referencias operativas vivas a `feature/backoffice-api-integration` ausentes fuera registro histórico; Payments bootstrap documentado como gate ops.

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

## Deuda técnica

Fuente: auditoría `docs/audits/2026-08-09-auditoria-tecnica-diaria.md` (merge `#106` @ `487ccd8`); reconciliación con inventario spec `2026-08-06-audit-crud-rbac-router-design.md` (pase deuda @ `ddc55ec`) y corrida `2026-08-07` (authz — commit `0d0db79` en rama `automation/spec-2026-08-07`, artefacto no mergeado a `main`; cierres verificados en tip `487ccd8`).

### Reconciliación heredada (cerrados)

| ID | Tema | Estado | Resolución |
|----|------|--------|------------|
| **D7** | CI GitHub Actions | **Resuelto** | PR #88 @ `8e6ed48` — `.github/workflows/platform-tests.yml` presente; `tests/Docs/CiWorkflowPresentTest.php` verde @ `487ccd8` |
| **CRUD-C1** | IDOR `scope_handler` | **Resuelto en tip** | PR #95 @ `64a6877` — consumo Composer pendiente REL-C1 |
| **CRUD-C2** | Acción sin `permission` | **Resuelto en tip** | PR #95 @ `64a6877` — consumo Composer pendiente REL-C1 |
| **CRUD-C5** | `CrudReporteDataSource` sin `{resource}.ver` | **Resuelto en tip** | PR #95 @ `64a6877` — consumo Composer pendiente REL-C1 |
| **CRUD-C3** | Columna states editable + demo toggle | **Resuelto en tip** | PR #100 @ `60477dc` — consumo Composer pendiente REL-C1 |
| **M1** | Sync semver trío | **Resuelto en tip** | `composer.json` L6, `config/app.php` L7, `skeleton/config/app.php` L7 → `1.2.7` @ `487ccd8`; tag publicado pendiente (REL-C1) |
| **M2** | `.env.example` vars Portal | **Resuelto** | #62 — root `.env.example` L55 remite keys Portal; activas `MKT_*`/`LEBYTEK_API_*`/`WAAPI_PORTAL_*` = **0** |
| **M7–M9** | Audit lifecycle / ops / dompdf | **Resuelto** | #54/#56/#57/#74 — sin regresión @ `487ccd8` |
| **C1** | Scripts `vps-deploy-*` | **Resuelto** | PR #36; `DeployScriptsRemovedTest` verde |
| **C2** | Stripe subscription (Framework) | **Resuelto** Framework | PR #42 + tags `v1.2.1`…`v1.2.3`; QA Portal **no verificado** (M6) |

**Cierres desde corrida anterior (2026-08-07 pase deuda @ `da3ab58`):** **4** — CRUD-C1, CRUD-C2, CRUD-C5 (#95 @ `64a6877`); CRUD-C3 (#100 @ `60477dc`). Intervalo `da3ab58..487ccd8` incluye merges código AuthZ/states + audits/invoicing docs; D7 ya estaba cerrado (#88 antes de `da3ab58`).

### Alcance principal de este spec (CRUD-C6 — abierto, verificado)

| ID | Hallazgo | Evidencia (`main` @ `487ccd8`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **CRUD-C6** | Uploads sin allowlist obligatoria + path sin jail | `UploadValidator.php` L63–68 — allowlist `null`/`[]` no rechaza; `tests/Security/UploadValidatorTest.php` L20 — test «acepta cuando no hay lista blanca» **PASS**; `FileUploadService.php` L62–63 — concatena `PUBLIC_PATH` sin `realpath`/bloqueo `..`; `CrudDataService.php` L705–719 — `allowedExtensions: … null` si falta `validation.allowed_extensions`; `CrudConfigValidator.php` — **sin** `uploadsBlockErrors`; `UploadValidator.php` L31 — `svg` en `MIME_BY_EXT` (XSS almacenado si servido inline) | Vector webshell / path traversal / XSS; superficie escritura disco | `Application` | Framework | Enfoque A § Comportamiento esperado + plan `2026-08-09-audit-crud-uploads-hardening.md`; tag **`v1.2.8`** post REL-C1 |

### Backlog Framework verificado (fuera alcance C6)

| ID | Hallazgo | Evidencia (`main` @ `487ccd8`) | Impacto | Capa | Owner | Acción |
|----|----------|--------------------------------|---------|------|-------|--------|
| **REL-C1** | Tip `1.2.7` sin tags `v1.2.6`/`v1.2.7` | `composer.json` L6 = `1.2.7`; `git tag -l 'v1.2.*'` → sólo hasta `v1.2.3` @ `041e402`; `git tag --contains 64a6877` / `60477dc` → vacío; spec/plan PR `#105` **no** en tip | AuthZ/states no consumibles por Composer lock | Release ops | Framework | Merge/ejecutar plan `2026-08-08-audit-release-semver-tag.md`; publicar tag mínimo `v1.2.7` |
| **CRUD-C4** | Transitions sin CAS / TOCTOU | `CrudTransitionService.php` L104–108 — `updateRecord` sin predicado `WHERE estado = :from`; audit crítica #90 § C4 | Carreras concurrentes en máquina de estados | `Application` | Framework | Plan p04 CAS/bulk (**no existe**); spec programa punto 4 |
| **INV-E1** | Doble timbrado tras timeout post-create | `IssueInvoiceFromSource.php` L73 — `releaseClaim` si create remoto ambiguo; audit invoicing #101 E1 | Doble CFDI si vertical ON | `Application` Invoicing | Framework | Plan hardening **0/50**; mantener `FACTURAPI_ENABLED=false` |
| **INV-E2** | Fallo dual markIssued/markNeedsReconcile | `IssueInvoiceFromSource.php` L57–62 — `catch` traga fallo `markNeedsReconcile`; id remoto irrecuperable localmente | Reconciliación manual | `Application` Invoicing | Framework | Mismo plan hardening **0/50** |
| **M3** | CRUD/Calendario sin `RbacMiddleware` router | `routes/web.php` L114–125 — `/crud/{resource}*`, `/calendario/{key}*` sin RBAC router; contraste L127+ pdf-kit/reportes **sí** usan `RbacMiddleware`; `skeleton/routes/web.php` espejo; plan **0/40** | Defensa en profundidad débil; 403 inconsistentes | `Presentation` / `routes/` | Framework | Spec/plan 2026-08-06; retarget tag ≥`1.2.8` |
| **M4** | `/api/*` sesión; sin health público | `routes/api.php` L14–16 — grupo `AuthMiddleware`; L23 `/api/ping` dentro del grupo; `rg '/health' routes/` → **0**; plan **0/38** | LB/cron no liveness sin cookie | `Presentation` / `routes/` | Framework | Spec/plan 2026-08-05 |
| **M5** | Slug `permisos.gestionar` ausente | `routes/web.php` L61–65 — workaround `administracion.ver`; `rg permisos.gestionar database/` → **0** | Catálogo RBAC acoplado | `Domain` RBAC | Framework | CF8 — seed + rutas futuro |
| **D6** | `skeleton.lebytek.com` pendiente | `docs/ENVIRONMENTS.md` L7, L13, L31 — «pendiente de implementar»; plan staging **0/10** deploy | LAB package puro no desplegado | Ops | Framework/Ops | Plan humano Tasks 6–8 |
| **M10** | Hueco auditorías 2026-08-03..05 | `docs/audits/` sin `2026-08-0{3,4,5}-auditoria-tecnica-diaria.md`; cadena 06–09 en curso | Specs 03–05 sin ancla audit diaria | Proceso automation | Ops/automation | Corrida AUTOMATION-00 retroactiva o aceptar hueco documentado |

### Planes activos — estado ejecución real

| Plan | Tareas | Estado @ `487ccd8` |
|------|--------|-------------------|
| `docs/superpowers/plans/2026-08-09-audit-crud-uploads-hardening.md` | 0/N | Pendiente — Task 1 tests Security (este spec C6) |
| `docs/superpowers/plans/2026-08-08-audit-release-semver-tag.md` | 0/N | Pendiente — PR `#105`; tag `v1.2.7` no publicado |
| `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` | 0/40 | Pendiente — M3 |
| `docs/superpowers/plans/2026-08-05-audit-api-health-public.md` | 0/38 | Pendiente — M4 |
| `docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md` | 0/50 | Pendiente — INV-E1/E2 |
| `docs/superpowers/plans/2026-08-07-crud-p04-cas-bulk-equality.md` | — | **No existe** — CRUD-C4 |

### No verificados (declarados explícitamente)

| ID | Hallazgo | Motivo | Acción |
|----|----------|--------|--------|
| **P1** | Portal lock ≥ `v1.2.3` / JSON uploads `dom_*` | `gh repo view Parzival2103/Lebytek_Portal` → GraphQL fail; última evidencia `v1.1.0` @ `a79d3ad` | Auditar `config/cruds/` antes bump `1.2.8` cuando M6 resuelva |
| **P2** | Portal merge CRUD / wiring post-tag | Clone Portal inaccesible | Operador valida staging post REL-C1 |
| **P3** | Portal CRUD `permission_prefix` / uploads ON | Repo Portal inaccesible | Checklist § Migración segura |
| **M6 / D3** | Portal SHA / `composer.lock` | `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 | Ops: conceder lectura Portal al token automation |
| **D14** | Stripe subscription QA Portal | Repo Portal inaccesible; Framework `vertical.payments=false` | Portal QA checkout antes de habilitar |
| **D15** | Bootstrap marketing Portal | Re-scopeado `Lebytek_Portal#4` | Portal issue #4 |
| **H1** | `composer validate` lock content-hash | No re-ejecutado en este pase; audit #104 documenta exit **2** con lock drift | `composer update --lock` en release train; CI `--no-check-lock` |

### Verificado sin deuda nueva

- **Migraciones ↔ manifiesto:** 3 archivos `database/migrations/` ↔ 3 entradas `config/modules/{core,crud-engine,pdf-kit}.php` L15–16 — sin drift.
- **`src/`:** `rg TODO\|FIXME src/` → **0** con impacto; sin `LebytekApiClient` ni Marketing.
- **Capas:** uploads en `Application`; Domain sin deps Presentation/Infrastructure; hook `afterListRows` sin violación.
- **Legacy operativo:** referencias vivas a `feature/backoffice-api-integration` **ausentes** en `scripts/`, `docs/composer-setup.md` (L128 cita tag archive como histórico), `docs/integration/`; menciones en `docs/automation/` = registro de proceso, no runbook deploy.
- **Payments bootstrap:** `config/vertical.php` — `payments=false`, `invoicing=false`; root `.env.example` keys Stripe OFF — requisitos Stripe = gate ops Portal (D14), no auto-fix `src/`.
- **`.env.example` root vs skeleton:** drift `APP_NAME`/`DB_DATABASE`/keys `PAYMENTS_*`/`STRIPE_*` — **intencional** (harness plataforma vs tenant mínimo); no reabre M2.
- **CI:** `platform-tests.yml` + `CiWorkflowPresentTest` — D7 permanece resuelto.

**Conteo:** **10 abiertos verificados** (CRUD-C6 alcance spec + REL-C1, CRUD-C4, INV-E1, INV-E2, M3, M4, M5, D6, M10); **7 no verificados** (P1, P2, P3, M6/D3, D14, D15, H1); **4 heredados cerrados** esta corrida (CRUD-C1, CRUD-C2, CRUD-C5, CRUD-C3 en tip).

---

*Report-only spec. Ningún archivo de código, config de producto, rutas, migraciones ni tests fue modificado en esta corrida.*
