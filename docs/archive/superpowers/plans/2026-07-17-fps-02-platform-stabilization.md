# FPS Plan 02 — Estabilización aislada de plataforma

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir la caché de instancia de `ConfiguracionService`, caracterizar seeds/migraciones legacy **sin borrarlas**, y dejar el subset **`ConfiguracionServiceCache`** en **`0 failed`**. La suite completa se ejecuta como **diagnóstico** contra el baseline de Plan 00 (no debe empeorar).

**Architecture:** `ConfiguracionService` debe hidratar la caché desde el repositorio antes de mutaciones parciales (`set` / `setMultiple`). Los directorios `database/seeds_legacy/` y `database/migrations_legacy/` quedan inventariados como referencia histórica; la decisión sobre legacy se documenta en Plan 03 (sin archivo destructivo).

**Tech Stack:** PHP 8.1+, microtest (`php tests/run.php`), PDO opcional para tests Install.

**Spec:** `docs/superpowers/specs/2026-07-15-framework-portal-separation-design.md` (deuda D2/D8 — resuelta en Plan 03)

**Roadmap:** `docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md` — Plan 02

**Predecesor obligatorio:** Plan 01 — Payments genérico verde en rama `consolidation/framework-portal-separation`.

**Sucesor:** Plan 03 (`PackagePaths`, SQL e Installer — roadmap 2026-07-17).

## Global Constraints

- Rama: **`consolidation/framework-portal-separation`**.
- **Prohibido** archivar, mover ni eliminar archivos en `database/seeds_legacy/` o `database/migrations_legacy/` en este plan.
- **Prohibido** merge feature→main, deploy, push remoto, editar `vendor/`.
- **Gate bloqueante (Plan 02):** `php tests/run.php ConfiguracionServiceCache` → **`0 failed`**.
- **Gate bloqueante (Plan 02):** `php tests/run.php Payments` → **`0 failed`** (heredado de Plan 01; no regresar).
- **Gate diagnóstico (no bloqueante si cumple criterio):** suite completa `php tests/run.php` — ver Task 3 Step 3.
- No introducir bindings Marketing ni paths `app/**` de Portal.

---

### Task 1: Corregir caché de ConfiguracionService (TDD)

**Files:**
- Modify: `src/Application/Services/ConfiguracionService.php`
- Create: `tests/Kernel/ConfiguracionServiceCacheTest.php`

**Interfaces:**
- Consumes: `ConfiguracionRepositoryInterface` (fake en tests).
- Produces: `ConfiguracionService::set()` y `setMultiple()` que preservan claves existentes en caché tras primera mutación sin lectura previa.

- [ ] **Step 1: Write the failing test**

Create `tests/Kernel/ConfiguracionServiceCacheTest.php`:

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Domain\Interfaces\ConfiguracionRepositoryInterface;

final class ArrayConfiguracionRepository implements ConfiguracionRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data = []) {}

    public function get(string $clave, mixed $default = null): mixed
    {
        return $this->data[$clave] ?? $default;
    }

    public function set(string $clave, mixed $valor): void
    {
        $this->data[$clave] = $valor;
    }

    /** @param array<string, mixed> $datos */
    public function setMultiple(array $datos): void
    {
        foreach ($datos as $clave => $valor) {
            $this->data[$clave] = $valor;
        }
    }

    public function all(): array
    {
        return $this->data;
    }
}

test('set hidrata cache desde repo antes de mutar si cache vacia', function (): void {
    $repo = new ArrayConfiguracionRepository([
        'empresa_nombre' => 'Lebytek Original',
        'dark_mode' => '0',
    ]);
    $service = new ConfiguracionService($repo);
    $service->set('primary_color', '#112233');
    assert_same('Lebytek Original', $service->get('empresa_nombre'));
    assert_same('#112233', $service->get('primary_color'));
});

test('setMultiple hidrata cache desde repo antes de mutar si cache vacia', function (): void {
    $repo = new ArrayConfiguracionRepository([
        'empresa_nombre' => 'Lebytek Original',
    ]);
    $service = new ConfiguracionService($repo);
    $service->setMultiple([
        'primary_color' => '#445566',
        'dark_mode' => '1',
    ]);
    assert_same('Lebytek Original', $service->get('empresa_nombre'));
    assert_same('#445566', $service->get('primary_color'));
    assert_same('1', $service->get('dark_mode'));
});

test('toggleDarkMode no pierde otras claves en cache', function (): void {
    $repo = new ArrayConfiguracionRepository([
        'dark_mode' => '0',
        'empresa_nombre' => 'Lebytek',
    ]);
    $service = new ConfiguracionService($repo);
    $nuevo = $service->toggleDarkMode();
    assert_same('1', $nuevo);
    assert_same('Lebytek', $service->get('empresa_nombre'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php ConfiguracionServiceCache
```

Expected: FAIL — `empresa_nombre` devuelve `null` o default tras `set()` sin lectura previa (caché parcial).

- [ ] **Step 3: Implement minimal fix in ConfiguracionService**

Replace `set` and `setMultiple` in `src/Application/Services/ConfiguracionService.php`:

```php
    public function set(string $clave, mixed $valor): void
    {
        if ($this->cache === null) {
            $this->cargarCache();
        }
        $this->configuracionRepo->set($clave, $valor);
        $this->cache[$clave] = $valor;
    }

    public function setMultiple(array $datos): void
    {
        if ($this->cache === null) {
            $this->cargarCache();
        }
        $this->configuracionRepo->setMultiple($datos);
        foreach ($datos as $clave => $valor) {
            $this->cache[$clave] = $valor;
        }
    }
```

No modificar `get()`, `all()` ni `cargarCache()`.

- [ ] **Step 4: Run test to verify it passes**

Run:

```powershell
php tests/run.php ConfiguracionServiceCache
php tests/run.php Auth
```

Expected: `0 failed` en ambos (Auth usa `ConfiguracionService` en varios tests).

- [ ] **Step 5: Commit**

```powershell
git add src/Application/Services/ConfiguracionService.php tests/Kernel/ConfiguracionServiceCacheTest.php
git commit -m "fix(config): hydrate ConfiguracionService cache before partial writes"
```

---

### Task 2: Inventario caracterización seeds/migraciones legacy (sin borrar)

**Files:**
- Create: `docs/superpowers/LEGACY-seeds-migrations-inventory.md`
- Modify: none (no mover/borrar legacy)

**Interfaces:**
- Consumes: árbol `database/seeds_legacy/`, `database/migrations_legacy/`, `database/migrations/README.md`.
- Produces: documento de caracterización con conteos, propósito y política “no delete until Plan 03 greenfield”.

- [ ] **Step 1: Generar listado automatizado de legacy**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
$seedFiles = Get-ChildItem -Recurse database/seeds_legacy -File | ForEach-Object { $_.FullName.Replace((Get-Location).Path + '\', '').Replace('\','/') }
$migFiles = Get-ChildItem -Recurse database/migrations_legacy -File -Filter *.sql | ForEach-Object { $_.FullName.Replace((Get-Location).Path + '\', '').Replace('\','/') }
"seeds_count=$($seedFiles.Count) migrations_legacy_sql=$($migFiles.Count)"
$seedFiles | Out-File docs/superpowers/FPS-legacy-seeds-list.txt -Encoding utf8
$migFiles | Out-File docs/superpowers/FPS-legacy-migrations-list.txt -Encoding utf8
```

Expected: `seeds_count` ≥ 6; `migrations_legacy_sql` ≥ 8; dos archivos list en `docs/superpowers/`.

- [ ] **Step 2: Escribir documento de inventario**

Create `docs/superpowers/LEGACY-seeds-migrations-inventory.md`:

```markdown
# Legacy seeds y migraciones — inventario FPS (caracterización)

**Fecha:** 2026-07-17  
**Plan:** 02 — Estabilización plataforma  
**Política:** **No archivar ni eliminar** hasta Plan 03 (instalación greenfield con evidencia).

## Contexto

El bootstrap greenfield actual usa `database/schema/schema.sql` + módulos en `database/schema/modules/`. Los scripts incrementales de junio 2026 se consolidaron; copias de referencia permanecen en legacy.

## seeds_legacy

| Path | Rol | Usado por instalador actual |
|------|-----|----------------------------|
| `database/seeds_legacy/baseline-2026-06/` | Copia seeds `010`–`035` pre-consolidación | **No** — ver README interno |
| Archivos | `010_auth_permisos.sql`, `015_core_menu_items.sql`, `020_auth_roles.sql`, `025_auth_roles_permisos.sql`, `030_auth_usuario_admin.sql`, `035_cfg_configuraciones.sql` | Referencia histórica |

Lista completa: `docs/superpowers/FPS-legacy-seeds-list.txt`

## migrations_legacy

| Path | Rol | Usado por instalador actual |
|------|-----|----------------------------|
| `database/migrations_legacy/incrementales-2026-06/` | Incrementales pre-consolidación | **No** |
| `database/migrations_legacy/*.sql` (raíz) | Scripts sueltos archivados | **No** |

Lista completa: `docs/superpowers/FPS-legacy-migrations-list.txt`

## Migraciones activas (no legacy)

- Directorio: `database/migrations/` — post-baseline incremental
- Reglas: `database/migrations/README.md`
- Manifiesto: cada archivo en `config/modules/*.php`
- Migraciones `*mkt*`: **negocio Portal** — no son SoT del paquete (Plan 05/06)

## Decisión diferida (Plan 03)

Tras instalar greenfield vía `PackagePaths` + `install.php`:

1. Confirmar que seeds plataforma vienen del paquete (`PackagePaths::seedsDir()`).
2. Documentar retención de legacy en `FPS-legacy-archival-decision.md` (Plan 03). **No** archivar ni eliminar físicamente en el roadmap FPS 00–08.

## Comandos de verificación (solo lectura)

```powershell
Get-ChildItem -Recurse database/seeds_legacy -File
Get-ChildItem -Recurse database/migrations_legacy -File -Filter *.sql
Get-ChildItem database/migrations -File -Filter *.sql
```
```

- [ ] **Step 3: Verificar que no se movió legacy**

Run:

```powershell
Test-Path database/seeds_legacy/baseline-2026-06/README.md
Test-Path database/migrations_legacy/README.md
git status --short database/seeds_legacy database/migrations_legacy
```

Expected: `True`, `True`; sin deletes en git status para esos directorios.

- [ ] **Step 4: Commit**

```powershell
git add docs/superpowers/LEGACY-seeds-migrations-inventory.md docs/superpowers/FPS-legacy-seeds-list.txt docs/superpowers/FPS-legacy-migrations-list.txt
git commit -m "docs(fps): characterize legacy seeds and migrations without deletion"
```

---

### Task 3: Gate ConfiguracionService + diagnóstico suite completa

**Files:**
- Modify: solo si algún fallo **nuevo** (no documentado) aparece tras Plans 00–01

**Interfaces:**
- Consumes: Tasks 1–2; Plan 01 Payments verde; baseline `M_failed` anotado en `docs/superpowers/FPS-git-baseline.md` (Plan 00).
- Produces: reporte SDD con conteos; subset ConfiguracionServiceCache verde; suite completa comparada contra baseline.

- [ ] **Step 1: Asegurar dependencias Composer**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
composer install --no-interaction
composer dump-autoload
```

Expected: exit 0; `vendor/stripe/stripe-php` presente si Plan 01 aplicado.

- [ ] **Step 2: Ejecutar gates bloqueantes de Plan 02**

Run:

```powershell
php tests/run.php ConfiguracionServiceCache
php tests/run.php Payments
php tests/run.php Kernel
php tests/run.php Auth
```

Expected: **cada comando** termina con `0 failed`. Si `ConfiguracionServiceCache` falla, volver a Task 1 — no continuar.

- [ ] **Step 3: Ejecutar suite completa (diagnóstico vs baseline)**

Run:

```powershell
$out = php tests/run.php 2>&1 | Out-String
$out | Select-Object -Last 5
if ($out -match '(\d+) passed, (\d+) failed') {
  $passed = [int]$Matches[1]; $failed = [int]$Matches[2]
  Write-Output "DIAG passed=$passed failed=$failed"
} else { Write-Error "Could not parse test summary"; exit 1 }
```

Leer `M_baseline` de `docs/superpowers/FPS-git-baseline.md` (sección *Tests baseline en main*).

**Criterio exacto (Plan 02):**

| Condición | Bloquea Plan 02 |
|-----------|-----------------|
| `ConfiguracionServiceCache` → `0 failed` | **Sí** (Step 2) |
| `Payments` → `0 failed` | **Sí** (Plan 01) |
| `Kernel` / `Auth` → `0 failed` | **Sí** (Step 2) |
| Suite completa: `M_failed <= M_baseline` | **Sí** si empeora |
| Suite completa: `M_failed > 0` solo en filtros D2/D8 preexistentes | **No**, si están listados abajo y el conteo no subió |
| Cualquier fallo nuevo no listado | **Sí** |

**Filtros D2/D8 preexistentes (esperados con fallo hasta Plan 03):** si existen en el runner, anotar en SDD — típicamente `PackagePaths`, `PlatformSqlResolve`, `InstallGreenfield`, subsets `Install` que dependen de `PackagePaths::resolveDataFile`. No silenciar ni desactivar tests.

Si `M_failed > M_baseline`: corregir solo regresiones introducidas por Plans 01–02; no “arreglar” D2/D8 aquí.

Si fallan tests Stripe con `Class "Stripe\Stripe" not found`: ejecutar Step 1 y repetir Step 2.

- [ ] **Step 4: Registrar progreso SDD y commit si hubo fix**

Append to `.superpowers/sdd/progress.md`:

```markdown
## Plan 02 — Platform stabilization (2026-07-17)

- [x] ConfiguracionService cache fix + characterization tests
- [x] LEGACY seeds/migrations inventory (no deletion)
- Gate ConfiguracionServiceCache: 0 failed
- Gate Payments: 0 failed
- Gate Kernel: 0 failed
- Gate Auth: 0 failed
- Gate full suite (diagnostic): `<N>` passed, `<M>` failed (baseline `M_baseline=<M0>`; D2/D8 preexisting: <list or none>)
- Siguiente: Plan 03 PackagePaths + Installer
```

```powershell
git add .superpowers/sdd/progress.md
git commit -m "docs(fps): record Plan 02 green suite gate"
```

---

## Self-review (author)

| Requisito roadmap Plan 02 | Task |
|---------------------------|------|
| Corregir caché ConfiguracionService | Task 1 |
| Tests Kernel/Auth/install afectados | Task 3 Steps 2–3 |
| Inventariar seeds/migraciones legacy | Task 2 |
| No archivar/eliminar en Plan 02 | Task 2 policy + Step 3 |
| Gate `ConfiguracionServiceCache` 0 failed | Task 3 Step 2 |
| Suite completa diagnóstico `M_failed <= M_baseline` | Task 3 Step 3 |
| No merge/deploy/vendor/secrets | Global Constraints |

Placeholder scan: sin TBD/TODO/"similar a".  
Consistencia: métodos `set`/`setMultiple` hidratan antes de mutar; paths legacy documentados con listas FPS-legacy-*.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-17-fps-02-platform-stabilization.md`. Two execution options:

**1. Subagent-Driven (recommended)** — fresh subagent per task, review between tasks

**2. Inline Execution** — executing-plans with checkpoints

**Which approach?**
