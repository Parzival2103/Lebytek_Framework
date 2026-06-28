<?php
// tests/Marketing/AjustesExtensibleTest.php
declare(strict_types=1);

use Lebytek\Framework\Application\Services\SettingsSectionRegistry;
use Lebytek\Framework\Domain\Interfaces\SettingsSectionProviderInterface;

// Verifica la lógica de combinación de campos sin arrancar HTTP:
// los campos de sistema fijos + los de providers visibles forman el set a persistir.
test('el set de claves a guardar incluye campos de sistema y de providers visibles', function (): void {
    $camposSistema = ['empresa_nombre','menu_layout','primary_color','navbar_color','body_color','empresa_logo'];

    $provider = new class implements SettingsSectionProviderInterface {
        public function clave(): string { return 'marketing_correo'; }
        public function titulo(): string { return 'Correo'; }
        public function icono(): string { return 'bi-envelope'; }
        public function permiso(): string { return 'marketing.gestionar'; }
        public function campos(): array { return [['name'=>'mkt_mail_host','label'=>'Host','type'=>'text']]; }
        public function vista(): ?string { return null; }
    };
    $reg = new SettingsSectionRegistry([$provider]);

    $todas = array_merge($camposSistema, $reg->fieldNames(['marketing.gestionar']));
    assert_true(in_array('empresa_nombre', $todas, true), 'conserva campo de sistema');
    assert_true(in_array('mkt_mail_host', $todas, true), 'incluye campo de provider');
});

test('sin permisos del módulo no se cuelan campos de provider', function (): void {
    $provider = new class implements SettingsSectionProviderInterface {
        public function clave(): string { return 'x'; }
        public function titulo(): string { return 'x'; }
        public function icono(): string { return 'bi-x'; }
        public function permiso(): string { return 'marketing.gestionar'; }
        public function campos(): array { return [['name'=>'mkt_x','label'=>'x','type'=>'text']]; }
        public function vista(): ?string { return null; }
    };
    $reg = new SettingsSectionRegistry([$provider]);
    assert_same([], $reg->fieldNames(['administracion.ver']));
});
