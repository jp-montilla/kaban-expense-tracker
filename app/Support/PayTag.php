<?php

namespace App\Support;

final class PayTag
{
    public static function wrap(?string $slug, ?string $notes): ?string
    {
        $clean = self::strip($notes);

        if ($slug === null || $slug === '') {
            return $clean;
        }

        $prefix = '[[pay:'.strtolower($slug).']]';

        return $clean ? $prefix.' '.$clean : $prefix;
    }

    public static function slug(?string $notes): ?string
    {
        if ($notes === null || preg_match('/\[\[pay:([a-z0-9_-]+)\]\]/i', $notes, $match) !== 1) {
            return null;
        }

        return strtolower($match[1]);
    }

    public static function strip(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $stripped = trim((string) preg_replace('/\[\[pay:[a-z0-9_-]+\]\]\s*/i', '', $notes));

        return $stripped === '' ? null : $stripped;
    }
}
