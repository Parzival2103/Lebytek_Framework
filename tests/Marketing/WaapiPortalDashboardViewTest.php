<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

test('waapi dashboard muestra plan y cuota desde account status', function (): void {
    $html = ViewHelper::render('publico/waapi/dashboard', [
        'instance' => [
            'label' => 'Demo ACME',
            'publicId' => '01JINSTANCE00000000000001',
            'status' => 'authorized',
        ],
        'usage' => [
            'messagesSent' => 3,
            'messagesReceived' => 1,
        ],
        'accountStatus' => [
            'requestedAt' => '2026-07-14T12:00:00+00:00',
            'plan' => ['slug' => 'demo', 'name' => 'Demo'],
            'demo' => ['daysRemaining' => 12],
            'usage' => [
                'messagesSentThisMonth' => 12,
                'messagesRemainingThisMonth' => 88,
            ],
        ],
        'error' => null,
        'docsUrl' => 'https://docs.lebytek.com',
    ], '');

    assert_true(str_contains($html, 'Panel cliente'));
    assert_true(str_contains($html, 'Demo'));
    assert_true(str_contains($html, '12'));
    assert_true(str_contains($html, '88'));
    assert_true(str_contains($html, 'authorized'));
    assert_true(str_contains($html, '01JINSTANCE00000000000001'));
});
