<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\Exceptions;

use RuntimeException;

final class InvoiceProviderIdConflict extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $providerKey,
        private readonly string $idempotencyKey,
        private readonly string $existingId,
        private readonly string $attemptedId,
    ) {
        parent::__construct($message);
    }

    public function providerKey(): string
    {
        return $this->providerKey;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function existingId(): string
    {
        return $this->existingId;
    }

    public function attemptedId(): string
    {
        return $this->attemptedId;
    }
}
