<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, shallowRef } from 'vue'
import AppLayout from '../Components/AppLayout.vue'
import DashPanel from '../Components/DashPanel.vue'
import DashboardHero from '../Components/DashboardHero.vue'
import PageHeader from '../Components/PageHeader.vue'
import DonutChart from '../Components/DonutChart.vue'
import Icon from '../Components/Icon.vue'
import type { IconName } from '../Components/Icon.vue'
import OverviewTiles from '../Components/OverviewTiles.vue'
import type { OverviewTile } from '../Components/OverviewTiles.vue'
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
    view_credit: number
    view_loan: number
    view_daily: number
    living_plan: number
    living_spent: number
    view_actual: number
    view_savings: number
    saved: number
    approx_savings: number
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

interface TopLiving {
    rank: number
    id: number
    description: string
    amount: number
    occurred_on: string
}

interface FundingBill {
    name: string
    amount: number
}

interface FundingSplit {
    pay: number
    bills: FundingBill[]
    bills_total: number
    saved: number
    leftover: number
    loans?: number
}

interface DueRow {
    id: string
    name: string
    amount: number
    paid: boolean
    kind: 'loan' | 'credit' | 'bill'
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
    cash_bills: DueRow[]
    dues: DueRow[]
    funding: {
        living: number
        credit: number
        first: FundingSplit
        second: FundingSplit
        saved: number
        approx_savings: number
    }
    trend: TrendPoint[]
    recent: Array<{ id: number; occurred_on: string; description: string; amount: number; direction: 'out' | 'in'; category: string | null }>
    debts: DebtCard[]
    logCategories: Array<{ id: number; name: string; slug: string; kind: string; color: string }>
    top_spends: { month: TopSpend[]; year: TopSpend[]; all: TopSpend[] }
    top_living: { month: TopLiving[]; year: TopLiving[]; all: TopLiving[] }
}>()

const page = usePage<{
    auth?: { user?: { name: string } | null }
}>()

const spendScope = shallowRef<'month' | 'year' | 'all'>('month')
const livingScope = shallowRef<'month' | 'year' | 'all'>('month')
const topList = computed(() => props.top_spends[spendScope.value] ?? [])
const livingList = computed(() => props.top_living[livingScope.value] ?? [])

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

const hello = computed(() => {
    const hour = new Date().getHours()
    const name = (page.props.auth?.user?.name ?? 'there').trim() || 'there'

    if (hour < 12) {
        return `Good morning, ${name}!`
    }

    if (hour < 18) {
        return `Good afternoon, ${name}!`
    }

    return `Good evening, ${name}!`
})

const overviewLabel = computed(() => props.period.month_label || props.period.label)

const cashHint = computed(() => {
    if (props.figures.variable_left < 0) {
        return `${pesos(props.figures.variable_left)} living`
    }

    if (props.period.days_left === 0) {
        return 'Month closed'
    }

    return `${pesos(props.figures.daily_budget)} left per day`
})

const firstPay = computed(() => {
    if (props.figures.first_cutoff == null || props.period.half === 2) {
        return null
    }

    return {
        label: monthPayDay(0),
        pay: props.figures.first_cutoff,
        saved: props.funding.first.saved,
        leftover: props.funding.first.leftover,
    }
})

const secondPay = computed(() => {
    if (props.figures.second_cutoff == null || props.period.half === 1) {
        return null
    }

    return {
        label: monthPayDay(15),
        pay: props.figures.second_cutoff,
        saved: props.funding.second.saved,
        leftover: props.funding.second.leftover,
    }
})

const overview = computed<OverviewTile[]>(() => [
    {
        label: 'Income',
        value: pesos(props.figures.income),
        icon: 'income',
        wrap: 'bg-[#E8F8EF] text-palm-deep',
    },
    {
        label: 'Spent',
        value: pesos(props.figures.view_actual),
        icon: 'spend',
        wrap: 'bg-[#FDECEC] text-spend',
    },
    {
        label: 'Bills',
        value: pesos(props.figures.view_bills),
        icon: 'bills',
        wrap: 'bg-[#EEEBFF] text-accent',
    },
    {
        label: 'Credit',
        value: pesos(props.figures.view_credit),
        icon: 'wallet',
        wrap: 'bg-[#FFF1E8] text-[#E67E22]',
    },
    {
        label: 'Loan',
        value: pesos(props.figures.view_loan),
        icon: 'lock',
        wrap: 'bg-[#E8F1FF] text-income',
    },
    {
        label: 'Living',
        value: pesos(props.figures.view_daily),
        hint: `${pesos(props.figures.living_plan)} − ${pesos(props.figures.living_spent)}`,
        valueClass: props.figures.view_daily < 0 ? 'text-clay' : '',
        icon: 'house',
        wrap: 'bg-[#FFF4E5] text-gold',
    },
    {
        label: 'Saved',
        value: pesos(props.figures.saved),
        hint: `left ${pesos(props.figures.approx_savings)}`,
        valueClass: props.figures.saved < 0 ? 'text-clay' : 'text-palm-deep',
        icon: 'savings',
        wrap: 'bg-[#E8F8EF] text-palm-deep',
    },
])

const donutTotal = computed(() => topList.value.reduce((sum, row) => sum + Math.abs(row.spent), 0))

function dueMark(due: DueRow): { icon: IconName; wrap: string } {
    const name = due.name.toLowerCase()

    if (name.includes('spay')) {
        return { icon: 'bills', wrap: 'bg-[#FFF1E8] text-[#E67E22]' }
    }

    if (name.includes('laz')) {
        return { icon: 'bills', wrap: 'bg-[#E8F8EF] text-palm-deep' }
    }

    if (due.kind === 'loan') {
        return { icon: 'loan', wrap: 'bg-[#EEEBFF] text-accent' }
    }

    if (due.kind === 'bill') {
        return { icon: 'bills', wrap: 'bg-[#EEEBFF] text-accent' }
    }

    return { icon: 'bills', wrap: 'bg-[#E8F1FF] text-income' }
}

const dueRows = computed(() => props.dues.map((due) => ({ due, mark: dueMark(due) })))
const billRows = computed(() => props.cash_bills.map((due) => ({ due, mark: dueMark(due) })))
const creditRows = computed(() => {
    if (props.period.half === 1) {
        return []
    }

    return dueRows.value.filter((row) => row.due.kind === 'credit' && row.due.amount > 0)
})
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <template #header>
            <PageHeader :title="hello" :subtitle="`Here’s your expense overview for ${overviewLabel}.`">
                <PeriodPicker :period="period" base="/" month-only tone="toolbar" />
            </PageHeader>
        </template>

        <template #hero>
            <DashboardHero
                :cash-left="figures.cash_left"
                :hint="cashHint"
                :saved="figures.saved"
                :leftover="figures.approx_savings"
                :first="firstPay"
                :second="secondPay"
            />
        </template>

        <DashPanel title="Monthly Overview" icon="chart" icon-wrap="bg-[#EEEBFF] text-accent">
            <template #actions>
                <div v-if="period.span === 'month'" class="flex gap-1" role="group" aria-label="Cutoff">
                    <Link :href="halfHref()" preserve-scroll class="btn-ghost py-1 text-xs" :class="period.half === null ? 'is-on' : ''">Month</Link>
                    <Link :href="halfHref(1)" preserve-scroll class="btn-ghost py-1 text-xs" :class="period.half === 1 ? 'is-on' : ''">1–15</Link>
                    <Link :href="halfHref(2)" preserve-scroll class="btn-ghost py-1 text-xs" :class="period.half === 2 ? 'is-on' : ''">16–end</Link>
                </div>
            </template>
            <div class="px-5 pb-5">
                <OverviewTiles :tiles="overview" />
            </div>
        </DashPanel>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <DashPanel title="Bills" icon="bills">
                <template #actions>
                    <Link :href="`/bills?year=${period.year}&month=${period.month}`" class="btn-ghost py-1 text-xs">Bills</Link>
                </template>
                <table class="data-table">
                    <caption class="sr-only">Cash bills this month</caption>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col" class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="billRows.length === 0">
                            <td colspan="2" class="text-moss">No cash bills in this view.</td>
                        </tr>
                        <tr v-for="row in billRows" :key="row.due.id">
                            <td>
                                <span class="flex items-center gap-3">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-xl" :class="row.mark.wrap">
                                        <Icon :name="row.mark.icon" :size="16" />
                                    </span>
                                    <span>
                                        {{ row.due.name }}
                                        <span v-if="row.due.paid" class="ml-2 rounded-full bg-[#E8F8EF] px-2 py-0.5 text-[11px] font-semibold text-palm-deep">Paid</span>
                                    </span>
                                </span>
                            </td>
                            <td class="num text-right">{{ pesos(row.due.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </DashPanel>

            <DashPanel title="Credit" icon="wallet" icon-wrap="bg-[#FFF1E8] text-[#E67E22]">
                <template #actions>
                    <Link :href="`/bills?year=${period.year}&month=${period.month}`" class="btn-ghost py-1 text-xs">Bills</Link>
                </template>
                <table class="data-table">
                    <caption class="sr-only">Credit this month</caption>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col" class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="creditRows.length === 0">
                            <td colspan="2" class="text-moss">No credit due in this view.</td>
                        </tr>
                        <tr v-for="row in creditRows" :key="row.due.id">
                            <td>
                                <span class="flex items-center gap-3">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-xl" :class="row.mark.wrap">
                                        <Icon :name="row.mark.icon" :size="16" />
                                    </span>
                                    <span>
                                        {{ row.due.name }}
                                        <span v-if="row.due.paid" class="ml-2 rounded-full bg-[#E8F8EF] px-2 py-0.5 text-[11px] font-semibold text-palm-deep">Paid</span>
                                    </span>
                                </span>
                            </td>
                            <td class="num text-right">{{ pesos(row.due.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </DashPanel>

            <DashPanel title="Latest" icon="clock" icon-wrap="bg-[#E8F1FF] text-income">
                <template #actions>
                    <div class="flex items-center gap-2">
                        <Link :href="`/log?year=${period.year}&month=${period.month}`" class="btn-ghost py-1 text-xs">Daily</Link>
                        <button type="button" class="btn-ghost py-1 text-xs md:hidden" @click="openLog">Add</button>
                    </div>
                </template>
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
                            <td class="num text-right" :class="item.direction === 'in' ? 'text-palm-deep' : 'text-spend'">
                                {{ item.direction === 'in' ? '+' : '−' }}{{ pesos(Math.abs(item.amount)) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </DashPanel>

            <DashPanel title="Loan" icon="lock" icon-wrap="bg-[#E8F1FF] text-income">
                <template #actions>
                    <Link href="/loans" class="btn-ghost py-1 text-xs">All</Link>
                </template>
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
            </DashPanel>

            <DashPanel title="Priciest living" icon="house" icon-wrap="bg-[#FFF4E5] text-gold">
                <template #actions>
                    <div class="flex gap-1" role="group" aria-label="Living range">
                        <button type="button" class="btn-ghost py-1 text-xs" :class="livingScope === 'month' ? 'is-on' : ''" @click="livingScope = 'month'">Month</button>
                        <button type="button" class="btn-ghost py-1 text-xs" :class="livingScope === 'year' ? 'is-on' : ''" @click="livingScope = 'year'">{{ period.year }}</button>
                        <button type="button" class="btn-ghost py-1 text-xs" :class="livingScope === 'all' ? 'is-on' : ''" @click="livingScope = 'all'">All</button>
                    </div>
                </template>
                <table class="data-table">
                    <caption class="sr-only">Top living expenses</caption>
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Item</th>
                            <th scope="col" class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="livingList.length === 0">
                            <td colspan="3" class="text-moss">No living spends in this range.</td>
                        </tr>
                        <tr v-for="row in livingList" :key="`${livingScope}-${row.id}`">
                            <td class="whitespace-nowrap text-moss">{{ prettyDay(row.occurred_on) }}</td>
                            <td>{{ row.description }}</td>
                            <td class="num text-right">{{ pesos(row.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </DashPanel>

            <DashPanel title="Expenses" icon="spend" icon-wrap="bg-[#E8F8EF] text-palm-deep">
                <template #actions>
                    <div class="flex gap-1" role="group" aria-label="Top spend range">
                        <button type="button" class="btn-ghost py-1 text-xs" :class="spendScope === 'month' ? 'is-on' : ''" @click="spendScope = 'month'">Month</button>
                        <button type="button" class="btn-ghost py-1 text-xs" :class="spendScope === 'year' ? 'is-on' : ''" @click="spendScope = 'year'">{{ period.year }}</button>
                        <button type="button" class="btn-ghost py-1 text-xs" :class="spendScope === 'all' ? 'is-on' : ''" @click="spendScope = 'all'">All</button>
                    </div>
                </template>
                <div class="flex flex-col items-center gap-5 px-5 pb-5 sm:flex-row sm:items-center">
                    <DonutChart :slices="topList" :total="donutTotal" label="Total expenses" />
                    <ul class="w-full min-w-0 space-y-2.5">
                        <li v-if="topList.length === 0" class="text-sm text-moss">Nothing to rank yet.</li>
                        <li v-for="row in topList.slice(0, 5)" :key="`${spendScope}-legend-${row.id}`" class="flex items-center justify-between gap-3 text-sm">
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="size-2.5 shrink-0 rounded-full" :style="{ background: row.color }" />
                                <span class="truncate">{{ row.name }}</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-3 text-moss">
                                <span>{{ row.share }}%</span>
                                <span class="num text-ink">{{ pesos(row.spent) }}</span>
                            </span>
                        </li>
                    </ul>
                </div>
            </DashPanel>

            <DashPanel title="Category breakdown" icon="bills" icon-wrap="bg-mist text-moss">
                <template #actions>
                    <p class="text-xs font-medium text-moss">{{ spendScope === 'all' ? 'All' : spendScope === 'year' ? period.year : 'Month' }}</p>
                </template>
                <table class="data-table">
                    <caption class="sr-only">Spend by category</caption>
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
                        <tr v-for="row in topList.slice(0, 5)" :key="`${spendScope}-cat-${row.id}`">
                            <td>
                                <span class="mr-2 inline-block size-2 rounded-full align-middle" :style="{ background: row.color }" />
                                {{ row.name }}
                            </td>
                            <td class="text-right text-moss">{{ row.share }}%</td>
                            <td class="num text-right">{{ pesos(row.spent) }}</td>
                        </tr>
                    </tbody>
                </table>
            </DashPanel>
        </div>

        <DashPanel class="mt-4" title="Pay vs expenses" icon="chart" icon-wrap="bg-[#E8F1FF] text-income">
            <template #actions>
                <p class="text-xs font-medium text-moss">12 months</p>
            </template>
            <div class="px-5 pb-5">
                <TrendChart :points="trend" />
            </div>
        </DashPanel>
    </AppLayout>
</template>
