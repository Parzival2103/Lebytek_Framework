# mkt_leads afterListRows Enrichment (Portal) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consumir el hook `afterListRows` de `lebytek/framework` ≥ `v1.2.2` para enriquecer el listado admin de `mkt_leads` con estado WhatsApi/tenant en vivo, con degradación graceful si la API falla.

**Architecture:** Enfoque A del spec — handler Portal en `App\Application\Marketing\Crud\` extiende `AbstractCrudHookHandler`, registrado por clave `mkt_leads_enrich` en `config/crud_handlers.php`; JSON CRUD declara hook + columnas `"virtual": true`. Enriquecimiento vía `LebytekApiClient::getTenant()` (documentado en `docs/integration/lebytek-implementation-real.md`) con presupuesto 2s por página; fallback a `api_lifecycle_status` persistido en BD. Sin edits en `vendor/lebytek/framework`.

**Tech Stack:** PHP 8.1+, Composer 2.x, `lebytek/framework` ≥ `v1.2.2`, harness Portal (`php tests/run.php` — **estructura no verificada**: gh 404 Portal), Bootstrap 5.3 admin CRUD.

**Source spec:** `docs/archive/superpowers/specs/2026-08-02-audit-v122-release-integrity-design.md`  ·  **Modo:** normal

**Source audit PR:** #67 — https://github.com/Parzival2103/Lebytek_Framework/pull/67 (Framework audit; Portal no inspeccionable)

**Target repository/branches:** `Parzival2103/Lebytek_Portal` @ `main` (**SHA no verificado** — gh 404); rama de trabajo `feature/mkt-leads-after-list-rows` (creable desde `main` — **Requiere operador humano:** verificar `git ls-remote origin refs/heads/main` en clone Portal)

**Depends on:** Framework plan `2026-08-02-audit-v122-release-integrity.md` Task 1 mínimo (tag `v1.2.2` ya publicado @ `09b4f3e`; recomendado `v1.2.3` post-dompdf).

## Global Constraints

- **Repositorio Portal no verificable** desde automation (M6/gh 404). Rutas basadas en convenciones documentadas en `docs/integration/lebytek-implementation-real.md` y spec 2026-08-02. El ejecutor debe confirmar existencia antes de editar.
- Clave handler whitelist: **`mkt_leads_enrich`** (no FQCN en JSON).
- Columnas virtuales: **`wa_estado`**, **`tenant_actividad`** (labels español).
- Timeout total enriquecimiento por página: **2 segundos**; fallback «—» o valor BD.
- No editar `vendor/lebytek/framework`.
- Deploy producción VPS: **fuera de alcance** — operador manual.

## Requisitos → tareas (matriz)

| Requisito spec | Owner | Tarea | Verificación |
|----------------|-------|-------|--------------|
| P1 bump framework ≥v1.2.2 | Portal | Task 1 | `composer.lock` referencia tag |
| P2 handler afterListRows | Portal | Task 3 | test enriquecimiento |
| P3 registro crud_handlers | Portal | Task 4 | test registro |
| P4 JSON hook + columnas | Portal | Task 4 | test JSON |
| P5 test gate | Portal | Task 2 | TDD rojo→verde |
| U5–U9 UX leads | Portal | Task 3–5 | smoke admin 320–768px |
| F1–F4 Framework | Framework | **Fuera de alcance** | plan `2026-08-02-audit-v122-release-integrity.md` |

## File Structure (convención Portal — verificar en clone)

| Archivo | Responsabilidad |
|---------|-----------------|
| `composer.json` | `"lebytek/framework": "^1.2"` o constraint existente |
| `composer.lock` | Pin ≥ `v1.2.2` |
| `app/Application/Marketing/Crud/MktLeadsListEnrichmentHandler.php` | Hook `afterListRows` |
| `config/crud_handlers.php` | Map `'mkt_leads_enrich' => Handler::class` |
| `config/cruds/mkt_leads.json` | `"hooks": { "handler": "mkt_leads_enrich" }` + columnas virtual |
| `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php` | Gate unitario con mock API |

**Contrato Framework consumido (ya en vendor ≥ v1.2.2):**

- `Lebytek\Framework\Application\Crud\Context\CrudListRowsContext` — `rows()`, `setRows()`, `query()`
- `Lebytek\Framework\Application\Crud\Handlers\AbstractCrudHookHandler::afterListRows`
- Invocación: `CrudResourceService::buildIndexData` post-query, pre-`CrudTableBuilder`

---

### Task 1: Bump `composer.lock` a `lebytek/framework` ≥ v1.2.2 (P1)

**Repository:** `Parzival2103/Lebytek_Portal`

**Branch:** `feature/mkt-leads-after-list-rows`

**Depends on:** Framework tag `v1.2.2` publicado (verificado en `Lebytek_Framework`)

**Files:**
- Modify: `composer.json` (solo si constraint < `^1.2`)
- Modify: `composer.lock`

**Interfaces:**
- Consumes: tag `v1.2.2` @ `09b4f3e` en repo VCS Framework
- Produces: `vendor/lebytek/framework` con `CrudListRowsContext` disponible

- [ ] **Step 1: Escribir el test que falla** — verificar versión instalada (script one-liner o test existente):

```bash
cd Lebytek_Portal
composer show lebytek/framework | grep -E 'versions|name'
php -r "require 'vendor/autoload.php'; echo class_exists('Lebytek\\Framework\\Application\\Crud\\Context\\CrudListRowsContext') ? 'OK_CTX' : 'MISSING'; echo PHP_EOL;"
```

Expected pre-bump: versión `< v1.2.2` o `MISSING` (última evidencia documentada: `v1.1.0` @ audit 2026-07-27).

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: comandos Step 1 / Expected: versión antigua o clase ausente.

- [ ] **Step 3: Implementar el cambio mínimo**

```bash
cd Lebytek_Portal
git checkout -b feature/mkt-leads-after-list-rows
# Asegurar repositorio VCS Framework en composer.json:
# { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
composer require lebytek/framework:^1.2 --no-interaction
composer update lebytek/framework --no-interaction
```

Expected: lock referencia commit/tag ≥ `v1.2.2`; autoload resuelve `CrudListRowsContext`.

- [ ] **Step 4: Verificación enfocada** — Run: `composer show lebytek/framework | head -5` / Expected: `versions : * v1.2.2` (o superior).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Kernel` (o suite base Portal) / Expected: 0 failed — distinguir fallos preexistentes de entorno.

- [ ] **Step 6: Commit** — archivos: `composer.json`, `composer.lock` / mensaje: `chore(deps): bump lebytek/framework to ^1.2.2 for afterListRows hook`

---

### Task 2: Test gate `MktLeadsListEnrichmentTest` (TDD — falla antes del handler)

**Repository:** `Parzival2103/Lebytek_Portal`

**Branch:** `feature/mkt-leads-after-list-rows`

**Depends on:** Task 1

**Files:**
- Create: `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php`
- Test: `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php`

**Interfaces:**
- Consumes: `AbstractCrudHookHandler`, `CrudListRowsContext` desde vendor
- Produces: test que falla porque handler/clase aún no existe o no enriquece filas

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Marketing\Crud\MktLeadsListEnrichmentHandler;
use Lebytek\Framework\Application\Crud\Context\CrudListRowsContext;

test('mkt_leads_enrich key is registered in crud_handlers.php', function (): void {
    $map = require dirname(__DIR__, 3) . '/config/crud_handlers.php';
    assert_true(
        isset($map['mkt_leads_enrich']),
        "config/crud_handlers.php must register 'mkt_leads_enrich'. Action: add handler map entry"
    );
    assert_same(MktLeadsListEnrichmentHandler::class, $map['mkt_leads_enrich']);
});

test('MktLeadsListEnrichmentHandler enriches wa_estado from API tenant', function (): void {
    $api = new class {
        public function getTenant(string $publicId): array
        {
            return ['publicId' => $publicId, 'isActive' => true, 'commercialStatus' => 'active'];
        }
    };

    $handler = new MktLeadsListEnrichmentHandler($api);
    $rows = [['id' => 1, 'api_tenant_public_id' => '01JTEST', 'api_lifecycle_status' => 'pending']];
    $ctx = new CrudListRowsContext('mkt_leads', 'dom_mkt_leads', 'id', 1, '127.0.0.1', $rows);
    $handler->afterListRows($ctx);

    assert_same('Activo', $ctx->rows()[0]['wa_estado'] ?? null);
    assert_same('active', $ctx->rows()[0]['tenant_actividad'] ?? null);
});

test('MktLeadsListEnrichmentHandler degrades when api_tenant_public_id missing', function (): void {
    $api = new class {
        public function getTenant(string $publicId): array { return []; }
    };
    $handler = new MktLeadsListEnrichmentHandler($api);
    $rows = [['id' => 2, 'api_tenant_public_id' => null]];
    $ctx = new CrudListRowsContext('mkt_leads', 'dom_mkt_leads', 'id', 1, '127.0.0.1', $rows);
    $handler->afterListRows($ctx);

    assert_same('—', $ctx->rows()[0]['wa_estado'] ?? null);
});
```

Ajustar `$api` stub al tipo real `LebytekApiClient` o interfaz si Portal ya define contrato; el test debe compilar contra la firma real verificada en clone.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php MktLeadsListEnrichment` / Expected: **FAIL** — class not found o registro ausente.

- [ ] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Tasks 3–4 implementan handler y registro.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php MktLeadsListEnrichment` / Expected: FAIL (TDD rojo).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Marketing` (si existe filtro) / Expected: nuevos fails únicamente por este test.

- [ ] **Step 6: Commit** — archivos: `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php` / mensaje: `test(marketing): add MktLeadsListEnrichmentTest gate (red)`

---

### Task 3: Implementar `MktLeadsListEnrichmentHandler` (P2)

**Repository:** `Parzival2103/Lebytek_Portal`

**Branch:** `feature/mkt-leads-after-list-rows`

**Depends on:** Task 2

**Files:**
- Create: `app/Application/Marketing/Crud/MktLeadsListEnrichmentHandler.php`
- Test: `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php`

**Interfaces:**
- Consumes: `App\Infrastructure\Integrations\LebytekApi\LebytekApiClient::getTenant(string $publicId): array` (documentado `docs/integration/lebytek-implementation-real.md` §4)
- Produces: filas con `wa_estado` (label español) y `tenant_actividad` antes del formateo CRUD

- [ ] **Step 1: Escribir el test que falla** — tests Task 2 ya rojos.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php MktLeadsListEnrichment` / Expected: FAIL.

- [ ] **Step 3: Implementar el cambio mínimo** — crear `app/Application/Marketing/Crud/MktLeadsListEnrichmentHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Marketing\Crud;

use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Application\Crud\Context\CrudListRowsContext;
use Lebytek\Framework\Application\Crud\Handlers\AbstractCrudHookHandler;

final class MktLeadsListEnrichmentHandler extends AbstractCrudHookHandler
{
    private const PAGE_BUDGET_SECONDS = 2.0;

    public function __construct(private readonly LebytekApiClient $api) {}

    public function afterListRows(CrudListRowsContext $ctx): void
    {
        $rows = $ctx->rows();
        $deadline = microtime(true) + self::PAGE_BUDGET_SECONDS;

        foreach ($rows as &$row) {
            $tenantId = $row['api_tenant_public_id'] ?? null;
            if (!is_string($tenantId) || $tenantId === '') {
                $row['wa_estado'] = '—';
                $row['tenant_actividad'] = '—';
                continue;
            }

            if (microtime(true) >= $deadline) {
                $row['wa_estado'] = $this->fallbackWaEstado($row);
                $row['tenant_actividad'] = $row['api_lifecycle_status'] ?? '—';
                continue;
            }

            try {
                $tenant = $this->api->getTenant($tenantId);
                $row['wa_estado'] = ($tenant['isActive'] ?? false) ? 'Activo' : 'Inactivo';
                $row['tenant_actividad'] = (string) ($tenant['commercialStatus'] ?? $row['api_lifecycle_status'] ?? '—');
            } catch (LebytekApiException) {
                $row['wa_estado'] = $this->fallbackWaEstado($row);
                $row['tenant_actividad'] = $row['api_lifecycle_status'] ?? '—';
            }
        }
        unset($row);

        $ctx->setRows($rows);
    }

    /** @param array<string, mixed> $row */
    private function fallbackWaEstado(array $row): string
    {
        $status = $row['api_lifecycle_status'] ?? null;
        return is_string($status) && $status !== '' ? $status : '—';
    }
}
```

Registrar binding DI si Portal usa container explícito para handlers CRUD (verificar patrón en `config/container.php` del clone).

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php MktLeadsListEnrichment` / Expected: tests handler PASS; test registro JSON puede seguir FAIL hasta Task 4.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php` (suite Marketing si existe) / Expected: 0 failed en tests no relacionados.

- [ ] **Step 6: Commit** — archivos: handler + binding DI si aplica / mensaje: `feat(marketing): add MktLeadsListEnrichmentHandler for afterListRows`

---

### Task 4: Registro handler + JSON CRUD con columnas virtuales (P3, P4)

**Repository:** `Parzival2103/Lebytek_Portal`

**Branch:** `feature/mkt-leads-after-list-rows`

**Depends on:** Task 3

**Files:**
- Modify: `config/crud_handlers.php` (añadir clave `mkt_leads_enrich`)
- Modify: `config/cruds/mkt_leads.json` (hooks + columnas virtual)
- Test: `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php`

**Interfaces:**
- Consumes: handler Task 3; validación `CrudConfigValidator` en framework exige clave registrada
- Produces: listado `/admin/crud/mkt_leads` invoca hook; columnas virtuales renderizadas

- [ ] **Step 1: Escribir el test que falla** — test registro Task 2 rojo pre-registro.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php MktLeadsListEnrichment` / Expected: FAIL en test registro.

- [ ] **Step 3: Implementar el cambio mínimo**

En `config/crud_handlers.php`:

```php
    'mkt_leads_enrich' => \App\Application\Marketing\Crud\MktLeadsListEnrichmentHandler::class,
```

En `config/cruds/mkt_leads.json`, añadir/actualizar (merge con definición existente — **no sobrescribir** campos resource/form):

```json
  "hooks": {
    "handler": "mkt_leads_enrich"
  },
  "list": {
    "columns": [
      { "name": "id", "label": "ID", "priority": 1 },
      { "name": "email", "label": "Correo", "priority": 2 },
      { "name": "wa_estado", "label": "Estado WhatsApp", "virtual": true, "priority": 3 },
      { "name": "tenant_actividad", "label": "Actividad tenant", "virtual": true, "priority": 4 }
    ]
  }
```

Conservar columnas SQL existentes del JSON actual; insertar virtuales con `priority` según spec R3 (320–768px).

Validación estática:

```bash
php -r "
\$j = json_decode(file_get_contents('config/cruds/mkt_leads.json'), true);
echo (\$j['hooks']['handler'] ?? '') === 'mkt_leads_enrich' ? 'hook_ok' : 'hook_fail';
echo PHP_EOL;
"
```

Expected: `hook_ok`.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php MktLeadsListEnrichment` / Expected: PASS — 3 tests, 0 failed.

- [ ] **Step 5: Regresión relevante** — Run: validador CRUD si existe (`php tests/run.php Crud` Portal) / Expected: 0 failed; sin FQCN en JSON hooks.

- [ ] **Step 6: Commit** — archivos: `config/crud_handlers.php`, `config/cruds/mkt_leads.json` / mensaje: `feat(marketing): wire mkt_leads afterListRows hook and virtual columns`

---

### Task 5: Regresión, smoke admin y PR (P5)

**Repository:** `Parzival2103/Lebytek_Portal`

**Branch:** `feature/mkt-leads-after-list-rows`

**Depends on:** Task 4

**Files:**
- Test: suite Portal

**Interfaces:**
- Consumes: Tasks 1–4
- Produces: PR hacia `main`; evidencia smoke listado leads

- [ ] **Step 1: Escribir el test que falla** — N/A.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php MktLeadsListEnrichment` pre-Task 3 / Expected: FAIL (evidencia PR).

- [ ] **Step 3: Implementar el cambio mínimo** — N/A.

- [ ] **Step 4: Verificación enfocada** — Run:

```bash
php tests/run.php MktLeadsListEnrichment
php tests/run.php
grep -R "vendor/lebytek/framework" .gitignore composer.json >/dev/null && echo vendor_readonly_ok
```

Expected: tests verdes; sin commits bajo `vendor/`.

- [ ] **Step 5: Regresión relevante** — Smoke manual admin (**Requiere operador humano:** sí — entorno con `.env` Portal + API mock o staging):

1. Login admin → `/admin/crud/mkt_leads`
2. Fila con `api_tenant_public_id`: columnas `Estado WhatsApp` y `Actividad tenant` pobladas
3. Fila sin tenant: celdas «—»
4. Viewport **320px** y **768px**: tabla con `table-responsive`, columnas priority sin truncar identificador

Expected: listado renderiza sin 500; degradación visible si API caída.

- [ ] **Step 6: Commit / PR**

```bash
git push -u origin feature/mkt-leads-after-list-rows
gh pr create --base main --title "feat(marketing): enrich mkt_leads list via afterListRows hook" \
  --body "Cierra P1–P5 del spec 2026-08-02.

- composer.lock → lebytek/framework >= v1.2.2
- MktLeadsListEnrichmentHandler + crud_handlers + mkt_leads.json
- Tests con mock API; sin edits vendor/
- Depende Framework tag v1.2.2 (#66)"
```

---

## Fuera de alcance

| ID | Tema | Motivo |
|----|------|--------|
| F1–F4 | Semver/dompdf Framework | Plan Framework separado |
| Deploy lebytek.com VPS | Ops | Operador manual |
| Stripe QA / PAYMENTS_SUBSCRIPTION_CHECKOUT | Portal/Ops | C2 ops |
| Cache Redis TTL (enfoque C spec) | Portal | Optimización futura |
| Cambios WhatsApiLebytek API | WhatsApi | Contrato vigente @ f3f3ec7 |

## Criterios finales de aceptación

- [ ] `composer.lock` referencia `lebytek/framework` ≥ **`v1.2.2`**.
- [ ] Handler registrado; JSON declara `mkt_leads_enrich`.
- [ ] `php tests/run.php MktLeadsListEnrichment` PASS (≥3 tests).
- [ ] Listado admin muestra columnas virtuales (smoke operador).
- [ ] Sin edits en `vendor/lebytek/framework`.
- [ ] Frontera FPS: handler en `App\`, cliente en `App\Infrastructure\Integrations\`.

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Portal prod en framework v1.1.0 | Task 1 bump lock antes de handler |
| Latencia API N×getTenant | Presupuesto 2s/página; fallback BD |
| Rutas Portal difieren de convención | Operador verifica paths en clone |
| gh 404 bloquea automation | Plan completo; ejecución manual |

**Rollback:** quitar hook JSON + entrada `crud_handlers.php`; revert composer bump si necesario.

## Evidencia que debe recopilar el ejecutor

- Salida `composer show lebytek/framework` post-bump.
- Salida tests pre/post fix.
- Captura listado `mkt_leads` con columnas enriquecidas (320px y 768px).
- SHA commit y número PR Portal.

---

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Reconciliación UTC | 2026-08-05T12:40:00Z (AUTOMATION-03) |
| Plan creado UTC | 2026-08-02T12:40:00Z |
| Framework `origin/main` verificado | `42c3a0a4d0fafacd24d8632ca6e77c00836da79f` |
| Portal SHA | **No verificado** (gh 404 M6) |
| Tareas completadas / totales | **0 / 5** |
| Modo fuente | normal (spec 2026-08-02; guía ampliada en plan archivado `docs/archive/superpowers/plans/2026-08-03-audit-mkt-leads-after-list-rows.md`) |
| Siguiente tarea ejecutable | **Task 1** — bump `composer.lock` Portal (requiere clone `Parzival2103/Lebytek_Portal`) |
| Prerrequisitos | Acceso git/gh Portal; Framework ≥ `v1.2.2` **satisfecho** — tag `v1.2.3` @ `041e402`, `CrudListRowsContext` en `src/Application/Crud/Context/CrudListRowsContext.php` @ `42c3a0a` |
| Bloqueos | **M6** — automation sin lectura Portal; smoke admin requiere operador |
| Evidencia verificación | Sin entregables Portal verificables desde Framework; hook `afterListRows` genérico presente en paquete @ `42c3a0a` |
| Rama `feature/mkt-leads-after-list-rows` | **No verificada** — crear en clone Portal desde `main` |
| Estado | **Pendiente de implementación** |
