<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\EnvLoader;

$bias = strtolower((string) EnvLoader::get('LANDING_VARIANT', 'v1'));

return [
    'cookie_ttl_days' => 30,
    'cookie_vid_name' => 'lb_vid',
    'cookie_var_name' => 'lb_var',
    'cookie_preview_name' => 'lb_preview',
    'preview_cookie_ttl_seconds' => 3600,
    'score_window_days' => 14,
    'w_eng' => 0.35,
    'w_conv' => 0.65,
    'min_sessions' => 50,
    'collect_max_per_hour' => 120,
    'collect_max_body_bytes' => 4096,
    'collect_require_origin' => false,
    'heartbeat_seconds' => 15,
    'fallback_slug' => 'v2',
    'proposal_min_delta' => 0.05,
    'min_explore_weight' => 0.05,
    'retention_days' => 90,
    'persist_heartbeat_events' => false, // Anti-deuda §Q — heartbeat updates session only
    /** @return array<string,float> Bootstrap only — used by seedMissing(); does not select traffic. */
    'seed_weight_defaults' => static function () use ($bias): array {
        if ($bias === 'v2') {
            return ['v1' => 0.3, 'v2' => 0.7];
        }

        return ['v1' => 0.7, 'v2' => 0.3];
    },
];
