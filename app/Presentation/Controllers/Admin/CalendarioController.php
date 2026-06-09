<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\CalendarViewModelBuilder;
use App\Application\Services\ConfiguracionService;
use App\Application\UseCases\Calendar\ListarEventosCalendarioUseCase;
use App\Domain\Calendar\DateRange;
use App\Domain\Entities\CalendarDefinition;
use App\Domain\Exceptions\AccesoException;
use App\Domain\Exceptions\ValidationException;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Presentation\Controllers\AdminBaseController;

final class CalendarioController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly CalendarViewModelBuilder $viewModelBuilder,
        private readonly ListarEventosCalendarioUseCase $listarEventos,
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        try {
            $key = (string) $request->param('key');
            $data = $this->viewModelBuilder->build($key);
            $data['titulo'] = $data['title'] . ' - Calendario';
            return $this->view('admin/calendario/index', $data);
        } catch (AccesoException) {
            return Response::forbidden();
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/dashboard', 'error', $e->getMessage());
        }
    }

    public function events(Request $request): Response
    {
        try {
            $key = (string) $request->param('key');
            $q = $request->all();

            $range = (isset($q['desde'], $q['hasta']) && $q['desde'] !== '' && $q['hasta'] !== '')
                ? DateRange::fromStrings((string) $q['desde'], (string) $q['hasta'])
                : DateRange::forMonth((int) date('Y'), (int) date('n'));

            $userId = (int) (($this->currentUser()['id'] ?? 0) ?: 0);
            // Filtros del feed: se incorporan en la Fase 2 (Task 2.2).
            $events = $this->listarEventos->execute($key, $range, $userId > 0 ? $userId : null);

            return Response::json(['eventos' => $events]);
        } catch (AccesoException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (ValidationException $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }
}
