<?php

declare(strict_types=1);

/**
 * El preflight de `docs/automation/` enumera los commits exclusivos de la rama
 * legacy para rechazar cualquier HEAD que herede uno. La comprobacion es util,
 * pero estaba anclada a `origin/feature/backoffice-api-integration`, que la
 * Task 10 del plan `2026-07-26-skeleton-package-staging.md` borra.
 *
 * Con la rama borrada, `git rev-list origin/main..origin/feature/...` aborta con
 * `invalid object name` en el primer paso y la automatizacion muere antes de
 * llegar a las comprobaciones utiles. Un preflight que revienta no protege nada.
 *
 * Los candidatos se exigen **totalmente cualificados** (`refs/tags/...`,
 * `refs/remotes/origin/...`) a proposito: un nombre corto como
 * `feature/backoffice-api-integration` tambien resuelve contra la rama *local*
 * homonima, asi que una version anterior de este test daba verde con la ref
 * remota ya borrada. Un candidato ambiguo no prueba que el preflight sobreviva.
 */

$root = dirname(__DIR__, 2);

/** @return list<string> rutas de los docs de automatizacion que llevan preflight */
function automation_preflight_docs(string $root): array
{
    $docs = glob($root . '/docs/automation/AUTOMATION-*.md') ?: [];
    sort($docs);

    return array_values(array_filter($docs, static function (string $path): bool {
        return str_contains((string) file_get_contents($path), 'backoffice-api-integration');
    }));
}

function automation_ref_resuelve(string $root, string $ref): bool
{
    $cmd = 'git -C ' . escapeshellarg($root)
        . ' rev-parse --verify --quiet ' . escapeshellarg($ref . '^{commit}');
    $out = [];
    $code = 0;
    exec($cmd . ' 2>&1', $out, $code);

    return $code === 0;
}

test('automation preflight docs exist and mention the legacy ref', function () use ($root): void {
    // Sin esto las demas aserciones serian vacuas por iterar sobre lista vacia.
    assert_true(
        automation_preflight_docs($root) !== [],
        'docs/automation/AUTOMATION-*.md must exist and reference the legacy history'
    );
});

test('no automation preflight pins the deletable branch as a rev-list operand', function () use ($root): void {
    foreach (automation_preflight_docs($root) as $doc) {
        $src = (string) file_get_contents($doc);
        assert_true(
            !str_contains($src, 'origin/main..origin/feature/backoffice-api-integration'),
            basename($doc) . ' pins a branch that Task 10 deletes; the preflight would'
                . ' abort with "invalid object name" before its useful checks'
        );
    }
});

test('automation preflight offers the archive tag as a legacy-ref candidate', function () use ($root): void {
    foreach (automation_preflight_docs($root) as $doc) {
        $src = (string) file_get_contents($doc);
        assert_true(
            str_contains($src, 'refs/tags/archive/backoffice-api-integration'),
            basename($doc) . ' must name refs/tags/archive/backoffice-api-integration,'
                . ' the only legacy ref that survives Task 10'
        );
    }
});

test('at least one fully qualified legacy-ref candidate resolves in this repo', function () use ($root): void {
    foreach (automation_preflight_docs($root) as $doc) {
        $src = (string) file_get_contents($doc);

        preg_match_all(
            '#refs/(?:tags|remotes/origin)/(?:feature|archive)/backoffice-api-integration#',
            $src,
            $matches
        );
        $candidatos = array_values(array_unique($matches[0]));
        assert_true(
            $candidatos !== [],
            basename($doc) . ' must name its legacy-ref candidates fully qualified'
        );

        $resueltos = array_values(array_filter(
            $candidatos,
            static fn(string $ref): bool => automation_ref_resuelve($root, $ref)
        ));

        assert_true(
            $resueltos !== [],
            basename($doc) . ' names only refs that do not resolve ('
                . implode(', ', $candidatos) . '): the preflight has nothing to enumerate'
        );
    }
});
