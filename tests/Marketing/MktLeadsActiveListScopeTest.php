<?php

declare(strict_types=1);

use App\Application\Marketing\MktLeadsActiveListScope;
use Lebytek\Framework\Application\Crud\Context\CrudListContext;

test('MktLeadsActiveListScope excluye pendiente y demo_baja por defecto', function (): void {
    $scope = new MktLeadsActiveListScope();
    $ctx = new CrudListContext('mkt_leads', 'dom_mkt_leads', 'id', 1, '', []);
    $scope->apply($ctx);

    assert_same([
        ['column' => 'estado', 'op' => '!=', 'value' => 'pendiente'],
        ['column' => 'estado', 'op' => '!=', 'value' => 'demo_baja'],
    ], $ctx->conditions());
});

test('MktLeadsActiveListScope no excluye si el usuario filtra f_estado=pendiente', function (): void {
    $scope = new MktLeadsActiveListScope();
    $ctx = new CrudListContext('mkt_leads', 'dom_mkt_leads', 'id', 1, '', ['f_estado' => 'pendiente']);
    $scope->apply($ctx);

    assert_same([], $ctx->conditions());
});

test('MktLeadsActiveListScope sigue excluyendo cuando filtra otro estado', function (): void {
    $scope = new MktLeadsActiveListScope();
    $ctx = new CrudListContext('mkt_leads', 'dom_mkt_leads', 'id', 1, '', ['f_estado' => 'demo_enviada']);
    $scope->apply($ctx);

    assert_same([
        ['column' => 'estado', 'op' => '!=', 'value' => 'pendiente'],
        ['column' => 'estado', 'op' => '!=', 'value' => 'demo_baja'],
    ], $ctx->conditions());
});
