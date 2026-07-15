<?php
// tests/Marketing/MarketingExperimentsControllerContractTest.php
declare(strict_types=1);

test('MarketingExperimentsController exige CSRF en accept/reject', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/app/Presentation/Controllers/Admin/MarketingExperimentsController.php');
    assert_true(str_contains($src, 'verifyCsrf'), 'csrf controller');
    assert_true(str_contains($src, 'function accept('), 'accept action');
    assert_true(str_contains($src, 'function reject('), 'reject action');
    assert_true(str_contains($src, 'function index('), 'dashboard action');
});

test('routes marketing_admin registra experimentos con CSRF y RBAC', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/routes/marketing_admin.php');
    assert_true(str_contains($src, '/marketing/experimentos'), 'ruta');
    assert_true(str_contains($src, 'marketing.experimentos'), 'rbac');
    assert_true(str_contains($src, 'CsrfMiddleware'), 'csrf middleware dual');
    assert_true(str_contains($src, '/marketing/experimentos/accept'), 'ruta accept');
    assert_true(str_contains($src, '/marketing/experimentos/reject'), 'ruta reject');
});

test('container.php enlaza MarketingExperimentsController y sus use cases bajo el guard de marketing', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/config/container.php');
    assert_true(str_contains($src, 'MarketingExperimentsController'), 'controller');
    assert_true(str_contains($src, 'AcceptVariantProposalUseCase'), 'accept use case');
    assert_true(str_contains($src, 'RejectVariantProposalUseCase'), 'reject use case');
});

test('vista experiments.php existe y usa csrfField en los forms de accept/reject', function (): void {
    $path = ROOT_PATH . '/app/Presentation/Views/admin/marketing/experiments.php';
    assert_true(is_file($path), 'vista existe');
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'ViewHelper::csrfField()'), 'csrf en vista');
    assert_true(str_contains($src, '/marketing/experimentos/accept'), 'form accept');
    assert_true(str_contains($src, '/marketing/experimentos/reject'), 'form reject');
    assert_true(str_contains($src, 'min_sessions'), 'hint anti-deuda §Y menciona min_sessions');
});
