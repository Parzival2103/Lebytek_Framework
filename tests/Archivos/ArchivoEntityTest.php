<?php

declare(strict_types=1);

use Lebytek\Framework\Domain\Entities\Archivo;

function archivo_fila(array $overrides = []): array
{
    return array_merge([
        'id'              => 7,
        'entidad_tipo'    => 'usuario',
        'entidad_id'      => 3,
        'coleccion'       => 'avatar',
        'ruta'            => '/uploads/avatars/foto_20260611_aa.png',
        'thumbnail_ruta'  => null,
        'nombre_original' => 'foto.png',
        'mime'            => 'image/png',
        'extension'       => 'png',
        'tamano_bytes'    => 2048,
        'disco'           => 'public',
        'es_actual'       => 1,
        'creado_por'      => 1,
        'created_at'      => '2026-06-11 12:00:00',
        'deleted_at'      => null,
    ], $overrides);
}

test('Archivo::desdeFila construye la entidad con todos los campos', function (): void {
    $a = Archivo::desdeFila(archivo_fila());

    assert_same(7, $a->id());
    assert_same('usuario', $a->entidadTipo());
    assert_same(3, $a->entidadId());
    assert_same('avatar', $a->coleccion());
    assert_same('/uploads/avatars/foto_20260611_aa.png', $a->ruta());
    assert_null($a->thumbnailRuta());
    assert_same('foto.png', $a->nombreOriginal());
    assert_same('image/png', $a->mime());
    assert_same('png', $a->extension());
    assert_same(2048, $a->tamanoBytes());
    assert_same('public', $a->disco());
    assert_true($a->esActual(), 'es_actual=1 debe mapear a true');
    assert_same(1, $a->creadoPor());
    assert_same('2026-06-11 12:00:00', $a->createdAt());
    assert_null($a->deletedAt());
});

test('Archivo::desdeFila tolera campos nulos opcionales', function (): void {
    $a = Archivo::desdeFila(archivo_fila([
        'entidad_id' => null,
        'creado_por' => null,
        'es_actual'  => 0,
        'mime'       => null,
        'extension'  => null,
        'nombre_original' => null,
    ]));

    assert_null($a->entidadId());
    assert_null($a->creadoPor());
    assert_true(!$a->esActual(), 'es_actual=0 debe mapear a false');
    assert_null($a->mime());
});

test('Archivo::marcarComoActual devuelve un clon actual sin mutar el original', function (): void {
    $original = Archivo::desdeFila(archivo_fila(['es_actual' => 0]));
    $clon     = $original->marcarComoActual();

    assert_true($clon->esActual(), 'el clon debe quedar como actual');
    assert_true(!$original->esActual(), 'el original no debe mutar');
    assert_true($clon !== $original, 'debe ser una instancia distinta');
    assert_same($original->ruta(), $clon->ruta());
});

test('Archivo::marcarBorrado devuelve un clon con deletedAt poblado', function (): void {
    $original = Archivo::desdeFila(archivo_fila());
    $clon     = $original->marcarBorrado('2026-06-11 13:00:00');

    assert_same('2026-06-11 13:00:00', $clon->deletedAt());
    assert_true(!$clon->esActual(), 'un archivo borrado deja de ser actual');
    assert_null($original->deletedAt(), 'el original no debe mutar');
});

test('Archivo::toArray refleja los campos de la fila', function (): void {
    $a   = Archivo::desdeFila(archivo_fila());
    $arr = $a->toArray();

    assert_same(7, $arr['id']);
    assert_same('usuario', $arr['entidadTipo']);
    assert_same('avatar', $arr['coleccion']);
    assert_same('/uploads/avatars/foto_20260611_aa.png', $arr['ruta']);
    assert_same(true, $arr['esActual']);
    assert_same(2048, $arr['tamanoBytes']);
});
