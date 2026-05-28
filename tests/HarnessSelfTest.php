<?php

declare(strict_types=1);

test('harness: assert_same works', function (): void {
    assert_same(2, 1 + 1);
});

test('harness: assert_throws catches the expected type', function (): void {
    assert_throws(\InvalidArgumentException::class, function (): void {
        throw new \InvalidArgumentException('boom');
    });
});
