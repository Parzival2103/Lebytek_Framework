<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');
define('STORAGE_PATH', ROOT_PATH.'/storage');

require ROOT_PATH.'/vendor/autoload.php';

use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Container\Container;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Http\Router;

EnvLoader::load(ROOT_PATH.'/.env');
Config::init(ROOT_PATH.'/config');

$marketingActivo = (bool) Config::get('vertical.modules.marketing', false);
fwrite(STDOUT, 'marketingActivo='.($marketingActivo ? 'true' : 'false')."\n");

$router = new Router();
$container = new Container();
($containerConfig = require ROOT_PATH.'/config/container.php')($container);
$router->setContainer($container);

require ROOT_PATH.'/routes/web.php';

$ref = new ReflectionClass($router);
$prop = $ref->getProperty('routes');
$prop->setAccessible(true);
$routes = $prop->getValue($router);

foreach ($routes as $r) {
    if (str_contains($r['pattern'], 'marketing')) {
        fwrite(STDOUT, $r['method'].' '.$r['pattern']."\n");
    }
}

$testUri = '/admin/marketing/leads/provision-api';
$matched = false;
foreach ($routes as $route) {
    if ($route['method'] !== 'GET') {
        continue;
    }
    $params = [];
    $matchMethod = $ref->getMethod('matchRoute');
    $matchMethod->setAccessible(true);
    if ($matchMethod->invoke($router, $route['pattern'], $testUri, $params)) {
        fwrite(STDOUT, "MATCH: {$route['pattern']}\n");
        $matched = true;
    }
}
fwrite(STDOUT, $matched ? "URI_OK\n" : "URI_NO_MATCH\n");
