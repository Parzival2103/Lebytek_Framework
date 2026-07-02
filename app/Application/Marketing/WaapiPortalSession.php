<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Infrastructure\Integrations\LebytekApi\ClientTenantApiClient;
use Lebytek\Framework\Kernel\Security\Session;

final class WaapiPortalSession
{
    private const SESSION_KEY = 'waapi_portal';

    public function __construct(
        private readonly ClientTenantApiClient $apiClient,
    ) {}

    public function login(string $plainToken): bool
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return false;
        }

        if (! $this->apiClient->validateToken($plainToken)) {
            return false;
        }

        Session::set(self::SESSION_KEY, [
            'token' => base64_encode($plainToken),
            'authenticated_at' => time(),
        ]);

        return true;
    }

    public function token(): ?string
    {
        $data = Session::get(self::SESSION_KEY);
        if (! is_array($data) || empty($data['token'])) {
            return null;
        }

        $decoded = base64_decode((string) $data['token'], true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    public function isAuthenticated(): bool
    {
        return $this->token() !== null;
    }

    public function logout(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
