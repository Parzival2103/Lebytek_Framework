<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\InvoiceDraftValidator;
use Lebytek\Framework\Application\Invoicing\InvoiceProviderRegistry;
use Lebytek\Framework\Application\Invoicing\InvoicingFactory;
use Lebytek\Framework\Application\Invoicing\IssueInvoiceFromSource;
use Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface;
use Lebytek\Framework\Infrastructure\Invoicing\PdoInvoiceEventLogRepository;

test('InvoicingFactory issue helper wires registry events validator and source', function (): void {
    InvoicingFactory::resetCached();
    $source = new class implements InvoiceableSourceInterface {
        public function findDraft(string $sourceRef): ?\Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft
        {
            return null;
        }
    };

    $useCase = InvoicingFactory::makeIssueInvoiceFromSource($source);

    assert_true($useCase instanceof IssueInvoiceFromSource);
    assert_same($source, task13_read_issue_dependency($useCase, 'source'));
    assert_true(task13_read_issue_dependency($useCase, 'events') instanceof PdoInvoiceEventLogRepository);
    assert_true(task13_read_issue_dependency($useCase, 'registry') instanceof InvoiceProviderRegistry);
    assert_true(task13_read_issue_dependency($useCase, 'validator') instanceof InvoiceDraftValidator);
});

function task13_read_issue_dependency(IssueInvoiceFromSource $useCase, string $property): mixed
{
    $ref = new ReflectionProperty($useCase, $property);
    $ref->setAccessible(true);

    return $ref->getValue($useCase);
}
