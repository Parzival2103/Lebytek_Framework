<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudDataService;
use Lebytek\Framework\Application\Services\FileUploadService;
use Lebytek\Framework\Application\Services\ImageProcessor;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

require_once __DIR__ . '/../../fixtures/archivo_repos.php';

/**
 * CrudDataService es final y depende de GenericCrudRepository (final, con BD),
 * así que se instancia sin constructor y se inyecta por reflexión solo la
 * dependencia que usa handleUpload (mismo alcance que el método migrado).
 */
function crud_upload_service(FileUploadService $uploads): CrudDataService
{
    $ref     = new ReflectionClass(CrudDataService::class);
    $service = $ref->newInstanceWithoutConstructor();
    $prop    = $ref->getProperty('fileUploadService');
    $prop->setAccessible(true);
    $prop->setValue($service, $uploads);

    return $service;
}

function crud_upload_definition(string $dir): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo_docs', 'table' => 'dom_demo_docs', 'primary_key' => 'id'],
        'uploads'  => ['enabled' => true, 'public_path' => $dir],
        'form'     => ['fields' => [
            ['name' => 'adjunto', 'label' => 'Adjunto', 'type' => 'file', 'validation' => ['allowed_extensions' => ['txt']]],
        ]],
    ]);
}

function crud_upload_invoke(CrudDataService $service, CrudResourceDefinition $def, array $files, ?int $rowId, ?int $userId): ?string
{
    $method = new ReflectionMethod(CrudDataService::class, 'handleUpload');
    $method->setAccessible(true);
    $field = $def->formFields()[0];

    return $method->invoke($service, $def, $field, $files, $rowId, $userId);
}

function crud_upload_tmp_files(): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'crd');
    file_put_contents($tmp, 'contenido demo');

    return ['adjunto' => [
        'name'     => 'manual.txt',
        'tmp_name' => $tmp,
        'size'     => 14,
        'error'    => UPLOAD_ERR_OK,
    ]];
}

function crud_upload_cleanup_dir(string $dir): void
{
    $abs = PUBLIC_PATH . '/' . trim($dir, '/');
    foreach (glob($abs . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($abs);
    @rmdir(dirname($abs));
}

test('CRUD handleUpload conserva la forma de la ruta y registra en el ledger (creación: entidad_id NULL)', function (): void {
    $repo    = new FakeArchivoRepository();
    $service = crud_upload_service(new FileUploadService(new ImageProcessor(), $repo));
    $dir     = 'uploads/test_crud_' . bin2hex(random_bytes(4));
    $def     = crud_upload_definition($dir);

    try {
        $path = crud_upload_invoke($service, $def, crud_upload_tmp_files(), null, 9);

        assert_true(is_string($path) && str_starts_with($path, '/' . $dir . '/'), 'ruta relativa con la misma forma que hoy');
        assert_true(str_ends_with($path, '.txt'));

        assert_same(1, count($repo->archivos), 'una fila en el ledger');
        $fila = $repo->buscarPorId(1);
        assert_same('crud:demo_docs', $fila->entidadTipo());
        assert_same('adjunto', $fila->coleccion());
        assert_null($fila->entidadId(), 'en creación el id de fila aún no existe');
        assert_true(!$fila->esActual(), 'uploads CRUD no marcan actual');
        assert_same(9, $fila->creadoPor());
        assert_same($path, $fila->ruta());
    } finally {
        crud_upload_cleanup_dir($dir);
    }
});

test('CRUD handleUpload en edición registra el id de la fila', function (): void {
    $repo    = new FakeArchivoRepository();
    $service = crud_upload_service(new FileUploadService(new ImageProcessor(), $repo));
    $dir     = 'uploads/test_crud_' . bin2hex(random_bytes(4));
    $def     = crud_upload_definition($dir);

    try {
        crud_upload_invoke($service, $def, crud_upload_tmp_files(), 42, null);

        $fila = $repo->buscarPorId(1);
        assert_same(42, $fila->entidadId());
        assert_null($fila->creadoPor());
    } finally {
        crud_upload_cleanup_dir($dir);
    }
});

test('CRUD handleUpload sin uploads habilitados o sin archivo devuelve null', function (): void {
    $repo    = new FakeArchivoRepository();
    $service = crud_upload_service(new FileUploadService(new ImageProcessor(), $repo));
    $dir     = 'uploads/test_crud_' . bin2hex(random_bytes(4));

    $sinUploads = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo_docs', 'table' => 'dom_demo_docs', 'primary_key' => 'id'],
        'form'     => ['fields' => [['name' => 'adjunto', 'label' => 'Adjunto', 'type' => 'file']]],
    ]);
    assert_null(crud_upload_invoke($service, $sinUploads, crud_upload_tmp_files(), null, null));

    $def = crud_upload_definition($dir);
    assert_null(crud_upload_invoke($service, $def, [], null, null), 'sin archivo en files');
    assert_same(0, count($repo->archivos), 'ledger intacto');
});
