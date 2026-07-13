<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\Contracts\LeadTeamAlertNotifierInterface;
use App\Domain\Marketing\LeadEmailVerification;
use Lebytek\Framework\Kernel\Logging\AppLogger;

final class VerificarLeadEmailUseCase
{
    public function __construct(
        private readonly LeadRepositoryInterface $repo,
        private readonly LeadTeamAlertNotifierInterface $notifier,
    ) {}

    /** @return array{status: string, lead?: array<string, mixed>|null} */
    public function execute(string $token, ?string $submittedCode = null): array
    {
        $lead = $this->repo->findByEmailVerifyToken($token);
        if ($lead === null) {
            return ['status' => 'invalid'];
        }

        if (!empty($lead['email_verified_at'])) {
            return ['status' => 'already_verified', 'lead' => $lead];
        }

        $expiresAt = (string) ($lead['email_verify_expires_at'] ?? '');
        if ($expiresAt !== '' && strtotime($expiresAt) < time()) {
            return ['status' => 'expired', 'lead' => $lead];
        }

        $attempts = (int) ($lead['email_verify_attempts'] ?? 0);
        if ($attempts >= LeadEmailVerification::MAX_ATTEMPTS) {
            return ['status' => 'locked', 'lead' => $lead];
        }

        if ($submittedCode === null) {
            return ['status' => 'form', 'lead' => $lead];
        }

        $hash = (string) ($lead['email_verify_code_hash'] ?? '');
        if ($hash === '' || !LeadEmailVerification::codeMatches($submittedCode, $hash)) {
            $this->repo->incrementEmailVerifyAttempts((int) $lead['id']);

            return ['status' => 'wrong_code', 'lead' => $lead];
        }

        $this->repo->markEmailVerified((int) $lead['id']);
        $verified = $this->repo->findById((int) $lead['id']) ?? $lead;

        try {
            $this->notifier->notifyLeadVerified($verified);
        } catch (\Throwable $e) {
            AppLogger::error('Lead email verify: notifier failed', [
                'lead_id' => $verified['id'] ?? null,
                'error'   => $e->getMessage(),
            ]);
        }

        return ['status' => 'ok', 'lead' => $verified];
    }
}
