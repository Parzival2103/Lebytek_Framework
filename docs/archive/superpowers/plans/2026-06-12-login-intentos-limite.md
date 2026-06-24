# Login Rate Limiting (IP + Email) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Limitar intentos fallidos de login con contadores duales por IP y email normalizado, bloqueo temporal configurable, mensaje genérico anti-enumeración, y reset tras login exitoso — cerrando el hallazgo M1 del roadmap de seguridad.

**Architecture:** Tabla append-only `auth_login_intentos` (dos filas por fallo: dimensión `ip` y `email`), repositorio PDO + fake en memoria, política en `LoginRateLimitService` (patrón de `AuthTokenService::contarRecientes`), orquestación en `LoginUseCase` antes de `AuthService::autenticar`, IP inyectada vía `LoginDTO::clientIp` desde `AuthController`. Sin lockout en `auth_usuarios`.

**Tech Stack:** PHP 8.1, MySQL/InnoDB, PDO (`BaseRepository`), test runner propio (`php tests/run.php [filtro]`), `AppLogger::warning` para bloqueos operativos.

**Spec de referencia:** `docs/superpowers/specs/2026-06-12-login-intentos-limite-design.md`

---

## Mapa de archivos

| Archivo | Rol |
|---------|-----|
| `app/Domain/Interfaces/LoginIntentoRepositoryInterface.php` | Contrato de persistencia de fallos |
| `app/Infrastructure/Repositories/LoginIntentoRepository.php` | Implementación PDO |
| `app/Application/Services/LoginRateLimitService.php` | Política: permitir / registrar / limpiar / log warning |
| `app/Application/DTO/Auth/LoginDTO.php` | Campo `clientIp` |
| `app/Application/UseCases/Auth/LoginUseCase.php` | Orquestación pre/post autenticación |
| `app/Presentation/Controllers/AuthController.php` | Pasa `$request->ip()` al DTO |
| `config/auth.php` | Sección `login` con env |
| `config/container.php` | Bindings singleton |
| `database/migrations/20260612130000_auth_login_intentos.sql` | Migración incremental |
| `database/schema/schema.sql` | Baseline actualizado |
| `tests/fixtures/auth_fakes.php` | `FakeLoginIntentoRepository` + `FakePermisoRepository` |
| `tests/Auth/LoginIntentoRepositoryContractTest.php` | Contrato del fake |
| `tests/Auth/LoginRateLimitServiceTest.php` | Política de throttle |
| `tests/Auth/LoginUseCaseTest.php` | Integración caso de uso |
| `.env.example` | Variables `LOGIN_*` |
| `docs/core/auth_rbac_seguridad_v0.1.md` | Checklist login actualizado |

---

## Contexto para el implementador (leer primero)

**Flujo actual:**

1. `POST /login` → `AuthController::login()` crea `LoginDTO` y llama `LoginUseCase::execute()`.
2. `LoginUseCase` valida con `LoginValidator`, luego `AuthService::autenticar()` y `iniciarSesion()`.
3. Fallos lanzan `AuthException` con `'Credenciales incorrectas.'` o mensaje de cuenta inactiva.
4. IP del cliente: `Request::ip()` en `app/Kernel/Http/Request.php:162-169`.
5. Email normalizado: `Email` VO hace `strtolower(trim())` en `app/Domain/ValueObjects/Email.php:21`.

**Regla de bloqueo (default `max_intentos = 5`):**

- Antes de cada intento: si `contarFallosRecientes('ip', $ip) >= 5` **o** `contarFallosRecientes('email', $email) >= 5` → `AuthException('Credenciales incorrectas.')`.
- Tras cada fallo de autenticación (credenciales o cuenta inactiva): `registrarFallo()` inserta 2 filas.
- Tras éxito: `limpiarTrasExito()` borra filas de esa IP y email.

**Convenciones del repo:**

- Tests: `test(string $name, Closure $fn)`, `assert_true()`, `assert_same()`, `assert_null()`. Filtro: `php tests/run.php LoginRateLimit`.
- Commits en español estilo conventional (`feat(auth): ...`).
- Capas: interfaz en Domain, PDO en Infrastructure, política en Application.

---

### Task 1: Contrato `LoginIntentoRepositoryInterface` + fake + test de contrato

**Files:**
- Create: `app/Domain/Interfaces/LoginIntentoRepositoryInterface.php`
- Modify: `tests/fixtures/auth_fakes.php` (agregar `FakeLoginIntentoRepository` al final, antes de `fake_correo_auth_service`)
- Create: `tests/Auth/LoginIntentoRepositoryContractTest.php`

- [ ] **Step 1: Escribir el test de contrato que falla**

Crear `tests/Auth/LoginIntentoRepositoryContractTest.php`:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../fixtures/auth_fakes.php';

test('Contrato: registrarFallo persiste dos filas (ip y email)', function (): void {
    $repo = new FakeLoginIntentoRepository();

    $repo->registrarFallo('192.168.1.10', 'admin@test.local');

    assert_same(2, count($repo->filas));
    assert_same('ip', $repo->filas[0]['dimension']);
    assert_same('192.168.1.10', $repo->filas[0]['clave']);
    assert_same('email', $repo->filas[1]['dimension']);
    assert_same('admin@test.local', $repo->filas[1]['clave']);
});

test('Contrato: contarFallosRecientes solo cuenta dentro de la ventana', function (): void {
    $repo = new FakeLoginIntentoRepository();
    $repo->filas[] = [
        'dimension'  => 'ip',
        'clave'      => '10.0.0.1',
        'created_at' => date('Y-m-d H:i:s', time() - 20 * 60),
    ];
    $repo->filas[] = [
        'dimension'  => 'ip',
        'clave'      => '10.0.0.1',
        'created_at' => date('Y-m-d H:i:s', time() - 5 * 60),
    ];

    assert_same(1, $repo->contarFallosRecientes('ip', '10.0.0.1', 15));
});

test('Contrato: limpiarPara elimina filas de ip y email indicados', function (): void {
    $repo = new FakeLoginIntentoRepository();
    $repo->registrarFallo('10.0.0.1', 'a@test.local');
    $repo->registrarFallo('10.0.0.2', 'b@test.local');

    $repo->limpiarPara('10.0.0.1', 'a@test.local');

    assert_same(2, count($repo->filas), 'solo quedan las de la otra pareja ip/email');
    assert_same('10.0.0.2', $repo->filas[0]['clave']);
});

test('Contrato: purgarAntiguos elimina filas fuera de 2x ventana', function (): void {
    $repo = new FakeLoginIntentoRepository();
    $repo->filas[] = [
        'dimension'  => 'email',
        'clave'      => 'viejo@test.local',
        'created_at' => date('Y-m-d H:i:s', time() - 40 * 60),
    ];
    $repo->filas[] = [
        'dimension'  => 'email',
        'clave'      => 'nuevo@test.local',
        'created_at' => date('Y-m-d H:i:s', time() - 5 * 60),
    ];

    $repo->purgarAntiguos(15);

    assert_same(1, count($repo->filas));
    assert_same('nuevo@test.local', $repo->filas[0]['clave']);
});
```

- [ ] **Step 2: Correr test y verificar que falla**

Run: `php tests/run.php LoginIntentoRepositoryContract`
Expected: FAIL — interface / `FakeLoginIntentoRepository` no existen.

- [ ] **Step 3: Crear la interface**

Crear `app/Domain/Interfaces/LoginIntentoRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

/*
|--------------------------------------------------------------------------
| LoginIntentoRepositoryInterface — Persistencia de fallos de login
|--------------------------------------------------------------------------
| Contadores por dimensión (ip | email) para rate limiting temporal.
*/

interface LoginIntentoRepositoryInterface
{
    public function contarFallosRecientes(string $dimension, string $clave, int $ventanaMin): int;

    public function registrarFallo(string $ip, string $emailNormalizado): void;

    public function limpiarPara(string $ip, string $emailNormalizado): void;

    public function purgarAntiguos(int $ventanaMin): void;
}
```

- [ ] **Step 4: Agregar fake en `tests/fixtures/auth_fakes.php`**

Insertar **antes** de la función `fake_correo_auth_service` (aprox. línea 178):

```php
if (!class_exists('FakeLoginIntentoRepository')) {
    /** Repositorio de intentos de login en memoria; replica el contrato PDO. */
    class FakeLoginIntentoRepository implements \App\Domain\Interfaces\LoginIntentoRepositoryInterface
    {
        /** @var list<array{dimension:string,clave:string,created_at:string}> */
        public array $filas = [];

        public function contarFallosRecientes(string $dimension, string $clave, int $ventanaMin): int
        {
            $desde = date('Y-m-d H:i:s', time() - $ventanaMin * 60);
            $n = 0;
            foreach ($this->filas as $fila) {
                if ($fila['dimension'] === $dimension
                    && $fila['clave'] === $clave
                    && $fila['created_at'] >= $desde) {
                    $n++;
                }
            }
            return $n;
        }

        public function registrarFallo(string $ip, string $emailNormalizado): void
        {
            $ahora = date('Y-m-d H:i:s');
            $this->filas[] = ['dimension' => 'ip',    'clave' => $ip,                 'created_at' => $ahora];
            $this->filas[] = ['dimension' => 'email', 'clave' => $emailNormalizado,   'created_at' => $ahora];
        }

        public function limpiarPara(string $ip, string $emailNormalizado): void
        {
            $this->filas = array_values(array_filter(
                $this->filas,
                fn(array $fila): bool => !(
                    ($fila['dimension'] === 'ip'    && $fila['clave'] === $ip)
                    || ($fila['dimension'] === 'email' && $fila['clave'] === $emailNormalizado)
                )
            ));
        }

        public function purgarAntiguos(int $ventanaMin): void
        {
            $limite = date('Y-m-d H:i:s', time() - $ventanaMin * 2 * 60);
            $this->filas = array_values(array_filter(
                $this->filas,
                fn(array $fila): bool => $fila['created_at'] >= $limite
            ));
        }
    }
}
```

- [ ] **Step 5: Correr test y verificar que pasa**

Run: `php tests/run.php LoginIntentoRepositoryContract`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Interfaces/LoginIntentoRepositoryInterface.php tests/fixtures/auth_fakes.php tests/Auth/LoginIntentoRepositoryContractTest.php
git commit -m "feat(auth): contrato LoginIntentoRepository + fake en memoria con test de contrato"
```

---

### Task 2: `LoginRateLimitService` + tests

**Files:**
- Create: `app/Application/Services/LoginRateLimitService.php`
- Create: `tests/Auth/LoginRateLimitServiceTest.php`

- [ ] **Step 1: Escribir tests que fallan**

Crear `tests/Auth/LoginRateLimitServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\Services\LoginRateLimitService;
use App\Domain\Exceptions\AuthException;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

test('LoginRateLimitService: permite intentos por debajo del máximo', function (): void {
    $repo    = new FakeLoginIntentoRepository();
    $service = new LoginRateLimitService($repo, maxIntentos: 3, ventanaMin: 15);

    $repo->registrarFallo('1.1.1.1', 'a@test.local');
    $repo->registrarFallo('1.1.1.1', 'a@test.local');

    $service->asegurarPermitido('1.1.1.1', 'a@test.local');
    assert_true(true, 'no debe lanzar con 2 fallos y max=3');
});

test('LoginRateLimitService: bloquea cuando IP alcanza el máximo en ventana', function (): void {
    $repo    = new FakeLoginIntentoRepository();
    $service = new LoginRateLimitService($repo, maxIntentos: 3, ventanaMin: 15);

    $repo->registrarFallo('9.9.9.9', 'x@test.local');
    $repo->registrarFallo('9.9.9.9', 'y@test.local');
    $repo->registrarFallo('9.9.9.9', 'z@test.local');

    $lanzo = false;
    try {
        $service->asegurarPermitido('9.9.9.9', 'otro@test.local');
    } catch (AuthException $e) {
        $lanzo = true;
        assert_same('Credenciales incorrectas.', $e->getMessage());
    }
    assert_true($lanzo, 'debe lanzar AuthException genérica');
});

test('LoginRateLimitService: bloquea cuando email alcanza el máximo aunque la IP sea distinta', function (): void {
    $repo    = new FakeLoginIntentoRepository();
    $service = new LoginRateLimitService($repo, maxIntentos: 2, ventanaMin: 15);

    $repo->registrarFallo('1.0.0.1', 'victima@test.local');
    $repo->registrarFallo('2.0.0.2', 'victima@test.local');

    $lanzo = false;
    try {
        $service->asegurarPermitido('3.0.0.3', 'victima@test.local');
    } catch (AuthException $e) {
        $lanzo = true;
        assert_same('Credenciales incorrectas.', $e->getMessage());
    }
    assert_true($lanzo);
});

test('LoginRateLimitService: registrarFallo persiste y purga antiguos', function (): void {
    $repo    = new FakeLoginIntentoRepository();
    $service = new LoginRateLimitService($repo, maxIntentos: 5, ventanaMin: 10);

    $service->registrarFallo('5.5.5.5', 'u@test.local');

    assert_same(2, count($repo->filas));
});

test('LoginRateLimitService: limpiarTrasExito borra contadores de ip y email', function (): void {
    $repo    = new FakeLoginIntentoRepository();
    $service = new LoginRateLimitService($repo, maxIntentos: 5, ventanaMin: 15);

    $service->registrarFallo('8.8.8.8', 'ok@test.local');
    $service->limpiarTrasExito('8.8.8.8', 'ok@test.local');

    assert_same(0, count($repo->filas));
    $service->asegurarPermitido('8.8.8.8', 'ok@test.local');
});
```

- [ ] **Step 2: Correr tests y verificar que fallan**

Run: `php tests/run.php LoginRateLimitServiceTest`
Expected: FAIL — clase `LoginRateLimitService` no existe.

- [ ] **Step 3: Implementar `LoginRateLimitService`**

Crear `app/Application/Services/LoginRateLimitService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Exceptions\AuthException;
use App\Domain\Interfaces\LoginIntentoRepositoryInterface;
use App\Kernel\Logging\AppLogger;

/*
|--------------------------------------------------------------------------
| LoginRateLimitService — Política de límite de intentos de login
|--------------------------------------------------------------------------
| Contadores duales IP + email; bloqueo temporal sin lockout de cuenta.
*/

final class LoginRateLimitService
{
    private const MENSAJE_BLOQUEO = 'Credenciales incorrectas.';

    public function __construct(
        private readonly LoginIntentoRepositoryInterface $repo,
        private readonly int $maxIntentos,
        private readonly int $ventanaMin
    ) {
    }

    public function asegurarPermitido(string $ip, string $emailNormalizado): void
    {
        if ($this->estaBloqueado('ip', $ip) || $this->estaBloqueado('email', $emailNormalizado)) {
            AppLogger::warning('Login bloqueado por rate limit', [
                'ip' => $ip,
            ]);
            throw new AuthException(self::MENSAJE_BLOQUEO);
        }
    }

    public function registrarFallo(string $ip, string $emailNormalizado): void
    {
        $this->repo->registrarFallo($ip, $emailNormalizado);
        $this->repo->purgarAntiguos($this->ventanaMin);

        if ($this->estaBloqueado('ip', $ip) || $this->estaBloqueado('email', $emailNormalizado)) {
            AppLogger::warning('Login alcanzó umbral de intentos fallidos', [
                'ip' => $ip,
            ]);
        }
    }

    public function limpiarTrasExito(string $ip, string $emailNormalizado): void
    {
        $this->repo->limpiarPara($ip, $emailNormalizado);
    }

    private function estaBloqueado(string $dimension, string $clave): bool
    {
        return $this->repo->contarFallosRecientes($dimension, $clave, $this->ventanaMin) >= $this->maxIntentos;
    }
}
```

- [ ] **Step 4: Correr tests y verificar que pasan**

Run: `php tests/run.php LoginRateLimitServiceTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/LoginRateLimitService.php tests/Auth/LoginRateLimitServiceTest.php
git commit -m "feat(auth): LoginRateLimitService con contadores duales IP y email"
```

---

### Task 3: Migración SQL + schema baseline

**Files:**
- Create: `database/migrations/20260612130000_auth_login_intentos.sql`
- Modify: `database/schema/schema.sql` (insertar DDL después del bloque `auth_tokens`, ~línea 104)

- [ ] **Step 1: Crear migración**

Crear `database/migrations/20260612130000_auth_login_intentos.sql`:

```sql
-- Intentos fallidos de login para rate limiting temporal (IP + email).
CREATE TABLE IF NOT EXISTS `auth_login_intentos` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dimension`  VARCHAR(10)     NOT NULL,
  `clave`      VARCHAR(255)    NOT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_login_intentos_busqueda` (`dimension`, `clave`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Actualizar `database/schema/schema.sql`**

Después del cierre de `auth_tokens` (`) ENGINE=InnoDB...;`), agregar el mismo bloque `CREATE TABLE auth_login_intentos` de la migración, seguido de un comentario separador `-- ============================================================` como en el resto del schema.

- [ ] **Step 3: Verificar sintaxis SQL (smoke local opcional)**

Si hay BD local:

```bash
php scripts/seed.php
```

Expected: migración aplicada sin error (o ejecutar solo el SQL en el instalador que use el proyecto).

- [ ] **Step 4: Commit**

```bash
git add database/migrations/20260612130000_auth_login_intentos.sql database/schema/schema.sql
git commit -m "feat(auth): tabla auth_login_intentos para rate limiting de login"
```

---

### Task 4: `LoginIntentoRepository` (PDO)

**Files:**
- Create: `app/Infrastructure/Repositories/LoginIntentoRepository.php`

- [ ] **Step 1: Implementar repositorio PDO**

Crear `app/Infrastructure/Repositories/LoginIntentoRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Interfaces\LoginIntentoRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;

final class LoginIntentoRepository extends BaseRepository implements LoginIntentoRepositoryInterface
{
    protected string $table = 'auth_login_intentos';

    public function contarFallosRecientes(string $dimension, string $clave, int $ventanaMin): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS cnt FROM auth_login_intentos
             WHERE dimension = ? AND clave = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$dimension, $clave, $ventanaMin]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function registrarFallo(string $ip, string $emailNormalizado): void
    {
        $this->execute(
            "INSERT INTO auth_login_intentos (dimension, clave, created_at) VALUES
             ('ip', ?, NOW()), ('email', ?, NOW())",
            [$ip, $emailNormalizado]
        );
    }

    public function limpiarPara(string $ip, string $emailNormalizado): void
    {
        $this->execute(
            "DELETE FROM auth_login_intentos
             WHERE (dimension = 'ip' AND clave = ?)
                OR (dimension = 'email' AND clave = ?)",
            [$ip, $emailNormalizado]
        );
    }

    public function purgarAntiguos(int $ventanaMin): void
    {
        $this->execute(
            "DELETE FROM auth_login_intentos
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$ventanaMin * 2]
        );
    }
}
```

- [ ] **Step 2: Verificar sintaxis PHP**

Run: `php -l app/Infrastructure/Repositories/LoginIntentoRepository.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Infrastructure/Repositories/LoginIntentoRepository.php
git commit -m "feat(auth): LoginIntentoRepository PDO para intentos fallidos de login"
```

---

### Task 5: Integrar en `LoginDTO`, `LoginUseCase`, `AuthController` + tests

**Files:**
- Modify: `app/Application/DTO/Auth/LoginDTO.php`
- Modify: `app/Application/UseCases/Auth/LoginUseCase.php`
- Modify: `app/Presentation/Controllers/AuthController.php`
- Modify: `tests/fixtures/auth_fakes.php` (agregar `FakePermisoRepository`)
- Create: `tests/Auth/LoginUseCaseTest.php`

- [ ] **Step 1: Escribir tests de integración que fallan**

Crear `tests/Auth/LoginUseCaseTest.php`:

```php
<?php

declare(strict_types=1);

use App\Application\DTO\Auth\LoginDTO;
use App\Application\Services\AuthService;
use App\Application\Services\LoginRateLimitService;
use App\Application\UseCases\Auth\LoginUseCase;
use App\Application\Validators\Auth\LoginValidator;
use App\Domain\Entities\Usuario;
use App\Domain\Exceptions\AuthException;
use App\Domain\ValueObjects\Email;
use App\Kernel\Security\Hash;

require_once __DIR__ . '/../fixtures/auth_fakes.php';
require_once __DIR__ . '/../fixtures/avatar_fakes.php';

function login_use_case_con_usuario(
    FakeUsuarioRepository $usuarioRepo,
    FakeLoginIntentoRepository $intentoRepo,
    int $maxIntentos = 5
): LoginUseCase {
    $authService = new AuthService(
        $usuarioRepo,
        new FakePermisoRepository(),
        new FakeRolRepository()
    );
    $rateLimit = new LoginRateLimitService($intentoRepo, $maxIntentos, 15);

    return new LoginUseCase($authService, new LoginValidator(), $rateLimit);
}

function usuario_activo_con_password(FakeUsuarioRepository $repo, string $email, string $passwordPlano): void
{
    $usuario = new Usuario(
        nombre: 'Admin',
        apellido: 'Test',
        email: new Email($email),
        passwordHash: Hash::make($passwordPlano),
        activo: true,
        id: 1
    );
    $repo->usuarios[1] = $usuario;
}

test('LoginUseCase: bloqueado por rate limit no actualiza último acceso del usuario', function (): void {
    $usuarioRepo = new FakeUsuarioRepository();
    $intentoRepo = new FakeLoginIntentoRepository();
    usuario_activo_con_password($usuarioRepo, 'admin@test.local', 'secret123');

    for ($i = 0; $i < 3; $i++) {
        $intentoRepo->registrarFallo('10.0.0.1', 'admin@test.local');
    }

    $useCase = login_use_case_con_usuario($usuarioRepo, $intentoRepo, maxIntentos: 3);

    $lanzo = false;
    try {
        $useCase->execute(new LoginDTO(
            email: 'admin@test.local',
            password: 'secret123',
            recordar: false,
            clientIp: '10.0.0.1'
        ));
    } catch (AuthException $e) {
        $lanzo = true;
        assert_same('Credenciales incorrectas.', $e->getMessage());
    }

    assert_true($lanzo);
    assert_null($usuarioRepo->ultimoUpdate, 'autenticar no debe ejecutarse si ya está bloqueado');
});

test('LoginUseCase: credenciales incorrectas registran fallo en el repositorio', function (): void {
    $usuarioRepo = new FakeUsuarioRepository();
    $intentoRepo = new FakeLoginIntentoRepository();
    usuario_activo_con_password($usuarioRepo, 'admin@test.local', 'secret123');
    $useCase = login_use_case_con_usuario($usuarioRepo, $intentoRepo);

    $lanzo = false;
    try {
        $useCase->execute(new LoginDTO('admin@test.local', 'mala-password', false, '1.2.3.4'));
    } catch (AuthException $e) {
        $lanzo = true;
    }

    assert_true($lanzo);
    assert_same(2, count($intentoRepo->filas), 'un fallo = fila ip + fila email');
});

test('LoginUseCase: login exitoso limpia contadores de ip y email', function (): void {
    $usuarioRepo = new FakeUsuarioRepository();
    $intentoRepo = new FakeLoginIntentoRepository();
    usuario_activo_con_password($usuarioRepo, 'admin@test.local', 'secret123');
    $intentoRepo->registrarFallo('5.6.7.8', 'admin@test.local');

    $useCase = login_use_case_con_usuario($usuarioRepo, $intentoRepo);
    $useCase->execute(new LoginDTO('admin@test.local', 'secret123', false, '5.6.7.8'));

    assert_same(0, count($intentoRepo->filas));
    assert_true($usuarioRepo->ultimoUpdate !== null, 'debe registrar último acceso');
});
```

- [ ] **Step 2: Correr tests y verificar que fallan**

Run: `php tests/run.php LoginUseCaseTest`
Expected: FAIL — `LoginDTO` sin `clientIp`, `LoginUseCase` sin rate limit, `FakePermisoRepository` no existe.

- [ ] **Step 3: Agregar `FakePermisoRepository` en `tests/fixtures/auth_fakes.php`**

Insertar antes de `FakeLoginIntentoRepository` (o después de `FakeRolRepository`):

```php
if (!class_exists('FakePermisoRepository')) {
    /** Permisos en memoria; slugs vacíos para tests de login. */
    class FakePermisoRepository implements \App\Domain\Interfaces\PermisoRepositoryInterface
    {
        public function findById(int $id): ?\App\Domain\Entities\Permiso { return null; }
        public function findBySlug(string $slug): ?\App\Domain\Entities\Permiso { return null; }
        public function findAll(): array { return []; }
        public function findAllActivosOrdenadosPorModuloSlug(): array { return []; }
        public function buscarPorRolId(int $rolId): array { return []; }
        public function slugsPorUsuarioId(int $usuarioId): array { return []; }
        public function filterExistingPermisoIds(array $permisoIds, bool $soloActivos = false): array { return []; }
        public function listarTodosLosSlugs(): array { return []; }
        public function mapSlugActivo(): array { return []; }
        public function sincronizarPermisosDeRol(int $rolId, array $permisoIds): void {}
        public function save(\App\Domain\Entities\Permiso $permiso): int { return 0; }
        public function update(\App\Domain\Entities\Permiso $permiso): void {}
        public function delete(int $id): void {}
    }
}
```

- [ ] **Step 4: Extender `LoginDTO`**

En `app/Application/DTO/Auth/LoginDTO.php`, agregar el campo con default para no romper instancias existentes en tests viejos:

```php
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool   $recordar = false,
        public readonly string $clientIp = '0.0.0.0'
    ) {}
```

- [ ] **Step 5: Modificar `LoginUseCase`**

Reemplazar el contenido de `app/Application/UseCases/Auth/LoginUseCase.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCases\Auth;

use App\Application\DTO\Auth\LoginDTO;
use App\Application\Services\AuthService;
use App\Application\Services\LoginRateLimitService;
use App\Application\Validators\Auth\LoginValidator;
use App\Domain\Exceptions\AuthException;
use App\Domain\Exceptions\ValidationException;

/*
|--------------------------------------------------------------------------
| LoginUseCase — Caso de uso: iniciar sesión
|--------------------------------------------------------------------------
*/

final class LoginUseCase
{
    public function __construct(
        private readonly AuthService           $authService,
        private readonly LoginValidator        $validator,
        private readonly LoginRateLimitService $rateLimit
    ) {}

    public function execute(LoginDTO $dto): void
    {
        $this->validator->validate([
            'email'    => $dto->email,
            'password' => $dto->password,
        ]);

        $emailNormalizado = strtolower(trim($dto->email));
        $ip               = $dto->clientIp;

        $this->rateLimit->asegurarPermitido($ip, $emailNormalizado);

        try {
            $usuario = $this->authService->autenticar($dto->email, $dto->password);
        } catch (AuthException $e) {
            $this->rateLimit->registrarFallo($ip, $emailNormalizado);
            throw $e;
        }

        $this->rateLimit->limpiarTrasExito($ip, $emailNormalizado);
        $this->authService->iniciarSesion($usuario);
    }
}
```

- [ ] **Step 6: Modificar `AuthController::login`**

En `app/Presentation/Controllers/AuthController.php`, al crear el DTO (~línea 57-61), agregar `clientIp`:

```php
            $dto = new LoginDTO(
                email:    trim((string) ($datosLogin['email']    ?? '')),
                password: (string) ($datosLogin['password'] ?? ''),
                recordar: !empty($datosLogin['recordar']),
                clientIp: $request->ip()
            );
```

- [ ] **Step 7: Correr tests y verificar que pasan**

Run: `php tests/run.php LoginUseCaseTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Application/DTO/Auth/LoginDTO.php app/Application/UseCases/Auth/LoginUseCase.php app/Presentation/Controllers/AuthController.php tests/fixtures/auth_fakes.php tests/Auth/LoginUseCaseTest.php
git commit -m "feat(auth): integrar rate limiting en LoginUseCase y AuthController"
```

---

### Task 6: Configuración, container y `.env.example`

**Files:**
- Modify: `config/auth.php`
- Modify: `config/container.php`
- Modify: `.env.example`

- [ ] **Step 1: Extender `config/auth.php`**

Agregar sección `login` al array de retorno:

```php
    'login' => [
        'max_intentos' => (int) EnvLoader::get('LOGIN_MAX_INTENTOS', 5),
        'ventana_min'  => (int) EnvLoader::get('LOGIN_VENTANA_MIN', 15),
    ],
```

- [ ] **Step 2: Registrar bindings en `config/container.php`**

Agregar imports junto a los de auth existentes:

```php
use App\Application\Services\LoginRateLimitService;
use App\Domain\Interfaces\LoginIntentoRepositoryInterface;
use App\Infrastructure\Repositories\LoginIntentoRepository;
```

Después del binding de `AuthTokenService` (~línea 121), agregar:

```php
    $container->singleton(LoginIntentoRepositoryInterface::class, fn() => new LoginIntentoRepository());

    $container->singleton(LoginRateLimitService::class, fn(Container $c) => new LoginRateLimitService(
        $c->get(LoginIntentoRepositoryInterface::class),
        (int) Config::get('auth.login.max_intentos', 5),
        (int) Config::get('auth.login.ventana_min', 15)
    ));
```

Actualizar el binding de `AuthController` (~línea 288) para inyectar rate limit en `LoginUseCase`:

```php
            new LoginUseCase(
                $authService,
                new LoginValidator(),
                $c->get(LoginRateLimitService::class)
            ),
```

- [ ] **Step 3: Documentar variables en `.env.example`**

Al final del archivo (junto a otras vars de auth), agregar:

```
# Rate limiting login (fallos por IP o email en ventana)
LOGIN_MAX_INTENTOS=5
LOGIN_VENTANA_MIN=15
```

- [ ] **Step 4: Verificar sintaxis**

Run:
```bash
php -l config/auth.php
php -l config/container.php
```
Expected: sin errores de sintaxis.

- [ ] **Step 5: Commit**

```bash
git add config/auth.php config/container.php .env.example
git commit -m "feat(auth): configuracion y DI para rate limiting de login"
```

---

### Task 7: Documentación, spec y verificación final

**Files:**
- Modify: `docs/core/auth_rbac_seguridad_v0.1.md` (§11 Login)
- Modify: `docs/superpowers/specs/2026-06-12-login-intentos-limite-design.md` (estado → Aprobado)

- [ ] **Step 1: Actualizar checklist de seguridad**

En `docs/core/auth_rbac_seguridad_v0.1.md`, sección **§11 Login**, agregar después de la línea de SQL injection:

```markdown
- Tras 5 intentos fallidos (mismo IP o mismo email en 15 min, configurable vía `LOGIN_MAX_INTENTOS` / `LOGIN_VENTANA_MIN`), el siguiente intento responde `Credenciales incorrectas.` sin autenticar (mensaje idéntico a password incorrecto).
- Login exitoso resetea contadores de esa IP y email (`auth_login_intentos`).
- Verificar en `storage/logs/app-*.log` que bloqueos generan entrada `WARNING` con IP (sin email en claro).
```

- [ ] **Step 2: Marcar spec como aprobado**

En `docs/superpowers/specs/2026-06-12-login-intentos-limite-design.md`, cambiar línea 4:

```markdown
**Estado:** Aprobado (brainstorming + plan 2026-06-12)
```

- [ ] **Step 3: Correr suite de tests de auth**

Run:
```bash
php tests/run.php Auth/
```
Expected: todos los tests bajo `tests/Auth/` en PASS (incluye los 3 archivos nuevos).

- [ ] **Step 4: Correr suite completa (regresión)**

Run:
```bash
./vendor/bin/phpunit
```
Expected: misma cantidad de fallos preexistentes que antes (5 conocidos no relacionados); **ningún fallo nuevo** en auth/login.

- [ ] **Step 5: Smoke manual (checklist)**

1. `php -S localhost:8000 -t public`
2. Intentar login 5 veces con password incorrecta → la 6.ª muestra el mismo flash de error.
3. Login correcto → entra al dashboard.
4. Tras login correcto, intentos fallidos vuelven a contar desde cero.
5. Revisar `storage/logs/app-YYYY-MM-DD.log` tras bloqueo: línea `WARNING` con `Login bloqueado por rate limit`.

- [ ] **Step 6: Commit**

```bash
git add docs/core/auth_rbac_seguridad_v0.1.md docs/superpowers/specs/2026-06-12-login-intentos-limite-design.md
git commit -m "docs(auth): checklist de rate limiting de login y spec aprobado"
```

---

## Self-review (cobertura del spec)

| Requisito spec | Task |
|----------------|------|
| §2 Opción A dual IP+email | Task 2, 5 |
| §2 Sin lockout de cuenta | Task 2 (solo tabla temporal) |
| §2 Mensaje genérico en bloqueo | Task 2 (`Credenciales incorrectas.`) |
| §2 Cuenta inactiva sin cambio de mensaje | Task 5 (re-lanza `AuthException` original) |
| §3 `config/auth.php` + `.env.example` | Task 6 |
| §4 Tabla + migración + schema | Task 3 |
| §4 Repositorio interfaz + PDO | Task 1, 4 |
| §5 `LoginRateLimitService` + logging | Task 2 (`AppLogger::warning`) |
| §5 `LoginDTO` / `LoginUseCase` / `AuthController` | Task 5 |
| §6 Sin cambios de vista | N/A (solo controller) |
| §7 Tests servicio + caso de uso + fake | Tasks 1, 2, 5 |
| §10 Criterios de aceptación 1-6 | Tasks 2-7 |
| §8 OWASP documentado | Task 7 (checklist seguridad) |

**Placeholder scan:** sin TBD ni pasos vagos; cada task incluye código y comandos.

---

**Plan complete and saved to `docs/superpowers/plans/2026-06-12-login-intentos-limite.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
