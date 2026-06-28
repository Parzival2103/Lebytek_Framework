<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Presentation\Controllers\AdminBaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Application\UseCases\Usuarios\CrearUsuarioUseCase;
use App\Application\UseCases\Usuarios\ListarUsuariosUseCase;
use App\Application\UseCases\Usuarios\ActualizarUsuarioUseCase;
use App\Application\UseCases\Usuarios\EliminarUsuarioUseCase;
use App\Application\DTO\Usuarios\CrearUsuarioDTO;
use App\Application\DTO\Usuarios\ActualizarUsuarioDTO;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Domain\Interfaces\RolRepositoryInterface;
use App\Domain\Policies\AvatarPolicy;
use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\ConfiguracionService;
use App\Application\Services\RbacService;
use App\Application\UseCases\Avatares\EliminarAvatarUseCase;
use App\Application\UseCases\Avatares\FijarAvatarActualUseCase;
use App\Application\UseCases\Avatares\ListarAvataresUseCase;
use App\Application\UseCases\Avatares\SubirAvatarUseCase;
use App\Presentation\Presenters\AvatarPresenter;

final class UsuariosController extends AdminBaseController
{
    private const USUARIOS_BASE = '/admin/administracion/usuarios';

    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly CrearUsuarioUseCase      $crearUseCase,
        private readonly ListarUsuariosUseCase    $listarUseCase,
        private readonly ActualizarUsuarioUseCase $actualizarUseCase,
        private readonly EliminarUsuarioUseCase   $eliminarUseCase,
        private readonly UsuarioRepositoryInterface $usuarioRepo,
        private readonly RolRepositoryInterface     $rolRepo,
        private readonly SubirAvatarUseCase $subirAvatarUseCase,
        private readonly FijarAvatarActualUseCase $fijarAvatarUseCase,
        private readonly EliminarAvatarUseCase $eliminarAvatarUseCase,
        private readonly ListarAvataresUseCase $listarAvataresUseCase,
        private readonly AvatarPolicy $avatarPolicy,
        private readonly RbacService $rbacService
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        $pagina    = (int) $request->query('pagina', 1);
        $resultado = $this->listarUseCase->execute($pagina);

        return $this->view('admin/usuarios/index', [
            'titulo'    => 'Usuarios',
            'usuarios'  => array_map(fn($usuario) => $usuario->toArray(), $resultado['usuarios']),
            'paginator' => $resultado['paginator'],
            'total'     => $resultado['total'],
        ]);
    }

    public function crear(Request $request): Response
    {
        $roles = $this->rolRepo->findAll();

        return $this->view('admin/usuarios/crear', [
            'titulo' => 'Nuevo usuario',
            'roles'  => array_map(fn($rol) => $rol->toArray(), $roles),
        ]);
    }

    public function guardar(Request $request): Response
    {
        try {
            $this->verifyCsrf($request);

            $datosUsuario = $request->only(['nombre', 'apellido', 'email', 'password', 'rol_ids', 'activo']);

            $dto = new CrearUsuarioDTO(
                nombre:   trim((string) ($datosUsuario['nombre']   ?? '')),
                apellido: trim((string) ($datosUsuario['apellido'] ?? '')),
                email:    trim((string) ($datosUsuario['email']    ?? '')),
                password: (string) ($datosUsuario['password'] ?? ''),
                rolIds:   array_map('intval', (array) ($datosUsuario['rol_ids'] ?? [])),
                activo:   !empty($datosUsuario['activo'])
            );

            $this->crearUseCase->execute($dto);

            return $this->redirectWithFlash(self::USUARIOS_BASE, 'success', 'Usuario creado correctamente.');

        } catch (ValidationException $e) {
            Session::flashInput($request->all());
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());
            return $this->redirect(self::USUARIOS_BASE . '/crear');
        }
    }

    public function editar(Request $request): Response
    {
        $id      = (int) $request->param('id');
        $usuario = $this->usuarioRepo->findById($id);

        if ($usuario === null) {
            return Response::notFound();
        }

        $roles           = $this->rolRepo->findAll();
        $rolesDelUsuario = $this->rolRepo->buscarPorUsuarioId($id);
        $rolIdsAsignados = array_map(fn($rol) => $rol->id(), $rolesDelUsuario);

        return $this->view('admin/usuarios/editar', [
            'titulo'          => 'Editar usuario',
            'usuario'         => $usuario->toArray(),
            'roles'           => array_map(fn($rol) => $rol->toArray(), $roles),
            'rolIdsAsignados' => $rolIdsAsignados,
            'historial'       => array_map(
                fn($archivo) => $archivo->toArray(),
                $this->listarAvataresUseCase->execute($id)
            ),
        ]);
    }

    public function actualizar(Request $request): Response
    {
        $id = (int) $request->param('id');

        try {
            $this->verifyCsrf($request);

            $datosUsuario = $request->only(['nombre', 'apellido', 'email', 'password', 'rol_ids', 'activo']);

            $dto = new ActualizarUsuarioDTO(
                id:       $id,
                nombre:   trim((string) ($datosUsuario['nombre']   ?? '')),
                apellido: trim((string) ($datosUsuario['apellido'] ?? '')),
                email:    trim((string) ($datosUsuario['email']    ?? '')),
                rolIds:   array_map('intval', (array) ($datosUsuario['rol_ids'] ?? [])),
                activo:   !empty($datosUsuario['activo']),
                password: (string) ($datosUsuario['password'] ?? '')
            );

            $this->actualizarUseCase->execute($dto);

            return $this->redirectWithFlash(self::USUARIOS_BASE, 'success', 'Usuario actualizado correctamente.');

        } catch (ValidationException $e) {
            Session::flashInput($request->all());
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());
            return $this->redirect(self::USUARIOS_BASE . '/' . $id . '/editar');
        }
    }

    public function eliminar(Request $request): Response
    {
        $this->verifyCsrf($request);

        $id              = (int) $request->param('id');
        $usuarioActual   = $this->currentUser();
        $usuarioActualId = $usuarioActual['id'] ?? null;

        try {
            $this->eliminarUseCase->execute($id, $usuarioActualId);
            return $this->redirectWithFlash(self::USUARIOS_BASE, 'success', 'Usuario desactivado correctamente.');
        } catch (ValidationException $e) {
            return $this->redirectWithFlash(self::USUARIOS_BASE, 'error', $e->getMessage());
        }
    }

    // ── Endpoints JSON de avatar (objetivo = {id} de la ruta) ────────────────

    public function subirAvatar(Request $request): Response
    {
        $usuarioId = (int) $request->param('id');
        if (($guard = $this->autorizarAvatar($usuarioId)) !== null) {
            return $guard;
        }

        try {
            $this->verifyCsrf($request);

            $file = $request->file('avatar');
            if (!is_array($file)) {
                return $this->json(AvatarPresenter::error('No se recibió ningún archivo.'), 422);
            }

            $this->subirAvatarUseCase->execute($usuarioId, $file, $this->actorId());

            return $this->json(AvatarPresenter::payload($this->listarAvataresUseCase->execute($usuarioId)));
        } catch (ValidationException $e) {
            return $this->json(AvatarPresenter::error($e->getMessage()), 422);
        }
    }

    public function fijarAvatar(Request $request): Response
    {
        $usuarioId = (int) $request->param('id');
        if (($guard = $this->autorizarAvatar($usuarioId)) !== null) {
            return $guard;
        }

        try {
            $this->verifyCsrf($request);
            $this->fijarAvatarUseCase->execute($usuarioId, (int) $request->param('aid'), $this->actorId());

            return $this->json(AvatarPresenter::payload($this->listarAvataresUseCase->execute($usuarioId)));
        } catch (ValidationException $e) {
            return $this->json(AvatarPresenter::error($e->getMessage()), 422);
        }
    }

    public function eliminarAvatar(Request $request): Response
    {
        $usuarioId = (int) $request->param('id');
        if (($guard = $this->autorizarAvatar($usuarioId)) !== null) {
            return $guard;
        }

        try {
            $this->verifyCsrf($request);
            $this->eliminarAvatarUseCase->execute($usuarioId, (int) $request->param('aid'), $this->actorId());

            return $this->json(AvatarPresenter::payload($this->listarAvataresUseCase->execute($usuarioId)));
        } catch (ValidationException $e) {
            return $this->json(AvatarPresenter::error($e->getMessage()), 422);
        }
    }

    public function listarAvatares(Request $request): Response
    {
        $usuarioId = (int) $request->param('id');
        if (($guard = $this->autorizarAvatar($usuarioId)) !== null) {
            return $guard;
        }

        return $this->json(AvatarPresenter::payload($this->listarAvataresUseCase->execute($usuarioId)));
    }

    private function actorId(): int
    {
        return (int) ($this->currentUser()['id'] ?? 0);
    }

    /** Aplica AvatarPolicy; devuelve la respuesta de rechazo o null si procede. */
    private function autorizarAvatar(int $usuarioId): ?Response
    {
        if ($this->usuarioRepo->findById($usuarioId) === null) {
            return $this->json(AvatarPresenter::error('El usuario no existe.'), 404);
        }

        $permitido = $this->avatarPolicy->puedeGestionar(
            actorId: $this->actorId(),
            usuarioObjetivoId: $usuarioId,
            puedeGestionarUsuarios: $this->rbacService->puede('usuarios.gestionar')
        );

        return $permitido ? null : $this->json(AvatarPresenter::error('No autorizado.'), 403);
    }
}
