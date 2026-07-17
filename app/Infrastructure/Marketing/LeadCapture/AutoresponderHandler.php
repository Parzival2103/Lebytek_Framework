<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing\LeadCapture;

use App\Application\Marketing\MarketingMailRenderer;
use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;
use Lebytek\Framework\Kernel\EnvLoader;

final class AutoresponderHandler implements LeadCaptureHandlerInterface
{
    public function __construct(private readonly MarketingMailRenderer $mailRenderer) {}

    public function handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult
    {
        $base = rtrim((string) EnvLoader::get('APP_URL', ''), '/');
        $token = (string) ($resultadoPrevio->emailVerifyToken() ?? '');
        $code  = (string) ($resultadoPrevio->emailVerifyCode() ?? '');
        $verifyUrl = $token !== '' ? $base . '/verificar-demo/' . rawurlencode($token) : $base;

        $this->mailRenderer->send('lead_welcome', $draft->email(), $draft->nombre(), [
            'nombre' => $draft->nombre(),
            'landing_url' => $base,
            'codigo' => $code,
            'verify_url' => $verifyUrl,
        ]);

        return $resultadoPrevio;
    }
}
