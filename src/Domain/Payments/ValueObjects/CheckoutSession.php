<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments\ValueObjects;

/** PHP 8.1: no `readonly class` (8.2+); use promoted `readonly` props. */
final class CheckoutSession
{
    public function __construct(
        private readonly string $providerSessionId,
        private readonly string $redirectUrl,
    ) {}

    public function providerSessionId(): string { return $this->providerSessionId; }
    public function redirectUrl(): string { return $this->redirectUrl; }
}
