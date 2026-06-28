<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

interface IntegrationAccountRepositoryInterface
{
    public function findDefault(string $provider): ?IntegrationAccount;

    public function findById(int $id): ?IntegrationAccount;

    public function findByLead(int $leadId, string $provider): ?IntegrationAccount;

    public function save(IntegrationAccount $account): int;

    public function markDefault(int $id, string $provider): void;
}
