<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '../../Components/AppLayout.vue'
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
</script>

<template>
    <Head :title="String(year)" />
    <AppLayout>
        <template #hero>
            <PageHero
                :eyebrow="`Year · ${year}`"
                label="Budget left"
                :value="pesos(cash_left)"
                :pill="`Expenses ${pesos(actual)}`"
            />
        </template>
        <dl class="card stat-grid">
            <div>
                <dt>Pay</dt>
                <dd class="num">{{ pesos(income) }}</dd>
            </div>
            <div>
                <dt>Expenses</dt>
                <dd class="num">{{ pesos(actual) }}</dd>
            </div>
            <div>
                <dt>Left</dt>
                <dd class="num" :class="tone(cash_left)">{{ pesos(cash_left) }}</dd>
            </div>
            <div>
                <dt>Months</dt>
                <dd class="num">{{ activeMonths }}</dd>
            </div>
        </dl>

        <section class="card mt-4 overflow-x-auto">
            <div class="sheet-head">
                <h2>Months</h2>
                <label class="sr-only" for="summary-year">Year</label>
                <select id="summary-year" class="field w-auto py-1 text-xs" :value="year" @change="onYear">
                    <option v-for="option in years" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
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
        </section>
    </AppLayout>
</template>
