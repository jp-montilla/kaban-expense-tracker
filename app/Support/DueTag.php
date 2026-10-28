<?php

namespace App\Support;

final class DueTag
{
    public static function wrap(string|array $id, ?string $notes): ?string
    {
        $clean = self::strip($notes);
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => strtolower((string) $value),
            (array) $id,
        ))));

        if ($ids === []) {
            return $clean;
        }

        $prefix = implode(' ', array_map(static fn (string $value): string => '[[due:'.$value.']]', $ids));

        return $clean ? $prefix.' '.$clean : $prefix;
    }

    /**
     * @return list<string>
     */
    public static function ids(?string $notes): array
    {
        if ($notes === null || preg_match_all('/\[\[due:([a-f0-9-]{36})\]\]/i', $notes, $matches) === 0) {
            return [];
        }

        return array_map('strtolower', $matches[1]);
    }

    public static function id(?string $notes): ?string
    {
        return self::ids($notes)[0] ?? null;
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
