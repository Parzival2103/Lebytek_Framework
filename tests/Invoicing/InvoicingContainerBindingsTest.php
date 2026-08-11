<?php
declare(strict_types=1);

test('root container gates invoicing bindings and keeps issue source conditional', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/config/container.php');

    assert_true(str_contains($src, "Config::get('vertical.modules.invoicing'"), 'container must gate invoicing by vertical.modules.invoicing');
    assert_true(str_contains($src, \Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry::class), 'container must bind invoice registry');
    assert_true(str_contains($src, \Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface::class), 'container must bind invoice event log port');
    assert_true(str_contains($src, \Lebytek\Framework\Infrastructure\Invoicing\PdoInvoiceEventLogRepository::class), 'container must bind PDO invoice event log');
    assert_true(str_contains($src, \Lebytek\Framework\Domain\Invoicing\OrganizationSettingsRepositoryInterface::class), 'container must bind organization settings port');
    assert_true(str_contains($src, \Lebytek\Framework\Infrastructure\Invoicing\PdoOrganizationSettingsRepository::class), 'container must bind PDO organization settings');
    assert_true(str_contains($src, \Lebytek\Framework\Application\Invoicing\InvoiceDraftValidator::class), 'container must bind invoice validator');
    assert_true(str_contains($src, \Lebytek\Framework\Application\Invoicing\CancelIssuedInvoice::class), 'container must bind cancel use case');
    assert_true(str_contains($src, \Lebytek\Framework\Application\Invoicing\DownloadInvoiceDocument::class), 'container must bind download use case');
    assert_true(str_contains($src, \Lebytek\Framework\Application\Invoicing\SendInvoiceByEmail::class), 'container must bind send use case');
    assert_true(str_contains($src, \Lebytek\Framework\Application\Invoicing\ReconcileIssuedInvoice::class), 'container must bind reconcile use case');
    assert_true(str_contains($src, \Lebytek\Framework\Application\Invoicing\ApplyInvoiceProviderEvent::class), 'container must bind webhook apply use case');
    assert_true(str_contains($src, \Lebytek\Framework\Application\Invoicing\SyncOrganizationSettingsFromConfig::class), 'container must sync organization settings');
    assert_true(str_contains($src, '$container->has(\\Lebytek\\Framework\\Domain\\Invoicing\\InvoiceableSourceInterface::class)'), 'IssueInvoiceFromSource must be conditional on consumer source binding');
    assert_true(str_contains($src, 'InvoicingFactory::makeIssueInvoiceFromSource'), 'IssueInvoiceFromSource must use factory helper');
    assert_true(!str_contains($src, 'NullInvoiceableSource'), 'harness must not bind a null invoice source');
});

test('skeleton container ships same gated invoicing contract with issue source conditional', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/skeleton/config/container.php');

    assert_true(str_contains($src, "Config::get('vertical.modules.invoicing'"), 'skeleton must gate invoicing by vertical.modules.invoicing');
    assert_true(str_contains($src, \Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry::class), 'skeleton must bind invoice registry');
    assert_true(str_contains($src, \Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface::class), 'skeleton must bind invoice event log port');
    assert_true(str_contains($src, \Lebytek\Framework\Infrastructure\Invoicing\PdoInvoiceEventLogRepository::class), 'skeleton must bind PDO invoice event log');
    assert_true(str_contains($src, \Lebytek\Framework\Domain\Invoicing\OrganizationSettingsRepositoryInterface::class), 'skeleton must bind organization settings port');
    assert_true(str_contains($src, \Lebytek\Framework\Infrastructure\Invoicing\PdoOrganizationSettingsRepository::class), 'skeleton must bind PDO organization settings');
    assert_true(str_contains($src, \Lebytek\Framework\Application\Invoicing\InvoiceDraftValidator::class), 'skeleton must bind invoice validator');
    assert_true(str_contains($src, \Lebytek\Framework\Application\Invoicing\ReconcileIssuedInvoice::class), 'skeleton must bind reconcile use case');
    assert_true(str_contains($src, \Lebytek\Framework\Application\Invoicing\ApplyInvoiceProviderEvent::class), 'skeleton must bind webhook apply use case');
    assert_true(str_contains($src, '$container->has(\\Lebytek\\Framework\\Domain\\Invoicing\\InvoiceableSourceInterface::class)'), 'skeleton IssueInvoiceFromSource must be conditional');
    assert_true(str_contains($src, 'InvoicingFactory::makeIssueInvoiceFromSource'), 'skeleton IssueInvoiceFromSource must use factory helper');
    assert_true(!str_contains($src, 'NullInvoiceableSource'), 'skeleton must not ship a null invoice source');
});
