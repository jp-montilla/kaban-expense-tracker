<?php

namespace App\Support;

final class CreditTag
{
    /**
     * @return array{slug: string, months: int, monthly: float, charge_id: string}|null
     */
    public static function parse(?string $notes): ?array
    {
        if ($notes === null || preg_match('/\[\[bnpl:([a-z0-9_-]+):(\d+):([0-9.]+):([a-f0-9-]{36})\]\]/i', $notes, $match) !== 1) {
            return null;
        }

        return [
            'slug' => strtolower($match[1]),
            'months' => (int) $match[2],
            'monthly' => (float) $match[3],
            'charge_id' => strtolower($match[4]),
        ];
    }

    public static function wrap(string $slug, int $months, float $monthly, string $chargeId, ?string $notes): ?string
    {
        $clean = self::strip($notes);
        $prefix = '[[bnpl:'.strtolower($slug).':'.$months.':'.number_format($monthly, 2, '.', '').':'.$chargeId.']]';

        return $clean ? $prefix.' '.$clean : $prefix;
    }

    public static function chargeId(?string $notes): ?string
    {
        return self::parse($notes)['charge_id'] ?? null;
    }

    public static function strip(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $stripped = trim((string) preg_replace('/\[\[bnpl:[a-z0-9_-]+:\d+:[0-9.]+:[a-f0-9-]{36}\]\]\s*/i', '', $notes));

        return $stripped === '' ? null : $stripped;
    }
}
