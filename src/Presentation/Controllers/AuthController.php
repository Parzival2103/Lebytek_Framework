<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Controllers;

use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Application\UseCases\Auth\LoginUseCase;
use Lebytek\Framework\Application\UseCases\Auth\LogoutUseCase;
use Lebytek\Framework\Application\DTO\Auth\LoginDTO;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Kernel\Helpers\LebytekUiConfig;
use Lebytek\Framework\Domain\Exceptions\AuthException;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

final class AuthController extends BaseController
{
    public function __construct(
        private readonly LoginUseCase         $loginUseCase,
        private readonly LogoutUseCase        $logoutUseCase,
        private readonly ConfiguracionService $configService
    ) {}

    public function showLogin(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        try {
            $all = $this->configService->all();
            $theme = LebytekUiConfig::resolve(is_array($all) ? $all : []);
        } catch (\Throwable) {
            $theme = LebytekUiConfig::resolve([]);
        }

        return $this->view('auth/login', array_merge($theme, [
            'registroHabilitado' => (bool) Config::get('auth.registro.habilitado', false),
        ]), '');
    }

    public function login(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        $datosLogin = $request->only(['email', 'password', 'recordar']);

        try {
            $this->verifyCsrf($request);

            $dto = new LoginDTO(
                email:    trim((string) ($datosLogin['email']    ?? '')),
                password: (string) ($datosLogin['password'] ?? ''),
                recordar: !empty($datosLogin['recordar']),
                clientIp: $request->ip()
            );

            $this->loginUseCase->execute($dto);

            return $this->redirect('/admin/dashboard');

        } catch (ValidationException $e) {
            Session::flashInput($datosLogin);
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());
            return $this->redirect('/login');

        } catch (AuthException $e) {
            Session::flashInput($datosLogin);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/login');
        }
    }

    public function logout(Request $request): Response
    {
        $this->logoutUseCase->execute();
        Session::flash('success', 'Sesión cerrada correctamente.');
        return $this->redirect('/login');
    }
}
