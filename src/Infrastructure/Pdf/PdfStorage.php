<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Pdf;

/**
 * Persiste bytes de PDF bajo storage/pdf con un nombre saneado. Útil para reportes
 * archivables / auditoría. Opcional: la descarga directa no necesita guardar.
 */
final class PdfStorage
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (ROOT_PATH . '/storage/pdf');
    }

    /** Guarda los bytes y devuelve la ruta absoluta del archivo. */
    public function save(string $bytes, string $filename): string
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }

        $safe = $this->safeName($filename);
        $path = $this->dir . '/' . $safe;
        file_put_contents($path, $bytes);

        return $path;
    }

    private function safeName(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '-', $base) ?? 'documento';
        $base = trim($base, '-') ?: 'documento';
        return $base . '-' . date('Ymd-His') . '.pdf';
    }
}
