<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonImmutable;

class DueWhen
{
    public const KEY = 'due_when';

    public const FIRST = 'first';

    public const SECOND = 'second';

    public const EXACT = 'exact';

    /** @var array{bills: array<string, array{mode: string, day: int|null}>, loans: array<string, array{mode: string, day: int|null}>}|null */
    private ?array $stash = null;

    /**
     * @return array{mode: string|null, day: int|null}
     */
    public function for(string $kind, int|string $id): array
    {
        return $this->normalize($this->all()[$kind][(string) $id] ?? null);
    }

    /**
     * @return array{mode: string|null, day: int|null, label: string|null, half: int|null}
     */
    public function decorate(string $kind, int|string $id): array
    {
        $due = $this->for($kind, $id);

        return [
            ...$due,
            'label' => $this->label($due),
            'half' => $this->half($due),
        ];
    }

    public function put(string $kind, int|string $id, ?string $mode, mixed $day = null): void
    {
        $all = $this->all();
        $normalized = $this->normalize(['mode' => $mode, 'day' => $day]);

        if ($normalized['mode'] === null) {
            unset($all[$kind][(string) $id]);
        } else {
            $all[$kind][(string) $id] = $normalized;
        }

        Setting::putValue(self::KEY, json_encode($all, JSON_THROW_ON_ERROR));
        $this->stash = $all;
    }

    /**
     * @param  array{mode?: string|null, day?: int|null}  $due
     */
    public function matchesHalf(array $due, ?int $half): bool
    {
        if ($half === null) {
            return true;
        }

        $bucket = $this->half($due);

        return $bucket === null || $bucket === $half;
    }

    /**
     * Calendar date this bill or loan covers in a month.
     *
     * @param  array{mode?: string|null, day?: int|null}  $due
     */
    public function dateIn(int $year, int $month, array $due = []): string
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $last = $start->endOfMonth()->day;
        $day = match ($due['mode'] ?? null) {
            self::EXACT => min((int) ($due['day'] ?? 15), $last),
            self::SECOND => $last,
            default => min(15, $last),
        };

        return $start->day($day)->toDateString();
    }

    public function matchesDate(?string $iso, ?int $half): bool
    {
        if ($half === null || $iso === null || $iso === '') {
            return true;
        }

        $day = (int) substr($iso, 8, 2);

        return $this->matchesHalf(['mode' => self::EXACT, 'day' => $day], $half);
    }

    /**
     * @param  array{mode?: string|null, day?: int|null}  $due
     */
    public function half(array $due): ?int
    {
        return match ($due['mode'] ?? null) {
            self::FIRST => 1,
            self::SECOND => 2,
            self::EXACT => (($due['day'] ?? 15) <= 15 ? 1 : 2),
            default => null,
        };
    }

    /**
     * @param  array{mode?: string|null, day?: int|null}  $due
     */
    public function label(array $due): ?string
    {
        return match ($due['mode'] ?? null) {
            self::FIRST => '1–15',
            self::SECOND => '16–end',
            self::EXACT => isset($due['day']) ? $this->ordinal((int) $due['day']) : null,
            default => null,
        };
    }

    /**
     * @return array{mode: string|null, day: int|null}
     */
    public function normalize(mixed $row): array
    {
        $row = is_array($row) ? $row : [];
        $mode = $row['mode'] ?? null;

        if (! in_array($mode, [self::FIRST, self::SECOND, self::EXACT], true)) {
            return ['mode' => null, 'day' => null];
        }

        $day = isset($row['day']) && is_numeric($row['day']) ? (int) $row['day'] : null;
        $day = $day !== null ? max(1, min(28, $day)) : null;

        if ($mode === self::EXACT && $day === null) {
            return ['mode' => null, 'day' => null];
        }

        return [
            'mode' => $mode,
            'day' => $mode === self::EXACT ? $day : null,
        ];
    }

    /**
     * @return array{bills: array<string, array{mode: string, day: int|null}>, loans: array<string, array{mode: string, day: int|null}>}
     */
    public function all(): array
    {
        if ($this->stash !== null) {
            return $this->stash;
        }

        $raw = json_decode((string) Setting::getValue(self::KEY, '{}'), true);
        $raw = is_array($raw) ? $raw : [];

        $this->stash = [
            'bills' => $this->normalizeGroup($raw['bills'] ?? []),
            'loans' => $this->normalizeGroup($raw['loans'] ?? []),
        ];

        return $this->stash;
    }

    /**
     * @return array<string, array{mode: string, day: int|null}>
     */
    private function normalizeGroup(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $clean = [];

        foreach ($rows as $id => $row) {
            $normalized = $this->normalize($row);

            if ($normalized['mode'] === null) {
                continue;
            }

            $clean[(string) $id] = $normalized;
        }

        return $clean;
    }

    private function ordinal(int $day): string
    {
        $mod = $day % 100;

        if ($mod >= 11 && $mod <= 13) {
            return $day.'th';
        }

        return $day.match ($day % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
