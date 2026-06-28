<?php

declare(strict_types=1);

use Lebytek\Framework\Application\DTO\Files\FileUploadConfig;
use Lebytek\Framework\Application\DTO\Files\ImageOptions;
use Lebytek\Framework\Application\DTO\Files\ThumbnailOptions;

test('FileUploadConfig expone todas las propiedades con argumentos nombrados', function (): void {
    $imagen = new ImageOptions(maxWidth: 1024, maxHeight: 1024, calidad: 82);
    $cfg = new FileUploadConfig(
        entidadTipo: 'usuario',
        entidadId: 5,
        coleccion: 'avatar',
        disco: 'public',
        directorio: 'uploads/avatars',
        allowedExtensions: ['jpg', 'png'],
        maxBytes: 5 * 1024 * 1024,
        imagen: $imagen,
        esActual: true,
        creadoPor: 1
    );

    assert_same('usuario', $cfg->entidadTipo);
    assert_same(5, $cfg->entidadId);
    assert_same('avatar', $cfg->coleccion);
    assert_same('public', $cfg->disco);
    assert_same('uploads/avatars', $cfg->directorio);
    assert_same(['jpg', 'png'], $cfg->allowedExtensions);
    assert_same(5 * 1024 * 1024, $cfg->maxBytes);
    assert_same($imagen, $cfg->imagen);
    assert_same(true, $cfg->esActual);
    assert_same(1, $cfg->creadoPor);
});

test('FileUploadConfig aplica los defaults del contrato', function (): void {
    $cfg = new FileUploadConfig(
        entidadTipo: 'crud:demo_clientes',
        directorio: 'uploads/demo',
        maxBytes: 1024
    );

    assert_same('default', $cfg->coleccion);
    assert_same('public', $cfg->disco);
    assert_null($cfg->imagen);
    assert_same(false, $cfg->esActual);
    assert_null($cfg->creadoPor);
    assert_null($cfg->entidadId);
    assert_null($cfg->allowedExtensions);
});

test('ImageOptions y ThumbnailOptions exponen sus propiedades', function (): void {
    $thumb = new ThumbnailOptions(width: 96, height: 96);
    $img   = new ImageOptions(maxWidth: 800, maxHeight: 600, calidad: 75, thumbnail: $thumb);

    assert_same(800, $img->maxWidth);
    assert_same(600, $img->maxHeight);
    assert_same(75, $img->calidad);
    assert_same($thumb, $img->thumbnail);
    assert_same(96, $thumb->width);
    assert_same(96, $thumb->height);

    $sinThumb = new ImageOptions(maxWidth: 100, maxHeight: 100, calidad: 80);
    assert_null($sinThumb->thumbnail);
});
