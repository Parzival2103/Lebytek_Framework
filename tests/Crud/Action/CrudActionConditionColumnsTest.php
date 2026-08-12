<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

test('actionConditionColumnNames unions visible_when and enabled_when keys', function (): void {
    if (!method_exists(CrudResourceDefinition::class, 'actionConditionColumnNames')) {
        throw new \RuntimeException('CrudResourceDefinition::actionConditionColumnNames missing (G14 list SELECT)');
    }
    $def = CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'demo', 'title' => 'Demo', 'table' => 'dom_demo',
            'primary_key' => 'id', 'permission_prefix' => 'demo',
        ],
        'list' => ['columns' => [['name' => 'nombre', 'label' => 'Nombre']]],
        'actions' => [
            'row' => [[
                'name' => 'go', 'type' => 'handler', 'handler' => 'h', 'permission' => 'demo.editar',
                'visible_when' => ['status' => 'pendiente'],
                'enabled_when' => ['bloqueado' => 0],
            ]],
            'bulk' => [[
                'name' => 'mass', 'type' => 'handler', 'handler' => 'h', 'permission' => 'demo.editar',
                'visible_when' => ['cola' => 'si'],
            ]],
        ],
    ]);
    $cols = $def->actionConditionColumnNames();
    sort($cols);
    assert_same(['bloqueado', 'cola', 'status'], $cols);
});
