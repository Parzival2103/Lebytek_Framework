<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Application\Services\ConfiguracionService;
use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Constants\AppConstants;
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;

/*
|--------------------------------------------------------------------------
| PwaController — Manifest web dinámico (icono y colores desde ajustes)
|--------------------------------------------------------------------------
*/

final class PwaController extends BaseController
{
    public function __construct(
        private readonly ConfiguracionService $configuracionService
    ) {}

    public function manifest(Request $request): Response
    {
        $cfg = $this->configuracionService->all();

        $name = trim((string) ($cfg[AppConstants::CONFIG_EMPRESA_NOMBRE] ?? '')) ?: 'Sistema';
        $short = mb_strlen($name) > 12 ? mb_substr($name, 0, 12) : $name;

        $logo = trim((string) ($cfg[AppConstants::CONFIG_EMPRESA_LOGO] ?? ''));
        $primary = trim((string) ($cfg[AppConstants::CONFIG_PRIMARY_COLOR] ?? '#0d6efd'));
        if ($primary === '' || !str_starts_with($primary, '#')) {
            $primary = '#0d6efd';
        }
        $bodyBg = trim((string) ($cfg['body_color'] ?? ''));
        if ($bodyBg === '' || !str_starts_with($bodyBg, '#')) {
            $bodyBg = '#f0f2f5';
        }

        $startUrl = rtrim(ViewHelper::url(''), '/') . '/';
        $scope    = $startUrl;

        $icons = $this->buildIcons($logo);

        $manifest = [
            'id'                 => $startUrl,
            'name'               => $name,
            'short_name'         => $short,
            'description'        => $name . ' — Panel administrativo',
            'start_url'          => $startUrl,
            'scope'              => $scope,
            'display'            => 'standalone',
            'orientation'        => 'any',
            'theme_color'        => $primary,
            'background_color'   => $bodyBg,
            'icons'              => $icons,
            'categories'         => ['business', 'productivity'],
            'lang'               => 'es',
            'dir'                => 'ltr',
        ];

        $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new Response($json, 200, [
            'Content-Type'  => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * @return list<array{src: string, sizes: string, type: string, purpose: string}>
     */
    private function buildIcons(string $logoUrl): array
    {
        if ($logoUrl !== '' && filter_var($logoUrl, FILTER_VALIDATE_URL)) {
            $type = $this->guessImageType($logoUrl);

            return [
                [
                    'src'     => $logoUrl,
                    'sizes'   => '192x192',
                    'type'    => $type,
                    'purpose' => 'any',
                ],
                [
                    'src'     => $logoUrl,
                    'sizes'   => '512x512',
                    'type'    => $type,
                    'purpose' => 'any',
                ],
                [
                    'src'     => $logoUrl,
                    'sizes'   => '512x512',
                    'type'    => $type,
                    'purpose' => 'maskable',
                ],
            ];
        }

        $default = ViewHelper::asset('icons/app-icon.svg');

        return [
            [
                'src'     => $default,
                'sizes'   => 'any',
                'type'    => 'image/svg+xml',
                'purpose' => 'any',
            ],
            [
                'src'     => $default,
                'sizes'   => '512x512',
                'type'    => 'image/svg+xml',
                'purpose' => 'maskable',
            ],
        ];
    }

    private function guessImageType(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'image/png',
        };
    }
}
