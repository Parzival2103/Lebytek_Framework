<?php
declare(strict_types=1);

use App\Domain\Calendar\DateRange;

test('DateRange::forMonth abarca el mes completo', function (): void {
    $r = DateRange::forMonth(2026, 6);
    assert_same('2026-06-01 00:00:00', $r->from()->format('Y-m-d H:i:s'), 'inicio de mes');
    assert_same('2026-06-30 23:59:59', $r->to()->format('Y-m-d H:i:s'), 'fin de mes');
});

test('DateRange::forDay abarca un solo día', function (): void {
    $r = DateRange::forDay(new DateTimeImmutable('2026-06-09 14:00:00'));
    assert_same('2026-06-09 00:00:00', $r->from()->format('Y-m-d H:i:s'), 'inicio del día');
    assert_same('2026-06-09 23:59:59', $r->to()->format('Y-m-d H:i:s'), 'fin del día');
});

test('DateRange::forWeek abarca lunes a domingo', function (): void {
    $r = DateRange::forWeek(new DateTimeImmutable('2026-06-09')); // martes
    assert_same('2026-06-08', $r->from()->format('Y-m-d'), 'lunes');
    assert_same('2026-06-14', $r->to()->format('Y-m-d'), 'domingo');
});

test('DateRange::forWeek desde un lunes permanece en ese lunes', function (): void {
    $r = DateRange::forWeek(new DateTimeImmutable('2026-06-08')); // lunes
    assert_same('2026-06-08', $r->from()->format('Y-m-d'), 'lunes ancla');
    assert_same('2026-06-14', $r->to()->format('Y-m-d'), 'domingo');
});

test('DateRange::fromStrings parsea desde/hasta y normaliza límites', function (): void {
    $r = DateRange::fromStrings('2026-06-01', '2026-06-30');
    assert_same('2026-06-01 00:00:00', $r->from()->format('Y-m-d H:i:s'), 'desde 00:00');
    assert_same('2026-06-30 23:59:59', $r->to()->format('Y-m-d H:i:s'), 'hasta 23:59:59');
});
