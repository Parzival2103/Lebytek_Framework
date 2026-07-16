<?php

declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class LeadDraft
{
    /**
     * @param array<string,string> $utm
     * `$landingVariant`/`$visitorId` van al final para conservar BC de los
     * call sites existentes que construyen `LeadDraft` con args posicionales.
     */
    public function __construct(
        private readonly string $nombre,
        private readonly string $email,
        private readonly ?string $telefono = null,
        private readonly ?string $mensaje = null,
        private readonly array $utm = [],
        private readonly ?string $landingVariant = null,
        private readonly ?string $visitorId = null,
    ) {}

    public function nombre(): string { return $this->nombre; }
    public function email(): string { return $this->email; }
    public function telefono(): ?string { return $this->telefono; }
    public function mensaje(): ?string { return $this->mensaje; }
    /** @return array<string,string> */
    public function utm(): array { return $this->utm; }
    public function landingVariant(): ?string { return $this->landingVariant; }
    public function visitorId(): ?string { return $this->visitorId; }
}
