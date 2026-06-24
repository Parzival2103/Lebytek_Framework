<?php
declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Application\Reporte\GenerarDocumentoUseCase;
use App\Application\Reporte\GenerarReporteUseCase;
use App\Application\Reporte\GuardarReporteUseCase;
use App\Application\Reporte\ReporteConfigLoader;
use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\ConfiguracionService;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\ReporteRepositoryInterface;
use App\Domain\Policies\RbacPolicy;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Kernel\Vertical\VerticalProfile;
use App\Presentation\Controllers\AdminBaseController;

/**
 * Módulo Reportes: índice estilo CRUD + wizard de colección + generación de PDF.
 */
final class ReportesController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly ReporteConfigLoader $loader,
        private readonly ReporteRepositoryInterface $repository,
        private readonly GuardarReporteUseCase $guardar,
        private readonly GenerarReporteUseCase $generar,
        private readonly GenerarDocumentoUseCase $documentos,
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        if (!$this->moduloHabilitado()) {
            return Response::notFound();
        }

        return $this->view('admin/reportes/index', [
            'titulo'   => 'Reportes',
            'reportes' => $this->repository->listForUser($this->userId()),
        ]);
    }

    public function crear(Request $request): Response
    {
        if (!$this->moduloHabilitado()) {
            return Response::notFound();
        }

        return $this->view('admin/reportes/builder', [
            'titulo'      => 'Nuevo reporte',
            'reporte'     => null,
            'fuentes'     => $this->loader->listFuentes(),
            'fuentesMeta' => $this->buildFuentesMeta(),
        ]);
    }

    public function editar(Request $request): Response
    {
        if (!$this->moduloHabilitado()) {
            return Response::notFound();
        }

        $id = (int) $request->param('id');
        $reporte = $this->repository->findVisible($id, $this->userId());
        if ($reporte === null) {
            return Response::notFound();
        }

        return $this->view('admin/reportes/builder', [
            'titulo'      => 'Editar reporte',
            'reporte'     => $reporte,
            'fuentes'     => $this->loader->listFuentes(),
            'fuentesMeta' => $this->buildFuentesMeta(),
        ]);
    }

    public function guardar(Request $request): Response
    {
        if (!$this->moduloHabilitado()) {
            return Response::notFound();
        }

        $this->verifyCsrf($request);

        try {
            $data = $this->guardar->toRow($this->inputFromRequest($request), $this->userId());
            $this->repository->create($data);
            return $this->redirectWithFlash('/admin/reportes', 'success', 'Reporte creado.');
        } catch (ValidationException $e) {
            Session::flash('errors', $this->flashErrors($e));
            return $this->redirect('/admin/reportes/crear');
        }
    }

    public function actualizar(Request $request): Response
    {
        if (!$this->moduloHabilitado()) {
            return Response::notFound();
        }

        $this->verifyCsrf($request);

        $id = (int) $request->param('id');
        $reporte = $this->repository->findVisible($id, $this->userId());
        if ($reporte === null || $reporte->createdBy() !== $this->userId()) {
            return Response::notFound();
        }

        try {
            $data = $this->guardar->toRow($this->inputFromRequest($request), $this->userId());
            $this->repository->update($id, $this->userId(), $data);
            return $this->redirectWithFlash('/admin/reportes', 'success', 'Reporte actualizado.');
        } catch (ValidationException $e) {
            Session::flash('errors', $this->flashErrors($e));
            return $this->redirect('/admin/reportes/' . $id . '/editar');
        }
    }

    public function eliminar(Request $request): Response
    {
        if (!$this->moduloHabilitado()) {
            return Response::notFound();
        }

        $this->verifyCsrf($request);

        $id = (int) $request->param('id');
        $reporte = $this->repository->findVisible($id, $this->userId());
        if ($reporte === null || $reporte->createdBy() !== $this->userId()) {
            return Response::notFound();
        }

        $this->repository->delete($id, $this->userId());
        return $this->redirectWithFlash('/admin/reportes', 'success', 'Reporte eliminado.');
    }

    public function generar(Request $request): Response
    {
        if (!$this->moduloHabilitado()) {
            return Response::notFound();
        }

        $this->verifyCsrf($request);

        $id = (int) $request->param('id');
        $reporte = $this->repository->findVisible($id, $this->userId());
        if ($reporte === null) {
            return Response::notFound();
        }

        try {
            $bytes = $this->generar->generar($reporte, $this->userId(), $this->canChecker());
        } catch (ValidationException $e) {
            Session::flash('errors', $this->flashErrors($e));
            return $this->redirect('/admin/reportes');
        }

        return Response::download($bytes, 'reporte-' . $id . '.pdf', 'application/pdf');
    }

    public function documento(Request $request): Response
    {
        if (!$this->moduloHabilitado()) {
            return Response::notFound();
        }

        $fuente = trim((string) $request->query('fuente', ''));
        $id = (int) $request->query('id', 0);
        $template = trim((string) $request->query('template', ''));
        if ($fuente === '' || $id <= 0 || $template === '') {
            return Response::notFound();
        }

        try {
            $bytes = $this->documentos->generar($fuente, $id, $template, $this->userId(), $this->canChecker());
        } catch (ValidationException) {
            return Response::notFound();
        } catch (\Throwable $e) {
            \App\Kernel\Logging\AppLogger::error('Reporte documento: fallo de generación', [
                'fuente' => $fuente, 'id' => $id, 'template' => $template, 'error' => $e->getMessage(),
            ]);
            return Response::notFound();
        }

        if ($bytes === null) {
            return Response::notFound();
        }

        return Response::download($bytes, 'documento-' . $fuente . '-' . $id . '.pdf', 'application/pdf');
    }

    private function moduloHabilitado(): bool
    {
        return VerticalProfile::moduleEnabled('reportes');
    }

    private function userId(): int
    {
        $user = $this->currentUser();
        return (int) ($user['id'] ?? 0);
    }

    /** @return callable(string):bool */
    private function canChecker(): callable
    {
        $rbac = new RbacPolicy(Session::get('auth_permisos', []), Session::get('auth_roles', []));
        return static fn(string $slug): bool => $rbac->puede($slug);
    }

    /** @return array<string,mixed> */
    private function inputFromRequest(Request $request): array
    {
        $input = $request->all();

        foreach (['columnas', 'tratamientos', 'filtros', 'periodo', 'opciones'] as $key) {
            $raw = $input[$key] ?? null;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $input[$key] = $decoded;
                }
            }
        }

        if (isset($input['compartido'])) {
            $input['compartido'] = in_array((string) $input['compartido'], ['1', 'true', 'on'], true);
        }

        return $input;
    }

    /** @return array<string,string> */
    private function flashErrors(ValidationException $e): array
    {
        $errors = $e->getErrors();
        if ($errors === []) {
            return ['_' => $e->getMessage()];
        }
        $out = [];
        foreach ($errors as $i => $msg) {
            $out[is_string($i) ? $i : '_' . $i] = (string) $msg;
        }
        return $out;
    }

    /** @return array<string,array<string,mixed>> */
    private function buildFuentesMeta(): array
    {
        $meta = [];
        foreach (array_keys($this->loader->listFuentes()) as $key) {
            try {
                $f = $this->loader->load($key);
                $meta[$key] = [
                    'columns'       => $f->columns(),
                    'groupBy'       => $f->groupBy(),
                    'orderBy'       => $f->orderBy(),
                    'filters'       => $f->filters(),
                    'periodPresets' => $f->periodPresets(),
                    'hasPeriod'     => $f->hasPeriod(),
                ];
            } catch (\Throwable) {
                continue;
            }
        }
        return $meta;
    }
}
