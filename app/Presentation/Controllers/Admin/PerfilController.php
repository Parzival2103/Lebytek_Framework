<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Presentation\Controllers\AdminBaseController;
use App\Presentation\Presenters\AvatarPresenter;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\ConfiguracionService;
use App\Application\UseCases\Avatares\EliminarAvatarUseCase;
use App\Application\UseCases\Avatares\FijarAvatarActualUseCase;
use App\Application\UseCases\Avatares\ListarAvataresUseCase;
use App\Application\UseCases\Avatares\SubirAvatarUseCase;
use App\Application\UseCases\Perfil\ActualizarPerfilUseCase;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\UsuarioRepositoryInterface;

/*
|--------------------------------------------------------------------------
| PerfilController — Perfil propio del usuario autenticado
|--------------------------------------------------------------------------
| El actor y el objetivo salen SIEMPRE de la sesión; nunca del request.
| Cualquier autenticado puede usarlo (sin RBAC extra).
*/

final class PerfilController extends AdminBaseController
{
    private const PERFIL_BASE = '/admin/perfil';

    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly UsuarioRepositoryInterface $usuarioRepo,
        private readonly ActualizarPerfilUseCase $actualizarPerfil,
        private readonly SubirAvatarUseCase $subirAvatarUseCase,
        private readonly FijarAvatarActualUseCase $fijarAvatarUseCase,
        private readonly EliminarAvatarUseCase $eliminarAvatarUseCase,
        private readonly ListarAvataresUseCase $listarAvataresUseCase
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        $actorId = $this->actorId();
        $usuario = $this->usuarioRepo->findById($actorId);

        if ($usuario === null) {
            return Response::notFound();
        }

        return $this->view('admin/perfil/index', [
            'titulo'    => 'Mi perfil',
            'usuario'   => $usuario->toArray(),
            'historial' => array_map(
                fn($archivo) => $archivo->toArray(),
                $this->listarAvataresUseCase->execute($actorId)
            ),
        ]);
    }

    public function actualizar(Request $request): Response
    {
        $actorId = $this->actorId();

        try {
            $this->verifyCsrf($request);

            $datos = $request->only(['nombre', 'apellido', 'email']);
            $datos = array_map(static fn($v) => trim((string) $v), $datos);

            $this->actualizarPerfil->execute($actorId, $datos);
            $this->refrescarSesion($actorId);

            return $this->redirectWithFlash(self::PERFIL_BASE, 'success', 'Perfil actualizado correctamente.');
        } catch (ValidationException $e) {
            Session::flashInput($request->all());
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());
            return $this->redirect(self::PERFIL_BASE);
        }
    }

    // ── Endpoints JSON de avatar (objetivo = actor de sesión) ────────────────

    public function subirAvatar(Request $request): Response
    {
        $actorId = $this->actorId();

        try {
            $this->verifyCsrf($request);

            $file = $request->file('avatar');
            if (!is_array($file)) {
                return $this->json(AvatarPresenter::error('No se recibió ningún archivo.'), 422);
            }

            $this->subirAvatarUseCase->execute($actorId, $file, $actorId);
            $this->refrescarSesion($actorId);

            return $this->json(AvatarPresenter::payload($this->listarAvataresUseCase->execute($actorId)));
        } catch (ValidationException $e) {
            return $this->json(AvatarPresenter::error($e->getMessage()), 422);
        }
    }

    public function fijarAvatar(Request $request): Response
    {
        $actorId = $this->actorId();

        try {
            $this->verifyCsrf($request);

            $this->fijarAvatarUseCase->execute($actorId, (int) $request->param('id'), $actorId);
            $this->refrescarSesion($actorId);

            return $this->json(AvatarPresenter::payload($this->listarAvataresUseCase->execute($actorId)));
        } catch (ValidationException $e) {
            return $this->json(AvatarPresenter::error($e->getMessage()), 422);
        }
    }

    public function eliminarAvatar(Request $request): Response
    {
        $actorId = $this->actorId();

        try {
            $this->verifyCsrf($request);

            $this->eliminarAvatarUseCase->execute($actorId, (int) $request->param('id'), $actorId);
            $this->refrescarSesion($actorId);

            return $this->json(AvatarPresenter::payload($this->listarAvataresUseCase->execute($actorId)));
        } catch (ValidationException $e) {
            return $this->json(AvatarPresenter::error($e->getMessage()), 422);
        }
    }

    public function listarAvatares(Request $request): Response
    {
        return $this->json(AvatarPresenter::payload(
            $this->listarAvataresUseCase->execute($this->actorId())
        ));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function actorId(): int
    {
        return (int) ($this->currentUser()['id'] ?? 0);
    }

    /** Refresca auth_user en sesión para que topbar/avatar reflejen el cambio. */
    private function refrescarSesion(int $usuarioId): void
    {
        $usuario = $this->usuarioRepo->findById($usuarioId);
        if ($usuario === null) {
            return;
        }

        Session::set('auth_user', [
            'id'             => $usuario->id(),
            'nombre'         => $usuario->nombre(),
            'apellido'       => $usuario->apellido(),
            'nombreCompleto' => $usuario->nombreCompleto(),
            'email'          => (string) $usuario->email(),
            'avatar'         => $usuario->avatar(),
        ]);
    }
}
