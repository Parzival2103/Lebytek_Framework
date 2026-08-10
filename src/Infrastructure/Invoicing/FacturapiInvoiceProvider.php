<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Invoicing;

use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderException;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Address;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\FiscalCustomer;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceItem;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceProviderEvent;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceTax;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Money;
use Lebytek\Framework\Infrastructure\Invoicing\Facturapi\FacturapiTransportInterface;
use Lebytek\Framework\Infrastructure\Invoicing\Facturapi\SdkFacturapiTransport;
use RuntimeException;
use Throwable;

final readonly class FacturapiInvoiceProvider implements InvoiceProviderInterface
{
    public function __construct(
        private FacturapiTransportInterface $transport,
        private string $webhookSecret = '',
    )
    {
    }

    /** @param array<string, mixed> $sdkConfig */
    public static function fromSecretKey(
        string $secretKey,
        array $sdkConfig = [],
        string $mode = 'test',
        string $webhookSecret = '',
    ): self
    {
        self::assertSecretKeyMatchesMode($secretKey, $mode);

        return new self(SdkFacturapiTransport::fromSecretKey($secretKey, $sdkConfig), $webhookSecret);
    }

    public static function assertSecretKeyMatchesMode(string $secretKey, string $mode): void
    {
        $secret = trim($secretKey);
        if ($secret === '') {
            throw new InvoiceProviderException('Facturapi secret_key vacío con proveedor habilitado.');
        }

        $normalizedMode = strtolower(trim($mode)) === 'live' ? 'live' : 'test';
        $expectedPrefix = $normalizedMode === 'live' ? 'sk_live_' : 'sk_test_';
        if (! str_starts_with($secret, $expectedPrefix)) {
            throw new InvoiceProviderException(
                "Facturapi secret_key no coincide con mode={$normalizedMode} (se espera prefijo {$expectedPrefix})."
            );
        }
    }

    public function key(): string
    {
        return 'facturapi';
    }

    public function createInvoice(InvoiceDraft $draft, string $idempotencyKey = ''): IssuedInvoice
    {
        $payload = $this->mapDraft($draft, $idempotencyKey);

        try {
            $response = $this->transport->create($payload);
        } catch (Throwable $exception) {
            $this->fail('create invoice', $exception);
        }

        return $this->mapIssuedInvoice($response, $draft->sourceRef());
    }

    public function externalIdForIssue(string $idempotencyKey): string
    {
        return FacturapiExternalId::forIssueClaim($this->key(), $idempotencyKey);
    }

    public function retrieveInvoice(string $providerInvoiceId): IssuedInvoice
    {
        try {
            $response = $this->transport->retrieve($providerInvoiceId);
        } catch (Throwable $exception) {
            $this->fail('retrieve invoice', $exception);
        }

        return $this->mapIssuedInvoice($response);
    }

    public function parseWebhook(string $rawBody, string $signature): InvoiceProviderEvent
    {
        FacturapiWebhookSignature::assertValid($rawBody, $signature, $this->webhookSecret);

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvoiceProviderException('Facturapi webhook payload is invalid JSON.', previous: $exception);
        }

        if (! is_array($payload)) {
            throw new InvoiceProviderException('Facturapi webhook payload must be a JSON object.');
        }

        $object = $payload['data']['object'] ?? [];
        if (! is_array($object)) {
            $object = [];
        }

        $providerEventId = $this->stringValue($payload['id'] ?? null);
        if ($providerEventId === null) {
            throw new InvoiceProviderException('Facturapi webhook payload missing event id.');
        }

        return new InvoiceProviderEvent(
            providerEventId: $providerEventId,
            type: $this->stringValue($payload['type'] ?? null) ?? '',
            providerInvoiceId: $this->stringValue($object['id'] ?? null) ?? '',
            status: $this->stringValue($object['status'] ?? null) ?? '',
            meta: [],
        );
    }

    /** @return IssuedInvoice[] */
    public function listByExternalId(string $externalId): array
    {
        try {
            $rows = $this->transport->listByExternalId($externalId);
        } catch (Throwable $exception) {
            $this->fail('list invoices by external id', $exception);
        }

        return array_map(fn (array $row): IssuedInvoice => $this->mapIssuedInvoice($row), $rows);
    }

    public function cancelInvoice(string $providerInvoiceId, InvoiceCancellation $cancellation): IssuedInvoice
    {
        $payload = ['motive' => $cancellation->motive()];
        if ($cancellation->substitution() !== null) {
            $payload['substitution'] = $cancellation->substitution();
        }

        try {
            $response = $this->transport->cancel($providerInvoiceId, $payload);
        } catch (Throwable $exception) {
            $this->fail('cancel invoice', $exception);
        }

        return $this->mapIssuedInvoice($response);
    }

    public function downloadPdf(string $providerInvoiceId): string
    {
        try {
            return $this->transport->pdf($providerInvoiceId);
        } catch (Throwable $exception) {
            $this->fail('download PDF', $exception);
        }
    }

    public function downloadXml(string $providerInvoiceId): string
    {
        try {
            return $this->transport->xml($providerInvoiceId);
        } catch (Throwable $exception) {
            $this->fail('download XML', $exception);
        }
    }

    public function sendByEmail(string $providerInvoiceId, string $email): void
    {
        try {
            $this->transport->email($providerInvoiceId, $email);
        } catch (Throwable $exception) {
            $this->fail('send invoice email', $exception);
        }
    }

    /** @return array<string, mixed> */
    private function mapDraft(InvoiceDraft $draft, string $idempotencyKey): array
    {
        $payload = [
            'type' => 'I',
        ];

        if ($idempotencyKey !== '') {
            $payload['idempotency_key'] = $idempotencyKey;
            $payload['external_id'] = $this->externalIdForIssue($idempotencyKey);
        }

        return array_merge($payload, [
            'customer' => $this->mapCustomer($draft->customer()),
            'items' => array_map(fn (InvoiceItem $item): array => $this->mapItem($item), $draft->items()),
            'payment_form' => $draft->paymentForm()->value,
            'payment_method' => $draft->paymentMethod()->value,
            'use' => $draft->cfdiUse()->value,
            'currency' => $draft->currency(),
        ]);
    }

    /** @return array<string, mixed> */
    private function mapCustomer(FiscalCustomer $customer): array
    {
        $payload = [
            'legal_name' => $customer->legalName(),
            'tax_id' => $customer->taxId(),
            'tax_system' => $customer->taxSystem(),
        ];
        if ($customer->email() !== null) {
            $payload['email'] = $customer->email();
        }
        $payload['address'] = $this->mapAddress($customer->address());

        return $payload;
    }

    /** @return array<string, string> */
    private function mapAddress(Address $address): array
    {
        $payload = [
            'zip' => $address->zip(),
            'country' => $address->country(),
        ];
        if ($address->street() !== null) {
            $payload['street'] = $address->street();
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function mapItem(InvoiceItem $item): array
    {
        $product = [
            'description' => $item->description(),
            'product_key' => $item->productKey(),
            'unit_key' => $item->unitKey(),
        ];
        $product['price'] = $this->majorAmount($item->unitPrice());
        $product['tax_included'] = false;
        $product['taxability'] = '02';
        $product['taxes'] = $this->mapTaxes($item);

        return [
            'quantity' => $item->quantity(),
            'product' => $product,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function mapTaxes(InvoiceItem $item): array
    {
        if ($item->taxExempt()) {
            return [[
                'type' => 'IVA',
                'rate' => 0.0,
                'factor' => 'Exento',
            ]];
        }

        return array_map(fn (InvoiceTax $tax): array => [
            'type' => $tax->type(),
            'rate' => $tax->rate(),
            'factor' => $tax->factor(),
        ], $item->taxes());
    }

    private function majorAmount(Money $money): float
    {
        return (float) number_format($money->amountMinor() / 100, 2, '.', '');
    }

    /** @param array<string, mixed> $response */
    private function mapIssuedInvoice(array $response, ?string $sourceRef = null): IssuedInvoice
    {
        $providerInvoiceId = $this->stringValue($response['id'] ?? null);
        if ($providerInvoiceId === null) {
            throw new InvoiceProviderException('Facturapi response missing invoice id');
        }

        $uuid = $this->stringValue($response['uuid'] ?? null)
            ?? $this->stringValue($response['stamp']['uuid'] ?? null)
            ?? '';
        $rawStatus = $this->stringValue($response['status'] ?? null) ?? '';

        return new IssuedInvoice(
            providerInvoiceId: $providerInvoiceId,
            uuid: $uuid,
            status: InvoiceStatus::fromProvider($rawStatus),
            folioNumber: $this->stringValue($response['folio_number'] ?? null),
            sourceRef: $sourceRef,
            pdfUrl: $this->stringValue($response['pdf_url'] ?? null),
            xmlUrl: $this->stringValue($response['xml_url'] ?? null),
            meta: ['provider_status' => $rawStatus],
        );
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function fail(string $operation, Throwable $exception): never
    {
        throw new InvoiceProviderException(
            'Facturapi ' . $operation . ' failed',
            0,
            $this->sanitizedPrevious($exception),
        );
    }

    private function sanitizedPrevious(Throwable $exception): Throwable
    {
        return new RuntimeException(
            $this->sanitizeSecretTokens($exception->getMessage()),
            (int) $exception->getCode(),
        );
    }

    private function sanitizeSecretTokens(string $message): string
    {
        return InvoiceSecretScrubber::sanitizeSecretTokens($message);
    }
}
