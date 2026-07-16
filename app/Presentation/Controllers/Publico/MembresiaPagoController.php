<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Application\Marketing\RecoverMembershipPaymentService;
use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;

final class MembresiaPagoController extends BaseController
{
    public function __construct(
        private readonly RecoverMembershipPaymentService $recover,
    ) {}

    public function reintentarPago(Request $request): Response
    {
        $token = trim((string) $request->query('t', ''));
        if ($token === '') {
            Session::flash('error', 'Enlace de pago inválido.');
            return $this->redirect('/#paquetes');
        }

        $membresia = $this->recover->findByRetryToken($token);
        if ($membresia === null || ($membresia['status'] ?? '') !== 'past_due') {
            Session::flash('error', 'Este enlace de pago ya no está disponible.');
            return $this->redirect('/#paquetes');
        }

        $graceEnds = $membresia['grace_ends_at'] ?? null;
        if ($graceEnds !== null && strtotime((string) $graceEnds) <= time()) {
            Session::flash('error', 'El plazo de gracia expiró. Revisa tu correo para reactivar.');
            return $this->redirect('/#paquetes');
        }

        try {
            $url = $this->recover->checkoutUrlForMembresia(
                $membresia,
                '/membresia/pago/exito',
                '/membresia/pago/cancelado',
            );
        } catch (\Throwable) {
            Session::flash('error', 'No pudimos iniciar el pago. Contacta a soporte.');
            return $this->redirect('/#paquetes');
        }

        return $this->redirect($url);
    }

    public function reactivar(Request $request): Response
    {
        $token = trim((string) $request->query('t', ''));
        if ($token === '') {
            Session::flash('error', 'Enlace de reactivación inválido.');
            return $this->redirect('/#paquetes');
        }

        $membresia = $this->recover->findByReactivationToken($token);
        if ($membresia === null || ($membresia['status'] ?? '') !== 'cancelled') {
            Session::flash('error', 'Este enlace de reactivación ya no está disponible.');
            return $this->redirect('/#paquetes');
        }

        try {
            $url = $this->recover->checkoutUrlForMembresia(
                $membresia,
                '/membresia/reactivar/exito',
                '/membresia/reactivar/cancelado',
            );
        } catch (\Throwable) {
            Session::flash('error', 'No pudimos iniciar la reactivación. Contacta a soporte.');
            return $this->redirect('/#paquetes');
        }

        return $this->redirect($url);
    }

    public function pagoExito(): Response
    {
        return $this->view('publico/compra_pago_exito', [
            'pageTitle' => 'Pago recibido — Lebytek',
            'publicId' => '',
            'order' => null,
        ], 'publico/layout');
    }

    public function pagoCancelado(): Response
    {
        return $this->view('publico/compra_pago_cancelado', [
            'pageTitle' => 'Pago cancelado — Lebytek',
        ], 'publico/layout');
    }
}
