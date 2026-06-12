<?php

declare(strict_types=1);

use App\Application\Services\CrudScopeResolver;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Exceptions\ValidationException;

/**
 * H2 (IDOR) — la regla de propiedad usada por show/edit/update/delete y por las
 * acciones de fila/masivas vive en un único guard puro: CrudScopeResolver::assertOwnedBy().
 * Aquí se prueba ese guard con una definición real (vía fromArray) y un closure
 * $can, sin tocar DB ni doblar clases final. CrudActionService la consume
 * idénticamente desde run()/runBulk().
 */
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
