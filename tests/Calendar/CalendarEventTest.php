<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Calendar\CalendarEvent;

test('CalendarEvent::toArray expone forma JSON estable', function (): void {
    $e = new CalendarEvent(
        id: 7, title: 'Cita López', start: '2026-06-09 10:00:00',
        end: '2026-06-09 11:00:00', allDay: false, color: 'success',
        url: '/admin/crud/demo_citas/7'
    );
    assert_same(
        ['id' => 7, 'title' => 'Cita López', 'start' => '2026-06-09 10:00:00',
         'end' => '2026-06-09 11:00:00', 'allDay' => false, 'color' => 'success',
         'url' => '/admin/crud/demo_citas/7'],
        $e->toArray(),
        'forma JSON'
    );
});

test('CalendarEvent admite end nulo', function (): void {
    $e = new CalendarEvent(id: 1, title: 'X', start: '2026-06-09', end: null,
        allDay: true, color: 'primary', url: '/x');
    assert_same(null, $e->toArray()['end'], 'end nulo se preserva');
});
