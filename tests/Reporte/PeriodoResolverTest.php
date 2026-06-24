<?php
declare(strict_types=1);

use App\Application\Reporte\PeriodoResolver;

function pr_now(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-06-14 15:00:00');
}

test('mes devuelve el primer y último día del mes vigente', function (): void {
    $r = (new PeriodoResolver())->resolve('mes', pr_now());
    assert_same('2026-06-01 00:00:00', $r['from']);
    assert_same('2026-06-30 23:59:59', $r['to']);
    assert_same('Este mes', $r['label']);
});

test('hoy cubre el día completo', function (): void {
    $r = (new PeriodoResolver())->resolve('hoy', pr_now());
    assert_same('2026-06-14 00:00:00', $r['from']);
    assert_same('2026-06-14 23:59:59', $r['to']);
});

test('semana va de lunes a domingo', function (): void {
    $r = (new PeriodoResolver())->resolve('semana', pr_now());
    assert_same('2026-06-08 00:00:00', $r['from']);
    assert_same('2026-06-14 23:59:59', $r['to']);
});

test('mes_pasado devuelve mayo completo', function (): void {
    $r = (new PeriodoResolver())->resolve('mes_pasado', pr_now());
    assert_same('2026-05-01 00:00:00', $r['from']);
    assert_same('2026-05-31 23:59:59', $r['to']);
});

test('anio devuelve el año vigente completo', function (): void {
    $r = (new PeriodoResolver())->resolve('anio', pr_now());
    assert_same('2026-01-01 00:00:00', $r['from']);
    assert_same('2026-12-31 23:59:59', $r['to']);
});

test('anio_pasado devuelve 2025 completo', function (): void {
    $r = (new PeriodoResolver())->resolve('anio_pasado', pr_now());
    assert_same('2025-01-01 00:00:00', $r['from']);
    assert_same('2025-12-31 23:59:59', $r['to']);
});

test('ayer cubre el día anterior', function (): void {
    $r = (new PeriodoResolver())->resolve('ayer', pr_now());
    assert_same('2026-06-13 00:00:00', $r['from']);
    assert_same('2026-06-13 23:59:59', $r['to']);
});

test('todo abarca un rango amplio con etiqueta Todo', function (): void {
    $r = (new PeriodoResolver())->resolve('todo', pr_now());
    assert_same('1970-01-01 00:00:00', $r['from']);
    assert_same('2999-12-31 23:59:59', $r['to']);
    assert_same('Todo', $r['label']);
});

test('preset desconocido cae a todo', function (): void {
    $r = (new PeriodoResolver())->resolve('decada', pr_now());
    assert_same('1970-01-01 00:00:00', $r['from']);
    assert_same('Todo', $r['label']);
});
