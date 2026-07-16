<?php

declare(strict_types=1);

/**
 * Calcula el score híbrido (engagement + conversión) por variante de landing
 * sobre la ventana `score_window_days` y, si el cambio de pesos sugerido
 * respecto a los pesos vigentes es material (`proposal_min_delta`), encola
 * una propuesta `pending` en `dom_mkt_variant_proposals` para revisión
 * manual (Task 9 — accept/reject en Ops UI).
 *
 * **Nunca** escribe `dom_mkt_variant_weights` — ver
 * `App\Application\Marketing\ComputeVariantScoresUseCase` (Anti-deuda §E/§W).
 *
 * Cron sugerido, diario 06:00:
 *   0 6 * * * cd /path/to/app && php scripts/compute-landing-variant-scores.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');

require ROOT_PATH.'/vendor/autoload.php';

use App\Application\Marketing\ComputeVariantScoresUseCase;
use App\Domain\Marketing\LandingVariantRegistry;
use App\Infrastructure\Marketing\PdoLandingMetricsRepository;
use App\Infrastructure\Marketing\PdoVariantProposalRepository;
use App\Infrastructure\Marketing\PdoVariantWeightRepository;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\EnvLoader;

EnvLoader::load(ROOT_PATH.'/.env');
Config::init(ROOT_PATH.'/config');
Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
]);

$variantsCfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
$experimentsCfg = require ROOT_PATH.'/config/marketing/landing_experiments.php';

$useCase = new ComputeVariantScoresUseCase(
    new PdoLandingMetricsRepository(),
    new PdoVariantWeightRepository(),
    new PdoVariantProposalRepository(),
    new LandingVariantRegistry($variantsCfg),
    $experimentsCfg,
);

$result = $useCase->ejecutar();

fwrite(STDOUT, 'proposals_created='.$result['proposals_created']."\n");
foreach ($result['rankings'] as $row) {
    fwrite(STDOUT, sprintf(
        "slug=%s score=%.4f sessions=%d leads=%d engagement=%.4f conversion=%s\n",
        (string) $row['slug'],
        (float) $row['score'],
        (int) $row['sessions'],
        (int) $row['leads'],
        (float) $row['engagement'],
        $row['conversion'] === null ? 'n/a' : sprintf('%.4f', (float) $row['conversion']),
    ));
}

exit(0);
