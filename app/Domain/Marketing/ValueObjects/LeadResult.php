<?php

declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class LeadResult
{
    /** @param array<string,string> $errores */
    public function __construct(
        private readonly bool $ok,
        private readonly ?int $leadId = null,
        private readonly array $errores = [],
    ) {}

    public function ok(): bool { return $this->ok; }
    public function leadId(): ?int { return $this->leadId; }
    /** @return array<string,string> */
    public function errores(): array { return $this->errores; }
    public function withLeadId(int $id): self { return new self(true, $id, $this->errores); }
}
