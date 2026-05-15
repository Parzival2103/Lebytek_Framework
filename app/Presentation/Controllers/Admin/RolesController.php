<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Presentation\Controllers\AdminBaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Domain\Exceptions\ValidationException;
use App\Application\UseCases\Roles\CrearRolUseCase;
use App\Application\UseCases\Roles\ActualizarRolUseCase;
use App\Application\UseCases\Roles\EliminarRolUseCase;
use App\Application\UseCases\Roles\ListarRolesUseCase;
use App\Application\DTO\Roles\CrearRolDTO;
use App\Application\DTO\Roles\ActualizarRolDTO;
use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\ConfiguracionService;

final class RolesController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly ListarRolesUseCase     $listarUseCase,
        private readonly CrearRolUseCase        $crearUseCase,
        private readonly ActualizarRolUseCase   $actualizarUseCase,
        private readonly EliminarRolUseCase     $eliminarUseCase
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        $roles = $this->listarUseCase->execute();

        return $this->view('admin/roles/index', [
            'titulo' => 'Roles y permisos',
            'roles'  => $roles,
        ]);
    }

    public function crear(Request $request): Response
    {
        $agrupados = $this->listarUseCase->obtenerPermisosAgrupadosParaFormulario();

        return $this->view('admin/roles/crear', [
            'titulo'             => 'Nuevo rol',
            'permisosAgrupados' => $agrupados,
        ]);
    }

    public function guardar(Request $request): Response
    {
        try {
            $this->verifyCsrf($request);

            $datosRol = $request->only(['nombre', 'slug', 'descripcion', 'permiso_ids', 'activo']);

            $dto = new CrearRolDTO(
                nombre:      trim((string) ($datosRol['nombre']      ?? '')),
                slug:        trim((string) ($datosRol['slug']        ?? '')),
                descripcion: trim((string) ($datosRol['descripcion'] ?? '')),
                activo:      !empty($datosRol['activo']),
                permisoIds:  (array) ($datosRol['permiso_ids'] ?? [])
            );

            $this->crearUseCase->execute($dto);

            return $this->redirectWithFlash('/admin/administracion/roles', 'success', 'Rol creado correctamente.');

        } catch (ValidationException $e) {
            Session::flashInput($request->all());
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());
            return $this->redirect('/admin/administracion/roles/crear');
        }
    }

    public function editar(Request $request): Response
    {
        $id  = (int) $request->param('id');
        $rol = $this->listarUseCase->obtenerPorId($id);

        if ($rol === null) {
            return Response::notFound();
        }

        $agrupados           = $this->listarUseCase->obtenerPermisosAgrupadosParaFormulario();
        $permisoIdsAsignados = $this->listarUseCase->obtenerPermisosAsignados($id);

        return $this->view('admin/roles/editar', [
            'titulo'              => 'Editar rol',
            'rol'                 => $rol,
            'permisosAgrupados'   => $agrupados,
            'permisoIdsAsignados' => $permisoIdsAsignados,
        ]);
    }

    public function actualizar(Request $request): Response
    {
        $id = (int) $request->param('id');

        try {
            $this->verifyCsrf($request);

            $datosRol = $request->only(['nombre', 'slug', 'descripcion', 'permiso_ids', 'activo']);

            $dto = new ActualizarRolDTO(
                id:          $id,
                nombre:      trim((string) ($datosRol['nombre']      ?? '')),
                slug:        trim((string) ($datosRol['slug']        ?? '')),
                descripcion: trim((string) ($datosRol['descripcion'] ?? '')),
                activo:      !empty($datosRol['activo']),
                permisoIds:  (array) ($datosRol['permiso_ids'] ?? [])
            );

            $this->actualizarUseCase->execute($dto);

            return $this->redirectWithFlash('/admin/administracion/roles', 'success', 'Rol actualizado correctamente.');

        } catch (ValidationException $e) {
            Session::flashInput($request->all());
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());
            return $this->redirect("/admin/administracion/roles/{$id}/editar");
        }
    }

    public function eliminar(Request $request): Response
    {
        $this->verifyCsrf($request);
        $id = (int) $request->param('id');

        try {
            $this->eliminarUseCase->execute($id);
            return $this->redirectWithFlash('/admin/administracion/roles', 'success', 'Rol eliminado.');
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/administracion/roles', 'error', $e->getMessage());
        }
    }
}
