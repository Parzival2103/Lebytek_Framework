<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

use InvalidArgumentException;

final readonly class InvoiceProviderEvent
{
    /** @var array<string, mixed> */
    private array $meta;

    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private string $providerEventId,
        private string $type,
        private string $providerInvoiceId,
        private string $status,
        array $meta = [],
    ) {
        if (trim($providerEventId) === '') {
            throw new InvalidArgumentException('Invoice provider event id cannot be empty.');
        }

        $this->meta = $this->sanitizeMeta($meta);
    }

    public function providerEventId(): string
    {
        return $this->providerEventId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function providerInvoiceId(): string
    {
        return $this->providerInvoiceId;
    }

    public function status(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function sanitizeMeta(array $meta): array
    {
        $safe = [];
        foreach ($meta as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if ($this->isUnsafeMetaKey($normalizedKey) || is_array($value) || is_object($value)) {
                continue;
            }

            if ($value === null || is_scalar($value)) {
                $safe[$normalizedKey] = $value;
            }
        }

        return $safe;
    }

    private function isUnsafeMetaKey(string $key): bool
    {
        if ($key === '') {
            return true;
        }

        foreach (['customer', 'items', 'line', 'pdf', 'xml', 'tax_id', 'rfc', 'legal_name'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
