<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Files\ImageOptions;
use App\Kernel\Logging\AppLogger;

/*
|--------------------------------------------------------------------------
| ImageProcessor — Redimensión y recompresión de imágenes (GD)
|--------------------------------------------------------------------------
| Procesa JPG/PNG/WEBP por MIME real; nunca agranda; preserva
| transparencia. Sin GD degrada: deja el archivo intacto y avisa en log.
*/

final class ImageProcessor
{
    public function redimensionar(string $rutaAbsoluta, ImageOptions $opts): void
    {
        if (!is_file($rutaAbsoluta)) {
            return;
        }

        $mime = $this->detectarMime($rutaAbsoluta);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return;
        }

        if (!extension_loaded('gd')) {
            AppLogger::warning('ImageProcessor: extensión GD no disponible; la imagen se guarda sin procesar.', [
                'archivo' => basename($rutaAbsoluta),
            ]);
            return;
        }

        $size = getimagesize($rutaAbsoluta);
        if ($size === false) {
            return;
        }
        [$width, $height] = $size;
        if ($width < 1 || $height < 1) {
            return;
        }

        $ratio = min($opts->maxWidth / $width, $opts->maxHeight / $height, 1.0);
        if ($ratio >= 1.0) {
            return; // nunca agranda
        }

        $source = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($rutaAbsoluta),
            'image/png'  => imagecreatefrompng($rutaAbsoluta),
            'image/webp' => imagecreatefromwebp($rutaAbsoluta),
        };
        if ($source === false) {
            return;
        }

        $newWidth  = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $dest = imagecreatetruecolor($newWidth, $newHeight);
        if ($mime !== 'image/jpeg') {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            imagefill($dest, 0, 0, imagecolorallocatealpha($dest, 0, 0, 0, 127));
        }
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        match ($mime) {
            'image/jpeg' => imagejpeg($dest, $rutaAbsoluta, $opts->calidad),
            // PNG usa nivel de compresión 0-9: mapear calidad 0-100 (invertida).
            'image/png'  => imagepng($dest, $rutaAbsoluta, (int) min(9, max(0, round((100 - $opts->calidad) / 11)))),
            'image/webp' => imagewebp($dest, $rutaAbsoluta, $opts->calidad),
        };

        imagedestroy($source);
        imagedestroy($dest);
    }

    private function detectarMime(string $rutaAbsoluta): ?string
    {
        if (!function_exists('finfo_open')) {
            $info = @getimagesize($rutaAbsoluta);
            return $info['mime'] ?? null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $mime = finfo_file($finfo, $rutaAbsoluta) ?: null;
        finfo_close($finfo);

        return $mime;
    }
}
