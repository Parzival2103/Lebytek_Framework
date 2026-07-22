<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Domain\Interfaces\ConfiguracionRepositoryInterface;

final class ArrayConfiguracionRepository implements ConfiguracionRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data = []) {}

    public function get(string $clave, mixed $default = null): mixed
    {
        return $this->data[$clave] ?? $default;
    }

    public function set(string $clave, mixed $valor): void
    {
        $this->data[$clave] = $valor;
    }

    /** @param array<string, mixed> $datos */
    public function setMultiple(array $datos): void
    {
        foreach ($datos as $clave => $valor) {
            $this->data[$clave] = $valor;
        }
    }

    public function all(): array
    {
        return $this->data;
    }
}

test('set hidrata cache desde repo antes de mutar si cache vacia', function (): void {
    $repo = new ArrayConfiguracionRepository([
        'empresa_nombre' => 'Lebytek Original',
        'dark_mode' => '0',
    ]);
    $service = new ConfiguracionService($repo);
    $service->set('primary_color', '#112233');
    assert_same('Lebytek Original', $service->get('empresa_nombre'));
    assert_same('#112233', $service->get('primary_color'));
});

test('setMultiple hidrata cache desde repo antes de mutar si cache vacia', function (): void {
    $repo = new ArrayConfiguracionRepository([
        'empresa_nombre' => 'Lebytek Original',
    ]);
    $service = new ConfiguracionService($repo);
    $service->setMultiple([
        'primary_color' => '#445566',
        'dark_mode' => '1',
    ]);
    assert_same('Lebytek Original', $service->get('empresa_nombre'));
    assert_same('#445566', $service->get('primary_color'));
    assert_same('1', $service->get('dark_mode'));
});

test('toggleDarkMode no pierde otras claves en cache', function (): void {
    $repo = new ArrayConfiguracionRepository([
        'dark_mode' => '0',
        'empresa_nombre' => 'Lebytek',
    ]);
    $service = new ConfiguracionService($repo);
    $nuevo = $service->toggleDarkMode();
    assert_same('1', $nuevo);
    assert_same('Lebytek', $service->get('empresa_nombre'));
});
