<?php
// tests/Marketing/CollectLandingMetricsUseCaseTest.php
declare(strict_types=1);

use App\Application\Marketing\CollectLandingMetricsUseCase;
use App\Domain\Marketing\Contracts\CollectRateLimiterInterface;
use App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface;
use App\Domain\Marketing\LandingVariantRegistry;

final class FakeMetricsRepo implements LandingMetricsRepositoryInterface
{
    /** @var array<string, array<string,mixed>> */
    public array $sessions = [];

    /** @var list<array<string,mixed>> */
    public array $events = [];

    private int $nextId = 1;

    /** @return array<string, mixed>|null */
    public function findSessionByPublicId(string $publicId): ?array
    {
        return $this->sessions[$publicId] ?? null;
    }

    /** @param array{public_id:string,visitor_id:string,variant_slug:string,is_preview:bool} $data */
    public function ensureSession(array $data): int
    {
        $existing = $this->sessions[$data['public_id']] ?? null;
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $id = $this->nextId++;
        $this->sessions[$data['public_id']] = [
            'id'             => $id,
            'public_id'      => $data['public_id'],
            'visitor_id'     => $data['visitor_id'],
            'variant_slug'   => $data['variant_slug'],
            'is_preview'     => $data['is_preview'] ? 1 : 0,
            'duration_ms'    => 0,
            'max_scroll_pct' => 0,
            'exit_section'   => null,
        ];

        return $id;
    }

    public function updateSessionMetrics(string $publicId, int $durationMs, int $maxScrollPct, ?string $exitSection): void
    {
        if (!isset($this->sessions[$publicId])) {
            return;
        }
        $this->sessions[$publicId]['duration_ms']    = $durationMs;
        $this->sessions[$publicId]['max_scroll_pct'] = $maxScrollPct;
        $this->sessions[$publicId]['exit_section']   = $exitSection;
    }

    /**
     * @param array{
     *   session_id:?int,visitor_id:string,variant_slug:string,event_type:string,
     *   meta:?array<string, mixed>,is_preview:bool
     * } $data
     */
    public function insertEvent(array $data): void
    {
        $this->events[] = $data;
    }

    public function insertLeadSubmitEvent(string $visitorId, string $variantSlug): void
    {
        $this->events[] = [
            'session_id'   => null,
            'visitor_id'   => $visitorId,
            'variant_slug' => $variantSlug,
            'event_type'   => 'lead_submit',
            'meta'         => null,
            'is_preview'   => false,
        ];
    }

    /** @return list<array{variant_slug:string,sessions:int,avg_scroll:float,avg_duration_ms:float,leads:int,top_exit_section:?string,sections_seen_avg:float}> */
    public function aggregateForScore(int $windowDays): array
    {
        return [];
    }

    /** @return array{sessions:int,events:int} */
    public function purgeOlderThan(\DateTimeImmutable $cutoff): array
    {
        $eventsDeleted = 0;
        $remainingEvents = [];
        foreach ($this->events as $event) {
            $createdAt = isset($event['created_at'])
                ? new \DateTimeImmutable((string) $event['created_at'])
                : new \DateTimeImmutable();
            if ($createdAt < $cutoff) {
                ++$eventsDeleted;
            } else {
                $remainingEvents[] = $event;
            }
        }
        $this->events = $remainingEvents;

        $sessionsDeleted = 0;
        $remainingSessions = [];
        foreach ($this->sessions as $key => $session) {
            $lastSeen = isset($session['last_seen_at'])
                ? new \DateTimeImmutable((string) $session['last_seen_at'])
                : new \DateTimeImmutable();
            if ($lastSeen < $cutoff) {
                ++$sessionsDeleted;
            } else {
                $remainingSessions[$key] = $session;
            }
        }
        $this->sessions = $remainingSessions;

        return ['sessions' => $sessionsDeleted, 'events' => $eventsDeleted];
    }

    public function seedOldAndNew(): void
    {
        $now = new \DateTimeImmutable();
        $old = $now->modify('-100 days')->format('Y-m-d H:i:s');
        $recent = $now->modify('-10 days')->format('Y-m-d H:i:s');

        $oldId = $this->nextId++;
        $this->sessions['old-session'] = [
            'id'             => $oldId,
            'public_id'      => '11111111-1111-4111-8111-111111111111',
            'visitor_id'     => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'variant_slug'   => 'v1',
            'is_preview'     => 0,
            'duration_ms'    => 0,
            'max_scroll_pct' => 0,
            'exit_section'   => null,
            'last_seen_at'   => $old,
        ];

        $newId = $this->nextId++;
        $this->sessions['new-session'] = [
            'id'             => $newId,
            'public_id'      => '22222222-2222-4222-8222-222222222222',
            'visitor_id'     => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'variant_slug'   => 'v2',
            'is_preview'     => 0,
            'duration_ms'    => 0,
            'max_scroll_pct' => 0,
            'exit_section'   => null,
            'last_seen_at'   => $recent,
        ];

        $this->events[] = [
            'session_id'   => $oldId,
            'visitor_id'   => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'variant_slug' => 'v1',
            'event_type'   => 'pageview',
            'meta'         => null,
            'is_preview'   => false,
            'created_at'   => $old,
        ];

        $this->events[] = [
            'session_id'   => $newId,
            'visitor_id'   => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'variant_slug' => 'v2',
            'event_type'   => 'pageview',
            'meta'         => null,
            'is_preview'   => false,
            'created_at'   => $recent,
        ];
    }

    public function countNewEvents(): int
    {
        $cutoff = (new \DateTimeImmutable())->modify('-90 days');
        $count = 0;
        foreach ($this->events as $event) {
            if (!isset($event['created_at'])) {
                continue;
            }
            if (new \DateTimeImmutable((string) $event['created_at']) >= $cutoff) {
                ++$count;
            }
        }

        return $count;
    }

    public function sessionDurationMs(string $publicId): int
    {
        return (int) ($this->sessions[$publicId]['duration_ms'] ?? 0);
    }
}

final class FakeCollectRateLimiter implements CollectRateLimiterInterface
{
    public function __construct(private readonly bool $allow) {}

    public function allow(string $key): bool
    {
        return $this->allow;
    }
}

test('collect rechaza event_type desconocido', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'event_type' => 'hack',
    ]);
    assert_same(false, $res['ok']);
});

test('collect acepta pageview y crea sesion', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v2',
        'session_public_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        'event_type' => 'pageview',
    ]);
    assert_same(true, $res['ok']);
    assert_same(1, count($fake->events));
});

test('collect prefiera cookie lb_vid sobre body spoof', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $cookieVid = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
        'event_type' => 'pageview',
    ], $cookieVid);
    assert_same(true, $res['ok']);
    assert_same($cookieVid, $fake->events[0]['visitor_id']);
});

test('collect rechaza variant_slug desconocido', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'nope',
        'session_public_id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
        'event_type' => 'pageview',
    ]);
    assert_same(false, $res['ok']);
});

test('collect is_preview viene de cookie lb_preview no del body', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v2',
        'session_public_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
        'event_type' => 'pageview',
        'is_preview' => '0', // spoof attempt
    ], null, 'v2');
    assert_same(true, $res['ok']);
    assert_same(1, (int) $fake->events[0]['is_preview']);

    $res2 = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => '99999999-9999-4999-8999-999999999999',
        'event_type' => 'pageview',
        'is_preview' => '1', // spoof without cookie
    ], null, null);
    assert_same(true, $res2['ok']);
    assert_same(0, (int) $fake->events[1]['is_preview']);
});

test('collect rechaza lead_submit desde cliente', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'event_type' => 'lead_submit',
    ]);
    assert_same(false, $res['ok']);
});

test('collect heartbeat no inserta event row (session only)', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $base = [
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    ];
    $uc->handle($base + ['event_type' => 'pageview']);
    $before = count($fake->events);
    $uc->handle($base + ['event_type' => 'heartbeat', 'duration_ms' => 15000, 'max_scroll_pct' => 40]);
    $uc->handle($base + ['event_type' => 'heartbeat', 'duration_ms' => 30000, 'max_scroll_pct' => 50]);
    assert_same($before, count($fake->events), 'heartbeat no flood events');
    assert_true($fake->sessionDurationMs('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa') >= 30000, 'actualiza sesion');
});

test('collect prefiere cookie lb_var sobre body variant_slug', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v2',
        'session_public_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        'event_type' => 'pageview',
    ], null, null, 'v1');
    assert_same(true, $res['ok']);
    assert_same('v1', $fake->events[0]['variant_slug']);
});

test('collect rechaza UUID no-v4', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-1111-1111-111111111111', // version nibble ≠ 4
        'variant_slug' => 'v1',
        'session_public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'event_type' => 'pageview',
    ]);
    assert_same(false, $res['ok']);
});

test('collect rechaza cuando el rate limiter deniega', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(false),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'event_type' => 'pageview',
    ]);
    assert_same(false, $res['ok']);
    assert_same(0, count($fake->events), 'rate limited no persiste');
});

test('collect meta desconocido se descarta y no rompe la request', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'event_type' => 'section_view',
        'meta' => ['section' => 'hero', 'password' => 'nope', 'email' => 'x@y.com'],
    ]);
    assert_same(true, $res['ok']);
    $meta = $fake->events[0]['meta'];
    assert_same(['section' => 'hero'], $meta);
});

test('collect acepta meta como JSON string (transporte real form-urlencoded)', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    // El JS envía `meta` como string JSON.stringify(...) vía sendBeacon/fetch
    // form-urlencoded; $_POST lo entrega como string plano, no como array.
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'event_type' => 'scroll_depth',
        'meta' => json_encode(['pct' => 50, 'unexpected' => 'x']),
    ]);
    assert_same(true, $res['ok']);
    assert_same(['pct' => 50], $fake->events[0]['meta']);
});
