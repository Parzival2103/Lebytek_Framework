<?php

declare(strict_types=1);

namespace App\Application\Marketing;

/**
 * Cookie the Application layer wants Presentation to set/clear. Application
 * never calls setcookie() itself (Anti-deuda §A) — it only queues specs.
 */
final class CookieSpec
{
    public function __construct(
        public readonly string $name,
        public readonly string $value = '',
        public readonly ?int $ttlDays = null,
        public readonly ?int $maxAgeSeconds = null,
        public readonly string $path = '/',
        public readonly bool $httpOnly = true,
        public readonly string $sameSite = 'Lax',
        // Default false: the controller resolves the real value from
        // AssignInput::$isHttps / APP_URL (Anti-deuda §X) and applies its own
        // `secure` flag when calling setcookie(); this field is not authoritative.
        public readonly bool $secure = false,
        public readonly bool $delete = false,
    ) {
    }

    public static function delete(string $name): self
    {
        return new self(name: $name, delete: true);
    }
}
