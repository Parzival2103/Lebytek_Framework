<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Application\UseCases\Auth\LoginUseCase;
use App\Application\UseCases\Auth\LogoutUseCase;
use App\Application\DTO\Auth\LoginDTO;
use App\Application\Services\ConfiguracionService;
use App\Kernel\Helpers\LebytekUiConfig;
use App\Domain\Exceptions\AuthException;
use App\Domain\Exceptions\ValidationException;

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

        return $this->view('auth/login', $theme, '');
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
                recordar: !empty($datosLogin['recordar'])
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
