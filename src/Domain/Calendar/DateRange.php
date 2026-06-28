<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Calendar;

use DateTimeImmutable;

final class DateRange
{
    public function __construct(
        private readonly DateTimeImmutable $from,
        private readonly DateTimeImmutable $to,
    ) {}

    public function from(): DateTimeImmutable { return $this->from; }
    public function to(): DateTimeImmutable { return $this->to; }

    public static function forMonth(int $year, int $month): self
    {
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $last  = $first->modify('last day of this month')->setTime(23, 59, 59);
        return new self($first, $last);
    }

    public static function forDay(DateTimeImmutable $day): self
    {
        return new self($day->setTime(0, 0, 0), $day->setTime(23, 59, 59));
    }

    public static function forWeek(DateTimeImmutable $anchor): self
    {
        $dow    = (int) $anchor->format('N'); // 1 = lunes ... 7 = domingo
        $monday = $anchor->modify('-' . ($dow - 1) . ' days')->setTime(0, 0, 0);
        $sunday = $monday->modify('+6 days')->setTime(23, 59, 59);
        return new self($monday, $sunday);
    }

    public static function fromStrings(string $from, string $to): self
    {
        $f = (new DateTimeImmutable($from))->setTime(0, 0, 0);
        $t = (new DateTimeImmutable($to))->setTime(23, 59, 59);
        return new self($f, $t);
    }
}
