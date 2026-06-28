<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Controllers;

use Lebytek\Framework\Application\DTO\Auth\RegistroDTO;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Application\UseCases\Auth\RegistrarUsuarioUseCase;
use Lebytek\Framework\Application\UseCases\Auth\ReenviarVerificacionUseCase;
use Lebytek\Framework\Application\UseCases\Auth\VerificarCorreoUseCase;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Exceptions\HttpException;
use Lebytek\Framework\Kernel\Helpers\LebytekUiConfig;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;

/*
|--------------------------------------------------------------------------
| RegistroController — Registro público con verificación de correo
|--------------------------------------------------------------------------
| CSRF en POSTs vía CsrfMiddleware (routes/web.php). El formulario da 404
| cuando registro.habilitado=false; verificar/reenviar siguen operativos
| para cuentas pendientes.
*/

final class RegistroController extends BaseController
{
    public function __construct(
        private readonly ConfiguracionService        $configService,
        private readonly RegistrarUsuarioUseCase     $registrarUseCase,
        private readonly VerificarCorreoUseCase      $verificarUseCase,
        private readonly ReenviarVerificacionUseCase $reenviarUseCase,
        private readonly bool                        $registroHabilitado
    ) {
    }

    public function mostrar(Request $request): Response
    {
        $this->abortarSiDeshabilitado();
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }
        return $this->view('auth/registro', $this->theme(), '');
    }

    public function registrar(Request $request): Response
    {
        $this->abortarSiDeshabilitado();
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        $datos = $request->only(['nombre', 'apellido', 'email', 'password', 'password_confirmacion']);

        try {
            $this->registrarUseCase->execute(new RegistroDTO(
                nombre:               trim((string) ($datos['nombre'] ?? '')),
                apellido:             trim((string) ($datos['apellido'] ?? '')),
                email:                trim((string) ($datos['email'] ?? '')),
                password:             (string) ($datos['password'] ?? ''),
                passwordConfirmacion: (string) ($datos['password_confirmacion'] ?? '')
            ));

            return $this->view('auth/registro_enviado', $this->theme() + [
                'email' => trim((string) ($datos['email'] ?? '')),
            ], '');

        } catch (ValidationException $e) {
            Session::flashInput(array_diff_key($datos, ['password' => 0, 'password_confirmacion' => 0]));
            Session::flash('errors', $e->getErrors());
            Session::flash('error', $e->getMessage());
            return $this->redirect('/registro');
        }
    }

    public function verificar(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        try {
            $this->verificarUseCase->execute((string) $request->input('token', ''));
            Session::flash('success', 'Tu correo fue verificado. Ya puedes iniciar sesión.');
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirect('/login');
    }

    public function reenviar(Request $request): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect('/admin/dashboard');
        }

        $email = trim((string) $request->input('email', ''));

        try {
            $this->reenviarUseCase->execute($email);
        } catch (ValidationException) {
            // Fallo de envío: misma respuesta genérica (anti-enumeración).
        }

        Session::flash('success', 'Si tu cuenta está pendiente de verificación, reenviamos el correo.');
        return $this->view('auth/registro_enviado', $this->theme() + ['email' => $email], '');
    }

    private function abortarSiDeshabilitado(): void
    {
        if (!$this->registroHabilitado) {
            throw new HttpException('Página no encontrada.', 404);
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
