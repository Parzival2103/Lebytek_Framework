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
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceTax;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Money;
use Lebytek\Framework\Infrastructure\Invoicing\Facturapi\FacturapiTransportInterface;
use Lebytek\Framework\Infrastructure\Invoicing\Facturapi\SdkFacturapiTransport;
use RuntimeException;
use Throwable;

final readonly class FacturapiInvoiceProvider implements InvoiceProviderInterface
{
    public function __construct(private FacturapiTransportInterface $transport)
    {
    }

    /** @param array<string, mixed> $sdkConfig */
    public static function fromSecretKey(string $secretKey, array $sdkConfig = []): self
    {
        return new self(SdkFacturapiTransport::fromSecretKey($secretKey, $sdkConfig));
    }

    public function key(): string
    {
        return 'facturapi';
    }

    public function createInvoice(InvoiceDraft $draft): IssuedInvoice
    {
        $payload = $this->mapDraft($draft);

        try {
            $response = $this->transport->create($payload);
        } catch (Throwable $exception) {
            $this->fail('create invoice', $exception);
        }

        return $this->mapIssuedInvoice($response, $draft->sourceRef());
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
    private function mapDraft(InvoiceDraft $draft): array
    {
        return [
            'type' => 'I',
            'customer' => $this->mapCustomer($draft->customer()),
            'items' => array_map(fn (InvoiceItem $item): array => $this->mapItem($item), $draft->items()),
            'payment_form' => $draft->paymentForm()->value,
            'use' => $draft->cfdiUse()->value,
            'currency' => $draft->currency(),
        ];
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
        ];
        if ($item->unitKey() !== null) {
            $product['unit_key'] = $item->unitKey();
        }
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
        $sanitized = preg_replace('/sk_(test|live)_[A-Za-z0-9]+/', '[redacted]', $message);

        return is_string($sanitized) ? $sanitized : $message;
    }
}
