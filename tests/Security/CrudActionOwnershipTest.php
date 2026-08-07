<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Crud\Context\CrudListContext;
use Lebytek\Framework\Application\Services\CrudHandlerRegistry;
use Lebytek\Framework\Application\Services\CrudScopeResolver;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Interfaces\CrudListScopeInterface;

/**
 * H2 (IDOR) — la regla de propiedad usada por show/edit/update/delete y por las
 * acciones de fila/masivas vive en un único guard puro: CrudScopeResolver::assertOwnedBy().
 * Cubre owner built-in y `list.scope_handler` custom (C1).
 * Aquí se prueba ese guard con una definición real (vía fromArray) y un closure
 * $can, sin tocar DB ni doblar clases final. CrudActionService la consume
 * idénticamente desde run()/runBulk().
 */

if (!class_exists('OwnershipFixtureCustomScope')) {
    class OwnershipFixtureCustomScope implements CrudListScopeInterface
    {
        public function apply(CrudListContext $ctx): void
        {
            $ctx->addCondition('created_by', '=', 99);
        }
    }
}

function ownership_def_handler(string $handlerKey): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'eventos',
            'title' => 'Eventos',
            'table' => 'dom_eventos',
            'primary_key' => 'id',
            'permission_prefix' => 'eventos',
        ],
        'list' => ['scope_handler' => $handlerKey],
    ]);
}
function ownership_def(?array $scope): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'eventos',
            'title' => 'Eventos',
            'table' => 'dom_eventos',
            'primary_key' => 'id',
            'permission_prefix' => 'eventos',
        ],
        'list' => $scope === null ? [] : ['scope' => $scope],
    ]);
}

$deny = static fn(string $slug): bool => false;

// ── Caracterización: recurso SIN owner scope NO bloquea (lógica sin cambios) ──
test('assertOwnedBy no bloquea cuando el recurso no declara owner scope', function () use ($deny): void {
    $r = new CrudScopeResolver();
    $r->assertOwnedBy(ownership_def(null), ['id' => 7, 'created_by' => 999], 42, $deny);
    assert_true(true, 'sin scope: no lanza');
});

// ── Caracterización: dueño legítimo pasa ─────────────────────────────────────
test('assertOwnedBy permite al dueño del registro', function () use ($deny): void {
    $r = new CrudScopeResolver();
    $r->assertOwnedBy(
        ownership_def(['type' => 'owner', 'column' => 'created_by']),
        ['id' => 7, 'created_by' => 42],
        42,
        $deny
    );
    assert_true(true, 'dueño: no lanza');
});

// ── Caracterización: admin con bypass pasa ───────────────────────────────────
test('assertOwnedBy permite a quien tiene permiso de bypass aunque no sea dueño', function (): void {
    $r = new CrudScopeResolver();
    $can = static fn(string $slug): bool => $slug === 'eventos.ver_todos';
    $r->assertOwnedBy(
        ownership_def(['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']),
        ['id' => 7, 'created_by' => 999],
        42,
        $can
    );
    assert_true(true, 'bypass: no lanza');
});

// ── Seguridad: usuario ajeno es bloqueado (IDOR) ─────────────────────────────
test('assertOwnedBy bloquea a un usuario ajeno (IDOR)', function () use ($deny): void {
    $r = new CrudScopeResolver();
    assert_throws(ValidationException::class, function () use ($r, $deny): void {
        $r->assertOwnedBy(
            ownership_def(['type' => 'owner', 'column' => 'created_by']),
            ['id' => 7, 'created_by' => 999],
            42,
            $deny
        );
    });
});

// ── Seguridad: conserva el mensaje que no revela existencia ──────────────────
test('assertOwnedBy conserva el mensaje "El registro solicitado no existe."', function () use ($deny): void {
    $r = new CrudScopeResolver();
    $msg = null;
    try {
        $r->assertOwnedBy(
            ownership_def(['type' => 'owner', 'column' => 'created_by']),
            ['id' => 7, 'created_by' => 999],
            42,
            $deny
        );
    } catch (ValidationException $e) {
        $msg = $e->getMessage();
    }
    assert_same('El registro solicitado no existe.', $msg);
});

// ── Seguridad: usuario nulo (sesión sin id) es bloqueado ─────────────────────
test('assertOwnedBy bloquea cuando el userId es nulo', function () use ($deny): void {
    $r = new CrudScopeResolver();
    assert_throws(ValidationException::class, function () use ($r, $deny): void {
        $r->assertOwnedBy(
            ownership_def(['type' => 'owner', 'column' => 'created_by']),
            ['id' => 7, 'created_by' => 42],
            null,
            $deny
        );
    });
});

// ── Seguridad: bypass declarado pero sin permiso y no-dueño → bloquea ─────────
test('assertOwnedBy bloquea si hay bypass declarado pero el usuario no lo posee ni es dueño', function () use ($deny): void {
    $r = new CrudScopeResolver();
    assert_throws(ValidationException::class, function () use ($r, $deny): void {
        $r->assertOwnedBy(
            ownership_def(['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']),
            ['id' => 7, 'created_by' => 999],
            42,
            $deny
        );
    });
});

test('assertOwnedBy bloquea registro fuera de scope_handler custom (C1 IDOR)', function () use ($deny): void {
    $r = new CrudScopeResolver(new CrudHandlerRegistry([
        'eventos_custom' => OwnershipFixtureCustomScope::class,
    ]));
    assert_throws(ValidationException::class, function () use ($r, $deny): void {
        $r->assertOwnedBy(
            ownership_def_handler('eventos_custom'),
            ['id' => 7, 'created_by' => 42], // 42 ≠ 99 del fixture
            42,
            $deny
        );
    });
});

test('assertOwnedBy permite registro dentro de scope_handler custom', function () use ($deny): void {
    $r = new CrudScopeResolver(new CrudHandlerRegistry([
        'eventos_custom' => OwnershipFixtureCustomScope::class,
    ]));
    $r->assertOwnedBy(
        ownership_def_handler('eventos_custom'),
        ['id' => 7, 'created_by' => 99],
        42,
        $deny
    );
    assert_true(true, 'dentro de scope custom: no lanza');
});

test('assertOwnedBy deniega si el scope_handler declarado no está en la whitelist', function () use ($deny): void {
    $r = new CrudScopeResolver(new CrudHandlerRegistry([]));
    assert_throws(ValidationException::class, function () use ($r, $deny): void {
        $r->assertOwnedBy(
            ownership_def_handler('eventos_custom'),
            ['id' => 7, 'created_by' => 42],
            42,
            $deny
        );
    }, 'handler no registrado no puede degradar a "sin scope"');
});

test('assertOwnedBy deniega si el recurso declara scope_handler y falta el registry', function () use ($deny): void {
    $r = new CrudScopeResolver();
    assert_throws(ValidationException::class, function () use ($r, $deny): void {
        $r->assertOwnedBy(
            ownership_def_handler('eventos_custom'),
            ['id' => 7, 'created_by' => 42],
            42,
            $deny
        );
    }, 'sin registry cableado el aislamiento declarado debe fallar cerrado');
});

test('assertOwnedBy con scope_handler conserva mensaje no revelador', function () use ($deny): void {
    $r = new CrudScopeResolver(new CrudHandlerRegistry([
        'eventos_custom' => OwnershipFixtureCustomScope::class,
    ]));
    $msg = null;
    try {
        $r->assertOwnedBy(
            ownership_def_handler('eventos_custom'),
            ['id' => 7, 'created_by' => 1],
            42,
            $deny
        );
    } catch (ValidationException $e) {
        $msg = $e->getMessage();
    }
    assert_same('El registro solicitado no existe.', $msg);
});
