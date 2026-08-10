<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\Exceptions;

use RuntimeException;
use Throwable;

final class InvoiceAmbiguousCreate extends RuntimeException
{
    public function __construct(
        private readonly string $providerKey,
        private readonly string $idempotencyKey,
        private readonly string $sourceRef,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Invoice create is ambiguous for provider "%s", idempotency key "%s" and source "%s"; keep the claim and reconcile before retrying.',
                $providerKey,
                $idempotencyKey,
                $sourceRef,
            ),
            0,
            $previous,
        );
    }

    public function providerKey(): string
    {
        return $this->providerKey;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function sourceRef(): string
    {
        return $this->sourceRef;
    }
}
