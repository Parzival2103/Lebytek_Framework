<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Crud\Context\CrudListRowsContext;
use Lebytek\Framework\Application\Crud\Handlers\AbstractCrudHookHandler;
use Lebytek\Framework\Application\Services\CrudHookRunner;
use Lebytek\Framework\Application\Services\CrudTableBuilder;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

test('CrudTableBuilder: datetime formatea ISO-8601 para display', function (): void {
    $builder = new CrudTableBuilder();
    $definition = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'title' => 'Demo', 'table' => 'demo_items', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => [
            'columns' => [
                ['name' => 'created_at', 'label' => 'Creado', 'format' => 'datetime'],
            ],
        ],
        'form' => ['fields' => []],
    ]);

    $data = $builder->build(
        definition: $definition,
        rows: [['id' => 1, 'created_at' => '2026-07-15T14:30:00+00:00']],
        paginator: new \Lebytek\Framework\Kernel\Helpers\Paginator(1, 15, 1, '/admin/crud/demo'),
        total: 1,
        permissions: ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
    );

    assert_same('15/07/2026 14:30', $data['rows'][0]['_formatted']['created_at']);
});

test('CrudHookRunner: afterListRows mutates rows before formatting', function (): void {
    $handler = new class extends AbstractCrudHookHandler {
        public function afterListRows(CrudListRowsContext $ctx): void
        {
            $rows = $ctx->rows();
            foreach ($rows as &$row) {
                $row['wa_estado'] = 'Autorizada';
            }
            unset($row);
            $ctx->setRows($rows);
        }
    };

    $registry = new \Lebytek\Framework\Application\Services\CrudHandlerRegistry([
        'demo_enrich' => $handler::class,
    ]);
    $runner = new CrudHookRunner($registry);
    $definition = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'title' => 'Demo', 'table' => 'demo_items', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'hooks' => ['handler' => 'demo_enrich'],
        'list' => [
            'columns' => [
                ['name' => 'wa_estado', 'label' => 'Estado', 'virtual' => true],
            ],
        ],
        'form' => ['fields' => []],
    ]);

    $rows = [['id' => 1]];
    $ctx = new CrudListRowsContext('demo', 'demo_items', 'id', 1, '127.0.0.1', $rows);
    $runner->run($definition, 'afterListRows', $ctx);

    assert_same('Autorizada', $ctx->rows()[0]['wa_estado']);
});
