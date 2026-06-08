<?php

declare(strict_types=1);

namespace App\Domain\Entities;

final class CrudFieldDefinition
{
    public function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly string $type = 'text',
        private readonly bool $required = false,
        private readonly bool $readonly = false,
        private readonly bool $hidden = false,
        private readonly string $col = 'col-12',
        private readonly array $options = [],
        private readonly array $validation = [],
        private readonly mixed $defaultValue = null,
        private readonly ?string $format = null,
        private readonly array $badge = [],
        private readonly ?string $helpText = null,
        private readonly ?string $relation = null
    ) {}

    public static function fromArray(array $data): self
    {
        $help = $data['help_text'] ?? $data['helpText'] ?? null;

        return new self(
            name: (string) ($data['name'] ?? ''),
            label: (string) ($data['label'] ?? ($data['name'] ?? '')),
            type: (string) ($data['type'] ?? 'text'),
            required: (bool) ($data['required'] ?? false),
            readonly: (bool) ($data['readonly'] ?? false),
            hidden: (bool) ($data['hidden'] ?? false),
            col: (string) ($data['col'] ?? 'col-12'),
            options: is_array($data['options'] ?? null) ? $data['options'] : [],
            validation: is_array($data['validation'] ?? null) ? $data['validation'] : [],
            defaultValue: $data['default'] ?? null,
            format: isset($data['format']) ? (string) $data['format'] : null,
            badge: is_array($data['badge'] ?? null) ? $data['badge'] : [],
            helpText: is_string($help) && $help !== '' ? $help : null,
            relation: isset($data['relation']) && $data['relation'] !== '' ? (string) $data['relation'] : null
        );
    }

    public function name(): string { return $this->name; }
    public function label(): string { return $this->label; }
    public function type(): string { return $this->type; }
    public function required(): bool { return $this->required; }
    public function readonly(): bool { return $this->readonly; }
    public function hidden(): bool { return $this->hidden; }
    public function col(): string { return $this->col; }
    public function options(): array { return $this->options; }
    public function validation(): array { return $this->validation; }
    public function defaultValue(): mixed { return $this->defaultValue; }
    public function format(): ?string { return $this->format; }
    public function badge(): array { return $this->badge; }
    public function helpText(): ?string { return $this->helpText; }
    public function relation(): ?string { return $this->relation; }
}
