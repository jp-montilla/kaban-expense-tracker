<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Wallets
{
    public const KEY = 'payment_methods';

    public const GROUPS = ['cash', 'wallet', 'credit', 'bank'];

    public const TERMS = [1, 3, 6, 9, 12, 18, 24, 36];

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $rows = array_values(array_filter(
            $this->stash(),
            fn (array $row): bool => ($row['deleted_at'] ?? null) === null,
        ));

        usort($rows, fn (array $a, array $b): int => $this->groupOrder($a['group']) <=> $this->groupOrder($b['group']));

        return array_values(array_map(fn (array $row): array => $this->public($row), $rows));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paySources(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $row): bool => $row['group'] !== 'credit',
        ));
    }

    /**
     * @return list<array{id: string, name: string, slug: string, due_day: int|null}>
     */
    public function credits(): array
    {
        return array_values(array_map(
            fn (array $row): array => [
                'id' => $row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'due_day' => $row['due_day'],
            ],
            array_filter($this->all(), fn (array $row): bool => $row['group'] === 'credit'),
        ));
    }

    /**
     * @param  array<string, mixed>  $wallet
     */
    public function firstDueOn(array $wallet, string $on): string
    {
        $occurred = CarbonImmutable::parse($on)->startOfDay();
        $month = $occurred->startOfMonth();
        $day = $this->dueDay($wallet['due_day'] ?? ($wallet['slug'] === 'lazpay' ? 16 : 15));
        $thisBill = $month->day(min($day, $month->endOfMonth()->day));

        if ($occurred->lessThan($thisBill)) {
            return $thisBill->toDateString();
        }

        $next = $month->addMonth();

        return $next->day(min($day, $next->endOfMonth()->day))->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    public function add(string $name, string $group = 'wallet'): array
    {
        $group = $this->normalizeGroup($group, $name, Str::slug($name));

        if ($group === 'cash') {
            throw new InvalidArgumentException('Cash is already there.');
        }

        $rows = $this->stash();
        $active = array_values(array_filter($rows, fn (array $row): bool => ($row['deleted_at'] ?? null) === null));
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'wallet';
        $slug = $base;
        $n = 2;

        while (collect($active)->contains(fn (array $row): bool => $row['slug'] === $slug)) {
            $slug = $base.'-'.$n;
            $n++;
        }

        $row = $this->blank($name, $slug, $group);
        $rows[] = $row;
        $this->save($rows);

        return $this->public($row);
    }

    /**
     * @param  array{name?: string, due_day?: int|null, plans?: list<array{id?: string, months: int, interest: float|int|string}>}  $data
     */
    public function update(string $id, array $data): void
    {
        $rows = $this->stash();
        $found = false;

        foreach ($rows as $index => $row) {
            if ($row['id'] !== $id || ($row['deleted_at'] ?? null) !== null) {
                continue;
            }

            $found = true;

            if ($row['locked']) {
                throw new InvalidArgumentException('Cash cannot be edited.');
            }

            if (array_key_exists('name', $data) && is_string($data['name']) && $data['name'] !== '') {
                $rows[$index]['name'] = $data['name'];
            }

            if ($row['group'] === 'credit') {
                if (array_key_exists('due_day', $data)) {
                    $rows[$index]['due_day'] = $this->dueDay($data['due_day'] ?? null);
                }

                if (array_key_exists('plans', $data) && is_array($data['plans'])) {
                    $rows[$index]['plans'] = $this->normalizePlans($data['plans']);
                }
            }
        }

        if (! $found) {
            throw new InvalidArgumentException('Payment option not found.');
        }

        $this->save($rows);
    }

    public function remove(string $id): void
    {
        $rows = $this->stash();
        $found = false;

        foreach ($rows as $index => $row) {
            if ($row['id'] !== $id) {
                continue;
            }

            $found = true;

            if ($row['locked']) {
                throw new InvalidArgumentException('Cash cannot be removed.');
            }

            $rows[$index]['deleted_at'] = now()->toIso8601String();
        }

        if (! $found) {
            throw new InvalidArgumentException('Payment option not found.');
        }

        $this->save($rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        return collect($this->all())->first(fn (array $row): bool => $row['slug'] === $slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        return collect($this->all())->first(fn (array $row): bool => $row['id'] === $id);
    }

    /**
     * @param  array{description: string, months?: int, monthly?: float|int|string, amount?: float|int|string, first_due_on?: string}  $data
     * @return array<string, mixed>
     */
    public function addCharge(string $walletId, array $data): array
    {
        $rows = $this->stash();

        foreach ($rows as $index => $row) {
            if ($row['id'] !== $walletId || ($row['deleted_at'] ?? null) !== null) {
                continue;
            }

            if ($row['group'] !== 'credit') {
                throw new InvalidArgumentException('Only credit options take charges.');
            }

            $months = (int) ($data['months'] ?? 1);

            if (! in_array($months, self::TERMS, true)) {
                throw new InvalidArgumentException('Unknown installment length.');
            }

            $monthly = (float) ($data['monthly'] ?? $data['amount'] ?? 0);

            if ($monthly < 0.01) {
                throw new InvalidArgumentException('Monthly pay is missing.');
            }

            $firstDueOn = (string) ($data['first_due_on'] ?? $this->firstDueOn($row, now()->toDateString()));
            $charge = $this->makeCharge((string) $data['description'], $months, $monthly, $firstDueOn);
            $rows[$index]['charges'][] = $charge;
            $this->save($rows);

            return $charge;
        }

        throw new InvalidArgumentException('Payment option not found.');
    }

    public function removeChargeById(string $chargeId): void
    {
        foreach ($this->stash() as $row) {
            foreach ($row['charges'] as $charge) {
                if ($charge['id'] !== $chargeId) {
                    continue;
                }

                $this->removeCharge($row['id'], $chargeId);

                return;
            }
        }
    }

    public function removeCharge(string $walletId, string $chargeId): void
    {
        $rows = $this->stash();

        foreach ($rows as $index => $row) {
            if ($row['id'] !== $walletId) {
                continue;
            }

            foreach ($row['charges'] as $chargeIndex => $charge) {
                if ($charge['id'] !== $chargeId) {
                    continue;
                }

                $paid = collect($charge['dues'])->contains(fn (array $due): bool => ($due['paid_on'] ?? null) !== null);

                if ($paid) {
                    throw new InvalidArgumentException('This charge already has a paid installment.');
                }

                unset($rows[$index]['charges'][$chargeIndex]);
                $rows[$index]['charges'] = array_values($rows[$index]['charges']);
                $this->save($rows);

                return;
            }
        }

        throw new InvalidArgumentException('Charge not found.');
    }

    public function dropUnpaidDue(string $dueId): void
    {
        $rows = $this->stash();

        foreach ($rows as $walletIndex => $row) {
            foreach ($row['charges'] as $chargeIndex => $charge) {
                foreach ($charge['dues'] as $dueIndex => $due) {
                    if ($due['id'] !== $dueId) {
                        continue;
                    }

                    if (($due['paid_on'] ?? null) !== null) {
                        throw new InvalidArgumentException('This installment is already paid.');
                    }

                    unset($rows[$walletIndex]['charges'][$chargeIndex]['dues'][$dueIndex]);
                    $rows[$walletIndex]['charges'][$chargeIndex]['dues'] = array_values(
                        $rows[$walletIndex]['charges'][$chargeIndex]['dues'],
                    );

                    if ($rows[$walletIndex]['charges'][$chargeIndex]['dues'] === []) {
                        unset($rows[$walletIndex]['charges'][$chargeIndex]);
                        $rows[$walletIndex]['charges'] = array_values($rows[$walletIndex]['charges']);
                    }

                    $this->save($rows);

                    return;
                }
            }
        }

        throw new InvalidArgumentException('Installment not found.');
    }

    /**
     * @return array{wallet: array<string, mixed>, charge: array<string, mixed>, due: array<string, mixed>}|null
     */
    public function findDue(string $dueId): ?array
    {
        foreach ($this->stash() as $row) {
            foreach ($row['charges'] as $charge) {
                foreach ($charge['dues'] as $due) {
                    if ($due['id'] !== $dueId) {
                        continue;
                    }

                    return [
                        'wallet' => $this->public($row),
                        'charge' => $charge,
                        'due' => $due,
                    ];
                }
            }
        }

        return null;
    }

    public function markDuePaid(string $dueId, int $transactionId, string $paidOn): void
    {
        $rows = $this->stash();

        foreach ($rows as $walletIndex => $row) {
            foreach ($row['charges'] as $chargeIndex => $charge) {
                foreach ($charge['dues'] as $dueIndex => $due) {
                    if ($due['id'] !== $dueId) {
                        continue;
                    }

                    if (($due['paid_on'] ?? null) !== null) {
                        throw new InvalidArgumentException('This installment is already paid.');
                    }

                    $rows[$walletIndex]['charges'][$chargeIndex]['dues'][$dueIndex]['paid_on'] = $paidOn;
                    $rows[$walletIndex]['charges'][$chargeIndex]['dues'][$dueIndex]['transaction_id'] = $transactionId;
                    $this->save($rows);

                    return;
                }
            }
        }

        throw new InvalidArgumentException('Installment not found.');
    }

    public function updateDuePaidOn(string $dueId, string $paidOn): void
    {
        $rows = $this->stash();

        foreach ($rows as $walletIndex => $row) {
            foreach ($row['charges'] as $chargeIndex => $charge) {
                foreach ($charge['dues'] as $dueIndex => $due) {
                    if ($due['id'] !== $dueId) {
                        continue;
                    }

                    if (($due['paid_on'] ?? null) === null) {
                        throw new InvalidArgumentException('This installment is not paid yet.');
                    }

                    $rows[$walletIndex]['charges'][$chargeIndex]['dues'][$dueIndex]['paid_on'] = $paidOn;
                    $this->save($rows);

                    return;
                }
            }
        }

        throw new InvalidArgumentException('Installment not found.');
    }

    public function unpayDue(string $dueId): void
    {
        $rows = $this->stash();

        foreach ($rows as $walletIndex => $row) {
            foreach ($row['charges'] as $chargeIndex => $charge) {
                foreach ($charge['dues'] as $dueIndex => $due) {
                    if ($due['id'] !== $dueId) {
                        continue;
                    }

                    $rows[$walletIndex]['charges'][$chargeIndex]['dues'][$dueIndex]['paid_on'] = null;
                    $rows[$walletIndex]['charges'][$chargeIndex]['dues'][$dueIndex]['transaction_id'] = null;
                    $this->save($rows);

                    return;
                }
            }
        }
    }

    /**
     * Unpaid dues due on or before $end, plus any unpaid due inside the window.
     *
     * @return list<array<string, mixed>>
     */
    public function duesForPeriod(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $dues = [];

        foreach ($this->stash() as $row) {
            foreach ($row['charges'] as $charge) {
                foreach ($charge['dues'] as $due) {
                    $dueOn = CarbonImmutable::parse($due['due_on'])->startOfDay();
                    $unpaid = ($due['paid_on'] ?? null) === null;
                    $inWindow = $dueOn->betweenIncluded($start->startOfDay(), $end->startOfDay());
                    $overdue = $unpaid && $dueOn->lessThan($start->startOfDay());

                    if (! $inWindow && ! $overdue) {
                        continue;
                    }

                    $dues[] = [
                        'id' => $due['id'],
                        'wallet_id' => $row['id'],
                        'wallet_name' => $row['name'],
                        'wallet_slug' => $row['slug'],
                        'charge_id' => $charge['id'],
                        'description' => $charge['description'],
                        'n' => $due['n'],
                        'months' => $charge['months'],
                        'due_on' => $due['due_on'],
                        'amount' => (float) $due['amount'],
                        'paid_on' => $due['paid_on'],
                        'transaction_id' => $due['transaction_id'] ?? null,
                        'overdue' => $overdue,
                    ];
                }
            }
        }

        $statementKeys = [];

        foreach ($dues as $due) {
            if (! $this->isStatementCharge($due['wallet_slug'], $due['wallet_name'], $due['description'])) {
                continue;
            }

            $statementKeys[$due['wallet_id'].'|'.$due['due_on']] = true;
        }

        $dues = array_values(array_filter($dues, function (array $due) use ($statementKeys): bool {
            $key = $due['wallet_id'].'|'.$due['due_on'];

            if (! isset($statementKeys[$key])) {
                return true;
            }

            return $this->isStatementCharge($due['wallet_slug'], $due['wallet_name'], $due['description']);
        }));

        foreach ($dues as $index => $due) {
            unset($dues[$index]['wallet_slug']);
        }

        usort($dues, function (array $a, array $b): int {
            return [$a['due_on'], $a['wallet_name'], $a['n']] <=> [$b['due_on'], $b['wallet_name'], $b['n']];
        });

        return array_values($dues);
    }

    public function unpaidDueCentsForMonth(int $year, int $month): int
    {
        $cents = 0;

        foreach ($this->computedBillsForMonth($year, $month) as $bill) {
            $cents += Money::cents($bill['monthly_plan']);
        }

        return $cents;
    }

    /**
     * SPay / LazPay bills are the unpaid dues from bill Pay and daily-log purchases charged to credit.
     *
     * @return list<array{id: string, name: string, slug: string, monthly_plan: float}>
     */
    public function computedBillsForMonth(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();
        $grouped = [];
        $credits = collect($this->credits())->keyBy('id');

        foreach ($this->duesForPeriod($start, $end) as $due) {
            if ($due['paid_on'] !== null) {
                continue;
            }

            $id = (string) $due['wallet_id'];
            $credit = $credits->get($id);
            $grouped[$id] ??= [
                'id' => $id,
                'name' => (string) ($credit['name'] ?? $due['wallet_name']),
                'slug' => (string) ($credit['slug'] ?? ''),
                'monthly_plan' => 0.0,
            ];
            $grouped[$id]['monthly_plan'] += (float) $due['amount'];
        }

        return array_values(array_map(
            function (array $row): array {
                $row['monthly_plan'] = round($row['monthly_plan'], 2);

                return $row;
            },
            array_filter($grouped, fn (array $row): bool => $row['monthly_plan'] > 0),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stash(): array
    {
        $raw = json_decode((string) Setting::getValue(self::KEY, '[]'), true);

        if (! is_array($raw) || $raw === []) {
            $rows = $this->defaults();
            $this->save($rows);

            return $rows;
        }

        $dirty = false;
        $rows = array_values(array_map(function (mixed $row) use (&$dirty): array {
            $row = is_array($row) ? $row : [];
            $name = (string) ($row['name'] ?? 'Wallet');
            $slug = (string) ($row['slug'] ?? Str::slug($name));
            $hadGroup = isset($row['group']) && is_string($row['group']);
            $group = $this->normalizeGroup($hadGroup ? (string) $row['group'] : null, $name, $slug);
            $locked = $group === 'cash';
            $hadPlans = is_array($row['plans'] ?? null) && $row['plans'] !== [];

            if (! $hadGroup || (($row['locked'] ?? null) !== $locked) || ($group === 'credit' && ! $hadPlans)) {
                $dirty = true;
            }

            $plans = $group === 'credit' ? $this->normalizePlans($hadPlans ? $row['plans'] : []) : [];
            $charges = $group === 'credit' ? $this->normalizeCharges(is_array($row['charges'] ?? null) ? $row['charges'] : []) : [];

            return [
                'id' => (string) ($row['id'] ?? Str::uuid()),
                'name' => $name,
                'slug' => $slug !== '' ? $slug : 'wallet',
                'group' => $group,
                'locked' => $locked,
                'due_day' => $group === 'credit' ? $this->dueDay($row['due_day'] ?? 15) : null,
                'plans' => $plans,
                'charges' => $charges,
                'deleted_at' => isset($row['deleted_at']) && is_string($row['deleted_at']) ? $row['deleted_at'] : null,
            ];
        }, $raw));

        $ensured = $this->ensureCash($rows);

        if ($ensured !== $rows) {
            $dirty = true;
            $rows = $ensured;
        }

        if ($dirty) {
            $this->save($rows);
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function save(array $rows): void
    {
        Setting::putValue(self::KEY, json_encode(array_values($rows), JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaults(): array
    {
        return [
            $this->blank('Cash', 'cash', 'cash'),
            $this->blank('GCash', 'gcash', 'wallet'),
            $this->blank('Maya', 'maya', 'wallet'),
            $this->blank('SPay', 'spay', 'credit'),
            $this->blank('LazPay', 'lazpay', 'credit'),
            $this->blank('Credit card', 'credit-card', 'credit'),
            $this->blank('Debit', 'debit', 'bank'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blank(string $name, string $slug, string $group): array
    {
        $credit = $group === 'credit';

        return [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'group' => $group,
            'locked' => $group === 'cash',
            'due_day' => $credit ? ($slug === 'lazpay' ? 16 : 15) : null,
            'plans' => $credit ? $this->defaultPlans() : [],
            'charges' => [],
            'deleted_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function public(array $row): array
    {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'group' => $row['group'],
            'locked' => (bool) $row['locked'],
            'due_day' => $row['due_day'],
            'plans' => $row['plans'],
            'charges' => $row['charges'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function ensureCash(array $rows): array
    {
        $hasCash = collect($rows)->contains(
            fn (array $row): bool => $row['group'] === 'cash' && ($row['deleted_at'] ?? null) === null,
        );

        if ($hasCash) {
            return $rows;
        }

        array_unshift($rows, $this->blank('Cash', 'cash', 'cash'));

        return $rows;
    }

    private function normalizeGroup(?string $group, string $name, string $slug): string
    {
        if ($group !== null && in_array($group, self::GROUPS, true)) {
            return $group;
        }

        $haystack = Str::lower($name.' '.$slug);

        if ($slug === 'cash' || $haystack === 'cash') {
            return 'cash';
        }

        if (str_contains($haystack, 'credit') || str_contains($haystack, 'spay') || str_contains($haystack, 'lazpay') || str_contains($haystack, 'later') || preg_match('/\bcc\b/', $haystack) === 1) {
            return 'credit';
        }

        if (str_contains($haystack, 'debit') || str_contains($haystack, 'bank') || str_contains($haystack, 'union')) {
            return 'bank';
        }

        return 'wallet';
    }

    private function groupOrder(string $group): int
    {
        return match ($group) {
            'cash' => 0,
            'wallet' => 1,
            'credit' => 2,
            default => 3,
        };
    }

    private function dueDay(mixed $day): int
    {
        $value = (int) $day;

        return max(1, min(28, $value > 0 ? $value : 15));
    }

    /**
     * SPay / LazPay live under payment options. Their bill is the sum of
     * charges from bill Pay and daily-log purchases, not a Plan bill row.
     */
    public function isCreditAccount(string $slug, string $name = ''): bool
    {
        foreach ($this->all() as $row) {
            if (($row['group'] ?? '') !== 'credit') {
                continue;
            }

            if ($this->isStatementCharge($row['slug'], $row['name'], $slug)) {
                return true;
            }

            if ($name !== '' && $this->isStatementCharge($row['slug'], $row['name'], $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Spaylater / Lazpaylater (and names that start with the credit option) are the month statement.
     * Other charges due that same day are already inside that total.
     */
    private function isStatementCharge(string $slug, string $name, string $description): bool
    {
        $haystack = Str::lower(Str::slug($description));
        $slug = Str::lower($slug);
        $nameSlug = Str::lower(Str::slug($name));

        if ($haystack === '') {
            return false;
        }

        return ($slug !== '' && str_starts_with($haystack, $slug))
            || ($nameSlug !== '' && str_starts_with($haystack, $nameSlug));
    }

    /**
     * @return list<array{id: string, months: int, interest: float}>
     */
    private function defaultPlans(): array
    {
        return [
            ['id' => (string) Str::uuid(), 'months' => 1, 'interest' => 0.0],
            ['id' => (string) Str::uuid(), 'months' => 3, 'interest' => 0.0],
        ];
    }

    /**
     * @param  list<mixed>  $plans
     * @return list<array{id: string, months: int, interest: float}>
     */
    private function normalizePlans(array $plans): array
    {
        $normalized = [];

        foreach ($plans as $plan) {
            if (! is_array($plan)) {
                continue;
            }

            $months = max(1, min(60, (int) ($plan['months'] ?? 0)));
            $interest = max(0, min(100, (float) ($plan['interest'] ?? 0)));
            $normalized[] = [
                'id' => isset($plan['id']) && is_string($plan['id']) && $plan['id'] !== ''
                    ? $plan['id']
                    : (string) Str::uuid(),
                'months' => $months,
                'interest' => round($interest, 2),
            ];
        }

        if ($normalized === []) {
            return $this->defaultPlans();
        }

        usort($normalized, fn (array $a, array $b): int => $a['months'] <=> $b['months']);

        return array_values($normalized);
    }

    /**
     * @param  list<mixed>  $charges
     * @return list<array<string, mixed>>
     */
    private function normalizeCharges(array $charges): array
    {
        $normalized = [];

        foreach ($charges as $charge) {
            if (! is_array($charge)) {
                continue;
            }

            $dues = [];

            foreach (is_array($charge['dues'] ?? null) ? $charge['dues'] : [] as $due) {
                if (! is_array($due)) {
                    continue;
                }

                $dues[] = [
                    'id' => (string) ($due['id'] ?? Str::uuid()),
                    'n' => (int) ($due['n'] ?? 1),
                    'due_on' => (string) ($due['due_on'] ?? now()->toDateString()),
                    'amount' => round((float) ($due['amount'] ?? 0), 2),
                    'paid_on' => isset($due['paid_on']) && is_string($due['paid_on']) ? $due['paid_on'] : null,
                    'transaction_id' => isset($due['transaction_id']) ? (int) $due['transaction_id'] : null,
                ];
            }

            $normalized[] = [
                'id' => (string) ($charge['id'] ?? Str::uuid()),
                'description' => (string) ($charge['description'] ?? 'Charge'),
                'amount' => round((float) ($charge['amount'] ?? 0), 2),
                'plan_id' => (string) ($charge['plan_id'] ?? ''),
                'months' => max(1, (int) ($charge['months'] ?? count($dues) ?: 1)),
                'monthly' => round((float) ($charge['monthly'] ?? ($dues[0]['amount'] ?? 0)), 2),
                'interest' => round((float) ($charge['interest'] ?? 0), 2),
                'first_due_on' => (string) ($charge['first_due_on'] ?? ($dues[0]['due_on'] ?? now()->toDateString())),
                'dues' => $dues,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function makeCharge(string $description, int $months, float $monthly, string $firstDueOn): array
    {
        $monthly = round($monthly, 2);
        $first = CarbonImmutable::parse($firstDueOn)->startOfDay();
        $dues = [];

        for ($n = 1; $n <= $months; $n++) {
            $dues[] = [
                'id' => (string) Str::uuid(),
                'n' => $n,
                'due_on' => $first->addMonthsNoOverflow($n - 1)->toDateString(),
                'amount' => $monthly,
                'paid_on' => null,
                'transaction_id' => null,
            ];
        }

        return [
            'id' => (string) Str::uuid(),
            'description' => $description,
            'amount' => round($monthly * $months, 2),
            'monthly' => $monthly,
            'plan_id' => '',
            'months' => $months,
            'interest' => 0.0,
            'first_due_on' => $first->toDateString(),
            'dues' => $dues,
        ];
    }
}
