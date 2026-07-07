<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use App\Domain\Marketing\ValueObjects\MagicLinkToken;
use Lebytek\Framework\Kernel\Database\Connection;

final class PortalClienteController extends BaseController
{
    public function __construct(private readonly ConfiguracionService $configuracionService) {}

    public function entrar(Request $request): Response
    {
        $token = (string) $request->input('token', '');

        if (!MagicLinkToken::esFormatoValido($token)) {
            return $this->view('publico/portal', $this->vm('Enlace inválido o expirado.', null), 'publico/layout');
        }

        $pdo  = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT id, lead_id, estado, expira_en FROM dom_mkt_provisiones
             WHERE access_token = :t AND deleted = 0 LIMIT 1'
        );
        $stmt->execute(['t' => $token]);
        $prov = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($prov === null || ($prov['expira_en'] !== null && strtotime((string) $prov['expira_en']) < time())) {
            return $this->view('publico/portal', $this->vm('Enlace inválido o expirado.', null), 'publico/layout');
        }

        // Abre sesión cliente (NO toca la sesión admin/auth_user).
        Session::set('portal_cliente', ['provision_id' => (int) $prov['id'], 'lead_id' => $prov['lead_id']]);

        return $this->view('publico/portal', $this->vm('Bienvenido a tu portal.', $prov), 'publico/layout');
    }

    /** @param array<string,mixed>|null $prov */
    private function vm(string $mensaje, ?array $prov): array
    {
        return [
            'empresaNombre' => $this->configuracionService->empresaNombre(),
            'empresaLogo'   => $this->configuracionService->empresaLogo(),
            'mensaje'       => $mensaje,
            'provision'     => $prov,
        ];
    }
}
