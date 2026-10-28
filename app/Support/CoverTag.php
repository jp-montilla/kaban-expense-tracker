<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class CoverTag
{
    public static function wrap(?string $iso, ?string $notes): ?string
    {
        $clean = self::strip($notes);

        if ($iso === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1) {
            return $clean;
        }

        $prefix = '[[cover:'.$iso.']]';

        return $clean ? $prefix.' '.$clean : $prefix;
    }

    public static function iso(?string $notes): ?string
    {
        if ($notes === null || preg_match('/\[\[cover:(\d{4}-\d{2}-\d{2})\]\]/', $notes, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    public static function on(?string $notes, CarbonImmutable|string|null $fallback = null): ?CarbonImmutable
    {
        $iso = self::iso($notes);

        if ($iso !== null) {
            return CarbonImmutable::parse($iso)->startOfDay();
        }

        if ($fallback === null) {
            return null;
        }

        return $fallback instanceof CarbonImmutable
            ? $fallback->startOfDay()
            : CarbonImmutable::parse($fallback)->startOfDay();
    }

    public static function inWindow(
        ?string $notes,
        CarbonImmutable|string|null $occurredOn,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): bool {
        $on = self::on($notes, $occurredOn);

        return $on !== null && $on->betweenIncluded($start->startOfDay(), $end->startOfDay());
    }

    public static function strip(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $stripped = trim((string) preg_replace('/\[\[cover:\d{4}-\d{2}-\d{2}\]\]\s*/', '', $notes));

        return $stripped === '' ? null : $stripped;
    }
}
