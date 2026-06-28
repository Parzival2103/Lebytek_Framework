# Separación Framework v1.0 / Dominio Lebytek — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convertir el monolito actual (framework + dominio mezclados bajo `App\`) en un paquete Composer puro `lebytek/framework` (namespace `Lebytek\Framework\` → `src/`) más un esqueleto de aplicación, dejando el repo actual listo para taggear como **v1.0.0** y consumible desde un Repo 2 nuevo.

**Architecture:** Patrón framework + skeleton (estilo Symfony/Laravel). El código genérico se mueve a `src/` con namespace propio; el dominio (Marketing) y los demos quedan en `app/`/esqueleto bajo `App\`. La extensión se hace por un entrypoint único `FrameworkServiceProvider` (mínimo viable) más los manifiestos de módulo existentes. Cada paso mantiene **verde el arnés de tests** como red de seguridad (es un refactor que preserva comportamiento).

**Tech Stack:** PHP 8.1+, Composer (autoload PSR-4), arnés de tests propio del repo, Git. Plataforma de trabajo: Windows + Git Bash; los scripts de migración son PHP (cross-platform).

## Global Constraints

- Namespaces: framework → `Lebytek\Framework\`; dominio → `App\`. Copiado literal del spec §2.
- El paquete **jamás** referencia `App\` (regla Onion; criterio de aceptación #2).
- Un solo paquete `lebytek/framework` (no micro-paquetes). Spec §2.3.
- Demos (`demo_*`, `marketing_demo`, CRUD showcase) quedan en el esqueleto **OFF** por defecto en `config/vertical.php`. Spec §2.7.
- El plan **debe** crear el archivo de constancia `docs/superpowers/PENDIENTE-promocion-modulos-providers.md` listando módulos pendientes de promover a provider por módulo (listar, no implementar). Spec §6.4 / criterio #7.
- Cada cambio estructural termina con el arnés de tests verde antes de commitear.
- Comando de tests: `php tests/run.php` (confirmar exacto en Task 1).
- Subtrees de **dominio** que NO se mueven a `src/` (se quedan `App\`): `app/Domain/Marketing`, `app/Application/Marketing`, `app/Infrastructure/Marketing`, y cualquier Presentation Marketing (confirmar lista exacta en Task 3).
- Carpetas a archivar/descartar (no entran a ningún artefacto): `auditoria/`, `nuevo_modulo/`, `plan/`, `vertical-kit/`, `database/migrations_legacy/`, `database/seeds_legacy/`.

---

## Task 1: Red de seguridad — commit pendiente, respaldo y baseline de tests

**Files:**
- Modify: none (operaciones de git + verificación)
- Create: none

**Interfaces:**
- Consumes: estado actual del working tree (hay cambios sin commitear de GreenApi).
- Produces: tag `pre-split-backup` apuntando al estado previo; número de tests que pasan en baseline (referencia para los gates siguientes); comando de test confirmado.

- [ ] **Step 1: Confirmar el comando del arnés de tests**

Run:
```bash
ls tests/run.php 2>/dev/null && echo "USAR: php tests/run.php" || ls tests/*.php
```
Si `tests/run.php` no existe, identificar el runner (p. ej. `tests/HarnessSelfTest.php`) y usar `php tests/HarnessSelfTest.php`. Anotar el comando confirmado; se usará como **GATE** en todas las tareas siguientes.

- [ ] **Step 2: Commitear el trabajo pendiente de GreenApi (para arrancar limpio)**

Run:
```bash
git add -A && git commit -m "chore(integrations): consolidar trabajo GreenApi en curso antes del carve"
git status --short
```
Expected: `git status --short` vacío (working tree limpio).

- [ ] **Step 3: Ejecutar el arnés y registrar el baseline**

Run: `php tests/run.php` (o el comando confirmado en Step 1)
Expected: PASS. Anotar el número de tests/aserciones que pasan (p. ej. "120 passed"). Este número es el **baseline**: ningún paso posterior puede reducirlo.

- [ ] **Step 4: Crear el tag de respaldo**

Run:
```bash
git tag -a pre-split-backup -m "Respaldo del monolito antes de separar framework/dominio"
git tag --list pre-split-backup
```
Expected: el tag `pre-split-backup` aparece listado.

- [ ] **Step 5: Commit (no aplica — solo tag)**

No hay archivos que commitear; el deliverable es el tag y el baseline anotado. Continuar.

---

## Task 2: Migrar al autoload de Composer (eliminar el autoloader manual)

**Files:**
- Modify: `composer.json` (añadir bloque `autoload`)
- Modify: `app/Kernel/Bootstrap.php` (reemplazar `require` del autoloader manual por `vendor/autoload.php`)
- Delete: `app/Kernel/Autoloader.php`

**Interfaces:**
- Consumes: namespace `App\` → `app/` (estado actual).
- Produces: autoload PSR-4 de Composer activo para `App\` → `app/`. A partir de aquí el arranque depende de `vendor/autoload.php`.

- [ ] **Step 1: Añadir el bloque autoload a composer.json**

En `composer.json`, añadir (manteniendo `require` y `config` existentes):
```json
"autoload": {
    "psr-4": {
        "App\\": "app/"
    }
}
```

- [ ] **Step 2: Regenerar el autoloader de Composer**

Run:
```bash
composer dump-autoload
```
Expected: "Generated autoload files".

- [ ] **Step 3: Reemplazar el require del autoloader manual en Bootstrap**

En `app/Kernel/Bootstrap.php`, localizar la línea que hace `require` de `Autoloader.php` (p. ej. `require __DIR__ . '/Autoloader.php';`) y reemplazarla por la carga del autoloader de Composer:
```php
require ROOT_PATH . '/vendor/autoload.php';
```
(Asegurar que esta línea quede antes de cualquier uso de clases `App\`.)

- [ ] **Step 4: Eliminar el autoloader manual**

Run:
```bash
git rm app/Kernel/Autoloader.php
```

- [ ] **Step 5: GATE — arnés verde**

Run: `php tests/run.php`
Expected: PASS, con el mismo número de baseline (Task 1 Step 3). Si falla por orden de carga, verificar que `vendor/autoload.php` se requiere antes del primer uso de una clase `App\`.

- [ ] **Step 6: Commit**

```bash
git add composer.json app/Kernel/Bootstrap.php
git commit -m "refactor(kernel): usar autoload de Composer y eliminar autoloader manual"
```

---

## Task 3: Inventariar los subtrees de dominio (Marketing) a preservar

**Files:**
- Create: `docs/superpowers/_carve-marketing-namespaces.txt` (archivo de trabajo temporal con la lista exacta)

**Interfaces:**
- Consumes: árbol `app/` actual.
- Produces: la **lista exacta** de namespaces de dominio que NO se renombran (entrada para el script de Task 5). Sin esta lista, el rename global rompería Marketing.

- [ ] **Step 1: Listar todos los namespaces que contienen "Marketing"**

Run:
```bash
grep -rho 'namespace App\\[A-Za-z0-9_\\]*Marketing[A-Za-z0-9_\\]*' app | sort -u > docs/superpowers/_carve-marketing-namespaces.txt
grep -rl 'Marketing' app/Presentation 2>/dev/null >> docs/superpowers/_carve-marketing-namespaces.txt
cat docs/superpowers/_carve-marketing-namespaces.txt
```
Expected: lista con al menos `App\Domain\Marketing`, `App\Application\Marketing`, `App\Infrastructure\Marketing` y cualquier ruta de Presentation con Marketing.

- [ ] **Step 2: Confirmar que el framework NO depende de Marketing (regla Onion)**

Run:
```bash
grep -rn 'Marketing' app/Kernel app/Domain/Interfaces app/Application/Crud app/Application/Services 2>/dev/null | grep -v '/Marketing/' || echo "OK: sin referencias de framework a Marketing"
```
Expected: "OK: sin referencias de framework a Marketing". Si aparece alguna, anotarla: es un acoplamiento a romper antes de continuar (el framework no debe conocer Marketing).

- [ ] **Step 3: Commit (archivo de trabajo)**

```bash
git add docs/superpowers/_carve-marketing-namespaces.txt
git commit -m "chore(carve): inventario de namespaces de dominio (Marketing) a preservar"
```

---

## Task 4: Separar físicamente framework (`src/`) y dominio (`app/`)

**Files:**
- Move: `app/**` → `src/**` (todo), luego devolver los subtrees Marketing a `app/`
- Modify: `composer.json` (segundo root PSR-4)

**Interfaces:**
- Consumes: lista de Marketing de Task 3.
- Produces: `src/` con código framework (aún namespace `App\`); `app/` con solo dominio Marketing (aún `App\`). El rename de namespaces ocurre en Task 5.

- [ ] **Step 1: Mover todo app/ a src/**

Run:
```bash
git mv app src
```
Expected: `src/` contiene Kernel, Domain, Application, Infrastructure, Presentation.

- [ ] **Step 2: Devolver los subtrees Marketing a app/ (dominio)**

Run (ajustar si Task 3 reveló rutas Presentation adicionales):
```bash
mkdir -p app/Domain app/Application app/Infrastructure
git mv src/Domain/Marketing app/Domain/Marketing
git mv src/Application/Marketing app/Application/Marketing
git mv src/Infrastructure/Marketing app/Infrastructure/Marketing
```
Para cada ruta Presentation con Marketing detectada en Task 3, moverla análogamente a `app/Presentation/...`.
Expected: `app/` contiene solo subtrees Marketing; `src/` ya no tiene carpetas `Marketing`.

- [ ] **Step 3: Verificar que no quedó Marketing en src/**

Run:
```bash
find src -type d -name Marketing
```
Expected: sin salida.

- [ ] **Step 4: Añadir el segundo root PSR-4 en composer.json**

En `composer.json`, actualizar `autoload.psr-4` a:
```json
"autoload": {
    "psr-4": {
        "Lebytek\\Framework\\": "src/",
        "App\\": "app/"
    }
}
```

- [ ] **Step 5: Regenerar autoload (aún NO verde; el rename viene en Task 5)**

Run:
```bash
composer dump-autoload
```
Expected: "Generated autoload files". (El arnés **fallará** ahora porque `src/` todavía declara `namespace App\...` mapeado a `Lebytek\Framework\` — se corrige en Task 5; no commitear aún si se quiere un solo commit, pero por trazabilidad commiteamos el movimiento.)

- [ ] **Step 6: Commit del movimiento físico**

```bash
git add -A
git commit -m "refactor(carve): mover framework a src/ y aislar dominio Marketing en app/"
```

---

## Task 5: Renombrar namespaces (`App\` → `Lebytek\Framework\`) preservando Marketing

**Files:**
- Create: `scripts/_carve_rename.php` (script de migración temporal)
- Modify: todos los `.php` bajo `src/` (namespace + uses), los `.php` de dominio bajo `app/` (solo refs a framework), y los `.php` de `config/`, `routes/`, `public/`, `tests/`, `scripts/` que referencian clases framework.

**Interfaces:**
- Consumes: `src/` (framework con namespace `App\`), `app/` (Marketing con namespace `App\`).
- Produces: `src/` con namespace `Lebytek\Framework\`; refs a framework reescritas en todo el repo; Marketing intacto bajo `App\Domain\Marketing` etc. Arnés verde de nuevo.

- [ ] **Step 1: Escribir el script de rename**

Crear `scripts/_carve_rename.php`:
```php
<?php
declare(strict_types=1);

// Reescribe referencias de namespace para el carve framework/dominio.
// Regla:
//  - En src/  : TODO  App\  ->  Lebytek\Framework\   (el framework no referencia Marketing).
//  - Fuera de src/ (app/, config/, routes/, public/, tests/, scripts/):
//       App\X -> Lebytek\Framework\X  EXCEPTO los prefijos de dominio preservados.
$root = dirname(__DIR__);

$preserve = [
    'App\\Domain\\Marketing',
    'App\\Application\\Marketing',
    'App\\Infrastructure\\Marketing',
    // Añadir aquí prefijos Presentation de Marketing si Task 3 los detectó.
];

$targets = [
    'src'     => true,   // true = reescribir TODO App\ sin excepciones
    'app'     => false,
    'config'  => false,
    'routes'  => false,
    'public'  => false,
    'tests'   => false,
    'scripts' => false,
];

$placeholder = "\x00MKT\x00"; // sentinela para proteger los prefijos preservados

$changed = 0;
foreach ($targets as $dir => $rewriteAll) {
    $base = $root . '/' . $dir;
    if (!is_dir($base)) { continue; }
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if ($file->getExtension() !== 'php') { continue; }
        if ($file->getRealPath() === __FILE__) { continue; }
        $code = file_get_contents($file->getPathname());
        $orig = $code;

        if (!$rewriteAll) {
            foreach ($preserve as $p) {
                $code = str_replace($p, $placeholder . substr($p, 4) . $placeholder, $code);
            }
        }
        // Reescribir App\  ->  Lebytek\Framework\
        $code = preg_replace('/\bApp\\\\/', 'Lebytek\\Framework\\\\', $code);

        if (!$rewriteAll) {
            // Restaurar los preservados a App\...
            $code = str_replace($placeholder, '', $code);
            // tras quitar sentinelas quedan p.ej. "Domain\Marketing"; reponer App\ delante
            foreach ($preserve as $p) {
                $tail = substr($p, 4); // Domain\Marketing
                $code = str_replace('Lebytek\\Framework\\' . $tail, $p, $code); // por si algo coló
            }
        }

        if ($code !== $orig) {
            file_put_contents($file->getPathname(), $code);
            $changed++;
        }
    }
}
echo "Archivos modificados: {$changed}\n";
```

> Nota: el bloque de sentinela protege los prefijos de Marketing antes del `preg_replace` global y los restaura después, de modo que `App\Domain\Marketing` permanece `App\…` mientras `App\Kernel`, `App\Domain\Interfaces`, etc. pasan a `Lebytek\Framework\…`.

- [ ] **Step 2: Ejecutar el rename**

Run:
```bash
php scripts/_carve_rename.php
```
Expected: "Archivos modificados: N" (N de varios cientos).

- [ ] **Step 3: Verificar que src/ no tiene residuos de `App\`**

Run:
```bash
grep -rn 'App\\' src | grep -v 'Lebytek\\Framework' || echo "OK: src/ sin App\\ residual"
```
Expected: "OK: src/ sin App\\ residual". Si aparecen líneas, son strings/comentarios; revisarlas manualmente.

- [ ] **Step 4: Verificar que Marketing sigue bajo App\ en app/**

Run:
```bash
grep -rn 'namespace App\\\(Domain\|Application\|Infrastructure\)\\Marketing' app | head
```
Expected: las declaraciones `namespace App\Domain\Marketing;` etc. intactas.

- [ ] **Step 5: Regenerar autoload**

Run:
```bash
composer dump-autoload
```
Expected: "Generated autoload files".

- [ ] **Step 6: GATE — arnés verde**

Run: `php tests/run.php`
Expected: PASS con el número baseline. Errores típicos: una clase framework que aún se referencia como `App\` en un archivo no cubierto por `$targets` (añadir el dir y re-ejecutar Step 2) o un prefijo Marketing que faltó en `$preserve`.

- [ ] **Step 7: Eliminar el script temporal y commitear**

```bash
git rm scripts/_carve_rename.php
git add -A
git commit -m "refactor(carve): renombrar framework a Lebytek\\Framework preservando dominio Marketing"
```

---

## Task 6: Partir `container.php` en `FrameworkServiceProvider` + container delgado del proyecto

**Files:**
- Create: `src/Kernel/Container/FrameworkServiceProvider.php`
- Modify: `config/container.php` (queda delgado: llama al provider + bindings de dominio)
- Test: `tests/Kernel/FrameworkServiceProviderTest.php`

**Interfaces:**
- Consumes: `Lebytek\Framework\Kernel\Container\Container`.
- Produces: `Lebytek\Framework\Kernel\Container\FrameworkServiceProvider::register(Container $c): void` que registra TODOS los bindings genéricos. `config/container.php` del proyecto invoca `FrameworkServiceProvider::register($container)` y luego añade bindings de dominio (`App\…`).

- [ ] **Step 1: Escribir el test del provider**

Crear `tests/Kernel/FrameworkServiceProviderTest.php`:
```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Container\Container;
use Lebytek\Framework\Kernel\Container\FrameworkServiceProvider;
use Lebytek\Framework\Domain\Interfaces\UsuarioRepositoryInterface;

// Asume el arnés expone funciones assert_true(); ajustar al estilo del repo.
$container = new Container();
FrameworkServiceProvider::register($container);

assert_true(
    $container->has(UsuarioRepositoryInterface::class),
    'FrameworkServiceProvider registra UsuarioRepositoryInterface'
);
```
> Ajustar el `use` de `UsuarioRepositoryInterface` a su FQCN real tras el rename y el método de chequeo (`has`/`make`) a la API real del `Container` (verificar en `src/Kernel/Container/Container.php`).

- [ ] **Step 2: Ejecutar el test — debe fallar**

Run: `php tests/run.php` (o el test puntual si el arnés lo permite)
Expected: FAIL — `Class FrameworkServiceProvider not found`.

- [ ] **Step 3: Crear el FrameworkServiceProvider moviendo los bindings genéricos**

Crear `src/Kernel/Container/FrameworkServiceProvider.php` con namespace `Lebytek\Framework\Kernel\Container`. Mover dentro de un método estático `register(Container $container): void` **todos** los `bind`/`singleton` actuales de `config/container.php` que apuntan a clases `Lebytek\Framework\…` (es decir, los genéricos). Estructura:
```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Kernel\Container;

// ... use de las interfaces/implementaciones framework (Lebytek\Framework\...) ...

final class FrameworkServiceProvider
{
    public static function register(Container $container): void
    {
        // <<< aquí van TODOS los bindings genéricos que hoy viven en config/container.php >>>
        // Ejemplo (repetir el patrón existente para cada binding framework):
        // $container->singleton(UsuarioRepositoryInterface::class, fn() => new UsuarioRepository(...));
    }
}
```

- [ ] **Step 4: Adelgazar config/container.php**

Reescribir `config/container.php` para que delegue en el provider y conserve **solo** bindings de dominio (`App\…`, p. ej. Marketing):
```php
<?php

use Lebytek\Framework\Kernel\Container\Container;
use Lebytek\Framework\Kernel\Container\FrameworkServiceProvider;

return function (Container $container): void {
    // Framework (paquete):
    FrameworkServiceProvider::register($container);

    // Dominio (proyecto) — bindings App\... (Marketing, y a futuro dom_*):
    // $container->singleton(\App\Domain\Marketing\...Interface::class, fn() => new \App\Infrastructure\Marketing\...());
};
```
> Si hoy `container.php` retorna un array o se consume distinto, conservar su **forma de retorno actual**; lo único que cambia es que los bindings framework salen al provider. Verificar cómo `Bootstrap` carga `container.php` y respetar ese contrato.

- [ ] **Step 5: GATE — arnés verde**

Run: `php tests/run.php`
Expected: PASS con baseline. El test de Step 1 ahora pasa.

- [ ] **Step 6: Commit**

```bash
git add src/Kernel/Container/FrameworkServiceProvider.php config/container.php tests/Kernel/FrameworkServiceProviderTest.php
git commit -m "refactor(container): extraer bindings genericos a FrameworkServiceProvider"
```

---

## Task 7: Recablear el entry point al arranque por Composer + framework

**Files:**
- Modify: `public/index.php` (definir paths, require `vendor/autoload.php`, invocar Bootstrap del framework)
- Modify: `src/Kernel/Bootstrap.php` (que el require de autoload no dependa de rutas del monolito)

**Interfaces:**
- Consumes: `Lebytek\Framework\Kernel\Bootstrap`.
- Produces: arranque HTTP funcional donde `public/index.php` (propiedad del proyecto) define las constantes de ruta, carga `vendor/autoload.php` y delega en el Bootstrap del framework.

- [ ] **Step 1: Ajustar public/index.php**

Reescribir `public/index.php` para que el require apunte al autoloader de Composer y luego al arranque del framework:
```php
<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');     // dominio del proyecto
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('APP_START', microtime(true));

require ROOT_PATH . '/vendor/autoload.php';

\Lebytek\Framework\Kernel\Bootstrap::run();
```
> Ajustar `Bootstrap::run()` al nombre real del método de arranque (verificar en `src/Kernel/Bootstrap.php`; puede ser `boot()`/`handle()`/instanciación). Mantener las constantes que el framework espera.

- [ ] **Step 2: Ajustar Bootstrap si carga el autoloader o asume `app/` para framework**

En `src/Kernel/Bootstrap.php`, eliminar cualquier `require` de `vendor/autoload.php` (ya lo hace `index.php`) y asegurar que las rutas a **código framework** usen `__DIR__`/localización del paquete, no `APP_PATH`. `APP_PATH` solo debe usarse para descubrir código/manifiestos de **dominio**.

- [ ] **Step 3: GATE — arranque + arnés**

Run:
```bash
php -S localhost:8000 -t public &
sleep 2 && curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/ ; kill %1
php tests/run.php
```
Expected: código HTTP de una respuesta válida (200/302/login) y arnés PASS con baseline.

- [ ] **Step 4: Commit**

```bash
git add public/index.php src/Kernel/Bootstrap.php
git commit -m "refactor(bootstrap): arranque por Composer + Bootstrap del framework"
```

---

## Task 8: Extraer el esqueleto (mover lo app-level a `skeleton/`) y dejar demos OFF

**Files:**
- Create: `skeleton/` (estructura del esqueleto)
- Move: `public/`, `config/`, `routes/`, `.env.example`, `storage/`, `tests/` (lo del proyecto), `app/` (dominio Marketing + demos) → `skeleton/`
- Modify: `skeleton/config/vertical.php` (demos OFF), `skeleton/composer.json` (require lebytek/framework)

**Interfaces:**
- Consumes: el repo ya separado (paquete en `src/`, dominio/app-level repartido).
- Produces: un directorio `skeleton/` autocontenido que será la semilla de Repo 2; el resto del repo queda como paquete puro (`src/`, `database/schema` framework, manifiestos framework).

- [ ] **Step 1: Crear la estructura del esqueleto y mover lo app-level**

Run:
```bash
mkdir -p skeleton
git mv public skeleton/public
git mv config skeleton/config
git mv routes skeleton/routes
git mv storage skeleton/storage
git mv app skeleton/app
git mv .env.example skeleton/.env.example
```
> `tests/` se queda en la raíz si el arnés valida el **paquete**; si valida la app, moverlo también a `skeleton/tests`. Decidir según qué prueba el arnés (Task 1). Por defecto: mover los tests de dominio a `skeleton/tests` y dejar en `tests/` solo los del framework.

- [ ] **Step 2: Mover los manifiestos/schemas de dominio y demos al esqueleto**

Run:
```bash
mkdir -p skeleton/database/schema/modules
git mv database/schema/modules/marketing.sql skeleton/database/schema/modules/ 2>/dev/null || true
git mv database/schema/modules/marketing_demo.sql skeleton/database/schema/modules/ 2>/dev/null || true
```
> Los schemas framework (`schema.sql`, `calendario.sql`, `crud-engine.sql`, `integrations.sql`, `pdf-kit.sql`, `reportes.sql`) **se quedan** en `database/schema` del paquete. `crud-engine.sql` (datos demo) es el caso límite: si contiene datos demo, moverlo también al esqueleto.

- [ ] **Step 3: Apagar los demos por defecto en vertical.php**

En `skeleton/config/vertical.php`, poner en `false` los toggles de los módulos demo (`crud-engine`/demos, `marketing` y `marketing_demo` según corresponda a "demo"). Marketing como producto se reactivará en Repo 2; los **demos** quedan OFF.

- [ ] **Step 4: Crear el composer.json del esqueleto**

Crear `skeleton/composer.json`:
```json
{
    "name": "lebytek/skeleton",
    "description": "Esqueleto de aplicacion Lebytek (consume lebytek/framework)",
    "type": "project",
    "require": {
        "php": ">=8.1",
        "lebytek/framework": "^1.0"
    },
    "repositories": [
        { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
    ],
    "autoload": {
        "psr-4": { "App\\": "app/" }
    },
    "config": { "sort-packages": true }
}
```

- [ ] **Step 5: GATE — el esqueleto arranca contra el paquete**

Run (instalación de prueba del esqueleto consumiendo el paquete local):
```bash
cd skeleton && composer config repositories.local path ../ && composer require lebytek/framework:@dev --no-interaction && php -S localhost:8001 -t public &
sleep 2 && curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8001/ ; kill %1 ; cd ..
```
Expected: HTTP válido sirviendo desde `skeleton/public` con el framework cargado desde el paquete (vía path repo local). Esto prueba el contrato paquete↔esqueleto antes de crear Repo 2.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(skeleton): extraer app-level a skeleton/, demos OFF, require lebytek/framework"
```

---

## Task 9: Limpieza del paquete y `composer.json` del framework

**Files:**
- Modify: `composer.json` (raíz → metadata del paquete `lebytek/framework`)
- Delete/Archive: `auditoria/`, `nuevo_modulo/`, `plan/`, `vertical-kit/`, `database/migrations_legacy/`, `database/seeds_legacy/`

**Interfaces:**
- Consumes: repo con `src/` (framework) + `skeleton/` (semilla Repo 2).
- Produces: `composer.json` raíz que describe el paquete `lebytek/framework` (name, type library, autoload `Lebytek\Framework\` → `src/`, sin require de app).

- [ ] **Step 1: Reescribir el composer.json raíz como paquete**

```json
{
    "name": "lebytek/framework",
    "description": "Lebytek Framework — plataforma PHP para sistemas administrativos (core + modulos genericos)",
    "type": "library",
    "require": {
        "php": ">=8.1",
        "dompdf/dompdf": "^3.1",
        "phpmailer/phpmailer": "^7.1"
    },
    "autoload": {
        "psr-4": { "Lebytek\\Framework\\": "src/" }
    },
    "config": { "sort-packages": true }
}
```

- [ ] **Step 2: Archivar/eliminar carpetas fuera de alcance**

Run:
```bash
git rm -r auditoria nuevo_modulo plan vertical-kit database/migrations_legacy database/seeds_legacy 2>/dev/null || true
```
> Si se prefiere conservarlas, moverlas a `docs/archive/` en vez de borrar. Por defecto se eliminan del paquete (siguen en el tag `pre-split-backup`).

- [ ] **Step 3: Verificar que el paquete no referencia `App\`**

Run:
```bash
grep -rn 'App\\' src database 2>/dev/null | grep -v 'Lebytek\\Framework' || echo "OK: paquete sin App\\"
```
Expected: "OK: paquete sin App\\" (criterio de aceptación #2).

- [ ] **Step 4: GATE — autoload del paquete + arnés framework**

Run:
```bash
composer dump-autoload
php tests/run.php
```
Expected: PASS (los tests del framework). Si algún test movido al esqueleto falla aquí, es esperado: esos tests viven ahora en `skeleton/tests` y se corren desde el esqueleto.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore(package): composer.json de lebytek/framework y limpieza de carpetas fuera de alcance"
```

---

## Task 10: Archivo de constancia de promoción pendiente (REQUISITO del spec §6.4)

**Files:**
- Create: `docs/superpowers/PENDIENTE-promocion-modulos-providers.md`

**Interfaces:**
- Consumes: el estado del mecanismo de extensión (entrypoint único `FrameworkServiceProvider`, sin providers por módulo aún).
- Produces: la fuente de verdad del trabajo incremental pendiente.

- [ ] **Step 1: Crear el archivo de constancia**

Crear `docs/superpowers/PENDIENTE-promocion-modulos-providers.md`:
```markdown
# PENDIENTE — Promoción de módulos a "provider por módulo"

> Estado actual: el registro de bindings/rutas/menú/dashboard usa el **mínimo viable**
> (entrypoint único `Lebytek\Framework\Kernel\Container\FrameworkServiceProvider`).
> El modelo objetivo (spec 2026-06-27 §6) es un **ServiceProvider por módulo** que
> registre sus bindings, rutas, menú, dashboard contributions, settings sections y
> crud handlers vía su manifiesto (`config/modules/*.php`, campo `providers`).
>
> Este archivo LISTA lo pendiente. NO implementa la promoción (fuera de alcance del
> ciclo de separación). Cada módulo se promoverá en su propio ciclo.

## Módulos del framework pendientes de promover
- [ ] core
- [ ] crud-engine
- [ ] dashboard
- [ ] calendario
- [ ] pdf-kit
- [ ] reportes
- [ ] integrations

## Módulos de dominio (cuando existan, en Repo 2)
- [ ] marketing
- [ ] (futuro) dom_apiwa_* — producto "API sola"
- [ ] (futuro) dom_salon_* — vertical salones/citas

## Criterio de "promovido"
Un módulo está promovido cuando su manifiesto declara un `provider` que registra
TODO lo suyo (container, rutas, menú, dashboard, settings, crud handlers) y el
entrypoint único ya no contiene bindings de ese módulo.
```

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/PENDIENTE-promocion-modulos-providers.md
git commit -m "docs(carve): constancia de modulos pendientes de promover a provider por modulo"
```

---

## Task 11: README de consumo privado, taggear v1.0.0 y limpiar artefactos de trabajo

**Files:**
- Create/Modify: `README.md` (instrucciones de consumo del paquete privado por Composer)
- Delete: `docs/superpowers/_carve-marketing-namespaces.txt` (archivo de trabajo de Task 3)

**Interfaces:**
- Consumes: el paquete `lebytek/framework` ya limpio + el esqueleto.
- Produces: tag `v1.0.0`; documentación de cómo Repo 2/VPS consumen el paquete privado.

- [ ] **Step 1: Documentar el consumo del paquete privado (VCS + auth VPS)**

Añadir a `README.md` una sección "Consumo como paquete (privado)":
```markdown
## Consumo como paquete (privado)

Este repo es el paquete `lebytek/framework` (no es una app ejecutable por sí sola).
Un proyecto lo consume con Composer vía repositorio VCS privado:

    "repositories": [
        { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
    ],
    "require": { "lebytek/framework": "^1.0" }

En el VPS, Composer necesita auth para el repo privado: configurar una **deploy key**
SSH (o un token de GitHub en `auth.json`). El deploy hace `composer install`.
El antiguo soporte de "hosting compartido sin Composer" queda DESCARTADO: el VPS
ya usa Composer (dompdf/phpmailer se instalan así).
```

- [ ] **Step 2: Limpiar el archivo de trabajo temporal**

Run:
```bash
git rm docs/superpowers/_carve-marketing-namespaces.txt
```

- [ ] **Step 3: GATE final — arnés del paquete verde**

Run:
```bash
composer dump-autoload && php tests/run.php
```
Expected: PASS.

- [ ] **Step 4: Commit y tag v1.0.0**

```bash
git add -A
git commit -m "docs(package): README de consumo privado; limpiar artefactos de carve"
git tag -a v1.0.0 -m "Lebytek Framework v1.0.0 — paquete Composer separado del dominio"
git tag --list | grep v1.0.0
```
Expected: `v1.0.0` listado.

- [ ] **Step 5: Push (cuando el usuario lo autorice)**

> Solo tras revisión del usuario. El VPS auto-pull de este repo **ya no sirve una app** (es paquete); confirmar con el usuario antes de pushear para no romper el deploy actual hasta que Repo 2 exista.
```bash
git push origin main --tags
```

---

## Task 12 (OPS, fuera de código): crear Repo 2 y repuntar el VPS

> Esta tarea es operativa (no produce código en este repo). Documentar y ejecutar con el usuario.

- [ ] **Step 1: Crear Repo 2 desde el esqueleto**

Crear un repo nuevo (p. ej. `Lebytek_Producto`) con el contenido de `skeleton/` como commit inicial. Ajustar `composer.json` para apuntar al VCS del paquete (Task 8 Step 4) y quitar el repositorio `path` local de prueba.

- [ ] **Step 2: Instalar y verificar Repo 2**

Run en Repo 2:
```bash
composer install
php -S localhost:8002 -t public
```
Expected: la app arranca consumiendo `lebytek/framework` v1.0.0 desde GitHub.

- [ ] **Step 3: Migrar Marketing como producto (reactivar toggle)**

En Repo 2, encender `marketing` (producto) en `config/vertical.php`; dejar los **demos** OFF. Verificar arnés de dominio en `skeleton/tests` → `tests/`.

- [ ] **Step 4: Repuntar el auto-pull del VPS a Repo 2**

Cambiar el origin que el VPS hace pull, de `Lebytek_Framework` a `Lebytek_Producto`, con `composer install` en el deploy y la auth del paquete privado configurada. Verificar el sitio en producción.

---

## Notas de ejecución

- **Red de seguridad:** todo está respaldado en el tag `pre-split-backup` (Task 1). Ante un carve que se complique, `git reset --hard pre-split-backup` revierte.
- **El gate es el arnés:** este es un refactor que **preserva comportamiento**; el arnés de tests (no tests nuevos por feature) es la verificación principal. El único test nuevo es el de `FrameworkServiceProvider` (Task 6).
- **Tasks 1–11** ocurren en ESTE repo. **Task 12** es operativa (Repo 2 + VPS) y se hace con el usuario.
- **Fuera de alcance:** los 4 servicios de dominio (A–D) y la promoción módulo-por-módulo (solo se LISTA en Task 10).
