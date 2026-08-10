<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;

function fakeInvoiceProvider(string $key): InvoiceProviderInterface
{
    return new class($key) implements InvoiceProviderInterface {
        public function __construct(private string $k) {}

        public function key(): string
        {
            return $this->k;
        }

        public function createInvoice(InvoiceDraft $draft, string $idempotencyKey = ''): IssuedInvoice
        {
            return new IssuedInvoice('inv_x', 'uuid_x', InvoiceStatus::Valid);
        }

        public function externalIdForIssue(string $idempotencyKey): string
        {
            return 'fake-external:' . $idempotencyKey;
        }

        public function cancelInvoice(string $providerInvoiceId, InvoiceCancellation $cancellation): IssuedInvoice
        {
            return new IssuedInvoice($providerInvoiceId, 'uuid_x', InvoiceStatus::Canceled);
        }

        public function downloadPdf(string $providerInvoiceId): string
        {
            return 'pdf';
        }

        public function downloadXml(string $providerInvoiceId): string
        {
            return 'xml';
        }

        public function sendByEmail(string $providerInvoiceId, string $email): void
        {
        }
    };
}

test('InvoiceProviderRegistry memoiza providers', function (): void {
    $registry = new InvoiceProviderRegistry([
        'facturapi' => ['driver' => 'facturapi', 'factory' => fn () => fakeInvoiceProvider('facturapi')],
    ]);
    assert_true($registry->has('facturapi'));
    assert_true($registry->get('facturapi') === $registry->get('facturapi'));
    assert_same('facturapi', $registry->driver('facturapi'));
});

test('InvoiceProviderRegistry reporta providers ausentes y falla al resolverlos', function (): void {
    $registry = new InvoiceProviderRegistry([]);
    assert_false($registry->has('facturapi'));
    assert_same('unknown', $registry->driver('facturapi'));
    assert_throws(\RuntimeException::class, fn () => $registry->get('facturapi'));
});
