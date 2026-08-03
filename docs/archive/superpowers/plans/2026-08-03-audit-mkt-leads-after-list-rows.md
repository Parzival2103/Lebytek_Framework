# mkt_leads afterListRows Enrichment + UX (Portal) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consumir el hook `afterListRows` de `lebytek/framework` ≥ `v1.2.2` (recomendado `v1.2.3`) para enriquecer `/admin/crud/mkt_leads` con columnas virtuales `wa_estado` y `tenant_actividad`, degradación graceful ante fallos API (U2), copy accionable (U3) y smoke responsive 320–768px (R1).

**Architecture:** Enfoque A del spec — handler Portal en `App\Application\Marketing\Crud\MktLeadsListEnrichmentHandler` extiende `AbstractCrudHookHandler`; registro por clave `mkt_leads_enrich` en `config/crud_handlers.php`; JSON CRUD declara hook + columnas `"virtual": true` con `priority` para responsive. Enriquecimiento vía `LebytekApiClient::getTenant(string $publicId): array` (`GET /tenants/{publicId}`) con presupuesto **2s por página**; fallback a `api_lifecycle_status` persistido o «—». Framework prerrequisito satisfecho @ `041e402` — **sin edits** en `vendor/lebytek/framework` ni en `Lebytek_Framework/src/`.

**Tech Stack:** PHP 8.1+, Composer 2.x, `lebytek/framework` tag `v1.2.3` @ `041e402`, harness Portal `php tests/run.php`, Bootstrap 5.3 admin CRUD + DataTables Responsive (Framework `src/Presentation/Views/admin/crud/index.php`).

**Source spec:** `docs/superpowers/specs/2026-08-03-audit-mkt-leads-after-list-rows-design.md`  ·  **Modo:** normal

**Source audit PR:** #67 — https://github.com/Parzival2103/Lebytek_Framework/pull/67 (auditoría 2026-08-02 mergeada; hallazgo P1 Portal abierto)

**Target repository/branches:** `Parzival2103/Lebytek_Portal` @ `main` (**SHA no verificado** — gh 404 M6); rama `feature/mkt-leads-after-list-rows` (creable desde `main` — **Requiere operador humano:** verificar `git ls-remote git@github.com:Parzival2103/Lebytek_Portal.git refs/heads/main`)

## Global Constraints

- **Portal no verificable** desde automation (M6). Rutas basadas en `docs/integration/lebytek-implementation-real.md` §4 y convenciones `App\`. El ejecutor confirma paths antes de editar.
- Clave handler whitelist: **`mkt_leads_enrich`** (no FQCN en JSON).
- Columnas virtuales: **`wa_estado`**, **`tenant_actividad`** (labels español U1).
- Timeout total enriquecimiento: **2.0 segundos** por página (U2, U5 parcial vía deadline).
- Framework hook verificado: `CrudListRowsContext`, `CrudResourceService::buildIndexData` L64–65 @ `041e402`.
- Deploy VPS producción: **fuera de alcance** — operador manual.

## Requisitos → tareas (matriz)

| Requisito spec | Owner | Tarea | Verificación |
|----------------|-------|-------|--------------|
| P1 bump framework ≥v1.2.2 | Portal | Task 1 | `composer.lock` ≥ `v1.2.3` |
| P5 test gate TDD | Portal | Task 2 | `MktLeadsListEnrichmentTest` rojo→verde |
| P2 handler afterListRows | Portal | Task 3 | U2/U3/U5 en tests |
| P3 registro crud_handlers | Portal | Task 4 | test registro U4 |
| P4 JSON hook + columnas R1 | Portal | Task 4 | priority + virtual U1 |
| U6 empty state | Portal | Task 5 | framework `list_empty` partial |
| U7 bump accionable | Portal | Task 1 | script/check versión mínima |
| F-hook, F-semver | Framework | **Fuera de alcance** | satisfecho @ `041e402` |
| CF3–CF4, CF6–CF10 | Framework/Portal | **Fuera de alcance** | carry-forward spec |

## File Structure (convención Portal — verificar en clone)

| Archivo | Responsabilidad |
|---------|-----------------|
| `composer.json` | constraint `"lebytek/framework": "^1.2"` |
| `composer.lock` | Pin ≥ `v1.2.3` |
| `app/Application/Marketing/Crud/MktLeadsListEnrichmentHandler.php` | Hook `afterListRows` (U2, U3, U5) |
| `config/crud_handlers.php` | `'mkt_leads_enrich' => Handler::class` |
| `config/cruds/mkt_leads.json` | hooks + columnas virtual + priority (U1, R1) |
| `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php` | Gate unitario mock API |

**Contrato Framework consumido (vendor ≥ v1.2.2 @ `041e402`):**

- `Lebytek\Framework\Application\Crud\Context\CrudListRowsContext` — `rows(): array`, `setRows(array $rows): void`, `query(): array`
- `Lebytek\Framework\Application\Crud\Handlers\AbstractCrudHookHandler::afterListRows(CrudListRowsContext $ctx): void`
- Invocación: `CrudResourceService::buildIndexData` post-`list()`, pre-`CrudTableBuilder::build`
- Columnas: `"virtual": true`, `"priority": int` propagado a `<th data-priority>` (`src/Presentation/Views/admin/crud/index.php` L117–121)

**Contrato API consumido (Portal cliente):**

- `App\Infrastructure\Integrations\LebytekApi\LebytekApiClient::getTenant(string $publicId): array` → `GET {LEBYTEK_API_URL}/tenants/{publicId}`
- Respuesta camelCase: `isActive` (bool), `commercialStatus` (string|null) — `docs/integration/waapi-api-contract.md` L184–187, TenantResource L169–177
- Excepción: `App\Infrastructure\Integrations\LebytekApi\LebytekApiException`

---

### Task 1: Bump `composer.lock` a `lebytek/framework` ≥ v1.2.3 (P1, U7)

**Repository:** `Parzival2103/Lebytek_Portal`

**Branch:** `feature/mkt-leads-after-list-rows`

**Depends on:** None (Framework tag `v1.2.3` @ `041e402` publicado)

**Files:**
- Modify: `composer.json` (solo si constraint < `^1.2`)
- Modify: `composer.lock`

**Interfaces:**
- Consumes: tag `v1.2.3` @ `041e402` en VCS `Parzival2103/Lebytek_Framework`
- Produces: `vendor/lebytek/framework` con `CrudListRowsContext`; fallo U7 si versión < mínima

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Marketing/Crud/FrameworkVersionGateTest.php`:

```php
<?php

declare(strict_types=1);

test('lebytek/framework lock satisfies afterListRows minimum v1.2.2', function (): void {
    $root = dirname(__DIR__, 3);
    $lockPath = $root . '/composer.lock';
    assert_true(is_readable($lockPath), 'composer.lock must exist');
    $lock = json_decode((string) file_get_contents($lockPath), true);
    $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
    $fw = null;
    foreach ($packages as $pkg) {
        if (($pkg['name'] ?? '') === 'lebytek/framework') {
            $fw = $pkg;
            break;
        }
    }
    assert_true($fw !== null, 'composer.lock must pin lebytek/framework. Action: composer require lebytek/framework:^1.2');
    $version = ltrim((string) ($fw['version'] ?? ''), 'v');
    assert_true(
        version_compare($version, '1.2.2', '>='),
        "lebytek/framework must be >= 1.2.2 for afterListRows (found {$version}). Action: composer update lebytek/framework to v1.2.3"
    );
});

test('CrudListRowsContext autoloads from vendor', function (): void {
    $root = dirname(__DIR__, 3);
    require $root . '/vendor/autoload.php';
    assert_true(
        class_exists('Lebytek\\Framework\\Application\\Crud\\Context\\CrudListRowsContext'),
        'CrudListRowsContext missing. Action: composer update lebytek/framework (minimum v1.2.2)'
    );
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php FrameworkVersionGate` / Expected: **FAIL** — versión `< 1.2.2` o `CrudListRowsContext missing` (evidencia doc: Portal @ `v1.1.0` audit 2026-07-27).

- [ ] **Step 3: Implementar el cambio mínimo**

```bash
cd Lebytek_Portal
git checkout -b feature/mkt-leads-after-list-rows
# composer.json repositories (si falta):
# { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
composer require lebytek/framework:^1.2 --no-interaction
composer update lebytek/framework --no-interaction
```

Expected: lock referencia commit/tag ≥ `v1.2.3`; mensaje U7 accionable si falla Composer.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php FrameworkVersionGate` / Expected: PASS — 2 tests, 0 failed.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Kernel` / Expected: 0 failed en tests base Portal (distinguir fallos preexistentes de entorno).

- [ ] **Step 6: Commit** — archivos: `composer.json`, `composer.lock`, `tests/Marketing/Crud/FrameworkVersionGateTest.php` / mensaje: `chore(deps): bump lebytek/framework to ^1.2.3 for afterListRows hook`

---

### Task 2: Test gate `MktLeadsListEnrichmentTest` (TDD — falla antes del handler) (P5)

**Repository:** `Parzival2103/Lebytek_Portal`

**Branch:** `feature/mkt-leads-after-list-rows`

**Depends on:** Task 1

**Files:**
- Create: `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php`
- Test: `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php`

**Interfaces:**
- Consumes: `AbstractCrudHookHandler`, `CrudListRowsContext` desde vendor
- Produces: tests rojos para registro, enriquecimiento, timeout U2, hint U3

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
        "config/crud_handlers.php must register 'mkt_leads_enrich'. Action: add handler map entry (U4)"
    );
    assert_same(MktLeadsListEnrichmentHandler::class, $map['mkt_leads_enrich']);
});

test('mkt_leads.json declares afterListRows hook handler key', function (): void {
    $json = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3) . '/config/cruds/mkt_leads.json'),
        true
    );
    assert_same('mkt_leads_enrich', $json['hooks']['handler'] ?? null);
});

test('MktLeadsListEnrichmentHandler enriches wa_estado from API tenant (U1)', function (): void {
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

test('MktLeadsListEnrichmentHandler degrades when api_tenant_public_id missing (U1)', function (): void {
    $api = new class {
        public function getTenant(string $publicId): array { return []; }
    };
    $handler = new MktLeadsListEnrichmentHandler($api);
    $rows = [['id' => 2, 'api_tenant_public_id' => null]];
    $ctx = new CrudListRowsContext('mkt_leads', 'dom_mkt_leads', 'id', 1, '127.0.0.1', $rows);
    $handler->afterListRows($ctx);
    assert_same('—', $ctx->rows()[0]['wa_estado'] ?? null);
    assert_same('—', $ctx->rows()[0]['tenant_actividad'] ?? null);
});

test('MktLeadsListEnrichmentHandler falls back on API exception with actionable hint (U2, U3)', function (): void {
    $api = new class {
        public function getTenant(string $publicId): array
        {
            throw new \App\Infrastructure\Integrations\LebytekApi\LebytekApiException('API down', 503);
        }
    };
    $handler = new MktLeadsListEnrichmentHandler($api);
    $rows = [['id' => 3, 'api_tenant_public_id' => '01JFAIL', 'api_lifecycle_status' => 'provisioned']];
    $ctx = new CrudListRowsContext('mkt_leads', 'dom_mkt_leads', 'id', 1, '127.0.0.1', $rows);
    $handler->afterListRows($ctx);
    assert_same('provisioned', $ctx->rows()[0]['wa_estado'] ?? null);
    $hint = (string) ($ctx->rows()[0]['wa_estado_hint'] ?? '');
    assert_true(
        str_contains($hint, 'LEBYTEK_API_TOKEN') || str_contains($hint, 'WhatsApi'),
        'wa_estado_hint must cite LEBYTEK_API_TOKEN or WhatsApi when API fails (U3)'
    );
});

test('MktLeadsListEnrichmentHandler respects page budget deadline (U2, U5)', function (): void {
    $calls = 0;
    $api = new class($calls) {
        public function __construct(private int &$calls) {}
        public function getTenant(string $publicId): array
        {
            $this->calls++;
            usleep(800_000); // 0.8s × 3 rows exceeds 2s budget
            return ['isActive' => true, 'commercialStatus' => 'active'];
        }
    };
    $handler = new MktLeadsListEnrichmentHandler($api);
    $rows = [
        ['id' => 10, 'api_tenant_public_id' => '01JA', 'api_lifecycle_status' => 'a'],
        ['id' => 11, 'api_tenant_public_id' => '01JB', 'api_lifecycle_status' => 'b'],
        ['id' => 12, 'api_tenant_public_id' => '01JC', 'api_lifecycle_status' => 'c'],
    ];
    $ctx = new CrudListRowsContext('mkt_leads', 'dom_mkt_leads', 'id', 1, '127.0.0.1', $rows);
    $start = microtime(true);
    $handler->afterListRows($ctx);
    assert_true(microtime(true) - $start < 3.5, 'afterListRows must finish within ~2s budget + margin');
    // At least one row beyond deadline uses BD fallback, not fresh API label
    $states = array_column($ctx->rows(), 'wa_estado');
    assert_true(in_array('a', $states, true) || in_array('b', $states, true), 'deadline path uses api_lifecycle_status fallback');
});
```

Ajustar stub `$api` al tipo `LebytekApiClient` si Portal define interfaz; compilar contra firma real verificada en clone.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php MktLeadsListEnrichment` / Expected: **FAIL** — class not found / registro ausente / asserts enriquecimiento.

- [ ] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Tasks 3–4 implementan handler y registro.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php MktLeadsListEnrichment` / Expected: FAIL (TDD rojo confirmado).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php FrameworkVersionGate` / Expected: PASS (Task 1 intacto).

- [ ] **Step 6: Commit** — archivos: `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php` / mensaje: `test(marketing): add MktLeadsListEnrichmentTest gate (red)`

---

### Task 3: Implementar `MktLeadsListEnrichmentHandler` (P2, U2, U3, U5)

**Repository:** `Parzival2103/Lebytek_Portal`

**Branch:** `feature/mkt-leads-after-list-rows`

**Depends on:** Task 2

**Files:**
- Create: `app/Application/Marketing/Crud/MktLeadsListEnrichmentHandler.php`
- Test: `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php`

**Interfaces:**
- Consumes: `LebytekApiClient::getTenant(string $publicId): array`
- Produces: filas con `wa_estado`, `tenant_actividad`, opcional `wa_estado_hint` (U3 metadata — no columna listado)

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
    private const API_FAIL_HINT = 'Revisa WhatsApi y LEBYTEK_API_TOKEN en .env Portal';

    public function __construct(private readonly LebytekApiClient $api) {}

    public function afterListRows(CrudListRowsContext $ctx): void
    {
        $rows = $ctx->rows();
        $deadline = microtime(true) + self::PAGE_BUDGET_SECONDS;

        foreach ($rows as &$row) {
            unset($row['wa_estado_hint']);
            $tenantId = $row['api_tenant_public_id'] ?? null;
            if (!is_string($tenantId) || $tenantId === '') {
                $row['wa_estado'] = '—';
                $row['tenant_actividad'] = '—';
                continue;
            }

            if (microtime(true) >= $deadline) {
                $row['wa_estado'] = $this->fallbackWaEstado($row);
                $row['tenant_actividad'] = (string) ($row['api_lifecycle_status'] ?? '—');
                $row['wa_estado_hint'] = 'Presupuesto 2s agotado; valor desde BD';
                continue;
            }

            try {
                $tenant = $this->api->getTenant($tenantId);
                $row['wa_estado'] = ($tenant['isActive'] ?? false) ? 'Activo' : 'Inactivo';
                $row['tenant_actividad'] = (string) (
                    $tenant['commercialStatus'] ?? $row['api_lifecycle_status'] ?? '—'
                );
            } catch (LebytekApiException) {
                $row['wa_estado'] = $this->fallbackWaEstado($row);
                $row['tenant_actividad'] = (string) ($row['api_lifecycle_status'] ?? '—');
                $row['wa_estado_hint'] = self::API_FAIL_HINT;
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

Registrar binding DI en `config/container.php` (patrón existente `LeadApiProvisioningService`):

```php
// Ejemplo — ajustar al container Portal real:
$container->singleton(MktLeadsListEnrichmentHandler::class, fn ($c) => new MktLeadsListEnrichmentHandler(
    $c->get(LebytekApiClient::class)
));
```

Si Portal resuelve handlers CRUD vía `CrudHandlerRegistry` con `new Handler()` sin DI, usar factory documentada en `config/crud_handlers.php` del clone.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php MktLeadsListEnrichment` / Expected: tests handler PASS (≥4); tests registro JSON pueden seguir FAIL hasta Task 4.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Marketing` (si existe) / Expected: 0 failed fuera de tests nuevos.

- [ ] **Step 6: Commit** — archivos: handler + binding DI / mensaje: `feat(marketing): add MktLeadsListEnrichmentHandler for afterListRows`

---

### Task 4: Registro handler + JSON CRUD con columnas virtuales (P3, P4, U1, R1)

**Repository:** `Parzival2103/Lebytek_Portal`

**Branch:** `feature/mkt-leads-after-list-rows`

**Depends on:** Task 3

**Files:**
- Modify: `config/crud_handlers.php`
- Modify: `config/cruds/mkt_leads.json`
- Test: `tests/Marketing/Crud/MktLeadsListEnrichmentTest.php`

**Interfaces:**
- Consumes: handler Task 3; `CrudConfigValidator` exige clave registrada (U4)
- Produces: listado invoca hook; columnas virtual con `priority` para DataTables Responsive (R1)

- [ ] **Step 1: Escribir el test que falla** — tests registro Task 2 rojos pre-registro.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php MktLeadsListEnrichment` / Expected: FAIL en tests registro/JSON.

- [ ] **Step 3: Implementar el cambio mínimo**

En `config/crud_handlers.php`:

```php
    'mkt_leads_enrich' => \App\Application\Marketing\Crud\MktLeadsListEnrichmentHandler::class,
```

En `config/cruds/mkt_leads.json`, merge (no sobrescribir resource/form existentes):

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

Conservar columnas SQL existentes; ajustar `priority` según columnas reales del JSON Portal.

Validación estática:

```bash
php -r "
\$j = json_decode(file_get_contents('config/cruds/mkt_leads.json'), true);
echo (\$j['hooks']['handler'] ?? '') === 'mkt_leads_enrich' ? 'hook_ok' : 'hook_fail';
echo PHP_EOL;
foreach (\$j['list']['columns'] ?? [] as \$c) {
  if ((\$c['name'] ?? '') === 'wa_estado') {
    echo ((\$c['virtual'] ?? false) ? 'virtual_ok' : 'virtual_fail') . PHP_EOL;
  }
}
"
```

Expected: `hook_ok`, `virtual_ok`.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php MktLeadsListEnrichment` / Expected: PASS — ≥6 tests, 0 failed.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Crud` Portal (si existe) / Expected: 0 failed; JSON sin FQCN en `hooks.handler`.

- [ ] **Step 6: Commit** — archivos: `config/crud_handlers.php`, `config/cruds/mkt_leads.json` / mensaje: `feat(marketing): wire mkt_leads afterListRows hook and virtual columns`

---

### Task 5: Regresión, smoke responsive, empty state y PR (P5, U6, R1)

**Repository:** `Parzival2103/Lebytek_Portal`

**Branch:** `feature/mkt-leads-after-list-rows`

**Depends on:** Task 4

**Files:**
- Test: suite Portal completa

**Interfaces:**
- Consumes: Tasks 1–4
- Produces: PR hacia `main`; evidencia smoke R1; U6 via framework `list_empty` partial

- [ ] **Step 1: Escribir el test que falla** — N/A.

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php MktLeadsListEnrichment` pre-Task 3 / Expected: FAIL (evidencia PR body).

- [ ] **Step 3: Implementar el cambio mínimo** — N/A (U6: framework CRUD ya renderiza empty state accionable vía `src/Presentation/Views/partials/crud/list_empty.php` — «No hay registros» + hint «Crea un registro o ajusta filtros». Verificar sin override Portal; CTA específico «crear lead» es opcional futuro).

- [ ] **Step 4: Verificación enfocada** — Run:

```bash
php tests/run.php MktLeadsListEnrichment
php tests/run.php FrameworkVersionGate
php tests/run.php
grep -R "vendor/lebytek/framework" .gitignore composer.json >/dev/null && echo vendor_readonly_ok
```

Expected: tests verdes; `vendor_readonly_ok`; sin commits bajo `vendor/`.

- [ ] **Step 5: Regresión relevante** — Smoke manual admin (**Requiere operador humano:** sí):

1. Login admin → `/admin/crud/mkt_leads`
2. Fila con `api_tenant_public_id`: columnas «Estado WhatsApp» / «Actividad tenant» pobladas (U1)
3. Fila sin tenant: celdas «—» (U1)
4. Simular API caída (token inválido): listado renderiza sin 500; fallback BD visible (U2)
5. Viewport **320px** y **768px**: `table-responsive` presente; columnas `id`/`email` visibles; virtuales colapsan según `priority` (R1)
6. Listado vacío: empty state con hint accionable (U6)

Expected: sin error 500; toolbar usable; scroll horizontal aceptable en secundarias.

- [ ] **Step 6: Commit / PR**

```bash
git push -u origin feature/mkt-leads-after-list-rows
gh pr create --base main --title "feat(marketing): enrich mkt_leads list via afterListRows hook" \
  --body "Cierra P1–P5 + U1–U7/R1 del spec 2026-08-03.

- composer.lock → lebytek/framework >= v1.2.3
- MktLeadsListEnrichmentHandler + crud_handlers + mkt_leads.json
- Tests con mock API; degradación 2s; sin edits vendor/
- Framework prerrequisito: tag v1.2.3 @ 041e402 (#74)"
```

---

## Fuera de alcance

| ID | Tema | Motivo |
|----|------|--------|
| F-hook, F-semver | Framework hook/release | Satisfecho @ `041e402` |
| Deploy lebytek.com VPS | Ops | Operador manual |
| M3–M5, D6, D7 | RBAC/CI/skeleton | Backlog Framework |
| CF3–CF4, CF6–CF10 | UX global | Carry-forward spec |
| Cache Redis TTL (enfoque C) | Portal | Optimización futura |
| Endpoint batch tenants | WhatsApi | Mejora futura |
| Edits `Lebytek_Framework` | FPS | Negocio Portal-only |

## Criterios finales de aceptación

- [x] `composer.lock` referencia `lebytek/framework` ≥ **`v1.2.2`** (preferible **`v1.2.3`**) — Portal #27 + gate test #28.
- [x] Handler registrado; JSON declara hook whitelist (`mkt_leads` — varianza vs `mkt_leads_enrich`).
- [x] `php tests/run.php MktLeadsListEnrich` PASS (4 tests enrich + CrudConfigs).
- [x] `php tests/run.php FrameworkVersionGate` PASS.
- [x] Columnas virtuales U1 (`wa_estado`, `tenant_actividad`); fallo API → fallback U2 + hint U3.
- [ ] Smoke R1 en 320px y 768px (**Requiere operador humano:** sí) — pendiente.
- [x] Sin edits en `vendor/lebytek/framework`.
- [x] Frontera FPS: handler en `App\`, cliente en `App\Infrastructure\Integrations\`.

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Portal prod en framework v1.1.0 | Task 1 bump lock primero |
| Latencia API N×getTenant | Presupuesto 2s; fallback BD |
| Rutas Portal difieren | Operador verifica paths en clone |
| gh 404 bloquea automation | Plan completo; ejecución manual |
| U3 hint no visible en UI | `wa_estado_hint` metadata; extender vista Portal futuro |

**Rollback:** quitar hook JSON + entrada `crud_handlers.php`; revert composer bump si necesario.

## Evidencia que debe recopilar el ejecutor

- Salida `composer show lebytek/framework` post-bump.
- Salida tests pre/post fix.
- Captura listado `mkt_leads` 320px y 768px con columnas enriquecidas.
- SHA commit y número PR Portal.

---

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Plan creado UTC | 2026-08-03T12:40:00Z |
| `origin/main` Framework | `860284f7e2af129603164763b386366d40531dab` |
| `origin/main` Portal | `ee2910379801b3405c23b9b9ccf54bc6fa015137` |
| Tareas | **5/5 código** (smoke R1 DEFERRED operador) |
| Modo fuente | normal |
| Siguiente tarea ejecutable | Ninguna de código — smoke admin R1 (operador) |
| Prerrequisitos | Clone Portal; Framework ≥ v1.2.3 — **satisfecho** |
| Bloqueos | Smoke R1 320/768px requiere operador |
| Estado | **Completo (código)** — Portal PR [#28](https://github.com/Parzival2103/Lebytek_Portal/pull/28) mergeado 2026-08-03T15:15:41Z |
| Varianza vs plan literal | Handler `MktLeadsListEnrichHandler` + clave `mkt_leads` + batch `getDemoLeadsSnapshot` (base `75554de`); gap-fill U2/U3 + `tenant_actividad` + `FrameworkVersionGateTest` en #28. No se usó N×`getTenant` ni rename a `mkt_leads_enrich`. |
| Evidencia tests | `php tests/run.php Marketing` → 335 passed, 0 failed (workstation 2026-08-03) |
