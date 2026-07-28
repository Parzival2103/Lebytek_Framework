# Integrations Fase 2 — Gestión de Instancias y Provisión de Demos WhatsApp — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir al módulo `integrations` la gestión de instancias Green API (interna + por cliente) con token cifrado, provisión de demos desde `dom_mkt_leads` (Partner API con fallback manual), UI en Ajustes/página dedicada, activación pública por QR y visor de `int_logs`.

**Architecture:** Una tabla `int_accounts` es la fuente única de instancias (token cifrado con `Crypto`/`APP_KEY`). `IntegrationsFactory` resuelve las credenciales internas desde la fila `is_default` con fallback a `.env`, sin tocar el canal ni el dispatcher de Fase 1. Un `IntegrationsController` con su propia página `/admin/integraciones` maneja configuración, prueba de conexión, provisión (auto/manual), visor de logs y la vista pública de QR. La sección de Ajustes es solo una tarjeta de acceso a esa página. La provisión se dispara desde una acción de fila `type: link` en `dom_mkt_leads`.

**Tech Stack:** PHP 8.1+, arquitectura Onion del framework Lebytek, PDO (`App\Kernel\Database\Connection`), `BaseRepository`, router propio (`routes/*.php`), microtest harness (`php tests/run.php`), Bootstrap 5 views, Green API.

## Global Constraints

- PHP `declare(strict_types=1);` en todo archivo nuevo.
- Clases PascalCase, métodos camelCase, constantes UPPER_SNAKE_CASE (CLAUDE.md).
- Prefijo de tabla `int_*` para integraciones; **no** exponer tablas `int_*` por el CRUD Engine.
- Regla Onion: Domain sin dependencias externas; Infrastructure implementa puertos del Domain; el dispatcher/canales de Fase 1 **no** se modifican.
- Ningún módulo de negocio referencia Green API: todo envío pasa por `NotificationDispatcher` (Fase 1).
- Tokens Green API **cifrados en reposo**; nunca en claro en vistas ni logs; destinatarios **enmascarados** en `int_logs`.
- Bootstrap SQL **idempotente** (`CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`).
- Toda ruta/binding/seed nuevo del módulo va condicionado a `modules.integrations` (toggle en `config/vertical.php`).
- Tests: archivos `*Test.php` bajo `tests/`, usando `test()`, `assert_true()`, `assert_same()`, `assert_null()`, `assert_throws()`. Ejecutar con `php tests/run.php <filtro>`.
- Credenciales y `APP_KEY` solo desde `.env` (vía `App\Kernel\EnvLoader` / `App\Kernel\Config`).

---

## File Structure

**Nuevos:**
- `app/Kernel/Security/Crypto.php` — cifrado simétrico AES-256-GCM con `APP_KEY`.
- `app/Kernel/Security/SignedToken.php` — token HMAC firmado con expiración (para el link público de QR).
- `app/Domain/Integrations/IntegrationAccount.php` — VO de instancia (sin token en claro fuera del repo).
- `app/Domain/Integrations/IntegrationAccountRepositoryInterface.php` — puerto del repo de instancias.
- `app/Domain/Integrations/PartnerConnectorInterface.php` — puerto de creación de instancias.
- `app/Infrastructure/Integrations/Repositories/IntegrationAccountRepository.php` — PDO + cifra/descifra.
- `app/Infrastructure/Integrations/Partner/GreenApiPartnerConnector.php` — crea instancias vía Partner API.
- `app/Infrastructure/Integrations/Settings/IntegrationsWhatsappSettingsProvider.php` — tarjeta en Ajustes.
- `app/Application/Integrations/DemoProvisioningService.php` — orquesta provisión + correo (testeable sin HTTP).
- `app/Presentation/Controllers/Admin/IntegrationsController.php` — página de gestión + provisión + QR público.
- `app/Presentation/Views/admin/integraciones/index.php` — config interna + test + logs.
- `app/Presentation/Views/admin/integraciones/provision.php` — modal/form auto vs manual.
- `app/Presentation/Views/admin/integraciones/_logs.php` — fragmento tabla de int_logs.
- `app/Presentation/Views/admin/ajustes/_provider_section.php` — (re-crear; fue borrado) soporta `vista()` custom.
- `app/Presentation/Views/publico/wa_activar.php` — vista pública de QR.
- `routes/integrations.php` — rutas admin + ruta pública, condicionadas al módulo.
- Tests: `tests/Integrations/CryptoTest.php`, `SignedTokenTest.php`, `IntegrationAccountRepositoryTest.php`, `IntegrationsFactoryDefaultTest.php`, `GreenApiPartnerConnectorTest.php`, `DemoProvisioningServiceTest.php`, `SettingsSectionVistaTest.php`.

**Modificados:**
- `database/schema/modules/integrations.sql` — añadir `int_accounts` + entrada de menú.
- `app/Application/Integrations/IntegrationsFactory.php` — creds internas desde DB con fallback `.env`.
- `app/Domain/Integrations/IntegrationLogRepositoryInterface.php` — añadir `recent()`.
- `app/Infrastructure/Integrations/Repositories/IntegrationLogRepository.php` — implementar `recent()`.
- `app/Domain/Interfaces/SettingsSectionProviderInterface.php` — añadir `vista(): ?string`.
- Las 4 clases `app/Infrastructure/Marketing/Settings/*SettingsProvider.php` — añadir `vista(): ?string { return null; }`.
- `config/container.php` — bindings del repo, partner connector, controller, provider de sección.
- `config/cruds/dom_mkt_leads.json` — acción de fila `provisionar_demo_wa`.
- `routes/web.php` — incluir `routes/integrations.php` si `modules.integrations`.
- `.env.example` — `GREEN_API_PARTNER_TOKEN=`.

---

## Notas de patrones del repo (leer antes de empezar)

- **PDO:** `App\Kernel\Database\Connection::getInstance()` devuelve el PDO. Los repos extienden `App\Kernel\BaseClasses\BaseRepository` (ver `IntegrationLogRepository`).
- **Config/Env:** `App\Kernel\Config::get('clave', default)`, `App\Kernel\EnvLoader::get('VAR', default)`.
- **Fachada de envío (Fase 1):** `IntegrationsFactory::dispatcher()->send(new MessageRequest(channel, recipient, body, meta))`. Canal `email` ya funciona.
- **Settings sections:** `SettingsSectionRegistry` itera providers; `AjustesController` los muestra dentro de un único `<form>` que postea a `/admin/ajustes` (`guardar`). Por eso la gestión pesada va en página propia, y la sección en Ajustes es solo un enlace.
- **CRUD action `link`:** ejemplo existente en `dom_*`: `{ "name": "...", "type": "link", "route": "/...?id={id}", "permission": "..." }` (ver acción `contrato` en `config/cruds/demo_clientes.json`).
- **Microtest:** los tests llaman `test('nombre', function() { ... })` a nivel de archivo; sin clases. Bootstrap en `tests/lib/bootstrap.php`.
- **APP_KEY** ya existe en `.env.example` (`APP_KEY=...`). Asumir ≥16 bytes; `Crypto` deriva la clave con `hash('sha256', APP_KEY, true)`.

---

### Task 1: Kernel `Crypto` (cifrado de tokens)

**Files:**
- Create: `app/Kernel/Security/Crypto.php`
- Test: `tests/Integrations/CryptoTest.php`

**Interfaces:**
- Consumes: `App\Kernel\EnvLoader::get('APP_KEY')`.
- Produces: `Crypto::encrypt(string $plain): string`, `Crypto::decrypt(string $payload): string` (lanza `\RuntimeException` si `APP_KEY` ausente o payload corrupto).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Integrations/CryptoTest.php
declare(strict_types=1);

use App\Kernel\Security\Crypto;

test('Crypto round-trip devuelve el texto original', function () {
    $plain = 'apiTokenInstance-1234567890abcdef';
    $cipher = Crypto::encrypt($plain);
    assert_true($cipher !== $plain, 'el cifrado no debe ser igual al claro');
    assert_same($plain, Crypto::decrypt($cipher));
});

test('Crypto produce cifrados distintos por IV aleatorio', function () {
    assert_true(Crypto::encrypt('x') !== Crypto::encrypt('x'), 'IV debe variar');
});

test('Crypto::decrypt lanza con payload corrupto', function () {
    assert_throws(\RuntimeException::class, fn() => Crypto::decrypt('no-es-base64-valido!!'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Integrations/CryptoTest`
Expected: FAIL ("Class \"App\\Kernel\\Security\\Crypto\" not found").

- [ ] **Step 3: Write minimal implementation**

```php
<?php
// app/Kernel/Security/Crypto.php
declare(strict_types=1);

namespace App\Kernel\Security;

use App\Kernel\EnvLoader;

final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    public static function encrypt(string $plain): string
    {
        $key = self::key();
        $ivLen = openssl_cipher_iv_length(self::CIPHER) ?: 12;
        $iv = random_bytes($ivLen);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Crypto: fallo al cifrar.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        $key = self::key();
        $raw = base64_decode($payload, true);
        $ivLen = openssl_cipher_iv_length(self::CIPHER) ?: 12;
        if ($raw === false || strlen($raw) < $ivLen + 16) {
            throw new \RuntimeException('Crypto: payload inválido.');
        }
        $iv = substr($raw, 0, $ivLen);
        $tag = substr($raw, $ivLen, 16);
        $cipher = substr($raw, $ivLen + 16);
        $plain = openssl_decrypt($cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Crypto: fallo al descifrar.');
        }
        return $plain;
    }

    private static function key(): string
    {
        $appKey = (string) EnvLoader::get('APP_KEY', '');
        if ($appKey === '') {
            throw new \RuntimeException('Crypto: APP_KEY ausente en .env.');
        }
        return hash('sha256', $appKey, true); // 32 bytes para aes-256
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Integrations/CryptoTest`
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Kernel/Security/Crypto.php tests/Integrations/CryptoTest.php
git commit -m "feat(integrations): helper Crypto para cifrado de tokens (AES-256-GCM)"
```

---

### Task 2: `SignedToken` (link público de activación)

**Files:**
- Create: `app/Kernel/Security/SignedToken.php`
- Test: `tests/Integrations/SignedTokenTest.php`

**Interfaces:**
- Consumes: `App\Kernel\EnvLoader::get('APP_KEY')`.
- Produces: `SignedToken::make(int $accountId, int $ttlSeconds = 86400): string`, `SignedToken::verify(string $token): ?int` (devuelve `accountId` o `null` si firma inválida/expirado).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Integrations/SignedTokenTest.php
declare(strict_types=1);

use App\Kernel\Security\SignedToken;

test('SignedToken round-trip devuelve el accountId', function () {
    $t = SignedToken::make(42, 3600);
    assert_same(42, SignedToken::verify($t));
});

test('SignedToken rechaza firma manipulada', function () {
    $t = SignedToken::make(42, 3600);
    assert_null(SignedToken::verify($t . 'x'));
});

test('SignedToken rechaza token expirado', function () {
    $t = SignedToken::make(42, -1); // ya expirado
    assert_null(SignedToken::verify($t));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Integrations/SignedTokenTest`
Expected: FAIL ("Class ... SignedToken not found").

- [ ] **Step 3: Write minimal implementation**

```php
<?php
// app/Kernel/Security/SignedToken.php
declare(strict_types=1);

namespace App\Kernel\Security;

use App\Kernel\EnvLoader;

final class SignedToken
{
    public static function make(int $accountId, int $ttlSeconds = 86400): string
    {
        $exp = time() + $ttlSeconds;
        $payload = $accountId . '.' . $exp;
        $sig = self::sign($payload);
        return rtrim(strtr(base64_encode($payload . '.' . $sig), '+/', '-_'), '=');
    }

    public static function verify(string $token): ?int
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }
        $parts = explode('.', $decoded);
        if (count($parts) !== 3) {
            return null;
        }
        [$accountId, $exp, $sig] = $parts;
        if (!hash_equals(self::sign($accountId . '.' . $exp), $sig)) {
            return null;
        }
        if ((int) $exp < time()) {
            return null;
        }
        return (int) $accountId;
    }

    private static function sign(string $payload): string
    {
        $key = (string) EnvLoader::get('APP_KEY', '');
        if ($key === '') {
            throw new \RuntimeException('SignedToken: APP_KEY ausente.');
        }
        return hash_hmac('sha256', $payload, $key);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Integrations/SignedTokenTest`
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Kernel/Security/SignedToken.php tests/Integrations/SignedTokenTest.php
git commit -m "feat(integrations): SignedToken HMAC para el link público de activación"
```

---

### Task 3: Tabla `int_accounts` + VO + repositorio

**Files:**
- Modify: `database/schema/modules/integrations.sql`
- Create: `app/Domain/Integrations/IntegrationAccount.php`
- Create: `app/Domain/Integrations/IntegrationAccountRepositoryInterface.php`
- Create: `app/Infrastructure/Integrations/Repositories/IntegrationAccountRepository.php`
- Test: `tests/Integrations/IntegrationAccountRepositoryTest.php`

**Interfaces:**
- Consumes: `Crypto::encrypt/decrypt` (Task 1), `App\Kernel\Database\Connection::getInstance()`, `App\Kernel\BaseClasses\BaseRepository`.
- Produces:
  - `IntegrationAccount` (readonly props: `int $id`, `string $provider`, `string $label`, `string $instanceId`, `string $token`, `bool $isDefault`, `?int $leadId`, `string $status`, `string $provisionedVia`).
  - `IntegrationAccountRepositoryInterface`:
    - `findDefault(string $provider): ?IntegrationAccount`
    - `findById(int $id): ?IntegrationAccount`
    - `findByLead(int $leadId, string $provider): ?IntegrationAccount`
    - `save(IntegrationAccount $account): int` (inserta o actualiza por `id`; devuelve id)
    - `markDefault(int $id, string $provider): void` (transacción: pone `is_default=0` al resto del provider y `1` a `id`)

- [ ] **Step 1: Add `int_accounts` + menú al bootstrap SQL (idempotente)**

Añadir al final de `database/schema/modules/integrations.sql`, **antes** de `SET FOREIGN_KEY_CHECKS = 1;`:

```sql
-- ── Instancias (Fase 2): fuente única de credenciales Green API ──────────────────
CREATE TABLE IF NOT EXISTS `int_accounts` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`         VARCHAR(40)     NOT NULL DEFAULT 'green_api',
  `label`            VARCHAR(190)    NOT NULL,
  `instance_id`      VARCHAR(190)    NOT NULL,
  `token_encrypted`  TEXT            NOT NULL,
  `is_default`       TINYINT(1)      NOT NULL DEFAULT 0,
  `lead_id`          BIGINT UNSIGNED DEFAULT NULL,
  `status`           VARCHAR(20)     NOT NULL DEFAULT 'manual',
  `provisioned_via`  VARCHAR(20)     NOT NULL DEFAULT 'manual',
  `meta`             JSON            DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`       BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_int_accounts_default` (`is_default`),
  KEY `idx_int_accounts_lead` (`lead_id`),
  KEY `idx_int_accounts_provider` (`provider`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Entrada de menú a la página de gestión (idempotente por slug/url).
INSERT IGNORE INTO `core_menu_items` (`titulo`, `url`, `icono`, `permiso_slug`, `orden`, `activo`)
VALUES ('Integraciones', '/admin/integraciones', 'bi-plug', 'integrations.ver', 95, 1);
```

> Nota: si la estructura real de `core_menu_items` difiere (columnas/orden), ajustar el INSERT a sus columnas reales — revisar `database/schema` o un INSERT existente antes de codificar. No inventar columnas.

- [ ] **Step 2: Write the failing test**

```php
<?php
// tests/Integrations/IntegrationAccountRepositoryTest.php
declare(strict_types=1);

use App\Domain\Integrations\IntegrationAccount;
use App\Infrastructure\Integrations\Repositories\IntegrationAccountRepository;
use App\Kernel\Database\Connection;

test('save + findById conserva datos y descifra el token', function () {
    $pdo = Connection::getInstance();
    $pdo->exec('DELETE FROM int_accounts');
    $repo = new IntegrationAccountRepository();

    $id = $repo->save(new IntegrationAccount(
        0, 'green_api', 'Interna', '110100001', 'secreto-token', true, null, 'manual', 'manual'
    ));
    $found = $repo->findById($id);
    assert_true($found !== null, 'debe encontrar la cuenta');
    assert_same('110100001', $found->instanceId);
    assert_same('secreto-token', $found->token); // descifrado
});

test('el token se guarda cifrado en la columna (no en claro)', function () {
    $pdo = Connection::getInstance();
    $pdo->exec('DELETE FROM int_accounts');
    $repo = new IntegrationAccountRepository();
    $id = $repo->save(new IntegrationAccount(0, 'green_api', 'X', 'i', 'EN-CLARO', false, null, 'manual', 'manual'));
    $raw = $pdo->query("SELECT token_encrypted FROM int_accounts WHERE id={$id}")->fetchColumn();
    assert_true(strpos((string) $raw, 'EN-CLARO') === false, 'el token no debe aparecer en claro');
});

test('markDefault deja solo una instancia por defecto', function () {
    $pdo = Connection::getInstance();
    $pdo->exec('DELETE FROM int_accounts');
    $repo = new IntegrationAccountRepository();
    $a = $repo->save(new IntegrationAccount(0, 'green_api', 'A', 'i1', 't1', true, null, 'manual', 'manual'));
    $b = $repo->save(new IntegrationAccount(0, 'green_api', 'B', 'i2', 't2', false, null, 'manual', 'manual'));
    $repo->markDefault($b, 'green_api');
    assert_same($b, $repo->findDefault('green_api')->id);
    $count = (int) $pdo->query("SELECT COUNT(*) FROM int_accounts WHERE is_default=1")->fetchColumn();
    assert_same(1, $count);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php tests/run.php Integrations/IntegrationAccountRepositoryTest`
Expected: FAIL ("Class ... IntegrationAccount not found").

- [ ] **Step 4: Implement the VO**

```php
<?php
// app/Domain/Integrations/IntegrationAccount.php
declare(strict_types=1);

namespace App\Domain\Integrations;

final class IntegrationAccount
{
    public function __construct(
        public readonly int $id,
        public readonly string $provider,
        public readonly string $label,
        public readonly string $instanceId,
        public readonly string $token,          // SIEMPRE en claro en memoria; el repo cifra al persistir
        public readonly bool $isDefault,
        public readonly ?int $leadId,
        public readonly string $status,
        public readonly string $provisionedVia,
    ) {}
}
```

- [ ] **Step 5: Implement the interface**

```php
<?php
// app/Domain/Integrations/IntegrationAccountRepositoryInterface.php
declare(strict_types=1);

namespace App\Domain\Integrations;

interface IntegrationAccountRepositoryInterface
{
    public function findDefault(string $provider): ?IntegrationAccount;
    public function findById(int $id): ?IntegrationAccount;
    public function findByLead(int $leadId, string $provider): ?IntegrationAccount;
    public function save(IntegrationAccount $account): int;
    public function markDefault(int $id, string $provider): void;
}
```

- [ ] **Step 6: Implement the repository**

```php
<?php
// app/Infrastructure/Integrations/Repositories/IntegrationAccountRepository.php
declare(strict_types=1);

namespace App\Infrastructure\Integrations\Repositories;

use App\Domain\Integrations\IntegrationAccount;
use App\Domain\Integrations\IntegrationAccountRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;
use App\Kernel\Database\Connection;
use App\Kernel\Security\Crypto;

final class IntegrationAccountRepository extends BaseRepository implements IntegrationAccountRepositoryInterface
{
    protected string $table = 'int_accounts';

    public function findDefault(string $provider): ?IntegrationAccount
    {
        return $this->one('SELECT * FROM int_accounts WHERE provider = ? AND is_default = 1 LIMIT 1', [$provider]);
    }

    public function findById(int $id): ?IntegrationAccount
    {
        return $this->one('SELECT * FROM int_accounts WHERE id = ? LIMIT 1', [$id]);
    }

    public function findByLead(int $leadId, string $provider): ?IntegrationAccount
    {
        return $this->one('SELECT * FROM int_accounts WHERE lead_id = ? AND provider = ? ORDER BY id DESC LIMIT 1', [$leadId, $provider]);
    }

    public function save(IntegrationAccount $a): int
    {
        $pdo = Connection::getInstance();
        $tokenEnc = Crypto::encrypt($a->token);
        if ($a->id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE int_accounts SET provider=?, label=?, instance_id=?, token_encrypted=?, is_default=?, lead_id=?, status=?, provisioned_via=? WHERE id=?'
            );
            $stmt->execute([$a->provider, $a->label, $a->instanceId, $tokenEnc, $a->isDefault ? 1 : 0, $a->leadId, $a->status, $a->provisionedVia, $a->id]);
            return $a->id;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO int_accounts (provider, label, instance_id, token_encrypted, is_default, lead_id, status, provisioned_via) VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$a->provider, $a->label, $a->instanceId, $tokenEnc, $a->isDefault ? 1 : 0, $a->leadId, $a->status, $a->provisionedVia]);
        return (int) $pdo->lastInsertId();
    }

    public function markDefault(int $id, string $provider): void
    {
        $pdo = Connection::getInstance();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE int_accounts SET is_default = 0 WHERE provider = ?')->execute([$provider]);
            $pdo->prepare('UPDATE int_accounts SET is_default = 1 WHERE id = ?')->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<int, mixed> $params */
    private function one(string $sql, array $params): ?IntegrationAccount
    {
        $stmt = Connection::getInstance()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): IntegrationAccount
    {
        return new IntegrationAccount(
            (int) $row['id'],
            (string) $row['provider'],
            (string) $row['label'],
            (string) $row['instance_id'],
            Crypto::decrypt((string) $row['token_encrypted']),
            (bool) $row['is_default'],
            $row['lead_id'] !== null ? (int) $row['lead_id'] : null,
            (string) $row['status'],
            (string) $row['provisioned_via'],
        );
    }
}
```

> Si `BaseRepository` no expone helpers de query directos, usar `Connection::getInstance()` como arriba (ya hecho). Verificar que `IntegrationLogRepository` no requiera constructor con argumentos; replicar su forma.

- [ ] **Step 7: Apply schema then run test to verify it passes**

Run (aplicar bootstrap del módulo en la DB de tests): `php scripts/seed.php` (o el comando del proyecto que ejecuta los `schema/modules/*.sql`), luego:
Run: `php tests/run.php Integrations/IntegrationAccountRepositoryTest`
Expected: PASS (3 passed). Si falla por columna de `core_menu_items`, corregir el INSERT del Step 1 a las columnas reales.

- [ ] **Step 8: Commit**

```bash
git add database/schema/modules/integrations.sql app/Domain/Integrations/IntegrationAccount.php app/Domain/Integrations/IntegrationAccountRepositoryInterface.php app/Infrastructure/Integrations/Repositories/IntegrationAccountRepository.php tests/Integrations/IntegrationAccountRepositoryTest.php
git commit -m "feat(integrations): tabla int_accounts + repositorio con token cifrado"
```

---

### Task 4: Credenciales internas desde DB en `IntegrationsFactory`

**Files:**
- Modify: `app/Application/Integrations/IntegrationsFactory.php`
- Test: `tests/Integrations/IntegrationsFactoryDefaultTest.php`

**Interfaces:**
- Consumes: `IntegrationAccountRepository::findDefault('green_api')` (Task 3).
- Produces: comportamiento — si existe fila `is_default`, el canal `whatsapp` se construye con su `instance_id` + token; si no, con los valores de `config/integrations.php` (`.env`).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Integrations/IntegrationsFactoryDefaultTest.php
declare(strict_types=1);

use App\Application\Integrations\IntegrationsFactory;
use App\Domain\Integrations\IntegrationAccount;
use App\Infrastructure\Integrations\Repositories\IntegrationAccountRepository;
use App\Kernel\Database\Connection;

test('IntegrationsFactory::resolveWhatsappConfig usa la instancia default de DB', function () {
    $pdo = Connection::getInstance();
    $pdo->exec('DELETE FROM int_accounts');
    (new IntegrationAccountRepository())->save(new IntegrationAccount(
        0, 'green_api', 'Interna', 'DB-INSTANCE', 'DB-TOKEN', true, null, 'authorized', 'manual'
    ));

    $base = ['instance_id' => 'ENV-INSTANCE', 'token' => 'ENV-TOKEN', 'base_url' => 'https://x', 'timeout' => 15];
    $resolved = IntegrationsFactory::resolveWhatsappConfig($base);
    assert_same('DB-INSTANCE', $resolved['instance_id']);
    assert_same('DB-TOKEN', $resolved['token']);
    assert_same('https://x', $resolved['base_url']); // base_url/timeout intactos
});

test('IntegrationsFactory::resolveWhatsappConfig cae a .env sin fila default', function () {
    Connection::getInstance()->exec('DELETE FROM int_accounts');
    $base = ['instance_id' => 'ENV-INSTANCE', 'token' => 'ENV-TOKEN', 'base_url' => 'https://x', 'timeout' => 15];
    $resolved = IntegrationsFactory::resolveWhatsappConfig($base);
    assert_same('ENV-INSTANCE', $resolved['instance_id']);
    assert_same('ENV-TOKEN', $resolved['token']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Integrations/IntegrationsFactoryDefaultTest`
Expected: FAIL ("Call to undefined method ... resolveWhatsappConfig").

- [ ] **Step 3: Add `resolveWhatsappConfig` and use it in `dispatcher()`**

En `app/Application/Integrations/IntegrationsFactory.php`, añadir el `use` y el método, y aplicar la resolución al construir los canales.

Añadir imports:
```php
use App\Infrastructure\Integrations\Repositories\IntegrationAccountRepository;
```

Añadir método público estático:
```php
    /**
     * Resuelve la config del canal whatsapp: instancia default de DB (token descifrado)
     * con fallback a los valores recibidos desde config/.env.
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    public static function resolveWhatsappConfig(array $base): array
    {
        try {
            $default = (new IntegrationAccountRepository())->findDefault('green_api');
        } catch (\Throwable $e) {
            $default = null; // DB no disponible ⇒ usar .env
        }
        if ($default !== null) {
            $base['instance_id'] = $default->instanceId;
            $base['token'] = $default->token;
        }
        return $base;
    }
```

En `dispatcher()`, justo después de obtener `$config`, sustituir la config del canal whatsapp:
```php
        $config = (array) Config::get('integrations', []);
        if (isset($config['channels']['whatsapp']['config'])) {
            $config['channels']['whatsapp']['config'] =
                self::resolveWhatsappConfig((array) $config['channels']['whatsapp']['config']);
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Integrations/IntegrationsFactoryDefaultTest`
Expected: PASS (2 passed).

- [ ] **Step 5: Run full integrations suite (no regressions in Fase 1)**

Run: `php tests/run.php Integrations`
Expected: PASS (todas las de integrations).

- [ ] **Step 6: Commit**

```bash
git add app/Application/Integrations/IntegrationsFactory.php tests/Integrations/IntegrationsFactoryDefaultTest.php
git commit -m "feat(integrations): credenciales internas desde int_accounts con fallback .env"
```

---

### Task 5: Partner connector (auto-provisión) + `.env.example`

**Files:**
- Create: `app/Domain/Integrations/PartnerConnectorInterface.php`
- Create: `app/Infrastructure/Integrations/Partner/GreenApiPartnerConnector.php`
- Modify: `.env.example`
- Test: `tests/Integrations/GreenApiPartnerConnectorTest.php`

**Interfaces:**
- Consumes: `App\Domain\Integrations\ApiConnectorInterface` (Fase 1: `request(method,url,payload,headers): array{status,body,json}`), `EnvLoader::get('GREEN_API_PARTNER_TOKEN')`.
- Produces:
  - `PartnerConnectorInterface`: `isAvailable(): bool`, `createInstance(string $label): array` (devuelve `['instance_id' => string, 'token' => string]`; lanza `\RuntimeException` en fallo).

- [ ] **Step 1: Write the failing test (con un ApiConnector fake)**

```php
<?php
// tests/Integrations/GreenApiPartnerConnectorTest.php
declare(strict_types=1);

use App\Domain\Integrations\ApiConnectorInterface;
use App\Infrastructure\Integrations\Partner\GreenApiPartnerConnector;

final class FakePartnerHttp implements ApiConnectorInterface
{
    public function __construct(private array $response) {}
    public function request(string $method, string $url, array $payload = [], array $headers = []): array
    {
        return $this->response;
    }
}

test('createInstance devuelve instance_id y token del proveedor', function () {
    $http = new FakePartnerHttp(['status' => 200, 'body' => '', 'json' => ['idInstance' => '110999', 'apiTokenInstance' => 'tok-abc']]);
    $c = new GreenApiPartnerConnector($http, 'PARTNER-TOKEN', 'https://api.green-api.com');
    $res = $c->createInstance('Demo - Juan');
    assert_same('110999', $res['instance_id']);
    assert_same('tok-abc', $res['token']);
});

test('isAvailable es false sin partner token', function () {
    $http = new FakePartnerHttp(['status' => 200, 'body' => '', 'json' => []]);
    assert_true((new GreenApiPartnerConnector($http, '', 'https://x'))->isAvailable() === false);
});

test('createInstance lanza si la respuesta no trae credenciales', function () {
    $http = new FakePartnerHttp(['status' => 500, 'body' => 'err', 'json' => []]);
    $c = new GreenApiPartnerConnector($http, 'PARTNER-TOKEN', 'https://x');
    assert_throws(\RuntimeException::class, fn() => $c->createInstance('x'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Integrations/GreenApiPartnerConnectorTest`
Expected: FAIL ("Class ... GreenApiPartnerConnector not found").

- [ ] **Step 3: Implement interface + connector**

```php
<?php
// app/Domain/Integrations/PartnerConnectorInterface.php
declare(strict_types=1);

namespace App\Domain\Integrations;

interface PartnerConnectorInterface
{
    public function isAvailable(): bool;
    /** @return array{instance_id:string, token:string} */
    public function createInstance(string $label): array;
}
```

```php
<?php
// app/Infrastructure/Integrations/Partner/GreenApiPartnerConnector.php
declare(strict_types=1);

namespace App\Infrastructure\Integrations\Partner;

use App\Domain\Integrations\ApiConnectorInterface;
use App\Domain\Integrations\PartnerConnectorInterface;

final class GreenApiPartnerConnector implements PartnerConnectorInterface
{
    public function __construct(
        private readonly ApiConnectorInterface $http,
        private readonly string $partnerToken,
        private readonly string $baseUrl,
    ) {}

    public function isAvailable(): bool
    {
        return trim($this->partnerToken) !== '';
    }

    /** @return array{instance_id:string, token:string} */
    public function createInstance(string $label): array
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('Partner API no disponible (sin GREEN_API_PARTNER_TOKEN).');
        }
        // Endpoint exacto del Partner API: verificar contra la doc vigente de Green API al implementar.
        $url = rtrim($this->baseUrl, '/') . '/partner/createInstance/' . $this->partnerToken;
        $res = $this->http->request('POST', $url, ['name' => $label]);
        $json = (array) ($res['json'] ?? []);
        $instanceId = (string) ($json['idInstance'] ?? '');
        $token = (string) ($json['apiTokenInstance'] ?? '');
        if ($instanceId === '' || $token === '') {
            throw new \RuntimeException('Partner API: respuesta sin credenciales (status ' . ($res['status'] ?? '?') . ').');
        }
        return ['instance_id' => $instanceId, 'token' => $token];
    }
}
```

- [ ] **Step 4: Add `.env.example` key**

Añadir bajo el bloque Green API existente en `.env.example` (tras `GREEN_API_WEBHOOK_TOKEN=`):
```dotenv
# Partner API (auto-crear instancias). Vacío ⇒ provisión solo manual.
GREEN_API_PARTNER_TOKEN=
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/run.php Integrations/GreenApiPartnerConnectorTest`
Expected: PASS (3 passed).

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Integrations/PartnerConnectorInterface.php app/Infrastructure/Integrations/Partner/GreenApiPartnerConnector.php .env.example tests/Integrations/GreenApiPartnerConnectorTest.php
git commit -m "feat(integrations): GreenApiPartnerConnector con fallback (isAvailable) + .env"
```

---

### Task 6: `recent()` en el repositorio de logs (visor)

**Files:**
- Modify: `app/Domain/Integrations/IntegrationLogRepositoryInterface.php`
- Modify: `app/Infrastructure/Integrations/Repositories/IntegrationLogRepository.php`
- Test: `tests/Integrations/IntegrationLogRecentTest.php`

**Interfaces:**
- Produces: `IntegrationLogRepositoryInterface::recent(int $limit = 50, ?string $channel = null): array` — lista de filas asociativas (`channel, driver, recipient_masked, status, provider_message_id, created_at`), más recientes primero.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Integrations/IntegrationLogRecentTest.php
declare(strict_types=1);

use App\Infrastructure\Integrations\Repositories\IntegrationLogRepository;
use App\Kernel\Database\Connection;

test('recent devuelve los últimos envíos, más nuevos primero', function () {
    $pdo = Connection::getInstance();
    $pdo->exec('DELETE FROM int_logs');
    $repo = new IntegrationLogRepository();
    $repo->record('email', 'mailer_adapter', 'a***@x.com', 'sent', 'id1', null, []);
    $repo->record('whatsapp', 'green_api', '52***1234', 'failed', null, 'timeout', []);

    $rows = $repo->recent(10);
    assert_same(2, count($rows));
    assert_same('whatsapp', $rows[0]['channel']); // el más reciente primero
});

test('recent filtra por canal', function () {
    $rows = (new IntegrationLogRepository())->recent(10, 'email');
    foreach ($rows as $r) {
        assert_same('email', $r['channel']);
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Integrations/IntegrationLogRecentTest`
Expected: FAIL ("Call to undefined method ... recent").

- [ ] **Step 3: Add `recent()` to the interface**

En `IntegrationLogRepositoryInterface`, añadir:
```php
    /**
     * Últimos envíos para el visor (más recientes primero).
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 50, ?string $channel = null): array;
```

- [ ] **Step 4: Implement `recent()` in the repository**

Añadir al final de `IntegrationLogRepository`:
```php
    public function recent(int $limit = 50, ?string $channel = null): array
    {
        $limit = max(1, min(200, $limit));
        if ($channel !== null) {
            $stmt = Connection::getInstance()->prepare(
                'SELECT channel, driver, recipient_masked, status, provider_message_id, created_at
                 FROM int_logs WHERE channel = ? ORDER BY id DESC LIMIT ' . $limit
            );
            $stmt->execute([$channel]);
        } else {
            $stmt = Connection::getInstance()->prepare(
                'SELECT channel, driver, recipient_masked, status, provider_message_id, created_at
                 FROM int_logs ORDER BY id DESC LIMIT ' . $limit
            );
            $stmt->execute();
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
```

Añadir `use App\Kernel\Database\Connection;` si no está ya importado en el archivo.

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/run.php Integrations/IntegrationLogRecentTest`
Expected: PASS (2 passed).

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Integrations/IntegrationLogRepositoryInterface.php app/Infrastructure/Integrations/Repositories/IntegrationLogRepository.php tests/Integrations/IntegrationLogRecentTest.php
git commit -m "feat(integrations): IntegrationLogRepository::recent para el visor de logs"
```

---

### Task 7: `DemoProvisioningService` (orquesta provisión + correo)

**Files:**
- Create: `app/Application/Integrations/DemoProvisioningService.php`
- Test: `tests/Integrations/DemoProvisioningServiceTest.php`

**Interfaces:**
- Consumes: `IntegrationAccountRepositoryInterface` (Task 3), `PartnerConnectorInterface` (Task 5), `NotificationDispatcher` (Fase 1, `send(MessageRequest): MessageResult`), `SignedToken::make` (Task 2).
- Produces:
  - `DemoProvisioningService::provisionAuto(int $leadId, string $leadNombre, string $leadEmail): IntegrationAccount` — usa partner API, guarda cuenta (provisioned_via=partner_api), envía correo.
  - `DemoProvisioningService::provisionManual(int $leadId, string $leadNombre, string $leadEmail, string $instanceId, string $token): IntegrationAccount` — guarda cuenta (provisioned_via=manual), envía correo.
  - Helper privado `sendDemoEmail(IntegrationAccount $acc, string $nombre, string $email): void` que arma el `MessageRequest` (channel `email`, body con link `/wa/activar/{token}`).

- [ ] **Step 1: Write the failing test (con repos/dispatcher fakes)**

```php
<?php
// tests/Integrations/DemoProvisioningServiceTest.php
declare(strict_types=1);

use App\Application\Integrations\DemoProvisioningService;
use App\Application\Integrations\NotificationDispatcher;
use App\Domain\Integrations\IntegrationAccount;
use App\Domain\Integrations\IntegrationAccountRepositoryInterface;
use App\Domain\Integrations\MessageRequest;
use App\Domain\Integrations\MessageResult;
use App\Domain\Integrations\PartnerConnectorInterface;

final class FakeAccountRepo implements IntegrationAccountRepositoryInterface
{
    public array $saved = [];
    public function findDefault(string $p): ?IntegrationAccount { return null; }
    public function findById(int $id): ?IntegrationAccount { return null; }
    public function findByLead(int $l, string $p): ?IntegrationAccount { return null; }
    public function save(IntegrationAccount $a): int { $this->saved[] = $a; return 7; }
    public function markDefault(int $id, string $p): void {}
}
final class FakePartner implements PartnerConnectorInterface
{
    public function __construct(private bool $avail) {}
    public function isAvailable(): bool { return $this->avail; }
    public function createInstance(string $label): array { return ['instance_id' => 'AUTO-1', 'token' => 'AUTO-TOK']; }
}
final class SpyDispatcher extends NotificationDispatcher
{
    public ?MessageRequest $last = null;
    public function __construct() {} // evita dependencias reales
    public function send(MessageRequest $r): MessageResult { $this->last = $r; return MessageResult::sent('x'); }
}

test('provisionManual guarda cuenta ligada al lead y manda correo con link', function () {
    $repo = new FakeAccountRepo();
    $disp = new SpyDispatcher();
    $svc = new DemoProvisioningService($repo, new FakePartner(false), $disp, 'https://demo.test');

    $acc = $svc->provisionManual(99, 'Juan', 'juan@x.com', 'INST-1', 'TOK-1');
    assert_same(99, $acc->leadId);
    assert_same('manual', $acc->provisionedVia);
    assert_same('INST-1', $repo->saved[0]->instanceId);
    assert_same('email', $disp->last->channel);
    assert_same('juan@x.com', $disp->last->recipient);
    assert_true(str_contains($disp->last->body, '/wa/activar/'), 'el correo debe incluir el link de activación');
});

test('provisionAuto usa partner API para crear la instancia', function () {
    $repo = new FakeAccountRepo();
    $svc = new DemoProvisioningService($repo, new FakePartner(true), new SpyDispatcher(), 'https://demo.test');
    $acc = $svc->provisionAuto(99, 'Juan', 'juan@x.com');
    assert_same('AUTO-1', $acc->instanceId);
    assert_same('partner_api', $acc->provisionedVia);
});
```

> Nota: si `NotificationDispatcher` es `final`, no se puede extender. En ese caso, crear en `app/Domain/Integrations/` una interfaz `MessageSenderInterface { public function send(MessageRequest $r): MessageResult; }`, hacer que `NotificationDispatcher` la implemente (cambio de 1 línea en su firma `implements`), y tipar el servicio contra la interfaz. Ajustar el fake del test a `implements MessageSenderInterface`. Decidir esto al implementar según el modificador real de la clase.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Integrations/DemoProvisioningServiceTest`
Expected: FAIL ("Class ... DemoProvisioningService not found").

- [ ] **Step 3: Implement the service**

```php
<?php
// app/Application/Integrations/DemoProvisioningService.php
declare(strict_types=1);

namespace App\Application\Integrations;

use App\Domain\Integrations\IntegrationAccount;
use App\Domain\Integrations\IntegrationAccountRepositoryInterface;
use App\Domain\Integrations\MessageRequest;
use App\Domain\Integrations\PartnerConnectorInterface;
use App\Kernel\Security\SignedToken;

final class DemoProvisioningService
{
    public function __construct(
        private readonly IntegrationAccountRepositoryInterface $accounts,
        private readonly PartnerConnectorInterface $partner,
        private readonly NotificationDispatcher $dispatcher,
        private readonly string $appUrl,
    ) {}

    public function provisionAuto(int $leadId, string $leadNombre, string $leadEmail): IntegrationAccount
    {
        $creds = $this->partner->createInstance('Demo - ' . $leadNombre);
        return $this->persistAndNotify($leadId, $leadNombre, $leadEmail, $creds['instance_id'], $creds['token'], 'partner_api');
    }

    public function provisionManual(int $leadId, string $leadNombre, string $leadEmail, string $instanceId, string $token): IntegrationAccount
    {
        return $this->persistAndNotify($leadId, $leadNombre, $leadEmail, $instanceId, $token, 'manual');
    }

    private function persistAndNotify(int $leadId, string $nombre, string $email, string $instanceId, string $token, string $via): IntegrationAccount
    {
        $draft = new IntegrationAccount(0, 'green_api', 'Demo - ' . $nombre, $instanceId, $token, false, $leadId, 'provisioning', $via);
        $id = $this->accounts->save($draft);
        $saved = new IntegrationAccount($id, 'green_api', $draft->label, $instanceId, $token, false, $leadId, 'provisioning', $via);
        $this->sendDemoEmail($saved, $nombre, $email);
        return $saved;
    }

    private function sendDemoEmail(IntegrationAccount $acc, string $nombre, string $email): void
    {
        $link = rtrim($this->appUrl, '/') . '/wa/activar/' . SignedToken::make($acc->id);
        $body = sprintf(
            "Hola %s,\n\nTu demo de WhatsApp está lista. Activa tu instancia escaneando el código QR en este enlace:\n%s\n\nGracias.",
            $nombre,
            $link
        );
        $this->dispatcher->send(new MessageRequest(
            channel: 'email',
            recipient: $email,
            body: $body,
            meta: ['source' => 'integrations:demo_provision', 'subject' => 'Activa tu demo de WhatsApp', 'account_id' => $acc->id]
        ));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php Integrations/DemoProvisioningServiceTest`
Expected: PASS (2 passed). Si `NotificationDispatcher` es `final`, aplicar la nota del Step 1 (interfaz `MessageSenderInterface`) antes de pasar.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Integrations/DemoProvisioningService.php tests/Integrations/DemoProvisioningServiceTest.php app/Domain/Integrations/MessageSenderInterface.php app/Application/Integrations/NotificationDispatcher.php 2>/dev/null
git commit -m "feat(integrations): DemoProvisioningService (provisión auto/manual + correo de activación)"
```

---

### Task 8: `SettingsSectionProviderInterface::vista()` + tarjeta en Ajustes

**Files:**
- Modify: `app/Domain/Interfaces/SettingsSectionProviderInterface.php`
- Modify: `app/Infrastructure/Marketing/Settings/MarketingContenidoSettingsProvider.php`
- Modify: `app/Infrastructure/Marketing/Settings/MarketingCorreoSettingsProvider.php`
- Modify: `app/Infrastructure/Marketing/Settings/MarketingPaquetesSettingsProvider.php`
- Modify: `app/Infrastructure/Marketing/Settings/MarketingTrackingSettingsProvider.php`
- Create: `app/Infrastructure/Integrations/Settings/IntegrationsWhatsappSettingsProvider.php`
- Create/Recreate: `app/Presentation/Views/admin/ajustes/_provider_section.php`
- Test: `tests/Integrations/SettingsSectionVistaTest.php`

**Interfaces:**
- Produces: `SettingsSectionProviderInterface::vista(): ?string` — ruta de vista custom (ej. `'admin/integraciones/_ajustes_card'`) o `null` para sección declarativa normal.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Integrations/SettingsSectionVistaTest.php
declare(strict_types=1);

use App\Infrastructure\Integrations\Settings\IntegrationsWhatsappSettingsProvider;
use App\Infrastructure\Marketing\Settings\MarketingCorreoSettingsProvider;

test('la sección de integraciones declara permiso y vista custom', function () {
    $p = new IntegrationsWhatsappSettingsProvider();
    assert_same('integrations.configurar', $p->permiso());
    assert_true($p->vista() !== null, 'integraciones usa vista custom');
    assert_same([], $p->campos());
});

test('las secciones declarativas de marketing devuelven vista() null', function () {
    assert_null((new MarketingCorreoSettingsProvider())->vista());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Integrations/SettingsSectionVistaTest`
Expected: FAIL ("Class ... IntegrationsWhatsappSettingsProvider not found" o "undefined method vista").

- [ ] **Step 3: Add `vista()` to the interface**

En `SettingsSectionProviderInterface`, añadir tras `campos()`:
```php
    /** Ruta de vista custom para secciones no declarativas; null = sección de campos normal. */
    public function vista(): ?string;
```

- [ ] **Step 4: Add `vista(): ?string { return null; }` a los 4 providers de marketing**

En cada una de las 4 clases `Marketing*SettingsProvider`, añadir el método:
```php
    public function vista(): ?string { return null; }
```

- [ ] **Step 5: Create the integrations provider**

```php
<?php
// app/Infrastructure/Integrations/Settings/IntegrationsWhatsappSettingsProvider.php
declare(strict_types=1);

namespace App\Infrastructure\Integrations\Settings;

use App\Domain\Interfaces\SettingsSectionProviderInterface;

final class IntegrationsWhatsappSettingsProvider implements SettingsSectionProviderInterface
{
    public function clave(): string { return 'integrations_whatsapp'; }
    public function titulo(): string { return 'Integraciones / WhatsApp'; }
    public function icono(): string { return 'bi-whatsapp'; }
    public function permiso(): string { return 'integrations.configurar'; }
    public function campos(): array { return []; }
    public function vista(): ?string { return 'admin/integraciones/_ajustes_card'; }
}
```

- [ ] **Step 6: Recreate `_provider_section` partial to honor `vista()`**

```php
<?php
// app/Presentation/Views/admin/ajustes/_provider_section.php
/** @var \App\Domain\Interfaces\SettingsSectionProviderInterface $section */
/** @var array<string,mixed> $configuracion */
use App\Kernel\Helpers\ViewHelper;
$vista = $section->vista();
?>
<div class="card ct-card mb-3">
  <div class="ct-card-header d-flex align-items-center gap-2">
    <i class="bi <?= ViewHelper::e($section->icono()) ?> text-primary" aria-hidden="true"></i>
    <span class="ct-card-title"><?= ViewHelper::e($section->titulo()) ?></span>
  </div>
  <div class="card-body">
    <?php if ($vista !== null): ?>
      <?= ViewHelper::partial($vista, ['configuracion' => $configuracion]) ?>
    <?php else: ?>
      <?php foreach ($section->campos() as $campo): ?>
        <div class="mb-3">
          <label class="form-label"><?= ViewHelper::e($campo['label'] ?? $campo['name']) ?></label>
          <?php $type = $campo['type'] ?? 'text'; $name = $campo['name']; $val = $configuracion[$name] ?? ($campo['default'] ?? ''); ?>
          <?php if ($type === 'toggle'): ?>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="<?= ViewHelper::e($name) ?>" value="1" <?= ((string)$val === '1') ? 'checked' : '' ?>>
            </div>
          <?php else: ?>
            <input class="form-control" type="<?= ($campo['secret'] ?? false) ? 'password' : 'text' ?>"
                   name="<?= ViewHelper::e($name) ?>" value="<?= ViewHelper::e((string) $val) ?>">
          <?php endif; ?>
          <?php if (!empty($campo['help'])): ?><div class="form-text"><?= ViewHelper::e($campo['help']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
```

> Verificar la firma real de `ViewHelper::partial()` y el markup de tarjetas existente (clases `ct-card`) en otras vistas de ajustes; ajustar el HTML para que coincida con el patrón del proyecto. La lógica clave (rama `vista()` vs `campos()`) es lo que importa.

- [ ] **Step 7: Create the Ajustes card view (enlace a la página de gestión)**

```php
<?php
// app/Presentation/Views/admin/integraciones/_ajustes_card.php
use App\Kernel\Helpers\ViewHelper;
?>
<p class="text-muted small mb-3">
  Configura la instancia interna de WhatsApp, prueba la conexión, gestiona instancias de demo y revisa el registro de envíos.
</p>
<a href="/admin/integraciones" class="btn btn-outline-primary">
  <i class="bi bi-plug me-1"></i>Gestionar integraciones
</a>
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php tests/run.php Integrations/SettingsSectionVistaTest`
Expected: PASS (2 passed).

- [ ] **Step 9: Run marketing settings suite (no regressions)**

Run: `php tests/run.php Feature`
Expected: PASS (las feature tests de provider sections siguen verdes).

- [ ] **Step 10: Commit**

```bash
git add app/Domain/Interfaces/SettingsSectionProviderInterface.php app/Infrastructure/Marketing/Settings/ app/Infrastructure/Integrations/Settings/ app/Presentation/Views/admin/ajustes/_provider_section.php app/Presentation/Views/admin/integraciones/_ajustes_card.php tests/Integrations/SettingsSectionVistaTest.php
git commit -m "feat(integrations): sección de Ajustes con vista custom (tarjeta de acceso)"
```

---

### Task 9: `IntegrationsController` + rutas + bindings

**Files:**
- Create: `app/Presentation/Controllers/Admin/IntegrationsController.php`
- Create: `app/Presentation/Views/admin/integraciones/index.php`
- Create: `app/Presentation/Views/admin/integraciones/provision.php`
- Create: `app/Presentation/Views/admin/integraciones/_logs.php`
- Create: `app/Presentation/Views/publico/wa_activar.php`
- Create: `routes/integrations.php`
- Modify: `routes/web.php`
- Modify: `config/container.php`

**Interfaces:**
- Consumes: `IntegrationAccountRepository` (Task 3), `GreenApiPartnerConnector` (Task 5), `DemoProvisioningService` (Task 7), `IntegrationLogRepository::recent` (Task 6), `IntegrationsFactory::dispatcher` (Fase 1), `SignedToken::verify` (Task 2), `App\Kernel\Http\Request/Response`, `AdminBaseController` pattern (ver `AjustesController`), Green API `getStateInstance`/`getQRCode` vía `HttpApiConnector`.
- Produces: rutas listadas en §9.2 del spec. La verificación es funcional (ver Step de smoke).

- [ ] **Step 1: Create `routes/integrations.php`**

Mirar primero cómo `routes/marketing.php` define rutas y middlewares (grupo admin, permisos, CSRF) y replicar exactamente ese estilo. Estructura objetivo:

```php
<?php
declare(strict_types=1);
/** @var \App\Kernel\Http\Router $router */

// Admin (requiere sesión + RBAC; CSRF en POST, igual que el resto del admin).
$router->get('/admin/integraciones', [\App\Presentation\Controllers\Admin\IntegrationsController::class, 'index'], ['permission' => 'integrations.ver']);
$router->post('/admin/integraciones/config/internal', [\App\Presentation\Controllers\Admin\IntegrationsController::class, 'saveInternal'], ['permission' => 'integrations.configurar']);
$router->post('/admin/integraciones/test', [\App\Presentation\Controllers\Admin\IntegrationsController::class, 'testConnection'], ['permission' => 'integrations.configurar']);
$router->get('/admin/integraciones/provision', [\App\Presentation\Controllers\Admin\IntegrationsController::class, 'provisionForm'], ['permission' => 'integrations.enviar']);
$router->post('/admin/integraciones/provision', [\App\Presentation\Controllers\Admin\IntegrationsController::class, 'provision'], ['permission' => 'integrations.enviar']);

// Público (sin auth, sin CSRF). Token firmado en la URL.
$router->get('/wa/activar/{token}', [\App\Presentation\Controllers\Admin\IntegrationsController::class, 'activar']);
```

> La firma exacta de `$router->get/post(...)` y cómo se declaran permisos/middlewares **debe** copiarse de `routes/marketing.php` / `routes/web.php`. No inventar la API del router.

- [ ] **Step 2: Include the routes file conditionally in `routes/web.php`**

Replicar el patrón con que `routes/marketing.php` se incluye solo si `modules.marketing`. Añadir en `routes/web.php` (junto a esa inclusión):

```php
if ((bool) (\App\Kernel\Config::get('vertical.modules.integrations', false))) {
    require __DIR__ . '/integrations.php';
}
```

> Verificar cómo se lee el toggle en `routes/web.php` para marketing y usar la misma forma exacta (puede ser un array `$vertical['modules']['marketing']` ya cargado). Copiar ese patrón.

- [ ] **Step 3: Implement the controller**

```php
<?php
// app/Presentation/Controllers/Admin/IntegrationsController.php
declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Application\Integrations\DemoProvisioningService;
use App\Application\Integrations\IntegrationsFactory;
use App\Domain\Integrations\IntegrationAccount;
use App\Domain\Integrations\IntegrationAccountRepositoryInterface;
use App\Domain\Integrations\PartnerConnectorInterface;
use App\Infrastructure\Integrations\Http\HttpApiConnector;
use App\Infrastructure\Integrations\Repositories\IntegrationLogRepository;
use App\Kernel\Config;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Kernel\Security\SignedToken;
use App\Presentation\Controllers\AdminBaseController;

final class IntegrationsController extends AdminBaseController
{
    public function __construct(
        \App\Application\Services\ConfiguracionService $config,
        \App\Application\Services\AdminNavigationMenuService $nav,
        private readonly IntegrationAccountRepositoryInterface $accounts,
        private readonly PartnerConnectorInterface $partner,
        private readonly DemoProvisioningService $provisioning,
        private readonly IntegrationLogRepository $logs,
    ) {
        parent::__construct($config, $nav);
    }

    public function index(Request $request): Response
    {
        $default = $this->accounts->findDefault('green_api');
        return $this->view('admin/integraciones/index', [
            'titulo'          => 'Integraciones / WhatsApp',
            'instancia'       => $default,        // puede ser null
            'partnerActivo'   => $this->partner->isAvailable(),
            'logs'            => $this->logs->recent(50),
        ]);
    }

    public function saveInternal(Request $request): Response
    {
        $this->verifyCsrf($request);
        $instanceId = trim((string) $request->input('instance_id', ''));
        $token = trim((string) $request->input('token', ''));
        if ($instanceId === '' || $token === '') {
            Session::flash('error', 'Instance ID y token son obligatorios.');
            return $this->redirect('/admin/integraciones');
        }
        $existing = $this->accounts->findDefault('green_api');
        $id = $this->accounts->save(new IntegrationAccount(
            $existing->id ?? 0, 'green_api', 'Instancia interna', $instanceId, $token, true, null, 'manual', 'manual'
        ));
        $this->accounts->markDefault($id, 'green_api');
        Session::flash('success', 'Instancia interna guardada.');
        return $this->redirect('/admin/integraciones');
    }

    public function testConnection(Request $request): Response
    {
        $this->verifyCsrf($request);
        $acc = $this->accounts->findDefault('green_api');
        if ($acc === null) {
            return $this->json(['ok' => false, 'error' => 'No hay instancia interna configurada.']);
        }
        $base = (array) Config::get('integrations.channels.whatsapp.config', []);
        $baseUrl = rtrim((string) ($base['base_url'] ?? 'https://api.green-api.com'), '/');
        $http = new HttpApiConnector((int) ($base['timeout'] ?? 15));
        $url = "{$baseUrl}/waInstance{$acc->instanceId}/getStateInstance/{$acc->token}";
        $res = $http->request('GET', $url);
        $state = (string) (($res['json']['stateInstance'] ?? '') ?: 'desconocido');
        return $this->json(['ok' => true, 'state' => $state]);
    }

    public function provisionForm(Request $request): Response
    {
        $leadId = (int) $request->input('lead_id', 0);
        return $this->view('admin/integraciones/provision', [
            'titulo'        => 'Provisionar demo WhatsApp',
            'leadId'        => $leadId,
            'partnerActivo' => $this->partner->isAvailable(),
        ]);
    }

    public function provision(Request $request): Response
    {
        $this->verifyCsrf($request);
        $leadId = (int) $request->input('lead_id', 0);
        $nombre = trim((string) $request->input('lead_nombre', ''));
        $email  = trim((string) $request->input('lead_email', ''));
        if ($leadId <= 0 || $email === '') {
            Session::flash('error', 'Lead inválido o sin correo.');
            return $this->redirect('/admin/integraciones');
        }
        try {
            if ($this->partner->isAvailable() && $request->input('modo', 'manual') === 'auto') {
                $this->provisioning->provisionAuto($leadId, $nombre, $email);
            } else {
                $instanceId = trim((string) $request->input('instance_id', ''));
                $token = trim((string) $request->input('token', ''));
                if ($instanceId === '' || $token === '') {
                    Session::flash('error', 'Sin Partner API: ingresa instance_id y token.');
                    return $this->redirect('/admin/integraciones/provision?lead_id=' . $leadId);
                }
                $this->provisioning->provisionManual($leadId, $nombre, $email, $instanceId, $token);
            }
            Session::flash('success', 'Demo provisionada y correo enviado.');
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo provisionar: ' . $e->getMessage());
        }
        return $this->redirect('/admin/integraciones');
    }

    /** Pública: muestra el QR para vincular WhatsApp. Sin auth; token firmado. */
    public function activar(Request $request, string $token = ''): Response
    {
        $accountId = SignedToken::verify($token);
        if ($accountId === null) {
            return $this->view('publico/wa_activar', ['error' => 'Enlace inválido o expirado.', 'qr' => null]);
        }
        $acc = $this->accounts->findById($accountId);
        if ($acc === null) {
            return $this->view('publico/wa_activar', ['error' => 'Instancia no encontrada.', 'qr' => null]);
        }
        $base = (array) Config::get('integrations.channels.whatsapp.config', []);
        $baseUrl = rtrim((string) ($base['base_url'] ?? 'https://api.green-api.com'), '/');
        $http = new HttpApiConnector((int) ($base['timeout'] ?? 15));
        // getQRCode: verificar path exacto contra la doc de Green API al implementar.
        $res = $http->request('GET', "{$baseUrl}/waInstance{$acc->instanceId}/qr/{$acc->token}");
        $qr = (string) (($res['json']['message'] ?? '') ?: '');
        return $this->view('publico/wa_activar', ['error' => null, 'qr' => $qr]);
    }
}
```

> Verificar la firma real de `AdminBaseController::__construct` (orden de dependencias) replicando `AjustesController`. Verificar cómo el router pasa `{token}` al método (`activar($request, $token)` vs `$request->param('token')`); ajustar a la convención real del router. Verificar `Request::input/has`, `Response`/`$this->view/json/redirect`, `verifyCsrf` (todos usados ya por `AjustesController`).

- [ ] **Step 4: Create the views (index, provision, _logs, wa_activar)**

`app/Presentation/Views/admin/integraciones/index.php` — form de instancia interna (POST a `/admin/integraciones/config/internal`, con CSRF), botón "Probar conexión" (AJAX POST a `/admin/integraciones/test`), e incluir `_logs`. Usar el layout admin estándar (ver cabecera de otras vistas admin). Campos: `instance_id` (text, prellenar `$instancia?->instanceId`), `token` (password, **no** prellenar con el token), submit. Mostrar `partnerActivo` como badge.

`app/Presentation/Views/admin/integraciones/provision.php` — si `$partnerActivo`, ofrecer radio "Crear automáticamente" (modo=auto) y, alternativamente, campos manuales (instance_id, token); si no, solo campos manuales. Campos ocultos `lead_id`, y `lead_nombre`/`lead_email` (ver nota Step 5). POST a `/admin/integraciones/provision` con CSRF.

`app/Presentation/Views/admin/integraciones/_logs.php`:
```php
<?php use App\Kernel\Helpers\ViewHelper; /** @var list<array<string,mixed>> $logs */ ?>
<table class="table table-sm">
  <thead><tr><th>Fecha</th><th>Canal</th><th>Destinatario</th><th>Estado</th><th>ID proveedor</th></tr></thead>
  <tbody>
  <?php foreach (($logs ?? []) as $l): ?>
    <tr>
      <td><?= ViewHelper::e((string) $l['created_at']) ?></td>
      <td><?= ViewHelper::e((string) $l['channel']) ?></td>
      <td><?= ViewHelper::e((string) $l['recipient_masked']) ?></td>
      <td><?= ViewHelper::e((string) $l['status']) ?></td>
      <td><?= ViewHelper::e((string) ($l['provider_message_id'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
```

`app/Presentation/Views/publico/wa_activar.php` — vista mínima pública (sin layout admin): si `$error`, mostrarlo; si `$qr`, mostrar `<img src="data:image/png;base64,<?= htmlspecialchars($qr) ?>">` + instrucciones de escaneo. Usar el layout público (ver `app/Presentation/Views/publico/layout.php`).

- [ ] **Step 5: Resolve lead nombre/email for the provision form**

La acción CRUD pasa `lead_id` por query. El controlador necesita `lead_nombre`/`lead_email` para el correo. Elegir **una** vía y dejarla explícita:
- (a) En `provisionForm`, leer el lead vía el repositorio genérico CRUD ya existente (ver cómo otros controladores leen un registro `dom_*`) y precargar nombre/email como campos ocultos del form.
- (b) Si resulta complejo, dejar nombre/email como inputs visibles en el form de provisión (el operador los confirma) — más simple y sin acoplar al repo de leads.

Implementar (b) por simplicidad y desacople (el módulo `integrations` no debe conocer tablas `dom_*`): el form de `provision.php` incluye inputs `lead_nombre` y `lead_email` prellenables manualmente.

- [ ] **Step 6: Register bindings in `config/container.php`**

Replicar el estilo de bindings existentes. Añadir (condicionar al toggle si los demás módulos lo hacen así — ver bloque marketing):
```php
$container->singleton(\App\Domain\Integrations\IntegrationAccountRepositoryInterface::class,
    fn() => new \App\Infrastructure\Integrations\Repositories\IntegrationAccountRepository());

$container->singleton(\App\Domain\Integrations\PartnerConnectorInterface::class, function () {
    $base = (array) \App\Kernel\Config::get('integrations.channels.whatsapp.config', []);
    return new \App\Infrastructure\Integrations\Partner\GreenApiPartnerConnector(
        new \App\Infrastructure\Integrations\Http\HttpApiConnector((int) ($base['timeout'] ?? 15)),
        (string) \App\Kernel\EnvLoader::get('GREEN_API_PARTNER_TOKEN', ''),
        (string) ($base['base_url'] ?? 'https://api.green-api.com')
    );
});

$container->singleton(\App\Application\Integrations\DemoProvisioningService::class, function ($c) {
    return new \App\Application\Integrations\DemoProvisioningService(
        $c->get(\App\Domain\Integrations\IntegrationAccountRepositoryInterface::class),
        $c->get(\App\Domain\Integrations\PartnerConnectorInterface::class),
        \App\Application\Integrations\IntegrationsFactory::dispatcher(),
        (string) \App\Kernel\EnvLoader::get('APP_URL', '')
    );
});

$container->bind(\App\Presentation\Controllers\Admin\IntegrationsController::class, function ($c) {
    return new \App\Presentation\Controllers\Admin\IntegrationsController(
        $c->get(\App\Application\Services\ConfiguracionService::class),
        $c->get(\App\Application\Services\AdminNavigationMenuService::class),
        $c->get(\App\Domain\Integrations\IntegrationAccountRepositoryInterface::class),
        $c->get(\App\Domain\Integrations\PartnerConnectorInterface::class),
        $c->get(\App\Application\Integrations\DemoProvisioningService::class),
        new \App\Infrastructure\Integrations\Repositories\IntegrationLogRepository()
    );
});
```

También registrar el provider de sección en el array del `SettingsSectionRegistry` (línea ~533 de `config/container.php`), condicionado a `modules.integrations`:
```php
        if ((bool) (\App\Kernel\Config::get('vertical.modules.integrations', false))) {
            $providers[] = new \App\Infrastructure\Integrations\Settings\IntegrationsWhatsappSettingsProvider();
        }
```

> Verificar nombres reales: `Config::get('vertical.modules.integrations')` debe corresponder a cómo el proyecto lee `config/vertical.php` (puede ser `Config::get('vertical')['modules']['integrations']`). Copiar el patrón de marketing. Confirmar que `APP_URL` existe en `.env`; si no, añadirlo a `.env.example` (`APP_URL=http://localhost:8000`).

- [ ] **Step 7: Smoke test the app boots and routes resolve**

Run: `php -l app/Presentation/Controllers/Admin/IntegrationsController.php` (lint) → "No syntax errors".
Run: `php tests/run.php` (suite completa) → sin fallos nuevos respecto al baseline.
Luego arranque manual: `php -S localhost:8000 -t public` y visitar `/admin/integraciones` (autenticado) → carga sin error; `/wa/activar/xxxx` → muestra "Enlace inválido o expirado".

- [ ] **Step 8: Commit**

```bash
git add app/Presentation/Controllers/Admin/IntegrationsController.php app/Presentation/Views/admin/integraciones/ app/Presentation/Views/publico/wa_activar.php routes/integrations.php routes/web.php config/container.php
git commit -m "feat(integrations): IntegrationsController (config, test, provisión, QR público) + rutas y bindings"
```

---

### Task 10: Acción de fila `provisionar_demo_wa` en `dom_mkt_leads`

**Files:**
- Modify: `config/cruds/dom_mkt_leads.json`

**Interfaces:**
- Consumes: ruta `GET /admin/integraciones/provision?lead_id={id}` (Task 9). Permiso `integrations.enviar`.
- Produces: botón de fila visible en `/admin/crud/dom_mkt_leads`.

- [ ] **Step 1: Add the row action**

En `config/cruds/dom_mkt_leads.json`, dentro de `actions.row` (replicando el formato de la acción `contrato` de `demo_clientes.json`):
```json
{
  "name": "provisionar_demo_wa",
  "type": "link",
  "label": "Provisionar demo WhatsApp",
  "icon": "bi-whatsapp",
  "route": "/admin/integraciones/provision?lead_id={id}",
  "permission": "integrations.enviar"
}
```

> Verificar las claves exactas que el CRUD Engine espera para `type: link` (mirar la acción `contrato` ya funcionando). Mantener el JSON válido (comas).

- [ ] **Step 2: Validate JSON**

Run: `php -r "json_decode(file_get_contents('config/cruds/dom_mkt_leads.json'), true); echo json_last_error_msg();"`
Expected: "No error".

- [ ] **Step 3: Manual verification**

Arrancar la app, ir a `/admin/crud/dom_mkt_leads` con un usuario con `integrations.enviar`: cada fila muestra "Provisionar demo WhatsApp"; al hacer clic abre el form de provisión con `lead_id` poblado.

- [ ] **Step 4: Commit**

```bash
git add config/cruds/dom_mkt_leads.json
git commit -m "feat(integrations): acción de fila Provisionar demo WhatsApp en dom_mkt_leads"
```

---

### Task 11: Verificación de aceptación end-to-end

**Files:** (sin cambios de código; documentar resultados)

- [ ] **Step 1: Full test suite**

Run: `php tests/run.php`
Expected: misma cantidad de fallos preexistentes que el baseline (las 6 fallas no relacionadas documentadas en memoria), **cero** fallos nuevos en `Integrations`/`Feature`.

- [ ] **Step 2: Recorrer criterios de aceptación del spec (§16)**

Verificar manualmente, marcando cada uno:
- (1) `int_accounts` guarda token cifrado (consultar columna en DB).
- (2) Sin fila default ⇒ envío usa `.env` (Fase 1 intacta); con fila default ⇒ usa DB.
- (3) Guardar instancia interna + "Probar conexión" muestra estado.
- (4) Acción en `dom_mkt_leads` provisiona (auto si Partner; manual si no).
- (5) Tras provisionar, llega correo con link `/wa/activar/...`; aparece en `int_logs`.
- (6) `/wa/activar/{token}` muestra QR; token inválido ⇒ "expirado".
- (7) Visor de logs muestra envíos enmascarados.
- (8) Sin `GREEN_API_PARTNER_TOKEN`, todo el flujo manual funciona.
- (9/10) `GreenApiWhatsappChannel`/dispatcher sin tocar; toggle `integrations` desactiva el módulo; bootstrap SQL re-ejecutable.

- [ ] **Step 3: Commit (cierre)**

```bash
git commit --allow-empty -m "chore(integrations): Fase 2 verificada contra criterios de aceptación"
```

---

## Self-Review (resultado)

**Cobertura del spec:**
- §4.1 `int_accounts` → Task 3. §5 `Crypto` → Task 1. §6 factory creds DB → Task 4. §7 Partner+fallback → Task 5 (connector) + Task 9 (decisión UI). §8 sección Ajustes → Task 8. §9 controller+acción → Task 9 + Task 10. §10 visor logs → Task 6 (repo) + Task 9 (vista). §11 QR público → Task 2 (token) + Task 9 (`activar`). §12 correo demo → Task 7. §13 cambios a piezas existentes → Tasks 3,4,6,8,9,10. §16 aceptación → Task 11.
- Gap consciente: el spec proponía panel custom **dentro** de Ajustes que postea al controller; por la restricción de form único en Ajustes (verificada en código) se resolvió como tarjeta-enlace + página dedicada. Misma intención ("configurable desde Ajustes"), implementación más segura.

**Placeholders:** sin "TBD/TODO"; los puntos "verificar contra X" son instrucciones de validación de API real (router, `core_menu_items`, endpoints Green API), no contenido omitido. El código de cada step está completo.

**Consistencia de tipos:** `IntegrationAccount` (props `instanceId/token/leadId/provisionedVia`) usado igual en Tasks 3,4,7,9. `IntegrationAccountRepositoryInterface` (findDefault/findById/findByLead/save/markDefault) consistente en Tasks 3,4,7,9. `PartnerConnectorInterface` (isAvailable/createInstance) consistente en Tasks 5,7,9. `recent()` consistente en Tasks 6,9. `vista()` consistente en Task 8 y partial. `DemoProvisioningService` (provisionAuto/provisionManual) consistente en Tasks 7,9.
