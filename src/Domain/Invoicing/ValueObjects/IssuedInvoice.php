<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;

final readonly class IssuedInvoice
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        private string $providerInvoiceId,
        private string $uuid,
        private InvoiceStatus $status,
        private ?string $folioNumber = null,
        private ?string $sourceRef = null,
        private ?string $pdfUrl = null,
        private ?string $xmlUrl = null,
        private array $meta = [],
    ) {}

    public function providerInvoiceId(): string
    {
        return $this->providerInvoiceId;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function status(): InvoiceStatus
    {
        return $this->status;
    }

    public function folioNumber(): ?string
    {
        return $this->folioNumber;
    }

    public function sourceRef(): ?string
    {
        return $this->sourceRef;
    }

    public function pdfUrl(): ?string
    {
        return $this->pdfUrl;
    }

    public function xmlUrl(): ?string
    {
        return $this->xmlUrl;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->meta;
    }
}
