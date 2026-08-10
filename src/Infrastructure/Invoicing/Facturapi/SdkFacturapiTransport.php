<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Invoicing\Facturapi;

use Facturapi\Facturapi;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderException;

final readonly class SdkFacturapiTransport implements FacturapiTransportInterface
{
    public function __construct(private Facturapi $client)
    {
    }

    /** @param array<string, mixed> $config */
    public static function fromSecretKey(string $secretKey, array $config = []): self
    {
        if (trim($secretKey) === '') {
            throw new InvoiceProviderException('Facturapi secret_key vacío con proveedor habilitado.');
        }

        return new self(new Facturapi($secretKey, $config !== [] ? $config : null));
    }

    public function create(array $payload): array
    {
        return $this->normalizeArray($this->client->Invoices->create($payload));
    }

    public function cancel(string $providerInvoiceId, array $payload): array
    {
        return $this->normalizeArray($this->client->Invoices->cancel($providerInvoiceId, $payload));
    }

    public function pdf(string $providerInvoiceId): string
    {
        return $this->client->Invoices->downloadPdf($providerInvoiceId);
    }

    public function xml(string $providerInvoiceId): string
    {
        return $this->client->Invoices->downloadXml($providerInvoiceId);
    }

    public function email(string $providerInvoiceId, string $email): array
    {
        return $this->normalizeArray($this->client->Invoices->sendByEmail($providerInvoiceId, $email));
    }

    /** @return array<string, mixed> */
    private function normalizeArray(mixed $response): array
    {
        if ($response === null) {
            return [];
        }
        if (is_array($response)) {
            return $response;
        }
        if (is_object($response)) {
            return json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }

        return ['value' => $response];
    }
}
