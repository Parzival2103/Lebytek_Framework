<?php

declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class Provision
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        private readonly int $id,
        private readonly ?int $leadId,
        private readonly string $accessToken,
        private readonly string $estado,
        private readonly array $payload = [],
    ) {}

    public function id(): int { return $this->id; }
    public function leadId(): ?int { return $this->leadId; }
    public function accessToken(): string { return $this->accessToken; }
    public function estado(): string { return $this->estado; }
    /** @return array<string,mixed> */
    public function payload(): array { return $this->payload; }
}
