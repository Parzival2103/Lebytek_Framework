<?php

declare(strict_types=1);

use Lebytek\Framework\Domain\Entities\Usuario;
use Lebytek\Framework\Domain\ValueObjects\Email;

test('Usuario: emailVerificadoEn es null por defecto y se expone por getter/toArray', function (): void {
    $usuario = new Usuario(
        nombre:       'Ana',
        apellido:     'Lopez',
        email:        new Email('ana@test.local'),
        passwordHash: 'hash'
    );
    assert_null($usuario->emailVerificadoEn());
    assert_null($usuario->toArray()['email_verificado_en']);
});

test('Usuario: acepta emailVerificadoEn en el constructor', function (): void {
    $momento = new \DateTimeImmutable('2026-06-12 10:00:00');
    $usuario = new Usuario(
        nombre:            'Ana',
        apellido:          'Lopez',
        email:             new Email('ana@test.local'),
        passwordHash:      'hash',
        emailVerificadoEn: $momento
    );
    assert_same('2026-06-12 10:00:00', $usuario->emailVerificadoEn()?->format('Y-m-d H:i:s'));
    assert_same('2026-06-12 10:00:00', $usuario->toArray()['email_verificado_en']);
});
