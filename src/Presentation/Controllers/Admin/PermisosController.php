<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Presentation\Controllers\AdminBaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Domain\Entities\Permiso;
use App\Domain\ValueObjects\Slug;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\PermisoRepositoryInterface;
use App\Domain\Rules\PermisoModuloFormatRule;
use App\Domain\Rules\PermisoSlugFormatRule;
use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\ConfiguracionService;

final class PermisosController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly PermisoRepositoryInterface $permisoRepo
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        $permisos  = $this->permisoRepo->findAll();
        $agrupados = [];
        foreach ($permisos as $p) {
            $key = $p->modulo() !== '' ? $p->modulo() : 'general';
            $agrupados[$key][] = $p->toArray();
        }
        ksort($agrupados);

        return $this->view('admin/permisos/index', [
            'titulo'    => 'Permisos',
            'agrupados' => $agrupados,
        ]);
    }

    public function crear(Request $request): Response
    {
        return $this->view('admin/permisos/crear', [
            'titulo' => 'Nuevo permiso',
        ]);
    }

    public function guardar(Request $request): Response
    {
        try {
            $this->verifyCsrf($request);

            $data = $request->only(['nombre', 'slug', 'modulo', 'descripcion']);

            $nombre = trim((string) ($data['nombre'] ?? ''));
            if ($nombre === '') {
                throw new ValidationException('El nombre es obligatorio.', ['nombre' => 'Requerido.']);
            }

            $modulo = PermisoModuloFormatRule::assertValid((string) ($data['modulo'] ?? ''));

            $slugStr = trim((string) ($data['slug'] ?? ''));
            if ($slugStr === '') {
                throw new ValidationException(
                    'El slug es obligatorio.',
                    ['slug' => 'Indica modulo.accion (ej. reportes.ver).']
                );
            }

            PermisoSlugFormatRule::assertValid($slugStr);

            if ($this->permisoRepo->findBySlug($slugStr) !== null) {
                throw new ValidationException(
                    'Ya existe un permiso con ese slug.',
                    ['slug' => 'Elige otro slug único.']
                );
            }

            $permiso = new Permiso(
                nombre:      $nombre,
                slug:        new Slug($slugStr),
                modulo:      $modulo,
                descripcion: trim((string) ($data['descripcion'] ?? '')),
            );

            $this->permisoRepo->save($permiso);

            return $this->redirectWithFlash(
                '/admin/administracion/permisos',
                'success',
                'Permiso creado correctamente.'
            );
        } catch (ValidationException $e) {
            Session::flashInput($request->all());
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());

            return $this->redirect('/admin/administracion/permisos/crear');
        }
    }

    public function editar(Request $request): Response
    {
        $id      = (int) $request->param('id');
        $permiso = $this->permisoRepo->findById($id);

        if ($permiso === null) {
            return Response::notFound();
        }

        return $this->view('admin/permisos/editar', [
            'titulo'  => 'Editar permiso',
            'permiso' => $permiso->toArray(),
        ]);
    }

    public function actualizar(Request $request): Response
    {
        $id = (int) $request->param('id');

        try {
            $this->verifyCsrf($request);

            $data = $request->only(['nombre', 'slug', 'modulo', 'descripcion']);

            $nombre = trim((string) ($data['nombre'] ?? ''));
            if ($nombre === '') {
                throw new ValidationException('El nombre es obligatorio.', ['nombre' => 'Requerido.']);
            }

            $modulo = PermisoModuloFormatRule::assertValid((string) ($data['modulo'] ?? ''));

            $slugStr = trim((string) ($data['slug'] ?? ''));
            PermisoSlugFormatRule::assertValid($slugStr);

            $otro = $this->permisoRepo->findBySlug($slugStr);
            if ($otro !== null && (int) $otro->id() !== $id) {
                throw new ValidationException(
                    'Ya existe otro permiso con ese slug.',
                    ['slug' => 'Elige otro slug único.']
                );
            }

            $permiso = new Permiso(
                nombre:      $nombre,
                slug:        new Slug($slugStr),
                modulo:      $modulo,
                descripcion: trim((string) ($data['descripcion'] ?? '')),
                id:          $id,
            );

            $this->permisoRepo->update($permiso);

            return $this->redirectWithFlash(
                '/admin/administracion/permisos',
                'success',
                'Permiso actualizado correctamente.'
            );
        } catch (ValidationException $e) {
            Session::flashInput($request->all());
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());

            return $this->redirect("/admin/administracion/permisos/{$id}/editar");
        }
    }

    public function eliminar(Request $request): Response
    {
        $this->verifyCsrf($request);
        $id = (int) $request->param('id');
        $this->permisoRepo->delete($id);

        return $this->redirectWithFlash(
            '/admin/administracion/permisos',
            'success',
            'Permiso eliminado.'
        );
    }
}
