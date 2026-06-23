<?php

declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class ProvisionResult
{
    /** @param array<string,mixed> $datos */
    public function __construct(
        private readonly bool $ok,
        private readonly ?int $provisionId = null,
        private readonly array $datos = [],
    ) {}

    public function ok(): bool { return $this->ok; }
    public function provisionId(): ?int { return $this->provisionId; }
    /** @return array<string,mixed> */
    public function datos(): array { return $this->datos; }
}
