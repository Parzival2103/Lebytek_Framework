<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Entities;

final class CalendarDefinition
{
    /**
     * @param list<array{field:string,label:string}> $filters
     * @param array<string,string> $colorMap
     */
    private function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly string $resource,
        private readonly string $icon,
        private readonly string $mappingStart,
        private readonly ?string $mappingEnd,
        private readonly ?bool $mappingAllDay,
        private readonly string $mappingTitle,
        private readonly string $colorBy,
        private readonly array $colorMap,
        private readonly string $colorField,
        private readonly string $colorFixed,
        private readonly string $viewsDefault,
        private readonly array $viewsEnabled,
        private readonly bool $createOnSlot,
        private readonly bool $openDetail,
        private readonly bool $editFromEvent,
        private readonly array $filters,
        private readonly bool $dashboardWidget,
    ) {}

    /** @param array<string,mixed> $c */
    public static function fromArray(array $c): self
    {
        $cal   = is_array($c['calendar'] ?? null) ? $c['calendar'] : [];
        $map   = is_array($c['mapping'] ?? null) ? $c['mapping'] : [];
        $color = is_array($map['color'] ?? null) ? $map['color'] : [];
        $views = is_array($c['views'] ?? null) ? $c['views'] : [];
        $inter = is_array($c['interaction'] ?? null) ? $c['interaction'] : [];

        return new self(
            key:           (string)($cal['key'] ?? ''),
            title:         (string)($cal['title'] ?? ''),
            resource:      (string)($cal['resource'] ?? ''),
            icon:          (string)($cal['icon'] ?? 'bi-calendar3'),
            mappingStart:  (string)($map['start'] ?? ''),
            mappingEnd:    isset($map['end']) && $map['end'] !== '' ? (string)$map['end'] : null,
            mappingAllDay: array_key_exists('all_day', $map) ? (bool)$map['all_day'] : null,
            mappingTitle:  (string)($map['title'] ?? ''),
            colorBy:       (string)($color['by'] ?? 'fixed'),
            colorMap:      is_array($color['map'] ?? null) ? $color['map'] : [],
            colorField:    (string)($color['field'] ?? ''),
            colorFixed:    (string)($color['value'] ?? 'primary'),
            viewsDefault:  (string)($views['default'] ?? 'month'),
            viewsEnabled:  array_values(array_map('strval', is_array($views['enabled'] ?? null) ? $views['enabled'] : ['month'])),
            createOnSlot:  (bool)($inter['create_on_slot'] ?? false),
            openDetail:    (bool)($inter['open_detail'] ?? true),
            editFromEvent: (bool)($inter['edit_from_event'] ?? false),
            filters:       array_values(is_array($c['filters'] ?? null) ? $c['filters'] : []),
            dashboardWidget: (bool)($c['dashboard_widget'] ?? false),
        );
    }

    public function key(): string { return $this->key; }
    public function title(): string { return $this->title; }
    public function resource(): string { return $this->resource; }
    public function icon(): string { return $this->icon; }
    public function mappingStart(): string { return $this->mappingStart; }
    public function mappingEnd(): ?string { return $this->mappingEnd; }
    public function mappingAllDay(): ?bool { return $this->mappingAllDay; }
    public function mappingTitle(): string { return $this->mappingTitle; }
    public function colorBy(): string { return $this->colorBy; }
    public function colorMap(): array { return $this->colorMap; }
    public function colorField(): string { return $this->colorField; }
    public function colorFixed(): string { return $this->colorFixed; }
    public function viewsDefault(): string { return $this->viewsDefault; }
    public function viewsEnabled(): array { return $this->viewsEnabled; }
    public function createOnSlot(): bool { return $this->createOnSlot; }
    public function openDetail(): bool { return $this->openDetail; }
    public function editFromEvent(): bool { return $this->editFromEvent; }
    public function filters(): array { return $this->filters; }
    public function dashboardWidget(): bool { return $this->dashboardWidget; }
}
