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
        private readonly ?string $emailVerifyToken = null,
        private readonly ?string $emailVerifyCode = null,
    ) {}

    public function ok(): bool { return $this->ok; }
    public function leadId(): ?int { return $this->leadId; }
    /** @return array<string,string> */
    public function errores(): array { return $this->errores; }
    public function emailVerifyToken(): ?string { return $this->emailVerifyToken; }
    public function emailVerifyCode(): ?string { return $this->emailVerifyCode; }

    public function withLeadId(int $id): self
    {
        return new self(true, $id, $this->errores, $this->emailVerifyToken, $this->emailVerifyCode);
    }

    public function withEmailVerification(string $token, string $code): self
    {
        return new self($this->ok, $this->leadId, $this->errores, $token, $code);
    }
}
