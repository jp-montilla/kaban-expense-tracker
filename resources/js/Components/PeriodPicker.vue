<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import MonthMenu from './MonthMenu.vue'

export interface PeriodValue {
    span: 'all' | 'month' | 'quarter' | 'range'
    year: number
    month: number
    half: number | null
    quarter: number | null
    from: string
    to: string
    label: string
}

const props = withDefaults(defineProps<{
    period: PeriodValue
    base: string
    tone?: 'page' | 'hero'
    allowAll?: boolean
    monthOnly?: boolean
}>(), {
    tone: 'page',
    allowAll: false,
    monthOnly: false,
})

const uid = computed(() => props.base.replace(/\W+/g, '') || 'home')

function ym(value: string) {
    const [year, month] = value.split('-').map(Number)

    return {
        year: year || props.period.year,
        month: month || 1,
    }
}

const from = computed(() => ym(props.period.from))
const to = computed(() => ym(props.period.to))

function stamp(year: number, month: number) {
    return `${year}-${String(month).padStart(2, '0')}`
}

function go(next: Record<string, string | number | null | undefined>) {
    const params = new URLSearchParams()
    const query = {
        span: props.monthOnly ? 'month' : (next.span ?? props.period.span),
        year: next.year ?? props.period.year,
        month: next.month ?? props.period.month,
        half: props.monthOnly ? null : (next.half === undefined ? props.period.half : next.half),
        quarter: next.quarter ?? props.period.quarter,
        from: next.from ?? props.period.from,
        to: next.to ?? props.period.to,
    }

    params.set('span', String(query.span))
    params.set('year', String(query.year))
    params.set('month', String(query.month))

    if (query.span === 'month' && (query.half === 1 || query.half === 2)) {
        params.set('half', String(query.half))
    }

    if (query.span === 'quarter' && query.quarter) {
        params.set('quarter', String(query.quarter))
    }

    if (query.span === 'range') {
        params.set('from', String(query.from))
        params.set('to', String(query.to))
    }

    router.get(`${props.base}?${params.toString()}`, {}, { preserveScroll: true, preserveState: false })
}

function onSpan(event: Event) {
    const span = (event.target as HTMLSelectElement).value as PeriodValue['span']
    go({ span, half: null })
}

function pickMonth(year: number, month: number) {
    go({ span: 'month', year, month, half: null })
}

function pickFrom(year: number, month: number) {
    go({ span: 'range', from: stamp(year, month), half: null })
}

function pickTo(year: number, month: number) {
    go({ span: 'range', to: stamp(year, month), half: null })
}

function onQuarter(event: Event) {
    go({ span: 'quarter', quarter: Number((event.target as HTMLSelectElement).value), half: null })
}

function onYear(event: Event) {
    go({ year: Number((event.target as HTMLSelectElement).value) })
}

const years = computed(() => {
    const current = new Date().getFullYear()
    const values = new Set(Array.from({ length: 8 }, (_, index) => current - 5 + index))
    values.add(props.period.year)

    return [...values].sort((left, right) => left - right)
})

const fieldClass = computed(() => props.tone === 'hero'
    ? 'rounded-full border-0 bg-white/15 px-3 py-1.5 text-sm font-medium text-white'
    : 'field py-1.5 text-sm')
</script>

<template>
    <div class="flex flex-wrap items-center justify-center gap-2">
        <template v-if="!monthOnly">
            <label class="sr-only" :for="`${uid}-span`">Range</label>
            <select :id="`${uid}-span`" :class="[fieldClass, '!w-auto']" :value="period.span" @change="onSpan">
                <option v-if="allowAll" value="all">All</option>
                <option value="month">Month</option>
                <option value="quarter">Quarter</option>
                <option value="range">Custom</option>
            </select>
        </template>

        <template v-if="monthOnly || period.span === 'month'">
            <MonthMenu :id="`${uid}-month`" :year="period.year" :month="period.month" :tone="tone" @pick="pickMonth" />
        </template>

        <template v-else-if="period.span === 'quarter'">
            <label class="sr-only" :for="`${uid}-year`">Year</label>
            <select :id="`${uid}-year`" :class="[fieldClass, '!w-auto']" :value="period.year" @change="onYear">
                <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
            </select>
            <label class="sr-only" :for="`${uid}-quarter`">Quarter</label>
            <select :id="`${uid}-quarter`" :class="[fieldClass, '!w-auto']" :value="period.quarter ?? 1" @change="onQuarter">
                <option :value="1">Q1</option>
                <option :value="2">Q2</option>
                <option :value="3">Q3</option>
                <option :value="4">Q4</option>
            </select>
        </template>

        <template v-else-if="period.span === 'range'">
            <MonthMenu :id="`${uid}-from`" :year="from.year" :month="from.month" :tone="tone" @pick="pickFrom" />
            <span :class="tone === 'hero' ? 'text-white/80' : 'text-moss'" class="text-sm">to</span>
            <MonthMenu :id="`${uid}-to`" :year="to.year" :month="to.month" :tone="tone" @pick="pickTo" />
        </template>
    </div>
</template>
