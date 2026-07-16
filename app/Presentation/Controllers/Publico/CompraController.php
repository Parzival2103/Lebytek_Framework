<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Application\Marketing\BankTransferConfig;
use App\Application\Marketing\CrearOrdenMembresiaUseCase;
use App\Application\Marketing\IniciarPagoStripeUseCase;
use App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Helpers\LebytekUiConfig;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;

final class CompraController extends BaseController
{
    private const PURCHASABLE_SLUGS = ['starter', 'business'];

    public function __construct(
        private readonly ConfiguracionService $configuracionService,
        private readonly MarketingContentRepositoryInterface $content,
        private readonly MembershipOrderRepositoryInterface $orders,
        private readonly CrearOrdenMembresiaUseCase $crearOrden,
        private readonly ?IniciarPagoStripeUseCase $iniciarPago = null,
    ) {}

    public function show(Request $request): Response
    {
        $slug = (string) $request->param('slug', '');
        $paquete = $this->resolvePurchasablePackage($slug);
        if ($paquete === null) {
            Session::flash('error', 'Paquete no disponible para compra.');
            return $this->redirect('/#paquetes');
        }

        $ciclo = $this->normalizeCiclo((string) $request->query('ciclo', 'monthly'));
        $ui = $this->uiVars();

        return $this->view('publico/compra_form', [
            'pageTitle' => 'Comprar '.($paquete['nombre'] ?? $slug).' — '.$ui['empresaNombre'],
            'empresaNombre' => $ui['empresaNombre'],
            'empresaLogo' => $ui['empresaLogo'],
            'paquete' => $paquete,
            'slug' => $slug,
            'ciclo' => $ciclo,
            'precio' => $this->priceForCycle($paquete, $ciclo),
            ...$ui['theme'],
        ], 'publico/layout');
    }

    public function submit(Request $request): Response
    {
        $this->verifyCsrf($request);
        $slug = (string) $request->param('slug', '');

        if (! $this->allowPost()) {
            Session::flash('error', 'Demasiados intentos. Espera un momento e inténtalo de nuevo.');
            return $this->redirect('/comprar/'.$slug);
        }

        $paquete = $this->resolvePurchasablePackage($slug);
        if ($paquete === null) {
            Session::flash('error', 'Paquete no disponible para compra.');
            return $this->redirect('/#paquetes');
        }

        $ciclo = $this->normalizeCiclo((string) $request->input('ciclo', 'monthly'));
        $metodo = (string) $request->input('metodo_pago', 'transfer');

        try {
            $result = $this->crearOrden->ejecutar($slug, [
                'nombre' => (string) $request->input('nombre', ''),
                'email' => (string) $request->input('email', ''),
                'telefono' => (string) $request->input('telefono', ''),
                'empresa' => (string) $request->input('empresa', ''),
                'direccion' => (string) $request->input('direccion', ''),
                'rfc' => (string) $request->input('rfc', ''),
                'ciclo' => $ciclo,
                'metodo_pago' => $metodo,
            ]);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/comprar/'.$slug.'?ciclo='.$ciclo);
        } catch (\Throwable $e) {
            Session::flash('error', 'No pudimos registrar tu orden. Inténtalo de nuevo.');
            return $this->redirect('/comprar/'.$slug.'?ciclo='.$ciclo);
        }

        $order = $result['order'];
        if ($metodo === 'stripe') {
            if ($this->iniciarPago === null) {
                Session::flash('error', 'Pago con tarjeta no disponible en este momento. Usa transferencia bancaria.');
                return $this->redirect('/comprar/'.$slug.'?ciclo='.$ciclo);
            }

            try {
                return $this->redirect($this->iniciarPago->ejecutar((int) ($order['id'] ?? 0)));
            } catch (\Throwable $e) {
                $logLine = sprintf(
                    "[%s] order_id=%s %s: %s @%s:%d\n%s\n\n",
                    date('c'),
                    (string) ($order['id'] ?? ''),
                    $e::class,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString(),
                );
                @file_put_contents(STORAGE_PATH.'/logs/stripe_iniciar.log', $logLine, FILE_APPEND | LOCK_EX);
                Session::flash(
                    'error',
                    'No pudimos iniciar el pago con tarjeta. Tu orden quedó registrada; inténtalo de nuevo o usa transferencia.',
                );
                return $this->redirect('/comprar/'.$slug.'?ciclo='.$ciclo);
            }
        }

        if (empty($result['alert_sent'])) {
            // Soft warning for ops: order kept; transfer_notified_at left null for CRUD follow-up.
            Session::flash(
                'warning',
                'Orden registrada. El aviso WhatsApp al equipo no se envió; operaciones debe revisarla en el panel.',
            );
        }

        return $this->redirect('/comprar/orden/'.($order['public_id'] ?? '').'/transferencia');
    }

    public function transferencia(Request $request): Response
    {
        $publicId = (string) $request->param('publicId', '');
        $order = $this->orders->findByPublicId($publicId);
        if ($order === null) {
            Session::flash('error', 'Orden no encontrada.');
            return $this->redirect('/#paquetes');
        }

        $ui = $this->uiVars();
        $bank = BankTransferConfig::forOrder($publicId);

        return $this->view('publico/compra_transferencia', [
            'pageTitle' => 'Transferencia bancaria — '.$ui['empresaNombre'],
            'empresaNombre' => $ui['empresaNombre'],
            'empresaLogo' => $ui['empresaLogo'],
            'order' => $order,
            'bank' => $bank,
            ...$ui['theme'],
        ], 'publico/layout');
    }

    public function pagoExito(Request $request): Response
    {
        $publicId = (string) $request->param('publicId', '');
        $order = $this->orders->findByPublicId($publicId);
        $ui = $this->uiVars();

        return $this->view('publico/compra_pago_exito', [
            'pageTitle' => 'Confirmación de pago — '.$ui['empresaNombre'],
            'empresaNombre' => $ui['empresaNombre'],
            'empresaLogo' => $ui['empresaLogo'],
            'order' => $order,
            'publicId' => $publicId,
            ...$ui['theme'],
        ], 'publico/layout');
    }

    public function pagoCancelado(Request $request): Response
    {
        $publicId = (string) $request->param('publicId', '');
        $ui = $this->uiVars();

        return $this->view('publico/compra_pago_cancelado', [
            'pageTitle' => 'Pago cancelado — '.$ui['empresaNombre'],
            'empresaNombre' => $ui['empresaNombre'],
            'empresaLogo' => $ui['empresaLogo'],
            'publicId' => $publicId,
            ...$ui['theme'],
        ], 'publico/layout');
    }

    /** @return array<string, mixed>|null */
    private function resolvePurchasablePackage(string $slug): ?array
    {
        if (! in_array($slug, self::PURCHASABLE_SLUGS, true)) {
            return null;
        }

        $paquete = $this->content->findPaqueteBySlug($slug);
        if ($paquete === null) {
            return null;
        }

        if (! $this->isPositiveNumericPrice($paquete['precio_mensual'] ?? null)) {
            return null;
        }

        return $paquete;
    }

    private function normalizeCiclo(string $ciclo): string
    {
        return $ciclo === 'annual' ? 'annual' : 'monthly';
    }

    /** @param array<string, mixed> $paquete */
    private function priceForCycle(array $paquete, string $ciclo): ?float
    {
        $col = $ciclo === 'annual' ? 'precio_anual' : 'precio_mensual';
        $raw = $paquete[$col] ?? null;

        return $this->isPositiveNumericPrice($raw) ? (float) $raw : null;
    }

    private function isPositiveNumericPrice(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return is_numeric($value) && (float) $value > 0;
    }

    private function allowPost(): bool
    {
        $key = 'compra_posts';
        $data = Session::get($key);
        if (! is_array($data)) {
            $data = ['count' => 0, 'window_start' => time()];
        }
        if (time() - (int) ($data['window_start'] ?? 0) > 3600) {
            $data = ['count' => 0, 'window_start' => time()];
        }
        if ((int) ($data['count'] ?? 0) >= 10) {
            return false;
        }
        $data['count'] = (int) ($data['count'] ?? 0) + 1;
        Session::set($key, $data);

        return true;
    }

    /** @return array{empresaNombre:string,empresaLogo:string,theme:array<string,mixed>} */
    private function uiVars(): array
    {
        $ui = LebytekUiConfig::resolve($this->configuracionService->all());

        return [
            'empresaNombre' => $this->configuracionService->empresaNombre(),
            'empresaLogo' => $this->configuracionService->empresaLogo(),
            'theme' => [
                'primaryColor' => $ui['primaryColor'],
                'primaryHover' => $ui['primaryHover'],
                'primaryActive' => $ui['primaryActive'],
                'primarySubtle' => $ui['primarySubtle'],
                'primaryRgb' => $ui['primaryRgb'],
                'lebytekCssVariables' => $ui['lebytekCssVariables'],
                'bodyBg' => $ui['bodyBg'],
                'darkMode' => $ui['darkMode'],
            ],
        ];
    }
}
