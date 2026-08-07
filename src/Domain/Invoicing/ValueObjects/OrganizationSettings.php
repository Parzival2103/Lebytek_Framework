<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

final readonly class OrganizationSettings
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        private string $providerKey,
        private string $mode,
        private ?string $externalOrgId = null,
        private ?string $label = null,
        private array $meta = [],
    ) {
        if (! in_array($mode, ['test', 'live'], true)) {
            throw new \InvalidArgumentException('mode must be test or live');
        }
    }

    public function providerKey(): string
    {
        return $this->providerKey;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function externalOrgId(): ?string
    {
        return $this->externalOrgId;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->meta;
    }
}
