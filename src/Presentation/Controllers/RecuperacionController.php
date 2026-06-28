<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Controllers;

use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Application\UseCases\Auth\RestablecerPasswordUseCase;
use Lebytek\Framework\Application\UseCases\Auth\SolicitarRecuperacionUseCase;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Helpers\LebytekUiConfig;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;

/*
|--------------------------------------------------------------------------
| RecuperacionController — Recuperación de contraseña por token
|--------------------------------------------------------------------------
| /recuperar responde siempre igual exista o no el correo (spec §8.2).
| /restablecer no revela la validez del token en el GET (spec §8.4).
*/

final class RecuperacionController extends BaseController
{
    public function __construct(
        private readonly ConfiguracionService         $configService,
        private readonly SolicitarRecuperacionUseCase $solicitarUseCase,
        private readonly RestablecerPasswordUseCase   $restablecerUseCase
    ) {
    }

    public function mostrar(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }
        return $this->view('auth/recuperar', $this->theme(), '');
    }

    public function solicitar(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        try {
            $this->solicitarUseCase->execute(trim((string) $request->input('email', '')));
        } catch (ValidationException) {
            // Fallo de envío: misma respuesta genérica (anti-enumeración).
        }

        return $this->view('auth/recuperar_enviado', $this->theme(), '');
    }

    public function mostrarRestablecer(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        return $this->view('auth/restablecer', $this->theme() + [
            'token' => (string) $request->input('token', ''),
        ], '');
    }

    public function restablecer(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        $token = (string) $request->input('token', '');

        try {
            $this->restablecerUseCase->execute(
                $token,
                (string) $request->input('password', ''),
                (string) $request->input('password_confirmacion', '')
            );

            Session::flash('success', 'Tu contraseña fue actualizada. Inicia sesión con la nueva contraseña.');
            return $this->redirect('/login');

        } catch (ValidationException $e) {
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());
            return $this->redirect('/restablecer?token=' . rawurlencode($token));
        }
    }

    private function theme(): array
    {
        try {
            $all = $this->configService->all();
            return LebytekUiConfig::resolve(is_array($all) ? $all : []);
        } catch (\Throwable) {
            return LebytekUiConfig::resolve([]);
        }
    }
}
