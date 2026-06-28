<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Pdf\PdfTemplateRegistry;
use Lebytek\Framework\Application\Reporte\GenerarDocumentoUseCase;
use Lebytek\Framework\Application\Reporte\ReporteConfigLoader;
use Lebytek\Framework\Application\Reporte\ReporteConfigValidator;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Reporte\ReporteRecordSourceInterface;

final class FakeReporteRecordSource implements ReporteRecordSourceInterface
{
    /** @param array{record:array,relations:array}|null $result */
    public function __construct(private readonly ?array $result, public array $lastCall = []) {}

    public function findRecord(CrudResourceDefinition $definition, int $id, ?int $userId, ?callable $can, array $relationNames): ?array
    {
        $this->lastCall = ['id' => $id, 'userId' => $userId, 'relationNames' => $relationNames];
        return $this->result;
    }
}

function gd_useCase(ReporteRecordSourceInterface $source): GenerarDocumentoUseCase
{
    return new GenerarDocumentoUseCase(
        new ReporteConfigLoader(new ReporteConfigValidator()),
        $source,
        new PdfTemplateRegistry(require SKELETON_PATH . '/config/pdf_templates.php')
    );
}

test('buildPayload arma el payload con record y relaciones declaradas', function (): void {
    $source = new FakeReporteRecordSource([
        'record'    => ['folio' => 'PED-1', 'total' => '289.40', 'cliente_id' => 7],
        'relations' => ['cliente' => 'Juan Pérez', 'items' => [['descripcion' => 'A']]],
    ]);
    $payload = gd_useCase($source)->buildPayload('pedidos', 5, 'ticket_compra', 3, fn(string $s): bool => true);

    assert_same('PED-1', $payload['record']['folio']);
    assert_same('Juan Pérez', $payload['relations']['cliente']);
    assert_same(['cliente', 'items'], $source->lastCall['relationNames']);
});

test('buildPayload devuelve null cuando el registro está fuera de scope', function (): void {
    $payload = gd_useCase(new FakeReporteRecordSource(null))
        ->buildPayload('pedidos', 99, 'ticket_compra', 3, fn(string $s): bool => true);
    assert_null($payload);
});

test('buildPayload rechaza una plantilla no declarada por la fuente para registro', function (): void {
    $source = new FakeReporteRecordSource(['record' => [], 'relations' => []]);
    assert_throws(ValidationException::class, fn() =>
        gd_useCase($source)->buildPayload('clientes', 1, 'ticket_compra', 3, fn(string $s): bool => true));
});

test('buildPayload acepta la fuente clientes con la plantilla contrato', function (): void {
    $source = new FakeReporteRecordSource(['record' => ['nombre' => 'X'], 'relations' => []]);
    $payload = gd_useCase($source)->buildPayload('clientes', 1, 'contrato', 3, fn(string $s): bool => true);
    assert_same('X', $payload['record']['nombre']);
});
