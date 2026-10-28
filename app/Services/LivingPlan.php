<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Money;
use App\Support\Ownership;
use Carbon\CarbonImmutable;

class LivingPlan
{
    public const KEY = 'living_plan_history';

    /** @var list<array{effective_on: string, amount: float}>|null */
    private ?array $stash = null;

    public function for(int $year, int $month): float
    {
        $target = CarbonImmutable::create($year, $month, 1)->toDateString();
        $amount = $this->fallback();

        foreach ($this->all() as $row) {
            if ($row['effective_on'] <= $target) {
                $amount = $row['amount'];
            }
        }

        return $amount;
    }

    public function cents(int $year, int $month): int
    {
        return Money::cents($this->for($year, $month));
    }

    /**
     * @return list<array{effective_on: string, amount: float}>
     */
    public function all(): array
    {
        if ($this->stash !== null) {
            return $this->stash;
        }

        $raw = json_decode((string) Setting::getValue(self::KEY, '[]'), true);
        $rows = [];

        foreach (is_array($raw) ? $raw : [] as $row) {
            if (! is_array($row) || ! isset($row['effective_on'], $row['amount'])) {
                continue;
            }

            try {
                $date = CarbonImmutable::parse((string) $row['effective_on']);
            } catch (\Throwable) {
                continue;
            }

            $amount = round((float) $row['amount'], 2);

            if ($amount < 0) {
                continue;
            }

            $rows[] = [
                'effective_on' => $date->startOfMonth()->toDateString(),
                'amount' => $amount,
            ];
        }

        usort($rows, fn (array $left, array $right): int => $left['effective_on'] <=> $right['effective_on']);

        $this->stash = $rows;

        return $this->stash;
    }

    public function put(float $amount, CarbonImmutable $from): void
    {
        $amount = round($amount, 2);
        $from = $from->startOfMonth();
        $rows = $this->all();
        $previous = $this->fallback();

        if ($rows === [] && abs($previous - $amount) >= 0.01) {
            $rows[] = [
                'effective_on' => '2000-01-01',
                'amount' => $previous,
            ];
        }

        $stamp = $from->toDateString();
        $rows = array_values(array_filter(
            $rows,
            fn (array $row): bool => $row['effective_on'] !== $stamp,
        ));
        $rows[] = [
            'effective_on' => $stamp,
            'amount' => $amount,
        ];

        usort($rows, fn (array $left, array $right): int => $left['effective_on'] <=> $right['effective_on']);

        Setting::putValue(self::KEY, json_encode($rows, JSON_THROW_ON_ERROR));
        $this->stash = $rows;
        $this->writeCurrent($amount);
    }

    public function fallback(): float
    {
        $living = (float) (auth()->user()?->living_plan ?? 0);

        if ($living > 0) {
            return $living;
        }

        return (float) (Setting::getValue(Setting::LIVING_PLAN, '17000') ?? 17000);
    }

    private function writeCurrent(float $amount): void
    {
        if (Ownership::hasLivingPlan() && auth()->user()) {
            auth()->user()->update(['living_plan' => $amount]);

            return;
        }

        Setting::putValue(Setting::LIVING_PLAN, (string) $amount);
    }
}
