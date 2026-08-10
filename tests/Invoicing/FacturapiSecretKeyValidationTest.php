<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\InvoicingFactory;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderException;
use Lebytek\Framework\Infrastructure\Invoicing\FacturapiInvoiceProvider;

test('fromSecretKey rechaza secret vacío', function (): void {
    assert_throws(InvoiceProviderException::class, function (): void {
        FacturapiInvoiceProvider::fromSecretKey('', [], 'test');
    });
});

test('fromSecretKey rechaza mode=test con sk_live_', function (): void {
    assert_throws(InvoiceProviderException::class, function (): void {
        FacturapiInvoiceProvider::fromSecretKey('sk_live_abc123', [], 'test');
    });
});

test('fromSecretKey rechaza mode=live con sk_test_', function (): void {
    assert_throws(InvoiceProviderException::class, function (): void {
        FacturapiInvoiceProvider::fromSecretKey('sk_test_abc123', [], 'live');
    });
});

test('fromSecretKey acepta sk_test_ con mode=test', function (): void {
    $provider = FacturapiInvoiceProvider::fromSecretKey('sk_test_abc123', [], 'test');
    assert_same('facturapi', $provider->key());
});

test('InvoicingFactory buildProviders rechaza enabled con secret vacío', function (): void {
    InvoicingFactory::resetCached();
    assert_throws(InvoiceProviderException::class, function (): void {
        InvoicingFactory::buildProviders([
            'facturapi' => [
                'driver' => 'facturapi',
                'enabled' => true,
                'config' => ['secret_key' => '   ', 'mode' => 'test'],
            ],
        ]);
    });
});

test('InvoicingFactory buildProviders rechaza mismatch mode/key al construir', function (): void {
    InvoicingFactory::resetCached();
    assert_throws(InvoiceProviderException::class, function (): void {
        InvoicingFactory::buildProviders([
            'facturapi' => [
                'driver' => 'facturapi',
                'enabled' => true,
                'config' => ['secret_key' => 'sk_live_x', 'mode' => 'test'],
            ],
        ]);
    });
});
