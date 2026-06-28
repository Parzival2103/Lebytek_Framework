<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CorreoAuthService;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

test('CorreoAuthService: verificación envía 1 correo con URL absoluta y token', function (): void {
    $mailer  = new FakeMailer();
    $service = new CorreoAuthService(
        $mailer,
        new \Lebytek\Framework\Application\Services\ConfiguracionService(new FakeConfiguracionRepository()),
        'https://app.test/'
    );

    $service->enviarVerificacion(fake_usuario(1, 'ana@test.local'), 'tok-verif-123');

    assert_same(1, count($mailer->enviados));
    $msg = $mailer->enviados[0];
    assert_same('ana@test.local', $msg->destinatario);
    assert_true(str_contains($msg->html, 'https://app.test/registro/verificar?token=tok-verif-123'));
    assert_true(str_contains($msg->html, 'Framework Lebytek'));
});

test('CorreoAuthService: recuperación envía 1 correo con URL de restablecer', function (): void {
    $mailer  = new FakeMailer();
    $service = new CorreoAuthService(
        $mailer,
        new \Lebytek\Framework\Application\Services\ConfiguracionService(new FakeConfiguracionRepository()),
        'https://app.test'
    );

    $service->enviarRecuperacion(fake_usuario(1, 'ana@test.local'), 'tok-rec-456');

    assert_same(1, count($mailer->enviados));
    assert_true(str_contains($mailer->enviados[0]->html, 'https://app.test/restablecer?token=tok-rec-456'));
});

test('CorreoAuthService: usa el nombre de empresa configurado en Admin → Ajustes', function (): void {
    $mailer  = new FakeMailer();
    $service = new CorreoAuthService(
        $mailer,
        new \Lebytek\Framework\Application\Services\ConfiguracionService(new FakeConfiguracionRepository([
            'empresa_nombre' => 'Acme Corp',
        ])),
        'https://app.test'
    );

    $service->enviarVerificacion(fake_usuario(1, 'ana@test.local'), 'tok');

    assert_true(str_contains($mailer->enviados[0]->html, 'Acme Corp'));
});

test('CorreoAuthService: fallo del transporte se traduce a ValidationException genérica', function (): void {
    $mailer        = new FakeMailer();
    $mailer->falla = new \RuntimeException('SMTP connect() failed con credenciales x');
    $service       = new CorreoAuthService(
        $mailer,
        new \Lebytek\Framework\Application\Services\ConfiguracionService(new FakeConfiguracionRepository()),
        'https://app.test'
    );

    assert_throws(ValidationException::class, function () use ($service): void {
        $service->enviarVerificacion(fake_usuario(1), 'tok');
    });

    try {
        $service->enviarVerificacion(fake_usuario(1), 'tok');
    } catch (ValidationException $e) {
        assert_true(!str_contains($e->getMessage(), 'SMTP'), 'no debe filtrar detalles del transporte');
    }
});
