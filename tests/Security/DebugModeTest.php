<?php

declare(strict_types=1);

use App\Kernel\Config\DebugMode;

// ── Caracterización: entornos no-producción conservan el flag configurado ────
test('DebugMode respeta debug=true en local', function (): void {
    assert_same(true, DebugMode::resolve('local', true));
});

test('DebugMode respeta debug=false en local', function (): void {
    assert_same(false, DebugMode::resolve('local', false));
});

test('DebugMode respeta debug=true en staging', function (): void {
    assert_same(true, DebugMode::resolve('staging', true));
});

// ── Seguridad: producción fuerza false sin importar el config ────────────────
test('DebugMode fuerza false en production aunque debug sea true', function (): void {
    assert_same(false, DebugMode::resolve('production', true));
});

test('DebugMode es case-insensitive para production', function (): void {
    assert_same(false, DebugMode::resolve('PRODUCTION', true));
});

test('DebugMode trata env nulo como no-producción', function (): void {
    assert_same(true, DebugMode::resolve(null, true));
});
