<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Application\Crud\Context\CrudListContext;
use Lebytek\Framework\Application\Crud\Scopes\OwnerListScope;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Interfaces\CrudListScopeInterface;

/**
 * Resuelve el scope de listado de un recurso (built-in owner o handler custom)
 * y traduce las condiciones acumuladas a SQL. Fuente única de verdad para el
 * filtrado del listado y el bloqueo server-side (show/edit/update/delete).
 */
final class CrudScopeResolver
{
    public function __construct(
        private readonly ?CrudHandlerRegistry $handlerRegistry = null
    ) {}

    /**
     * @param callable(string): bool $can
     */
    public function resolve(CrudResourceDefinition $definition, ?int $userId, callable $can): ?CrudListScopeInterface
    {
        $handlerKey = $definition->listScopeHandler();
        if ($handlerKey !== null && $handlerKey !== '' && $this->handlerRegistry !== null) {
            $scope = $this->handlerRegistry->resolve($handlerKey, CrudListScopeInterface::class);
            return $scope instanceof CrudListScopeInterface ? $scope : null;
        }

        $meta = $this->ownerMeta($definition);
        if ($meta === null) {
            return null;
        }

        $hasBypass = $meta['bypass'] !== null && $can($meta['bypass']);
        return new OwnerListScope($meta['column'], $hasBypass, $userId);
    }

    /**
     * Bloqueo server-side de propiedad: única fuente de verdad para show/edit/
     * update/delete (CrudResourceService) y para las acciones de fila/masivas
     * (CrudActionService). Si `resolve()` no produce scope, no hace nada. Si
     * produce scope (owner o `scope_handler`), exige que el registro cumpla las
     * condiciones; si no, trata como inexistente para no revelar registros ajenos.
     *
     * @param array<string, mixed> $record
     * @param callable(string): bool $can
     */
    public function assertOwnedBy(CrudResourceDefinition $definition, array $record, ?int $userId, callable $can): void
    {
        $scope = $this->resolve($definition, $userId, $can);
        if ($scope === null) {
            return;
        }

        $ctx = new CrudListContext(
            $definition->key(),
            $definition->table(),
            $definition->primaryKey(),
            $userId,
            '',
            []
        );
        $scope->apply($ctx);

        if (!self::recordMatchesConditions($record, $ctx->conditions())) {
            throw new ValidationException('El registro solicitado no existe.');
        }
    }

    /**
     * Evalúa condiciones de scope contra un registro ya cargado (acceso por ID).
     * Mapa vacío => true (p. ej. owner con bypass). Columna ausente => false (fail-closed).
     *
     * @param array<string, mixed> $record
     * @param list<array{column: string, op: string, value: mixed}> $conditions
     */
    public static function recordMatchesConditions(array $record, array $conditions): bool
    {
        foreach ($conditions as $cond) {
            $column = (string) ($cond['column'] ?? '');
            if ($column === '' || !array_key_exists($column, $record)) {
                return false;
            }
            $actual = $record[$column];
            $op = (string) ($cond['op'] ?? '=');
            $expected = $cond['value'] ?? null;

            $ok = match ($op) {
                '='  => (string) $actual === (string) $expected,
                '!=' => (string) $actual !== (string) $expected,
                '<'  => (float) $actual < (float) $expected,
                '>'  => (float) $actual > (float) $expected,
                '<=' => (float) $actual <= (float) $expected,
                '>=' => (float) $actual >= (float) $expected,
                'IN' => is_array($expected) && in_array((string) $actual, array_map('strval', $expected), true),
                'LIKE' => is_string($expected) && self::likeMatch((string) $actual, $expected),
                default => false,
            };
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    private static function likeMatch(string $actual, string $pattern): bool
    {
        $regex = '/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote($pattern, '/')) . '$/u';
        return preg_match($regex, $actual) === 1;
    }

    /**
     * Metadata de propiedad para el bloqueo server-side. bypass ya con {prefix}
     * expandido. Devuelve null si el recurso no declara scope owner.
     *
     * @return array{column: string, bypass: ?string}|null
     */
    public function ownerMeta(CrudResourceDefinition $definition): ?array
    {
        $scope = $definition->listScope();
        if (!is_array($scope) || (string) ($scope['type'] ?? '') !== 'owner') {
            return null;
        }
        $column = (string) ($scope['column'] ?? '');
        if ($column === '') {
            return null;
        }
        $bypassRaw = isset($scope['bypass_permission']) && is_string($scope['bypass_permission']) && $scope['bypass_permission'] !== ''
            ? $scope['bypass_permission']
            : null;
        $bypass = $bypassRaw !== null
            ? str_replace('{prefix}', $definition->permissionPrefix(), $bypassRaw)
            : null;

        return ['column' => $column, 'bypass' => $bypass];
    }

    /**
     * Traduce condiciones estructuradas a partes WHERE + params posicionales.
     *
     * @param list<array{column: string, op: string, value: mixed}> $conditions
     * @return array{0: list<string>, 1: list<mixed>}
     */
    public static function conditionsToSql(array $conditions): array
    {
        $where = [];
        $params = [];

        foreach ($conditions as $cond) {
            $column = '`' . str_replace('`', '', (string) ($cond['column'] ?? '')) . '`';
            $op = (string) ($cond['op'] ?? '=');
            $value = $cond['value'] ?? null;

            if ($op === 'IN' && is_array($value)) {
                if ($value === []) {
                    $where[] = '1 = 0';
                    continue;
                }
                $where[] = $column . ' IN (' . implode(', ', array_fill(0, count($value), '?')) . ')';
                foreach ($value as $v) {
                    $params[] = $v;
                }
                continue;
            }

            $where[] = $column . ' ' . $op . ' ?';
            $params[] = $value;
        }

        return [$where, $params];
    }
}
