<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Application\Marketing\AcceptVariantProposalUseCase;
use App\Application\Marketing\RejectVariantProposalUseCase;
use App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface;
use App\Domain\Marketing\Contracts\VariantProposalRepositoryInterface;
use App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface;
use Lebytek\Framework\Application\Services\AdminNavigationMenuService;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Presentation\Controllers\AdminBaseController;

/**
 * Ops UI (Task 9) para revisar el aggregate en vivo de experimentos de
 * landing y aceptar/rechazar propuestas de reponderación (Task 8).
 *
 * Dual CSRF: `CsrfMiddleware` en la ruta (`routes/marketing_admin.php`) +
 * `verifyCsrf()` en el controlador — patrón de `MarketingOrdenesController`.
 */
final class MarketingExperimentsController extends AdminBaseController
{
    private const STALE_TOLERANCE = 1e-4;

    /** @param array<string, mixed> $experimentsConfig `config/marketing/landing_experiments.php` */
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly LandingMetricsRepositoryInterface $metrics,
        private readonly VariantWeightRepositoryInterface $weights,
        private readonly VariantProposalRepositoryInterface $proposals,
        private readonly AcceptVariantProposalUseCase $acceptProposal,
        private readonly RejectVariantProposalUseCase $rejectProposal,
        private readonly array $experimentsConfig,
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        $windowDays = (int) ($this->experimentsConfig['score_window_days'] ?? 14);
        $minSessions = (int) ($this->experimentsConfig['min_sessions'] ?? 50);

        $liveWeights = $this->weights->all();
        $aggregate = $this->metrics->aggregateForScore($windowDays);
        $pending = array_map(
            fn (array $row): array => $this->toPendingView($row, $liveWeights),
            $this->proposals->findPending(),
        );

        return $this->view('admin/marketing/experiments', [
            'titulo' => 'Experimentos de landing',
            'windowDays' => $windowDays,
            'minSessions' => $minSessions,
            'aggregate' => $aggregate,
            'liveWeights' => $liveWeights,
            'pending' => $pending,
        ]);
    }

    public function accept(Request $request): Response
    {
        $this->verifyCsrf($request);

        [$proposalId, $uid, $invalid] = $this->parseActionInput($request);
        if ($invalid) {
            return $this->redirectWithFlash('/admin/marketing/experimentos', 'error', 'Solicitud inválida.');
        }

        try {
            $this->acceptProposal->ejecutar($proposalId, $uid);

            return $this->redirectWithFlash('/admin/marketing/experimentos', 'success', 'Propuesta aceptada: pesos actualizados.');
        } catch (\InvalidArgumentException $e) {
            return $this->redirectWithFlash('/admin/marketing/experimentos', 'error', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->redirectWithFlash('/admin/marketing/experimentos', 'error', 'No se pudo aceptar: '.$e->getMessage());
        }
    }

    public function reject(Request $request): Response
    {
        $this->verifyCsrf($request);

        [$proposalId, $uid, $invalid] = $this->parseActionInput($request);
        if ($invalid) {
            return $this->redirectWithFlash('/admin/marketing/experimentos', 'error', 'Solicitud inválida.');
        }

        try {
            $this->rejectProposal->ejecutar($proposalId, $uid);

            return $this->redirectWithFlash('/admin/marketing/experimentos', 'success', 'Propuesta rechazada.');
        } catch (\InvalidArgumentException $e) {
            return $this->redirectWithFlash('/admin/marketing/experimentos', 'error', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->redirectWithFlash('/admin/marketing/experimentos', 'error', 'No se pudo rechazar: '.$e->getMessage());
        }
    }

    /** @return array{0:int,1:int,2:bool} [proposalId, userId, invalid] */
    private function parseActionInput(Request $request): array
    {
        $proposalId = (int) $request->input('proposal_id', 0);
        $uid = $this->currentUserId();

        return [$proposalId, $uid, $proposalId <= 0 || $uid <= 0];
    }

    private function currentUserId(): int
    {
        $user = Session::get('auth_user');

        return is_array($user) && isset($user['id']) ? (int) $user['id'] : 0;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, float> $liveWeights
     * @return array<string, mixed>
     */
    private function toPendingView(array $row, array $liveWeights): array
    {
        $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
        $currentSnapshot = is_array($payload['current_weights'] ?? null) ? $payload['current_weights'] : [];
        $suggested = is_array($payload['suggested_weights'] ?? null) ? $payload['suggested_weights'] : [];

        return [
            'id' => (int) $row['id'],
            'created_at' => (string) ($row['created_at'] ?? ''),
            'reason' => (string) ($payload['reason'] ?? ''),
            'current_weights' => $currentSnapshot,
            'suggested_weights' => $suggested,
            'is_stale' => $this->isStale($currentSnapshot, $liveWeights),
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, float> $live
     */
    private function isStale(array $snapshot, array $live): bool
    {
        foreach ($snapshot as $slug => $value) {
            $current = $live[(string) $slug] ?? null;
            if ($current === null || abs((float) $current - (float) $value) > self::STALE_TOLERANCE) {
                return true;
            }
        }

        return false;
    }
}
