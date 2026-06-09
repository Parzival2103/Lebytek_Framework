<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Crud\Scopes\OwnerListScope;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Interfaces\CrudListScopeInterface;

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
