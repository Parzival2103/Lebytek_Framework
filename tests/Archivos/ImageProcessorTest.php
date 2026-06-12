<?php

declare(strict_types=1);

use App\Application\DTO\Files\ImageOptions;
use App\Application\Services\ImageProcessor;

function image_tmp_dir(): string
{
    $dir = sys_get_temp_dir() . '/contraste_imgproc_' . bin2hex(random_bytes(4));
    mkdir($dir, 0775, true);
    return $dir;
}

if (!extension_loaded('gd')) {
    test('ImageProcessor (GD no disponible: se omiten pruebas de redimensión)', function (): void {
        // Skip suave: sin GD el procesador degrada sin tocar el archivo.
        $dir  = image_tmp_dir();
        $file = $dir . '/nota.txt';
        file_put_contents($file, 'contenido');
        (new ImageProcessor())->redimensionar($file, new ImageOptions(50, 50, 82));
        assert_same('contenido', file_get_contents($file), 'sin GD no debe tocar el archivo');
        unlink($file);
        rmdir($dir);
    });
} else {
    test('ImageProcessor redimensiona PNG conservando proporción y formato', function (): void {
        $dir  = image_tmp_dir();
        $file = $dir . '/img.png';
        $img  = imagecreatetruecolor(200, 100);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 10, 10));
        imagepng($img, $file);
        imagedestroy($img);

        (new ImageProcessor())->redimensionar($file, new ImageOptions(maxWidth: 50, maxHeight: 50, calidad: 82));

        $info = getimagesize($file);
        assert_same(50, $info[0], 'ancho');
        assert_same(25, $info[1], 'alto proporcional');
        assert_same('image/png', $info['mime'], 'sigue siendo PNG');
        unlink($file);
        rmdir($dir);
    });

    test('ImageProcessor no toca archivos que no son imagen', function (): void {
        $dir  = image_tmp_dir();
        $file = $dir . '/doc.txt';
        file_put_contents($file, 'no soy una imagen');

        (new ImageProcessor())->redimensionar($file, new ImageOptions(50, 50, 82));

        assert_same('no soy una imagen', file_get_contents($file));
        unlink($file);
        rmdir($dir);
    });

    test('ImageProcessor no agranda imágenes menores al límite', function (): void {
        $dir  = image_tmp_dir();
        $file = $dir . '/small.png';
        $img  = imagecreatetruecolor(30, 20);
        imagepng($img, $file);
        imagedestroy($img);

        (new ImageProcessor())->redimensionar($file, new ImageOptions(100, 100, 82));

        $info = getimagesize($file);
        assert_same(30, $info[0]);
        assert_same(20, $info[1]);
        unlink($file);
        rmdir($dir);
    });
}
