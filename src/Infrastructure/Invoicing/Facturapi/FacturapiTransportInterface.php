<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Invoicing\Facturapi;

interface FacturapiTransportInterface
{
    /** @param array<string, mixed> $payload */
    public function create(array $payload): array;

    /** @param array<string, mixed> $payload */
    public function cancel(string $providerInvoiceId, array $payload): array;

    public function pdf(string $providerInvoiceId): string;

    public function xml(string $providerInvoiceId): string;

    public function email(string $providerInvoiceId, string $email): array;
}
