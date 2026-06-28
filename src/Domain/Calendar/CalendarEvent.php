<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Calendar;

final class CalendarEvent
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $start,
        public readonly ?string $end,
        public readonly bool $allDay,
        public readonly string $color,
        public readonly string $url,
    ) {}

    /** @return array{id:int,title:string,start:string,end:?string,allDay:bool,color:string,url:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'title' => $this->title, 'start' => $this->start,
            'end' => $this->end, 'allDay' => $this->allDay, 'color' => $this->color,
            'url' => $this->url,
        ];
    }
}
