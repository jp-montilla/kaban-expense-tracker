<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class Period
{
    public function __construct(
        public string $span,
        public int $year,
        public int $month,
        public ?int $half,
        public ?int $quarter,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $today = CarbonImmutable::today();
        $span = $request->string('span')->toString();

        if (! in_array($span, ['all', 'month', 'quarter', 'range'], true)) {
            $span = 'month';
        }

        $year = max(2000, min(2100, $request->integer('year', $today->year)));
        $month = max(1, min(12, $request->integer('month', $today->month)));
        $half = $request->filled('half') ? $request->integer('half') : null;

        if (! in_array($half, [1, 2], true)) {
            $half = null;
        }

        if ($span === 'all') {
            return new self(
                'all',
                $year,
                $month,
                null,
                null,
                CarbonImmutable::create(2000, 1, 1)->startOfDay(),
                CarbonImmutable::create(2100, 12, 31)->endOfDay(),
            );
        }

        if ($span === 'quarter') {
            $quarter = max(1, min(4, $request->integer('quarter', (int) ceil($today->month / 3))));
            $startMonth = (($quarter - 1) * 3) + 1;
            $start = CarbonImmutable::create($year, $startMonth, 1)->startOfDay();

            return new self('quarter', $year, $startMonth, null, $quarter, $start, $start->addMonths(2)->endOfMonth());
        }

        if ($span === 'range') {
            $from = self::monthStart($request->input('from'), $today->startOfMonth());
            $to = self::monthStart($request->input('to'), $today->startOfMonth());

            if ($to->lt($from)) {
                $to = $from;
            }

            if ($from->diffInMonths($to) > 11) {
                $to = $from->addMonths(11);
            }

            return new self('range', $from->year, $from->month, null, null, $from->startOfDay(), $to->endOfMonth());
        }

        [$start, $end] = Cutoff::range($year, $month, $half);

        return new self('month', $year, $month, $half, null, $start, $end);
    }

    public static function month(int $year, int $month, ?int $half = null): self
    {
        [$start, $end] = Cutoff::range($year, $month, $half);

        return new self('month', $year, $month, $half, null, $start, $end);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function query(): array
    {
        $query = [
            'span' => $this->span,
            'year' => $this->year,
            'month' => $this->month,
        ];

        if ($this->span === 'month' && $this->half) {
            $query['half'] = $this->half;
        }

        if ($this->span === 'quarter') {
            $query['quarter'] = $this->quarter;
        }

        if ($this->span === 'range') {
            $query['from'] = $this->start->format('Y-m');
            $query['to'] = $this->end->format('Y-m');
        }

        return $query;
    }

    public function href(string $base): string
    {
        return $base.'?'.http_build_query($this->query());
    }

    public function label(): string
    {
        if ($this->span === 'all') {
            return 'All';
        }

        if ($this->span === 'quarter') {
            return 'Q'.$this->quarter.' '.$this->year;
        }

        if ($this->span === 'range') {
            return $this->start->format('M Y').' – '.$this->end->format('M Y');
        }

        return Cutoff::label($this->year, $this->month, $this->half);
    }

    public function monthCount(): int
    {
        return ((int) $this->start->startOfMonth()->diffInMonths($this->end->startOfMonth())) + 1;
    }

    public function daysLeft(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $end = $this->end->startOfDay();

        if ($today->gt($end)) {
            return 0;
        }

        $start = $this->start->startOfDay();

        if ($today->lt($start)) {
            return (int) $start->diffInDays($end) + 1;
        }

        return (int) $today->diffInDays($end) + 1;
    }

    public function payOn(?CarbonImmutable $today = null): string
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $start = $this->start->startOfDay();
        $end = $this->end->startOfDay();

        if ($today->betweenIncluded($start, $end)) {
            return $today->toDateString();
        }

        if ($today->gt($end)) {
            return $end->toDateString();
        }

        $on = $start->day(min(15, $end->day));

        if ($on->lt($start)) {
            $on = $start;
        }

        if ($on->gt($end)) {
            $on = $end;
        }

        return $on->toDateString();
    }

    /**
     * @return list<array{year: int, month: int}>
     */
    public function months(): array
    {
        $cursor = $this->start->startOfMonth();
        $last = $this->end->startOfMonth();
        $months = [];

        while ($cursor->lte($last)) {
            $months[] = ['year' => $cursor->year, 'month' => $cursor->month];
            $cursor = $cursor->addMonth();
        }

        return $months;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'span' => $this->span,
            'year' => $this->year,
            'month' => $this->month,
            'half' => $this->half,
            'quarter' => $this->quarter,
            'from' => $this->start->format('Y-m'),
            'to' => $this->end->format('Y-m'),
            'label' => $this->label(),
            'days_left' => $this->daysLeft(),
            'days_in_month' => $this->start->daysInMonth,
            'month_count' => $this->monthCount(),
        ];
    }

    private static function monthStart(mixed $value, CarbonImmutable $fallback): CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return $fallback;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
                $parsed = CarbonImmutable::createFromFormat('Y-m-d', $value.'-01');

                return $parsed ? $parsed->startOfMonth() : $fallback;
            }

            return CarbonImmutable::parse($value)->startOfMonth();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
