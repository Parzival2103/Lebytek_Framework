<?php

declare(strict_types=1);

use App\Application\DTO\Files\FileUploadConfig;
use App\Application\Services\FileUploadService;
use App\Application\Services\ImageProcessor;
use App\Domain\Entities\Archivo;
use App\Domain\Exceptions\ValidationException;

require_once __DIR__ . '/../fixtures/archivo_repos.php';

/** Crea un archivo temporal real y devuelve la estructura $_FILES simulada. */
function upload_file_array(string $originalName, string $contents = 'data'): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'upl');
    file_put_contents($tmp, $contents);

    return [
        'name'     => $originalName,
        'tmp_name' => $tmp,
        'size'     => strlen($contents),
        'error'    => UPLOAD_ERR_OK,
        'type'     => 'application/octet-stream',
    ];
}

function upload_test_dir(): string
{
    return 'uploads/test_' . bin2hex(random_bytes(4));
}

/** Borra recursivamente el directorio de pruebas bajo public/. */
function upload_cleanup(string $directorio): void
{
    $abs = PUBLIC_PATH . '/' . trim($directorio, '/');
    if (!is_dir($abs)) {
        return;
    }
    foreach (glob($abs . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($abs);
    @rmdir(dirname($abs)); // borra public/uploads solo si quedó vacío
}

function upload_config(string $directorio, array $overrides = []): FileUploadConfig
{
    return new FileUploadConfig(
        entidadTipo:       $overrides['entidadTipo'] ?? 'usuario',
        directorio:        $directorio,
        maxBytes:          $overrides['maxBytes'] ?? 1024 * 1024,
        entidadId:         array_key_exists('entidadId', $overrides) ? $overrides['entidadId'] : 3,
        coleccion:         $overrides['coleccion'] ?? 'avatar',
        allowedExtensions: $overrides['allowedExtensions'] ?? null,
        esActual:          $overrides['esActual'] ?? false,
        creadoPor:         $overrides['creadoPor'] ?? 9
    );
}

test('FileUploadService caso feliz: mueve el archivo y registra metadatos en el ledger', function (): void {
    $repo    = new FakeArchivoRepository();
    $service = new FileUploadService(new ImageProcessor(), $repo);
    $dir     = upload_test_dir();

    try {
        $archivo = $service->handle(upload_file_array('foto.txt', 'hola mundo'), upload_config($dir), 'Foto');

        assert_true(str_starts_with($archivo->ruta(), '/' . $dir . '/'), 'ruta debe empezar con /' . $dir);
        assert_true(is_file(PUBLIC_PATH . $archivo->ruta()), 'el archivo debe existir en destino');
        assert_true($archivo->id() !== null, 'debe venir persistido');

        $fila = $repo->buscarPorId($archivo->id());
        assert_same('usuario', $fila->entidadTipo());
        assert_same(3, $fila->entidadId());
        assert_same('avatar', $fila->coleccion());
        assert_same('foto.txt', $fila->nombreOriginal());
        assert_same('txt', $fila->extension());
        assert_same(10, $fila->tamanoBytes());
        assert_same(9, $fila->creadoPor());
        assert_same('text/plain', $fila->mime());
    } finally {
        upload_cleanup($dir);
    }
});

test('FileUploadService con esActual=true desplaza al actual previo', function (): void {
    $repo    = new FakeArchivoRepository();
    $service = new FileUploadService(new ImageProcessor(), $repo);
    $dir     = upload_test_dir();

    try {
        $previo = $service->handle(upload_file_array('a.txt'), upload_config($dir, ['esActual' => true]), 'Foto');
        $nuevo  = $service->handle(upload_file_array('b.txt'), upload_config($dir, ['esActual' => true]), 'Foto');

        assert_true($repo->buscarPorId($nuevo->id())->esActual(), 'el nuevo es el actual');
        assert_true(!$repo->buscarPorId($previo->id())->esActual(), 'el previo deja de ser actual');
        assert_same($nuevo->id(), $repo->buscarActual('usuario', 3, 'avatar')?->id());
    } finally {
        upload_cleanup($dir);
    }
});

test('FileUploadService con entidadId NULL inserta sin marcar actual', function (): void {
    $repo    = new FakeArchivoRepository();
    $service = new FileUploadService(new ImageProcessor(), $repo);
    $dir     = upload_test_dir();

    try {
        $archivo = $service->handle(
            upload_file_array('c.txt'),
            upload_config($dir, ['entidadId' => null, 'esActual' => true]),
            'Foto'
        );
        assert_true(!$repo->buscarPorId($archivo->id())->esActual(), 'sin entidadId queda es_actual=0');
    } finally {
        upload_cleanup($dir);
    }
});

test('FileUploadService conserva el mensaje de extensión no permitida', function (): void {
    $service = new FileUploadService(new ImageProcessor(), new FakeArchivoRepository());
    $dir     = upload_test_dir();
    $file    = upload_file_array('malware.exe');

    try {
        $service->handle($file, upload_config($dir, ['allowedExtensions' => ['png', 'jpg']]), 'Foto');
        assert_true(false, 'debió lanzar ValidationException');
    } catch (ValidationException $e) {
        assert_same('Extensión de archivo no permitida para Foto.', $e->getMessage());
    } finally {
        @unlink($file['tmp_name']);
        upload_cleanup($dir);
    }
});

test('FileUploadService conserva el mensaje de tamaño máximo', function (): void {
    $service = new FileUploadService(new ImageProcessor(), new FakeArchivoRepository());
    $dir     = upload_test_dir();
    $file    = upload_file_array('grande.txt', str_repeat('x', 2048));

    try {
        $service->handle($file, upload_config($dir, ['maxBytes' => 1024]), 'Documento');
        assert_true(false, 'debió lanzar ValidationException');
    } catch (ValidationException $e) {
        assert_same('El archivo para Documento supera el tamaño máximo permitido.', $e->getMessage());
    } finally {
        @unlink($file['tmp_name']);
        upload_cleanup($dir);
    }
});

test('FileUploadService sanea nombres con caracteres raros', function (): void {
    $repo    = new FakeArchivoRepository();
    $service = new FileUploadService(new ImageProcessor(), $repo);
    $dir     = upload_test_dir();

    try {
        $archivo  = $service->handle(upload_file_array('mí fótö (1)!.txt'), upload_config($dir), 'Foto');
        $basename = basename($archivo->ruta());
        assert_true(
            (bool) preg_match('/^[a-zA-Z0-9_-]+_\d{14}_[0-9a-f]{8}\.txt$/', $basename),
            "nombre final seguro, obtuve: {$basename}"
        );
    } finally {
        upload_cleanup($dir);
    }
});
