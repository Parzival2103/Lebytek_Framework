<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\OrganizationSettingsRepositoryInterface;
use Lebytek\Framework\Infrastructure\Invoicing\PdoInvoiceEventLogRepository;
use Lebytek\Framework\Infrastructure\Invoicing\PdoOrganizationSettingsRepository;

test('PDO invoicing repositories implement their domain ports', function (): void {
    assert_true(class_exists(PdoInvoiceEventLogRepository::class), 'PdoInvoiceEventLogRepository class exists');
    assert_true(class_exists(PdoOrganizationSettingsRepository::class), 'PdoOrganizationSettingsRepository class exists');

    $events = new ReflectionClass(PdoInvoiceEventLogRepository::class);
    assert_true($events->isFinal(), 'event log repository is final');
    assert_true($events->implementsInterface(InvoiceEventLogRepositoryInterface::class));
    $eventConstructor = $events->getConstructor();
    assert_true($eventConstructor === null || $eventConstructor->isPublic(), 'event log constructor remains usable');

    $organizations = new ReflectionClass(PdoOrganizationSettingsRepository::class);
    assert_true($organizations->isFinal(), 'organization settings repository is final');
    assert_true($organizations->implementsInterface(OrganizationSettingsRepositoryInterface::class));
    $organizationConstructor = $organizations->getConstructor();
    assert_true($organizationConstructor === null || $organizationConstructor->isPublic(), 'organization settings constructor remains usable');
});
