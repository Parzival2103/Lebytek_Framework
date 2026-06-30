<?php
// tests/Marketing/ContentProvidersTest.php
declare(strict_types=1);

use App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface;
use App\Infrastructure\Marketing\CrudLandingContentProvider;
use App\Infrastructure\Marketing\CrudCommercialPackageSource;
use App\Application\Marketing\RenderLandingUseCase;

function fakeContentRepo(): MarketingContentRepositoryInterface
{
    return new class implements MarketingContentRepositoryInterface {
        public function bloquesPorPagina(string $pagina): array {
            return $pagina === 'home'
                ? ['hero' => ['titulo' => 'Bienvenido', 'subtitulo' => 'Sub', 'cta_texto' => 'Demo', 'cta_url' => '#demo']]
                : [];
        }
        public function paquetesActivos(): array {
            return [['nombre' => 'Plan A', 'precio_mensual' => '299', 'destacado' => 1, 'badge' => 'Popular']];
        }
    };
}

test('CrudLandingContentProvider devuelve bloques de la página', function (): void {
    $p = new CrudLandingContentProvider(fakeContentRepo());
    $bloques = $p->getBloques('home');
    assert_same('Bienvenido', $bloques['hero']['titulo']);
    assert_same([], $p->getBloques('inexistente'));
});

test('CrudCommercialPackageSource lista paquetes activos', function (): void {
    $s = new CrudCommercialPackageSource(fakeContentRepo());
    $paquetes = $s->listarPaquetes();
    assert_same(1, count($paquetes));
    assert_same('Plan A', $paquetes[0]['nombre']);
});

test('RenderLandingUseCase compone bloques y paquetes', function (): void {
    $uc = new RenderLandingUseCase(
        new CrudLandingContentProvider(fakeContentRepo()),
        new CrudCommercialPackageSource(fakeContentRepo())
    );
    $vm = $uc->ejecutar('home');
    assert_same('Bienvenido', $vm['bloques']['hero']['titulo']);
    assert_same('Plan A', $vm['paquetes'][0]['nombre']);
});
