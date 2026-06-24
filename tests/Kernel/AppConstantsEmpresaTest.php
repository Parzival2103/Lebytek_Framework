<?php

declare(strict_types=1);

use App\Kernel\Constants\AppConstants;

test('AppConstants: resolveEmpresaNombre usa Framework Lebytek cuando está vacío', function (): void {
    assert_same('Framework Lebytek', AppConstants::resolveEmpresaNombre(null));
    assert_same('Framework Lebytek', AppConstants::resolveEmpresaNombre(''));
    assert_same('Framework Lebytek', AppConstants::resolveEmpresaNombre('   '));
});

test('AppConstants: resolveEmpresaNombre respeta el valor de Admin → Ajustes', function (): void {
    assert_same('Mi Clínica', AppConstants::resolveEmpresaNombre('Mi Clínica'));
});

test('AppConstants: footerMuestraPoweredBy solo con nombre personalizado', function (): void {
    assert_false(AppConstants::footerMuestraPoweredBy(null));
    assert_false(AppConstants::footerMuestraPoweredBy('Framework Lebytek'));
    assert_true(AppConstants::footerMuestraPoweredBy('Acme Corp'));
});

test('AppConstants: empresaMostrarNombre activo por defecto y respeta desactivación', function (): void {
    assert_true(AppConstants::empresaMostrarNombre(null));
    assert_true(AppConstants::empresaMostrarNombre(''));
    assert_true(AppConstants::empresaMostrarNombre('1'));
    assert_true(AppConstants::empresaMostrarNombre(true));
    assert_false(AppConstants::empresaMostrarNombre('0'));
    assert_false(AppConstants::empresaMostrarNombre('false'));
    assert_false(AppConstants::empresaMostrarNombre(false));
});
