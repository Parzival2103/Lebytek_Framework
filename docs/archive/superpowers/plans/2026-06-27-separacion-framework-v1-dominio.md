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
- **Comando de tests (CONFIRMADO):** `php tests/run.php`. Es un arnés propio (no PHPUnit): `tests/run.php` recorre recursivamente `tests/**/*Test.php`, carga `tests/lib/bootstrap.php` + `tests/lib/microtest.php`, y al final imprime `N passed, M failed` (función `microtest_summary()`, exit code 1 si `M>0`). Aserciones disponibles: `test()`, `assert_true()`, `assert_same()`, `assert_null()`, `assert_throws()`. El baseline se mide por la línea final `N passed, M failed` con `M=0`.
- **API del `Container` (CONFIRMADO en `app/Kernel/Container/Container.php`):** métodos `bind(string $id, \Closure $factory): void`, `singleton(string $id, \Closure $factory): void`, `get(string $id): mixed`, `has(string $id): bool`. No existe `make`. Las factories reciben `Container $c` como primer argumento.
- **Forma de `config/container.php` (CONFIRMADO):** retorna `return static function (Container $container): void { ... }`. `Bootstrap` lo consume así: `$cfg = require ROOT_PATH.'/config/container.php'; $cfg($container);`. El split (Task 6) debe preservar esta forma de retorno (un callable que recibe `Container`).
- Subtrees de **dominio** que NO se mueven a `src/` (se quedan `App\`): ver la sección **"Inventario del footprint de dominio"** más abajo — es la lista EXACTA investigada, no "confirmar después".
- Carpetas a archivar/descartar (no entran a ningún artefacto): `auditoria/`, `nuevo_modulo/`, `plan/`, `vertical-kit/`, `database/migrations_legacy/`, `database/seeds_legacy/`.

---

## Inventario del footprint de dominio (investigado — fuente de verdad)

> Esta sección es el resultado de inspeccionar el repo el 2026-06-27. Reemplaza los "confirmar en Task 3" del plan original. El **dominio** = módulo Marketing + el sitio público (landing/portal/captación de leads). Todo lo demás es framework (incluido el módulo **integrations**, que es genérico `int_*` y se queda en el paquete).

### A. Dominio puro (se queda en `app/`, namespace `App\`) — mover en Task 4, preservar en Task 5

Capas Onion completas de Marketing:
- `app/Domain/Marketing/` (incluye `Contracts/`, `ValueObjects/`)
- `app/Application/Marketing/` (`RenderLandingUseCase`, `CapturarLeadUseCase`)
- `app/Infrastructure/Marketing/` (incluye `LeadCapture/`, `Settings/`)

Presentación del sitio público (domain-purpose, hoy físicamente entre la Presentation del framework):
- `app/Presentation/Controllers/Publico/LandingController.php`
- `app/Presentation/Controllers/Publico/LeadController.php`
- `app/Presentation/Controllers/Publico/PortalClienteController.php`
- `app/Presentation/Views/publico/` (árbol completo: `landing.php`, `layout.php`, `portal.php`, `wa_activar.php`, `partials/_footer.php`, `_hero.php`, `_lead_form.php`, `_pricing.php`, `_testimonios.php`, `_trust.php`)

**Repos de dominio escondidos en la carpeta compartida del framework** (¡trampa! el plan original los habría mandado a `src/`):
- `app/Infrastructure/Repositories/PdoLeadRepository.php` → mover a `app/Infrastructure/Marketing/`
- `app/Infrastructure/Repositories/PdoMarketingContentRepository.php` → mover a `app/Infrastructure/Marketing/`
- (Tras moverlos, actualizar sus `namespace` a `App\Infrastructure\Marketing` y las 2 referencias en `config/container.php` líneas ~592 y ~613.)

Wiring de dominio (se queda en el `config/container.php` del proyecto, NO en el provider del framework):
- Bloque `if (vertical.modules.marketing)` de `config/container.php` (≈ líneas 590–end): bindings de `MarketingContentRepositoryInterface`, `LandingContentProviderInterface`, `CommercialPackageSourceInterface`, `RenderLandingUseCase`, `LeadRepositoryInterface`, `CapturarLeadUseCase`, y los controllers `LandingController`/`LeadController`/`PortalClienteController`.
- Los 4 `Marketing*SettingsProvider` instanciados dentro de `SettingsSectionRegistry` (≈ líneas 535–541).

Rutas de dominio:
- `routes/marketing.php` (usa `App\Presentation\Controllers\Publico\*`; incluido condicionalmente desde `routes/web.php` con el toggle `marketing`).

Schema/datos de dominio:
- `database/schema/modules/marketing.sql` y `database/schema/modules/marketing_demo.sql`.

Manifiesto de dominio:
- `config/modules/marketing.php`.

### B. Framework (se mueve a `src/`, namespace `Lebytek\Framework\`)

Todo el resto de `app/`: `Kernel/`, `Domain/` (excepto `Marketing`), `Application/` (excepto `Marketing`), `Infrastructure/` (excepto `Marketing` y los 2 repos del punto A), `Presentation/` (excepto `Controllers/Publico` y `Views/publico`). Incluye el módulo **integrations** completo (`app/Domain/Integrations`, `app/Application/Integrations`, `app/Infrastructure/Integrations` —incluye el trabajo GreenApi en curso—, su `routes/integrations.php`, manifiesto `config/modules/integrations.php`, schema `integrations.sql`, y su `IntegrationsWhatsappSettingsProvider`).

### C. Ambigüedad resuelta: `wa_activar.php`

`app/Presentation/Views/publico/wa_activar.php` es la página pública de activación de WhatsApp (módulo integrations = framework) **pero** vive en el árbol `Views/publico/` y comparte `publico/layout.php` con el landing de Marketing. **Decisión:** todo el árbol `Views/publico/` + `Controllers/Publico/` se trata como "presentación del sitio público" a nivel **proyecto** (`app/`), incluido `wa_activar.php`. Razón: mantiene cohesivo el layout público; integrations sigue siendo framework en su backend (channels/repos/dispatcher), y su vista pública la sirve una ruta del proyecto. Esto es coherente con que integrations está "pendiente de promover a provider" (Task 10), no plenamente encapsulado aún.

### D. Constantes de ruta y resolución de vistas (riesgo crítico — ver Task 7)

`ViewHelper` (`app/Kernel/Helpers/ViewHelper.php`) y `app/Presentation/bootstrap_error_renderers.php` resuelven vistas con `APP_PATH . '/Presentation/Views/...'`. Tras el carve, las vistas **framework** (admin/auth/errors/layouts/partials) viven en `src/Presentation/Views`, pero `APP_PATH` apunta a `app/` del proyecto (solo vistas de dominio). **Sin un resolver en cascada, TODA vista admin del framework da "Vista no encontrada".** Task 7 añade la cascada proyecto→paquete. Archivos que usan `APP_PATH` para vistas: `ViewHelper.php` (3 usos), `bootstrap_error_renderers.php` (3), `Views/admin/dashboard/index.php` (1).

### E. Referencias al autoloader manual (se rompen al borrarlo — ver Task 2)

`require_once APP_PATH . '/Kernel/Autoloader.php'` aparece en 10 archivos además de `Bootstrap.php`: `tests/lib/bootstrap.php`, `public/install/index.php`, y `scripts/{add_color_configs,crear_usuario,install,migrate,rbac_integrity_report,seed,status}.php`. Task 2 debe arreglarlos todos (el GATE del arnés solo detecta el de `tests/lib/bootstrap.php`; los demás fallan en silencio).

### F. `Bootstrap.php` es un script procedural, no una clase

`app/Kernel/Bootstrap.php` NO declara clase: es un script que `public/index.php` hace `require`. Por eso `\Lebytek\Framework\Kernel\Bootstrap::run()` (Task 7 original) no existe todavía. Task 7 lo refactoriza a clase `Bootstrap` con `public static function run(): void` (autoloadable por Composer, para que el esqueleto lo invoque sin conocer la ruta `vendor/`), preservando la lógica idéntica.

---

## Task 1: Red de seguridad — commit pendiente, respaldo y baseline de tests

**Files:**
- Modify: none (operaciones de git + verificación)
- Create: none

**Interfaces:**
- Consumes: estado actual del working tree (hay cambios sin commitear de GreenApi).
- Produces: tag `pre-split-backup` apuntando al estado previo; número de tests que pasan en baseline (referencia para los gates siguientes); comando de test confirmado.

- [ ] **Step 1: Confirmar el comando del arnés de tests (ya investigado: `php tests/run.php`)**

Run:
```bash
php tests/run.php 2>&1 | tail -3
```
Expected: la última línea es `N passed, M failed`. El comando **GATE** de todas las tareas siguientes es `php tests/run.php`. (Detalle del arnés en Global Constraints; no es PHPUnit.)

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
- Modify: `tests/lib/bootstrap.php` (quitar el `require` de `Autoloader.php`)
- Modify: `public/install/index.php` y `scripts/{add_color_configs,crear_usuario,install,migrate,rbac_integrity_report,seed,status}.php` (mismo cambio)
- Delete: `app/Kernel/Autoloader.php`

**Interfaces:**
- Consumes: namespace `App\` → `app/` (estado actual).
- Produces: autoload PSR-4 de Composer activo para `App\` → `app/`. A partir de aquí el arranque depende de `vendor/autoload.php`.

> **Hallazgo (inventario §E):** `require_once APP_PATH . '/Kernel/Autoloader.php'` vive en 11 archivos (Bootstrap + 10 más). Borrar el autoloader sin arreglarlos rompe el arnés (vía `tests/lib/bootstrap.php`) y los scripts CLI (en silencio, sin GATE que los detecte). Por eso Step 3 los reescribe TODOS.

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

- [ ] **Step 3: Reemplazar el require del autoloader manual en TODOS los archivos**

En `app/Kernel/Bootstrap.php` (línea 30) la secuencia actual es:
```php
require_once APP_PATH . '/Kernel/Autoloader.php';

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_readable($composerAutoload)) {
    require_once $composerAutoload;
}
```
Reemplazarla por (Composer obligatorio):
```php
require_once ROOT_PATH . '/vendor/autoload.php';
```

En `tests/lib/bootstrap.php` (línea 19) hay el mismo patrón seguido de la carga condicional de Composer (líneas 21-23). Borrar la línea `require_once APP_PATH . '/Kernel/Autoloader.php';` y volver el require de Composer incondicional:
```php
require_once ROOT_PATH . '/vendor/autoload.php';
```

Para los 8 archivos CLI/install (`public/install/index.php:13`, `scripts/add_color_configs.php:6`, `scripts/crear_usuario.php:16`, `scripts/install.php:20`, `scripts/migrate.php:16`, `scripts/rbac_integrity_report.php:16`, `scripts/seed.php:18`, `scripts/status.php:18`): cada uno define `ROOT_PATH`/`APP_PATH` arriba; reemplazar su `require_once APP_PATH . '/Kernel/Autoloader.php';` por `require_once ROOT_PATH . '/vendor/autoload.php';`. Verificar que ninguno quede sin tocar:
```bash
grep -rln "Kernel/Autoloader" . --include='*.php' | grep -v vendor || echo "OK: sin referencias a Autoloader manual"
```
Expected: "OK: sin referencias a Autoloader manual".

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
git add -A
git commit -m "refactor(kernel): usar autoload de Composer y eliminar autoloader manual"
```
> `git add -A` recoge `composer.json`, `Bootstrap.php`, `tests/lib/bootstrap.php`, `public/install/index.php`, los 7 scripts, la eliminación de `Autoloader.php` y los archivos regenerados de `vendor/composer/` (si `vendor/` no está en `.gitignore`; verificar).

---

## Task 3: Verificar el footprint de dominio y el aislamiento Onion

> El inventario EXACTO ya está en la sección "Inventario del footprint de dominio" (investigado). Esta tarea solo **verifica** que el repo sigue coincidiendo con ese inventario (por si el commit de GreenApi de Task 1 movió algo) y graba el archivo de trabajo que consumirán Tasks 4 y 5.

**Files:**
- Create: `docs/superpowers/_carve-marketing-namespaces.txt` (lista de prefijos a preservar; entrada del script de Task 5)

**Interfaces:**
- Consumes: la sección de inventario + el árbol `app/` actual.
- Produces: el archivo `_carve-marketing-namespaces.txt` con los prefijos exactos a preservar.

- [ ] **Step 1: Grabar el archivo de prefijos a preservar (literal del inventario)**

Crear `docs/superpowers/_carve-marketing-namespaces.txt` con exactamente:
```
App\Domain\Marketing
App\Application\Marketing
App\Infrastructure\Marketing
App\Presentation\Controllers\Publico
```
> Las **vistas** `Views/publico/` no llevan namespace PHP (son plantillas), así que no entran en la lista de rename; se mueven físicamente en Task 4 pero no se tocan en el rename de namespaces. Los 2 repos de dominio (`PdoLeadRepository`, `PdoMarketingContentRepository`) cambian de namespace a `App\Infrastructure\Marketing` al moverse (Task 4 Step 2b), así que su prefijo destino ya está cubierto por la línea `App\Infrastructure\Marketing`.

- [ ] **Step 2: Verificar que el árbol coincide con el inventario**

Run:
```bash
for p in app/Domain/Marketing app/Application/Marketing app/Infrastructure/Marketing \
         app/Presentation/Controllers/Publico app/Presentation/Views/publico \
         app/Infrastructure/Repositories/PdoLeadRepository.php \
         app/Infrastructure/Repositories/PdoMarketingContentRepository.php; do
  [ -e "$p" ] && echo "OK   $p" || echo "FALTA $p"
done
```
Expected: 7 líneas `OK`. Si alguna dice `FALTA`, reconciliar con la sección de inventario antes de seguir (el árbol cambió desde la investigación).

- [ ] **Step 3: Confirmar que el framework NO depende del dominio (regla Onion; criterio #2)**

Run:
```bash
grep -rn 'App\\\(Domain\|Application\|Infrastructure\)\\Marketing\|Controllers\\Publico' \
  app/Kernel app/Domain/Interfaces app/Application/Crud app/Application/Services \
  app/Domain/Integrations app/Application/Integrations app/Infrastructure/Integrations 2>/dev/null \
  | grep -v '/Marketing/' || echo "OK: el framework no referencia dominio"
```
Expected: "OK: el framework no referencia dominio". Si aparece una referencia, es un acoplamiento que rompe el criterio #2: hay que resolverlo antes de continuar (mover ese código a dominio o invertir la dependencia con una interfaz framework).

- [ ] **Step 4: Commit (archivo de trabajo)**

```bash
git add docs/superpowers/_carve-marketing-namespaces.txt
git commit -m "chore(carve): inventario verificado de footprint de dominio a preservar"
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

- [ ] **Step 2: Devolver las capas Onion de Marketing a app/ (dominio)**

Run:
```bash
mkdir -p app/Domain app/Application app/Infrastructure app/Presentation/Controllers
git mv src/Domain/Marketing app/Domain/Marketing
git mv src/Application/Marketing app/Application/Marketing
git mv src/Infrastructure/Marketing app/Infrastructure/Marketing
git mv src/Presentation/Controllers/Publico app/Presentation/Controllers/Publico
git mv src/Presentation/Views/publico app/Presentation/Views/publico
```
Expected: `app/` contiene esas 5 rutas; `src/` ya no las tiene.

- [ ] **Step 2b: Mover los 2 repos de dominio escondidos en la carpeta compartida**

Estos repos son dominio pero viven en `Infrastructure/Repositories/` (carpeta framework). Moverlos a Marketing:
```bash
git mv src/Infrastructure/Repositories/PdoLeadRepository.php app/Infrastructure/Marketing/PdoLeadRepository.php
git mv src/Infrastructure/Repositories/PdoMarketingContentRepository.php app/Infrastructure/Marketing/PdoMarketingContentRepository.php
```
Editar el `namespace` de cada archivo movido:
- `namespace App\Infrastructure\Repositories;` → `namespace App\Infrastructure\Marketing;`

Actualizar las 2 referencias en `config/container.php` (≈ líneas 592 y 613):
- `\App\Infrastructure\Repositories\PdoMarketingContentRepository` → `\App\Infrastructure\Marketing\PdoMarketingContentRepository`
- `\App\Infrastructure\Repositories\PdoLeadRepository` → `\App\Infrastructure\Marketing\PdoLeadRepository`

> Estos repos extienden/usan clases base del framework (p. ej. `BaseRepository`, `Connection`). Tras Task 5 esas bases serán `Lebytek\Framework\...`; el `use` se reescribirá automáticamente porque `config/container.php` y `app/` están en `$targets`. El repo de dominio puede depender de bases del framework (dependencia hacia adentro, válida en Onion).

- [ ] **Step 3: Verificar que no quedó dominio en src/**

Run:
```bash
find src -type d \( -name Marketing -o -name Publico -o -name publico \); \
ls src/Infrastructure/Repositories/Pdo{Lead,MarketingContent}Repository.php 2>/dev/null
```
Expected: sin salida (ni carpetas de dominio, ni los 2 repos en la carpeta compartida).

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
    'App\\Infrastructure\\Marketing',          // incluye los 2 repos movidos en Task 4 Step 2b
    'App\\Presentation\\Controllers\\Publico',  // LandingController, LeadController, PortalClienteController
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

- [ ] **Step 4: Verificar que el dominio sigue bajo App\ en app/**

Run:
```bash
grep -rn 'namespace App\\\(Domain\|Application\|Infrastructure\)\\Marketing\|namespace App\\Presentation\\Controllers\\Publico' app | head -20
```
Expected: las declaraciones `namespace App\Domain\Marketing;`, `App\Infrastructure\Marketing;` (incluidos los 2 repos movidos), `App\Presentation\Controllers\Publico;` etc. intactas. Confirmar también que `routes/marketing.php` sigue con `use App\Presentation\Controllers\Publico\...` (no renombrado):
```bash
grep -n 'use App\\Presentation\\Controllers\\Publico' routes/marketing.php
```
Expected: las 3 líneas `use ...Publico\LandingController/LeadController/PortalClienteController` intactas.

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
- Produces: `Lebytek\Framework\Kernel\Container\FrameworkServiceProvider::register(Container $c): void` que registra TODOS los bindings genéricos. `config/container.php` del proyecto invoca `FrameworkServiceProvider::register($container)` y luego ejecuta la **sección de módulos** (gated por toggles: SettingsSectionRegistry + integrations + marketing).

> **Boundary EXACTO del split (investigado).** El `config/container.php` actual es `return static function (Container $container): void { ... }` con dos zonas:
> - **Zona framework (always-on)** = cuerpo del closure desde el primer binding (≈ línea 84) **hasta el bind de `SistemaEstadoController` inclusive** (≈ línea 529). Todo esto va a `FrameworkServiceProvider::register()`. Son los bindings de repos auth/RBAC, servicios CRUD, dashboard, motor de instalación, controllers admin, etc.
> - **Zona de módulos (gated)** = desde el comentario `// Registry de secciones de Ajustes` (≈ línea 531) **hasta el final**: el singleton `SettingsSectionRegistry` (que instancia providers de Marketing e Integrations inline), el bloque `if (vertical.modules.integrations)` (≈ 550–588) y el bloque `if (vertical.modules.marketing)` (≈ 590–end). **Toda esta zona se QUEDA en `config/container.php` del proyecto.**
>
> Razón de dejar `SettingsSectionRegistry` e integrations en el proyecto (no en el provider): `SettingsSectionRegistry` es un servicio framework pero **construye providers de dominio** (`App\Infrastructure\Marketing\Settings\*`) — meterlo al paquete violaría "el paquete jamás referencia `App\`" (criterio #2). Integrations es módulo framework pero su wiring está "pendiente de promover a provider por módulo" (Task 10); por ahora se cablea desde el proyecto, igual que Marketing. Esto mantiene `FrameworkServiceProvider` 100% libre de `App\` y de toggles de módulo.

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
> FQCN real (CONFIRMADO): `Lebytek\Framework\Domain\Interfaces\UsuarioRepositoryInterface` (era `App\Domain\Interfaces\UsuarioRepositoryInterface`, renombrado en Task 5). El `Container` expone `has(string): bool` (confirmado), así que `assert_true($container->has(...))` es correcto — no existe `make`.

- [ ] **Step 2: Ejecutar el test — debe fallar**

Run: `php tests/run.php` (o el test puntual si el arnés lo permite)
Expected: FAIL — `Class FrameworkServiceProvider not found`.

- [ ] **Step 3: Crear el FrameworkServiceProvider moviendo los bindings genéricos**

Crear `src/Kernel/Container/FrameworkServiceProvider.php` con namespace `Lebytek\Framework\Kernel\Container`. Mover dentro de un método estático `register(Container $container): void` el cuerpo de la **zona framework** (≈ líneas 84–529 del closure; ver boundary arriba) junto con sus `use` (las decenas de `use Lebytek\Framework\...` del cabezal de `config/container.php`, ahora ya renombrados). NO mover la zona de módulos (531–end). Estructura:
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

Reescribir `config/container.php`: delega la zona framework en el provider y **conserva la zona de módulos (531–end) tal cual**. Preservar la forma `return static function (Container $container): void { ... }` (confirmado: `Bootstrap` hace `$cfg($container)`):
```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Container\Container;
use Lebytek\Framework\Kernel\Container\FrameworkServiceProvider;
use Lebytek\Framework\Kernel\Config\Config;
// (mantener los use que la zona de módulos todavía necesite, p. ej. Config para los toggles)

return static function (Container $container): void {
    // ── Framework (paquete): todos los bindings genéricos always-on ──
    FrameworkServiceProvider::register($container);

    // ── Sección de módulos (gated por toggles) — PEGAR AQUÍ, sin cambios, ──
    //    las líneas 531–end del container.php original:
    //    - singleton SettingsSectionRegistry (providers Marketing + Integrations inline)
    //    - if (Config::get('vertical.modules.integrations')) { ... }
    //    - if (Config::get('vertical.modules.marketing'))    { ... }
};
```
> La zona de módulos referencia clases framework por FQCN (`\Lebytek\Framework\Application\Services\SettingsSectionRegistry`, `\Lebytek\Framework\Application\Integrations\...`) y clases de dominio (`\App\Infrastructure\Marketing\...`). Ambas resuelven por Composer (dos roots PSR-4). Es correcto que el proyecto referencie ambas; lo que importa para el criterio #2 es que el **paquete** (`src/`) no referencie `App\`.

- [ ] **Step 5: GATE — arnés verde**

Run: `php tests/run.php`
Expected: PASS con baseline. El test de Step 1 ahora pasa.

- [ ] **Step 6: Commit**

```bash
git add src/Kernel/Container/FrameworkServiceProvider.php config/container.php tests/Kernel/FrameworkServiceProviderTest.php
git commit -m "refactor(container): extraer bindings genericos a FrameworkServiceProvider"
```

---

## Task 7: Bootstrap como clase + entry point por Composer + resolución de vistas en cascada

> Tres cambios entrelazados que comparten un GATE de arranque HTTP. **El central es la cascada de vistas** (inventario §D): sin ella, todas las vistas admin del framework dan "Vista no encontrada" porque viven en `src/Presentation/Views` pero `APP_PATH` apunta al `app/` del proyecto.

**Files:**
- Modify: `src/Kernel/Bootstrap.php` (envolver el script procedural en clase `Bootstrap` con `run()`)
- Modify: `public/index.php` (definir paths, require autoload, `Bootstrap::run()`)
- Modify: `src/Kernel/Helpers/ViewHelper.php` (resolver con cascada proyecto→paquete)
- Modify: `src/Presentation/bootstrap_error_renderers.php` y `src/Presentation/Views/admin/dashboard/index.php` (usar la cascada, no `APP_PATH` directo)
- Test: `tests/Kernel/ViewHelperResolveTest.php`

**Interfaces:**
- Consumes: `Lebytek\Framework\Kernel\Bootstrap`, `Lebytek\Framework\Kernel\Helpers\ViewHelper`.
- Produces:
  - `Lebytek\Framework\Kernel\Bootstrap::run(): void` — arranque HTTP (antes script procedural).
  - `ViewHelper::resolve(string $viewRelPath): string` — devuelve la ruta absoluta del primer `.php` existente buscando primero en las vistas del **proyecto** (`APP_PATH/Presentation/Views`) y luego en las del **paquete** (`__DIR__/../../Presentation/Views`). Lanza `RuntimeException` si no existe en ninguna. `render()`/`partial()` lo usan internamente.

- [ ] **Step 1: Test de la cascada de vistas (TDD) — escribir el test**

Crear `tests/Kernel/ViewHelperResolveTest.php`:
```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

// Una vista que SOLO existe en el paquete (admin) debe resolverse via fallback.
test('ViewHelper resuelve una vista del paquete (admin/dashboard/index)', function (): void {
    $path = ViewHelper::resolve('admin/dashboard/index');
    assert_true(is_file($path), "resolve debe devolver un archivo existente, got: {$path}");
});

// Una vista inexistente lanza RuntimeException.
test('ViewHelper::resolve lanza si la vista no existe', function (): void {
    assert_throws(\RuntimeException::class, function (): void {
        ViewHelper::resolve('no/existe/jamas');
    });
});
```
> El arnés ya define `APP_PATH` en `tests/lib/bootstrap.php`. La vista `admin/dashboard/index.php` es framework (vive en `src/Presentation/Views`), así que prueba el fallback al paquete.

- [ ] **Step 2: Ejecutar el test — debe fallar**

Run: `php tests/run.php Kernel/ViewHelperResolve`
Expected: FAIL — `Call to undefined method ...ViewHelper::resolve()`.

- [ ] **Step 3: Implementar `resolve()` y reconectar `render()`/`partial()`**

En `src/Kernel/Helpers/ViewHelper.php`, añadir el resolver en cascada y usarlo en los 3 puntos que hoy concatenan `APP_PATH . '/Presentation/Views/...'` (líneas 33, 47, 73):
```php
/** Ruta base de las vistas del paquete (este archivo está en src/Kernel/Helpers). */
private static function packageViewsPath(): string
{
    return dirname(__DIR__, 2) . '/Presentation/Views'; // src/Presentation/Views
}

/** Ruta base de las vistas del proyecto (dominio/overrides). */
private static function projectViewsPath(): string
{
    return (defined('APP_PATH') ? APP_PATH : dirname(__DIR__, 3) . '/app') . '/Presentation/Views';
}

/** Resuelve una vista relativa (sin .php): proyecto primero, luego paquete. */
public static function resolve(string $viewRelPath): string
{
    foreach ([self::projectViewsPath(), self::packageViewsPath()] as $base) {
        $candidate = $base . '/' . $viewRelPath . '.php';
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    throw new \RuntimeException("Vista no encontrada: {$viewRelPath}");
}
```
Reescribir los cuerpos existentes:
- `render()`: `$viewFile = self::resolve($view);` y `$layoutFile = self::resolve($layout);` (en vez de `APP_PATH . '/Presentation/Views/' . ...`). Conservar los mensajes/flujo; `resolve()` ya lanza si falta.
- `partial()`: `return self::renderFile(self::resolve('partials/' . $name), $data);`

> Cascada proyecto→paquete = el proyecto puede **override** cualquier vista del framework dejando un archivo del mismo nombre en `app/Presentation/Views/...`. Las vistas de dominio (`publico/*`) resuelven desde el proyecto; las admin/auth/errors/layouts/partials desde el paquete.

- [ ] **Step 4: Reconectar los otros 2 consumidores de `APP_PATH` para vistas**

En `src/Presentation/bootstrap_error_renderers.php` (líneas 16/22/28) reemplazar cada `require APP_PATH . '/Presentation/Views/errors/{404,403,500}.php';` por:
```php
require \Lebytek\Framework\Kernel\Helpers\ViewHelper::resolve('errors/404'); // (403, 500 análogos)
```
En `src/Presentation/Views/admin/dashboard/index.php` (línea 25) reemplazar el chequeo `is_file(APP_PATH . '/Presentation/Views/partials/' . $partial . '.php')` por una comprobación vía resolver tolerante (envolver en try/catch o añadir `ViewHelper::exists()` si se prefiere; lo mínimo: `try { ViewHelper::resolve('partials/' . $partial); $ok = true; } catch (\RuntimeException) { $ok = false; }`).

- [ ] **Step 5: Envolver `Bootstrap.php` en clase `Bootstrap::run()`**

`src/Kernel/Bootstrap.php` HOY es un script procedural (no clase). Refactor mínimo: mover el cuerpo (todo lo de líneas ≈18–113 del original: uses, env, config, error handlers, sesión, DB, container, router, dispatch) dentro de una clase, preservando la lógica:
```php
<?php
declare(strict_types=1);

namespace Lebytek\Framework\Kernel;

use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Config\Config;
// ... (resto de uses, ya renombrados por Task 5) ...

final class Bootstrap
{
    public static function run(): void
    {
        // <<< pegar aquí el cuerpo procedural original, SIN el require del autoloader
        //     (ya lo hace public/index.php) y SIN cambiar la lógica de env/config/router >>>
        // Notas de paths:
        //   - require de routes/config/container: siguen con ROOT_PATH (son del PROYECTO).
        //   - require de bootstrap_error_renderers: cambia a self-locate del paquete:
        //       require __DIR__ . '/../Presentation/bootstrap_error_renderers.php';
    }
}
```
Cambios puntuales dentro del cuerpo:
- Quitar `require_once APP_PATH . '/Kernel/Autoloader.php';` y el bloque condicional de Composer (ya cargado por `index.php`).
- `require_once APP_PATH . '/Presentation/bootstrap_error_renderers.php';` → `require_once __DIR__ . '/../Presentation/bootstrap_error_renderers.php';` (archivo del paquete).
- `$container = new \App\Kernel\Container\Container();` → `new \Lebytek\Framework\Kernel\Container\Container();` (ya lo hizo Task 5, verificar).
- `require ROOT_PATH . '/config/container.php'`, `routes/web.php`, `routes/api.php`: **se quedan** con `ROOT_PATH` (son del proyecto).

- [ ] **Step 6: Ajustar `public/index.php`**

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

- [ ] **Step 7: GATE — arranque HTTP (admin + público) + arnés**

Run:
```bash
php -S localhost:8000 -t public &
SRV=$!; sleep 2
echo -n "raíz:  "; curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/
echo -n "login: "; curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/login
echo -n "admin: "; curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/admin
kill $SRV
php tests/run.php
```
Expected: códigos HTTP válidos (200/302; nada de 500 por "Vista no encontrada") y arnés PASS con baseline (incluye los 2 tests nuevos de `ViewHelperResolve`). Un 500 en `/login` o `/admin` casi siempre = una vista que `resolve()` no encontró → revisar que esa vista esté en `src/Presentation/Views` y que `resolve()` busque ahí.

- [ ] **Step 8: Commit**

```bash
git add src/Kernel/Bootstrap.php public/index.php src/Kernel/Helpers/ViewHelper.php \
        src/Presentation/bootstrap_error_renderers.php src/Presentation/Views/admin/dashboard/index.php \
        tests/Kernel/ViewHelperResolveTest.php
git commit -m "refactor(bootstrap): Bootstrap como clase, arranque por Composer y resolucion de vistas en cascada"
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
- **El gate es el arnés:** este es un refactor que **preserva comportamiento**; el arnés de tests es la verificación principal. Tests nuevos (los únicos): `FrameworkServiceProviderTest` (Task 6) y `ViewHelperResolveTest` (Task 7) — ambos cubren mecanismos del propio carve (registro del provider y cascada de vistas), no features de dominio.
- **GATE de arranque HTTP:** además del arnés, Task 7 y Task 8 ejercen `php -S` + `curl` porque la cascada de vistas y el contrato paquete↔esqueleto NO los cubre el arnés (que no renderiza HTTP admin completo). Un 500 por "Vista no encontrada" solo aparece en el arranque real.
- **Trampas confirmadas (ver Inventario):** (§D) vistas del framework dejan de resolverse si no se hace la cascada de Task 7; (§E) borrar el autoloader rompe 10 archivos extra (Task 2); (§F) `Bootstrap` es procedural y debe volverse clase (Task 7); 2 repos de dominio escondidos en `Infrastructure/Repositories/` (Task 4 Step 2b); la zona de módulos de `container.php` (incl. `SettingsSectionRegistry` e integrations) se queda en el proyecto, no en el provider (Task 6).
- **Tasks 1–11** ocurren en ESTE repo. **Task 12** es operativa (Repo 2 + VPS) y se hace con el usuario.
- **Fuera de alcance:** los 4 servicios de dominio (A–D) y la promoción módulo-por-módulo (solo se LISTA en Task 10).
