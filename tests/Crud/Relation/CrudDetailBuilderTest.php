<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudDetailBuilder;
use Lebytek\Framework\Application\Services\CrudRelationService;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Interfaces\BitacoraRepositoryInterface;

require_once dirname(__DIR__, 2) . '/fixtures/relation_repos.php';

if (!class_exists('FakeBitacoraRepo')) {
    class FakeBitacoraRepo implements BitacoraRepositoryInterface
    {
        public array $entries = [];
        public function registrar(?int $usuarioId, string $accion, string $tabla = '', ?int $registroId = null, string $detalle = '', string $ip = ''): void {}
        public function recientes(int $limit = 50): array { return []; }
        public function porRegistro(string $tabla, int $registroId, int $limit = 50): array { return $this->entries; }
    }
}

function detail_definition(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo_pedidos', 'title' => 'Pedidos', 'table' => 'dom_demo_pedidos', 'primary_key' => 'id', 'permission_prefix' => 'demo_pedidos'],
        'list' => ['columns' => [['name' => 'folio', 'label' => 'Folio'], ['name' => 'total', 'label' => 'Total', 'format' => 'money']]],
        'relations' => ['items' => ['type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id', 'columns' => [['name' => 'cantidad', 'label' => 'Cantidad']]]],
        'detail' => ['tabs' => [
            ['key' => 'general', 'label' => 'General', 'type' => 'fields', 'columns' => ['folio', 'total']],
            ['key' => 'items', 'label' => 'Items', 'type' => 'relation', 'relation' => 'items'],
            ['key' => 'historial', 'label' => 'Historial', 'type' => 'history'],
        ]],
    ]);
}

test('CrudDetailBuilder: sin detail genera una tab general desde list.columns', function (): void {
    $repo = new FakeRelationRepository();
    $bita = new FakeBitacoraRepo();
    $builder = new CrudDetailBuilder(new CrudRelationService($repo), $bita);

    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'x', 'title' => 'X', 'table' => 'dom_x', 'primary_key' => 'id', 'permission_prefix' => 'x'],
        'list' => ['columns' => [['name' => 'nombre', 'label' => 'Nombre']]],
    ]);
    $tabs = $builder->build($def, ['id' => 1, 'nombre' => 'Ana']);
    assert_same(1, count($tabs));
    assert_same('general', $tabs[0]['key']);
    assert_same('fields', $tabs[0]['type']);
});

test('CrudDetailBuilder: construye tabs fields/relation/history con datos', function (): void {
    $repo = new FakeRelationRepository();
    $repo->children['dom_demo_pedido_items'] = [['cantidad' => 2]];
    $bita = new FakeBitacoraRepo();
    $bita->entries = [['accion' => 'crud.create']];
    $builder = new CrudDetailBuilder(new CrudRelationService($repo), $bita);

    $tabs = $builder->build(detail_definition(), ['id' => 7, 'folio' => 'P-7', 'total' => 100]);
    assert_same(3, count($tabs));

    assert_same('fields', $tabs[0]['type']);
    assert_same(2, count($tabs[0]['columns']));   // folio + total

    assert_same('relation', $tabs[1]['type']);
    assert_same([['cantidad' => 2]], $tabs[1]['rows']);
    assert_same(7, $repo->childCalls[0]['parent']);

    assert_same('history', $tabs[2]['type']);
    assert_same([['accion' => 'crud.create']], $tabs[2]['entries']);
});
