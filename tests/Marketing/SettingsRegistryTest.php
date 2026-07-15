<?php
// tests/Marketing/SettingsRegistryTest.php
declare(strict_types=1);

use Lebytek\Framework\Application\Services\SettingsSectionRegistry;
use Lebytek\Framework\Domain\Interfaces\SettingsSectionProviderInterface;

function fakeSettingsProvider(string $clave, string $permiso, array $fieldNames): SettingsSectionProviderInterface
{
    return new class($clave, $permiso, $fieldNames) implements SettingsSectionProviderInterface {
        public function __construct(private string $c, private string $p, private array $f) {}
        public function clave(): string { return $this->c; }
        public function titulo(): string { return 'T ' . $this->c; }
        public function icono(): string { return 'bi-gear'; }
        public function permiso(): string { return $this->p; }
        public function campos(): array {
            return array_map(fn($n) => ['name' => $n, 'label' => $n, 'type' => 'text'], $this->f);
        }
        public function vista(): ?string { return null; }
    };
}

test('visibles filtra providers por permiso del usuario', function (): void {
    $reg = new SettingsSectionRegistry([
        fakeSettingsProvider('correo', 'marketing.gestionar', ['mkt_mail_host']),
        fakeSettingsProvider('otro', 'permiso.inexistente', ['x']),
    ]);
    $visibles = $reg->visibles(['marketing.gestionar']);
    assert_same(1, count($visibles));
    assert_same('correo', $visibles[0]->clave());
});

test('fieldNames devuelve campos planos solo de providers visibles', function (): void {
    $reg = new SettingsSectionRegistry([
        fakeSettingsProvider('correo', 'marketing.gestionar', ['mkt_mail_host', 'mkt_mail_from']),
        fakeSettingsProvider('oculto', 'no.tengo', ['mkt_secreto']),
    ]);
    $names = $reg->fieldNames(['marketing.gestionar']);
    assert_true(in_array('mkt_mail_host', $names, true), 'incluye host');
    assert_true(in_array('mkt_mail_from', $names, true), 'incluye from');
    assert_true(!in_array('mkt_secreto', $names, true), 'excluye oculto');
});

test('registro vacío devuelve listas vacías', function (): void {
    $reg = new SettingsSectionRegistry([]);
    assert_same([], $reg->visibles(['x']));
    assert_same([], $reg->fieldNames(['x']));
});
