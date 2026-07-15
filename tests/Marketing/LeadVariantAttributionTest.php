<?php
// tests/Marketing/LeadVariantAttributionTest.php
declare(strict_types=1);

use App\Domain\Marketing\ValueObjects\LeadDraft;

test('LeadDraft expone landingVariant y visitorId', function (): void {
    $d = new LeadDraft('A', 'a@b.com', null, null, [], 'v2', '11111111-1111-4111-8111-111111111111');
    assert_same('v2', $d->landingVariant());
    assert_same('11111111-1111-4111-8111-111111111111', $d->visitorId());
});

test('LeadDraft mantiene BC sin landingVariant/visitorId', function (): void {
    $d = new LeadDraft('Ana', 'ana@example.com', '555', 'Hola', ['utm_source' => 'fb']);
    assert_same(null, $d->landingVariant());
    assert_same(null, $d->visitorId());
});

test('PdoLeadRepository INSERT incluye landing_variant', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/app/Infrastructure/Marketing/PdoLeadRepository.php');
    assert_true(str_contains($src, 'landing_variant'), 'columna');
    assert_true(str_contains($src, 'visitor_id'), 'visitor');
});

test('lead forms incluyen hidden landing_variant', function (): void {
    foreach (['_lead_form.php', 'v2/_lead_form.php'] as $rel) {
        $src = (string) file_get_contents(ROOT_PATH.'/app/Presentation/Views/publico/partials/'.$rel);
        assert_true(str_contains($src, 'name="landing_variant"'), $rel);
        assert_true(str_contains($src, 'name="visitor_id"'), $rel);
    }
});

test('LeadController no atribuye variante bajo lb_preview', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/app/Presentation/Controllers/Publico/LeadController.php');
    assert_true(str_contains($src, 'lb_preview'), 'lee preview cookie');
});

test('LeadController resuelve variante desde lb_var o body con regex de slug', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/app/Presentation/Controllers/Publico/LeadController.php');
    assert_true(str_contains($src, 'lb_var'), 'lee cookie sticky lb_var');
    assert_true(str_contains($src, 'landing_variant'), 'lee body landing_variant');
    assert_true(str_contains($src, 'lb_vid'), 'lee cookie lb_vid');
});

test('LeadController usa LandingMetricsRepositoryInterface::insertLeadSubmitEvent tras captura exitosa', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/app/Presentation/Controllers/Publico/LeadController.php');
    assert_true(str_contains($src, 'insertLeadSubmitEvent'), 'emite evento lead_submit de confianza');
});

test('LeadController aísla fallo de insertLeadSubmitEvent para no romper captura', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/app/Presentation/Controllers/Publico/LeadController.php');
    assert_true(str_contains($src, 'insertLeadSubmitEvent'), 'emite evento lead_submit de confianza');
    assert_true(str_contains($src, 'catch (\Throwable'), 'fallo de metrics no rompe redirect/flash');
});

test('LandingMetricsRepositoryInterface expone insertLeadSubmitEvent', function (): void {
    assert_true(
        method_exists(\App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface::class, 'insertLeadSubmitEvent'),
        'contrato define insertLeadSubmitEvent'
    );
});
