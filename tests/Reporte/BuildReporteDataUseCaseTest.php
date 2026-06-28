<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Reporte\BuildReporteDataUseCase;
use Lebytek\Framework\Application\Reporte\PeriodoResolver;
use Lebytek\Framework\Application\Reporte\ReporteAggregator;
use Lebytek\Framework\Application\Reporte\ReporteConfigLoader;
use Lebytek\Framework\Application\Reporte\ReporteConfigValidator;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Reporte\ReporteDataSourceInterface;
use Lebytek\Framework\Domain\Reporte\ReporteGuardado;

final class FakeReporteDataSource implements ReporteDataSourceInterface
{
    /** @var list<array<string,mixed>> */
    public array $rows;
    public array $lastCall = [];

    public function __construct(array $rows) { $this->rows = $rows; }

    public function rows(CrudResourceDefinition $definition, string $dateColumn, string $from, string $to, ?int $userId, ?callable $can, array $filters): array
    {
        $this->lastCall = compact('dateColumn', 'from', 'to', 'userId', 'filters');
        return $this->rows;
    }
}

function brd_useCase(ReporteDataSourceInterface $source): BuildReporteDataUseCase
{
    return new BuildReporteDataUseCase(
        new ReporteConfigLoader(new ReporteConfigValidator()),
        $source,
        new PeriodoResolver(),
        new ReporteAggregator()
    );
}

function brd_reporte(): ReporteGuardado
{
    return ReporteGuardado::fromRow([
        'id' => 1, 'nombre' => 'Citas por estado', 'fuente_key' => 'citas', 'modo' => 'coleccion',
        'columnas' => json_encode([['name' => 'estado', 'label' => 'Estado', 'type' => 'text']]),
        'tratamientos' => json_encode(['group_by' => ['estado'], 'aggregations' => [['op' => 'count', 'column' => '']], 'order' => ['by' => 'estado', 'dir' => 'asc']]),
        'filtros' => json_encode([]),
        'periodo' => json_encode(['preset' => 'todo']),
        'opciones' => json_encode(['titulo' => 'Citas por estado', 'orientacion' => 'portrait']),
        'template_key' => 'tabla_estadistica', 'compartido' => 1, 'created_by' => 3,
    ]);
}

test('construye el payload agregando por estado', function (): void {
    $source = new FakeReporteDataSource([
        ['estado' => 'pagado'], ['estado' => 'pagado'], ['estado' => 'pendiente'],
    ]);
    $payload = brd_useCase($source)->build(brd_reporte(), 3, fn(string $s): bool => true);

    assert_same('Citas por estado', $payload['title']);
    assert_same('Todo', $payload['period']);
    assert_same(2, count($payload['rows']));
    assert_same(3, $payload['totals'][0]['value']);
    assert_same('fecha_inicio', $source->lastCall['dateColumn']);
});

test('descarta columnas que la fuente ya no expone', function (): void {
    $reporte = ReporteGuardado::fromRow([
        'id' => 1, 'nombre' => 'X', 'fuente_key' => 'citas', 'modo' => 'coleccion',
        'columnas' => json_encode([
            ['name' => 'estado', 'label' => 'Estado', 'type' => 'text'],
            ['name' => 'columna_retirada', 'label' => 'Vieja', 'type' => 'text'],
        ]),
        'tratamientos' => json_encode([]),
        'filtros' => json_encode([]),
        'periodo' => json_encode(['preset' => 'todo']),
        'opciones' => json_encode([]),
        'template_key' => 'tabla_estadistica', 'compartido' => 0, 'created_by' => 3,
    ]);
    $source = new FakeReporteDataSource([['estado' => 'pagado', 'columna_retirada' => 'x']]);
    $payload = brd_useCase($source)->build($reporte, 3, fn(string $s): bool => true);

    $names = array_map(static fn($c) => $c['name'], $payload['columns']);
    assert_true(in_array('estado', $names, true));
    assert_true(!in_array('columna_retirada', $names, true), 'la columna retirada no debe aparecer');
});
