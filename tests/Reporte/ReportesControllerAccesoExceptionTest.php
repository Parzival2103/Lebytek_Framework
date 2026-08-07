<?php

declare(strict_types=1);

/**
 * Contrato de superficie HTTP: una denegación RBAC dentro de los casos de uso
 * de reportes debe salir como 403, no como 500 ni como error de aplicación en
 * bitácora. No hay arnés de controladores en el paquete, así que se verifica
 * sobre la fuente igual que otros contratos de Presentation.
 */

function reportes_controller_source(): string
{
    $path = dirname(__DIR__, 2) . '/src/Presentation/Controllers/Admin/ReportesController.php';
    $src = file_get_contents($path);
    assert_true(is_string($src) && $src !== '', 'ReportesController.php es legible');
    return (string) $src;
}

function reportes_controller_method(string $name): string
{
    $src = reportes_controller_source();
    $start = strpos($src, 'public function ' . $name . '(Request $request): Response');
    assert_true($start !== false, "existe el método {$name}()");
    $end = strpos($src, "\n    public function ", (int) $start + 1);
    return substr($src, (int) $start, $end === false ? null : $end - (int) $start);
}

test('ReportesController importa AccesoException (C5)', function (): void {
    assert_true(
        str_contains(reportes_controller_source(), 'use Lebytek\Framework\Domain\Exceptions\AccesoException;'),
        'importa AccesoException'
    );
});

test('ReportesController::generar responde 403 ante AccesoException (C5)', function (): void {
    $body = reportes_controller_method('generar');
    $catch = strpos($body, 'catch (AccesoException)');
    assert_true($catch !== false, 'generar() captura AccesoException');
    assert_true(
        str_contains(substr($body, (int) $catch, 120), 'Response::forbidden()'),
        'generar() devuelve Response::forbidden() en esa rama'
    );
});

test('ReportesController::documento captura AccesoException antes de Throwable (C5)', function (): void {
    $body = reportes_controller_method('documento');
    $acceso = strpos($body, 'catch (AccesoException)');
    $throwable = strpos($body, 'catch (\Throwable');
    assert_true($acceso !== false, 'documento() captura AccesoException');
    assert_true($throwable !== false, 'documento() conserva el catch de \Throwable');
    assert_true($acceso < $throwable, 'AccesoException no cae en el catch que registra AppLogger::error');
    assert_true(
        str_contains(substr($body, (int) $acceso, 120), 'Response::forbidden()'),
        'documento() devuelve Response::forbidden() en esa rama'
    );
});
