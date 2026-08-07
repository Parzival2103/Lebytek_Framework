<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry;
use Lebytek\Framework\Application\Invoicing\InvoicingFactory;

test('InvoicingFactory lanza si driver no soportado', function (): void {
    InvoicingFactory::resetCached();
    assert_throws(\RuntimeException::class, function (): void {
        InvoicingFactory::buildProviders([
            'bad' => ['driver' => 'unknown', 'enabled' => true, 'config' => []],
        ]);
    });
});

test('InvoicingFactory omite providers deshabilitados', function (): void {
    InvoicingFactory::resetCached();
    $providers = InvoicingFactory::buildProviders([
        'facturapi' => ['driver' => 'facturapi', 'enabled' => false, 'config' => []],
    ]);
    assert_same([], $providers);
});

test('InvoicingFactory incluye facturapi habilitado', function (): void {
    InvoicingFactory::resetCached();
    $providers = InvoicingFactory::buildProviders([
        'facturapi' => [
            'driver'  => 'facturapi',
            'enabled' => true,
            'config'  => ['secret_key' => 'sk_test', 'mode' => 'test'],
        ],
    ]);
    assert_true(isset($providers['facturapi']));
    assert_same('facturapi', $providers['facturapi']['driver']);

    $registry = new InvoiceProviderRegistry($providers);
    assert_true($registry->has('facturapi'));
    assert_same('facturapi', $registry->driver('facturapi'));
    assert_same('facturapi', $registry->get('facturapi')->key());
});
