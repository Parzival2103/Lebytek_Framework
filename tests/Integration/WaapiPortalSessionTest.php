<?php

declare(strict_types=1);

use App\Application\Marketing\WaapiPortalSession;
use App\Infrastructure\Integrations\LebytekApi\ClientTenantApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Kernel\Security\Session;

final class FakeTenantTransport implements LebytekApiTransport
{
    /** @var list<string> */
    public array $lastHeaders = [];

    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        $this->lastHeaders = $headers;

        return [
            'status' => 200,
            'body' => json_encode(['data' => [['publicId' => '01JINST', 'status' => 'waiting_qr']]], JSON_THROW_ON_ERROR),
            'error' => '',
        ];
    }
}

test('WaapiPortalSession stores tenant token server-side after api validation', function () {
    Session::start();
    $transport = new FakeTenantTransport();
    $client = new ClientTenantApiClient($transport, 'https://api.lebytek.com/api/v1');
    $session = new WaapiPortalSession($client);

    assert_true($session->login('tenant-token-plain'));
    assert_true(in_array('Authorization: Bearer tenant-token-plain', $transport->lastHeaders, true));
    assert_true($session->isAuthenticated());
    $session->logout();
    assert_true(! $session->isAuthenticated());
});

test('WaapiPortalSession rejects invalid token', function () {
    Session::start();
    $badTransport = new class implements LebytekApiTransport {
        public function execute(string $method, string $url, array $headers, ?string $body): array
        {
            return ['status' => 401, 'body' => '{"message":"Unauthorized"}', 'error' => ''];
        }
    };
    $badSession = new WaapiPortalSession(new ClientTenantApiClient($badTransport, 'https://api.lebytek.com/api/v1'));
    assert_true(! $badSession->login('invalid'));
});
