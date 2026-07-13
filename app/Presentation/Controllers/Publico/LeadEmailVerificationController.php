<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Application\Marketing\VerificarLeadEmailUseCase;
use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;

final class LeadEmailVerificationController extends BaseController
{
    public function __construct(private readonly VerificarLeadEmailUseCase $useCase) {}

    public function show(Request $request): Response
    {
        $token = (string) $request->param('token', '');
        $result = $this->useCase->execute($token, null);

        return $this->renderVerification($result, $token);
    }

    public function submit(Request $request): Response
    {
        $this->verifyCsrf($request);

        $token = (string) $request->param('token', '');
        $codigo = trim((string) $request->input('codigo', ''));
        $result = $this->useCase->execute($token, $codigo);

        return $this->renderVerification($result, $token);
    }

    /** @param array{status: string, lead?: array<string, mixed>|null} $result */
    private function renderVerification(array $result, string $token): Response
    {
        return $this->view('publico/verificar_demo', [
            'pageTitle'     => 'Verificar correo — Lebytek',
            'empresaNombre' => 'Lebytek',
            'status'        => (string) $result['status'],
            'token'         => $token,
            'lead'          => $result['lead'] ?? null,
        ], 'publico/layout');
    }
}
