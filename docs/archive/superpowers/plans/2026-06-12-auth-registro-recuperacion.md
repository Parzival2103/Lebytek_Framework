# Auth: Registro público, recuperación de contraseña y vistas de auth — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar registro público con verificación de correo, recuperación de contraseña por token y las vistas de auth correspondientes, según el spec `docs/superpowers/specs/2026-06-12-auth-registro-recuperacion-login-design.md`.

**Architecture:** Tabla única `auth_tokens` (token hasheado sha256, un solo uso, TTL por tipo) + `MailerInterface` propia con drivers PHPMailer/log + 5 use cases en `app/Application/UseCases/Auth/` + 2 controllers públicos nuevos + shell visual común `partials/auth_card.php` extraído del login sin cambio visual. El `LoginUseCase` no se toca.

**Tech Stack:** PHP 8.1+, framework Lebytek propio (sin Laravel/Symfony), PHPMailer vía composer, MariaDB/MySQL, harness de tests propio (`php tests/run.php`, microtest, sin BD).

---

## Contexto esencial del codebase (leer antes de empezar)

- **Tests:** se corren con `php tests/run.php` (NO existe phpunit instalado). Filtro por substring de ruta: `php tests/run.php Auth`. Los tests son archivos `*Test.php` con funciones globales `test()`, `assert_true()`, `assert_same()`, `assert_null()`, `assert_throws()` de `tests/lib/microtest.php`. No hay clases de test. Al momento de escribir este plan la suite completa pasa con 294 tests.
- **Autoload:** custom (`app/Kernel/Autoloader.php`, PSR-4 `App\` → `app/`). El de composer se carga además si existe (`Bootstrap.php` línea 32).
- **Capas:** Domain no depende de nada; Application puede usar Kernel (precedente: `CrearUsuarioUseCase` usa `App\Kernel\Security\Hash`); Infrastructure implementa interfaces de Domain.
- **Convención de fechas en entidades nuevas:** strings `'Y-m-d H:i:s'` (patrón `Archivo`), inmutables con clones.
- **`BaseRepository`** (Kernel) da `query()`, `queryOne()`, `insert()` (devuelve lastInsertId), `execute()`, `findRowById()`.
- **Flashes/old input:** `Session::flash('error'|'errors'|'success', ...)`, `Session::flashInput()`, `ViewHelper::old()`, `Session::flashAll()`.
- **Logs:** `App\Kernel\Logging\AppLogger::info()/error()` → `storage/logs/app-YYYY-MM-DD.log`.
- **404:** `throw new \App\Kernel\Exceptions\HttpException('mensaje', 404)`.
- **Config:** `App\Kernel\Config\Config::get('archivo.clave.subclave', $default)` carga todos los `config/*.php`. `EnvLoader::get()` ya convierte `'true'/'false'` a bool.
- **Request:** `$request->input('token')` lee body **y** query string (sirve para `?token=...`).

---

### Task 1: Dependencia PHPMailer + archivos de configuración

**Files:**
- Modify: `composer.json`
- Create: `config/auth.php`
- Create: `config/mail.php`
- Modify: `.env.example`

- [ ] **Step 1: Instalar PHPMailer**

```bash
composer require phpmailer/phpmailer
```

Expected: agrega `"phpmailer/phpmailer": "^6.x"` a `composer.json` y vendor instalado. Si composer no está disponible en PATH, probar `php composer.phar require phpmailer/phpmailer` o instalar composer primero; no continuar sin esto.

- [ ] **Step 2: Crear `config/auth.php`**

```php
<?php

use App\Kernel\EnvLoader;

return [
    'registro' => [
        // Registro público apagado por defecto; se enciende por .env.
        'habilitado'  => (bool) EnvLoader::get('REGISTRO_HABILITADO', false),
        'rol_default' => 'usuario',
    ],
    'tokens' => [
        'recuperacion_ttl_min' => 60,
        'verificacion_ttl_min' => 1440,
        'max_por_hora'         => 3,
    ],
];
```

- [ ] **Step 3: Crear `config/mail.php`**

```php
<?php

use App\Kernel\EnvLoader;

return [
    'driver'       => EnvLoader::get('MAIL_DRIVER', 'log'),
    'host'         => EnvLoader::get('MAIL_HOST', ''),
    'port'         => (int) EnvLoader::get('MAIL_PORT', 587),
    'username'     => (string) EnvLoader::get('MAIL_USERNAME', ''),
    'password'     => (string) EnvLoader::get('MAIL_PASSWORD', ''),
    'from_address' => EnvLoader::get('MAIL_FROM_ADDRESS', 'noreply@localhost'),
    'from_name'    => EnvLoader::get('MAIL_FROM_NAME', 'Sistema Administrativo'),
];
```

- [ ] **Step 4: Actualizar `.env.example`**

En el bloque MAIL existente (líneas 40–46), cambiar la línea `MAIL_DRIVER=smtp` por:

```env
# MAIL_DRIVER: smtp | log (log escribe el correo a storage/logs — usar en desarrollo)
MAIL_DRIVER=log
```

Y agregar después del bloque MAIL:

```env
# Registro público de usuarios (requiere correo verificado). Apagado por defecto.
REGISTRO_HABILITADO=false
```

- [ ] **Step 5: Verificar que la config carga**

```bash
php -r "define('ROOT_PATH', __DIR__); require 'app/Kernel/Autoloader.php'; App\Kernel\EnvLoader::load('.env'); var_dump(require 'config/auth.php', require 'config/mail.php');"
```

Expected: dos arrays sin errores, `registro.habilitado` => `bool(false)`.

- [ ] **Step 6: Correr la suite (regresión)**

```bash
php tests/run.php
```

Expected: misma cantidad de PASS que antes (294), 0 failed.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock config/auth.php config/mail.php .env.example
git commit -m "feat(auth): config de registro/tokens/mail y dependencia phpmailer"
```

---

### Task 2: Base de datos — `auth_tokens`, `email_verificado_en`, rol `usuario`

**Files:**
- Modify: `database/schema/schema.sql`
- Create: `database/migrations/20260612120000_auth_registro_recuperacion.sql`
- Modify: `config/modules/core.php`

- [ ] **Step 1: Agregar columna al baseline (`database/schema/schema.sql`)**

En el `CREATE TABLE IF NOT EXISTS \`auth_usuarios\`` (línea ~17), después de la línea de `ultimo_acceso`:

```sql
  `ultimo_acceso` DATETIME         DEFAULT NULL,
  `email_verificado_en` DATETIME   DEFAULT NULL,
```

- [ ] **Step 2: Agregar tabla `auth_tokens` al baseline**

En `schema.sql`, inmediatamente después del bloque `CREATE TABLE` de `auth_usuarios_roles` (último `auth_*`), insertar:

```sql
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED    NOT NULL,
  `tipo`       VARCHAR(30)     NOT NULL,
  `token_hash` CHAR(64)        NOT NULL,
  `expira_en`  DATETIME        NOT NULL,
  `usado_en`   DATETIME        DEFAULT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tokens_usuario_tipo` (`usuario_id`, `tipo`),
  INDEX `idx_tokens_hash` (`token_hash`),
  CONSTRAINT `fk_tokens_usuario`
      FOREIGN KEY (`usuario_id`) REFERENCES `auth_usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 3: Sembrar rol `usuario` en el baseline**

En la sección `DATOS INICIALES` de `schema.sql` (línea ~284), agregar el rol al INSERT existente:

```sql
INSERT IGNORE INTO `auth_roles` (`nombre`, `slug`, `descripcion`) VALUES
  ('Administrador', 'administrador', 'Acceso total al sistema'),
  ('Operador', 'operador', 'Acceso al dashboard (extender al añadir dominio)'),
  ('Soporte', 'soporte', 'Rol de ejemplo mínimo hasta definir módulos'),
  ('Usuario', 'usuario', 'Usuario registrado desde el formulario público');
```

Y en el grant de `dashboard.ver` (línea ~295), ampliar el `IN`:

```sql
WHERE `r`.`slug` IN ('operador', 'soporte', 'usuario');
```

- [ ] **Step 4: Crear migración incremental `database/migrations/20260612120000_auth_registro_recuperacion.sql`**

(Reglas del README de migraciones: idempotente, `IF NOT EXISTS`, sin `SELECT` de verificación al final.)

```sql
-- Registro público y recuperación de contraseña (spec 2026-06-12).
-- Tabla de tokens multi-propósito + verificación de email + rol 'usuario'.
-- Idempotente: se puede re-ejecutar en cada despliegue.
SET NAMES utf8mb4;

ALTER TABLE `auth_usuarios` ADD COLUMN IF NOT EXISTS `email_verificado_en` DATETIME DEFAULT NULL AFTER `ultimo_acceso`;

CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED    NOT NULL,
  `tipo`       VARCHAR(30)     NOT NULL,
  `token_hash` CHAR(64)        NOT NULL,
  `expira_en`  DATETIME        NOT NULL,
  `usado_en`   DATETIME        DEFAULT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tokens_usuario_tipo` (`usuario_id`, `tipo`),
  INDEX `idx_tokens_hash` (`token_hash`),
  CONSTRAINT `fk_tokens_usuario`
      FOREIGN KEY (`usuario_id`) REFERENCES `auth_usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `auth_roles` (`nombre`, `slug`, `descripcion`) VALUES
  ('Usuario', 'usuario', 'Usuario registrado desde el formulario público');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` = 'dashboard.ver'
WHERE `r`.`slug` = 'usuario';
```

- [ ] **Step 5: Declarar la migración en el manifiesto core**

En `config/modules/core.php`, cambiar:

```php
    'migraciones' => [],
```

por:

```php
    'migraciones' => [
        '20260612120000_auth_registro_recuperacion.sql',
    ],
```

- [ ] **Step 6: Correr la suite (los tests de Install validan manifiestos/migraciones)**

```bash
php tests/run.php
```

Expected: 0 failed. Si un test de `tests/Install/` falla por la migración nueva, leer su mensaje: valida convenciones (nombre, declaración única en manifiestos) — corregir el archivo o manifiesto, no el test.

- [ ] **Step 7: (Opcional, si hay BD local configurada en `.env`) aplicar el seed**

```bash
php scripts/seed.php
```

Expected: termina sin errores; `auth_tokens` existe.

- [ ] **Step 8: Commit**

```bash
git add database/schema/schema.sql database/migrations/20260612120000_auth_registro_recuperacion.sql config/modules/core.php
git commit -m "feat(auth): tabla auth_tokens, email_verificado_en y rol usuario (baseline + migracion)"
```

---

### Task 3: Entidad de dominio `AuthToken` (TDD)

**Files:**
- Create: `app/Domain/Entities/AuthToken.php`
- Test: `tests/Auth/AuthTokenEntityTest.php`

- [ ] **Step 1: Escribir el test que falla — `tests/Auth/AuthTokenEntityTest.php`**

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\AuthToken;

test('AuthToken: desdeFila hidrata todos los campos', function (): void {
    $token = AuthToken::desdeFila([
        'id'         => 5,
        'usuario_id' => 9,
        'tipo'       => 'recuperacion',
        'token_hash' => str_repeat('a', 64),
        'expira_en'  => '2030-01-01 00:00:00',
        'usado_en'   => null,
        'created_at' => '2026-06-12 10:00:00',
    ]);

    assert_same(5, $token->id());
    assert_same(9, $token->usuarioId());
    assert_same('recuperacion', $token->tipo());
    assert_same(str_repeat('a', 64), $token->tokenHash());
    assert_same('2030-01-01 00:00:00', $token->expiraEn());
    assert_null($token->usadoEn());
    assert_same('2026-06-12 10:00:00', $token->createdAt());
});

test('AuthToken: vigente si no está usado y no ha expirado', function (): void {
    $token = new AuthToken(
        usuarioId: 1,
        tipo:      AuthToken::TIPO_VERIFICACION,
        tokenHash: str_repeat('b', 64),
        expiraEn:  date('Y-m-d H:i:s', time() + 3600)
    );
    assert_true($token->estaVigente());
});

test('AuthToken: no vigente si expiró', function (): void {
    $token = new AuthToken(
        usuarioId: 1,
        tipo:      AuthToken::TIPO_VERIFICACION,
        tokenHash: str_repeat('b', 64),
        expiraEn:  date('Y-m-d H:i:s', time() - 60)
    );
    assert_true(!$token->estaVigente(), 'token expirado no debe estar vigente');
});

test('AuthToken: no vigente si ya fue usado; marcarUsado es clon inmutable', function (): void {
    $token = new AuthToken(
        usuarioId: 1,
        tipo:      AuthToken::TIPO_RECUPERACION,
        tokenHash: str_repeat('c', 64),
        expiraEn:  date('Y-m-d H:i:s', time() + 3600)
    );

    $usado = $token->marcarUsado('2026-06-12 11:00:00');

    assert_true($token->estaVigente(), 'el original no debe mutar');
    assert_true(!$usado->estaVigente(), 'el clon usado no está vigente');
    assert_same('2026-06-12 11:00:00', $usado->usadoEn());
});
```

- [ ] **Step 2: Correr y verificar que falla**

```bash
php tests/run.php Auth
```

Expected: FAIL con `Class "App\Domain\Entities\AuthToken" not found` (o similar).

- [ ] **Step 3: Implementar `app/Domain/Entities/AuthToken.php`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entities;

/*
|--------------------------------------------------------------------------
| AuthToken — Entidad de dominio
|--------------------------------------------------------------------------
| Token de un solo uso para verificación de correo y recuperación de
| contraseña (auth_tokens). Solo persiste el hash sha256 del token.
| Entidad pura e inmutable: no depende de SQL ni HTTP.
*/

final class AuthToken
{
    public const TIPO_RECUPERACION = 'recuperacion';
    public const TIPO_VERIFICACION = 'verificacion';

    public function __construct(
        private int     $usuarioId,
        private string  $tipo,
        private string  $tokenHash,
        private string  $expiraEn,
        private ?string $usadoEn = null,
        private ?string $createdAt = null,
        private ?int    $id = null
    ) {
    }

    public static function desdeFila(array $row): self
    {
        return new self(
            usuarioId: (int) $row['usuario_id'],
            tipo:      (string) $row['tipo'],
            tokenHash: (string) $row['token_hash'],
            expiraEn:  (string) $row['expira_en'],
            usadoEn:   isset($row['usado_en']) ? (string) $row['usado_en'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            id:        isset($row['id']) ? (int) $row['id'] : null
        );
    }

    // ── Comportamiento ─────────────────────────────────────────────────────────

    public function estaVigente(?string $ahora = null): bool
    {
        $ahora ??= date('Y-m-d H:i:s');
        return $this->usadoEn === null && $this->expiraEn > $ahora;
    }

    public function marcarUsado(?string $momento = null): self
    {
        $clone          = clone $this;
        $clone->usadoEn = $momento ?? date('Y-m-d H:i:s');
        return $clone;
    }

    // ── Getters ────────────────────────────────────────────────────────────────

    public function id(): ?int           { return $this->id;        }
    public function usuarioId(): int     { return $this->usuarioId; }
    public function tipo(): string       { return $this->tipo;      }
    public function tokenHash(): string  { return $this->tokenHash; }
    public function expiraEn(): string   { return $this->expiraEn;  }
    public function usadoEn(): ?string   { return $this->usadoEn;   }
    public function createdAt(): ?string { return $this->createdAt; }
}
```

- [ ] **Step 4: Correr y verificar que pasa**

```bash
php tests/run.php Auth
```

Expected: 4 PASS, 0 failed.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Entities/AuthToken.php tests/Auth/AuthTokenEntityTest.php
git commit -m "feat(auth): entidad AuthToken inmutable con vigencia y un solo uso"
```

---

### Task 4: `AuthTokenRepositoryInterface` + fake en memoria + test de contrato

**Files:**
- Create: `app/Domain/Interfaces/AuthTokenRepositoryInterface.php`
- Create: `tests/fixtures/auth_fakes.php`
- Test: `tests/Auth/AuthTokenRepositoryContractTest.php`

- [ ] **Step 1: Escribir el test de contrato que falla — `tests/Auth/AuthTokenRepositoryContractTest.php`**

(Patrón de `tests/Archivos/ArchivoRepositoryContractTest.php`: el contrato se prueba sobre el fake.)

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\AuthToken;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

function token_nuevo(array $overrides = []): AuthToken
{
    return AuthToken::desdeFila(array_merge([
        'usuario_id' => 1,
        'tipo'       => AuthToken::TIPO_RECUPERACION,
        'token_hash' => hash('sha256', 'token-claro'),
        'expira_en'  => date('Y-m-d H:i:s', time() + 3600),
    ], $overrides));
}

test('Contrato: buscarVigentePorHash encuentra solo tokens vigentes del tipo pedido', function (): void {
    $repo = new FakeAuthTokenRepository();
    $repo->guardar(token_nuevo());

    $hash = hash('sha256', 'token-claro');
    assert_true($repo->buscarVigentePorHash($hash, AuthToken::TIPO_RECUPERACION) !== null);
    assert_null($repo->buscarVigentePorHash($hash, AuthToken::TIPO_VERIFICACION), 'otro tipo no debe matchear');
    assert_null($repo->buscarVigentePorHash(hash('sha256', 'otro'), AuthToken::TIPO_RECUPERACION));
});

test('Contrato: un token expirado no es vigente', function (): void {
    $repo = new FakeAuthTokenRepository();
    $repo->guardar(token_nuevo(['expira_en' => date('Y-m-d H:i:s', time() - 60)]));

    assert_null($repo->buscarVigentePorHash(hash('sha256', 'token-claro'), AuthToken::TIPO_RECUPERACION));
});

test('Contrato: marcarUsado consume el token (deja de ser vigente)', function (): void {
    $repo = new FakeAuthTokenRepository();
    $id   = $repo->guardar(token_nuevo());

    $repo->marcarUsado($id);

    assert_null($repo->buscarVigentePorHash(hash('sha256', 'token-claro'), AuthToken::TIPO_RECUPERACION));
});

test('Contrato: invalidarDeUsuario invalida solo el mismo usuario+tipo', function (): void {
    $repo = new FakeAuthTokenRepository();
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'a')]));
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'b'), 'tipo' => AuthToken::TIPO_VERIFICACION]));
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'c'), 'usuario_id' => 2]));

    $repo->invalidarDeUsuario(1, AuthToken::TIPO_RECUPERACION);

    assert_null($repo->buscarVigentePorHash(hash('sha256', 'a'), AuthToken::TIPO_RECUPERACION));
    assert_true($repo->buscarVigentePorHash(hash('sha256', 'b'), AuthToken::TIPO_VERIFICACION) !== null, 'otro tipo sigue vigente');
    assert_true($repo->buscarVigentePorHash(hash('sha256', 'c'), AuthToken::TIPO_RECUPERACION) !== null, 'otro usuario sigue vigente');
});

test('Contrato: contarRecientes cuenta emisiones por usuario+tipo dentro de la ventana', function (): void {
    $repo = new FakeAuthTokenRepository();
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'a')]));
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'b')]));
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'c'), 'tipo' => AuthToken::TIPO_VERIFICACION]));
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'd'), 'created_at' => date('Y-m-d H:i:s', time() - 7200)]));

    assert_same(2, $repo->contarRecientes(1, AuthToken::TIPO_RECUPERACION, 60));
    assert_same(1, $repo->contarRecientes(1, AuthToken::TIPO_VERIFICACION, 60));
    assert_same(0, $repo->contarRecientes(2, AuthToken::TIPO_RECUPERACION, 60));
});
```

- [ ] **Step 2: Correr y verificar que falla**

```bash
php tests/run.php Auth
```

Expected: FAIL — `auth_fakes.php` no existe / `FakeAuthTokenRepository` not found.

- [ ] **Step 3: Crear la interface `app/Domain/Interfaces/AuthTokenRepositoryInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\Entities\AuthToken;

/*
|--------------------------------------------------------------------------
| AuthTokenRepositoryInterface — Contrato de persistencia de AuthToken
|--------------------------------------------------------------------------
*/

interface AuthTokenRepositoryInterface
{
    public function guardar(AuthToken $token): int;

    /** Busca un token no usado y no expirado por su hash sha256 y tipo. */
    public function buscarVigentePorHash(string $hash, string $tipo): ?AuthToken;

    public function marcarUsado(int $id): void;

    /** Invalida (marca usados) todos los tokens vigentes del usuario para ese tipo. */
    public function invalidarDeUsuario(int $usuarioId, string $tipo): void;

    /** Cuenta tokens emitidos al usuario para ese tipo en los últimos N minutos. */
    public function contarRecientes(int $usuarioId, string $tipo, int $minutos): int;
}
```

- [ ] **Step 4: Crear `tests/fixtures/auth_fakes.php`**

**Nota:** en este task el archivo contiene SOLO `FakeAuthTokenRepository` y `FakeRolRepository` (este último lo usan las tasks 9–11). `FakeMailer` se agrega a este mismo archivo en Task 6, cuando ya exista `MailerInterface`.

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\AuthToken;
use App\Domain\Entities\Rol;
use App\Domain\Interfaces\AuthTokenRepositoryInterface;
use App\Domain\Interfaces\RolRepositoryInterface;
use App\Domain\ValueObjects\Slug;

require_once __DIR__ . '/avatar_fakes.php'; // FakeUsuarioRepository + fake_usuario()

if (!class_exists('FakeAuthTokenRepository')) {
    /** Repositorio de tokens en memoria; replica el contrato del repo PDO. */
    class FakeAuthTokenRepository implements AuthTokenRepositoryInterface
    {
        /** @var array<int, AuthToken> */
        public array $tokens = [];
        private int $nextId = 1;

        public function guardar(AuthToken $token): int
        {
            $id = $this->nextId++;
            $this->tokens[$id] = AuthToken::desdeFila([
                'id'         => $id,
                'usuario_id' => $token->usuarioId(),
                'tipo'       => $token->tipo(),
                'token_hash' => $token->tokenHash(),
                'expira_en'  => $token->expiraEn(),
                'usado_en'   => $token->usadoEn(),
                'created_at' => $token->createdAt() ?? date('Y-m-d H:i:s'),
            ]);
            return $id;
        }

        public function buscarVigentePorHash(string $hash, string $tipo): ?AuthToken
        {
            foreach ($this->tokens as $token) {
                if ($token->tokenHash() === $hash && $token->tipo() === $tipo && $token->estaVigente()) {
                    return $token;
                }
            }
            return null;
        }

        public function marcarUsado(int $id): void
        {
            if (isset($this->tokens[$id])) {
                $this->tokens[$id] = $this->tokens[$id]->marcarUsado();
            }
        }

        public function invalidarDeUsuario(int $usuarioId, string $tipo): void
        {
            foreach ($this->tokens as $id => $token) {
                if ($token->usuarioId() === $usuarioId && $token->tipo() === $tipo && $token->usadoEn() === null) {
                    $this->tokens[$id] = $token->marcarUsado();
                }
            }
        }

        public function contarRecientes(int $usuarioId, string $tipo, int $minutos): int
        {
            $desde = date('Y-m-d H:i:s', time() - $minutos * 60);
            $n = 0;
            foreach ($this->tokens as $token) {
                if ($token->usuarioId() === $usuarioId
                    && $token->tipo() === $tipo
                    && ($token->createdAt() ?? '') >= $desde) {
                    $n++;
                }
            }
            return $n;
        }
    }
}

if (!class_exists('FakeRolRepository')) {
    /** Repositorio de roles en memoria; registra asignaciones para asserts. */
    class FakeRolRepository implements RolRepositoryInterface
    {
        /** @var array<string, Rol> indexado por slug */
        public array $roles = [];
        /** @var list<array{0:int,1:int}> pares (usuarioId, rolId) asignados */
        public array $asignaciones = [];

        public function conRol(string $slug, int $id, string $nombre = 'Rol'): self
        {
            $this->roles[$slug] = new Rol($nombre, new Slug($slug), '', true, $id);
            return $this;
        }

        public function findById(int $id): ?Rol
        {
            foreach ($this->roles as $rol) {
                if ($rol->id() === $id) {
                    return $rol;
                }
            }
            return null;
        }

        public function findBySlug(string $slug): ?Rol
        {
            return $this->roles[$slug] ?? null;
        }

        public function findAll(): array
        {
            return array_values($this->roles);
        }

        public function save(Rol $rol): int { return 0; }
        public function update(Rol $rol): void {}
        public function delete(int $id): void {}
        public function buscarPorUsuarioId(int $usuarioId): array { return []; }

        public function asignarRolAUsuario(int $usuarioId, int $rolId): void
        {
            $this->asignaciones[] = [$usuarioId, $rolId];
        }

        public function revocarRolDeUsuario(int $usuarioId, int $rolId): void {}
        public function sincronizarRolesDeUsuario(int $usuarioId, array $rolIds): void {}
    }
}
```

(Verificar la firma exacta de `Slug::__construct` en `app/Domain/ValueObjects/Slug.php` antes de usarla; si valida formato, `'usuario'` es un slug válido.)

- [ ] **Step 5: Correr y verificar que pasa**

```bash
php tests/run.php Auth
```

Expected: tests de entidad + 5 de contrato PASS, 0 failed.

- [ ] **Step 6: Suite completa y commit**

```bash
php tests/run.php
git add app/Domain/Interfaces/AuthTokenRepositoryInterface.php tests/fixtures/auth_fakes.php tests/Auth/AuthTokenRepositoryContractTest.php
git commit -m "feat(auth): contrato AuthTokenRepository + fakes en memoria con test de contrato"
```

---

### Task 5: `Usuario.emailVerificadoEn` + `marcarEmailVerificado` en el repo

**Decisión de diseño:** la escritura de la verificación va en un método dedicado del repositorio (`marcarEmailVerificado`), igual que el patrón existente `actualizarAvatar`. El `UPDATE` genérico de `UsuarioRepository::update()` NO toca la columna nueva — así `ActualizarPerfilUseCase` y `ActualizarUsuarioUseCase` (que reconstruyen el `Usuario` sin ese campo) no pueden borrarla por accidente.

**Files:**
- Modify: `app/Domain/Entities/Usuario.php`
- Modify: `app/Domain/Interfaces/UsuarioRepositoryInterface.php`
- Modify: `app/Infrastructure/Repositories/UsuarioRepository.php`
- Modify: `tests/fixtures/avatar_fakes.php` (el fake debe implementar el método nuevo)
- Test: `tests/Auth/UsuarioEmailVerificadoTest.php`

- [ ] **Step 1: Escribir el test que falla — `tests/Auth/UsuarioEmailVerificadoTest.php`**

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\Usuario;
use App\Domain\ValueObjects\Email;

test('Usuario: emailVerificadoEn es null por defecto y se expone por getter/toArray', function (): void {
    $usuario = new Usuario(
        nombre:       'Ana',
        apellido:     'Lopez',
        email:        new Email('ana@test.local'),
        passwordHash: 'hash'
    );
    assert_null($usuario->emailVerificadoEn());
    assert_null($usuario->toArray()['email_verificado_en']);
});

test('Usuario: acepta emailVerificadoEn en el constructor', function (): void {
    $momento = new \DateTimeImmutable('2026-06-12 10:00:00');
    $usuario = new Usuario(
        nombre:            'Ana',
        apellido:          'Lopez',
        email:             new Email('ana@test.local'),
        passwordHash:      'hash',
        emailVerificadoEn: $momento
    );
    assert_same('2026-06-12 10:00:00', $usuario->emailVerificadoEn()?->format('Y-m-d H:i:s'));
    assert_same('2026-06-12 10:00:00', $usuario->toArray()['email_verificado_en']);
});
```

- [ ] **Step 2: Correr y verificar que falla**

```bash
php tests/run.php Auth
```

Expected: FAIL — `Unknown named parameter $emailVerificadoEn` / método `emailVerificadoEn` no existe.

- [ ] **Step 3: Modificar `app/Domain/Entities/Usuario.php`**

Agregar la propiedad (junto a `$ultimoAcceso`):

```php
    private ?\DateTimeImmutable $emailVerificadoEn;
```

Agregar el parámetro al constructor **antes de `$id`** (todas las construcciones del codebase usan argumentos nombrados — verificado: `CrearUsuarioUseCase`, `ActualizarPerfilUseCase`, `UsuarioRepository::hydrate`, `fake_usuario`):

```php
        ?\DateTimeImmutable $ultimoAcceso = null,
        ?\DateTimeImmutable $creadoEn     = null,
        ?\DateTimeImmutable $emailVerificadoEn = null,
        ?int    $id              = null
```

Y en el cuerpo del constructor:

```php
        $this->emailVerificadoEn = $emailVerificadoEn;
```

Getter (junto a los demás):

```php
    public function emailVerificadoEn(): ?\DateTimeImmutable { return $this->emailVerificadoEn; }
```

En `toArray()`, después de `'ultimo_acceso'`:

```php
            'email_verificado_en' => $this->emailVerificadoEn?->format('Y-m-d H:i:s'),
```

- [ ] **Step 4: Correr y verificar que pasa**

```bash
php tests/run.php Auth
```

Expected: PASS.

- [ ] **Step 5: Agregar `marcarEmailVerificado` al contrato e implementaciones**

En `app/Domain/Interfaces/UsuarioRepositoryInterface.php`, después de `actualizarAvatar`:

```php
    /** Activa la cuenta y registra el momento de verificación del correo. */
    public function marcarEmailVerificado(int $usuarioId): void;
```

En `app/Infrastructure/Repositories/UsuarioRepository.php`, después de `actualizarAvatar()`:

```php
    public function marcarEmailVerificado(int $usuarioId): void
    {
        $this->execute(
            "UPDATE auth_usuarios SET activo = 1, email_verificado_en = NOW(), updated_at = NOW() WHERE id = ?",
            [$usuarioId]
        );
    }
```

Y en `hydrate()` del mismo archivo, agregar el argumento nombrado (antes de `creadoEn:`):

```php
            emailVerificadoEn: !empty($row['email_verificado_en'])
                ? new \DateTimeImmutable($row['email_verificado_en'])
                : null,
```

En `tests/fixtures/avatar_fakes.php`, dentro de `FakeUsuarioRepository` (después de `actualizarAvatar`):

```php
        /** @var list<int> usuarios verificados vía marcarEmailVerificado */
        public array $verificados = [];

        public function marcarEmailVerificado(int $usuarioId): void
        {
            $this->verificados[] = $usuarioId;
            if (isset($this->usuarios[$usuarioId])) {
                $this->usuarios[$usuarioId] = $this->usuarios[$usuarioId]->activar();
            }
        }
```

(La propiedad `$verificados` va junto a las otras propiedades públicas de la clase, no dentro de un método.)

- [ ] **Step 6: Correr la suite completa (los tests de avatares usan el fake y la interface)**

```bash
php tests/run.php
```

Expected: 0 failed.

- [ ] **Step 7: Commit**

```bash
git add app/Domain/Entities/Usuario.php app/Domain/Interfaces/UsuarioRepositoryInterface.php app/Infrastructure/Repositories/UsuarioRepository.php tests/fixtures/avatar_fakes.php tests/Auth/UsuarioEmailVerificadoTest.php
git commit -m "feat(auth): email_verificado_en en Usuario y marcarEmailVerificado en repositorio"
```

---

### Task 6: `MailerInterface`, DTO `MensajeCorreo`, `LogMailer` y `FakeMailer`

**Files:**
- Create: `app/Domain/Interfaces/MailerInterface.php`
- Create: `app/Application/DTO/Mail/MensajeCorreo.php`
- Create: `app/Infrastructure/Mail/LogMailer.php`
- Modify: `tests/fixtures/auth_fakes.php` (agregar `FakeMailer`)

(Sin test propio: `LogMailer` solo escribe al log; el contrato se ejercita vía `FakeMailer` en los use cases. Esto sigue el spec §9.)

- [ ] **Step 1: Crear `app/Application/DTO/Mail/MensajeCorreo.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\DTO\Mail;

/*
|--------------------------------------------------------------------------
| MensajeCorreo — DTO de correo saliente
|--------------------------------------------------------------------------
*/

final class MensajeCorreo
{
    public function __construct(
        public readonly string $destinatario,
        public readonly string $nombreDestinatario,
        public readonly string $asunto,
        public readonly string $html
    ) {
    }
}
```

- [ ] **Step 2: Crear `app/Domain/Interfaces/MailerInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\DTO\Mail\MensajeCorreo;

/*
|--------------------------------------------------------------------------
| MailerInterface — Contrato de envío de correo
|--------------------------------------------------------------------------
| Implementaciones en app/Infrastructure/Mail/ (smtp real y log de dev).
| Nota de capas: el DTO vive en Application por decisión del spec
| 2026-06-12 (§5), igual que el resto del patrón del framework.
*/

interface MailerInterface
{
    /** @throws \Throwable si el transporte falla */
    public function enviar(MensajeCorreo $mensaje): void;
}
```

- [ ] **Step 3: Crear `app/Infrastructure/Mail/LogMailer.php`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\DTO\Mail\MensajeCorreo;
use App\Domain\Interfaces\MailerInterface;
use App\Kernel\Logging\AppLogger;

/*
|--------------------------------------------------------------------------
| LogMailer — Driver de correo para desarrollo
|--------------------------------------------------------------------------
| No envía nada: escribe el correo completo a storage/logs/app-*.log
| para poder copiar la URL de verificación/recuperación en dev.
*/

final class LogMailer implements MailerInterface
{
    public function enviar(MensajeCorreo $mensaje): void
    {
        AppLogger::info('[mail:log] Correo simulado', [
            'para'   => $mensaje->destinatario,
            'nombre' => $mensaje->nombreDestinatario,
            'asunto' => $mensaje->asunto,
            'html'   => $mensaje->html,
        ]);
    }
}
```

- [ ] **Step 4: Agregar `FakeMailer` a `tests/fixtures/auth_fakes.php`**

Al final del archivo, agregar:

```php
if (!class_exists('FakeMailer')) {
    /** Mailer espía: acumula los mensajes enviados; puede simular fallo. */
    class FakeMailer implements \App\Domain\Interfaces\MailerInterface
    {
        /** @var list<\App\Application\DTO\Mail\MensajeCorreo> */
        public array $enviados = [];
        public ?\Throwable $falla = null;

        public function enviar(\App\Application\DTO\Mail\MensajeCorreo $mensaje): void
        {
            if ($this->falla !== null) {
                throw $this->falla;
            }
            $this->enviados[] = $mensaje;
        }
    }
}
```

- [ ] **Step 5: Suite completa y commit**

```bash
php tests/run.php
git add app/Domain/Interfaces/MailerInterface.php app/Application/DTO/Mail/MensajeCorreo.php app/Infrastructure/Mail/LogMailer.php tests/fixtures/auth_fakes.php
git commit -m "feat(mail): MailerInterface, DTO MensajeCorreo y driver LogMailer"
```

---

### Task 7: `AuthTokenService` — emisión con throttle e invalidación (TDD)

**Files:**
- Create: `app/Application/Services/AuthTokenService.php`
- Test: `tests/Auth/AuthTokenServiceTest.php`

- [ ] **Step 1: Escribir el test que falla — `tests/Auth/AuthTokenServiceTest.php`**

```php
<?php

declare(strict_types=1);

use App\Application\Services\AuthTokenService;
use App\Domain\Entities\AuthToken;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

test('AuthTokenService: emitir devuelve token claro de 64 hex y persiste solo el hash', function (): void {
    $repo    = new FakeAuthTokenRepository();
    $service = new AuthTokenService($repo, 3);

    $token = $service->emitir(7, AuthToken::TIPO_VERIFICACION, 1440);

    assert_true($token !== null);
    assert_same(64, strlen($token));
    assert_true(ctype_xdigit($token));
    assert_same(1, count($repo->tokens));
    $guardado = array_values($repo->tokens)[0];
    assert_same(hash('sha256', $token), $guardado->tokenHash());
    assert_same(7, $guardado->usuarioId());
    assert_same(AuthToken::TIPO_VERIFICACION, $guardado->tipo());
    assert_true($guardado->expiraEn() > date('Y-m-d H:i:s'), 'debe expirar en el futuro');
});

test('AuthTokenService: emitir invalida los tokens previos del mismo usuario+tipo', function (): void {
    $repo    = new FakeAuthTokenRepository();
    $service = new AuthTokenService($repo, 3);

    $token1 = $service->emitir(7, AuthToken::TIPO_RECUPERACION, 60);
    $token2 = $service->emitir(7, AuthToken::TIPO_RECUPERACION, 60);

    assert_null($repo->buscarVigentePorHash(hash('sha256', $token1), AuthToken::TIPO_RECUPERACION), 'el previo queda invalidado');
    assert_true($repo->buscarVigentePorHash(hash('sha256', $token2), AuthToken::TIPO_RECUPERACION) !== null);
});

test('AuthTokenService: la emisión que excede el máximo por hora devuelve null y no persiste', function (): void {
    $repo    = new FakeAuthTokenRepository();
    $service = new AuthTokenService($repo, 3);

    assert_true($service->emitir(7, AuthToken::TIPO_RECUPERACION, 60) !== null);
    assert_true($service->emitir(7, AuthToken::TIPO_RECUPERACION, 60) !== null);
    assert_true($service->emitir(7, AuthToken::TIPO_RECUPERACION, 60) !== null);

    assert_null($service->emitir(7, AuthToken::TIPO_RECUPERACION, 60), '4ª emisión en la hora debe throttlearse');
    assert_same(3, count($repo->tokens));
});

test('AuthTokenService: el throttle es independiente por tipo', function (): void {
    $repo    = new FakeAuthTokenRepository();
    $service = new AuthTokenService($repo, 1);

    assert_true($service->emitir(7, AuthToken::TIPO_RECUPERACION, 60) !== null);
    assert_null($service->emitir(7, AuthToken::TIPO_RECUPERACION, 60));
    assert_true($service->emitir(7, AuthToken::TIPO_VERIFICACION, 60) !== null, 'otro tipo no comparte throttle');
});
```

- [ ] **Step 2: Correr y verificar que falla**

```bash
php tests/run.php Auth
```

Expected: FAIL — `AuthTokenService` not found.

- [ ] **Step 3: Implementar `app/Application/Services/AuthTokenService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\AuthToken;
use App\Domain\Interfaces\AuthTokenRepositoryInterface;

/*
|--------------------------------------------------------------------------
| AuthTokenService — Emisión de tokens de un solo uso
|--------------------------------------------------------------------------
| Centraliza la política del spec §8: 32 bytes aleatorios, solo el hash
| sha256 en BD, invalidación de previos y throttle por usuario+tipo.
*/

final class AuthTokenService
{
    public function __construct(
        private readonly AuthTokenRepositoryInterface $tokenRepo,
        private readonly int $maxPorHora = 3
    ) {
    }

    /**
     * Emite un token nuevo y devuelve el valor EN CLARO para la URL.
     * Devuelve null si el usuario excedió el máximo de emisiones por hora
     * (el caller responde genérico, sin enviar nada).
     */
    public function emitir(int $usuarioId, string $tipo, int $ttlMinutos): ?string
    {
        if ($this->tokenRepo->contarRecientes($usuarioId, $tipo, 60) >= $this->maxPorHora) {
            return null;
        }

        $this->tokenRepo->invalidarDeUsuario($usuarioId, $tipo);

        $token = bin2hex(random_bytes(32));

        $this->tokenRepo->guardar(new AuthToken(
            usuarioId: $usuarioId,
            tipo:      $tipo,
            tokenHash: hash('sha256', $token),
            expiraEn:  date('Y-m-d H:i:s', time() + $ttlMinutos * 60)
        ));

        return $token;
    }
}
```

- [ ] **Step 4: Correr y verificar que pasa**

```bash
php tests/run.php Auth
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Services/AuthTokenService.php tests/Auth/AuthTokenServiceTest.php
git commit -m "feat(auth): AuthTokenService con hash sha256, invalidacion de previos y throttle"
```

---

### Task 8: Plantillas de correo + `CorreoAuthService` (TDD)

**Files:**
- Create: `app/Presentation/Views/emails/verificacion.php`
- Create: `app/Presentation/Views/emails/recuperacion.php`
- Create: `app/Application/Services/CorreoAuthService.php`
- Test: `tests/Auth/CorreoAuthServiceTest.php`

- [ ] **Step 1: Escribir el test que falla — `tests/Auth/CorreoAuthServiceTest.php`**

```php
<?php

declare(strict_types=1);

use App\Application\Services\CorreoAuthService;
use App\Domain\Exceptions\ValidationException;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

test('CorreoAuthService: verificación envía 1 correo con URL absoluta y token', function (): void {
    $mailer  = new FakeMailer();
    $service = new CorreoAuthService($mailer, 'https://app.test/');

    $service->enviarVerificacion(fake_usuario(1, 'ana@test.local'), 'tok-verif-123');

    assert_same(1, count($mailer->enviados));
    $msg = $mailer->enviados[0];
    assert_same('ana@test.local', $msg->destinatario);
    assert_true(str_contains($msg->html, 'https://app.test/registro/verificar?token=tok-verif-123'));
});

test('CorreoAuthService: recuperación envía 1 correo con URL de restablecer', function (): void {
    $mailer  = new FakeMailer();
    $service = new CorreoAuthService($mailer, 'https://app.test');

    $service->enviarRecuperacion(fake_usuario(1, 'ana@test.local'), 'tok-rec-456');

    assert_same(1, count($mailer->enviados));
    assert_true(str_contains($mailer->enviados[0]->html, 'https://app.test/restablecer?token=tok-rec-456'));
});

test('CorreoAuthService: fallo del transporte se traduce a ValidationException genérica', function (): void {
    $mailer        = new FakeMailer();
    $mailer->falla = new \RuntimeException('SMTP connect() failed con credenciales x');
    $service       = new CorreoAuthService($mailer, 'https://app.test');

    assert_throws(ValidationException::class, function () use ($service): void {
        $service->enviarVerificacion(fake_usuario(1), 'tok');
    });

    try {
        $service->enviarVerificacion(fake_usuario(1), 'tok');
    } catch (ValidationException $e) {
        assert_true(!str_contains($e->getMessage(), 'SMTP'), 'no debe filtrar detalles del transporte');
    }
});
```

- [ ] **Step 2: Correr y verificar que falla**

```bash
php tests/run.php Auth
```

Expected: FAIL — `CorreoAuthService` not found.

- [ ] **Step 3: Crear `app/Presentation/Views/emails/verificacion.php`**

```php
<?php
use App\Kernel\Helpers\ViewHelper;

/** @var string $nombre */
/** @var string $url */
/** @var string $empresaNombre */
$empresaNombre = $empresaNombre ?? 'Sistema Administrativo';
?>
<!DOCTYPE html>
<html lang="es">
<body style="margin:0; padding:24px; background:#f0f2f5; font-family: Arial, Helvetica, sans-serif; color:#212529;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; padding:32px;">
                <tr><td>
                    <h2 style="margin:0 0 16px;"><?= ViewHelper::e($empresaNombre) ?></h2>
                    <p style="margin:0 0 8px;">Hola <?= ViewHelper::e($nombre) ?>,</p>
                    <p style="margin:0 0 24px;">Gracias por registrarte. Confirma tu correo para activar tu cuenta:</p>
                    <p style="margin:0 0 24px;">
                        <a href="<?= ViewHelper::e($url) ?>"
                           style="background:#0d6efd; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; display:inline-block;">
                            Verificar mi correo
                        </a>
                    </p>
                    <p style="margin:0 0 8px; font-size:13px; color:#6c757d;">
                        Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                        <a href="<?= ViewHelper::e($url) ?>"><?= ViewHelper::e($url) ?></a>
                    </p>
                    <p style="margin:16px 0 0; font-size:13px; color:#6c757d;">
                        Si no creaste esta cuenta, ignora este mensaje.
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
```

- [ ] **Step 4: Crear `app/Presentation/Views/emails/recuperacion.php`**

```php
<?php
use App\Kernel\Helpers\ViewHelper;

/** @var string $nombre */
/** @var string $url */
/** @var string $empresaNombre */
$empresaNombre = $empresaNombre ?? 'Sistema Administrativo';
?>
<!DOCTYPE html>
<html lang="es">
<body style="margin:0; padding:24px; background:#f0f2f5; font-family: Arial, Helvetica, sans-serif; color:#212529;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; padding:32px;">
                <tr><td>
                    <h2 style="margin:0 0 16px;"><?= ViewHelper::e($empresaNombre) ?></h2>
                    <p style="margin:0 0 8px;">Hola <?= ViewHelper::e($nombre) ?>,</p>
                    <p style="margin:0 0 24px;">Recibimos una solicitud para restablecer tu contraseña:</p>
                    <p style="margin:0 0 24px;">
                        <a href="<?= ViewHelper::e($url) ?>"
                           style="background:#0d6efd; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; display:inline-block;">
                            Restablecer contraseña
                        </a>
                    </p>
                    <p style="margin:0 0 8px; font-size:13px; color:#6c757d;">
                        Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                        <a href="<?= ViewHelper::e($url) ?>"><?= ViewHelper::e($url) ?></a>
                    </p>
                    <p style="margin:16px 0 0; font-size:13px; color:#6c757d;">
                        Si no solicitaste este cambio, ignora este mensaje; tu contraseña actual sigue siendo válida.
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
```

- [ ] **Step 5: Implementar `app/Application/Services/CorreoAuthService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Mail\MensajeCorreo;
use App\Domain\Entities\Usuario;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\MailerInterface;
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Logging\AppLogger;

/*
|--------------------------------------------------------------------------
| CorreoAuthService — Correos de verificación y recuperación
|--------------------------------------------------------------------------
| Arma la URL absoluta con el token en claro, renderiza la plantilla
| (Views/emails/) y delega al MailerInterface. Un fallo del transporte
| se loguea y se traduce a un mensaje genérico (spec §5).
*/

final class CorreoAuthService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $baseUrl
    ) {
    }

    public function enviarVerificacion(Usuario $usuario, string $token): void
    {
        $url  = $this->url('/registro/verificar', $token);
        $html = ViewHelper::render('emails/verificacion', [
            'nombre' => $usuario->nombre(),
            'url'    => $url,
        ], '');

        $this->enviar(new MensajeCorreo(
            destinatario:       (string) $usuario->email(),
            nombreDestinatario: $usuario->nombreCompleto(),
            asunto:             'Confirma tu correo',
            html:               $html
        ));
    }

    public function enviarRecuperacion(Usuario $usuario, string $token): void
    {
        $url  = $this->url('/restablecer', $token);
        $html = ViewHelper::render('emails/recuperacion', [
            'nombre' => $usuario->nombre(),
            'url'    => $url,
        ], '');

        $this->enviar(new MensajeCorreo(
            destinatario:       (string) $usuario->email(),
            nombreDestinatario: $usuario->nombreCompleto(),
            asunto:             'Restablece tu contraseña',
            html:               $html
        ));
    }

    private function url(string $path, string $token): string
    {
        return rtrim($this->baseUrl, '/') . $path . '?token=' . rawurlencode($token);
    }

    private function enviar(MensajeCorreo $mensaje): void
    {
        try {
            $this->mailer->enviar($mensaje);
        } catch (\Throwable $e) {
            AppLogger::error('[mail] Fallo al enviar correo de auth', [
                'para'   => $mensaje->destinatario,
                'asunto' => $mensaje->asunto,
                'error'  => $e->getMessage(),
            ]);
            throw new ValidationException('No fue posible enviar el correo. Intenta más tarde.');
        }
    }
}
```

- [ ] **Step 6: Correr y verificar que pasa**

```bash
php tests/run.php Auth
```

Expected: PASS (las plantillas se renderizan de verdad — `APP_PATH` está definido en el bootstrap de tests).

- [ ] **Step 7: Commit**

```bash
git add app/Presentation/Views/emails app/Application/Services/CorreoAuthService.php tests/Auth/CorreoAuthServiceTest.php
git commit -m "feat(auth): CorreoAuthService y plantillas de verificacion/recuperacion"
```

---

### Task 9: `RegistrarUsuarioUseCase` (TDD)

**Files:**
- Create: `app/Application/DTO/Auth/RegistroDTO.php`
- Create: `app/Application/UseCases/Auth/RegistrarUsuarioUseCase.php`
- Test: `tests/Auth/RegistroUseCaseTest.php`

- [ ] **Step 1: Escribir el test que falla — `tests/Auth/RegistroUseCaseTest.php`**

```php
<?php

declare(strict_types=1);

use App\Application\DTO\Auth\RegistroDTO;
use App\Application\Services\AuthTokenService;
use App\Application\Services\CorreoAuthService;
use App\Application\UseCases\Auth\RegistrarUsuarioUseCase;
use App\Application\Validators\Usuarios\CrearUsuarioValidator;
use App\Domain\Entities\AuthToken;
use App\Domain\Exceptions\ValidationException;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

function registro_armar(array $overrides = []): array
{
    $usuarioRepo = new FakeUsuarioRepository();
    $rolRepo     = (new FakeRolRepository())->conRol('usuario', 7, 'Usuario');
    $tokenRepo   = new FakeAuthTokenRepository();
    $mailer      = new FakeMailer();

    $useCase = new RegistrarUsuarioUseCase(
        usuarioRepo:        $usuarioRepo,
        rolRepo:            $rolRepo,
        validator:          new CrearUsuarioValidator(),
        tokens:             new AuthTokenService($tokenRepo, 3),
        correo:             new CorreoAuthService($mailer, 'https://app.test'),
        habilitado:         $overrides['habilitado'] ?? true,
        rolDefault:         'usuario',
        verificacionTtlMin: 1440
    );

    return [$useCase, $usuarioRepo, $rolRepo, $tokenRepo, $mailer];
}

function registro_dto(array $overrides = []): RegistroDTO
{
    return new RegistroDTO(
        nombre:               $overrides['nombre'] ?? 'Ana',
        apellido:             $overrides['apellido'] ?? 'Lopez',
        email:                $overrides['email'] ?? 'ana@test.local',
        password:             $overrides['password'] ?? 'secreto123',
        passwordConfirmacion: $overrides['passwordConfirmacion'] ?? 'secreto123'
    );
}

test('Registro: crea usuario inactivo con rol default, 1 token de verificación y 1 correo', function (): void {
    [$useCase, $usuarioRepo, $rolRepo, $tokenRepo, $mailer] = registro_armar();

    $useCase->execute(registro_dto());

    assert_same(1, count($usuarioRepo->usuarios));
    $usuario = array_values($usuarioRepo->usuarios)[0];
    assert_true(!$usuario->activo(), 'el registrado nace inactivo hasta verificar');
    assert_same('ana@test.local', (string) $usuario->email());

    assert_same([[1, 7]], $rolRepo->asignaciones, 'rol default asignado al id nuevo');

    assert_same(1, count($tokenRepo->tokens));
    assert_same(AuthToken::TIPO_VERIFICACION, array_values($tokenRepo->tokens)[0]->tipo());

    assert_same(1, count($mailer->enviados));
    assert_same('ana@test.local', $mailer->enviados[0]->destinatario);
});

test('Registro: email duplicado lanza ValidationException y no envía nada', function (): void {
    [$useCase, $usuarioRepo, , $tokenRepo, $mailer] = registro_armar();
    $usuarioRepo->emailsExistentes[] = 'ana@test.local';

    assert_throws(ValidationException::class, fn() => $useCase->execute(registro_dto()));
    assert_same(0, count($tokenRepo->tokens));
    assert_same(0, count($mailer->enviados));
});

test('Registro: deshabilitado lanza ValidationException', function (): void {
    [$useCase] = registro_armar(['habilitado' => false]);

    assert_throws(ValidationException::class, fn() => $useCase->execute(registro_dto()));
});

test('Registro: confirmación de contraseña distinta lanza ValidationException', function (): void {
    [$useCase, $usuarioRepo] = registro_armar();

    assert_throws(ValidationException::class, fn() => $useCase->execute(
        registro_dto(['passwordConfirmacion' => 'otra-cosa'])
    ));
    assert_same(0, count($usuarioRepo->usuarios));
});

test('Registro: password corto lanza ValidationException (reglas de CrearUsuarioValidator)', function (): void {
    [$useCase] = registro_armar();

    assert_throws(ValidationException::class, fn() => $useCase->execute(
        registro_dto(['password' => 'corto', 'passwordConfirmacion' => 'corto'])
    ));
});
```

- [ ] **Step 2: Correr y verificar que falla**

```bash
php tests/run.php Auth
```

Expected: FAIL — `RegistroDTO` / `RegistrarUsuarioUseCase` not found.

- [ ] **Step 3: Crear `app/Application/DTO/Auth/RegistroDTO.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\DTO\Auth;

final class RegistroDTO
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $apellido,
        public readonly string $email,
        public readonly string $password,
        public readonly string $passwordConfirmacion
    ) {
    }
}
```

- [ ] **Step 4: Crear `app/Application/UseCases/Auth/RegistrarUsuarioUseCase.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCases\Auth;

use App\Application\DTO\Auth\RegistroDTO;
use App\Application\Services\AuthTokenService;
use App\Application\Services\CorreoAuthService;
use App\Application\Validators\Usuarios\CrearUsuarioValidator;
use App\Domain\Entities\AuthToken;
use App\Domain\Entities\Usuario;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\RolRepositoryInterface;
use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Domain\ValueObjects\Email;
use App\Kernel\Security\Hash;

/*
|--------------------------------------------------------------------------
| RegistrarUsuarioUseCase — Registro público con verificación de correo
|--------------------------------------------------------------------------
| Crea el usuario inactivo con el rol default, emite token de verificación
| y envía el correo. El usuario no puede iniciar sesión hasta verificar.
*/

final class RegistrarUsuarioUseCase
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepo,
        private readonly RolRepositoryInterface     $rolRepo,
        private readonly CrearUsuarioValidator      $validator,
        private readonly AuthTokenService           $tokens,
        private readonly CorreoAuthService          $correo,
        private readonly bool                       $habilitado,
        private readonly string                     $rolDefault,
        private readonly int                        $verificacionTtlMin
    ) {
    }

    public function execute(RegistroDTO $dto): void
    {
        if (!$this->habilitado) {
            throw new ValidationException('El registro no está disponible.');
        }

        $this->validator->validate([
            'nombre'   => $dto->nombre,
            'apellido' => $dto->apellido,
            'email'    => $dto->email,
            'password' => $dto->password,
        ]);

        if ($dto->password !== $dto->passwordConfirmacion) {
            throw new ValidationException('Los datos del registro son inválidos.', [
                'password_confirmacion' => 'Las contraseñas no coinciden.',
            ]);
        }

        $email = new Email($dto->email);

        if ($this->usuarioRepo->emailExists($email)) {
            throw new ValidationException('El correo ya está registrado.', [
                'email' => 'Este correo ya existe.',
            ]);
        }

        $usuario = new Usuario(
            nombre:       $dto->nombre,
            apellido:     $dto->apellido,
            email:        $email,
            passwordHash: Hash::make($dto->password),
            activo:       false
        );

        $id = $this->usuarioRepo->save($usuario);

        $rol = $this->rolRepo->findBySlug($this->rolDefault);
        if ($rol !== null && $rol->id() !== null) {
            $this->rolRepo->asignarRolAUsuario($id, $rol->id());
        }

        $token = $this->tokens->emitir($id, AuthToken::TIPO_VERIFICACION, $this->verificacionTtlMin);
        if ($token !== null) {
            $this->correo->enviarVerificacion($usuario, $token);
        }
    }
}
```

- [ ] **Step 5: Correr y verificar que pasa**

```bash
php tests/run.php Auth
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Application/DTO/Auth/RegistroDTO.php app/Application/UseCases/Auth/RegistrarUsuarioUseCase.php tests/Auth/RegistroUseCaseTest.php
git commit -m "feat(auth): RegistrarUsuarioUseCase con usuario inactivo, rol default y verificacion"
```

---

### Task 10: `VerificarCorreoUseCase` + `ReenviarVerificacionUseCase` (TDD)

**Files:**
- Create: `app/Application/UseCases/Auth/VerificarCorreoUseCase.php`
- Create: `app/Application/UseCases/Auth/ReenviarVerificacionUseCase.php`
- Test: `tests/Auth/VerificacionUseCaseTest.php`

- [ ] **Step 1: Escribir el test que falla — `tests/Auth/VerificacionUseCaseTest.php`**

```php
<?php

declare(strict_types=1);

use App\Application\Services\AuthTokenService;
use App\Application\Services\CorreoAuthService;
use App\Application\UseCases\Auth\ReenviarVerificacionUseCase;
use App\Application\UseCases\Auth\VerificarCorreoUseCase;
use App\Domain\Entities\AuthToken;
use App\Domain\Entities\Usuario;
use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\Email;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

function usuario_sin_verificar(int $id, string $email = 'ana@test.local'): Usuario
{
    return new Usuario(
        nombre:       'Ana',
        apellido:     'Lopez',
        email:        new Email($email),
        passwordHash: 'hash',
        activo:       false,
        id:           $id
    );
}

function token_verificacion_guardado(FakeAuthTokenRepository $repo, int $usuarioId, string $claro, array $overrides = []): int
{
    return $repo->guardar(AuthToken::desdeFila(array_merge([
        'usuario_id' => $usuarioId,
        'tipo'       => AuthToken::TIPO_VERIFICACION,
        'token_hash' => hash('sha256', $claro),
        'expira_en'  => date('Y-m-d H:i:s', time() + 3600),
    ], $overrides)));
}

test('Verificar: token vigente activa la cuenta, marca verificado y consume el token', function (): void {
    $usuarioRepo = new FakeUsuarioRepository();
    $tokenRepo   = new FakeAuthTokenRepository();
    $usuarioRepo->usuarios[5] = usuario_sin_verificar(5);
    token_verificacion_guardado($tokenRepo, 5, 'tok-claro');

    $useCase = new VerificarCorreoUseCase($tokenRepo, $usuarioRepo);
    $useCase->execute('tok-claro');

    assert_same([5], $usuarioRepo->verificados, 'marcarEmailVerificado del usuario 5');
    assert_null($tokenRepo->buscarVigentePorHash(hash('sha256', 'tok-claro'), AuthToken::TIPO_VERIFICACION), 'token consumido');
});

test('Verificar: token inexistente, vencido, usado o de otro tipo lanza ValidationException', function (): void {
    $usuarioRepo = new FakeUsuarioRepository();
    $tokenRepo   = new FakeAuthTokenRepository();
    $usuarioRepo->usuarios[5] = usuario_sin_verificar(5);

    $useCase = new VerificarCorreoUseCase($tokenRepo, $usuarioRepo);

    assert_throws(ValidationException::class, fn() => $useCase->execute('no-existe'), 'inexistente');

    token_verificacion_guardado($tokenRepo, 5, 'vencido', ['expira_en' => date('Y-m-d H:i:s', time() - 60)]);
    assert_throws(ValidationException::class, fn() => $useCase->execute('vencido'), 'vencido');

    $idUsado = token_verificacion_guardado($tokenRepo, 5, 'usado');
    $tokenRepo->marcarUsado($idUsado);
    assert_throws(ValidationException::class, fn() => $useCase->execute('usado'), 'usado');

    token_verificacion_guardado($tokenRepo, 5, 'otro-tipo', ['tipo' => AuthToken::TIPO_RECUPERACION]);
    assert_throws(ValidationException::class, fn() => $useCase->execute('otro-tipo'), 'tipo recuperacion no verifica correo');

    assert_same([], $usuarioRepo->verificados, 'nadie quedó verificado');
});

function reenviar_armar(): array
{
    $usuarioRepo = new FakeUsuarioRepository();
    $tokenRepo   = new FakeAuthTokenRepository();
    $mailer      = new FakeMailer();
    $useCase     = new ReenviarVerificacionUseCase(
        usuarioRepo:        $usuarioRepo,
        tokens:             new AuthTokenService($tokenRepo, 3),
        correo:             new CorreoAuthService($mailer, 'https://app.test'),
        verificacionTtlMin: 1440
    );
    return [$useCase, $usuarioRepo, $tokenRepo, $mailer];
}

test('Reenviar: usuario pendiente recibe token nuevo y el previo queda invalidado', function (): void {
    [$useCase, $usuarioRepo, $tokenRepo, $mailer] = reenviar_armar();
    $usuarioRepo->usuarios[5] = usuario_sin_verificar(5);
    token_verificacion_guardado($tokenRepo, 5, 'previo');

    $useCase->execute('ana@test.local');

    assert_null($tokenRepo->buscarVigentePorHash(hash('sha256', 'previo'), AuthToken::TIPO_VERIFICACION), 'previo invalidado');
    assert_same(2, count($tokenRepo->tokens));
    assert_same(1, count($mailer->enviados));
});

test('Reenviar: email inexistente responde silencioso y no envía nada (anti-enumeración)', function (): void {
    [$useCase, , $tokenRepo, $mailer] = reenviar_armar();

    $useCase->execute('nadie@test.local');
    $useCase->execute('esto-no-es-un-email');

    assert_same(0, count($tokenRepo->tokens));
    assert_same(0, count($mailer->enviados));
});

test('Reenviar: usuario activo o ya verificado no recibe correo', function (): void {
    [$useCase, $usuarioRepo, $tokenRepo, $mailer] = reenviar_armar();
    $usuarioRepo->usuarios[1] = fake_usuario(1, 'activa@test.local'); // activo=true
    $usuarioRepo->usuarios[2] = new Usuario(
        nombre: 'Bea', apellido: 'Ruiz', email: new Email('verificada@test.local'),
        passwordHash: 'hash', activo: false,
        emailVerificadoEn: new \DateTimeImmutable('2026-01-01 00:00:00'), id: 2
    );

    $useCase->execute('activa@test.local');
    $useCase->execute('verificada@test.local');

    assert_same(0, count($tokenRepo->tokens));
    assert_same(0, count($mailer->enviados));
});
```

- [ ] **Step 2: Correr y verificar que falla**

```bash
php tests/run.php Auth
```

Expected: FAIL — use cases not found.

- [ ] **Step 3: Crear `app/Application/UseCases/Auth/VerificarCorreoUseCase.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCases\Auth;

use App\Domain\Entities\AuthToken;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\AuthTokenRepositoryInterface;
use App\Domain\Interfaces\UsuarioRepositoryInterface;

/*
|--------------------------------------------------------------------------
| VerificarCorreoUseCase — Consume el token y activa la cuenta
|--------------------------------------------------------------------------
| No inicia sesión (spec §8.5): el controller redirige a /login.
*/

final class VerificarCorreoUseCase
{
    private const MENSAJE_INVALIDO = 'El enlace de verificación no es válido o ya expiró.';

    public function __construct(
        private readonly AuthTokenRepositoryInterface $tokenRepo,
        private readonly UsuarioRepositoryInterface   $usuarioRepo
    ) {
    }

    public function execute(string $tokenClaro): void
    {
        $token = $this->tokenRepo->buscarVigentePorHash(
            hash('sha256', $tokenClaro),
            AuthToken::TIPO_VERIFICACION
        );

        if ($token === null || $token->id() === null) {
            throw new ValidationException(self::MENSAJE_INVALIDO);
        }

        $usuario = $this->usuarioRepo->findById($token->usuarioId());
        if ($usuario === null) {
            throw new ValidationException(self::MENSAJE_INVALIDO);
        }

        $this->tokenRepo->marcarUsado($token->id());
        $this->usuarioRepo->marcarEmailVerificado($token->usuarioId());
    }
}
```

- [ ] **Step 4: Crear `app/Application/UseCases/Auth/ReenviarVerificacionUseCase.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCases\Auth;

use App\Application\Services\AuthTokenService;
use App\Application\Services\CorreoAuthService;
use App\Domain\Entities\AuthToken;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Domain\ValueObjects\Email;

/*
|--------------------------------------------------------------------------
| ReenviarVerificacionUseCase — Reenvío anti-enumeración
|--------------------------------------------------------------------------
| Solo actúa para cuentas pendientes (inactivas y sin verificar). Para
| cualquier otro caso retorna en silencio: la respuesta observable es
| idéntica exista o no la cuenta (spec §8.2). El throttle vive en
| AuthTokenService (null => silencio).
*/

final class ReenviarVerificacionUseCase
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepo,
        private readonly AuthTokenService           $tokens,
        private readonly CorreoAuthService          $correo,
        private readonly int                        $verificacionTtlMin
    ) {
    }

    public function execute(string $email): void
    {
        try {
            $emailVO = new Email($email);
        } catch (ValidationException) {
            return;
        }

        $usuario = $this->usuarioRepo->findByEmail($emailVO);
        if ($usuario === null
            || $usuario->id() === null
            || $usuario->activo()
            || $usuario->emailVerificadoEn() !== null) {
            return;
        }

        $token = $this->tokens->emitir($usuario->id(), AuthToken::TIPO_VERIFICACION, $this->verificacionTtlMin);
        if ($token !== null) {
            $this->correo->enviarVerificacion($usuario, $token);
        }
    }
}
```

- [ ] **Step 5: Correr y verificar que pasa**

```bash
php tests/run.php Auth
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Application/UseCases/Auth/VerificarCorreoUseCase.php app/Application/UseCases/Auth/ReenviarVerificacionUseCase.php tests/Auth/VerificacionUseCaseTest.php
git commit -m "feat(auth): verificacion de correo y reenvio anti-enumeracion"
```

---

### Task 11: `SolicitarRecuperacionUseCase` + `RestablecerPasswordUseCase` (TDD)

**Files:**
- Create: `app/Application/UseCases/Auth/SolicitarRecuperacionUseCase.php`
- Create: `app/Application/UseCases/Auth/RestablecerPasswordUseCase.php`
- Test: `tests/Auth/RecuperacionUseCaseTest.php`

- [ ] **Step 1: Escribir el test que falla — `tests/Auth/RecuperacionUseCaseTest.php`**

```php
<?php

declare(strict_types=1);

use App\Application\Services\AuthTokenService;
use App\Application\Services\CorreoAuthService;
use App\Application\UseCases\Auth\RestablecerPasswordUseCase;
use App\Application\UseCases\Auth\SolicitarRecuperacionUseCase;
use App\Domain\Entities\AuthToken;
use App\Domain\Exceptions\ValidationException;
use App\Kernel\Security\Hash;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

function recuperacion_armar(): array
{
    $usuarioRepo = new FakeUsuarioRepository();
    $tokenRepo   = new FakeAuthTokenRepository();
    $mailer      = new FakeMailer();
    $solicitar   = new SolicitarRecuperacionUseCase(
        usuarioRepo:        $usuarioRepo,
        tokens:             new AuthTokenService($tokenRepo, 3),
        correo:             new CorreoAuthService($mailer, 'https://app.test'),
        recuperacionTtlMin: 60
    );
    $restablecer = new RestablecerPasswordUseCase($usuarioRepo, $tokenRepo);
    return [$solicitar, $restablecer, $usuarioRepo, $tokenRepo, $mailer];
}

test('Recuperar: email existente y activo genera token tipo recuperacion + 1 correo', function (): void {
    [$solicitar, , $usuarioRepo, $tokenRepo, $mailer] = recuperacion_armar();
    $usuarioRepo->usuarios[1] = fake_usuario(1, 'ana@test.local');

    $solicitar->execute('ana@test.local');

    assert_same(1, count($tokenRepo->tokens));
    assert_same(AuthToken::TIPO_RECUPERACION, array_values($tokenRepo->tokens)[0]->tipo());
    assert_same(1, count($mailer->enviados));
});

test('Recuperar: email inexistente o inactivo — mismo resultado externo y cero correos', function (): void {
    [$solicitar, , $usuarioRepo, $tokenRepo, $mailer] = recuperacion_armar();
    $inactiva = fake_usuario(2, 'inactiva@test.local')->desactivar();
    $usuarioRepo->usuarios[2] = $inactiva;

    $solicitar->execute('nadie@test.local');
    $solicitar->execute('inactiva@test.local');
    $solicitar->execute('no-es-email');

    assert_same(0, count($tokenRepo->tokens));
    assert_same(0, count($mailer->enviados));
});

test('Recuperar: la 4ª solicitud en la hora no envía correo (throttle silencioso)', function (): void {
    [$solicitar, , $usuarioRepo, , $mailer] = recuperacion_armar();
    $usuarioRepo->usuarios[1] = fake_usuario(1, 'ana@test.local');

    $solicitar->execute('ana@test.local');
    $solicitar->execute('ana@test.local');
    $solicitar->execute('ana@test.local');
    $solicitar->execute('ana@test.local');

    assert_same(3, count($mailer->enviados), 'solo 3 correos: la 4ª se throttlea sin error');
});

test('Restablecer: actualiza el hash y consume el token', function (): void {
    [, $restablecer, $usuarioRepo, $tokenRepo] = recuperacion_armar();
    $usuarioRepo->usuarios[1] = fake_usuario(1, 'ana@test.local');
    $tokenRepo->guardar(AuthToken::desdeFila([
        'usuario_id' => 1,
        'tipo'       => AuthToken::TIPO_RECUPERACION,
        'token_hash' => hash('sha256', 'tok-rec'),
        'expira_en'  => date('Y-m-d H:i:s', time() + 3600),
    ]));

    $restablecer->execute('tok-rec', 'nuevoPass123', 'nuevoPass123');

    assert_true($usuarioRepo->ultimoUpdate !== null);
    assert_true(Hash::verify('nuevoPass123', $usuarioRepo->ultimoUpdate->passwordHash()), 'hash nuevo verificable');
    assert_null($tokenRepo->buscarVigentePorHash(hash('sha256', 'tok-rec'), AuthToken::TIPO_RECUPERACION), 'token consumido');
});

test('Restablecer: token consumido no se reutiliza', function (): void {
    [, $restablecer, $usuarioRepo, $tokenRepo] = recuperacion_armar();
    $usuarioRepo->usuarios[1] = fake_usuario(1, 'ana@test.local');
    $tokenRepo->guardar(AuthToken::desdeFila([
        'usuario_id' => 1,
        'tipo'       => AuthToken::TIPO_RECUPERACION,
        'token_hash' => hash('sha256', 'tok-rec'),
        'expira_en'  => date('Y-m-d H:i:s', time() + 3600),
    ]));

    $restablecer->execute('tok-rec', 'nuevoPass123', 'nuevoPass123');

    assert_throws(ValidationException::class, fn() => $restablecer->execute('tok-rec', 'otroPass123', 'otroPass123'));
});

test('Restablecer: password inválido o sin coincidencia lanza ValidationException sin consumir token', function (): void {
    [, $restablecer, $usuarioRepo, $tokenRepo] = recuperacion_armar();
    $usuarioRepo->usuarios[1] = fake_usuario(1, 'ana@test.local');
    $tokenRepo->guardar(AuthToken::desdeFila([
        'usuario_id' => 1,
        'tipo'       => AuthToken::TIPO_RECUPERACION,
        'token_hash' => hash('sha256', 'tok-rec'),
        'expira_en'  => date('Y-m-d H:i:s', time() + 3600),
    ]));

    assert_throws(ValidationException::class, fn() => $restablecer->execute('tok-rec', 'corto', 'corto'));
    assert_throws(ValidationException::class, fn() => $restablecer->execute('tok-rec', 'nuevoPass123', 'distinto123'));

    assert_true(
        $tokenRepo->buscarVigentePorHash(hash('sha256', 'tok-rec'), AuthToken::TIPO_RECUPERACION) !== null,
        'el token sigue vigente tras fallos de validación'
    );
});
```

- [ ] **Step 2: Correr y verificar que falla**

```bash
php tests/run.php Auth
```

Expected: FAIL — use cases not found.

- [ ] **Step 3: Crear `app/Application/UseCases/Auth/SolicitarRecuperacionUseCase.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCases\Auth;

use App\Application\Services\AuthTokenService;
use App\Application\Services\CorreoAuthService;
use App\Domain\Entities\AuthToken;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Domain\ValueObjects\Email;

/*
|--------------------------------------------------------------------------
| SolicitarRecuperacionUseCase — Solicitud anti-enumeración
|--------------------------------------------------------------------------
| El resultado observable es idéntico exista o no el email (spec §8.2):
| siempre retorna void; solo envía correo si la cuenta existe y está
| activa. El throttle vive en AuthTokenService (null => silencio).
*/

final class SolicitarRecuperacionUseCase
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepo,
        private readonly AuthTokenService           $tokens,
        private readonly CorreoAuthService          $correo,
        private readonly int                        $recuperacionTtlMin
    ) {
    }

    public function execute(string $email): void
    {
        try {
            $emailVO = new Email($email);
        } catch (ValidationException) {
            return;
        }

        $usuario = $this->usuarioRepo->findByEmail($emailVO);
        if ($usuario === null || $usuario->id() === null || !$usuario->activo()) {
            return;
        }

        $token = $this->tokens->emitir($usuario->id(), AuthToken::TIPO_RECUPERACION, $this->recuperacionTtlMin);
        if ($token !== null) {
            $this->correo->enviarRecuperacion($usuario, $token);
        }
    }
}
```

- [ ] **Step 4: Crear `app/Application/UseCases/Auth/RestablecerPasswordUseCase.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCases\Auth;

use App\Domain\Entities\AuthToken;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\AuthTokenRepositoryInterface;
use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Kernel\Security\Hash;

/*
|--------------------------------------------------------------------------
| RestablecerPasswordUseCase — Consume el token y actualiza el hash
|--------------------------------------------------------------------------
| Valida primero el password (mismas reglas que CrearUsuarioValidator)
| para no consumir el token con datos inválidos.
*/

final class RestablecerPasswordUseCase
{
    private const MENSAJE_INVALIDO = 'El enlace de recuperación no es válido o ya expiró.';

    public function __construct(
        private readonly UsuarioRepositoryInterface   $usuarioRepo,
        private readonly AuthTokenRepositoryInterface $tokenRepo
    ) {
    }

    public function execute(string $tokenClaro, string $password, string $passwordConfirmacion): void
    {
        $errors = [];
        if (empty($password)) {
            $errors['password'] = 'La contraseña es obligatoria.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($password !== $passwordConfirmacion) {
            $errors['password_confirmacion'] = 'Las contraseñas no coinciden.';
        }
        if (!empty($errors)) {
            throw new ValidationException('Los datos son inválidos.', $errors);
        }

        $token = $this->tokenRepo->buscarVigentePorHash(
            hash('sha256', $tokenClaro),
            AuthToken::TIPO_RECUPERACION
        );

        if ($token === null || $token->id() === null) {
            throw new ValidationException(self::MENSAJE_INVALIDO);
        }

        $usuario = $this->usuarioRepo->findById($token->usuarioId());
        if ($usuario === null) {
            throw new ValidationException(self::MENSAJE_INVALIDO);
        }

        $this->tokenRepo->marcarUsado($token->id());
        $this->usuarioRepo->update($usuario->cambiarContrasena(Hash::make($password)));
    }
}
```

- [ ] **Step 5: Correr y verificar que pasa, y suite completa**

```bash
php tests/run.php Auth
php tests/run.php
```

Expected: PASS en ambos; 0 failed.

- [ ] **Step 6: Commit**

```bash
git add app/Application/UseCases/Auth/SolicitarRecuperacionUseCase.php app/Application/UseCases/Auth/RestablecerPasswordUseCase.php tests/Auth/RecuperacionUseCaseTest.php
git commit -m "feat(auth): solicitud de recuperacion anti-enumeracion y restablecer password"
```

---

### Task 12: Infraestructura — `AuthTokenRepository` (PDO) y `PhpMailerMailer`

(Sin unit tests: requieren BD/SMTP reales. El contrato del repo está cubierto por el test de contrato sobre el fake; el SQL replica exactamente esa semántica. Verificación: suite en verde + smoke manual del Task 16.)

**Files:**
- Create: `app/Infrastructure/Repositories/AuthTokenRepository.php`
- Create: `app/Infrastructure/Mail/PhpMailerMailer.php`

- [ ] **Step 1: Crear `app/Infrastructure/Repositories/AuthTokenRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\AuthToken;
use App\Domain\Interfaces\AuthTokenRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;

final class AuthTokenRepository extends BaseRepository implements AuthTokenRepositoryInterface
{
    protected string $table = 'auth_tokens';

    public function guardar(AuthToken $token): int
    {
        return $this->insert(
            "INSERT INTO auth_tokens (usuario_id, tipo, token_hash, expira_en, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [
                $token->usuarioId(),
                $token->tipo(),
                $token->tokenHash(),
                $token->expiraEn(),
            ]
        );
    }

    public function buscarVigentePorHash(string $hash, string $tipo): ?AuthToken
    {
        $row = $this->queryOne(
            "SELECT * FROM auth_tokens
             WHERE token_hash = ? AND tipo = ? AND usado_en IS NULL AND expira_en > NOW()
             LIMIT 1",
            [$hash, $tipo]
        );
        return $row ? AuthToken::desdeFila($row) : null;
    }

    public function marcarUsado(int $id): void
    {
        $this->execute(
            "UPDATE auth_tokens SET usado_en = NOW() WHERE id = ?",
            [$id]
        );
    }

    public function invalidarDeUsuario(int $usuarioId, string $tipo): void
    {
        $this->execute(
            "UPDATE auth_tokens SET usado_en = NOW()
             WHERE usuario_id = ? AND tipo = ? AND usado_en IS NULL",
            [$usuarioId, $tipo]
        );
    }

    public function contarRecientes(int $usuarioId, string $tipo, int $minutos): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS cnt FROM auth_tokens
             WHERE usuario_id = ? AND tipo = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$usuarioId, $tipo, $minutos]
        );
        return (int) ($row['cnt'] ?? 0);
    }
}
```

- [ ] **Step 2: Crear `app/Infrastructure/Mail/PhpMailerMailer.php`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\DTO\Mail\MensajeCorreo;
use App\Domain\Interfaces\MailerInterface;
use PHPMailer\PHPMailer\PHPMailer;

/*
|--------------------------------------------------------------------------
| PhpMailerMailer — Driver SMTP real (config/mail.php)
|--------------------------------------------------------------------------
| Lanza excepción si el transporte falla; el caller (CorreoAuthService)
| la loguea y la traduce a un mensaje genérico.
*/

final class PhpMailerMailer implements MailerInterface
{
    /** @param array{host:string,port:int,username:string,password:string,from_address:string,from_name:string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function enviar(MensajeCorreo $mensaje): void
    {
        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException('phpmailer/phpmailer no está instalado (ejecuta composer install).');
        }

        $mail = new PHPMailer(true); // true => lanza excepciones

        $mail->isSMTP();
        $mail->Host    = (string) $this->config['host'];
        $mail->Port    = (int) $this->config['port'];
        $mail->CharSet = 'UTF-8';

        if (($this->config['username'] ?? '') !== '') {
            $mail->SMTPAuth   = true;
            $mail->Username   = (string) $this->config['username'];
            $mail->Password   = (string) $this->config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom((string) $this->config['from_address'], (string) $this->config['from_name']);
        $mail->addAddress($mensaje->destinatario, $mensaje->nombreDestinatario);

        $mail->isHTML(true);
        $mail->Subject = $mensaje->asunto;
        $mail->Body    = $mensaje->html;
        $mail->AltBody = trim(strip_tags($mensaje->html));

        $mail->send();
    }
}
```

- [ ] **Step 3: Verificar sintaxis y suite**

```bash
php -l app/Infrastructure/Repositories/AuthTokenRepository.php
php -l app/Infrastructure/Mail/PhpMailerMailer.php
php tests/run.php
```

Expected: `No syntax errors detected` ×2; suite 0 failed.

- [ ] **Step 4: Commit**

```bash
git add app/Infrastructure/Repositories/AuthTokenRepository.php app/Infrastructure/Mail/PhpMailerMailer.php
git commit -m "feat(auth): AuthTokenRepository PDO y driver SMTP PhpMailerMailer"
```

---

### Task 13: Shell visual `auth_card` + refactor de `login.php` con enlaces

**Files:**
- Create: `app/Presentation/Views/partials/auth_card.php`
- Modify: `app/Presentation/Views/auth/login.php` (refactor completo)
- Modify: `app/Presentation/Controllers/AuthController.php` (pasar `registroHabilitado`)

**Objetivo:** misma apariencia píxel a píxel del login + dos enlaces nuevos. El shell contiene TODO el documento HTML; cada vista solo aporta el contenido del lado del formulario.

- [ ] **Step 1: Crear `app/Presentation/Views/partials/auth_card.php`**

El contenido es el `login.php` actual (líneas 1–172) con tres sustituciones. Copiar el archivo actual y aplicar:

1. **Encabezado PHP** — reemplazar las líneas 1–14 por:

```php
<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Security\Session;

/*
 * Shell visual común de las vistas de auth (login, registro, recuperación).
 * Recibe: pageTitle, contentHtml (lado del formulario), extraScripts (opcional)
 * y las variables de tema de LebytekUiConfig.
 */
$empresaNombre = $empresaNombre ?? 'Sistema Administrativo';
$primaryColor  = $primaryColor ?? '#0d6efd';
$darkMode      = $darkMode ?? false;
$empresaLogo   = $empresaLogo ?? '';
$flashAll      = $flashAll ?? Session::flashAll();
$pageTitle     = $pageTitle ?? 'Acceso';
$contentHtml   = $contentHtml ?? '';
$extraScripts  = $extraScripts ?? '';
$pwaBasePath   = ViewHelper::basePath();
$pwaManifestHref = ($pwaBasePath === '' ? '' : $pwaBasePath) . '/manifest.webmanifest';
$lebytekBody   = trim('ct-login-page ' . (string) ($lebytekBodyClasses ?? ''));
?>
```

2. **Título** (línea 23 original) — reemplazar:

```php
    <title><?= ViewHelper::e($pageTitle) ?> — <?= ViewHelper::e($empresaNombre) ?></title>
```

3. **Contenido del formulario** — reemplazar el bloque desde `<div class="mb-4 text-center text-md-start">` (línea 66) hasta `</form>` (línea 136) inclusive por:

```php
            <?= $contentHtml ?>
```

(Se conservan: el bloque de flashes de las líneas 71–81 **dentro del shell**, justo antes de `<?= $contentHtml ?>`; el footer `&copy;` de las líneas 138–140; los scripts de bootstrap/app.js de las líneas 145–146.)

4. **Scripts inline** — reemplazar el `<script>` inline de las líneas 147–170 por:

```php
<?= $extraScripts ?>
```

- [ ] **Step 2: Reescribir `app/Presentation/Views/auth/login.php`**

```php
<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Security\Csrf;
use App\Kernel\Security\Session;

// Captura las variables de tema que pasó el controller para reenviarlas al shell.
$__theme = get_defined_vars();

$flashAll           = $flashAll ?? Session::flashAll();
$registroHabilitado = !empty($registroHabilitado);

ob_start();
?>
<div class="mb-4 text-center text-md-start">
    <h3 class="fw-bold mb-1">Bienvenido</h3>
    <p class="text-muted small mb-0">Ingresa tus credenciales para continuar</p>
</div>

<form method="POST" action="/login" novalidate id="loginForm">
    <?= Csrf::field() ?>

    <div class="mb-3">
        <label for="email" class="form-label fw-medium small">Correo electrónico</label>
        <div class="input-group">
            <span class="input-group-text" aria-hidden="true"><i class="bi bi-envelope"></i></span>
            <input type="email"
                   id="email"
                   name="email"
                   class="form-control <?= Session::hasFlash('errors') || isset($flashAll['errors']['email']) ? 'is-invalid' : '' ?>"
                   placeholder="correo@empresa.com"
                   value="<?= ViewHelper::old('email') ?>"
                   autocomplete="email"
                   autofocus
                   required>
            <?php if (!empty($flashAll['errors']['email'])): ?>
                <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['email']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mb-4">
        <label for="password" class="form-label fw-medium small">Contraseña</label>
        <div class="input-group">
            <span class="input-group-text" aria-hidden="true"><i class="bi bi-lock"></i></span>
            <input type="password"
                   id="password"
                   name="password"
                   class="form-control <?= !empty($flashAll['errors']['password']) ? 'is-invalid' : '' ?>"
                   placeholder="Tu contraseña"
                   autocomplete="current-password"
                   required>
            <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Mostrar u ocultar contraseña">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
            <?php if (!empty($flashAll['errors']['password'])): ?>
                <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['password']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="recordar" id="recordar" value="1">
            <label class="form-check-label small" for="recordar">Recordarme</label>
        </div>
        <a href="/recuperar" class="small text-decoration-none">¿Olvidaste tu contraseña?</a>
    </div>

    <button type="submit" class="btn btn-primary w-100 fw-semibold" id="loginBtn">
        <span class="btn-text">Iniciar sesión</span>
        <span class="btn-spinner spinner-border spinner-border-sm ms-2 d-none" aria-hidden="true"></span>
    </button>

    <?php if ($registroHabilitado): ?>
        <p class="text-center small mt-3 mb-0">
            ¿No tienes cuenta? <a href="/registro" class="text-decoration-none">Crear cuenta</a>
        </p>
    <?php endif; ?>
</form>
<?php
$contentHtml = ob_get_clean();

ob_start();
?>
<script>
document.getElementById('togglePassword')?.addEventListener('click', function () {
    const input = document.getElementById('password');
    const icon  = this.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
        this.setAttribute('aria-label', 'Ocultar contraseña');
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
        this.setAttribute('aria-label', 'Mostrar contraseña');
    }
});

document.getElementById('loginForm')?.addEventListener('submit', function () {
    const btn     = document.getElementById('loginBtn');
    const spinner = btn.querySelector('.btn-spinner');
    const text    = btn.querySelector('.btn-text');
    btn.disabled  = true;
    spinner.classList.remove('d-none');
    text.textContent = 'Verificando...';
});
</script>
<?php
$extraScripts = ob_get_clean();

echo ViewHelper::partial('auth_card', $__theme + [
    'pageTitle'    => 'Iniciar sesión',
    'flashAll'     => $flashAll,
    'contentHtml'  => $contentHtml,
    'extraScripts' => $extraScripts,
]);
```

- [ ] **Step 3: Pasar `registroHabilitado` desde `AuthController::showLogin`**

En `app/Presentation/Controllers/AuthController.php`, agregar el import:

```php
use App\Kernel\Config\Config;
```

y cambiar la última línea de `showLogin()`:

```php
        return $this->view('auth/login', $theme + [
            'registroHabilitado' => (bool) Config::get('auth.registro.habilitado', false),
        ], '');
```

- [ ] **Step 4: Verificación visual manual**

```bash
php -S localhost:8000 -t public
```

Abrir `http://localhost:8000/login` y comparar contra el login previo (branding, card, flashes con credenciales malas, toggle de contraseña, spinner del submit). El único cambio visible: enlace "¿Olvidaste tu contraseña?" (y "Crear cuenta" solo si `REGISTRO_HABILITADO=true` en `.env`).

- [ ] **Step 5: Suite y commit**

```bash
php tests/run.php
git add app/Presentation/Views/partials/auth_card.php app/Presentation/Views/auth/login.php app/Presentation/Controllers/AuthController.php
git commit -m "refactor(auth): shell visual auth_card compartido y enlaces de registro/recuperacion en login"
```

---

### Task 14: Vistas nuevas de registro y recuperación

**Files:**
- Create: `app/Presentation/Views/auth/registro.php`
- Create: `app/Presentation/Views/auth/registro_enviado.php`
- Create: `app/Presentation/Views/auth/recuperar.php`
- Create: `app/Presentation/Views/auth/recuperar_enviado.php`
- Create: `app/Presentation/Views/auth/restablecer.php`

Todas siguen el mismo patrón del login refactorizado: capturar `$__theme = get_defined_vars()`, armar `$contentHtml` con `ob_start()`, delegar en `ViewHelper::partial('auth_card', ...)`.

- [ ] **Step 1: Crear `app/Presentation/Views/auth/registro.php`**

```php
<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Security\Csrf;
use App\Kernel\Security\Session;

$__theme  = get_defined_vars();
$flashAll = $flashAll ?? Session::flashAll();

ob_start();
?>
<div class="mb-4 text-center text-md-start">
    <h3 class="fw-bold mb-1">Crear cuenta</h3>
    <p class="text-muted small mb-0">Completa tus datos; te enviaremos un correo de verificación</p>
</div>

<form method="POST" action="/registro" novalidate>
    <?= Csrf::field() ?>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="nombre" class="form-label fw-medium small">Nombre</label>
            <input type="text" id="nombre" name="nombre"
                   class="form-control <?= !empty($flashAll['errors']['nombre']) ? 'is-invalid' : '' ?>"
                   value="<?= ViewHelper::old('nombre') ?>" autocomplete="given-name" required>
            <?php if (!empty($flashAll['errors']['nombre'])): ?>
                <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['nombre']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-6 mb-3">
            <label for="apellido" class="form-label fw-medium small">Apellido</label>
            <input type="text" id="apellido" name="apellido"
                   class="form-control <?= !empty($flashAll['errors']['apellido']) ? 'is-invalid' : '' ?>"
                   value="<?= ViewHelper::old('apellido') ?>" autocomplete="family-name" required>
            <?php if (!empty($flashAll['errors']['apellido'])): ?>
                <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['apellido']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label fw-medium small">Correo electrónico</label>
        <input type="email" id="email" name="email"
               class="form-control <?= !empty($flashAll['errors']['email']) ? 'is-invalid' : '' ?>"
               placeholder="correo@empresa.com"
               value="<?= ViewHelper::old('email') ?>" autocomplete="email" required>
        <?php if (!empty($flashAll['errors']['email'])): ?>
            <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['email']) ?></div>
        <?php endif; ?>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label fw-medium small">Contraseña</label>
        <input type="password" id="password" name="password"
               class="form-control <?= !empty($flashAll['errors']['password']) ? 'is-invalid' : '' ?>"
               placeholder="Mínimo 8 caracteres" autocomplete="new-password" required>
        <?php if (!empty($flashAll['errors']['password'])): ?>
            <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['password']) ?></div>
        <?php endif; ?>
    </div>

    <div class="mb-4">
        <label for="password_confirmacion" class="form-label fw-medium small">Confirmar contraseña</label>
        <input type="password" id="password_confirmacion" name="password_confirmacion"
               class="form-control <?= !empty($flashAll['errors']['password_confirmacion']) ? 'is-invalid' : '' ?>"
               placeholder="Repite la contraseña" autocomplete="new-password" required>
        <?php if (!empty($flashAll['errors']['password_confirmacion'])): ?>
            <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['password_confirmacion']) ?></div>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-100 fw-semibold">Crear cuenta</button>

    <p class="text-center small mt-3 mb-0">
        ¿Ya tienes cuenta? <a href="/login" class="text-decoration-none">Iniciar sesión</a>
    </p>
</form>
<?php
$contentHtml = ob_get_clean();

echo ViewHelper::partial('auth_card', $__theme + [
    'pageTitle'   => 'Crear cuenta',
    'flashAll'    => $flashAll,
    'contentHtml' => $contentHtml,
]);
```

- [ ] **Step 2: Crear `app/Presentation/Views/auth/registro_enviado.php`**

```php
<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Security\Csrf;
use App\Kernel\Security\Session;

$__theme  = get_defined_vars();
$flashAll = $flashAll ?? Session::flashAll();
$email    = (string) ($email ?? '');

ob_start();
?>
<div class="text-center">
    <div class="display-5 text-primary mb-3" aria-hidden="true"><i class="bi bi-envelope-check"></i></div>
    <h3 class="fw-bold mb-2">Revisa tu correo</h3>
    <p class="text-muted small mb-4">
        Enviamos un enlace de verificación<?= $email !== '' ? ' a <strong>' . ViewHelper::e($email) . '</strong>' : '' ?>.
        La cuenta se activa al confirmar el correo.
    </p>

    <?php if ($email !== ''): ?>
        <form method="POST" action="/registro/reenviar" class="mb-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="email" value="<?= ViewHelper::e($email) ?>">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reenviar correo
            </button>
        </form>
    <?php endif; ?>

    <p class="small mb-0"><a href="/login" class="text-decoration-none">Volver a iniciar sesión</a></p>
</div>
<?php
$contentHtml = ob_get_clean();

echo ViewHelper::partial('auth_card', $__theme + [
    'pageTitle'   => 'Revisa tu correo',
    'flashAll'    => $flashAll,
    'contentHtml' => $contentHtml,
]);
```

- [ ] **Step 3: Crear `app/Presentation/Views/auth/recuperar.php`**

```php
<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Security\Csrf;
use App\Kernel\Security\Session;

$__theme  = get_defined_vars();
$flashAll = $flashAll ?? Session::flashAll();

ob_start();
?>
<div class="mb-4 text-center text-md-start">
    <h3 class="fw-bold mb-1">Recuperar contraseña</h3>
    <p class="text-muted small mb-0">Te enviaremos un enlace para restablecerla</p>
</div>

<form method="POST" action="/recuperar" novalidate>
    <?= Csrf::field() ?>

    <div class="mb-4">
        <label for="email" class="form-label fw-medium small">Correo electrónico</label>
        <div class="input-group">
            <span class="input-group-text" aria-hidden="true"><i class="bi bi-envelope"></i></span>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="correo@empresa.com"
                   value="<?= ViewHelper::old('email') ?>" autocomplete="email" autofocus required>
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 fw-semibold">Enviar instrucciones</button>

    <p class="text-center small mt-3 mb-0">
        <a href="/login" class="text-decoration-none">Volver a iniciar sesión</a>
    </p>
</form>
<?php
$contentHtml = ob_get_clean();

echo ViewHelper::partial('auth_card', $__theme + [
    'pageTitle'   => 'Recuperar contraseña',
    'flashAll'    => $flashAll,
    'contentHtml' => $contentHtml,
]);
```

- [ ] **Step 4: Crear `app/Presentation/Views/auth/recuperar_enviado.php`**

```php
<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Security\Session;

$__theme  = get_defined_vars();
$flashAll = $flashAll ?? Session::flashAll();

ob_start();
?>
<div class="text-center">
    <div class="display-5 text-primary mb-3" aria-hidden="true"><i class="bi bi-envelope-check"></i></div>
    <h3 class="fw-bold mb-2">Revisa tu correo</h3>
    <p class="text-muted small mb-4">
        Si el correo existe en el sistema, enviamos las instrucciones para restablecer la contraseña.
        El enlace vence en 60 minutos.
    </p>
    <p class="small mb-0"><a href="/login" class="text-decoration-none">Volver a iniciar sesión</a></p>
</div>
<?php
$contentHtml = ob_get_clean();

echo ViewHelper::partial('auth_card', $__theme + [
    'pageTitle'   => 'Recuperar contraseña',
    'flashAll'    => $flashAll,
    'contentHtml' => $contentHtml,
]);
```

- [ ] **Step 5: Crear `app/Presentation/Views/auth/restablecer.php`**

```php
<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Security\Csrf;
use App\Kernel\Security\Session;

$__theme  = get_defined_vars();
$flashAll = $flashAll ?? Session::flashAll();
$token    = (string) ($token ?? '');

ob_start();
?>
<div class="mb-4 text-center text-md-start">
    <h3 class="fw-bold mb-1">Nueva contraseña</h3>
    <p class="text-muted small mb-0">Define la nueva contraseña de tu cuenta</p>
</div>

<form method="POST" action="/restablecer" novalidate>
    <?= Csrf::field() ?>
    <input type="hidden" name="token" value="<?= ViewHelper::e($token) ?>">

    <div class="mb-3">
        <label for="password" class="form-label fw-medium small">Nueva contraseña</label>
        <input type="password" id="password" name="password"
               class="form-control <?= !empty($flashAll['errors']['password']) ? 'is-invalid' : '' ?>"
               placeholder="Mínimo 8 caracteres" autocomplete="new-password" autofocus required>
        <?php if (!empty($flashAll['errors']['password'])): ?>
            <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['password']) ?></div>
        <?php endif; ?>
    </div>

    <div class="mb-4">
        <label for="password_confirmacion" class="form-label fw-medium small">Confirmar contraseña</label>
        <input type="password" id="password_confirmacion" name="password_confirmacion"
               class="form-control <?= !empty($flashAll['errors']['password_confirmacion']) ? 'is-invalid' : '' ?>"
               placeholder="Repite la contraseña" autocomplete="new-password" required>
        <?php if (!empty($flashAll['errors']['password_confirmacion'])): ?>
            <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['password_confirmacion']) ?></div>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-100 fw-semibold">Guardar contraseña</button>

    <p class="text-center small mt-3 mb-0">
        <a href="/login" class="text-decoration-none">Volver a iniciar sesión</a>
    </p>
</form>
<?php
$contentHtml = ob_get_clean();

echo ViewHelper::partial('auth_card', $__theme + [
    'pageTitle'   => 'Restablecer contraseña',
    'flashAll'    => $flashAll,
    'contentHtml' => $contentHtml,
]);
```

- [ ] **Step 6: Lint y commit**

```bash
php -l app/Presentation/Views/auth/registro.php
php -l app/Presentation/Views/auth/registro_enviado.php
php -l app/Presentation/Views/auth/recuperar.php
php -l app/Presentation/Views/auth/recuperar_enviado.php
php -l app/Presentation/Views/auth/restablecer.php
git add app/Presentation/Views/auth
git commit -m "feat(auth): vistas de registro, verificacion y recuperacion sobre auth_card"
```

---

### Task 15: Controllers, rutas, bindings y fix del botón del perfil

**Files:**
- Create: `app/Presentation/Controllers/RegistroController.php`
- Create: `app/Presentation/Controllers/RecuperacionController.php`
- Modify: `routes/web.php`
- Modify: `config/container.php`
- Modify: `app/Presentation/Views/admin/perfil/index.php` (línea 42)

- [ ] **Step 1: Crear `app/Presentation/Controllers/RegistroController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Application\DTO\Auth\RegistroDTO;
use App\Application\Services\ConfiguracionService;
use App\Application\UseCases\Auth\RegistrarUsuarioUseCase;
use App\Application\UseCases\Auth\ReenviarVerificacionUseCase;
use App\Application\UseCases\Auth\VerificarCorreoUseCase;
use App\Domain\Exceptions\ValidationException;
use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Exceptions\HttpException;
use App\Kernel\Helpers\LebytekUiConfig;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;

/*
|--------------------------------------------------------------------------
| RegistroController — Registro público con verificación de correo
|--------------------------------------------------------------------------
| CSRF en POSTs vía CsrfMiddleware (routes/web.php). El formulario da 404
| cuando registro.habilitado=false; verificar/reenviar siguen operativos
| para cuentas pendientes.
*/

final class RegistroController extends BaseController
{
    public function __construct(
        private readonly ConfiguracionService        $configService,
        private readonly RegistrarUsuarioUseCase     $registrarUseCase,
        private readonly VerificarCorreoUseCase      $verificarUseCase,
        private readonly ReenviarVerificacionUseCase $reenviarUseCase,
        private readonly bool                        $registroHabilitado
    ) {
    }

    public function mostrar(Request $request): Response
    {
        $this->abortarSiDeshabilitado();
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }
        return $this->view('auth/registro', $this->theme(), '');
    }

    public function registrar(Request $request): Response
    {
        $this->abortarSiDeshabilitado();
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        $datos = $request->only(['nombre', 'apellido', 'email', 'password', 'password_confirmacion']);

        try {
            $this->registrarUseCase->execute(new RegistroDTO(
                nombre:               trim((string) ($datos['nombre'] ?? '')),
                apellido:             trim((string) ($datos['apellido'] ?? '')),
                email:                trim((string) ($datos['email'] ?? '')),
                password:             (string) ($datos['password'] ?? ''),
                passwordConfirmacion: (string) ($datos['password_confirmacion'] ?? '')
            ));

            return $this->view('auth/registro_enviado', $this->theme() + [
                'email' => trim((string) ($datos['email'] ?? '')),
            ], '');

        } catch (ValidationException $e) {
            Session::flashInput(array_diff_key($datos, ['password' => 0, 'password_confirmacion' => 0]));
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());
            return $this->redirect('/registro');
        }
    }

    public function verificar(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        try {
            $this->verificarUseCase->execute((string) $request->input('token', ''));
            Session::flash('success', 'Tu correo fue verificado. Ya puedes iniciar sesión.');
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirect('/login');
    }

    public function reenviar(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        $email = trim((string) $request->input('email', ''));

        try {
            $this->reenviarUseCase->execute($email);
        } catch (ValidationException) {
            // Fallo de envío: misma respuesta genérica (anti-enumeración).
        }

        Session::flash('success', 'Si tu cuenta está pendiente de verificación, reenviamos el correo.');
        return $this->view('auth/registro_enviado', $this->theme() + ['email' => $email], '');
    }

    private function abortarSiDeshabilitado(): void
    {
        if (!$this->registroHabilitado) {
            throw new HttpException('Página no encontrada.', 404);
        }
    }

    private function theme(): array
    {
        try {
            $all = $this->configService->all();
            return LebytekUiConfig::resolve(is_array($all) ? $all : []);
        } catch (\Throwable) {
            return LebytekUiConfig::resolve([]);
        }
    }
}
```

- [ ] **Step 2: Crear `app/Presentation/Controllers/RecuperacionController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Application\Services\ConfiguracionService;
use App\Application\UseCases\Auth\RestablecerPasswordUseCase;
use App\Application\UseCases\Auth\SolicitarRecuperacionUseCase;
use App\Domain\Exceptions\ValidationException;
use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Helpers\LebytekUiConfig;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;

/*
|--------------------------------------------------------------------------
| RecuperacionController — Recuperación de contraseña por token
|--------------------------------------------------------------------------
| /recuperar responde siempre igual exista o no el correo (spec §8.2).
| /restablecer no revela la validez del token en el GET (spec §8.4).
*/

final class RecuperacionController extends BaseController
{
    public function __construct(
        private readonly ConfiguracionService         $configService,
        private readonly SolicitarRecuperacionUseCase $solicitarUseCase,
        private readonly RestablecerPasswordUseCase   $restablecerUseCase
    ) {
    }

    public function mostrar(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }
        return $this->view('auth/recuperar', $this->theme(), '');
    }

    public function solicitar(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        try {
            $this->solicitarUseCase->execute(trim((string) $request->input('email', '')));
        } catch (ValidationException) {
            // Fallo de envío: misma respuesta genérica (anti-enumeración).
        }

        return $this->view('auth/recuperar_enviado', $this->theme(), '');
    }

    public function mostrarRestablecer(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        return $this->view('auth/restablecer', $this->theme() + [
            'token' => (string) $request->input('token', ''),
        ], '');
    }

    public function restablecer(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        $token = (string) $request->input('token', '');

        try {
            $this->restablecerUseCase->execute(
                $token,
                (string) $request->input('password', ''),
                (string) $request->input('password_confirmacion', '')
            );

            Session::flash('success', 'Tu contraseña fue actualizada. Inicia sesión con la nueva contraseña.');
            return $this->redirect('/login');

        } catch (ValidationException $e) {
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());
            return $this->redirect('/restablecer?token=' . rawurlencode($token));
        }
    }

    private function theme(): array
    {
        try {
            $all = $this->configService->all();
            return LebytekUiConfig::resolve(is_array($all) ? $all : []);
        } catch (\Throwable) {
            return LebytekUiConfig::resolve([]);
        }
    }
}
```

- [ ] **Step 3: Registrar rutas en `routes/web.php`**

Agregar los imports junto a los existentes:

```php
use App\Presentation\Controllers\RegistroController;
use App\Presentation\Controllers\RecuperacionController;
```

Después del bloque de login/logout (línea 29), agregar:

```php
// Registro público y recuperación de contraseña (404 en /registro si registro.habilitado=false)
$router->get('/registro',           [RegistroController::class, 'mostrar']);
$router->post('/registro',          [RegistroController::class, 'registrar'], [CsrfMiddleware::class]);
$router->get('/registro/verificar', [RegistroController::class, 'verificar']);
$router->post('/registro/reenviar', [RegistroController::class, 'reenviar'], [CsrfMiddleware::class]);

$router->get('/recuperar',    [RecuperacionController::class, 'mostrar']);
$router->post('/recuperar',   [RecuperacionController::class, 'solicitar'], [CsrfMiddleware::class]);
$router->get('/restablecer',  [RecuperacionController::class, 'mostrarRestablecer']);
$router->post('/restablecer', [RecuperacionController::class, 'restablecer'], [CsrfMiddleware::class]);
```

- [ ] **Step 4: Bindings en `config/container.php`**

Agregar los imports junto a los existentes (`use App\Application\UseCases\Auth\LoginUseCase;` etc.):

```php
use App\Application\Services\AuthTokenService;
use App\Application\Services\CorreoAuthService;
use App\Application\UseCases\Auth\RegistrarUsuarioUseCase;
use App\Application\UseCases\Auth\ReenviarVerificacionUseCase;
use App\Application\UseCases\Auth\VerificarCorreoUseCase;
use App\Application\UseCases\Auth\SolicitarRecuperacionUseCase;
use App\Application\UseCases\Auth\RestablecerPasswordUseCase;
use App\Domain\Interfaces\AuthTokenRepositoryInterface;
use App\Domain\Interfaces\MailerInterface;
use App\Infrastructure\Repositories\AuthTokenRepository;
use App\Infrastructure\Mail\LogMailer;
use App\Infrastructure\Mail\PhpMailerMailer;
```

Después del binding de `AuthService` (línea ~95), agregar los singletons:

```php
    $container->singleton(AuthTokenRepositoryInterface::class, fn() => new AuthTokenRepository());

    $container->singleton(MailerInterface::class, static function (): MailerInterface {
        $mailConfig = (array) Config::get('mail', []);
        return ($mailConfig['driver'] ?? 'log') === 'smtp'
            ? new PhpMailerMailer($mailConfig)
            : new LogMailer();
    });

    $container->singleton(AuthTokenService::class, fn(Container $c) => new AuthTokenService(
        $c->get(AuthTokenRepositoryInterface::class),
        (int) Config::get('auth.tokens.max_por_hora', 3)
    ));

    $container->singleton(CorreoAuthService::class, fn(Container $c) => new CorreoAuthService(
        $c->get(MailerInterface::class),
        (string) Config::get('app.url', 'http://localhost')
    ));
```

Después del binding de `AuthController` (línea ~260), agregar los controllers:

```php
    $container->bind(\App\Presentation\Controllers\RegistroController::class, function (Container $c) {
        $usuarioRepo = $c->get(UsuarioRepositoryInterface::class);
        $habilitado  = (bool) Config::get('auth.registro.habilitado', false);
        $ttlVerif    = (int) Config::get('auth.tokens.verificacion_ttl_min', 1440);
        return new \App\Presentation\Controllers\RegistroController(
            $c->get(ConfiguracionService::class),
            new RegistrarUsuarioUseCase(
                usuarioRepo:        $usuarioRepo,
                rolRepo:            $c->get(RolRepositoryInterface::class),
                validator:          new CrearUsuarioValidator(),
                tokens:             $c->get(AuthTokenService::class),
                correo:             $c->get(CorreoAuthService::class),
                habilitado:         $habilitado,
                rolDefault:         (string) Config::get('auth.registro.rol_default', 'usuario'),
                verificacionTtlMin: $ttlVerif
            ),
            new VerificarCorreoUseCase($c->get(AuthTokenRepositoryInterface::class), $usuarioRepo),
            new ReenviarVerificacionUseCase(
                usuarioRepo:        $usuarioRepo,
                tokens:             $c->get(AuthTokenService::class),
                correo:             $c->get(CorreoAuthService::class),
                verificacionTtlMin: $ttlVerif
            ),
            $habilitado
        );
    });

    $container->bind(\App\Presentation\Controllers\RecuperacionController::class, function (Container $c) {
        $usuarioRepo = $c->get(UsuarioRepositoryInterface::class);
        return new \App\Presentation\Controllers\RecuperacionController(
            $c->get(ConfiguracionService::class),
            new SolicitarRecuperacionUseCase(
                usuarioRepo:        $usuarioRepo,
                tokens:             $c->get(AuthTokenService::class),
                correo:             $c->get(CorreoAuthService::class),
                recuperacionTtlMin: (int) Config::get('auth.tokens.recuperacion_ttl_min', 60)
            ),
            new RestablecerPasswordUseCase($usuarioRepo, $c->get(AuthTokenRepositoryInterface::class))
        );
    });
```

- [ ] **Step 5: Corregir el botón "Cambiar contraseña" del perfil**

En `app/Presentation/Views/admin/perfil/index.php` línea 42, cambiar:

```php
                            <a href="/auth/recuperar" class="btn btn-outline-secondary">
```

por:

```php
                            <a href="/recuperar" class="btn btn-outline-secondary">
```

- [ ] **Step 6: Lint, suite y commit**

```bash
php -l app/Presentation/Controllers/RegistroController.php
php -l app/Presentation/Controllers/RecuperacionController.php
php -l config/container.php
php -l routes/web.php
php tests/run.php
```

Expected: sin errores de sintaxis; suite 0 failed.

```bash
git add app/Presentation/Controllers/RegistroController.php app/Presentation/Controllers/RecuperacionController.php routes/web.php config/container.php app/Presentation/Views/admin/perfil/index.php
git commit -m "feat(auth): controllers y rutas publicas de registro/recuperacion + fix boton perfil"
```

---

### Task 16: Verificación final — suite completa + smoke manual

- [ ] **Step 1: Suite completa**

```bash
php tests/run.php
```

Expected: todos los tests previos (294) + los nuevos de `tests/Auth/` en PASS, 0 failed.

- [ ] **Step 2: Preparar entorno de smoke**

En `.env` local poner:

```env
REGISTRO_HABILITADO=true
MAIL_DRIVER=log
APP_URL=http://localhost:8000
```

Si hay BD local: `php scripts/seed.php` (asegura `auth_tokens` y el rol `usuario`). Luego:

```bash
php -S localhost:8000 -t public
```

- [ ] **Step 3: Smoke — ciclo de registro**

1. `http://localhost:8000/login` → se ve idéntico + enlaces nuevos.
2. "Crear cuenta" → `/registro`; registrar un usuario nuevo → página "Revisa tu correo".
3. Intentar login con ese usuario ANTES de verificar → debe rechazar ("cuenta desactivada").
4. Abrir `storage/logs/app-YYYY-MM-DD.log`, copiar la URL `/registro/verificar?token=...` del correo simulado y abrirla → redirige a `/login` con flash de éxito.
5. Login con el usuario nuevo → entra al dashboard (rol `usuario`, permiso `dashboard.ver`).
6. Botón "Reenviar correo": registrar otro usuario y verificar que el reenvío genera nueva URL en el log y la anterior deja de funcionar.

- [ ] **Step 4: Smoke — ciclo de recuperación**

1. `/recuperar` con el email del usuario → página genérica; URL `/restablecer?token=...` en el log.
2. `/recuperar` con un email inexistente → misma página genérica, nada en el log.
3. Abrir la URL de restablecer, poner contraseña nueva → redirige a `/login` con éxito; login con la nueva contraseña funciona y con la vieja no.
4. Reusar la misma URL de restablecer → error "enlace no válido o expirado".
5. `/admin/perfil` → botón "Cambiar contraseña" lleva a `/recuperar` (ya no 404).

- [ ] **Step 5: Smoke — toggle apagado**

Poner `REGISTRO_HABILITADO=false` en `.env`, reiniciar el server:

1. `/registro` → 404.
2. `/login` → sin enlace "Crear cuenta" (el de "¿Olvidaste tu contraseña?" permanece).

- [ ] **Step 6: Restaurar `.env` de desarrollo y commit final si hubo ajustes**

```bash
php tests/run.php
git status
```

Si el smoke obligó a algún ajuste, commitearlo con mensaje `fix(auth): ...`. Dejar `.env` local como estaba (no se versiona).

---

## Cobertura del spec (auto-verificación)

| Spec | Task |
|---|---|
| §3 config auth/mail/seed/composer | 1, 2 |
| §4 `auth_tokens` + `email_verificado_en` + entidad/contrato/repo | 2, 3, 4, 5, 12 |
| §5 MailerInterface, drivers, plantillas, fallo genérico | 6, 8, 12 |
| §6 cinco use cases + token sha256 | 7, 9, 10, 11 |
| §7 rutas, controllers, shell `auth_card`, vistas, fix perfil, enlaces login | 13, 14, 15 |
| §8 seguridad (hash, un solo uso, anti-enumeración, throttle, no auto-login, CSRF) | 7, 10, 11, 15 (tests en 7/10/11) |
| §9 plan de pruebas (fakes, casos, contrato, regresión, smoke) | 4, 9, 10, 11, 16 |
