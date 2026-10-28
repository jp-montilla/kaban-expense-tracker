<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '../../Components/AppLayout.vue'
import DashPanel from '../../Components/DashPanel.vue'
import OverviewTiles from '../../Components/OverviewTiles.vue'
import type { OverviewTile } from '../../Components/OverviewTiles.vue'
import PageHeader from '../../Components/PageHeader.vue'
import PageHero from '../../Components/PageHero.vue'
import { pesos, tone } from '../../lib/money'

interface Half {
    half: number
    label: string
    bills: number
    loan: number
    daily: number
    actual: number
}

interface MonthRow {
    month: number
    label: string
    halves: Half[]
    actual: number
    income: number | null
    income_source: string
    income_since: string | null
    cash_left: number | null
    has_activity: boolean
}

const props = defineProps<{
    year: number
    months: MonthRow[]
    actual: number
    income: number | null
    cash_left: number | null
}>()

const years = computed(() => {
    const current = new Date().getFullYear()
    const values = new Set(Array.from({ length: 8 }, (_, index) => current - 5 + index))
    values.add(props.year)

    return [...values].sort()
})

function onYear(event: Event) {
    router.get(`/summary?year=${(event.target as HTMLSelectElement).value}`)
}

function sumHalves(month: MonthRow, key: 'bills' | 'loan' | 'daily') {
    return month.halves.reduce((sum, half) => sum + half[key], 0)
}

const activeMonths = computed(() => props.months.filter((row) => row.has_activity).length)

const overview = computed<OverviewTile[]>(() => [
    { label: 'Pay', value: pesos(props.income), icon: 'income', wrap: 'bg-[#E8F8EF] text-palm-deep' },
    { label: 'Expenses', value: pesos(props.actual), icon: 'spend', wrap: 'bg-[#FDECEC] text-spend' },
    { label: 'Left', value: pesos(props.cash_left), valueClass: tone(props.cash_left), icon: 'wallet', wrap: 'bg-[#EEEBFF] text-accent' },
    { label: 'Months', value: String(activeMonths.value), icon: 'calendar', wrap: 'bg-[#E8F1FF] text-income' },
])
</script>

<template>
    <Head :title="String(year)" />
    <AppLayout>
        <template #header>
            <PageHeader :title="String(year)" subtitle="Pay, bills, living, and leftover by month.">
                <label class="sr-only" for="summary-year">Year</label>
                <select id="summary-year" class="rounded-full border border-line bg-paper px-3.5 py-2 text-sm font-medium shadow-sm" :value="year" @change="onYear">
                    <option v-for="option in years" :key="option" :value="option">{{ option }}</option>
                </select>
            </PageHeader>
        </template>
        <template #hero>
            <PageHero
                label="Budget left"
                :value="pesos(cash_left)"
                :hint="`Expenses ${pesos(actual)}`"
            />
        </template>
        <DashPanel title="Overview" icon="chart" icon-wrap="bg-[#EEEBFF] text-accent">
            <div class="px-5 pb-5">
                <OverviewTiles :tiles="overview" />
            </div>
        </DashPanel>

        <DashPanel class="mt-4" title="Months" icon="calendar" icon-wrap="bg-[#E8F1FF] text-income">
            <table class="data-table min-w-[40rem]">
                <caption class="sr-only">{{ year }} by month</caption>
                <thead>
                    <tr>
                        <th scope="col">Month</th>
                        <th scope="col" class="text-right">Pay</th>
                        <th scope="col" class="text-right">Bills</th>
                        <th scope="col" class="text-right">Loan</th>
                        <th scope="col" class="text-right">Living</th>
                        <th scope="col" class="text-right">Left</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="month in months" :key="month.month">
                        <td>
                            <Link :href="`/?year=${year}&month=${month.month}`" class="hover:text-accent">{{ month.label }}</Link>
                        </td>
                        <td class="num text-right text-moss">{{ month.income_source === 'missing' ? '—' : pesos(month.income) }}</td>
                        <td class="num text-right">{{ pesos(sumHalves(month, 'bills')) }}</td>
                        <td class="num text-right">{{ pesos(sumHalves(month, 'loan')) }}</td>
                        <td class="num text-right">{{ pesos(sumHalves(month, 'daily')) }}</td>
                        <td class="num text-right" :class="tone(month.cash_left)">{{ pesos(month.cash_left) }}</td>
                    </tr>
                </tbody>
            </table>
        </DashPanel>
    </AppLayout>
</template>
