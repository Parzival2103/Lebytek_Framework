<?php

declare(strict_types=1);

require_once __DIR__ . '/../fixtures/auth_fakes.php';

test('Contrato: registrarFallo persiste dos filas (ip y email)', function (): void {
    $repo = new FakeLoginIntentoRepository();

    $repo->registrarFallo('192.168.1.10', 'admin@test.local');

    assert_same(2, count($repo->filas));
    assert_same('ip', $repo->filas[0]['dimension']);
    assert_same('192.168.1.10', $repo->filas[0]['clave']);
    assert_same('email', $repo->filas[1]['dimension']);
    assert_same('admin@test.local', $repo->filas[1]['clave']);
});

test('Contrato: contarFallosRecientes solo cuenta dentro de la ventana', function (): void {
    $repo = new FakeLoginIntentoRepository();
    $repo->filas[] = [
        'dimension'  => 'ip',
        'clave'      => '10.0.0.1',
        'created_at' => date('Y-m-d H:i:s', time() - 20 * 60),
    ];
    $repo->filas[] = [
        'dimension'  => 'ip',
        'clave'      => '10.0.0.1',
        'created_at' => date('Y-m-d H:i:s', time() - 5 * 60),
    ];

    assert_same(1, $repo->contarFallosRecientes('ip', '10.0.0.1', 15));
});

test('Contrato: limpiarPara elimina filas de ip y email indicados', function (): void {
    $repo = new FakeLoginIntentoRepository();
    $repo->registrarFallo('10.0.0.1', 'a@test.local');
    $repo->registrarFallo('10.0.0.2', 'b@test.local');

    $repo->limpiarPara('10.0.0.1', 'a@test.local');

    assert_same(2, count($repo->filas), 'solo quedan las de la otra pareja ip/email');
    assert_same('10.0.0.2', $repo->filas[0]['clave']);
});

test('Contrato: purgarAntiguos elimina filas fuera de 2x ventana', function (): void {
    $repo = new FakeLoginIntentoRepository();
    $repo->filas[] = [
        'dimension'  => 'email',
        'clave'      => 'viejo@test.local',
        'created_at' => date('Y-m-d H:i:s', time() - 40 * 60),
    ];
    $repo->filas[] = [
        'dimension'  => 'email',
        'clave'      => 'nuevo@test.local',
        'created_at' => date('Y-m-d H:i:s', time() - 5 * 60),
    ];

    $repo->purgarAntiguos(15);

    assert_same(1, count($repo->filas));
    assert_same('nuevo@test.local', $repo->filas[0]['clave']);
});
