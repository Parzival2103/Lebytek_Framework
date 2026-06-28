<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Integrations;

use Lebytek\Framework\Domain\Integrations\ApiConnectorInterface;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Infrastructure\Integrations\Channels\EmailChannel;
use Lebytek\Framework\Infrastructure\Integrations\Channels\GreenApiWhatsappChannel;
use Lebytek\Framework\Infrastructure\Integrations\Http\HttpApiConnector;
use Lebytek\Framework\Infrastructure\Integrations\Repositories\IntegrationAccountRepository;
use Lebytek\Framework\Infrastructure\Integrations\Repositories\IntegrationLogRepository;
use Lebytek\Framework\Infrastructure\Mail\LogMailer;
use Lebytek\Framework\Infrastructure\Mail\PhpMailerMailer;
use Lebytek\Framework\Kernel\Config\Config;

/*
|--------------------------------------------------------------------------
| IntegrationsFactory — única vía de construcción de la fachada.
|--------------------------------------------------------------------------
| Usada por el binding del container y por los CRUD handlers (que se
| instancian con `new $class()` sin DI, por lo que no pueden recibir el
| dispatcher por constructor). Un solo camino de construcción.
*/
final class IntegrationsFactory
{
    private static ?NotificationDispatcher $cached = null;

    public static function dispatcher(): NotificationDispatcher
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $config = (array) Config::get('integrations', []);
        if (isset($config['channels']['whatsapp']['config'])) {
            $config['channels']['whatsapp']['config'] =
                self::resolveWhatsappConfig((array) $config['channels']['whatsapp']['config']);
        }
        $logs = new IntegrationLogRepository();
        $http = new HttpApiConnector((int) (($config['channels']['whatsapp']['config']['timeout'] ?? 15)));
        $mailer = self::mailer();

        $registry = new ChannelRegistry(
            self::buildChannels((array) ($config['channels'] ?? []), $mailer, $http)
        );
        $rateLimiter = new RateLimiter((array) ($config['rate_limit'] ?? []), $logs);

        return self::$cached = new NotificationDispatcher($registry, $logs, $rateLimiter);
    }

    /**
     * @param array<string, array{driver?:string, enabled?:bool, config?:array}> $channelsConfig
     * @return array<string, array{driver:string, factory:callable():\Lebytek\Framework\Domain\Integrations\MessageChannelInterface}>
     */
    public static function buildChannels(array $channelsConfig, MailerInterface $mailer, ApiConnectorInterface $http): array
    {
        $out = [];
        foreach ($channelsConfig as $key => $def) {
            if (!(bool) ($def['enabled'] ?? false)) {
                continue;
            }
            $driver = (string) ($def['driver'] ?? $key);
            $cfg = (array) ($def['config'] ?? []);
            $out[$key] = [
                'driver'  => $driver,
                'factory' => static function () use ($driver, $cfg, $mailer, $http) {
                    return match ($driver) {
                        'green_api'      => new GreenApiWhatsappChannel($http, $cfg),
                        'mailer_adapter' => new EmailChannel($mailer),
                        default          => throw new \RuntimeException("Driver de canal no soportado: {$driver}"),
                    };
                },
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    public static function resolveWhatsappConfig(array $base): array
    {
        try {
            $default = (new IntegrationAccountRepository())->findDefault('green_api');
        } catch (\Throwable $e) {
            $default = null;
        }
        if ($default !== null) {
            $base['instance_id'] = $default->instanceId;
            $base['token'] = $default->token;
        }
        return $base;
    }

    /** Replica la resolución de mailer del container (smtp vs log). */
    private static function mailer(): MailerInterface
    {
        $mailConfig = (array) Config::get('mail', []);
        return ($mailConfig['driver'] ?? 'log') === 'smtp'
            ? new PhpMailerMailer($mailConfig)
            : new LogMailer();
    }
}
