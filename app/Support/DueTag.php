<?php

namespace App\Support;

final class DueTag
{
    public static function wrap(string $id, ?string $notes): ?string
    {
        $clean = self::strip($notes);
        $prefix = '[[due:'.$id.']]';

        return $clean ? $prefix.' '.$clean : $prefix;
    }

    public static function id(?string $notes): ?string
    {
        if ($notes === null || preg_match('/\[\[due:([a-f0-9-]{36})\]\]/i', $notes, $match) !== 1) {
            return null;
        }

        return strtolower($match[1]);
    }

    public static function strip(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $stripped = trim((string) preg_replace('/\[\[due:[a-f0-9-]{36}\]\]\s*/i', '', $notes));

        return $stripped === '' ? null : $stripped;
    }
}
