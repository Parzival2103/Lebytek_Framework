<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\ConfiguracionService;
use App\Application\Services\CrudResourceService;
use App\Kernel\Helpers\LebytekUiConfig;
use App\Domain\Exceptions\AccesoException;
use App\Domain\Exceptions\ValidationException;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Presentation\Controllers\AdminBaseController;

final class CrudController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly CrudResourceService $crudResourceService
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        try {
            $resource = (string) $request->param('resource');
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $data = $this->crudResourceService->buildIndexData($resource, $request->all(), $userId > 0 ? $userId : null);
            $sys = $this->configuracionService->all();
            if (!($data['tableCompact'] ?? false) && LebytekUiConfig::globalTableCompact(is_array($sys) ? $sys : [])) {
                $data['tableCompact'] = true;
            }
            $data['titulo'] = $data['title'] . ' - CRUD';

            return $this->view('admin/crud/index', $data);
        } catch (AccesoException $e) {
            return Response::forbidden();
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/dashboard', 'error', $e->getMessage());
        }
    }

    public function create(Request $request): Response
    {
        try {
            $resource = (string) $request->param('resource');
            $data = $this->crudResourceService->buildCreateData($resource, $request->all());
            $data['titulo'] = 'Crear ' . $data['title'];
            return $this->view('admin/crud/form', $data);
        } catch (AccesoException) {
            return Response::forbidden();
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/dashboard', 'error', $e->getMessage());
        }
    }

    public function store(Request $request): Response
    {
        $resource = (string) $request->param('resource');
        try {
            $this->verifyCsrf($request);

            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $this->crudResourceService->store($resource, $request->all(), $_FILES, $userId > 0 ? $userId : null, $request->ip());
            $returnTo = (string) $request->input('return_to', '');
            $redirectTo = $this->crudResourceService->resolveListReturnUrl(
                $resource,
                $returnTo !== '' ? $returnTo : null
            );
            return $this->redirectWithFlash($redirectTo, 'success', 'Registro creado correctamente.');
        } catch (AccesoException) {
            return Response::forbidden();
        } catch (ValidationException $e) {
            Session::flashInput(['_crud_values' => $request->all()]);
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());
            return $this->redirect('/admin/crud/' . $resource . '/crear');
        }
    }

    public function show(Request $request): Response
    {
        try {
            $resource = (string) $request->param('resource');
            $id = (int) $request->param('id');
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $data = $this->crudResourceService->buildShowData($resource, $id, $userId > 0 ? $userId : null);
            $data['titulo'] = 'Detalle de ' . $data['title'];
            return $this->view('admin/crud/show', $data);
        } catch (AccesoException) {
            return Response::forbidden();
        } catch (ValidationException) {
            return Response::notFound();
        }
    }

    public function edit(Request $request): Response
    {
        try {
            $resource = (string) $request->param('resource');
            $id = (int) $request->param('id');
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $returnTo = (string) $request->input('return_to', '');
            $data = $this->crudResourceService->buildEditData(
                $resource,
                $id,
                $userId > 0 ? $userId : null,
                $returnTo !== '' ? $returnTo : null
            );
            $data['titulo'] = 'Editar ' . $data['title'];
            return $this->view('admin/crud/form', $data);
        } catch (AccesoException) {
            return Response::forbidden();
        } catch (ValidationException) {
            return Response::notFound();
        }
    }

    public function update(Request $request): Response
    {
        $resource = (string) $request->param('resource');
        $id = (int) $request->param('id');
        try {
            $this->verifyCsrf($request);
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $this->crudResourceService->update($resource, $id, $request->all(), $_FILES, $userId > 0 ? $userId : null, $request->ip());
            $returnTo = (string) $request->input('return_to', '');
            return $this->redirectWithFlash(
                $this->crudResourceService->resolveListReturnUrl($resource, $returnTo !== '' ? $returnTo : null),
                'success',
                'Registro actualizado correctamente.'
            );
        } catch (AccesoException) {
            return Response::forbidden();
        } catch (ValidationException $e) {
            Session::flashInput(['_crud_values' => $request->all()]);
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());
            return $this->redirect('/admin/crud/' . $resource . '/' . $id . '/editar');
        }
    }

    public function delete(Request $request): Response
    {
        $resource = (string) $request->param('resource');
        $id = (int) $request->param('id');
        try {
            $this->verifyCsrf($request);
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $this->crudResourceService->delete($resource, $id, $userId > 0 ? $userId : null, $request->ip());
            $returnTo = (string) $request->input('return_to', '');
            return $this->redirectWithFlash(
                $this->crudResourceService->resolveListReturnUrl($resource, $returnTo !== '' ? $returnTo : null),
                'success',
                'Registro eliminado correctamente.'
            );
        } catch (AccesoException) {
            return Response::forbidden();
        } catch (ValidationException $e) {
            $returnTo = (string) $request->input('return_to', '');
            return $this->redirectWithFlash(
                $this->crudResourceService->resolveListReturnUrl($resource, $returnTo !== '' ? $returnTo : null),
                'error',
                $e->getMessage()
            );
        }
    }

    public function action(Request $request): Response
    {
        $resource = (string) $request->param('resource');
        $id = (int) $request->param('id');
        $action = (string) $request->param('action');
        try {
            $this->verifyCsrf($request);
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $this->crudResourceService->runAction($resource, $id, $action, $request->all(), $userId > 0 ? $userId : null, $request->ip());
            $returnTo = (string) $request->input('return_to', '');
            return $this->redirectWithFlash(
                $this->crudResourceService->resolveListReturnUrl($resource, $returnTo !== '' ? $returnTo : null),
                'success',
                'Acción ejecutada correctamente.'
            );
        } catch (AccesoException) {
            return Response::forbidden();
        } catch (ValidationException $e) {
            $returnTo = (string) $request->input('return_to', '');
            return $this->redirectWithFlash(
                $this->crudResourceService->resolveListReturnUrl($resource, $returnTo !== '' ? $returnTo : null),
                'error',
                $e->getMessage()
            );
        }
    }

    public function bulkAction(Request $request): Response
    {
        $resource = (string) $request->param('resource');
        $action = (string) $request->param('action');
        try {
            $this->verifyCsrf($request);
            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            $idsRaw = $request->all()['ids'] ?? [];
            $ids = is_array($idsRaw) ? array_map('intval', $idsRaw) : [];
            $returnTo = (string) $request->input('return_to', '');
            $listUrl = $this->crudResourceService->resolveListReturnUrl($resource, $returnTo !== '' ? $returnTo : null);
            if ($ids === []) {
                return $this->redirectWithFlash($listUrl, 'error', 'No se seleccionaron registros.');
            }
            $summary = $this->crudResourceService->runBulkAction($resource, $action, $ids, $request->all(), $userId > 0 ? $userId : null, $request->ip());
            $type = $summary['fail'] > 0 ? 'warning' : 'success';
            $msg = "Acción masiva: {$summary['ok']} correctos, {$summary['fail']} con error.";
            return $this->redirectWithFlash($listUrl, $type, $msg);
        } catch (AccesoException) {
            return Response::forbidden();
        } catch (ValidationException $e) {
            $returnTo = (string) $request->input('return_to', '');
            return $this->redirectWithFlash(
                $this->crudResourceService->resolveListReturnUrl($resource, $returnTo !== '' ? $returnTo : null),
                'error',
                $e->getMessage()
            );
        }
    }
}
