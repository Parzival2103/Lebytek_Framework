<?php

declare(strict_types=1);

use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Entities\Crud\CrudRelationDefinition;
use App\Domain\Entities\Crud\CrudTabDefinition;

function relations_definition(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo_pedidos', 'title' => 'Pedidos', 'table' => 'dom_demo_pedidos', 'primary_key' => 'id', 'permission_prefix' => 'demo_pedidos'],
        'relations' => [
            'cliente' => ['type' => 'belongsTo', 'table' => 'dom_demo_clientes', 'foreign_key' => 'cliente_id', 'value' => 'id', 'label' => 'nombre'],
            'items' => ['type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id'],
        ],
        'detail' => ['tabs' => [
            ['key' => 'general', 'label' => 'Datos generales', 'type' => 'fields', 'columns' => ['folio', 'total']],
            ['key' => 'items', 'label' => 'Items', 'type' => 'relation', 'relation' => 'items'],
            ['key' => 'historial', 'label' => 'Historial', 'type' => 'history'],
        ]],
    ]);
}

test('CrudResourceDefinition: relations are parsed into VOs accessible by name', function (): void {
    $def = relations_definition();
    assert_true($def->hasRelations());
    $cliente = $def->relation('cliente');
    assert_true($cliente instanceof CrudRelationDefinition);
    assert_true($cliente->isBelongsTo());
    assert_null($def->relation('inexistente'));
});

test('CrudResourceDefinition: detail tabs are parsed into VOs', function (): void {
    $def = relations_definition();
    assert_true($def->hasDetail());
    $tabs = $def->detailTabs();
    assert_same(3, count($tabs));
    assert_true($tabs[0] instanceof CrudTabDefinition);
    assert_true($tabs[0]->isFields());
    assert_true($tabs[1]->isRelation());
    assert_same('items', $tabs[1]->relation());
    assert_true($tabs[2]->isHistory());
});

test('CrudResourceDefinition: no detail block => empty tabs and hasDetail false', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'x', 'title' => 'X', 'table' => 'dom_x', 'primary_key' => 'id', 'permission_prefix' => 'x'],
    ]);
    assert_true(!$def->hasDetail());
    assert_same([], $def->detailTabs());
    assert_true(!$def->hasRelations());
});
