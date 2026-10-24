<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class Cutoff
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function range(int $year, int $month, ?int $half = null): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth();

        if ($half === 1) {
            return [$start, $start->day(15)->endOfDay()];
        }

        if ($half === 2) {
            return [$start->day(16)->startOfDay(), $end];
        }

        return [$start, $end];
    }

    public static function daysLeft(int $year, int $month, ?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth()->startOfDay();

        if ($today->lt($start)) {
            return $start->daysInMonth;
        }

        if ($today->gt($end)) {
            return 0;
        }

        return (int) $today->diffInDays($end) + 1;
    }

    public static function label(int $year, int $month, ?int $half = null): string
    {
        $name = CarbonImmutable::create($year, $month, 1)->format('F Y');

        if ($half === 1) {
            return $name.' · 1–15';
        }

        if ($half === 2) {
            $last = CarbonImmutable::create($year, $month, 1)->endOfMonth()->day;

            return $name.' · 16–'.$last;
        }

        return $name;
    }
}
