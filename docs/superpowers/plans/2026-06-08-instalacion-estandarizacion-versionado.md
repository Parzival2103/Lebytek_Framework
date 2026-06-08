# Instalación, Estandarización y Versionado — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convertir cada despliegue del framework Lebytek en algo autodescriptivo, versionado y reproducible mediante manifiestos de módulo (PHP), tracking de migraciones/seeds, un motor de instalación puro en `Application`, un wizard web guiado, una página admin de estado y un CLI — todo sobre la misma lógica.

**Architecture:** Enfoque A del spec: registro central (`cfg_migraciones`, `cfg_modulos`) + manifiestos PHP livianos en `config/modules/`. La lógica vive en `Application/Install` (pura, testeable con fakes), los contratos en `Domain`, el acceso a BD/FS en `Infrastructure`, y las superficies (wizard `public/install/`, página `/admin/sistema/estado`, CLI `scripts/`) son delgadas y comparten el mismo `Installer` / `DeploymentStatus`. Los manifiestos **referencian** recursos (cruds, permisos, menú) para inventario/validación; el runtime sigue cargándolos desde `config/*` (sin doble fuente de verdad).

**Tech Stack:** PHP 8.1+, PDO/MySQL, autoloader PSR-4 propio (`App\` → `app/`), harness de tests propio (`tests/run.php` + `tests/lib/microtest.php`), sin Composer en runtime de instalación.

---

## Contexto de la base de código (leer antes de empezar)

Patrones reales que este plan reutiliza — **respétalos exactamente**:

- **Tests:** harness propio. Cada archivo `tests/**/*Test.php` registra casos con `test('nombre', fn)` y asserts `assert_same`, `assert_true`, `assert_null`, `assert_throws`. Se corre con `php tests/run.php [filtro]`. Bootstrap (`tests/lib/bootstrap.php`) define `ROOT_PATH`/`APP_PATH` y carga `app/Kernel/Autoloader.php`. Los validadores existentes exponen métodos **estáticos** que devuelven `string[]` de errores (ver `CrudConfigValidator::statesBlockErrors`) — seguimos ese estilo para `ManifestValidator`.
- **Fakes:** se definen como clases en `tests/fixtures/*.php` y se incluyen con `require` desde el test (ver `tests/fixtures/relation_repos.php`).
- **Repositorios:** extienden `App\Kernel\BaseClasses\BaseRepository` (helpers `query`, `queryOne`, `execute`, `insert`), declaran `protected string $table`, e implementan una interfaz de `app/Domain/Interfaces/`.
- **Exceptions de dominio:** en `app/Domain/Exceptions/` (ver `ValidationException`).
- **DI:** `config/container.php` registra singletons con closures `fn(Container $c) => new X(...)`.
- **Rutas:** `routes/web.php`, grupo `/admin` con `AuthMiddleware` y por-ruta `new RbacMiddleware('slug')`.
- **Controladores admin:** extienden `App\Presentation\Controllers\AdminBaseController`, su constructor llama `parent::__construct($configuracionService, $adminNavigationMenuService)` y renderizan con `$this->view('admin/x/y', [...])`.
- **Seeds SQL:** `INSERT IGNORE`, archivos numerados (`010_…`, `015_…`). El rol `administrador` recibe **todos** los permisos por `CROSS JOIN` en `025_auth_roles_permisos.sql`, así que un permiso nuevo añadido a `010_auth_permisos.sql` queda asignado al admin automáticamente.
- **Migraciones:** `database/migrations/YYYYMMDDHHMMSS_descripcion.sql`.
- **Runner SQL multi-statement:** la función `splitSqlStatements()` de `scripts/seed.php` es la referencia probada para partir sentencias en hosting compartido.

---

## File Structure

**Configuración / datos**
- `config/app.php` — *(modificar)* añadir `'version' => '1.0.0'`.
- `config/modules/core.php` — *(crear)* manifiesto core (reclama schema base + migraciones/seeds de plataforma).
- `config/modules/dashboard.php` — *(crear)* manifiesto dashboard.
- `config/modules/crud-engine.php` — *(crear)* manifiesto del demo CRUD.
- `database/schema/schema.sql` — *(modificar)* añadir `cfg_migraciones` y `cfg_modulos`.
- `database/seeds/010_auth_permisos.sql` — *(modificar)* añadir permiso `sistema.ver`.
- `database/seeds/015_core_menu_items.sql` — *(modificar)* añadir ítem de menú "Estado del sistema".

**Domain**
- `app/Domain/Interfaces/MigrationRepositoryInterface.php` — *(crear)*
- `app/Domain/Interfaces/ModuleStateRepositoryInterface.php` — *(crear)*
- `app/Domain/Exceptions/InstallerException.php` — *(crear)*

**Infrastructure**
- `app/Infrastructure/Install/SqlFileRunner.php` — *(crear)* lee/ejecuta `.sql` + sha256.
- `app/Infrastructure/Repositories/MigrationRepository.php` — *(crear)*
- `app/Infrastructure/Repositories/ModuleStateRepository.php` — *(crear)*

**Application/Install**
- `app/Application/Install/ModuleManifest.php` — *(crear)* VO inmutable.
- `app/Application/Install/ModuleRegistry.php` — *(crear)* carga `config/modules/*.php`.
- `app/Application/Install/ManifestValidator.php` — *(crear)* validación estática.
- `app/Application/Install/DependencyResolver.php` — *(crear)* orden topológico.
- `app/Application/Install/InstallPlan.php` — *(crear)* VO del plan.
- `app/Application/Install/Installer.php` — *(crear)* orquestador.
- `app/Application/Install/DeploymentStatus.php` — *(crear)* view-model de estado.

**Presentation**
- `app/Presentation/Controllers/Admin/SistemaEstadoController.php` — *(crear)*
- `app/Presentation/Views/admin/sistema/estado.php` — *(crear)*
- `public/install/index.php` — *(crear)* front controller del wizard.
- `public/install/views/_layout.php`, `paso_requisitos.php`, `paso_bd.php`, `paso_modulos.php`, `paso_admin.php`, `paso_revision.php`, `paso_resultado.php`, `ya_instalado.php` — *(crear)* vistas del wizard.

**Scripts**
- `scripts/install.php` — *(reescribir)* sobre `Installer` (flags `--modules`, `--dry-run`, `--baseline`).
- `scripts/status.php` — *(crear)* imprime `DeploymentStatus` en texto.

**DI / rutas**
- `config/container.php` — *(modificar)* bindings additivos.
- `routes/web.php` — *(modificar)* ruta `/admin/sistema/estado`.

**Tests**
- `tests/fixtures/install_repos.php` — *(crear)* fakes en memoria + helpers de fixtures SQL.
- `tests/Install/ModuleManifestTest.php`, `ModuleRegistryTest.php`, `ManifestValidatorTest.php`, `DependencyResolverTest.php`, `InstallerPlanTest.php`, `InstallerBaselineTest.php`, `DeploymentStatusTest.php`, `EstandarizacionIntegridadTest.php` — *(crear)*

**Docs**
- `docs/core/instalacion-y-versionado.md` — *(crear)*
- `docs/core/despliegue_hosting.md`, `docs/core/vertical-onboarding.md` — *(modificar)* notas de remisión.

---

## Fase 0 — Datos, versión de plataforma y manifiestos

### Task 1: Versión de plataforma en `config/app.php`

**Files:**
- Modify: `config/app.php`

- [ ] **Step 1: Añadir la clave `version`**

En `config/app.php`, dentro del `return [...]`, añade la línea tras `'name'`:

```php
    'name'     => EnvLoader::get('APP_NAME', 'Sistema Administrativo'),
    'version'  => '1.0.0',
    'env'      => EnvLoader::get('APP_ENV',  'production'),
```

- [ ] **Step 2: Verificar que carga sin error de sintaxis**

Run: `php -r "require 'config/app.php'; echo 'ok';"`
Expected: imprime `ok` (sin warnings).

- [ ] **Step 3: Commit**

```bash
git add config/app.php
git commit -m "feat(install): versión de plataforma en config/app.php"
```

---

### Task 2: Tablas de versionado en el schema base

**Files:**
- Modify: `database/schema/schema.sql`

- [ ] **Step 1: Añadir las tablas `cfg_*` al final del schema**

Añade al final de `database/schema/schema.sql` (idempotentes con `IF NOT EXISTS`):

```sql
-- ─────────────────────────────────────────────────────────────────────────
-- Versionado de instalación (instalador / estado del sistema)
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `cfg_migraciones` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modulo`      VARCHAR(64)  NOT NULL,
  `archivo`     VARCHAR(255) NOT NULL,
  `checksum`    CHAR(64)     NOT NULL,
  `aplicada_en` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cfg_migraciones_archivo` (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cfg_modulos` (
  `clave`          VARCHAR(64) NOT NULL,
  `version`        VARCHAR(20) NOT NULL,
  `activo`         TINYINT(1)  NOT NULL DEFAULT 1,
  `instalado_en`   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Verificar que el archivo no tiene errores triviales**

Run: `php -r "$s=file_get_contents('database/schema/schema.sql'); echo (str_contains($s,'cfg_migraciones') && str_contains($s,'cfg_modulos')) ? 'ok' : 'FALTA';"`
Expected: `ok`

- [ ] **Step 3: Commit**

```bash
git add database/schema/schema.sql
git commit -m "feat(install): tablas cfg_migraciones y cfg_modulos en schema base"
```

---

### Task 3: Permiso y menú `sistema.ver` en seeds existentes

**Files:**
- Modify: `database/seeds/010_auth_permisos.sql`
- Modify: `database/seeds/015_core_menu_items.sql`

Se añaden a los seeds **de core** para que el orden idempotente funcione (el permiso debe existir antes del `CROSS JOIN` de `025_auth_roles_permisos.sql`, y `010 < 025`).

- [ ] **Step 1: Añadir el permiso `sistema.ver`**

En `database/seeds/010_auth_permisos.sql`, añade una fila al `VALUES` (antes del `;` final, agregando la coma a la fila previa):

```sql
  ('Eliminar clientes', 'clientes.eliminar', 'clientes'),
  ('Ver estado del sistema', 'sistema.ver', 'sistema');
```

- [ ] **Step 2: Añadir el ítem de menú "Estado del sistema" bajo Administración**

En `database/seeds/015_core_menu_items.sql`, dentro del subquery `FROM (... ) AS r` del segundo `INSERT`, añade una fila `UNION ALL` (antes del `) AS r`):

```sql
      UNION ALL SELECT 20, 'administracion_roles', 'Roles y permisos', 'bi-key', '/admin/administracion/roles', 'roles.gestionar'
      UNION ALL SELECT 30, 'administracion_ajustes', 'Ajustes', 'bi-gear', '/admin/ajustes', 'administracion.ver'
      UNION ALL SELECT 40, 'sistema_estado', 'Estado del sistema', 'bi-hdd-stack', '/admin/sistema/estado', 'sistema.ver') AS `r`
```

- [ ] **Step 3: Verificar que ambos archivos referencian el slug**

Run: `php -r "echo (str_contains(file_get_contents('database/seeds/010_auth_permisos.sql'),'sistema.ver') && str_contains(file_get_contents('database/seeds/015_core_menu_items.sql'),'sistema_estado')) ? 'ok' : 'FALTA';"`
Expected: `ok`

- [ ] **Step 4: Commit**

```bash
git add database/seeds/010_auth_permisos.sql database/seeds/015_core_menu_items.sql
git commit -m "feat(install): permiso y menú sistema.ver para la página de estado"
```

---

### Task 4: Manifiestos de módulo (`core`, `dashboard`, `crud-engine`)

**Files:**
- Create: `config/modules/core.php`
- Create: `config/modules/dashboard.php`
- Create: `config/modules/crud-engine.php`

Cada migración/seed real debe quedar con **dueño único**. Inventario actual:

- Migraciones (`database/migrations/`): `20260427120000_core_menu_items.sql`, `20260428132500_crud_engine_demo_resources.sql`, `20260428132600_drop_crud_engine_demo_resources.sql`, `20260428133000_crud_demo_menu_parent_perm_null.sql`, `20260502120000_menu_rbac_granular_admin_subitems.sql`, `20260502150000_auth_permisos_dom_clientes.sql`, `20260503100000_deprecate_legacy_domain_permissions_and_menus.sql`, `20260607120000_crud_engine_demo_showcase.sql`.
- Seeds (`database/seeds/`): `010_auth_permisos.sql`, `015_core_menu_items.sql`, `020_auth_roles.sql`, `025_auth_roles_permisos.sql`, `030_auth_usuario_admin.sql`, `035_cfg_configuraciones.sql`.
- Cruds JSON demo (`config/cruds/`): `demo_clientes`, `demo_productos`, `demo_categorias`, `demo_pedidos` (las claves que existan; ver Step 4 de verificación).

Reparto: **core** = todo lo de plataforma (auth/menú/ajustes/clientes-legacy) y todos los seeds. **crud-engine** = solo las migraciones y cruds del demo. **dashboard** = sin migraciones propias (solo providers declarados).

> ⚠️ Antes de escribir, confirma los nombres exactos de los `.sql` con:
> `php -r "foreach(glob('database/migrations/*.sql') as $f) echo basename($f).PHP_EOL;"`
> y ajusta las listas si difieren. El test de la Task 19 fallará si hay archivos sin dueño o con doble dueño — es la red de seguridad.

- [ ] **Step 1: Crear `config/modules/core.php`**

```php
<?php

declare(strict_types=1);

// Manifiesto del módulo core. Reclama el schema base + toda la plataforma
// (auth, menú, ajustes). Obligatorio: ningún deploy existe sin core.
return [
    'clave'       => 'core',
    'nombre'      => 'Núcleo de plataforma',
    'descripcion' => 'Autenticación, RBAC, menú dinámico, ajustes y configuración base.',
    'version'     => '1.0.0',
    'obligatorio' => true,
    'requiere'    => [],
    'migraciones' => [
        '20260427120000_core_menu_items.sql',
        '20260428133000_crud_demo_menu_parent_perm_null.sql',
        '20260502120000_menu_rbac_granular_admin_subitems.sql',
        '20260502150000_auth_permisos_dom_clientes.sql',
        '20260503100000_deprecate_legacy_domain_permissions_and_menus.sql',
    ],
    'seeds' => [
        '010_auth_permisos.sql',
        '015_core_menu_items.sql',
        '020_auth_roles.sql',
        '025_auth_roles_permisos.sql',
        '030_auth_usuario_admin.sql',
        '035_cfg_configuraciones.sql',
    ],
    'cruds'     => [],
    'permisos'  => [
        'administracion.ver', 'usuarios.gestionar', 'roles.gestionar',
        'bitacora.ver', 'dashboard.ver', 'sistema.ver',
    ],
    'menu'      => ['dashboard', 'administracion'],
    'providers' => [],
];
```

- [ ] **Step 2: Crear `config/modules/dashboard.php`**

```php
<?php

declare(strict_types=1);

// Manifiesto del módulo dashboard. No posee migraciones propias; declara
// los providers de contribución para inventario en la página de estado.
return [
    'clave'       => 'dashboard',
    'nombre'      => 'Dashboard',
    'descripcion' => 'Panel principal extensible mediante providers de contribución.',
    'version'     => '1.0.0',
    'obligatorio' => false,
    'requiere'    => ['core'],
    'migraciones' => [],
    'seeds'       => [],
    'cruds'       => [],
    'permisos'    => ['dashboard.ver'],
    'menu'        => ['dashboard'],
    'providers'   => [],
];
```

- [ ] **Step 3: Crear `config/modules/crud-engine.php`**

```php
<?php

declare(strict_types=1);

// Manifiesto del módulo CRUD Engine (demo/showcase). Opcional: un deploy que
// no lo quiera queda sin estas tablas demo. Los recursos siguen cargándose
// desde config/cruds/*.json; aquí solo se referencian para inventario.
return [
    'clave'       => 'crud-engine',
    'nombre'      => 'CRUD Engine (demo)',
    'descripcion' => 'Motor CRUD genérico dirigido por JSON + showcase con relaciones, estados y validaciones.',
    'version'     => '1.0.0',
    'obligatorio' => false,
    'requiere'    => ['core'],
    'migraciones' => [
        '20260428132500_crud_engine_demo_resources.sql',
        '20260428132600_drop_crud_engine_demo_resources.sql',
        '20260607120000_crud_engine_demo_showcase.sql',
    ],
    'seeds'     => [],
    'cruds'     => ['demo_clientes', 'demo_productos', 'demo_categorias', 'demo_pedidos'],
    'permisos'  => [],
    'menu'      => [],
    'providers' => [],
];
```

- [ ] **Step 4: Verificar carga y reparto exhaustivo de archivos**

Run:
```bash
php -r "$o=[]; foreach(glob('config/modules/*.php') as $f){ $m=require $f; foreach(array_merge($m['migraciones'],$m['seeds']) as $a){ $o[$a]=($o[$a]??0)+1; } } $files=array_merge(array_map('basename',glob('database/migrations/*.sql')),array_map('basename',glob('database/seeds/*.sql'))); $sin=array_diff($files,array_keys($o)); $dup=array_filter($o,fn($n)=>$n>1); echo 'huerfanos: '.implode(',',$sin).' | duplicados: '.implode(',',array_keys($dup));"
```
Expected: `huerfanos:  | duplicados: ` (ambas listas vacías). Si no, ajusta los manifiestos.

- [ ] **Step 5: Commit**

```bash
git add config/modules/
git commit -m "feat(install): manifiestos de módulo core, dashboard y crud-engine"
```

---

## Fase 1 — Domain (contratos) e Infrastructure

### Task 5: Excepción `InstallerException`

**Files:**
- Create: `app/Domain/Exceptions/InstallerException.php`

- [ ] **Step 1: Crear la excepción**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use RuntimeException;

final class InstallerException extends RuntimeException
{
    public static function cicloDependencias(array $claves): self
    {
        return new self('Ciclo de dependencias entre módulos: ' . implode(' → ', $claves));
    }

    public static function manifiestoInvalido(string $detalle): self
    {
        return new self("Manifiesto inválido: {$detalle}");
    }
}
```

- [ ] **Step 2: Verificar sintaxis**

Run: `php -l app/Domain/Exceptions/InstallerException.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Domain/Exceptions/InstallerException.php
git commit -m "feat(install): InstallerException de dominio"
```

---

### Task 6: Interfaces de repositorio (Domain)

**Files:**
- Create: `app/Domain/Interfaces/MigrationRepositoryInterface.php`
- Create: `app/Domain/Interfaces/ModuleStateRepositoryInterface.php`

- [ ] **Step 1: Crear `MigrationRepositoryInterface`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

interface MigrationRepositoryInterface
{
    /**
     * Archivos ya aplicados.
     *
     * @return array<string,string> archivo => checksum sha256
     */
    public function aplicadas(): array;

    public function registrar(string $modulo, string $archivo, string $checksum): void;

    public function existeTabla(string $nombre): bool;
}
```

- [ ] **Step 2: Crear `ModuleStateRepositoryInterface`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

interface ModuleStateRepositoryInterface
{
    /**
     * Módulos registrados.
     *
     * @return array<string,array{version:string,activo:bool}> clave => estado
     */
    public function instalados(): array;

    public function registrar(string $clave, string $version, bool $activo): void;
}
```

- [ ] **Step 3: Verificar sintaxis**

Run: `php -l app/Domain/Interfaces/MigrationRepositoryInterface.php && php -l app/Domain/Interfaces/ModuleStateRepositoryInterface.php`
Expected: `No syntax errors detected` (×2)

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Interfaces/MigrationRepositoryInterface.php app/Domain/Interfaces/ModuleStateRepositoryInterface.php
git commit -m "feat(install): contratos MigrationRepository y ModuleStateRepository"
```

---

### Task 7: `SqlFileRunner` (Infrastructure)

**Files:**
- Create: `app/Infrastructure/Install/SqlFileRunner.php`
- Test: `tests/Install/SqlFileRunnerTest.php`

- [ ] **Step 1: Escribir el test (checksum estable, sin BD)**

```php
<?php

declare(strict_types=1);

use App\Infrastructure\Install\SqlFileRunner;

test('SqlFileRunner::checksum es sha256 del contenido del archivo', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'sql');
    file_put_contents($tmp, "SELECT 1;\n");
    $runner = new SqlFileRunner();
    assert_same(hash('sha256', "SELECT 1;\n"), $runner->checksum($tmp));
    unlink($tmp);
});

test('SqlFileRunner::partir separa sentencias e ignora comentarios y vacías', function (): void {
    $runner = new SqlFileRunner();
    $stmts = $runner->partir("-- comentario\nSELECT 1;\n\nSELECT 2;\n");
    assert_same(2, count($stmts));
    assert_same('SELECT 1;', trim($stmts[0]));
});
```

- [ ] **Step 2: Correr el test para verlo fallar**

Run: `php tests/run.php Install/SqlFileRunner`
Expected: FAIL — `Class "App\Infrastructure\Install\SqlFileRunner" not found`.

- [ ] **Step 3: Implementar `SqlFileRunner`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Install;

use App\Kernel\Database\Connection;
use RuntimeException;

/**
 * Lee y ejecuta archivos .sql multi-statement (reutiliza el partido de
 * sentencias probado en scripts/seed.php) y calcula su checksum sha256.
 */
final class SqlFileRunner
{
    public function checksum(string $ruta): string
    {
        $contenido = @file_get_contents($ruta);
        if ($contenido === false) {
            throw new RuntimeException("No se pudo leer {$ruta}");
        }
        return hash('sha256', $contenido);
    }

    /**
     * @return list<string>
     */
    public function partir(string $sql): array
    {
        $lines  = preg_split('/\R/', $sql) ?: [];
        $buffer = '';
        $out    = [];

        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '' || str_starts_with($t, '--')) {
                continue;
            }
            $buffer .= $line . "\n";
            if (preg_match('/;\s*$/', rtrim($line))) {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $out[] = $stmt;
                }
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $out[] = $tail;
        }

        return $out;
    }

    public function ejecutar(string $ruta): void
    {
        $contenido = @file_get_contents($ruta);
        if ($contenido === false) {
            throw new RuntimeException("No se pudo leer {$ruta}");
        }
        $pdo = Connection::getInstance();
        foreach ($this->partir($contenido) as $statement) {
            $pdo->exec($statement);
        }
    }
}
```

- [ ] **Step 4: Correr el test para verlo pasar**

Run: `php tests/run.php Install/SqlFileRunner`
Expected: `2 passed, 0 failed`

- [ ] **Step 5: Commit**

```bash
git add app/Infrastructure/Install/SqlFileRunner.php tests/Install/SqlFileRunnerTest.php
git commit -m "feat(install): SqlFileRunner con checksum y partido de sentencias"
```

---

### Task 8: Repositorios PDO (`MigrationRepository`, `ModuleStateRepository`)

**Files:**
- Create: `app/Infrastructure/Repositories/MigrationRepository.php`
- Create: `app/Infrastructure/Repositories/ModuleStateRepository.php`

> Estos tocan BD real; no se testean con el harness (que no abre conexión). Se ejercitan vía CLI en la Task 16. Se implementan siguiendo `ConfiguracionRepository`.

- [ ] **Step 1: Implementar `MigrationRepository`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Interfaces\MigrationRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;

final class MigrationRepository extends BaseRepository implements MigrationRepositoryInterface
{
    protected string $table = 'cfg_migraciones';

    public function aplicadas(): array
    {
        $rows = $this->query("SELECT archivo, checksum FROM cfg_migraciones");
        $out  = [];
        foreach ($rows as $row) {
            $out[(string) $row['archivo']] = (string) $row['checksum'];
        }
        return $out;
    }

    public function registrar(string $modulo, string $archivo, string $checksum): void
    {
        $this->execute(
            "INSERT INTO cfg_migraciones (modulo, archivo, checksum)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), modulo = VALUES(modulo)",
            [$modulo, $archivo, $checksum]
        );
    }

    public function existeTabla(string $nombre): bool
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS n FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?",
            [$nombre]
        );
        return $row !== null && (int) $row['n'] > 0;
    }
}
```

- [ ] **Step 2: Implementar `ModuleStateRepository`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Interfaces\ModuleStateRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;

final class ModuleStateRepository extends BaseRepository implements ModuleStateRepositoryInterface
{
    protected string $table = 'cfg_modulos';

    public function instalados(): array
    {
        $rows = $this->query("SELECT clave, version, activo FROM cfg_modulos");
        $out  = [];
        foreach ($rows as $row) {
            $out[(string) $row['clave']] = [
                'version' => (string) $row['version'],
                'activo'  => (bool) $row['activo'],
            ];
        }
        return $out;
    }

    public function registrar(string $clave, string $version, bool $activo): void
    {
        $this->execute(
            "INSERT INTO cfg_modulos (clave, version, activo)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE version = VALUES(version), activo = VALUES(activo)",
            [$clave, $version, $activo ? 1 : 0]
        );
    }
}
```

- [ ] **Step 3: Verificar sintaxis**

Run: `php -l app/Infrastructure/Repositories/MigrationRepository.php && php -l app/Infrastructure/Repositories/ModuleStateRepository.php`
Expected: `No syntax errors detected` (×2)

- [ ] **Step 4: Commit**

```bash
git add app/Infrastructure/Repositories/MigrationRepository.php app/Infrastructure/Repositories/ModuleStateRepository.php
git commit -m "feat(install): repositorios PDO de migraciones y estado de módulos"
```

---

## Fase 2 — Application: VOs, registro, validación, resolución

### Task 9: `ModuleManifest` (VO inmutable)

**Files:**
- Create: `app/Application/Install/ModuleManifest.php`
- Test: `tests/Install/ModuleManifestTest.php`

- [ ] **Step 1: Escribir el test**

```php
<?php

declare(strict_types=1);

use App\Application\Install\ModuleManifest;
use App\Domain\Exceptions\InstallerException;

test('ModuleManifest::fromArray expone propiedades y aplica defaults', function (): void {
    $m = ModuleManifest::fromArray([
        'clave'       => 'crud-engine',
        'nombre'      => 'CRUD Engine',
        'version'     => '1.0.0',
        'requiere'    => ['core'],
        'migraciones' => ['a.sql'],
    ]);
    assert_same('crud-engine', $m->clave);
    assert_same('1.0.0', $m->version);
    assert_same(false, $m->obligatorio);
    assert_same(['core'], $m->requiere);
    assert_same(['a.sql'], $m->migraciones);
    assert_same([], $m->seeds);
});

test('ModuleManifest::fromArray exige clave', function (): void {
    assert_throws(InstallerException::class, function (): void {
        ModuleManifest::fromArray(['version' => '1.0.0']);
    });
});

test('ModuleManifest::fromArray exige version', function (): void {
    assert_throws(InstallerException::class, function (): void {
        ModuleManifest::fromArray(['clave' => 'x']);
    });
});
```

- [ ] **Step 2: Correr el test para verlo fallar**

Run: `php tests/run.php Install/ModuleManifest`
Expected: FAIL — clase no encontrada.

- [ ] **Step 3: Implementar el VO**

```php
<?php

declare(strict_types=1);

namespace App\Application\Install;

use App\Domain\Exceptions\InstallerException;

final class ModuleManifest
{
    /**
     * @param list<string> $requiere
     * @param list<string> $migraciones
     * @param list<string> $seeds
     * @param list<string> $cruds
     * @param list<string> $permisos
     * @param list<string> $menu
     * @param list<string> $providers
     */
    public function __construct(
        public readonly string $clave,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $version,
        public readonly bool $obligatorio,
        public readonly array $requiere,
        public readonly array $migraciones,
        public readonly array $seeds,
        public readonly array $cruds,
        public readonly array $permisos,
        public readonly array $menu,
        public readonly array $providers,
    ) {}

    public static function fromArray(array $raw): self
    {
        $clave = (string) ($raw['clave'] ?? '');
        if ($clave === '') {
            throw InstallerException::manifiestoInvalido('falta "clave".');
        }
        $version = (string) ($raw['version'] ?? '');
        if ($version === '') {
            throw InstallerException::manifiestoInvalido("módulo {$clave}: falta \"version\".");
        }

        $strList = static fn(mixed $v): array => array_values(array_map(
            'strval',
            array_filter(is_array($v) ? $v : [], static fn($x) => is_scalar($x))
        ));

        return new self(
            clave:       $clave,
            nombre:      (string) ($raw['nombre'] ?? $clave),
            descripcion: (string) ($raw['descripcion'] ?? ''),
            version:     $version,
            obligatorio: (bool) ($raw['obligatorio'] ?? false),
            requiere:    $strList($raw['requiere']    ?? []),
            migraciones: $strList($raw['migraciones'] ?? []),
            seeds:       $strList($raw['seeds']        ?? []),
            cruds:       $strList($raw['cruds']        ?? []),
            permisos:    $strList($raw['permisos']     ?? []),
            menu:        $strList($raw['menu']         ?? []),
            providers:   $strList($raw['providers']    ?? []),
        );
    }
}
```

- [ ] **Step 4: Correr el test para verlo pasar**

Run: `php tests/run.php Install/ModuleManifest`
Expected: `3 passed, 0 failed`

- [ ] **Step 5: Commit**

```bash
git add app/Application/Install/ModuleManifest.php tests/Install/ModuleManifestTest.php
git commit -m "feat(install): ModuleManifest VO inmutable con fromArray"
```

---

### Task 10: `ModuleRegistry` (carga de `config/modules/*.php`)

**Files:**
- Create: `app/Application/Install/ModuleRegistry.php`
- Test: `tests/Install/ModuleRegistryTest.php`
- Fixtures: `tests/fixtures/modules_ok/` (dos manifiestos válidos)

- [ ] **Step 1: Crear los manifiestos fixture**

Crear `tests/fixtures/modules_ok/core.php`:

```php
<?php
return [
    'clave' => 'core', 'nombre' => 'Core', 'version' => '1.0.0',
    'obligatorio' => true, 'requiere' => [], 'seeds' => ['010_x.sql'],
];
```

Crear `tests/fixtures/modules_ok/crud-engine.php`:

```php
<?php
return [
    'clave' => 'crud-engine', 'nombre' => 'CRUD', 'version' => '2.0.0',
    'requiere' => ['core'], 'migraciones' => ['m1.sql'], 'cruds' => ['demo_x'],
];
```

- [ ] **Step 2: Escribir el test**

```php
<?php

declare(strict_types=1);

use App\Application\Install\ModuleRegistry;
use App\Application\Install\ModuleManifest;

test('ModuleRegistry::all carga manifiestos por clave', function (): void {
    $reg = new ModuleRegistry(ROOT_PATH . '/tests/fixtures/modules_ok');
    $all = $reg->all();
    assert_same(2, count($all));
    assert_true(isset($all['core']) && $all['core'] instanceof ModuleManifest);
    assert_same('2.0.0', $all['crud-engine']->version);
});

test('ModuleRegistry::get devuelve null para clave inexistente', function (): void {
    $reg = new ModuleRegistry(ROOT_PATH . '/tests/fixtures/modules_ok');
    assert_null($reg->get('fantasma'));
});
```

- [ ] **Step 3: Correr el test para verlo fallar**

Run: `php tests/run.php Install/ModuleRegistry`
Expected: FAIL — clase no encontrada.

- [ ] **Step 4: Implementar `ModuleRegistry`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Install;

final class ModuleRegistry
{
    /** @var array<string,ModuleManifest>|null */
    private ?array $cache = null;

    public function __construct(private readonly string $directorio) {}

    /**
     * @return array<string,ModuleManifest> clave => manifiesto
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $out   = [];
        $files = glob(rtrim($this->directorio, '/\\') . '/*.php') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $raw = require $file;
            if (!is_array($raw)) {
                continue;
            }
            $manifest = ModuleManifest::fromArray($raw);
            $out[$manifest->clave] = $manifest;
        }

        return $this->cache = $out;
    }

    public function get(string $clave): ?ModuleManifest
    {
        return $this->all()[$clave] ?? null;
    }
}
```

- [ ] **Step 5: Correr el test para verlo pasar**

Run: `php tests/run.php Install/ModuleRegistry`
Expected: `2 passed, 0 failed`

- [ ] **Step 6: Commit**

```bash
git add app/Application/Install/ModuleRegistry.php tests/Install/ModuleRegistryTest.php tests/fixtures/modules_ok/
git commit -m "feat(install): ModuleRegistry carga manifiestos desde directorio"
```

---

### Task 11: `ManifestValidator` (validación estática)

**Files:**
- Create: `app/Application/Install/ManifestValidator.php`
- Test: `tests/Install/ManifestValidatorTest.php`

- [ ] **Step 1: Escribir el test**

```php
<?php

declare(strict_types=1);

use App\Application\Install\ManifestValidator;
use App\Application\Install\ModuleManifest;

function mv_manifest(array $over): ModuleManifest
{
    return ModuleManifest::fromArray(array_merge(
        ['clave' => 'x', 'nombre' => 'X', 'version' => '1.0.0'],
        $over
    ));
}

test('ManifestValidator: manifiestos consistentes no producen errores', function (): void {
    $manifests = [
        'core'  => mv_manifest(['clave' => 'core', 'obligatorio' => true, 'seeds' => ['010.sql']]),
        'crud'  => mv_manifest(['clave' => 'crud', 'requiere' => ['core'], 'migraciones' => ['m1.sql'], 'cruds' => ['demo_x']]),
    ];
    $ctx = ['migraciones' => ['m1.sql'], 'seeds' => ['010.sql'], 'cruds' => ['demo_x']];
    assert_same([], ManifestValidator::errores($manifests, $ctx));
});

test('ManifestValidator: archivo en disco sin dueño es huérfano', function (): void {
    $manifests = ['core' => mv_manifest(['clave' => 'core'])];
    $ctx = ['migraciones' => ['huerfana.sql'], 'seeds' => [], 'cruds' => []];
    $errores = ManifestValidator::errores($manifests, $ctx);
    assert_same(1, count($errores));
});

test('ManifestValidator: archivo con doble dueño se reporta', function (): void {
    $manifests = [
        'a' => mv_manifest(['clave' => 'a', 'migraciones' => ['m1.sql']]),
        'b' => mv_manifest(['clave' => 'b', 'migraciones' => ['m1.sql']]),
    ];
    $ctx = ['migraciones' => ['m1.sql'], 'seeds' => [], 'cruds' => []];
    $errores = ManifestValidator::errores($manifests, $ctx);
    assert_same(1, count($errores));
});

test('ManifestValidator: dependencia inexistente se reporta', function (): void {
    $manifests = ['crud' => mv_manifest(['clave' => 'crud', 'requiere' => ['core']])];
    $ctx = ['migraciones' => [], 'seeds' => [], 'cruds' => []];
    $errores = ManifestValidator::errores($manifests, $ctx);
    assert_same(1, count($errores));
});

test('ManifestValidator: crud declarado inexistente se reporta', function (): void {
    $manifests = ['crud' => mv_manifest(['clave' => 'crud', 'cruds' => ['demo_x']])];
    $ctx = ['migraciones' => [], 'seeds' => [], 'cruds' => []];
    $errores = ManifestValidator::errores($manifests, $ctx);
    assert_same(1, count($errores));
});
```

- [ ] **Step 2: Correr el test para verlo fallar**

Run: `php tests/run.php Install/ManifestValidator`
Expected: FAIL — clase no encontrada.

- [ ] **Step 3: Implementar `ManifestValidator`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Install;

/**
 * Valida un conjunto de manifiestos contra el estado real del filesystem.
 * Acumula errores (estilo CrudConfigValidator); no lanza excepciones.
 */
final class ManifestValidator
{
    /**
     * @param array<string,ModuleManifest> $manifests
     * @param array{migraciones:list<string>,seeds:list<string>,cruds:list<string>} $contexto
     * @return list<string>
     */
    public static function errores(array $manifests, array $contexto): array
    {
        $errores = [];

        $clavesValidas = array_keys($manifests);

        // 1) Dependencias resueltas.
        foreach ($manifests as $clave => $m) {
            foreach ($m->requiere as $dep) {
                if (!in_array($dep, $clavesValidas, true)) {
                    $errores[] = "Módulo {$clave}: requiere \"{$dep}\" que no existe.";
                }
            }
        }

        // 2) Dueño único de cada migración y seed presentes en disco.
        $errores = array_merge($errores, self::erroresPropiedad(
            $manifests,
            $contexto['migraciones'] ?? [],
            static fn(ModuleManifest $m): array => $m->migraciones,
            'migración'
        ));
        $errores = array_merge($errores, self::erroresPropiedad(
            $manifests,
            $contexto['seeds'] ?? [],
            static fn(ModuleManifest $m): array => $m->seeds,
            'seed'
        ));

        // 3) Cruds declarados existen.
        $crudsPresentes = $contexto['cruds'] ?? [];
        foreach ($manifests as $clave => $m) {
            foreach ($m->cruds as $crud) {
                if (!in_array($crud, $crudsPresentes, true)) {
                    $errores[] = "Módulo {$clave}: crud \"{$crud}\" no existe en config/cruds/.";
                }
            }
        }

        return $errores;
    }

    /**
     * @param array<string,ModuleManifest> $manifests
     * @param list<string> $presentes archivos reales en disco
     * @param callable(ModuleManifest):list<string> $extraer
     * @return list<string>
     */
    private static function erroresPropiedad(array $manifests, array $presentes, callable $extraer, string $tipo): array
    {
        $errores = [];

        // Conteo de dueños por archivo declarado.
        $duenos = [];
        foreach ($manifests as $clave => $m) {
            foreach ($extraer($m) as $archivo) {
                $duenos[$archivo][] = $clave;
                if (!in_array($archivo, $presentes, true)) {
                    $errores[] = "Módulo {$clave}: {$tipo} \"{$archivo}\" declarada pero ausente en disco.";
                }
            }
        }

        // Doble dueño.
        foreach ($duenos as $archivo => $claves) {
            if (count($claves) > 1) {
                $errores[] = "El {$tipo} \"{$archivo}\" tiene múltiples dueños: " . implode(', ', $claves) . '.';
            }
        }

        // Huérfanos (en disco sin dueño).
        foreach ($presentes as $archivo) {
            if (!isset($duenos[$archivo])) {
                $errores[] = "El {$tipo} \"{$archivo}\" no tiene dueño en ningún manifiesto.";
            }
        }

        return $errores;
    }
}
```

- [ ] **Step 4: Correr el test para verlo pasar**

Run: `php tests/run.php Install/ManifestValidator`
Expected: `5 passed, 0 failed`

- [ ] **Step 5: Commit**

```bash
git add app/Application/Install/ManifestValidator.php tests/Install/ManifestValidatorTest.php
git commit -m "feat(install): ManifestValidator (dueño único, deps, cruds)"
```

---

### Task 12: `DependencyResolver` (orden topológico)

**Files:**
- Create: `app/Application/Install/DependencyResolver.php`
- Test: `tests/Install/DependencyResolverTest.php`

- [ ] **Step 1: Escribir el test**

```php
<?php

declare(strict_types=1);

use App\Application\Install\DependencyResolver;
use App\Application\Install\ModuleManifest;
use App\Domain\Exceptions\InstallerException;

function dr_manifest(string $clave, array $requiere = []): ModuleManifest
{
    return ModuleManifest::fromArray([
        'clave' => $clave, 'nombre' => $clave, 'version' => '1.0.0', 'requiere' => $requiere,
    ]);
}

test('DependencyResolver: ordena dependencias antes que dependientes e incluye core', function (): void {
    $manifests = [
        'core'  => dr_manifest('core'),
        'crud'  => dr_manifest('crud', ['core']),
        'inv'   => dr_manifest('inv', ['crud']),
    ];
    $orden = (new DependencyResolver())->resolver($manifests, ['inv']);
    // core antes que crud antes que inv
    assert_true(array_search('core', $orden) < array_search('crud', $orden));
    assert_true(array_search('crud', $orden) < array_search('inv', $orden));
});

test('DependencyResolver: core siempre presente aunque no se seleccione', function (): void {
    $manifests = ['core' => dr_manifest('core'), 'dash' => dr_manifest('dash', ['core'])];
    $orden = (new DependencyResolver())->resolver($manifests, ['dash']);
    assert_true(in_array('core', $orden, true));
});

test('DependencyResolver: ciclo lanza InstallerException', function (): void {
    $manifests = [
        'a' => dr_manifest('a', ['b']),
        'b' => dr_manifest('b', ['a']),
        'core' => dr_manifest('core'),
    ];
    assert_throws(InstallerException::class, function () use ($manifests): void {
        (new DependencyResolver())->resolver($manifests, ['a']);
    });
});
```

- [ ] **Step 2: Correr el test para verlo fallar**

Run: `php tests/run.php Install/DependencyResolver`
Expected: FAIL — clase no encontrada.

- [ ] **Step 3: Implementar `DependencyResolver`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Install;

use App\Domain\Exceptions\InstallerException;

final class DependencyResolver
{
    /**
     * Devuelve las claves a instalar en orden topológico (dependencias primero),
     * expandiendo la selección con sus dependencias transitivas. 'core' siempre
     * se incluye si existe.
     *
     * @param array<string,ModuleManifest> $manifests
     * @param list<string> $seleccion
     * @return list<string>
     */
    public function resolver(array $manifests, array $seleccion): array
    {
        $objetivo = $seleccion;
        if (isset($manifests['core'])) {
            $objetivo[] = 'core';
        }

        $orden    = [];
        $visitado = []; // clave => true (resuelto), false (en proceso → ciclo)

        $visit = function (string $clave) use (&$visit, &$orden, &$visitado, $manifests): void {
            if (($visitado[$clave] ?? null) === true) {
                return;
            }
            if (($visitado[$clave] ?? null) === false) {
                throw InstallerException::cicloDependencias([$clave]);
            }
            $manifest = $manifests[$clave] ?? null;
            if ($manifest === null) {
                throw InstallerException::manifiestoInvalido("dependencia \"{$clave}\" no existe.");
            }
            $visitado[$clave] = false;
            foreach ($manifest->requiere as $dep) {
                $visit($dep);
            }
            $visitado[$clave] = true;
            $orden[] = $clave;
        };

        foreach (array_unique($objetivo) as $clave) {
            $visit($clave);
        }

        return $orden;
    }
}
```

- [ ] **Step 4: Correr el test para verlo pasar**

Run: `php tests/run.php Install/DependencyResolver`
Expected: `3 passed, 0 failed`

- [ ] **Step 5: Commit**

```bash
git add app/Application/Install/DependencyResolver.php tests/Install/DependencyResolverTest.php
git commit -m "feat(install): DependencyResolver con orden topológico y detección de ciclos"
```

---

## Fase 3 — Application: motor `Installer` y `DeploymentStatus`

### Task 13: `InstallPlan` (VO del plan) + fakes en memoria

**Files:**
- Create: `app/Application/Install/InstallPlan.php`
- Create: `tests/fixtures/install_repos.php`

- [ ] **Step 1: Implementar `InstallPlan`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Install;

final class InstallPlan
{
    /**
     * @param list<array{modulo:string,archivo:string,ruta:string,checksum:string}> $migracionesPendientes
     * @param list<array{modulo:string,archivo:string,ruta:string,checksum:string}> $seedsPendientes
     * @param list<array{clave:string,version:string}> $modulos
     * @param list<array{modulo:string,archivo:string}> $checksumsModificados
     */
    public function __construct(
        public readonly bool $nueva,
        public readonly array $migracionesPendientes,
        public readonly array $seedsPendientes,
        public readonly array $modulos,
        public readonly array $checksumsModificados,
    ) {}

    public function vacio(): bool
    {
        return $this->migracionesPendientes === [] && $this->seedsPendientes === [];
    }
}
```

- [ ] **Step 2: Crear los fakes en memoria**

`tests/fixtures/install_repos.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Interfaces\MigrationRepositoryInterface;
use App\Domain\Interfaces\ModuleStateRepositoryInterface;

final class FakeMigrationRepository implements MigrationRepositoryInterface
{
    /** @param array<string,string> $aplicadas archivo => checksum */
    public function __construct(private array $aplicadas = [], private array $tablas = []) {}

    public function aplicadas(): array { return $this->aplicadas; }

    public function registrar(string $modulo, string $archivo, string $checksum): void
    {
        $this->aplicadas[$archivo] = $checksum;
    }

    public function existeTabla(string $nombre): bool { return in_array($nombre, $this->tablas, true); }
}

final class FakeModuleStateRepository implements ModuleStateRepositoryInterface
{
    /** @param array<string,array{version:string,activo:bool}> $estado */
    public function __construct(private array $estado = []) {}

    public function instalados(): array { return $this->estado; }

    public function registrar(string $clave, string $version, bool $activo): void
    {
        $this->estado[$clave] = ['version' => $version, 'activo' => $activo];
    }
}

/**
 * Crea un directorio temporal con archivos .sql de contenido conocido.
 *
 * @param array<string,string> $archivos nombre => contenido
 */
function install_fixture_dir(array $archivos): string
{
    $dir = sys_get_temp_dir() . '/inst_' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    foreach ($archivos as $nombre => $contenido) {
        file_put_contents($dir . '/' . $nombre, $contenido);
    }
    return $dir;
}
```

- [ ] **Step 3: Verificar sintaxis**

Run: `php -l app/Application/Install/InstallPlan.php && php -l tests/fixtures/install_repos.php`
Expected: `No syntax errors detected` (×2)

- [ ] **Step 4: Commit**

```bash
git add app/Application/Install/InstallPlan.php tests/fixtures/install_repos.php
git commit -m "feat(install): InstallPlan VO y fakes de repositorios en memoria"
```

---

### Task 14: `Installer` — `plan()`, `aplicar()`, `baseline()`

**Files:**
- Create: `app/Application/Install/Installer.php`
- Test: `tests/Install/InstallerPlanTest.php`
- Test: `tests/Install/InstallerBaselineTest.php`

- [ ] **Step 1: Escribir el test de `plan()`**

`tests/Install/InstallerPlanTest.php`:

```php
<?php

declare(strict_types=1);

require_once ROOT_PATH . '/tests/fixtures/install_repos.php';

use App\Application\Install\Installer;
use App\Application\Install\ModuleRegistry;
use App\Application\Install\DependencyResolver;
use App\Infrastructure\Install\SqlFileRunner;

function installer_con(array $aplicadas, string $migDir, string $seedDir): Installer
{
    return new Installer(
        new ModuleRegistry(ROOT_PATH . '/tests/fixtures/modules_plan'),
        new DependencyResolver(),
        new FakeMigrationRepository($aplicadas),
        new FakeModuleStateRepository(),
        new SqlFileRunner(),
        $migDir,
        $seedDir
    );
}

test('Installer::plan en instalación nueva marca todo pendiente', function (): void {
    $migDir  = install_fixture_dir(['m1.sql' => "SELECT 1;\n"]);
    $seedDir = install_fixture_dir(['010.sql' => "SELECT 2;\n"]);
    $plan = installer_con([], $migDir, $seedDir)->plan(['core']);

    assert_true($plan->nueva);
    assert_same(1, count($plan->migracionesPendientes));
    assert_same('m1.sql', $plan->migracionesPendientes[0]['archivo']);
    assert_same(1, count($plan->seedsPendientes));
});

test('Installer::plan no repite migración ya aplicada con mismo checksum', function (): void {
    $migDir  = install_fixture_dir(['m1.sql' => "SELECT 1;\n"]);
    $seedDir = install_fixture_dir(['010.sql' => "SELECT 2;\n"]);
    $checksum = (new SqlFileRunner())->checksum($migDir . '/m1.sql');
    $plan = installer_con(['m1.sql' => $checksum], $migDir, $seedDir)->plan(['core']);

    assert_same(false, $plan->nueva);
    assert_same(0, count($plan->migracionesPendientes));
});

test('Installer::plan reporta checksum modificado sin re-aplicar', function (): void {
    $migDir  = install_fixture_dir(['m1.sql' => "SELECT 1;\n"]);
    $seedDir = install_fixture_dir(['010.sql' => "SELECT 2;\n"]);
    $plan = installer_con(['m1.sql' => 'checksum-viejo-distinto'], $migDir, $seedDir)->plan(['core']);

    assert_same(0, count($plan->migracionesPendientes));
    assert_same(1, count($plan->checksumsModificados));
    assert_same('m1.sql', $plan->checksumsModificados[0]['archivo']);
});
```

- [ ] **Step 2: Crear los manifiestos fixture para el Installer**

`tests/fixtures/modules_plan/core.php`:

```php
<?php
return [
    'clave' => 'core', 'nombre' => 'Core', 'version' => '1.2.3',
    'obligatorio' => true, 'migraciones' => ['m1.sql'], 'seeds' => ['010.sql'],
];
```

- [ ] **Step 3: Correr el test para verlo fallar**

Run: `php tests/run.php Install/InstallerPlan`
Expected: FAIL — `Class "App\Application\Install\Installer" not found`.

- [ ] **Step 4: Implementar `Installer`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Install;

use App\Domain\Interfaces\MigrationRepositoryInterface;
use App\Domain\Interfaces\ModuleStateRepositoryInterface;
use App\Infrastructure\Install\SqlFileRunner;

final class Installer
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly DependencyResolver $resolver,
        private readonly MigrationRepositoryInterface $migraciones,
        private readonly ModuleStateRepositoryInterface $modulos,
        private readonly SqlFileRunner $runner,
        private readonly string $migracionesDir,
        private readonly string $seedsDir,
    ) {}

    /**
     * Comprobaciones de entorno. Cada item: [clave, ok, detalle].
     *
     * @return list<array{clave:string,ok:bool,detalle:string}>
     */
    public function requisitosCheck(): array
    {
        $checks = [];

        $checks[] = [
            'clave'   => 'php',
            'ok'      => PHP_VERSION_ID >= 80100,
            'detalle' => 'PHP ' . PHP_VERSION . ' (se requiere ≥ 8.1).',
        ];
        $checks[] = [
            'clave'   => 'pdo_mysql',
            'ok'      => extension_loaded('pdo_mysql'),
            'detalle' => extension_loaded('pdo_mysql') ? 'Extensión pdo_mysql cargada.' : 'Falta extensión pdo_mysql.',
        ];
        $storageOk = is_writable(ROOT_PATH . '/storage');
        $checks[] = [
            'clave'   => 'storage',
            'ok'      => $storageOk,
            'detalle' => $storageOk ? 'storage/ escribible.' : 'storage/ no es escribible.',
        ];
        $envOk = is_file(ROOT_PATH . '/.env');
        $checks[] = [
            'clave'   => 'env',
            'ok'      => $envOk,
            'detalle' => $envOk ? '.env presente.' : 'Falta archivo .env.',
        ];

        $conexionOk = false;
        $detalleConn = 'No se pudo conectar a la BD.';
        try {
            $this->migraciones->existeTabla('cfg_modulos');
            $conexionOk = true;
            $detalleConn = 'Conexión a la base de datos correcta.';
        } catch (\Throwable $e) {
            $detalleConn = 'Error de conexión: ' . $e->getMessage();
        }
        $checks[] = ['clave' => 'bd', 'ok' => $conexionOk, 'detalle' => $detalleConn];

        return $checks;
    }

    /**
     * Calcula el plan sin ejecutar nada (preview / dry-run).
     *
     * @param list<string> $seleccion
     */
    public function plan(array $seleccion): InstallPlan
    {
        $orden     = $this->resolver->resolver($this->registry->all(), $seleccion);
        $aplicadas = $this->migraciones->aplicadas();
        $nueva     = $this->modulos->instalados() === [];

        $migPend = [];
        $seedPend = [];
        $modificados = [];
        $modulosPlan = [];

        foreach ($orden as $clave) {
            $manifest = $this->registry->get($clave);
            if ($manifest === null) {
                continue;
            }
            $modulosPlan[] = ['clave' => $clave, 'version' => $manifest->version];

            foreach ($manifest->migraciones as $archivo) {
                $this->clasificar($clave, $archivo, $this->migracionesDir, $aplicadas, $migPend, $modificados);
            }
            foreach ($manifest->seeds as $archivo) {
                $this->clasificar($clave, $archivo, $this->seedsDir, $aplicadas, $seedPend, $modificados);
            }
        }

        return new InstallPlan($nueva, $migPend, $seedPend, $modulosPlan, $modificados);
    }

    /**
     * @param array<string,string> $aplicadas
     * @param list<array{modulo:string,archivo:string,ruta:string,checksum:string}> $pendientes
     * @param list<array{modulo:string,archivo:string}> $modificados
     */
    private function clasificar(string $clave, string $archivo, string $dir, array $aplicadas, array &$pendientes, array &$modificados): void
    {
        $ruta     = rtrim($dir, '/\\') . '/' . $archivo;
        $checksum = $this->runner->checksum($ruta);

        if (!isset($aplicadas[$archivo])) {
            $pendientes[] = ['modulo' => $clave, 'archivo' => $archivo, 'ruta' => $ruta, 'checksum' => $checksum];
            return;
        }
        if ($aplicadas[$archivo] !== $checksum) {
            $modificados[] = ['modulo' => $clave, 'archivo' => $archivo];
        }
    }

    public function aplicar(InstallPlan $plan): void
    {
        foreach ($plan->migracionesPendientes as $item) {
            $this->runner->ejecutar($item['ruta']);
            $this->migraciones->registrar($item['modulo'], $item['archivo'], $item['checksum']);
        }
        foreach ($plan->seedsPendientes as $item) {
            $this->runner->ejecutar($item['ruta']);
            $this->migraciones->registrar($item['modulo'], $item['archivo'], $item['checksum']);
        }
        foreach ($plan->modulos as $mod) {
            $this->modulos->registrar($mod['clave'], $mod['version'], true);
        }
    }

    /**
     * Adopta un deploy legacy: marca como aplicadas las migraciones/seeds
     * presentes (sin ejecutarlas) y registra los módulos detectados.
     */
    public function baseline(): void
    {
        $aplicadas = $this->migraciones->aplicadas();

        foreach ($this->registry->all() as $clave => $manifest) {
            foreach ($manifest->migraciones as $archivo) {
                $this->baselineArchivo($clave, $archivo, $this->migracionesDir, $aplicadas);
            }
            foreach ($manifest->seeds as $archivo) {
                $this->baselineArchivo($clave, $archivo, $this->seedsDir, $aplicadas);
            }
            $this->modulos->registrar($clave, $manifest->version, true);
        }
    }

    /** @param array<string,string> $aplicadas */
    private function baselineArchivo(string $clave, string $archivo, string $dir, array $aplicadas): void
    {
        if (isset($aplicadas[$archivo])) {
            return;
        }
        $ruta = rtrim($dir, '/\\') . '/' . $archivo;
        if (!is_file($ruta)) {
            return;
        }
        $this->migraciones->registrar($clave, $archivo, $this->runner->checksum($ruta));
    }
}
```

- [ ] **Step 5: Correr el test de `plan()` para verlo pasar**

Run: `php tests/run.php Install/InstallerPlan`
Expected: `3 passed, 0 failed`

- [ ] **Step 6: Escribir el test de `baseline()`**

`tests/Install/InstallerBaselineTest.php`:

```php
<?php

declare(strict_types=1);

require_once ROOT_PATH . '/tests/fixtures/install_repos.php';

use App\Application\Install\Installer;
use App\Application\Install\ModuleRegistry;
use App\Application\Install\DependencyResolver;
use App\Infrastructure\Install\SqlFileRunner;

test('Installer::baseline marca presentes como aplicadas y registra módulos', function (): void {
    $migDir  = install_fixture_dir(['m1.sql' => "SELECT 1;\n"]);
    $seedDir = install_fixture_dir(['010.sql' => "SELECT 2;\n"]);

    $migRepo = new FakeMigrationRepository([]);
    $modRepo = new FakeModuleStateRepository();

    $installer = new Installer(
        new ModuleRegistry(ROOT_PATH . '/tests/fixtures/modules_plan'),
        new DependencyResolver(),
        $migRepo,
        $modRepo,
        new SqlFileRunner(),
        $migDir,
        $seedDir
    );

    $installer->baseline();

    // Tras baseline, plan() no debe encontrar pendientes.
    $plan = $installer->plan(['core']);
    assert_same(0, count($plan->migracionesPendientes));
    assert_same(0, count($plan->seedsPendientes));
    assert_true(isset($modRepo->instalados()['core']));
});
```

- [ ] **Step 7: Correr el test de baseline para verlo pasar**

Run: `php tests/run.php Install/InstallerBaseline`
Expected: `1 passed, 0 failed`

- [ ] **Step 8: Commit**

```bash
git add app/Application/Install/Installer.php tests/Install/InstallerPlanTest.php tests/Install/InstallerBaselineTest.php tests/fixtures/modules_plan/
git commit -m "feat(install): Installer con plan/aplicar/baseline (TDD con fakes)"
```

---

### Task 15: `DeploymentStatus` (view-model de estado)

**Files:**
- Create: `app/Application/Install/DeploymentStatus.php`
- Test: `tests/Install/DeploymentStatusTest.php`

- [ ] **Step 1: Escribir el test**

```php
<?php

declare(strict_types=1);

require_once ROOT_PATH . '/tests/fixtures/install_repos.php';

use App\Application\Install\DeploymentStatus;
use App\Application\Install\Installer;
use App\Application\Install\ModuleRegistry;
use App\Application\Install\DependencyResolver;
use App\Infrastructure\Install\SqlFileRunner;

function status_para(array $instalados): DeploymentStatus
{
    $migDir  = install_fixture_dir(['m1.sql' => "SELECT 1;\n"]);
    $seedDir = install_fixture_dir(['010.sql' => "SELECT 2;\n"]);
    $registry = new ModuleRegistry(ROOT_PATH . '/tests/fixtures/modules_plan');

    $installer = new Installer(
        $registry,
        new DependencyResolver(),
        new FakeMigrationRepository([]),
        new FakeModuleStateRepository($instalados),
        new SqlFileRunner(),
        $migDir,
        $seedDir
    );

    return new DeploymentStatus(
        $registry,
        $installer,
        new FakeModuleStateRepository($instalados),
        '1.0.0'
    );
}

test('DeploymentStatus: módulo no instalado figura como instalada=null', function (): void {
    $rep = status_para([])->reporte();
    assert_same('1.0.0', $rep['plataformaVersion']);
    assert_same(null, $rep['modulos']['core']['instalada']);
    assert_same('1.2.3', $rep['modulos']['core']['declarada']);
});

test('DeploymentStatus: versión instalada distinta marca actualización disponible', function (): void {
    $rep = status_para(['core' => ['version' => '1.0.0', 'activo' => true]])->reporte();
    assert_same('1.0.0', $rep['modulos']['core']['instalada']);
    assert_true($rep['modulos']['core']['actualizacionDisponible']);
});

test('DeploymentStatus: versión igual no marca actualización', function (): void {
    $rep = status_para(['core' => ['version' => '1.2.3', 'activo' => true]])->reporte();
    assert_same(false, $rep['modulos']['core']['actualizacionDisponible']);
});
```

- [ ] **Step 2: Correr el test para verlo fallar**

Run: `php tests/run.php Install/DeploymentStatus`
Expected: FAIL — clase no encontrada.

- [ ] **Step 3: Implementar `DeploymentStatus`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Install;

use App\Domain\Interfaces\ModuleStateRepositoryInterface;

/**
 * Fuente única del estado del despliegue: versión de plataforma, módulos
 * (declarada vs instalada), migraciones pendientes, checksums modificados y
 * health checks. La consumen la página admin y el CLI status.
 */
final class DeploymentStatus
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Installer $installer,
        private readonly ModuleStateRepositoryInterface $modulos,
        private readonly string $plataformaVersion,
    ) {}

    /**
     * @return array{
     *   plataformaVersion:string,
     *   modulos:array<string,array{declarada:string,instalada:?string,activo:bool,actualizacionDisponible:bool}>,
     *   migracionesPendientes:list<array{modulo:string,archivo:string}>,
     *   checksumsModificados:list<array{modulo:string,archivo:string}>,
     *   healthChecks:list<array{clave:string,ok:bool,detalle:string}>
     * }
     */
    public function reporte(): array
    {
        $instalados = $this->modulos->instalados();
        $manifests  = $this->registry->all();

        $modulos = [];
        foreach ($manifests as $clave => $manifest) {
            $instalada = $instalados[$clave]['version'] ?? null;
            $modulos[$clave] = [
                'declarada'                => $manifest->version,
                'instalada'                => $instalada,
                'activo'                   => $instalados[$clave]['activo'] ?? false,
                'actualizacionDisponible'  => $instalada !== null && $instalada !== $manifest->version,
            ];
        }

        // Plan sobre todos los módulos declarados → pendientes y checksums.
        $plan = $this->installer->plan(array_keys($manifests));

        $migracionesPendientes = array_map(
            static fn(array $p): array => ['modulo' => $p['modulo'], 'archivo' => $p['archivo']],
            array_merge($plan->migracionesPendientes, $plan->seedsPendientes)
        );

        return [
            'plataformaVersion'     => $this->plataformaVersion,
            'modulos'               => $modulos,
            'migracionesPendientes' => $migracionesPendientes,
            'checksumsModificados'  => $plan->checksumsModificados,
            'healthChecks'          => $this->installer->requisitosCheck(),
        ];
    }
}
```

- [ ] **Step 4: Correr el test para verlo pasar**

Run: `php tests/run.php Install/DeploymentStatus`
Expected: `3 passed, 0 failed`

- [ ] **Step 5: Correr toda la suite (regresión)**

Run: `php tests/run.php`
Expected: todos verdes, `N passed, 0 failed`.

- [ ] **Step 6: Commit**

```bash
git add app/Application/Install/DeploymentStatus.php tests/Install/DeploymentStatusTest.php
git commit -m "feat(install): DeploymentStatus view-model (declarada vs instalada, pendientes)"
```

---

### Task 16: Bindings DI del motor de instalación

**Files:**
- Modify: `config/container.php`

- [ ] **Step 1: Añadir imports**

Tras los `use` existentes en `config/container.php`, añade:

```php
use App\Domain\Interfaces\MigrationRepositoryInterface;
use App\Domain\Interfaces\ModuleStateRepositoryInterface;
use App\Infrastructure\Repositories\MigrationRepository;
use App\Infrastructure\Repositories\ModuleStateRepository;
use App\Infrastructure\Install\SqlFileRunner;
use App\Application\Install\ModuleRegistry;
use App\Application\Install\DependencyResolver;
use App\Application\Install\Installer;
use App\Application\Install\DeploymentStatus;
use App\Kernel\Config\Config;
```

- [ ] **Step 2: Registrar los singletons**

Dentro del `return static function (Container $container): void {`, antes del cierre `};`, añade:

```php
    $container->singleton(MigrationRepositoryInterface::class, fn() => new MigrationRepository());
    $container->singleton(ModuleStateRepositoryInterface::class, fn() => new ModuleStateRepository());
    $container->singleton(SqlFileRunner::class, fn() => new SqlFileRunner());
    $container->singleton(ModuleRegistry::class, fn() => new ModuleRegistry(ROOT_PATH . '/config/modules'));
    $container->singleton(DependencyResolver::class, fn() => new DependencyResolver());

    $container->singleton(Installer::class, fn(Container $c) => new Installer(
        $c->get(ModuleRegistry::class),
        $c->get(DependencyResolver::class),
        $c->get(MigrationRepositoryInterface::class),
        $c->get(ModuleStateRepositoryInterface::class),
        $c->get(SqlFileRunner::class),
        ROOT_PATH . '/database/migrations',
        ROOT_PATH . '/database/seeds'
    ));

    $container->singleton(DeploymentStatus::class, fn(Container $c) => new DeploymentStatus(
        $c->get(ModuleRegistry::class),
        $c->get(Installer::class),
        $c->get(ModuleStateRepositoryInterface::class),
        (string) Config::get('app.version', '0.0.0')
    ));
```

- [ ] **Step 3: Verificar sintaxis**

Run: `php -l config/container.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add config/container.php
git commit -m "feat(install): bindings DI del motor de instalación"
```

---

## Fase 4 — CLI

### Task 17: Reescribir `scripts/install.php` sobre `Installer`

**Files:**
- Modify: `scripts/install.php`

- [ ] **Step 1: Reescribir el script completo**

Reemplaza todo el contenido de `scripts/install.php` por:

```php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| install.php — Instalador con tracking real (motor Installer)
|--------------------------------------------------------------------------
| Uso:
|   php scripts/install.php                       (instala/actualiza core + opcionales activos)
|   php scripts/install.php --modules=core,crud-engine
|   php scripts/install.php --dry-run             (muestra el plan, no ejecuta)
|   php scripts/install.php --baseline            (adopta deploy legacy sin re-ejecutar)
*/

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once APP_PATH . '/Kernel/Autoloader.php';

use App\Kernel\EnvLoader;
use App\Kernel\Config\Config;
use App\Kernel\Database\Connection;
use App\Kernel\Container\Container;
use App\Application\Install\Installer;
use App\Application\Install\ModuleRegistry;

EnvLoader::load(ROOT_PATH . '/.env');
Config::init(ROOT_PATH . '/config');

Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
    'charset'  => 'utf8mb4',
]);

// Argumentos.
$args     = array_slice($argv, 1);
$dryRun   = in_array('--dry-run', $args, true);
$baseline = in_array('--baseline', $args, true);
$modules  = null;
foreach ($args as $a) {
    if (str_starts_with($a, '--modules=')) {
        $modules = array_values(array_filter(array_map('trim', explode(',', substr($a, strlen('--modules='))))));
    }
}

// Schema base SIEMPRE primero (crea cfg_migraciones/cfg_modulos si faltan).
echo "=== Instalación Lebytek ===\n\n→ Schema base\n";
(new App\Infrastructure\Install\SqlFileRunner())->ejecutar(ROOT_PATH . '/database/schema/schema.sql');
echo "   ✓ schema.sql\n\n";

// Contenedor / motor.
$container = new Container();
(require ROOT_PATH . '/config/container.php')($container);
/** @var Installer $installer */
$installer  = $container->get(Installer::class);
/** @var ModuleRegistry $registry */
$registry   = $container->get(ModuleRegistry::class);

if ($baseline) {
    echo "→ Baseline (adoptando deploy existente)\n";
    $installer->baseline();
    echo "   ✓ Migraciones presentes marcadas como aplicadas; módulos registrados.\n";
    echo "\n=== Listo ===\n";
    exit(0);
}

// Selección por defecto: todos los módulos declarados (core + opcionales).
$seleccion = $modules ?? array_keys($registry->all());

$plan = $installer->plan($seleccion);

echo "→ Plan (" . ($plan->nueva ? 'instalación nueva' : 'actualización') . ")\n";
echo "   Migraciones pendientes: " . count($plan->migracionesPendientes) . "\n";
foreach ($plan->migracionesPendientes as $m) { echo "     - [{$m['modulo']}] {$m['archivo']}\n"; }
echo "   Seeds pendientes: " . count($plan->seedsPendientes) . "\n";
foreach ($plan->seedsPendientes as $s) { echo "     - [{$s['modulo']}] {$s['archivo']}\n"; }
echo "   Módulos a registrar: " . implode(', ', array_map(fn($x) => $x['clave'] . '@' . $x['version'], $plan->modulos)) . "\n";
if ($plan->checksumsModificados !== []) {
    echo "   ⚠ Checksums modificados tras aplicar (NO se re-ejecutan):\n";
    foreach ($plan->checksumsModificados as $c) { echo "     - [{$c['modulo']}] {$c['archivo']}\n"; }
}

if ($dryRun) {
    echo "\n(dry-run: no se ejecutó nada)\n";
    exit(0);
}

echo "\n→ Aplicando…\n";
$installer->aplicar($plan);
echo "   ✓ Aplicado y registrado en cfg_migraciones / cfg_modulos.\n";
echo "\n=== Instalación completada ===\n";
```

- [ ] **Step 2: Verificar sintaxis**

Run: `php -l scripts/install.php`
Expected: `No syntax errors detected`

- [ ] **Step 3 (manual, requiere BD): smoke `--dry-run`**

Run: `php scripts/install.php --dry-run`
Expected: imprime el plan sin errores. (Si no hay BD configurada localmente, omitir y documentar; se valida en el VPS.)

- [ ] **Step 4: Commit**

```bash
git add scripts/install.php
git commit -m "feat(install): reescribe scripts/install.php sobre Installer (dry-run, baseline, modules)"
```

---

### Task 18: `scripts/status.php` (CLI de estado)

**Files:**
- Create: `scripts/status.php`

- [ ] **Step 1: Crear el script**

```php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| status.php — Imprime el estado del despliegue (DeploymentStatus)
|--------------------------------------------------------------------------
| Uso: php scripts/status.php
*/

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once APP_PATH . '/Kernel/Autoloader.php';

use App\Kernel\EnvLoader;
use App\Kernel\Config\Config;
use App\Kernel\Database\Connection;
use App\Kernel\Container\Container;
use App\Application\Install\DeploymentStatus;

EnvLoader::load(ROOT_PATH . '/.env');
Config::init(ROOT_PATH . '/config');

Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
    'charset'  => 'utf8mb4',
]);

$container = new Container();
(require ROOT_PATH . '/config/container.php')($container);
/** @var DeploymentStatus $status */
$status = $container->get(DeploymentStatus::class);
$r = $status->reporte();

echo "=== Estado del despliegue ===\n\n";
echo "Plataforma: v{$r['plataformaVersion']}\n\n";

echo "Módulos:\n";
foreach ($r['modulos'] as $clave => $m) {
    $inst = $m['instalada'] ?? '(no instalado)';
    $flag = $m['actualizacionDisponible'] ? '  ⬆ actualización disponible' : '';
    $act  = $m['activo'] ? 'activo' : 'inactivo';
    echo "  - {$clave}: declarada {$m['declarada']} / instalada {$inst} [{$act}]{$flag}\n";
}

echo "\nMigraciones pendientes: " . count($r['migracionesPendientes']) . "\n";
foreach ($r['migracionesPendientes'] as $p) { echo "  - [{$p['modulo']}] {$p['archivo']}\n"; }

echo "\nChecksums modificados: " . count($r['checksumsModificados']) . "\n";
foreach ($r['checksumsModificados'] as $c) { echo "  - [{$c['modulo']}] {$c['archivo']}\n"; }

echo "\nHealth checks:\n";
foreach ($r['healthChecks'] as $h) {
    echo '  [' . ($h['ok'] ? 'OK ' : 'XX ') . "] {$h['clave']}: {$h['detalle']}\n";
}
```

- [ ] **Step 2: Verificar sintaxis**

Run: `php -l scripts/status.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add scripts/status.php
git commit -m "feat(install): scripts/status.php imprime DeploymentStatus en texto"
```

---

### Task 19: Test de integridad de estandarización (sobre archivos reales)

**Files:**
- Create: `tests/Install/EstandarizacionIntegridadTest.php`

- [ ] **Step 1: Escribir el test que valida los manifiestos reales**

```php
<?php

declare(strict_types=1);

use App\Application\Install\ModuleRegistry;
use App\Application\Install\ManifestValidator;

test('Integridad: todos los .sql reales tienen dueño único en algún manifiesto', function (): void {
    $registry  = new ModuleRegistry(ROOT_PATH . '/config/modules');
    $manifests = $registry->all();

    $migraciones = array_map('basename', glob(ROOT_PATH . '/database/migrations/*.sql') ?: []);
    $seeds       = array_map('basename', glob(ROOT_PATH . '/database/seeds/*.sql') ?: []);

    $crudFiles = glob(ROOT_PATH . '/config/cruds/*.json') ?: [];
    $cruds = array_map(static fn(string $f): string => basename($f, '.json'), $crudFiles);

    $errores = ManifestValidator::errores($manifests, [
        'migraciones' => array_values($migraciones),
        'seeds'       => array_values($seeds),
        'cruds'       => array_values($cruds),
    ]);

    assert_same([], $errores);
});

test('Integridad: core existe y es obligatorio', function (): void {
    $registry = new ModuleRegistry(ROOT_PATH . '/config/modules');
    $core = $registry->get('core');
    assert_true($core !== null);
    assert_true($core->obligatorio);
});
```

- [ ] **Step 2: Correr el test**

Run: `php tests/run.php Install/EstandarizacionIntegridad`
Expected: `2 passed, 0 failed`. Si falla por huérfanos/doble dueño, corrige los manifiestos de la Task 4 (no el test).

> Nota: los recursos CRUD pueden estar en `.json` o `.php`. Si en este repo `config/cruds/` usa `.php`, cambia el glob a `*.php` y `basename($f, '.php')`. Verifica con `php -r "foreach(glob('config/cruds/*') as $f) echo basename($f).PHP_EOL;"` antes de implementar.

- [ ] **Step 3: Commit**

```bash
git add tests/Install/EstandarizacionIntegridadTest.php
git commit -m "test(install): integridad de estandarización sobre manifiestos reales"
```

---

## Fase 5 — Página admin "Estado del sistema"

### Task 20: Ruta `/admin/sistema/estado`

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Añadir el import del controlador**

En la cabecera de `routes/web.php`, junto a los demás `use ...Admin\...`:

```php
use App\Presentation\Controllers\Admin\SistemaEstadoController;
```

- [ ] **Step 2: Registrar la ruta dentro del grupo `/admin`**

Dentro del `group(['prefix' => '/admin', ...])`, tras la ruta de `/dashboard`, añade:

```php
    $router->get('/sistema/estado', [SistemaEstadoController::class, 'index'], [new RbacMiddleware('sistema.ver')]);
```

- [ ] **Step 3: Verificar sintaxis**

Run: `php -l routes/web.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add routes/web.php
git commit -m "feat(install): ruta /admin/sistema/estado con RBAC sistema.ver"
```

---

### Task 21: `SistemaEstadoController` + vista

**Files:**
- Create: `app/Presentation/Controllers/Admin/SistemaEstadoController.php`
- Create: `app/Presentation/Views/admin/sistema/estado.php`

- [ ] **Step 1: Crear el controlador**

```php
<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Presentation\Controllers\AdminBaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Application\Services\ConfiguracionService;
use App\Application\Services\AdminNavigationMenuService;
use App\Application\Install\DeploymentStatus;

final class SistemaEstadoController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly DeploymentStatus $deploymentStatus
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        return $this->view('admin/sistema/estado', [
            'titulo' => 'Estado del sistema',
            'estado' => $this->deploymentStatus->reporte(),
        ]);
    }
}
```

- [ ] **Step 2: Crear la vista**

`app/Presentation/Views/admin/sistema/estado.php`:

```php
<?php /** @var array $estado */ ?>
<div class="container-fluid py-3">
  <h1 class="h4 mb-4"><i class="bi bi-hdd-stack me-2"></i>Estado del sistema</h1>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Versión de plataforma</div>
          <div class="h3 mb-0">v<?= htmlspecialchars((string) $estado['plataformaVersion']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Módulos</div>
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead><tr><th>Clave</th><th>Declarada</th><th>Instalada</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($estado['modulos'] as $clave => $m): ?>
          <tr>
            <td><code><?= htmlspecialchars((string) $clave) ?></code></td>
            <td><?= htmlspecialchars((string) $m['declarada']) ?></td>
            <td><?= $m['instalada'] === null ? '<span class="text-muted">no instalado</span>' : htmlspecialchars((string) $m['instalada']) ?></td>
            <td>
              <?php if ($m['actualizacionDisponible']): ?>
                <span class="badge bg-warning text-dark">Actualización disponible</span>
              <?php elseif (!$m['activo']): ?>
                <span class="badge bg-secondary">Inactivo</span>
              <?php else: ?>
                <span class="badge bg-success">Al día</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-white fw-semibold">Migraciones pendientes (<?= count($estado['migracionesPendientes']) ?>)</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($estado['migracionesPendientes'] as $p): ?>
            <li class="list-group-item small"><span class="text-muted">[<?= htmlspecialchars((string) $p['modulo']) ?>]</span> <?= htmlspecialchars((string) $p['archivo']) ?></li>
          <?php endforeach; ?>
          <?php if ($estado['migracionesPendientes'] === []): ?>
            <li class="list-group-item small text-muted">Ninguna.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-white fw-semibold">Checksums modificados (<?= count($estado['checksumsModificados']) ?>)</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($estado['checksumsModificados'] as $c): ?>
            <li class="list-group-item small text-danger"><span class="text-muted">[<?= htmlspecialchars((string) $c['modulo']) ?>]</span> <?= htmlspecialchars((string) $c['archivo']) ?></li>
          <?php endforeach; ?>
          <?php if ($estado['checksumsModificados'] === []): ?>
            <li class="list-group-item small text-muted">Ninguno.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold">Health checks</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($estado['healthChecks'] as $h): ?>
        <li class="list-group-item small d-flex align-items-center">
          <?php if ($h['ok']): ?>
            <i class="bi bi-check-circle-fill text-success me-2"></i>
          <?php else: ?>
            <i class="bi bi-x-circle-fill text-danger me-2"></i>
          <?php endif; ?>
          <span class="fw-semibold me-2"><?= htmlspecialchars((string) $h['clave']) ?></span>
          <span class="text-muted"><?= htmlspecialchars((string) $h['detalle']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
```

- [ ] **Step 3: Verificar sintaxis de ambos**

Run: `php -l app/Presentation/Controllers/Admin/SistemaEstadoController.php && php -l app/Presentation/Views/admin/sistema/estado.php`
Expected: `No syntax errors detected` (×2)

- [ ] **Step 4: Commit**

```bash
git add app/Presentation/Controllers/Admin/SistemaEstadoController.php app/Presentation/Views/admin/sistema/estado.php
git commit -m "feat(install): página admin Estado del sistema (controlador + vista)"
```

---

## Fase 6 — Wizard web (`public/install/`)

> El wizard corre **fuera** del router admin (puede ejecutarse antes de que existan seeds/sesión). Reutiliza Autoloader + EnvLoader + Config + el contenedor para obtener `Installer`. Estado del asistente en `$_SESSION`. Seguridad: lock file + token en producción + CSRF.

### Task 22: Front controller del wizard (bootstrap, seguridad, ruteo de pasos)

**Files:**
- Create: `public/install/index.php`
- Create: `public/install/views/_layout.php`
- Create: `public/install/views/ya_instalado.php`

- [ ] **Step 1: Crear el layout standalone**

`public/install/views/_layout.php`:

```php
<?php /** @var string $contenido */ /** @var string $tituloPaso */ ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instalador — <?= htmlspecialchars($tituloPaso ?? '') ?></title>
  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/lebytek-ui.css">
</head>
<body class="bg-light">
  <div class="container" style="max-width: 720px;">
    <div class="py-4 text-center">
      <h1 class="h4">Instalador Lebytek</h1>
    </div>
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <?= $contenido ?>
      </div>
    </div>
    <p class="text-center text-muted small mt-3">Lebytek Framework</p>
  </div>
</body>
</html>
```

- [ ] **Step 2: Crear la vista "ya instalado"**

`public/install/views/ya_instalado.php`:

```php
<h2 class="h5 mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Sistema ya instalado</h2>
<p class="text-muted">Este despliegue ya fue instalado. El asistente está bloqueado por <code>storage/install.lock</code>.</p>
<pre class="bg-light p-3 small rounded"><?= htmlspecialchars($lockResumen ?? '') ?></pre>
<a href="/login" class="btn btn-primary">Ir al login</a>
```

- [ ] **Step 3: Crear el front controller**

`public/install/index.php`:

```php
<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once APP_PATH . '/Kernel/Autoloader.php';

use App\Kernel\EnvLoader;
use App\Kernel\Config\Config;
use App\Kernel\Database\Connection;
use App\Kernel\Container\Container;
use App\Application\Install\Installer;
use App\Application\Install\ModuleRegistry;

EnvLoader::load(ROOT_PATH . '/.env');
Config::init(ROOT_PATH . '/config');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$lockFile = STORAGE_PATH . '/install.lock';

/** Renderiza una vista del wizard dentro del layout. */
function wizard_render(string $vista, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/views/' . $vista . '.php';
    $contenido = ob_get_clean();
    require __DIR__ . '/views/_layout.php';
    exit;
}

// 1) Ya instalado → solo lectura.
if (is_file($lockFile)) {
    wizard_render('ya_instalado', [
        'tituloPaso'  => 'Ya instalado',
        'lockResumen' => (string) @file_get_contents($lockFile),
    ]);
}

// 2) Token exigido en producción.
$esProd = (string) Config::get('app.env', 'production') === 'production';
if ($esProd) {
    $tokenEsperado = (string) EnvLoader::get('INSTALL_TOKEN', '');
    $tokenRecibido = (string) ($_GET['token'] ?? $_POST['token'] ?? $_SESSION['install_token'] ?? '');
    if ($tokenEsperado === '' || !hash_equals($tokenEsperado, $tokenRecibido)) {
        http_response_code(403);
        echo 'Instalador protegido. Proporcione ?token=INSTALL_TOKEN (definido en .env).';
        exit;
    }
    $_SESSION['install_token'] = $tokenRecibido;
}

// 3) CSRF para POST.
if (!isset($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['install_csrf'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(419);
        echo 'Token CSRF inválido. Recargue el asistente.';
        exit;
    }
}

// 4) Conexión BD (los pasos la usan; si falla, el paso BD lo reporta).
try {
    Connection::configure([
        'host'     => Config::get('database.host'),
        'port'     => Config::get('database.port'),
        'database' => Config::get('database.database'),
        'username' => Config::get('database.username'),
        'password' => Config::get('database.password'),
        'charset'  => 'utf8mb4',
    ]);
} catch (\Throwable) {
    // Silencioso aquí; el paso de BD comprobará y mostrará el detalle.
}

$container = new Container();
(require ROOT_PATH . '/config/container.php')($container);
/** @var Installer $installer */
$installer = $container->get(Installer::class);
/** @var ModuleRegistry $registry */
$registry  = $container->get(ModuleRegistry::class);

$paso = (string) ($_GET['paso'] ?? 'requisitos');

require __DIR__ . '/steps.php';
```

- [ ] **Step 4: Verificar sintaxis**

Run: `php -l public/install/index.php && php -l public/install/views/_layout.php && php -l public/install/views/ya_instalado.php`
Expected: `No syntax errors detected` (×3)

- [ ] **Step 5: Commit**

```bash
git add public/install/index.php public/install/views/_layout.php public/install/views/ya_instalado.php
git commit -m "feat(install): front controller del wizard (lock, token, CSRF, bootstrap)"
```

---

### Task 23: Lógica de pasos del wizard (`steps.php`)

**Files:**
- Create: `public/install/steps.php`
- Create: `public/install/views/paso_requisitos.php`
- Create: `public/install/views/paso_bd.php`
- Create: `public/install/views/paso_modulos.php`
- Create: `public/install/views/paso_admin.php`
- Create: `public/install/views/paso_revision.php`
- Create: `public/install/views/paso_resultado.php`

- [ ] **Step 1: Crear el despachador de pasos**

`public/install/steps.php` (incluido al final de `index.php`; tiene en alcance `$installer`, `$registry`, `$csrf`, `$paso`):

```php
<?php

declare(strict_types=1);

use App\Presentation\Controllers\AdminBaseController; // no usado; placeholder de import evitado

/** Helper de escape para las vistas. */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES); }

switch ($paso) {

    case 'requisitos':
        $checks = $installer->requisitosCheck();
        $puedeSeguir = !in_array(false, array_column($checks, 'ok'), true);
        wizard_render('paso_requisitos', [
            'tituloPaso' => 'Requisitos', 'checks' => $checks, 'puedeSeguir' => $puedeSeguir, 'csrf' => $csrf,
        ]);
        break;

    case 'bd':
        $checks = $installer->requisitosCheck();
        $bd = array_values(array_filter($checks, fn($c) => $c['clave'] === 'bd'))[0] ?? ['ok' => false, 'detalle' => 'Sin información'];
        wizard_render('paso_bd', ['tituloPaso' => 'Base de datos', 'bd' => $bd, 'csrf' => $csrf]);
        break;

    case 'modulos':
        $manifests = $registry->all();
        wizard_render('paso_modulos', ['tituloPaso' => 'Módulos', 'manifests' => $manifests, 'csrf' => $csrf]);
        break;

    case 'admin':
        // Guarda selección de módulos en sesión.
        $sel = $_POST['modulos'] ?? ['core'];
        $_SESSION['install_modulos'] = array_values(array_unique(array_merge(['core'], is_array($sel) ? $sel : [])));
        wizard_render('paso_admin', ['tituloPaso' => 'Cuenta admin', 'csrf' => $csrf]);
        break;

    case 'revision':
        // Guarda y valida datos del admin.
        $email = trim((string) ($_POST['email'] ?? ''));
        $pass  = (string) ($_POST['password'] ?? '');
        $errores = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errores[] = 'Email inválido.'; }
        if (strlen($pass) < 8) { $errores[] = 'La contraseña debe tener al menos 8 caracteres.'; }
        if ($errores !== []) {
            wizard_render('paso_admin', ['tituloPaso' => 'Cuenta admin', 'csrf' => $csrf, 'errores' => $errores, 'email' => $email]);
        }
        $_SESSION['install_admin'] = ['email' => $email, 'password' => $pass];

        $seleccion = $_SESSION['install_modulos'] ?? ['core'];
        $plan = $installer->plan($seleccion);
        wizard_render('paso_revision', ['tituloPaso' => 'Revisión', 'plan' => $plan, 'seleccion' => $seleccion, 'csrf' => $csrf]);
        break;

    case 'ejecutar':
        $seleccion = $_SESSION['install_modulos'] ?? ['core'];
        $admin     = $_SESSION['install_admin'] ?? null;

        // Schema base primero.
        (new App\Infrastructure\Install\SqlFileRunner())->ejecutar(ROOT_PATH . '/database/schema/schema.sql');

        $plan = $installer->plan($seleccion);
        $installer->aplicar($plan);

        // Escribir config/vertical.php con módulos activos.
        instalar_escribir_vertical($seleccion);

        // Crear/actualizar admin.
        if (is_array($admin)) {
            instalar_crear_admin($admin['email'], $admin['password']);
        }

        // Lock file.
        $resumen = json_encode([
            'version'  => Config::get('app.version', '0.0.0'),
            'fecha'    => date('c'),
            'modulos'  => $seleccion,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents(STORAGE_PATH . '/install.lock', (string) $resumen);

        // Limpiar estado del asistente.
        unset($_SESSION['install_modulos'], $_SESSION['install_admin']);

        wizard_render('paso_resultado', [
            'tituloPaso' => 'Listo',
            'version'    => (string) Config::get('app.version', '0.0.0'),
            'seleccion'  => $seleccion,
        ]);
        break;

    default:
        http_response_code(404);
        echo 'Paso desconocido.';
}

/**
 * Escribe config/vertical.php conservando labels si existían.
 *
 * @param list<string> $seleccion
 */
function instalar_escribir_vertical(array $seleccion): void
{
    $ruta = ROOT_PATH . '/config/vertical.php';
    $labels = [];
    if (is_file($ruta)) {
        $actual = require $ruta;
        $labels = $actual['labels'] ?? ['menu' => []];
    }

    // dashboard/administracion son módulos de plataforma siempre activos;
    // el resto se enciende según selección.
    $modules = ['dashboard' => true, 'administracion' => true];
    foreach ($seleccion as $clave) {
        if ($clave === 'core') { continue; }
        $modules[$clave] = true;
    }

    $export = var_export(['modules' => $modules, 'labels' => $labels], true);
    $php = "<?php\n\ndeclare(strict_types=1);\n\n// Generado por el instalador (" . date('c') . ").\nreturn {$export};\n";
    file_put_contents($ruta, $php);
}

function instalar_crear_admin(string $email, string $passwordPlano): void
{
    $pdo  = Connection::getInstance();
    $hash = password_hash($passwordPlano, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT id FROM auth_usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row) {
        $upd = $pdo->prepare("UPDATE auth_usuarios SET password = ?, activo = 1 WHERE id = ?");
        $upd->execute([$hash, (int) $row['id']]);
        $usuarioId = (int) $row['id'];
    } else {
        $ins = $pdo->prepare("INSERT INTO auth_usuarios (nombre, email, password, activo) VALUES (?, ?, ?, 1)");
        $ins->execute(['Administrador', $email, $hash]);
        $usuarioId = (int) $pdo->lastInsertId();
    }

    // Asignar rol administrador.
    $rol = $pdo->query("SELECT id FROM auth_roles WHERE slug = 'administrador' LIMIT 1")->fetch();
    if ($rol) {
        $rel = $pdo->prepare("INSERT IGNORE INTO auth_usuarios_roles (usuario_id, rol_id) VALUES (?, ?)");
        $rel->execute([$usuarioId, (int) $rol['id']]);
    }
}
```

> ⚠️ Antes de implementar `instalar_crear_admin`, confirma los nombres exactos de columnas/tablas con:
> `php -r "echo file_get_contents('database/schema/schema.sql');" | findstr /i "auth_usuarios"`
> Ajusta `nombre`/`password`/`activo`/`auth_usuarios_roles` si el schema difiere. La forma del seed admin está en `database/seeds/030_auth_usuario_admin.sql` — úsala como fuente de verdad de columnas.

- [ ] **Step 2: Crear `paso_requisitos.php`**

```php
<?php /** @var array $checks */ /** @var bool $puedeSeguir */ /** @var string $csrf */ ?>
<h2 class="h5 mb-3">1. Requisitos del sistema</h2>
<ul class="list-group mb-3">
  <?php foreach ($checks as $c): ?>
    <li class="list-group-item d-flex align-items-center">
      <i class="bi <?= $c['ok'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?> me-2"></i>
      <span class="fw-semibold me-2"><?= e($c['clave']) ?></span>
      <span class="text-muted small"><?= e($c['detalle']) ?></span>
    </li>
  <?php endforeach; ?>
</ul>
<?php if ($puedeSeguir): ?>
  <a href="?paso=bd" class="btn btn-primary">Continuar</a>
<?php else: ?>
  <div class="alert alert-warning">Corrige los requisitos en rojo antes de continuar.</div>
  <a href="?paso=requisitos" class="btn btn-outline-secondary">Reintentar</a>
<?php endif; ?>
```

- [ ] **Step 3: Crear `paso_bd.php`**

```php
<?php /** @var array $bd */ /** @var string $csrf */ ?>
<h2 class="h5 mb-3">2. Conexión a la base de datos</h2>
<div class="alert <?= $bd['ok'] ? 'alert-success' : 'alert-danger' ?>"><?= e($bd['detalle']) ?></div>
<?php if ($bd['ok']): ?>
  <a href="?paso=modulos" class="btn btn-primary">Continuar</a>
<?php else: ?>
  <p class="text-muted small">Edita <code>.env</code> con credenciales válidas y reintenta. El instalador no modifica <code>.env</code>.</p>
  <a href="?paso=bd" class="btn btn-outline-secondary">Reintentar</a>
<?php endif; ?>
```

- [ ] **Step 4: Crear `paso_modulos.php`**

```php
<?php /** @var array $manifests */ /** @var string $csrf */ ?>
<h2 class="h5 mb-3">3. Selección de módulos</h2>
<form method="post" action="?paso=admin">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <?php foreach ($manifests as $clave => $m): ?>
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" name="modulos[]"
             value="<?= e($clave) ?>" id="mod_<?= e($clave) ?>"
             <?= $m->obligatorio ? 'checked disabled' : 'checked' ?>>
      <label class="form-check-label" for="mod_<?= e($clave) ?>">
        <span class="fw-semibold"><?= e($m->nombre) ?></span>
        <span class="badge bg-light text-dark ms-1">v<?= e($m->version) ?></span>
        <?php if ($m->obligatorio): ?><span class="badge bg-secondary ms-1">obligatorio</span><?php endif; ?>
        <div class="text-muted small"><?= e($m->descripcion) ?></div>
      </label>
    </div>
  <?php endforeach; ?>
  <?php // core obligatorio: campo oculto garantiza su envío aunque el checkbox esté disabled ?>
  <input type="hidden" name="modulos[]" value="core">
  <button type="submit" class="btn btn-primary mt-2">Continuar</button>
</form>
```

- [ ] **Step 5: Crear `paso_admin.php`**

```php
<?php /** @var string $csrf */ /** @var array $errores */ /** @var string $email */ ?>
<h2 class="h5 mb-3">4. Cuenta de administrador</h2>
<?php foreach (($errores ?? []) as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>
<form method="post" action="?paso=revision">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control" required value="<?= e($email ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Contraseña (mín. 8 caracteres)</label>
    <input type="password" name="password" class="form-control" required minlength="8">
  </div>
  <button type="submit" class="btn btn-primary">Continuar</button>
</form>
```

- [ ] **Step 6: Crear `paso_revision.php`**

```php
<?php /** @var \App\Application\Install\InstallPlan $plan */ /** @var array $seleccion */ /** @var string $csrf */ ?>
<h2 class="h5 mb-3">5. Revisión</h2>
<p>Modo: <strong><?= $plan->nueva ? 'Instalación nueva' : 'Actualización' ?></strong></p>

<h3 class="h6 mt-3">Módulos a registrar</h3>
<p><?= e(implode(', ', $seleccion)) ?></p>

<h3 class="h6 mt-3">Migraciones pendientes (<?= count($plan->migracionesPendientes) ?>)</h3>
<ul class="small">
  <?php foreach ($plan->migracionesPendientes as $m): ?>
    <li>[<?= e($m['modulo']) ?>] <?= e($m['archivo']) ?></li>
  <?php endforeach; ?>
</ul>

<h3 class="h6 mt-3">Seeds pendientes (<?= count($plan->seedsPendientes) ?>)</h3>
<ul class="small">
  <?php foreach ($plan->seedsPendientes as $s): ?>
    <li>[<?= e($s['modulo']) ?>] <?= e($s['archivo']) ?></li>
  <?php endforeach; ?>
</ul>

<form method="post" action="?paso=ejecutar" onsubmit="this.querySelector('button').disabled=true;">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <button type="submit" class="btn btn-success">Instalar ahora</button>
</form>
```

- [ ] **Step 7: Crear `paso_resultado.php`**

```php
<?php /** @var string $version */ /** @var array $seleccion */ ?>
<h2 class="h5 mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Instalación completada</h2>
<p>Plataforma <strong>v<?= e($version) ?></strong> instalada.</p>
<p class="text-muted small">Módulos activos: <?= e(implode(', ', $seleccion)) ?></p>
<p class="text-muted small">El asistente quedó bloqueado por <code>storage/install.lock</code>.</p>
<a href="/login" class="btn btn-primary">Ir al login</a>
```

- [ ] **Step 8: Verificar sintaxis de todos los archivos**

Run: `php -l public/install/steps.php && php -l public/install/views/paso_requisitos.php && php -l public/install/views/paso_bd.php && php -l public/install/views/paso_modulos.php && php -l public/install/views/paso_admin.php && php -l public/install/views/paso_revision.php && php -l public/install/views/paso_resultado.php`
Expected: `No syntax errors detected` (×7)

- [ ] **Step 9: Eliminar el import placeholder no usado**

En `public/install/steps.php`, borra la línea `use App\Presentation\Controllers\AdminBaseController; // ...` (quedó como recordatorio; no se usa). Verifica de nuevo: `php -l public/install/steps.php`.

- [ ] **Step 10: Commit**

```bash
git add public/install/steps.php public/install/views/
git commit -m "feat(install): pasos del wizard (requisitos→bd→módulos→admin→revisión→ejecución)"
```

---

## Fase 7 — Documentación y verificación final

### Task 24: Documentación

**Files:**
- Create: `docs/core/instalacion-y-versionado.md`
- Modify: `docs/core/despliegue_hosting.md` (nota de remisión)

- [ ] **Step 1: Crear la guía**

`docs/core/instalacion-y-versionado.md`:

```markdown
# Instalación y versionado

Cada despliegue es autodescriptivo y versionado mediante:

- **Manifiestos de módulo** (`config/modules/*.php`): declaran versión, dependencias, y los archivos `.sql`/cruds que el módulo posee.
- **Tablas de versionado** (`cfg_migraciones`, `cfg_modulos`): qué se aplicó y qué versión de cada módulo está instalada.
- **Versión de plataforma**: `config/app.php` → `version`.

## Superficies (misma lógica)

| Superficie | Cómo |
|---|---|
| Wizard web | `https://tu-host/install/` (lock + token en producción) |
| CLI instalar | `php scripts/install.php [--modules=core,crud-engine] [--dry-run] [--baseline]` |
| CLI estado | `php scripts/status.php` |
| Página admin | `/admin/sistema/estado` (permiso `sistema.ver`) |

## Flujos

- **Instalación nueva:** schema base → migraciones/seeds pendientes → registro de versiones → admin → `install.lock`.
- **Actualización:** aplica solo lo pendiente por checksum; actualiza versiones de módulo.
- **Deploy legacy:** `php scripts/install.php --baseline` marca el histórico como aplicado sin re-ejecutar.

## Estandarización

Toda migración/seed pertenece a **exactamente un** manifiesto. El test
`tests/Install/EstandarizacionIntegridadTest.php` falla si hay archivos huérfanos o con doble dueño.

## Añadir un módulo nuevo

1. Crea `config/modules/<clave>.php` (ver contrato en `crud-engine.php`).
2. Lista sus migraciones/seeds y declara `requiere`.
3. Corre `php tests/run.php Install` (todo verde) y `php scripts/status.php`.
```

- [ ] **Step 2: Añadir nota de remisión en despliegue**

Al inicio de `docs/core/despliegue_hosting.md`, tras el primer encabezado, añade:

```markdown
> **Instalación y versionado:** el flujo de instalación (wizard web, CLI con tracking, página de estado y manifiestos de módulo) está documentado en [`instalacion-y-versionado.md`](instalacion-y-versionado.md).
```

- [ ] **Step 3: Commit**

```bash
git add docs/core/instalacion-y-versionado.md docs/core/despliegue_hosting.md
git commit -m "docs(install): guía de instalación y versionado + remisión desde despliegue"
```

---

### Task 25: Verificación final de la suite y criterios de aceptación

**Files:** _(ninguno — verificación)_

- [ ] **Step 1: Correr toda la suite de tests**

Run: `php tests/run.php`
Expected: `N passed, 0 failed` (incluye todos los `Install/*Test.php` nuevos y los CRUD existentes).

- [ ] **Step 2: Correr solo la suite de instalación**

Run: `php tests/run.php Install`
Expected: todos verdes; incluye integridad de estandarización sobre los manifiestos reales.

- [ ] **Step 3: Verificación de sintaxis global de los archivos nuevos**

Run:
```bash
php -l scripts/install.php && php -l scripts/status.php && php -l config/container.php && php -l routes/web.php && php -l public/install/index.php && php -l public/install/steps.php
```
Expected: `No syntax errors detected` en todos.

- [ ] **Step 4: Repasar criterios de aceptación del spec**

Confirmar manualmente contra `docs/superpowers/specs/2026-06-08-instalacion-estandarizacion-versionado-design.md` §13:
1. ✅ Manifiestos `core`/`dashboard`/`crud-engine`; dueño único (Task 4 + Task 19).
2. ✅ `cfg_migraciones`/`cfg_modulos` creadas; `baseline()` adopta legacy (Task 2, 14).
3. ✅ Wizard nuevo: requisitos→conexión→módulos→admin→preview→ejecución + lock + token (Task 22-23).
4. ✅ Wizard escribe `config/vertical.php` (Task 23, `instalar_escribir_vertical`).
5. ✅ Actualización aplica solo pendientes + versiones (Task 14).
6. ✅ `/admin/sistema/estado` con `sistema.ver` muestra versión/módulos/pendientes/checksums/health (Task 20-21).
7. ✅ `install.php --dry-run` y `status.php` sobre misma lógica (Task 17-18).
8. ✅ `php tests/run.php` verde (este Step).

- [ ] **Step 5: Commit final (si hubo ajustes)**

```bash
git add -A
git commit -m "chore(install): verificación final de instalación/versionado"
```

---

## Notas de ejecución

- **Tests que tocan BD** (repos PDO, `aplicar()` real, `crear_admin`) no se cubren con el harness (que no abre conexión). Se ejercitan vía `scripts/install.php --dry-run`/`status.php` y la prueba manual del wizard en el VPS de testing. El motor puro (`plan`, `baseline`, validadores, resolver, status) sí está cubierto con fakes.
- **Orden de Fases:** 0→1→2→3 son prerequisito duro de 4/5/6. Las Fases 5 (página) y 6 (wizard) son independientes entre sí una vez completada la 3+4.
- **`config/cruds/` formato:** el plan asume `.json`. Verifica con el comando de la Task 19 Step 2 y ajusta globs si el repo usa `.php`.
```
