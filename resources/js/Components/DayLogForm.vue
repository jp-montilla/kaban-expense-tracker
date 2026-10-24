<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import BusyOverlay from './BusyOverlay.vue'
import CreditTerms from './CreditTerms.vue'
import { pesos } from '../lib/money'
import type { CreditOption } from '../lib/wallets'

interface DayItem {
    id: number
    description: string
    amount: number
    type: string
    direction: 'out' | 'in'
    via_credit?: boolean
    payment_method?: string | null
    credit_months?: number | null
    credit_monthly?: number | null
}

interface Line {
    key: string
    id: number | null
    description: string
    amount: string
    type: 'daily' | 'refund'
    via_credit: boolean
    credit_method: string
    credit_months: number
    credit_monthly: string
}

const props = defineProps<{
    occurredOn?: string
    items?: DayItem[]
}>()

const emit = defineEmits<{
    cancel: []
    saved: []
}>()

const page = usePage<{ today: string; credits: CreditOption[] }>()
const credits = computed(() => page.props.credits ?? [])
const editing = computed(() => (props.items?.length ?? 0) > 0)

let lineSeq = 0

function blankLine(): Line {
    lineSeq += 1

    return {
        key: `new-${lineSeq}`,
        id: null,
        description: '',
        amount: '',
        type: 'daily',
        via_credit: false,
        credit_method: credits.value[0]?.slug ?? '',
        credit_months: 1,
        credit_monthly: '',
    }
}

function lineFrom(item: DayItem): Line {
    lineSeq += 1

    return {
        key: `item-${item.id}`,
        id: item.id,
        description: item.description,
        amount: String(item.amount),
        type: item.type === 'refund' ? 'refund' : 'daily',
        via_credit: Boolean(item.via_credit),
        credit_method: item.payment_method ?? credits.value[0]?.slug ?? '',
        credit_months: item.credit_months ?? 1,
        credit_monthly: item.credit_monthly == null ? (item.via_credit ? String(item.amount) : '') : String(item.credit_monthly),
    }
}

const form = useForm({
    occurred_on: props.occurredOn ?? page.props.today,
    items: (props.items?.length ? props.items.map(lineFrom) : [blankLine()]) as Line[],
})

watch(() => form.items.map((line) => `${line.via_credit}:${line.amount}:${line.credit_months}:${line.type}`), () => {
    for (const line of form.items) {
        if (line.type === 'refund') {
            line.via_credit = false
        }

        if (line.via_credit && line.credit_months === 1) {
            line.credit_monthly = line.amount
        }
    }
})

const net = computed(() => form.items.reduce((sum, line) => {
    const amount = Number(line.amount)

    if (! Number.isFinite(amount) || amount <= 0 || line.via_credit) {
        return sum
    }

    return sum + (line.type === 'refund' ? -amount : amount)
}, 0))

function addItem() {
    form.items.push(blankLine())
}

function removeItem(index: number) {
    if (form.items.length === 1) {
        form.items[0] = blankLine()

        return
    }

    form.items.splice(index, 1)
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    }

    if (editing.value) {
        form.put(`/log/${form.occurred_on}`, options)

        return
    }

    form.post('/log', options)
}

function itemError(index: number): string | undefined {
    return form.errors[`items.${index}.description`]
        || form.errors[`items.${index}.amount`]
        || form.errors[`items.${index}.type`]
        || form.errors[`items.${index}.credit_method`]
        || form.errors[`items.${index}.credit_months`]
        || form.errors[`items.${index}.credit_monthly`]
}
</script>

<template>
    <form class="grid gap-3" @submit.prevent="submit">
        <BusyOverlay :show="form.processing" :label="editing ? 'Updating…' : 'Saving…'" />
        <label class="grid gap-1">
            <span class="kicker">Date</span>
            <input v-model="form.occurred_on" type="date" required class="field" :disabled="editing">
        </label>

        <div class="grid gap-3">
            <div v-for="(line, index) in form.items" :key="line.key" class="grid gap-2 rounded-lg border border-line p-3">
                <div class="grid gap-2 sm:grid-cols-[1fr_8rem_7rem_auto] sm:items-end">
                    <label class="grid gap-1">
                        <span class="kicker">Item</span>
                        <input v-model="line.description" type="text" required maxlength="500" class="field" placeholder="Taho, gas, grocery">
                    </label>
                    <label class="grid gap-1">
                        <span class="kicker">Amount</span>
                        <input v-model="line.amount" type="number" required min="0.01" step="0.01" inputmode="decimal" class="field" placeholder="0.00">
                    </label>
                    <label class="grid gap-1">
                        <span class="kicker">Kind</span>
                        <select v-model="line.type" class="field">
                            <option value="daily">Expense</option>
                            <option value="refund">Money back</option>
                        </select>
                    </label>
                    <button type="button" class="btn-ghost text-clay" @click="removeItem(index)">Remove</button>
                </div>
                <CreditTerms
                    v-if="line.type === 'daily'"
                    v-model:via-credit="line.via_credit"
                    v-model:credit-method="line.credit_method"
                    v-model:months="line.credit_months"
                    v-model:monthly="line.credit_monthly"
                    :credits="credits"
                />
                <p v-if="itemError(index)" class="text-sm text-clay">{{ itemError(index) }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <button type="button" class="btn-ghost" @click="addItem">Add item</button>
            <p class="num text-sm text-moss">{{ pesos(net) }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 pt-1">
            <button type="submit" class="btn" :disabled="form.processing">Save</button>
            <button v-if="editing" type="button" class="btn-ghost" @click="emit('cancel')">Cancel</button>
            <p v-if="form.errors.occurred_on || form.errors.items" class="text-sm text-clay">
                {{ form.errors.occurred_on || form.errors.items }}
            </p>
        </div>
    </form>
</template>
