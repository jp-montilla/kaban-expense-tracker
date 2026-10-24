<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '../Components/AppLayout.vue'
import PageHero from '../Components/PageHero.vue'
import DonutChart from '../Components/DonutChart.vue'
import PeriodPicker from '../Components/PeriodPicker.vue'
import type { PeriodValue } from '../Components/PeriodPicker.vue'
import TrendChart from '../Components/TrendChart.vue'
import { openLog } from '../lib/logModal'
import { pesos } from '../lib/money'

interface FigureSet {
    income: number | null
    income_source: string
    income_since: string | null
    latest_income: number | null
    latest_since: string | null
    view_bills: number
    view_loan: number
    view_daily: number
    view_actual: number
    view_savings: number
    month_spent: number
    month_actual: number
    cash_left: number | null
    spending_plan: number
    plan_left: number
    variable_plan: number
    variable_spent: number
    variable_left: number
    daily_budget: number | null
    cutoff_pay: number | null
    first_cutoff: number | null
    second_cutoff: number | null
    pay_mode: 'cutoff' | 'lagged' | 'month'
    funding_on: string | null
    savings_pay_on: string | null
    savings_pay: number | null
    bonus: number
    savings_balance: number
}

interface TrendPoint {
    label: string
    full_label: string
    income: number | null
    actual: number
    cash_left: number | null
}

interface TopSpend {
    rank: number
    id: number
    name: string
    color: string
    spent: number
    share: number
}

interface DueRow {
    id: string
    name: string
    amount: number
    paid: boolean
    kind: 'loan' | 'credit'
}

interface DebtCard {
    id: number
    name: string
    creditor: string | null
    item: string | null
    balance: number
    principal: number
    progress: number
    installment_amount: number | null
    installments_logged: number
    installment_count: number | null
    installments_paid: number
    installments_remaining: number | null
}

const props = defineProps<{
    period: PeriodValue & { days_left: number; days_in_month: number; month_label?: string }
    figures: FigureSet
    dues: DueRow[]
    trend: TrendPoint[]
    recent: Array<{ id: number; occurred_on: string; description: string; amount: number; direction: 'out' | 'in'; category: string | null }>
    debts: DebtCard[]
    logCategories: Array<{ id: number; name: string; slug: string; kind: string; color: string }>
    top_spends: { month: TopSpend[]; year: TopSpend[]; all: TopSpend[] }
}>()

const spendScope = ref<'month' | 'year' | 'all'>('month')
const topList = computed(() => props.top_spends[spendScope.value] ?? [])

function halfHref(half?: number) {
    const params = new URLSearchParams({
        span: props.period.span ?? 'month',
        year: String(props.period.year),
        month: String(props.period.month),
    })

    if (half) {
        params.set('half', String(half))
    }

    return `/?${params.toString()}`
}

function prettyDay(iso: string | null) {
    if (! iso) {
        return ''
    }

    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}

function monthPayDay(day: number) {
    const date = new Date(props.period.year, props.period.month - 1, day)

    return date.toLocaleDateString('en-PH', { month: 'long', day: 'numeric' })
}

const cashHint = computed(() => {
    if (props.figures.variable_left < 0) {
        return `${pesos(props.figures.variable_left)} living`
    }

    if (props.period.days_left === 0) {
        return 'Month closed'
    }

    return `${pesos(props.figures.daily_budget)} left per day`
})

const donutTotal = computed(() => topList.value.reduce((sum, row) => sum + Math.abs(row.spent), 0))
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <template #hero>
            <PageHero
                :eyebrow="`Dashboard · ${period.label}`"
                label="Budget left"
                :value="pesos(figures.cash_left)"
                :pill="cashHint"
            >
                <div v-if="figures.first_cutoff != null && figures.second_cutoff != null" class="hero-split">
                    <div>
                        <p class="text-xs text-white/70">{{ monthPayDay(0) }}</p>
                        <p class="num mt-1 text-lg text-white">{{ pesos(figures.first_cutoff) }}</p>
                    </div>
                    <p class="self-center text-white/40">+</p>
                    <div>
                        <p class="text-xs text-white/70">{{ monthPayDay(15) }}</p>
                        <p class="num mt-1 text-lg text-white">{{ pesos(figures.second_cutoff) }}</p>
                    </div>
                </div>
            </PageHero>
        </template>

        <section class="card overflow-hidden">
            <div class="sheet-head">
                <PeriodPicker :period="period" base="/" />
                <div v-if="period.span === 'month'" class="flex gap-1" role="group" aria-label="Cutoff">
                    <Link :href="halfHref()" preserve-scroll class="btn-ghost py-1 text-xs" :class="period.half === null ? 'is-on' : ''">Month</Link>
                    <Link :href="halfHref(1)" preserve-scroll class="btn-ghost py-1 text-xs" :class="period.half === 1 ? 'is-on' : ''">1–15</Link>
                    <Link :href="halfHref(2)" preserve-scroll class="btn-ghost py-1 text-xs" :class="period.half === 2 ? 'is-on' : ''">16–end</Link>
                </div>
            </div>
            <dl class="stat-grid">
                <div>
                    <dt>Income</dt>
                    <dd class="num">{{ pesos(figures.income) }}</dd>
                </div>
                <div>
                    <dt>Bills</dt>
                    <dd class="num">{{ pesos(figures.view_bills) }}</dd>
                </div>
                <div>
                    <dt>Loan</dt>
                    <dd class="num">{{ pesos(figures.view_loan) }}</dd>
                </div>
                <div>
                    <dt>Living</dt>
                    <dd class="num">{{ pesos(figures.view_daily) }}</dd>
                </div>
            </dl>
        </section>

        <section class="card mt-4 overflow-x-auto">
            <div class="sheet-head">
                <h2>Due</h2>
                <Link :href="`/bills?year=${period.year}&month=${period.month}`" class="btn-ghost py-1 text-xs">Bills</Link>
            </div>
            <table class="data-table">
                <caption class="sr-only">Due this month</caption>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col" class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="dues.length === 0">
                        <td colspan="2" class="text-moss">Nothing due in this view.</td>
                    </tr>
                    <tr v-for="due in dues" :key="due.id">
                        <td>
                            {{ due.name }}
                            <span class="text-moss"> · {{ due.kind === 'loan' ? 'Loan' : 'Credit' }}</span>
                            <span v-if="due.paid" class="ml-2 text-xs text-palm-deep">Paid</span>
                        </td>
                        <td class="num text-right">{{ pesos(due.amount) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card mt-4 overflow-x-auto">
            <div class="sheet-head">
                <h2>Latest</h2>
                <div class="flex items-center gap-2">
                    <Link :href="`/log?year=${period.year}&month=${period.month}`" class="btn-ghost py-1 text-xs">Daily</Link>
                    <button type="button" class="btn-ghost py-1 text-xs md:hidden" @click="openLog">Add</button>
                </div>
            </div>
            <table class="data-table">
                <caption class="sr-only">Latest</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Item</th>
                        <th scope="col" class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="recent.length === 0">
                        <td colspan="3" class="text-moss">Nothing logged in this view.</td>
                    </tr>
                    <tr v-for="item in recent" :key="item.id">
                        <td class="whitespace-nowrap text-moss">{{ prettyDay(item.occurred_on) }}</td>
                        <td>
                            {{ item.description }}
                            <span class="text-moss"> · {{ item.category ?? 'Living' }}</span>
                        </td>
                        <td class="num text-right" :class="item.direction === 'in' ? 'text-palm-deep' : ''">
                            {{ item.direction === 'in' ? '+' : '−' }}{{ pesos(Math.abs(item.amount)) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card mt-4 overflow-hidden p-0">
            <div class="sheet-head">
                <h2>Expenses</h2>
                <div class="flex gap-1" role="group" aria-label="Top spend range">
                    <button type="button" class="btn-ghost py-1 text-xs" :class="spendScope === 'month' ? 'is-on' : ''" @click="spendScope = 'month'">Month</button>
                    <button type="button" class="btn-ghost py-1 text-xs" :class="spendScope === 'year' ? 'is-on' : ''" @click="spendScope = 'year'">{{ period.year }}</button>
                    <button type="button" class="btn-ghost py-1 text-xs" :class="spendScope === 'all' ? 'is-on' : ''" @click="spendScope = 'all'">All</button>
                </div>
            </div>
            <DonutChart class="px-3 pb-3" :slices="topList" :total="donutTotal" label="Total expenses" />
            <table class="data-table">
                <caption class="sr-only">Top spends</caption>
                <thead>
                    <tr>
                        <th scope="col">Category</th>
                        <th scope="col" class="text-right">Share</th>
                        <th scope="col" class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="topList.length === 0">
                        <td colspan="3" class="text-moss">Nothing to rank yet.</td>
                    </tr>
                    <tr v-for="row in topList.slice(0, 5)" :key="`${spendScope}-${row.id}`">
                        <td>
                            <span class="mr-2 inline-block size-2 rounded-full align-middle" :style="{ background: row.color }" />
                            {{ row.name }}
                        </td>
                        <td class="text-right text-moss">{{ row.share }}%</td>
                        <td class="num text-right">{{ pesos(row.spent) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="mt-4 grid gap-4 lg:grid-cols-5">
            <article class="card overflow-hidden lg:col-span-3">
                <div class="sheet-head">
                    <h2>Pay vs expenses</h2>
                    <p class="text-xs font-medium text-moss">12 months</p>
                </div>
                <div class="px-3 pb-3">
                    <TrendChart :points="trend" />
                </div>
            </article>
            <article class="card overflow-x-auto lg:col-span-2">
                <div class="sheet-head">
                    <h2>Loans</h2>
                    <Link href="/loans" class="btn-ghost py-1 text-xs">All</Link>
                </div>
                <table class="data-table">
                    <caption class="sr-only">Loans</caption>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col" class="text-right">Left</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="debts.length === 0">
                            <td colspan="2" class="text-moss">No open loans.</td>
                        </tr>
                        <tr v-for="debt in debts" :key="debt.id">
                            <td>
                                {{ debt.name }}
                                <span class="text-moss"> · {{ debt.progress }}%</span>
                            </td>
                            <td class="num text-right">{{ pesos(debt.balance) }}</td>
                        </tr>
                    </tbody>
                </table>
            </article>
        </section>
    </AppLayout>
</template>
