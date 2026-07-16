<?php
// tests/Marketing/LandingExperimentAssignerTest.php
declare(strict_types=1);

use App\Application\Marketing\AssignInput;
use App\Application\Marketing\AssignedLandingVariant;
use App\Application\Marketing\LandingExperimentAssigner;
use App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface;
use App\Domain\Marketing\LandingVariantRegistry;

final class FakeWeights implements VariantWeightRepositoryInterface
{
    /** @param array<string,float> $weights */
    public function __construct(private array $weights) {}
    public function all(): array { return $this->weights; }
    public function get(string $slug): ?float { return $this->weights[$slug] ?? null; }
    public function upsert(string $slug, float $weight): void { $this->weights[$slug] = $weight; }
    public function seedMissing(array $defaults): void {
        foreach ($defaults as $s => $w) {
            if (!isset($this->weights[$s])) {
                $this->weights[$s] = $w;
            }
        }
    }
}

function makeAssigner(array $weights, ?array $variantsCfg = null): LandingExperimentAssigner
{
    $exp = require ROOT_PATH . '/config/marketing/landing_experiments.php';
    $cfg = $variantsCfg ?? require ROOT_PATH . '/config/marketing/landing_variants.php';
    return new LandingExperimentAssigner(
        new LandingVariantRegistry($cfg),
        new FakeWeights($weights),
        $exp
    );
}

/** @return list<string> */
function cookieNames(AssignedLandingVariant $out): array
{
    return array_map(static fn ($c) => $c->name, $out->cookies);
}

function input(string $force = '', array $cookies = []): AssignInput
{
    return new AssignInput($force, $cookies, true);
}

test('assigner reusa cookie sticky si variante elegible', function (): void {
    $a = makeAssigner(['v1' => 0.5, 'v2' => 0.5]);
    $out = $a->assign(input('', [
        'lb_vid' => '11111111-1111-4111-8111-111111111111',
        'lb_var' => 'v1',
    ]));
    assert_same('v1', $out->slug);
    assert_same(false, $out->isPreview);
    assert_true(!in_array('lb_var', cookieNames($out), true), 'no reescribe sticky si ya válida');
});

test('assigner reasigna si peso es 0', function (): void {
    $a = makeAssigner(['v1' => 0.0, 'v2' => 1.0]);
    $out = $a->assign(input('', [
        'lb_vid' => '11111111-1111-4111-8111-111111111111',
        'lb_var' => 'v1',
    ]));
    assert_same('v2', $out->slug);
    assert_true(in_array('lb_var', cookieNames($out), true), 'reescribe sticky al reasignar');
});

test('assigner reasigna si status paused aunque weight > 0', function (): void {
    $cfg = require ROOT_PATH . '/config/marketing/landing_variants.php';
    $cfg['variants']['v1']['status'] = 'paused';
    $a = makeAssigner(['v1' => 1.0, 'v2' => 1.0], $cfg);
    $out = $a->assign(input('', [
        'lb_vid' => '11111111-1111-4111-8111-111111111111',
        'lb_var' => 'v1',
    ]));
    assert_same('v2', $out->slug);
    assert_true(in_array('lb_var', cookieNames($out), true), 'reescribe sticky al pausar');
});

test('assigner ?variant= fuerza preview SIN escribir lb_var y CON lb_preview', function (): void {
    $a = makeAssigner(['v1' => 1.0, 'v2' => 1.0]);
    $out = $a->assign(input('v2', [
        'lb_vid' => '11111111-1111-4111-8111-111111111111',
        'lb_var' => 'v1',
    ]));
    assert_same('v2', $out->slug);
    assert_same(true, $out->isPreview);
    assert_true(!in_array('lb_var', cookieNames($out), true), 'preview no pisa sticky');
    assert_true(in_array('lb_preview', cookieNames($out), true), 'marca preview cookie');
});

test('assigner no-preview borra lb_preview', function (): void {
    $a = makeAssigner(['v1' => 1.0, 'v2' => 0.0]);
    $out = $a->assign(input('', [
        'lb_vid' => '11111111-1111-4111-8111-111111111111',
        'lb_var' => 'v1',
        'lb_preview' => 'v2',
    ]));
    $previewCookies = array_values(array_filter($out->cookies, static fn ($c) => $c->name === 'lb_preview'));
    assert_true(count($previewCookies) === 1 && $previewCookies[0]->delete === true, 'limpia preview');
});

test('assigner ?landing= llega como forceVariant desde controller', function (): void {
    $a = makeAssigner(['v1' => 1.0, 'v2' => 1.0]);
    $out = $a->assign(input('v1', [
        'lb_vid' => '22222222-2222-4222-8222-222222222222',
    ]));
    assert_same('v1', $out->slug);
    assert_same(true, $out->isPreview);
});

test('assigner fallback a v2 si todos peso 0', function (): void {
    $a = makeAssigner(['v1' => 0.0, 'v2' => 0.0]);
    $out = $a->assign(input('', [
        'lb_vid' => '33333333-3333-4333-8333-333333333333',
    ]));
    assert_same('v2', $out->slug);
});

test('assigner no lee LANDING_VARIANT ni Request ni setcookie', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/app/Application/Marketing/LandingExperimentAssigner.php');
    assert_true(!str_contains($src, 'LANDING_VARIANT'), 'sin env en assign');
    assert_true(!str_contains($src, 'setcookie'), 'sin side-effect cookie en Application');
    assert_true(!str_contains($src, 'Kernel\\Http\\Request'), 'sin Request Kernel');
});
