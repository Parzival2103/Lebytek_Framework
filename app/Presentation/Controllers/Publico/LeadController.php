<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Logging\AppLogger;
use Lebytek\Framework\Kernel\Security\Session;
use App\Application\Marketing\CapturarLeadUseCase;
use App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface;
use App\Domain\Marketing\LandingVariantRegistry;
use App\Domain\Marketing\ValueObjects\LeadDraft;

/**
 * Atribución de leads a variante/visitante (Task 7, Anti-deuda §N):
 *
 * - `lb_preview` presente → **no** atribuye (`landing_variant = NULL`) y
 *   **no** emite el evento `lead_submit` de score; los leads de preview no
 *   deben contaminar la conversión de las variantes activas.
 * - Orden de resolución de variante: cookie sticky `lb_var` → `landing_variant`
 *   del body. Solo acepta slugs con formato válido y conocidos por el registry.
 * - Orden de resolución de visitante: cookie `lb_vid` → `visitor_id` del body.
 *   Solo acepta UUID v4.
 */
final class LeadController extends BaseController
{
    private const VARIANT_SLUG_PATTERN = '/^[a-z0-9_-]{1,40}$/';

    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly CapturarLeadUseCase $capturarLead,
        private readonly LandingVariantRegistry $variantRegistry,
        private readonly LandingMetricsRepositoryInterface $metrics,
    ) {}

    public function capturar(Request $request): Response
    {
        $this->verifyCsrf($request);

        $nombre = trim((string) $request->input('nombre', ''));
        $email  = trim((string) $request->input('email', ''));

        if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Revisa tu nombre y correo.');
            return $this->redirect('/#demo');
        }

        $landingVariant = $this->resolveLandingVariant($request);
        $visitorId = $this->resolveVisitorId($request);

        $draft = new LeadDraft(
            $nombre,
            $email,
            trim((string) $request->input('telefono', '')) ?: null,
            trim((string) $request->input('mensaje', '')) ?: null,
            [
                'utm_source'   => (string) $request->input('utm_source', ''),
                'utm_medium'   => (string) $request->input('utm_medium', ''),
                'utm_campaign' => (string) $request->input('utm_campaign', ''),
            ],
            $landingVariant,
            $visitorId,
        );

        $res = $this->capturarLead->ejecutar($draft);

        if ($res->ok() && $landingVariant !== null && $visitorId !== null) {
            try {
                $this->metrics->insertLeadSubmitEvent($visitorId, $landingVariant);
            } catch (\Throwable $e) {
                // Attribution must never break capture UX (redirect + flash).
                if (class_exists(AppLogger::class)) {
                    AppLogger::error('Lead attribution: insertLeadSubmitEvent failed', [
                        'visitor_id' => $visitorId,
                        'landing_variant' => $landingVariant,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Session::flash(
            $res->ok() ? 'success' : 'error',
            $res->ok() ? '¡Gracias! Te contactaremos pronto.' : 'No pudimos registrar tu solicitud.'
        );
        return $this->redirect('/#demo');
    }

    /**
     * Anti-deuda §N fallback B — `lb_preview` presente anula toda atribución,
     * sin importar lo que traiga el hidden field `landing_variant` del form
     * (puede llevar la variante forzada de preview; se ignora a propósito).
     */
    private function resolveLandingVariant(Request $request): ?string
    {
        if (trim((string) $request->cookie('lb_preview', '')) !== '') {
            return null;
        }

        $cookieVar = strtolower(trim((string) $request->cookie('lb_var', '')));
        if ($this->isValidSlug($cookieVar)) {
            return $cookieVar;
        }

        $bodyVar = strtolower(trim((string) $request->input('landing_variant', '')));
        if ($this->isValidSlug($bodyVar)) {
            return $bodyVar;
        }

        return null;
    }

    private function isValidSlug(string $slug): bool
    {
        return $slug !== ''
            && preg_match(self::VARIANT_SLUG_PATTERN, $slug) === 1
            && $this->variantRegistry->get($slug) !== null;
    }

    private function resolveVisitorId(Request $request): ?string
    {
        $cookieVid = trim((string) $request->cookie('lb_vid', ''));
        if ($this->isUuidV4($cookieVid)) {
            return $cookieVid;
        }

        $bodyVid = trim((string) $request->input('visitor_id', ''));
        if ($this->isUuidV4($bodyVid)) {
            return $bodyVid;
        }

        return null;
    }

    private function isUuidV4(string $value): bool
    {
        return $value !== '' && preg_match(self::UUID_V4_PATTERN, $value) === 1;
    }
}
