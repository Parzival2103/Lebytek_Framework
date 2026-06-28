<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Entities\Crud;

/**
 * Definición inmutable de una acción de fila o masiva del CRUD Engine.
 * `visible_when`/`enabled_when` son mapas de igualdad simple (escalar o lista);
 * no hay lenguaje de expresiones ni eval. Se evalúan al render y se RE-VALIDAN
 * en el servidor antes de ejecutar.
 */
final class CrudActionDefinition
{
    private const TYPES = ['builtin', 'handler', 'link', 'transition'];

    /**
     * @param array<string, mixed> $visibleWhen
     * @param array<string, mixed> $enabledWhen
     */
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly string $label,
        private readonly string $icon,
        private readonly string $method,
        private readonly ?string $route,
        private readonly ?string $confirm,
        private readonly ?string $handler,
        private readonly ?string $permission,
        private readonly ?string $to,
        private readonly ?string $guard,
        private readonly array $visibleWhen,
        private readonly array $enabledWhen
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $name = (string) ($config['name'] ?? '');
        $type = (string) ($config['type'] ?? 'builtin');
        if (!in_array($type, self::TYPES, true)) {
            $type = 'builtin';
        }

        $defaultMethod = $type === 'link' ? 'GET' : 'POST';
        $method = strtoupper((string) ($config['method'] ?? $defaultMethod));
        if (!in_array($method, ['GET', 'POST'], true)) {
            $method = $defaultMethod;
        }

        return new self(
            name: $name,
            type: $type,
            label: (string) ($config['label'] ?? $name),
            icon: (string) ($config['icon'] ?? ''),
            method: $method,
            route: isset($config['route']) && $config['route'] !== '' ? (string) $config['route'] : null,
            confirm: isset($config['confirm']) && $config['confirm'] !== '' ? (string) $config['confirm'] : null,
            handler: isset($config['handler']) && $config['handler'] !== '' ? (string) $config['handler'] : null,
            permission: isset($config['permission']) && $config['permission'] !== '' ? (string) $config['permission'] : null,
            to: isset($config['to']) && $config['to'] !== '' ? (string) $config['to'] : null,
            guard: isset($config['guard']) && $config['guard'] !== '' ? (string) $config['guard'] : null,
            visibleWhen: is_array($config['visible_when'] ?? null) ? $config['visible_when'] : [],
            enabledWhen: is_array($config['enabled_when'] ?? null) ? $config['enabled_when'] : []
        );
    }

    public function name(): string { return $this->name; }
    public function type(): string { return $this->type; }
    public function label(): string { return $this->label; }
    public function icon(): string { return $this->icon; }
    public function method(): string { return $this->method; }
    public function route(): ?string { return $this->route; }
    public function confirm(): ?string { return $this->confirm; }
    public function handler(): ?string { return $this->handler; }
    public function to(): ?string { return $this->to; }
    public function guard(): ?string { return $this->guard; }

    public function isBuiltin(): bool { return $this->type === 'builtin'; }
    public function isHandler(): bool { return $this->type === 'handler'; }
    public function isLink(): bool { return $this->type === 'link'; }
    public function isTransition(): bool { return $this->type === 'transition'; }

    /** Slug completo si contiene punto; si no, se expande contra el prefijo. null si no hay permiso. */
    public function resolvePermission(string $prefix): ?string
    {
        if ($this->permission === null) {
            return null;
        }
        return str_contains($this->permission, '.') ? $this->permission : $prefix . '.' . $this->permission;
    }

    /** @param array<string, mixed> $row */
    public function isVisibleFor(array $row): bool
    {
        return self::equalityMatches($this->visibleWhen, $row);
    }

    /** @param array<string, mixed> $row */
    public function isEnabledFor(array $row): bool
    {
        return self::equalityMatches($this->enabledWhen, $row);
    }

    /**
     * Cada par columna→valor debe coincidir. El valor puede ser escalar (igualdad
     * laxa por string) o lista (pertenencia). Mapa vacío => true.
     *
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $row
     */
    public static function equalityMatches(array $conditions, array $row): bool
    {
        foreach ($conditions as $column => $expected) {
            $actual = $row[$column] ?? null;
            if (is_array($expected)) {
                $ok = false;
                foreach ($expected as $candidate) {
                    if ((string) $actual === (string) $candidate) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    return false;
                }
            } elseif ((string) $actual !== (string) $expected) {
                return false;
            }
        }
        return true;
    }
}
