# Hardening de Seguridad — Fase 0 (Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar los huecos de seguridad explotables de la plataforma (secretos en git, open redirect, IDOR en acciones CRUD, uploads sin validar, debug en producción) **sin alterar la lógica de ningún flujo existente**: cada variante válida debe producir el mismo resultado antes y después del cambio.

**Architecture:** Cada endurecimiento se aísla detrás de una unidad pequeña, pura y testeable (`SafeRedirect`, `UploadValidator`, `DebugMode`, ownership en `CrudActionService`). El comportamiento legítimo se congela con **tests de caracterización** (escritos primero, deben quedar verdes y no cambiar) y la vulnerabilidad se cierra con un **test de seguridad** (escrito para fallar, luego verde). Ningún cambio toca la firma pública de los flujos: se añaden parámetros opcionales al final y validaciones que solo rechazan entradas hostiles.

**Tech Stack:** PHP 8.1, arquitectura Onion (Presentation / Application / Domain / Infrastructure / Kernel), microtest harness propio (`tests/run.php`), contenedor DI manual (`config/container.php`), CRUD Engine dirigido por JSON.

---

## Principio rector — "mismo resultado en todas las variantes"

El usuario es explícito: **estricto en seguridad, pero el flujo NO cambia su lógica**. Por eso este plan invierte ligeramente el TDD clásico y usa **doble red**:

1. **Test de caracterización (guard rail) — se escribe ANTES y debe pasar contra el código actual.** Congela el resultado del camino feliz / variantes legítimas.
2. **Test de seguridad — se escribe para fallar contra el código actual.** Demuestra el hueco.
3. **Implementación mínima.** Cierra el hueco.
4. **Re-correr ambos:** caracterización sigue verde (idéntica) + seguridad ahora verde.
5. **Suite completa verde** (`php tests/run.php`).

Si un test de caracterización cambia de verde a rojo, **la lógica cambió y el cambio se revierte**. Esa es la condición de aceptación no negociable de toda la Fase 0.

**Reglas de compatibilidad aplicadas en todo el plan:**
- Parámetros nuevos en constructores: **siempre al final, opcionales (`= null`)**, para no romper instanciación posicional existente.
- Recursos CRUD sin `scope: owner`: comportamiento **byte-idéntico** (la metadata de ownership devuelve `null` → early return).
- Admins con permiso de bypass: comportamiento **idéntico**.
- Mensajes de error existentes: se **conservan textualmente** cuando ya existían.

---

## File Structure

| Archivo | Responsabilidad | Acción |
|---------|-----------------|--------|
| `.gitignore` | Excluir `.env` del control de versiones | Modificar |
| `docs/core/seguridad_secretos_deploy.md` | Política de secretos + checklist de despliegue VPS | Crear |
| `app/Kernel/Http/SafeRedirect.php` | Normaliza un destino de redirección a ruta interna segura | Crear |
| `app/Presentation/Middlewares/CsrfMiddleware.php` | Usar `SafeRedirect` en el redirect de fallo CSRF | Modificar (línea 39) |
| `app/Kernel/BaseClasses/BaseController.php` | Usar `SafeRedirect` en `back()` | Modificar (líneas 31-35) |
| `app/Application/Services/CrudActionService.php` | Enforce ownership en `run()` y `runBulk()` | Modificar |
| `app/Application/Services/UploadValidator.php` | Validación pura de uploads (error, tamaño, extensión, MIME) | Crear |
| `app/Application/Services/CrudDataService.php` | Delegar validación de upload a `UploadValidator` | Modificar (`handleUpload`, ~621-667) |
| `app/Kernel/Config/DebugMode.php` | Resolver flag de debug forzando `false` en producción | Crear |
| `app/Kernel/Bootstrap.php` | Usar `DebugMode::resolve()` | Modificar (línea 49) |
| `config/security.php` | Exponer `max_upload_mb` | Modificar |
| `config/container.php` | Inyectar `CrudScopeResolver` en `CrudActionService`; `UploadValidator` en `CrudDataService` | Modificar |
| `tests/Security/SafeRedirectTest.php` | Tests de `SafeRedirect` (caracterización + seguridad) | Crear |
| `tests/Security/CrudActionOwnershipTest.php` | Tests de ownership en acciones (caracterización + seguridad) | Crear |
| `tests/Security/UploadValidatorTest.php` | Tests de validación de uploads | Crear |
| `tests/Security/DebugModeTest.php` | Tests del guard de debug | Crear |

---

## Task 0: Línea base verde (red de seguridad antes de tocar nada)

**Files:**
- (ninguno) — solo verificación

- [ ] **Step 1: Correr la suite completa y registrar el conteo**

Run: `php tests/run.php`
Expected: termina con `N passed, 0 failed` (anota N; toda la Fase 0 debe terminar con ≥ N passed, 0 failed).

- [ ] **Step 2: Confirmar árbol de trabajo limpio para los archivos que tocaremos**

Run: `git status --short .gitignore app/Kernel/Http app/Application/Services app/Kernel/Bootstrap.php config/security.php config/container.php`
Expected: vacío (sin cambios sin commitear en esos paths). Si hay cambios previos no relacionados, detenerse y consultar.

---

## Task 1: C1 — Sacar `.env` del control de versiones y documentar rotación

**Contexto:** `git ls-files` confirma que `.env` **está trackeado**. El VPS hace auto-pull de `main`, por lo que `DB_PASSWORD` / `APP_KEY` se propagan. `Bootstrap.php:37-40` **copia** `.env.example` a `.env` si falta — esa lógica de arranque NO debe romperse: el archivo sigue existiendo en disco; solo deja de versionarse.

**Files:**
- Modify: `.gitignore`
- Create: `docs/core/seguridad_secretos_deploy.md`

- [ ] **Step 1: Añadir `.env` a `.gitignore`**

Editar `.gitignore` para que quede:

```gitignore
/vendor/
.env
.envbk
public/error_log
public/install/error_log
storage/logs/*.log
```

- [ ] **Step 2: Quitar `.env` del índice de git SIN borrarlo del disco**

Run: `git rm --cached .env`
Expected: `rm '.env'` (el archivo sigue presente en disco).

- [ ] **Step 3: Verificar que el arranque sigue funcionando (la lógica de copia no se tocó)**

Run: `php -r "define('ROOT_PATH', getcwd()); var_export(file_exists(ROOT_PATH.'/.env'));"`
Expected: `true` — `.env` sigue en disco; `Bootstrap.php` no cambia su comportamiento.

- [ ] **Step 4: Confirmar que git ya no rastrea `.env` pero sí `.env.example`**

Run: `git ls-files .env .env.example`
Expected: solo imprime `.env.example`.

- [ ] **Step 5: Crear la política de secretos y checklist de despliegue**

Crear `docs/core/seguridad_secretos_deploy.md`:

```markdown
# Seguridad de secretos y checklist de despliegue (VPS)

**Regla:** En el repositorio solo vive `.env.example`. `.env` jamás se versiona.
El VPS hace auto-pull de `main`; cualquier secreto commiteado se considera comprometido.

## Rotación obligatoria si `.env` estuvo alguna vez en git

1. Rotar `DB_PASSWORD` en el motor de base de datos y en el `.env` del VPS.
2. Regenerar `APP_KEY` (32+ caracteres aleatorios).
3. Revisar `git log -- .env` para confirmar si hubo exposición histórica.
   - Si hubo push a remoto: rotar TODO secreto presente en esos commits.
4. (Opcional, si el remoto es privado y se requiere limpieza histórica) purgar
   `.env` del historial con `git filter-repo`. Coordinar antes: reescribe SHAs.

## Checklist antes de cada despliegue a producción

- [ ] `APP_ENV=production` en el `.env` del servidor.
- [ ] `APP_DEBUG=false` (además, el código fuerza `false` cuando `APP_ENV=production`).
- [ ] `SESSION_SECURE=true` (cookies solo por HTTPS).
- [ ] `APP_KEY` único por entorno (no el de `.env.example`).
- [ ] `DB_PASSWORD` rotado respecto a desarrollo.
- [ ] `MAX_UPLOAD_MB` acorde al límite real del servidor PHP (`upload_max_filesize`).
- [ ] `git ls-files .env` no devuelve nada.
```

- [ ] **Step 6: Commit**

```bash
git add .gitignore docs/core/seguridad_secretos_deploy.md
git rm --cached .env
git commit -m "security: untrack .env and document secret rotation + deploy checklist"
```

---

## Task 2: H1 — `SafeRedirect` contra open redirect

**Contexto:** Dos puntos redirigen al header `Referer` sin validar:
- `CsrfMiddleware.php:39` → `Response::redirect($request->header('Referer', '/'))`
- `BaseController::back()` `BaseController.php:33-34` → `Response::redirect($referer)`

Un `Referer: https://evil.com` produce open redirect (phishing post-login/post-CSRF). La corrección: **solo se aceptan rutas internas root-relative**; cualquier otra cosa cae al fallback. Para destinos legítimos same-origin el resultado es idéntico.

**Files:**
- Create: `app/Kernel/Http/SafeRedirect.php`
- Test: `tests/Security/SafeRedirectTest.php`
- Modify: `app/Presentation/Middlewares/CsrfMiddleware.php`
- Modify: `app/Kernel/BaseClasses/BaseController.php`

- [ ] **Step 1: Escribir los tests (caracterización + seguridad) — fallan porque la clase no existe**

Crear `tests/Security/SafeRedirectTest.php`:

```php
<?php

declare(strict_types=1);

use App\Kernel\Http\SafeRedirect;

// ── Caracterización: las variantes legítimas se conservan idénticas ──────────
test('SafeRedirect conserva una ruta interna simple', function (): void {
    assert_same('/admin/usuarios', SafeRedirect::toInternal('/admin/usuarios'));
});

test('SafeRedirect conserva ruta interna con query y fragmento', function (): void {
    assert_same('/admin/crud/clientes?page=2#tab', SafeRedirect::toInternal('/admin/crud/clientes?page=2#tab'));
});

test('SafeRedirect conserva la raíz', function (): void {
    assert_same('/', SafeRedirect::toInternal('/'));
});

// ── Seguridad: todo destino externo o malformado cae al fallback ─────────────
test('SafeRedirect rechaza URL absoluta con esquema', function (): void {
    assert_same('/', SafeRedirect::toInternal('https://evil.com/phish'));
});

test('SafeRedirect rechaza URL protocol-relative', function (): void {
    assert_same('/', SafeRedirect::toInternal('//evil.com'));
});

test('SafeRedirect neutraliza trucos con backslash', function (): void {
    assert_same('/', SafeRedirect::toInternal('/\\evil.com'));
    assert_same('/', SafeRedirect::toInternal('\\/evil.com'));
});

test('SafeRedirect rechaza inyección de cabecera (CRLF) y control chars', function (): void {
    assert_same('/', SafeRedirect::toInternal("/admin\r\nSet-Cookie: x=1"));
});

test('SafeRedirect usa el fallback dado ante entrada vacía o nula', function (): void {
    assert_same('/admin', SafeRedirect::toInternal(null, '/admin'));
    assert_same('/admin', SafeRedirect::toInternal('   ', '/admin'));
});

test('SafeRedirect rechaza rutas relativas sin slash inicial', function (): void {
    assert_same('/', SafeRedirect::toInternal('admin/usuarios'));
    assert_same('/', SafeRedirect::toInternal('javascript:alert(1)'));
});
```

- [ ] **Step 2: Correr y verificar que fallan**

Run: `php tests/run.php Security/SafeRedirect`
Expected: FAIL (`Class "App\Kernel\Http\SafeRedirect" not found` o similar).

- [ ] **Step 3: Implementar `SafeRedirect`**

Crear `app/Kernel/Http/SafeRedirect.php`:

```php
<?php

declare(strict_types=1);

namespace App\Kernel\Http;

/**
 * Normaliza un destino de redirección (típicamente el header `Referer`) a una
 * ruta interna segura. Solo acepta rutas root-relative same-origin; cualquier
 * URL absoluta, protocol-relative, con esquema o con caracteres de control cae
 * al fallback. No cambia el resultado de redirecciones internas legítimas.
 */
final class SafeRedirect
{
    public static function toInternal(?string $candidate, string $fallback = '/'): string
    {
        $candidate = is_string($candidate) ? trim($candidate) : '';
        if ($candidate === '') {
            return $fallback;
        }

        // Rechazar caracteres de control / CRLF (header injection).
        if (preg_match('/[\x00-\x1f\x7f]/', $candidate) === 1) {
            return $fallback;
        }

        // Los navegadores tratan '\' como '/'. Normalizar para detectar
        // '//host' y '/\host' disfrazados antes de decidir.
        $normalized = str_replace('\\', '/', $candidate);

        // Debe ser root-relative ('/algo') y NO protocol-relative ('//host').
        if ($normalized[0] !== '/') {
            return $fallback;
        }
        if (isset($normalized[1]) && $normalized[1] === '/') {
            return $fallback;
        }

        return $candidate;
    }
}
```

- [ ] **Step 4: Correr los tests y verificar verde**

Run: `php tests/run.php Security/SafeRedirect`
Expected: PASS (todos los tests, incluidos los de caracterización).

- [ ] **Step 5: Cablear en `CsrfMiddleware`**

En `app/Presentation/Middlewares/CsrfMiddleware.php`, añadir el import y cambiar el redirect.

Añadir junto a los `use` existentes:

```php
use App\Kernel\Http\SafeRedirect;
```

Reemplazar la línea 39:

```php
            return Response::redirect($request->header('Referer', '/'));
```

por:

```php
            return Response::redirect(SafeRedirect::toInternal($request->header('Referer', '/')));
```

- [ ] **Step 6: Cablear en `BaseController::back()`**

En `app/Kernel/BaseClasses/BaseController.php`, añadir el import:

```php
use App\Kernel\Http\SafeRedirect;
```

Reemplazar el cuerpo de `back()` (líneas 31-35):

```php
    protected function back(Request $request): Response
    {
        $referer = $request->header('Referer', '/');
        return Response::redirect($referer);
    }
```

por:

```php
    protected function back(Request $request): Response
    {
        $referer = SafeRedirect::toInternal($request->header('Referer', '/'));
        return Response::redirect($referer);
    }
```

- [ ] **Step 7: Verificar sintaxis de los archivos modificados**

Run: `php -l app/Presentation/Middlewares/CsrfMiddleware.php; php -l app/Kernel/BaseClasses/BaseController.php; php -l app/Kernel/Http/SafeRedirect.php`
Expected: `No syntax errors detected` en los tres.

- [ ] **Step 8: Suite completa (caracterización global intacta)**

Run: `php tests/run.php`
Expected: `≥N passed, 0 failed`.

- [ ] **Step 9: Commit**

```bash
git add app/Kernel/Http/SafeRedirect.php tests/Security/SafeRedirectTest.php app/Presentation/Middlewares/CsrfMiddleware.php app/Kernel/BaseClasses/BaseController.php
git commit -m "security: add SafeRedirect and use it for CSRF/back redirects (H1 open redirect)"
```

---

## Task 3: H2 — Ownership en acciones CRUD (`run` y `runBulk`)

**Contexto:** `CrudResourceService` ya aplica `assertOwnership()` en `show/edit/update/delete` (ver `CrudResourceService.php:26-40`). Pero `CrudActionService::run()` y `runBulk()` **no** verifican propiedad: un usuario con scope `owner` puede ejecutar una acción `handler` sobre un registro ajeno vía POST directo (IDOR). La corrección reusa **exactamente** la misma lógica que `CrudResourceService::assertOwnership` apoyándose en `CrudScopeResolver::ownerMeta()`, garantizando consistencia.

**Garantía de no-cambio de lógica:**
- Recurso **sin** `scope: owner` → `ownerMeta()` devuelve `null` → la verificación retorna de inmediato → comportamiento **idéntico** (los tests existentes de `CrudActionServiceTest`, que instancian el servicio solo con el registry y llaman a `dispatch()`, no tocan este código y siguen verdes).
- Usuario **admin** con permiso de bypass → retorna antes de comparar owner → **idéntico**.
- Único cambio observable: usuario no-dueño sin bypass recibe `ValidationException('El registro solicitado no existe.')` (mismo mensaje que el resto del CRUD, no revela existencia).

**Files:**
- Modify: `app/Application/Services/CrudActionService.php`
- Modify: `config/container.php` (binding de `CrudActionService`)
- Test: `tests/Security/CrudActionOwnershipTest.php`

- [ ] **Step 1: Escribir los tests de ownership (caracterización + seguridad)**

Crear `tests/Security/CrudActionOwnershipTest.php`. Usa los fixtures de acción existentes (`tests/fixtures/action_handlers.php`) y un loader/data/resolver/rbac de prueba minimalistas. Reusa el patrón de `tests/Crud/Action/CrudActionServiceTest.php`.

```php
<?php

declare(strict_types=1);

use App\Application\Services\CrudActionService;
use App\Application\Services\CrudHandlerRegistry;
use App\Application\Services\CrudScopeResolver;
use App\Domain\Exceptions\ValidationException;

require_once dirname(__DIR__) . '/fixtures/action_handlers.php';

/**
 * Dobles de prueba mínimos: cada uno expone solo lo que run() necesita.
 */
function ownership_definition(array $scope = []): object
{
    return new class($scope) {
        public function __construct(private array $scope) {}
        public function key(): string { return 'eventos'; }
        public function table(): string { return 'dom_eventos'; }
        public function primaryKey(): string { return 'id'; }
        public function permissionPrefix(): string { return 'eventos'; }
        public function listScope(): array { return $this->scope; }
    };
}

// configLoader doble: devuelve la definición fija
function ownership_loader(object $def): object
{
    return new class($def) {
        public function __construct(private object $def) {}
        public function load(string $resource): object { return $this->def; }
    };
}

// dataService doble: devuelve un registro con created_by controlado
function ownership_data(array $record): object
{
    return new class($record) {
        public function __construct(private array $record) {}
        public function find(object $def, int $id): array { return $this->record; }
    };
}

// resolver doble: siempre devuelve una acción handler ejecutable 'autorizar'
function ownership_resolver(): object
{
    return new class {
        public function resolveExecutable(object $def, string $name): object {
            return new class {
                public function name(): string { return 'autorizar'; }
                public function type(): string { return 'handler'; }
                public function handler(): string { return 'evt_auth'; }
                public function isHandler(): bool { return true; }
                public function isTransition(): bool { return false; }
                public function resolvePermission(string $prefix): ?string { return null; }
                public function isVisibleFor(array $r): bool { return true; }
                public function isEnabledFor(array $r): bool { return true; }
            };
        }
    };
}

// rbac doble: 'puede' controla el bypass; 'verificar' no-op
function ownership_rbac(bool $bypass): object
{
    return new class($bypass) {
        public function __construct(private bool $bypass) {}
        public function verificar(string $slug): void {}
        public function puede(string $slug): bool { return $this->bypass; }
    };
}

// bitacora doble: registra si se llamó
function ownership_bitacora(): object
{
    return new class {
        public bool $registrado = false;
        public function registrar(...$args): void { $this->registrado = true; }
    };
}

function ownership_service(object $def, array $record, bool $bypass, object $bitacora): CrudActionService
{
    return new CrudActionService(
        new CrudHandlerRegistry(['evt_auth' => RecordingActionHandler::class]),
        ownership_loader($def),
        ownership_data($record),
        ownership_resolver(),
        ownership_rbac($bypass),
        $bitacora,
        null,                       // transitionService no usado
        new CrudScopeResolver()     // <-- nuevo parámetro, scope real
    );
}

// ── Caracterización: recurso SIN owner scope ejecuta igual que siempre ───────
test('run() ejecuta la acción cuando el recurso no declara owner scope', function (): void {
    RecordingActionHandler::$last = null;
    $bit = ownership_bitacora();
    $def = ownership_definition([]); // sin scope
    $svc = ownership_service($def, ['id' => 7, 'created_by' => 999, 'deleted' => 0], false, $bit);
    $svc->run('eventos', 7, 'autorizar', [], 42, '127.0.0.1');
    assert_true(RecordingActionHandler::$last !== null, 'handler corrió (sin scope, sin cambio de lógica)');
    assert_true($bit->registrado, 'se registró en bitácora');
});

// ── Caracterización: dueño legítimo ejecuta igual ────────────────────────────
test('run() ejecuta la acción cuando el usuario es el dueño', function (): void {
    RecordingActionHandler::$last = null;
    $bit = ownership_bitacora();
    $def = ownership_definition(['type' => 'owner', 'column' => 'created_by']);
    $svc = ownership_service($def, ['id' => 7, 'created_by' => 42, 'deleted' => 0], false, $bit);
    $svc->run('eventos', 7, 'autorizar', [], 42, '127.0.0.1');
    assert_true(RecordingActionHandler::$last !== null, 'el dueño ejecuta normalmente');
});

// ── Caracterización: admin con bypass ejecuta igual ──────────────────────────
test('run() ejecuta la acción para admin con permiso de bypass', function (): void {
    RecordingActionHandler::$last = null;
    $bit = ownership_bitacora();
    $def = ownership_definition(['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']);
    $svc = ownership_service($def, ['id' => 7, 'created_by' => 999, 'deleted' => 0], true, $bit);
    $svc->run('eventos', 7, 'autorizar', [], 42, '127.0.0.1');
    assert_true(RecordingActionHandler::$last !== null, 'admin bypass ejecuta normalmente');
});

// ── Seguridad: usuario ajeno NO ejecuta y NO deja bitácora ───────────────────
test('run() bloquea la acción sobre un registro ajeno (IDOR)', function (): void {
    RecordingActionHandler::$last = null;
    $bit = ownership_bitacora();
    $def = ownership_definition(['type' => 'owner', 'column' => 'created_by']);
    $svc = ownership_service($def, ['id' => 7, 'created_by' => 999, 'deleted' => 0], false, $bit);
    assert_throws(ValidationException::class, function () use ($svc): void {
        $svc->run('eventos', 7, 'autorizar', [], 42, '127.0.0.1');
    });
    assert_true(RecordingActionHandler::$last === null, 'el handler NO debe correr');
    assert_true($bit->registrado === false, 'NO debe registrarse bitácora');
});

// ── Seguridad: runBulk cuenta el ajeno como fallo, no lo ejecuta ─────────────
test('runBulk() cuenta el registro ajeno como fallo sin ejecutar handler', function (): void {
    RecordingActionHandler::$last = null;
    $bit = ownership_bitacora();
    $def = ownership_definition(['type' => 'owner', 'column' => 'created_by']);
    // resolver de bulk: reusa resolveBulkExecutable
    $svc = new CrudActionService(
        new CrudHandlerRegistry(['evt_auth' => RecordingActionHandler::class]),
        ownership_loader($def),
        ownership_data(['id' => 7, 'created_by' => 999, 'deleted' => 0]),
        new class {
            public function resolveBulkExecutable(object $def, string $name): object {
                return ownership_resolver()->resolveExecutable($def, $name);
            }
        },
        ownership_rbac(false),
        $bit,
        null,
        new CrudScopeResolver()
    );
    $summary = $svc->runBulk('eventos', 'autorizar', [7], [], 42, '127.0.0.1');
    assert_same(0, $summary['ok'], 'ninguno ok');
    assert_same(1, $summary['fail'], 'uno fallido (ajeno)');
    assert_true(RecordingActionHandler::$last === null, 'handler NO corrió en bulk');
});
```

> Nota para el implementador: si la firma real de algún método del resolver/loader difiere, ajusta el doble anónimo — lo único que importa es que `run()`/`runBulk()` reciban una definición cuyo `listScope()` devuelva el array de scope y un `find()` que devuelva el registro. La clase real `CrudScopeResolver` se usa **sin doblar** (es pura).

- [ ] **Step 2: Correr y verificar que fallan**

Run: `php tests/run.php Security/CrudActionOwnership`
Expected: FAIL — el constructor de `CrudActionService` aún no acepta el 8º parámetro `CrudScopeResolver`, y/o la acción ajena se ejecuta (no se lanza `ValidationException`).

- [ ] **Step 3: Añadir `CrudScopeResolver` al constructor de `CrudActionService`**

En `app/Application/Services/CrudActionService.php`, añadir el `use`:

```php
use App\Domain\Entities\CrudResourceDefinition;
```

Cambiar el constructor para añadir el parámetro **al final** (líneas 28-36):

```php
    public function __construct(
        private readonly CrudHandlerRegistry $handlerRegistry,
        private readonly ?CrudConfigLoader $configLoader = null,
        private readonly ?CrudDataService $dataService = null,
        private readonly ?CrudActionResolver $resolver = null,
        private readonly ?RbacService $rbacService = null,
        private readonly ?BitacoraRepositoryInterface $bitacoraRepository = null,
        private readonly ?CrudTransitionService $transitionService = null,
        private readonly ?CrudScopeResolver $scopeResolver = null
    ) {}
```

- [ ] **Step 4: Añadir el método de verificación de ownership (espejo de `CrudResourceService`)**

Añadir este método privado dentro de `CrudActionService` (debajo de `dispatch()`):

```php
    /**
     * Bloqueo server-side de propiedad para acciones, idéntico en lógica a
     * CrudResourceService::assertOwnership. Si el recurso no declara owner
     * scope, retorna sin efecto (comportamiento sin cambios).
     *
     * @param array<string, mixed> $record
     */
    private function assertActionOwnership(CrudResourceDefinition $definition, array $record, ?int $userId): void
    {
        if ($this->scopeResolver === null || $this->rbacService === null) {
            return;
        }
        $meta = $this->scopeResolver->ownerMeta($definition);
        if ($meta === null) {
            return;
        }
        if ($meta['bypass'] !== null && $this->rbacService->puede($meta['bypass'])) {
            return;
        }
        $owner = $record[$meta['column']] ?? null;
        if ($userId === null || (string) $owner !== (string) $userId) {
            throw new ValidationException('El registro solicitado no existe.');
        }
    }
```

- [ ] **Step 5: Llamar la verificación en `run()` justo después de cargar el registro**

En `run()`, inmediatamente **después** del bloque que valida que el registro existe (después de la línea 76, antes del re-chequeo de `visible/enabled`), insertar:

```php
        $this->assertActionOwnership($definition, $record, $userId);
```

Queda:

```php
        $record = $this->dataService->find($definition, $id);
        if (!is_array($record) || (int) ($record['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }

        $this->assertActionOwnership($definition, $record, $userId);

        // Re-chequeo server-side: nunca confiar en la UI.
        if (!$action->isVisibleFor($record) || !$action->isEnabledFor($record)) {
```

- [ ] **Step 6: Llamar la verificación en `runBulk()` dentro del loop**

En `runBulk()`, dentro del `try`, **después** de validar que el registro existe (después de la línea 145) y **antes** de construir el `CrudActionContext`, insertar:

```php
                $this->assertActionOwnership($definition, $record, $userId);
```

Queda:

```php
                $record = $this->dataService->find($definition, $id);
                if (!is_array($record) || (int) ($record['deleted'] ?? 0) === 1) {
                    throw new ValidationException("Registro {$id} no existe.");
                }
                $this->assertActionOwnership($definition, $record, $userId);
                $ctx = new CrudActionContext(
```

> El `ValidationException` lanzado por ownership es capturado por el `catch (\Throwable)` existente del loop → cuenta como `fail` (semántica best-effort intacta).

- [ ] **Step 7: Inyectar `CrudScopeResolver` en el binding del contenedor**

En `config/container.php`, en el binding de `CrudActionService` (líneas 145-153), añadir el resolver como último argumento. Reemplazar:

```php
    $container->singleton(CrudActionService::class, fn(Container $c) => new CrudActionService(
        $c->get(CrudHandlerRegistry::class),
        $c->get(CrudConfigLoader::class),
        $c->get(CrudDataService::class),
        $c->get(CrudActionResolver::class),
        $c->get(RbacService::class),
        $c->get(BitacoraRepositoryInterface::class),
        $c->get(CrudTransitionService::class)
    ));
```

por:

```php
    $container->singleton(CrudActionService::class, fn(Container $c) => new CrudActionService(
        $c->get(CrudHandlerRegistry::class),
        $c->get(CrudConfigLoader::class),
        $c->get(CrudDataService::class),
        $c->get(CrudActionResolver::class),
        $c->get(RbacService::class),
        $c->get(BitacoraRepositoryInterface::class),
        $c->get(CrudTransitionService::class),
        $c->get(CrudScopeResolver::class)
    ));
```

- [ ] **Step 8: Verificar sintaxis**

Run: `php -l app/Application/Services/CrudActionService.php; php -l config/container.php`
Expected: `No syntax errors detected`.

- [ ] **Step 9: Correr los tests de ownership y verificar verde**

Run: `php tests/run.php Security/CrudActionOwnership`
Expected: PASS (los 5 tests, incluidos los 3 de caracterización).

- [ ] **Step 10: Correr los tests existentes de acciones (no deben cambiar)**

Run: `php tests/run.php Crud/Action`
Expected: PASS — los tests previos de `CrudActionServiceTest` siguen verdes sin modificación (prueba de que la lógica no cambió para los flujos existentes).

- [ ] **Step 11: Suite completa**

Run: `php tests/run.php`
Expected: `≥N passed, 0 failed`.

- [ ] **Step 12: Commit**

```bash
git add app/Application/Services/CrudActionService.php config/container.php tests/Security/CrudActionOwnershipTest.php
git commit -m "security: enforce ownership in CRUD row/bulk actions (H2 IDOR)"
```

---

## Task 4: H3 — Endurecer uploads (tamaño + MIME), preservando subidas válidas

**Contexto:** `CrudDataService::handleUpload()` (líneas 621-667) solo valida extensión **si el config la declara**, sin verificación de tamaño ni de tipo real (MIME). Un archivo ejecutable renombrado a `.png` se guarda en `public/` y queda servible. La corrección extrae la validación a una unidad pura `UploadValidator` (testeable) que añade tamaño máximo + verificación MIME. **El contrato de retorno y el nombrado de archivo no cambian**: una subida legítima (extensión permitida, dentro de tamaño, MIME coherente) produce el mismo path que antes.

**Files:**
- Create: `app/Application/Services/UploadValidator.php`
- Test: `tests/Security/UploadValidatorTest.php`
- Modify: `config/security.php`
- Modify: `app/Application/Services/CrudDataService.php` (`handleUpload`)
- Modify: `config/container.php` (binding de `CrudDataService`)

- [ ] **Step 1: Escribir los tests de `UploadValidator`**

Crear `tests/Security/UploadValidatorTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\UploadValidator;
use App\Domain\Exceptions\ValidationException;

function up_file(string $name, int $size, int $error = UPLOAD_ERR_OK): array
{
    return ['name' => $name, 'size' => $size, 'error' => $error, 'tmp_name' => '/tmp/x'];
}

// ── Caracterización: extensión permitida válida pasa y devuelve la extensión ──
test('UploadValidator acepta extensión permitida y devuelve la extensión', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    $ext = $v->assertValid(up_file('foto.PNG', 1024), 'Foto', ['png', 'jpg'], 'image/png');
    assert_same('png', $ext);
});

test('UploadValidator acepta cuando no hay lista blanca declarada', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    $ext = $v->assertValid(up_file('doc.pdf', 2048), 'Doc', null, 'application/pdf');
    assert_same('pdf', $ext);
});

test('UploadValidator omite verificación MIME cuando no se provee MIME', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    $ext = $v->assertValid(up_file('foto.png', 1024), 'Foto', ['png'], null);
    assert_same('png', $ext);
});

// ── Seguridad / robustez ─────────────────────────────────────────────────────
test('UploadValidator conserva el mensaje de extensión no permitida', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('malware.exe', 1024), 'Foto', ['png', 'jpg'], null);
    });
});

test('UploadValidator rechaza archivo que supera el tamaño máximo', function (): void {
    $v = new UploadValidator(1024); // 1 KB
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('foto.png', 2048), 'Foto', ['png'], 'image/png');
    });
});

test('UploadValidator rechaza MIME incoherente con la extensión (ejecutable disfrazado)', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('foto.png', 1024), 'Foto', ['png'], 'text/x-php');
    });
});

test('UploadValidator propaga error de subida del PHP', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('foto.png', 0, UPLOAD_ERR_PARTIAL), 'Foto', ['png'], null);
    });
});
```

- [ ] **Step 2: Correr y verificar que fallan**

Run: `php tests/run.php Security/UploadValidator`
Expected: FAIL (`Class "App\Application\Services\UploadValidator" not found`).

- [ ] **Step 3: Implementar `UploadValidator`**

Crear `app/Application/Services/UploadValidator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Exceptions\ValidationException;

/**
 * Validación pura de un archivo subido: error PHP, tamaño máximo, lista blanca
 * de extensiones y coherencia MIME ↔ extensión. No mueve archivos ni toca disco
 * (el MIME detectado se inyecta), por lo que es unit-testable. Conserva los
 * mensajes de error previos del CRUD Engine para no alterar el flujo existente.
 */
final class UploadValidator
{
    /**
     * MIME esperados por extensión conocida. Si una extensión NO está aquí, la
     * verificación MIME se omite (no se bloquea), preservando la capacidad de
     * subir tipos legítimos no catalogados.
     *
     * @var array<string, list<string>>
     */
    private const MIME_BY_EXT = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'svg'  => ['image/svg+xml', 'text/plain', 'text/xml'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];

    public function __construct(private readonly int $maxBytes = 10485760) {}

    /**
     * @param array<string, mixed> $file estructura de $_FILES[campo]
     * @param list<string>|null    $allowedExtensions lista blanca declarada en el campo
     * @param string|null          $detectedMime MIME real (finfo) o null para omitir el chequeo
     * @return string extensión validada en minúsculas, sin punto
     */
    public function assertValid(array $file, string $label, ?array $allowedExtensions, ?string $detectedMime): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        if ($error !== UPLOAD_ERR_OK) {
            throw new ValidationException('Error al subir archivo para ' . $label . '.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($this->maxBytes > 0 && $size > $this->maxBytes) {
            throw new ValidationException('El archivo para ' . $label . ' supera el tamaño máximo permitido.');
        }

        $original = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (is_array($allowedExtensions) && $allowedExtensions !== []) {
            $allowedLower = array_map(static fn($x): string => strtolower((string) $x), $allowedExtensions);
            if ($extension === '' || !in_array($extension, $allowedLower, true)) {
                throw new ValidationException('Extensión de archivo no permitida para ' . $label . '.');
            }
        }

        if ($detectedMime !== null && $extension !== '' && isset(self::MIME_BY_EXT[$extension])) {
            if (!in_array($detectedMime, self::MIME_BY_EXT[$extension], true)) {
                throw new ValidationException('El contenido del archivo para ' . $label . ' no coincide con su extensión.');
            }
        }

        return $extension;
    }
}
```

- [ ] **Step 4: Correr los tests y verificar verde**

Run: `php tests/run.php Security/UploadValidator`
Expected: PASS (los 7 tests).

- [ ] **Step 5: Exponer `max_upload_mb` en config**

En `config/security.php`, añadir la clave (ya existe `MAX_UPLOAD_MB=10` en `.env.example`):

```php
<?php

use App\Kernel\EnvLoader;

return [
    'bcrypt_rounds'      => EnvLoader::get('BCRYPT_ROUNDS', 12),
    'csrf_token_length'  => EnvLoader::get('CSRF_TOKEN_LENGTH', 32),
    'max_upload_mb'      => EnvLoader::get('MAX_UPLOAD_MB', 10),
];
```

- [ ] **Step 6: Inyectar `UploadValidator` en `CrudDataService`**

En `app/Application/Services/CrudDataService.php`, añadir al final del constructor un parámetro opcional (revisar el constructor actual y añadir como **último** parámetro):

```php
        private readonly ?UploadValidator $uploadValidator = null
```

> El implementador debe abrir el constructor de `CrudDataService` (cerca de la parte superior de la clase) y agregar la coma + línea anterior tras el último parámetro existente (`CrudScopeResolver $scopeResolver`). No reordenar los existentes.

- [ ] **Step 7: Reescribir el cuerpo de `handleUpload()` para delegar la validación**

Reemplazar el bloque de validación de extensión dentro de `handleUpload()` (las líneas que van desde el cálculo de `$original`/`$extension` hasta el final del chequeo de `$allowed`, líneas ~635-644) de modo que:
- se conserve el early-return de `UPLOAD_ERR_NO_FILE` (líneas 627-630),
- se detecte el MIME con `finfo`,
- se delegue en `UploadValidator::assertValid()`,
- el resto (nombrado seguro, mkdir, move) quede **idéntico**.

El método completo queda así:

```php
    private function handleUpload(CrudResourceDefinition $definition, CrudFieldDefinition $field, array $files): ?string
    {
        if (!$definition->uploadsEnabled()) {
            return null;
        }

        $file = $files[$field->name()] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $detectedMime = null;
        if ($tmpName !== '' && is_readable($tmpName) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detectedMime = finfo_file($finfo, $tmpName) ?: null;
                finfo_close($finfo);
            }
        }

        $allowed = $field->validation()['allowed_extensions'] ?? null;
        $validator = $this->uploadValidator ?? new UploadValidator(
            ((int) Config::get('security.max_upload_mb', 10)) * 1024 * 1024
        );
        $extension = $validator->assertValid(
            $file,
            $field->label(),
            is_array($allowed) ? $allowed : null,
            $detectedMime
        );

        $original = (string) ($file['name'] ?? '');
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original, PATHINFO_FILENAME));
        if ($safeName === '') {
            $safeName = 'upload';
        }
        $filename = $safeName . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
        if ($extension !== '') {
            $filename .= '.' . $extension;
        }

        $publicRelative = trim($definition->uploadsPath(), '/');
        $publicAbsolute = PUBLIC_PATH . '/' . $publicRelative;

        if (!is_dir($publicAbsolute) && !mkdir($publicAbsolute, 0775, true) && !is_dir($publicAbsolute)) {
            throw new ValidationException('No fue posible crear el directorio de uploads.');
        }

        $destination = $publicAbsolute . '/' . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            throw new ValidationException('No fue posible guardar el archivo subido.');
        }

        return '/' . $publicRelative . '/' . $filename;
    }
```

- [ ] **Step 8: Asegurar el import de `Config` en `CrudDataService`**

Verificar que `CrudDataService.php` tenga (si no, añadir junto a los demás `use`):

```php
use App\Kernel\Config\Config;
```

Run: `php -r "echo (strpos(file_get_contents('app/Application/Services/CrudDataService.php'), 'use App\\\\Kernel\\\\Config\\\\Config;') !== false) ? 'OK import' : 'FALTA import';"`
Expected: `OK import` (si imprime `FALTA import`, añadir el `use`).

- [ ] **Step 9: Inyectar el validador configurado en el contenedor**

En `config/container.php`, en el binding de `CrudDataService` (líneas 118-126), añadir el validador como último argumento. Añadir el `use` arriba:

```php
use App\Application\Services\UploadValidator;
```

Reemplazar el binding por:

```php
    $container->singleton(CrudDataService::class, fn(Container $c) => new CrudDataService(
        $c->get(GenericCrudRepository::class),
        $c->get(BitacoraRepositoryInterface::class),
        $c->get(CrudHookRunner::class),
        $c->get(CrudFieldValidationService::class),
        $c->get(CrudDbConstraintValidator::class),
        $c->get(CrudHandlerRegistry::class),
        $c->get(CrudScopeResolver::class),
        new UploadValidator(((int) \App\Kernel\Config\Config::get('security.max_upload_mb', 10)) * 1024 * 1024)
    ));
```

- [ ] **Step 10: Verificar sintaxis**

Run: `php -l app/Application/Services/UploadValidator.php; php -l app/Application/Services/CrudDataService.php; php -l config/security.php; php -l config/container.php`
Expected: `No syntax errors detected` en los cuatro.

- [ ] **Step 11: Suite completa**

Run: `php tests/run.php`
Expected: `≥N passed, 0 failed`.

- [ ] **Step 12: Commit**

```bash
git add app/Application/Services/UploadValidator.php tests/Security/UploadValidatorTest.php config/security.php app/Application/Services/CrudDataService.php config/container.php
git commit -m "security: validate upload size and MIME via UploadValidator (H3)"
```

---

## Task 5: M6 — Forzar `APP_DEBUG=false` cuando `APP_ENV=production`

**Contexto:** `Bootstrap.php:49` lee `app.debug` directo. Si alguien deja `APP_DEBUG=true` en producción, los stack traces (con rutas y SQL) se exponen. La corrección extrae la decisión a una función pura `DebugMode::resolve()` y la usa en Bootstrap. **Local/staging no cambian**: solo `production` fuerza `false`.

**Files:**
- Create: `app/Kernel/Config/DebugMode.php`
- Test: `tests/Security/DebugModeTest.php`
- Modify: `app/Kernel/Bootstrap.php` (línea 49)

- [ ] **Step 1: Escribir los tests**

Crear `tests/Security/DebugModeTest.php`:

```php
<?php

declare(strict_types=1);

use App\Kernel\Config\DebugMode;

// ── Caracterización: entornos no-producción conservan el flag configurado ────
test('DebugMode respeta debug=true en local', function (): void {
    assert_same(true, DebugMode::resolve('local', true));
});

test('DebugMode respeta debug=false en local', function (): void {
    assert_same(false, DebugMode::resolve('local', false));
});

test('DebugMode respeta debug=true en staging', function (): void {
    assert_same(true, DebugMode::resolve('staging', true));
});

// ── Seguridad: producción fuerza false sin importar el config ────────────────
test('DebugMode fuerza false en production aunque debug sea true', function (): void {
    assert_same(false, DebugMode::resolve('production', true));
});

test('DebugMode es case-insensitive para production', function (): void {
    assert_same(false, DebugMode::resolve('PRODUCTION', true));
});

test('DebugMode trata env nulo como no-producción', function (): void {
    assert_same(true, DebugMode::resolve(null, true));
});
```

- [ ] **Step 2: Correr y verificar que fallan**

Run: `php tests/run.php Security/DebugMode`
Expected: FAIL (`Class "App\Kernel\Config\DebugMode" not found`).

- [ ] **Step 3: Implementar `DebugMode`**

Crear `app/Kernel/Config/DebugMode.php`:

```php
<?php

declare(strict_types=1);

namespace App\Kernel\Config;

/**
 * Decide si el modo debug está activo. En producción siempre es false, sin
 * importar la configuración, para no exponer stack traces. En cualquier otro
 * entorno respeta el flag configurado (comportamiento sin cambios).
 */
final class DebugMode
{
    public static function resolve(?string $env, bool $configDebug): bool
    {
        if (strtolower((string) $env) === 'production') {
            return false;
        }
        return $configDebug;
    }
}
```

- [ ] **Step 4: Correr los tests y verificar verde**

Run: `php tests/run.php Security/DebugMode`
Expected: PASS (los 6 tests).

- [ ] **Step 5: Usar `DebugMode` en Bootstrap**

En `app/Kernel/Bootstrap.php`, añadir el `use` junto a los demás (debajo de `use App\Kernel\Config\Config;`):

```php
use App\Kernel\Config\DebugMode;
```

Reemplazar la línea 49:

```php
$isDebug = (bool) Config::get('app.debug', false);
```

por:

```php
$isDebug = DebugMode::resolve((string) Config::get('app.env', 'production'), (bool) Config::get('app.debug', false));
```

- [ ] **Step 6: Verificar sintaxis**

Run: `php -l app/Kernel/Bootstrap.php; php -l app/Kernel/Config/DebugMode.php`
Expected: `No syntax errors detected`.

- [ ] **Step 7: Suite completa**

Run: `php tests/run.php`
Expected: `≥N passed, 0 failed`.

- [ ] **Step 8: Commit**

```bash
git add app/Kernel/Config/DebugMode.php tests/Security/DebugModeTest.php app/Kernel/Bootstrap.php
git commit -m "security: force APP_DEBUG=false in production (M6)"
```

---

## Task 6: Verificación final de regresión y seguridad

**Files:**
- (ninguno) — verificación + checklist manual

- [ ] **Step 1: Suite completa final**

Run: `php tests/run.php`
Expected: `≥N passed, 0 failed` (N = baseline de Task 0; debe haber aumentado por los nuevos tests de seguridad, nunca disminuido).

- [ ] **Step 2: Confirmar que ningún test de caracterización previo se rompió**

Run: `php tests/run.php Crud`
Expected: PASS — todos los tests del CRUD Engine previos siguen verdes (prueba de "mismo resultado en todas las variantes").

- [ ] **Step 3: Lint de todos los archivos tocados**

Run:
```bash
php -l app/Kernel/Http/SafeRedirect.php
php -l app/Application/Services/CrudActionService.php
php -l app/Application/Services/UploadValidator.php
php -l app/Application/Services/CrudDataService.php
php -l app/Kernel/Config/DebugMode.php
php -l app/Kernel/Bootstrap.php
php -l app/Presentation/Middlewares/CsrfMiddleware.php
php -l app/Kernel/BaseClasses/BaseController.php
php -l config/security.php
php -l config/container.php
```
Expected: `No syntax errors detected` en todos.

- [ ] **Step 4: Verificar arranque del contenedor (wiring real)**

Run: `php -S localhost:8000 -t public` (en background) y luego `curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/login`
Expected: `200` (la app levanta; los nuevos parámetros del contenedor se resuelven sin error). Detener el servidor tras verificar.

- [ ] **Step 5: Checklist manual de seguridad (marcar lo verificado)**

- [ ] `git ls-files .env` → vacío.
- [ ] Login con `Referer: https://evil.com` y CSRF inválido → redirige a `/`, no a evil.com.
- [ ] Usuario no-dueño ejecutando acción CRUD sobre registro ajeno (recurso con `scope: owner`) → "El registro solicitado no existe.", sin efecto ni bitácora.
- [ ] Subir archivo `.png` que en realidad es PHP → rechazado por MIME.
- [ ] Subir archivo > `MAX_UPLOAD_MB` → rechazado por tamaño.
- [ ] `APP_ENV=production` + `APP_DEBUG=true` → sin stack trace al forzar un error.
- [ ] Subida legítima (png real, tamaño normal) → funciona igual que antes (path con nombre aleatorio).

- [ ] **Step 6: Actualizar el roadmap marcando Fase 0 como ejecutada**

En `docs/superpowers/plans/2026-06-09-roadmap-seguridad-y-continuidad.md`, al final de la sección "Fase 0 — Hardening mínimo", añadir una nota:

```markdown
> **Estado:** Fase 0 implementada en `docs/superpowers/plans/2026-06-10-hardening-seguridad-fase0.md`
> (C1, H1, H2, H3, M6 + checklist de despliegue). M1/M2/M3/M4/M5/M7 quedan para Fase 3.
```

- [ ] **Step 7: Commit final de documentación**

```bash
git add docs/superpowers/plans/2026-06-09-roadmap-seguridad-y-continuidad.md
git commit -m "docs: mark security hardening Fase 0 as implemented"
```

---

## Fuera de alcance de este plan (handoff a Fase 1/3)

Estos ítems del roadmap **no** se implementan aquí; requieren diseño previo (Fase 1) o son endurecimientos posteriores (Fase 3), y se documentan para no perderlos:

| ID | Ítem | Fase |
|----|------|------|
| M1 | Rate limiting en login | 3 |
| M2 | CSRF en `/logout` | 3 |
| M3 | Reemplazar exclusión global `/api/` del CSRF | 3 (cuando exista API) |
| M4 | Security headers + CSP en bootstrap PHP | 3 |
| M5 | `SESSION_SECURE` por defecto / validación de entorno | 3 (cubierto por checklist) |
| M7 | Slug dedicado `permisos.gestionar` | 3 |
| M9 | Validador: prohibir columna owner editable en formulario | 1 (diseño) → 3 |
| Fase 1 | Plantillas de spec `dom_*`, uploads doc, dashboard widgets | 1 (solo diseño) |

---

## Self-Review (ejecutado por el autor del plan)

**1. Cobertura del roadmap (Fase 0):** C1 → Task 1 ✓; H1 → Task 2 ✓; H2 → Task 3 ✓; H3 → Task 4 ✓; M6 → Task 5 ✓; checklist de despliegue (0.6) → Task 1 Step 5 ✓. Los ítems M1–M9 restantes están explícitamente diferidos arriba.

**2. Escaneo de placeholders:** sin "TBD/TODO/etc." Todos los pasos de código incluyen el código completo; todos los comandos incluyen salida esperada.

**3. Consistencia de tipos/firmas:**
- `SafeRedirect::toInternal(?string, string=): string` — mismo nombre en tests, middleware y controller.
- `CrudActionService` 8º parámetro `?CrudScopeResolver $scopeResolver = null`; método `assertActionOwnership(CrudResourceDefinition, array, ?int)`; `ownerMeta()` ya existe en `CrudScopeResolver` y devuelve `array{column,bypass}|null` — consumido idénticamente.
- `UploadValidator::__construct(int $maxBytes=10485760)` y `assertValid(array, string, ?array, ?string): string` — misma firma en tests y en `handleUpload`.
- `DebugMode::resolve(?string, bool): bool` — misma firma en tests y Bootstrap.

**4. Garantía transversal de no-cambio de lógica:** cada task de código (2,3,4,5) incluye tests de caracterización que pasan contra el comportamiento legítimo y el paso de "suite completa verde", más Task 6 Step 2 que re-corre toda la suite CRUD previa. Si alguno se torna rojo, el cambio se revierte.
</content>
</invoke>
