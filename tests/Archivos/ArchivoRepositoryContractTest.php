<?php

declare(strict_types=1);

use Lebytek\Framework\Domain\Entities\Archivo;

require_once __DIR__ . '/../fixtures/archivo_repos.php';

function archivo_nuevo(array $overrides = []): Archivo
{
    return Archivo::desdeFila(array_merge([
        'entidad_tipo' => 'usuario',
        'entidad_id'   => 3,
        'coleccion'    => 'avatar',
        'ruta'         => '/uploads/avatars/a.png',
        'tamano_bytes' => 100,
        'es_actual'    => 0,
    ], $overrides));
}

test('Contrato: marcarActual deja exactamente uno como actual', function (): void {
    $repo = new FakeArchivoRepository();
    $id1  = $repo->guardar(archivo_nuevo(['ruta' => '/uploads/avatars/1.png']));
    $id2  = $repo->guardar(archivo_nuevo(['ruta' => '/uploads/avatars/2.png']));
    $id3  = $repo->guardar(archivo_nuevo(['ruta' => '/uploads/avatars/3.png', 'es_actual' => 1]));

    $repo->marcarActual($id2, 'usuario', 3, 'avatar');

    $actuales = array_filter(
        $repo->listarPorEntidad('usuario', 3, 'avatar'),
        fn(Archivo $a) => $a->esActual()
    );
    assert_same(1, count($actuales), 'exactamente uno debe ser actual');
    assert_same($id2, array_values($actuales)[0]->id());
    assert_same($id2, $repo->buscarActual('usuario', 3, 'avatar')?->id());
    assert_true(!$repo->buscarPorId($id1)->esActual());
    assert_true(!$repo->buscarPorId($id3)->esActual());
});

test('Contrato: softDelete excluye de listarPorEntidad y anula buscarActual si era el actual', function (): void {
    $repo = new FakeArchivoRepository();
    $id1  = $repo->guardar(archivo_nuevo(['ruta' => '/uploads/avatars/1.png']));
    $id2  = $repo->guardar(archivo_nuevo(['ruta' => '/uploads/avatars/2.png']));
    $repo->marcarActual($id2, 'usuario', 3, 'avatar');

    $repo->softDelete($id2);

    $ids = array_map(fn(Archivo $a) => $a->id(), $repo->listarPorEntidad('usuario', 3, 'avatar'));
    assert_same([$id1], $ids, 'el borrado no debe listarse');
    assert_null($repo->buscarActual('usuario', 3, 'avatar'), 'sin actual tras borrar el actual');
});

test('Contrato: softDelete de uno no actual no afecta al actual', function (): void {
    $repo = new FakeArchivoRepository();
    $id1  = $repo->guardar(archivo_nuevo(['ruta' => '/uploads/avatars/1.png']));
    $id2  = $repo->guardar(archivo_nuevo(['ruta' => '/uploads/avatars/2.png']));
    $repo->marcarActual($id2, 'usuario', 3, 'avatar');

    $repo->softDelete($id1);

    assert_same($id2, $repo->buscarActual('usuario', 3, 'avatar')?->id());
});

test('Contrato: listarPorEntidad ordena más reciente primero y filtra por colección', function (): void {
    $repo = new FakeArchivoRepository();
    $id1  = $repo->guardar(archivo_nuevo());
    $repo->guardar(archivo_nuevo(['coleccion' => 'otra']));
    $repo->guardar(archivo_nuevo(['entidad_id' => 99]));
    $id4  = $repo->guardar(archivo_nuevo());

    $ids = array_map(fn(Archivo $a) => $a->id(), $repo->listarPorEntidad('usuario', 3, 'avatar'));
    assert_same([$id4, $id1], $ids);
});

test('Contrato: buscarPorId devuelve también borrados (validación de pertenencia)', function (): void {
    $repo = new FakeArchivoRepository();
    $id1  = $repo->guardar(archivo_nuevo());
    $repo->softDelete($id1);

    $archivo = $repo->buscarPorId($id1);
    assert_true($archivo !== null, 'buscarPorId debe devolver el borrado');
    assert_true($archivo->deletedAt() !== null, 'con deletedAt poblado');
    assert_true(!$archivo->esActual(), 'borrado deja de ser actual');
});
