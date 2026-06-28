<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\FileUploadService;
use Lebytek\Framework\Application\Services\ImageProcessor;
use Lebytek\Framework\Application\UseCases\Avatares\AvatarDefaults;
use Lebytek\Framework\Application\UseCases\Avatares\EliminarAvatarUseCase;
use Lebytek\Framework\Application\UseCases\Avatares\FijarAvatarActualUseCase;
use Lebytek\Framework\Application\UseCases\Avatares\ListarAvataresUseCase;
use Lebytek\Framework\Application\UseCases\Avatares\SubirAvatarUseCase;
use Lebytek\Framework\Domain\Entities\Archivo;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

require_once __DIR__ . '/../fixtures/archivo_repos.php';
require_once __DIR__ . '/../fixtures/avatar_fakes.php';

function avatar_file(string $name = 'selfie.png'): array
{
    // PNG real de 1x1 para que la verificación MIME (finfo) lo acepte.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $tmp = tempnam(sys_get_temp_dir(), 'ava');
    file_put_contents($tmp, $png);

    return ['name' => $name, 'tmp_name' => $tmp, 'size' => strlen($png), 'error' => UPLOAD_ERR_OK];
}

function avatar_ledger_row(int $usuarioId, array $overrides = []): Archivo
{
    return Archivo::desdeFila(array_merge([
        'entidad_tipo' => 'usuario',
        'entidad_id'   => $usuarioId,
        'coleccion'    => 'avatar',
        'ruta'         => '/uploads/avatars/x.png',
        'tamano_bytes' => 10,
        'es_actual'    => 0,
    ], $overrides));
}

function avatar_uploads_cleanup(): void
{
    $abs = PUBLIC_PATH . '/uploads/avatars';
    foreach (glob($abs . '/*_test_ava_*') ?: [] as $f) {
        @unlink($f);
    }
}

test('SubirAvatarUseCase sube con config de avatar y actualiza el cache', function (): void {
    $archivos = new FakeArchivoRepository();
    $usuarios = new FakeUsuarioRepository();
    $useCase  = new SubirAvatarUseCase(new FileUploadService(new ImageProcessor(), $archivos), $usuarios);

    $file = avatar_file('selfie_test_ava_1.png');
    try {
        $archivo = $useCase->execute(usuarioId: 3, file: $file, actorId: 9);

        $fila = $archivos->buscarPorId($archivo->id());
        assert_same('usuario', $fila->entidadTipo());
        assert_same(3, $fila->entidadId());
        assert_same('avatar', $fila->coleccion());
        assert_true($fila->esActual(), 'el avatar recién subido queda como actual');
        assert_same(9, $fila->creadoPor());
        assert_true(str_starts_with($fila->ruta(), '/uploads/avatars/'));
        assert_same([[3, $fila->ruta()]], $usuarios->avatarUpdates, 'cache auth_usuarios.avatar actualizado');
    } finally {
        @unlink(PUBLIC_PATH . ($archivo ?? null)?->ruta() ?? '');
        avatar_uploads_cleanup();
    }
});

test('FijarAvatarActualUseCase valida pertenencia e integridad', function (): void {
    $archivos = new FakeArchivoRepository();
    $usuarios = new FakeUsuarioRepository();
    $useCase  = new FijarAvatarActualUseCase($archivos, $usuarios);

    // No existe
    assert_throws(ValidationException::class, fn() => $useCase->execute(3, 99, 3));

    // De otro usuario
    $ajeno = $archivos->guardar(avatar_ledger_row(8));
    assert_throws(ValidationException::class, fn() => $useCase->execute(3, $ajeno, 3));

    // De otra colección/tipo
    $otraCol = $archivos->guardar(avatar_ledger_row(3, ['coleccion' => 'firma']));
    assert_throws(ValidationException::class, fn() => $useCase->execute(3, $otraCol, 3));

    // Borrado
    $borrado = $archivos->guardar(avatar_ledger_row(3));
    $archivos->softDelete($borrado);
    assert_throws(ValidationException::class, fn() => $useCase->execute(3, $borrado, 3));

    assert_same([], $usuarios->avatarUpdates, 'sin efectos colaterales en los casos inválidos');
});

test('FijarAvatarActualUseCase marca actual y refresca el cache', function (): void {
    $archivos = new FakeArchivoRepository();
    $usuarios = new FakeUsuarioRepository();
    $useCase  = new FijarAvatarActualUseCase($archivos, $usuarios);

    $viejo = $archivos->guardar(avatar_ledger_row(3, ['ruta' => '/uploads/avatars/viejo.png']));
    $nuevo = $archivos->guardar(avatar_ledger_row(3, ['ruta' => '/uploads/avatars/nuevo.png', 'es_actual' => 1]));

    $useCase->execute(usuarioId: 3, archivoId: $viejo, actorId: 3);

    assert_true($archivos->buscarPorId($viejo)->esActual());
    assert_true(!$archivos->buscarPorId($nuevo)->esActual());
    assert_same([[3, '/uploads/avatars/viejo.png']], $usuarios->avatarUpdates);
});

test('EliminarAvatarUseCase borra el actual y vacía el cache', function (): void {
    $archivos = new FakeArchivoRepository();
    $usuarios = new FakeUsuarioRepository();
    $useCase  = new EliminarAvatarUseCase($archivos, $usuarios);

    $actual = $archivos->guardar(avatar_ledger_row(3, ['es_actual' => 1]));

    $useCase->execute(usuarioId: 3, archivoId: $actual, actorId: 3);

    assert_true($archivos->buscarPorId($actual)->deletedAt() !== null, 'soft delete');
    assert_same([[3, null]], $usuarios->avatarUpdates, 'cache vaciado');
});

test('EliminarAvatarUseCase de uno no actual no toca el cache', function (): void {
    $archivos = new FakeArchivoRepository();
    $usuarios = new FakeUsuarioRepository();
    $useCase  = new EliminarAvatarUseCase($archivos, $usuarios);

    $viejo  = $archivos->guardar(avatar_ledger_row(3));
    $actual = $archivos->guardar(avatar_ledger_row(3, ['es_actual' => 1]));

    $useCase->execute(usuarioId: 3, archivoId: $viejo, actorId: 3);

    assert_true($archivos->buscarPorId($viejo)->deletedAt() !== null);
    assert_same([], $usuarios->avatarUpdates, 'el cache no cambia');
    assert_same($actual, $archivos->buscarActual('usuario', 3, 'avatar')?->id());
});

test('EliminarAvatarUseCase valida pertenencia', function (): void {
    $archivos = new FakeArchivoRepository();
    $useCase  = new EliminarAvatarUseCase($archivos, new FakeUsuarioRepository());

    $ajeno = $archivos->guardar(avatar_ledger_row(8));
    assert_throws(ValidationException::class, fn() => $useCase->execute(3, $ajeno, 3));
    assert_throws(ValidationException::class, fn() => $useCase->execute(3, 99, 3));
});

test('ListarAvataresUseCase devuelve historial vigente con el actual marcado', function (): void {
    $archivos = new FakeArchivoRepository();
    $useCase  = new ListarAvataresUseCase($archivos);

    $a = $archivos->guardar(avatar_ledger_row(3, ['ruta' => '/uploads/avatars/a.png']));
    $b = $archivos->guardar(avatar_ledger_row(3, ['ruta' => '/uploads/avatars/b.png', 'es_actual' => 1]));
    $c = $archivos->guardar(avatar_ledger_row(3, ['ruta' => '/uploads/avatars/c.png']));
    $archivos->softDelete($c);

    $historial = $useCase->execute(3);

    $ids = array_map(fn(Archivo $x) => $x->id(), $historial);
    assert_same([$b, $a], $ids, 'no borrados, más reciente primero');
    $actuales = array_values(array_filter($historial, fn(Archivo $x) => $x->esActual()));
    assert_same(1, count($actuales));
    assert_same($b, $actuales[0]->id());
});

test('AvatarDefaults define las convenciones del módulo', function (): void {
    assert_same('usuario', AvatarDefaults::ENTIDAD_TIPO);
    assert_same('avatar', AvatarDefaults::COLECCION);
    assert_same('uploads/avatars', AvatarDefaults::DIRECTORIO);
    $img = AvatarDefaults::imageOptions();
    assert_same(1024, $img->maxWidth);
    assert_same(1024, $img->maxHeight);
    assert_same(82, $img->calidad);
});
