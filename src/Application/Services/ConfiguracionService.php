<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Domain\Interfaces\ConfiguracionRepositoryInterface;
use Lebytek\Framework\Kernel\Constants\AppConstants;
use Lebytek\Framework\Kernel\Security\Session;

/*
|--------------------------------------------------------------------------
| ConfiguracionService — Acceso y actualización de ajustes del sistema
|--------------------------------------------------------------------------
*/

final class ConfiguracionService
{
    private static ?array $cache = null;

    public function __construct(
        private readonly ConfiguracionRepositoryInterface $configuracionRepo
    ) {}

    public function get(string $clave, mixed $default = null): mixed
    {
        if (self::$cache === null) {
            $this->cargarCache();
        }
        return self::$cache[$clave] ?? $default;
    }

    public function set(string $clave, mixed $valor): void
    {
        $this->configuracionRepo->set($clave, $valor);
        self::$cache[$clave] = $valor;
    }

    public function setMultiple(array $datos): void
    {
        $this->configuracionRepo->setMultiple($datos);
        foreach ($datos as $clave => $valor) {
            self::$cache[$clave] = $valor;
        }
    }

    public function all(): array
    {
        if (self::$cache === null) {
            $this->cargarCache();
        }
        return self::$cache;
    }

    private function cargarCache(): void
    {
        self::$cache = $this->configuracionRepo->all();
    }

    // ── Accesores semánticos ──────────────────────────────────────────────────

    public function menuLayout(): string
    {
        return $this->get(AppConstants::CONFIG_MENU_LAYOUT, AppConstants::MENU_LAYOUT_SIDE);
    }

    public function primaryColor(): string
    {
        return $this->get(AppConstants::CONFIG_PRIMARY_COLOR, '#0d6efd');
    }

    public function darkMode(): bool
    {
        return (bool) $this->get(AppConstants::CONFIG_DARK_MODE, false);
    }

    public function empresaNombre(): string
    {
        return AppConstants::resolveEmpresaNombre($this->get(AppConstants::CONFIG_EMPRESA_NOMBRE, null));
    }

    public function empresaLogo(): string
    {
        return $this->get(AppConstants::CONFIG_EMPRESA_LOGO, '');
    }

    public function toggleDarkMode(): string
    {
        $actual = $this->get(AppConstants::CONFIG_DARK_MODE, '0');
        $nuevo  = $actual === '1' ? '0' : '1';
        $this->set(AppConstants::CONFIG_DARK_MODE, $nuevo);
        return $nuevo;
    }
}
