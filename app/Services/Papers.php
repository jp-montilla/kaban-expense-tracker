<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class Papers
{
    public const KEY = 'documents';

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $rows = json_decode((string) Setting::getValue(self::KEY, '[]'), true);

        if (! is_array($rows)) {
            return [];
        }

        $today = CarbonImmutable::today();
        $rows = array_values(array_filter(
            $rows,
            fn (mixed $row): bool => is_array($row) && empty($row['deleted_at']),
        ));

        return array_values(array_map(function (mixed $row) use ($today): array {
            $row = is_array($row) ? $row : [];
            $expires = isset($row['expires_on']) && is_string($row['expires_on']) && $row['expires_on'] !== ''
                ? CarbonImmutable::parse($row['expires_on'])
                : null;
            $daysLeft = $expires?->startOfDay()->diffInDays($today, false);
            $daysLeft = $daysLeft === null ? null : (int) -$daysLeft;

            return [
                'id' => (string) ($row['id'] ?? Str::uuid()),
                'kind' => (string) ($row['kind'] ?? 'license'),
                'name' => (string) ($row['name'] ?? 'Document'),
                'issued_on' => $row['issued_on'] ?? null,
                'expires_on' => $expires?->toDateString(),
                'renewal_months' => isset($row['renewal_months']) ? (int) $row['renewal_months'] : null,
                'notes' => $row['notes'] ?? null,
                'days_left' => $daysLeft,
                'status' => $this->status($daysLeft),
            ];
        }, $rows));
    }

    /**
     * @param  array{kind: string, name: string, issued_on?: ?string, expires_on?: ?string, renewal_months?: ?int, notes?: ?string}  $data
     */
    public function add(array $data): void
    {
        $rows = json_decode((string) Setting::getValue(self::KEY, '[]'), true);
        $rows = is_array($rows) ? $rows : [];
        $rows[] = [
            'id' => (string) Str::uuid(),
            ...$data,
        ];
        Setting::putValue(self::KEY, json_encode(array_values($rows), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array{kind?: string, name?: string, issued_on?: ?string, expires_on?: ?string, renewal_months?: ?int, notes?: ?string}  $data
     */
    public function update(string $id, array $data): void
    {
        $rows = json_decode((string) Setting::getValue(self::KEY, '[]'), true);
        $rows = is_array($rows) ? $rows : [];

        foreach ($rows as $index => $row) {
            if (! is_array($row) || ($row['id'] ?? null) !== $id) {
                continue;
            }

            $rows[$index] = [...$row, ...$data, 'id' => $id];
        }

        Setting::putValue(self::KEY, json_encode(array_values($rows), JSON_THROW_ON_ERROR));
    }

    public function remove(string $id): void
    {
        $rows = json_decode((string) Setting::getValue(self::KEY, '[]'), true);
        $rows = is_array($rows) ? $rows : [];

        foreach ($rows as $index => $row) {
            if (! is_array($row) || ($row['id'] ?? null) !== $id) {
                continue;
            }

            $rows[$index]['deleted_at'] = now()->toIso8601String();
        }

        Setting::putValue(self::KEY, json_encode(array_values($rows), JSON_THROW_ON_ERROR));
    }

    private function status(?int $daysLeft): string
    {
        if ($daysLeft === null) {
            return 'open';
        }

        if ($daysLeft < 0) {
            return 'overdue';
        }

        if ($daysLeft <= 30) {
            return 'soon';
        }

        return 'ok';
    }
}
