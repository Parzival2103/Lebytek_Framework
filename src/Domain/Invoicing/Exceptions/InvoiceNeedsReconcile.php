<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\Exceptions;

use RuntimeException;
use Throwable;

final class InvoiceNeedsReconcile extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $providerInvoiceId,
        private readonly string $providerKey,
        private readonly string $idempotencyKey,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function providerInvoiceId(): string
    {
        return $this->providerInvoiceId;
    }

    public function providerKey(): string
    {
        return $this->providerKey;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }
}
