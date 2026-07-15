<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments\ValueObjects;

final readonly class CheckoutSession
{
    public function __construct(
        private string $providerSessionId,
        private string $redirectUrl,
    ) {}

    public function providerSessionId(): string { return $this->providerSessionId; }
    public function redirectUrl(): string { return $this->redirectUrl; }
}
