<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Application\Marketing\CapturarLeadUseCase;
use App\Domain\Marketing\ValueObjects\LeadDraft;

final class LeadController extends BaseController
{
    public function __construct(private readonly CapturarLeadUseCase $capturarLead) {}

    public function capturar(Request $request): Response
    {
        $this->verifyCsrf($request);

        $nombre = trim((string) $request->input('nombre', ''));
        $email  = trim((string) $request->input('email', ''));

        if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Revisa tu nombre y correo.');
            return $this->redirect('/#demo');
        }

        $draft = new LeadDraft(
            $nombre,
            $email,
            trim((string) $request->input('telefono', '')) ?: null,
            trim((string) $request->input('mensaje', '')) ?: null,
            [
                'utm_source'   => (string) $request->input('utm_source', ''),
                'utm_medium'   => (string) $request->input('utm_medium', ''),
                'utm_campaign' => (string) $request->input('utm_campaign', ''),
            ]
        );

        $res = $this->capturarLead->ejecutar($draft);
        Session::flash(
            $res->ok() ? 'success' : 'error',
            $res->ok() ? '¡Gracias! Te contactaremos pronto.' : 'No pudimos registrar tu solicitud.'
        );
        return $this->redirect('/#demo');
    }
}
