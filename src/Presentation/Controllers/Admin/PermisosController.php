<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Controllers\Admin;

use Lebytek\Framework\Presentation\Controllers\AdminBaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Domain\Entities\Permiso;
use Lebytek\Framework\Domain\ValueObjects\Slug;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Interfaces\PermisoRepositoryInterface;
use Lebytek\Framework\Domain\Rules\PermisoModuloFormatRule;
use Lebytek\Framework\Domain\Rules\PermisoSlugFormatRule;
use Lebytek\Framework\Application\Services\AdminNavigationMenuService;
use Lebytek\Framework\Application\Services\ConfiguracionService;

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
