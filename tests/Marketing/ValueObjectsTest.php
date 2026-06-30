<?php
// tests/Marketing/ValueObjectsTest.php
declare(strict_types=1);

use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;

test('LeadDraft expone sus campos', function (): void {
    $d = new LeadDraft('Ana', 'ana@example.com', '555', 'Hola', ['utm_source' => 'fb']);
    assert_same('Ana', $d->nombre());
    assert_same('ana@example.com', $d->email());
    assert_same('555', $d->telefono());
    assert_same('Hola', $d->mensaje());
    assert_same('fb', $d->utm()['utm_source']);
});

test('LeadResult ok y errores', function (): void {
    $ok = new LeadResult(true, 42);
    assert_same(true, $ok->ok());
    assert_same(42, $ok->leadId());
    $fail = new LeadResult(false, null, ['email' => 'inválido']);
    assert_same(false, $fail->ok());
    assert_same('inválido', $fail->errores()['email']);
});

test('las interfaces de extensión existen', function (): void {
    foreach ([
        \App\Domain\Marketing\Contracts\LandingContentProviderInterface::class,
        \App\Domain\Marketing\Contracts\CommercialPackageSourceInterface::class,
        \App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface::class,
        \App\Domain\Marketing\Contracts\ProvisionAdapterInterface::class,
        \App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class,
    ] as $iface) {
        assert_true(interface_exists($iface), "{$iface} existe");
    }
});
