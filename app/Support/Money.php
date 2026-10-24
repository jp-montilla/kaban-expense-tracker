<?php

namespace App\Support;

final class Money
{
    public static function cents(int|float|string|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    public static function pesos(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
