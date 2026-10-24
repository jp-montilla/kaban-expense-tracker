<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

interface CategoryOption {
    id: number
    name: string
    slug: string
    kind: string
    color?: string
}

interface Editable {
    id: number
    occurred_on: string
    description: string
    amount: number
    direction: 'out' | 'in'
    type: string
    category_id: number | null
    notes: string | null
    source?: 'transaction' | 'savings'
}

const props = defineProps<{
    categories: CategoryOption[]
    mode: 'daily' | 'bills'
    editing?: Editable | null
}>()

const emit = defineEmits<{
    cancel: []
    saved: []
}>()

const page = usePage<{ today: string }>()

const choices = computed(() => props.mode === 'daily'
    ? [
        { value: 'expense', label: 'Expense' },
        { value: 'refund', label: 'Money back' },
    ]
    : [
        { value: 'bill', label: 'Bill' },
        { value: 'savings', label: 'Add to savings' },
        { value: 'savings_out', label: 'Take from savings' },
    ])

function defaultCategory(): number | null {
    const preferred = props.categories.find((category) => category.slug === (props.mode === 'daily' ? 'daily' : 'meralco'))

    return preferred?.id ?? props.categories[0]?.id ?? null
}

function entryFrom(item: Editable | null | undefined): string {
    if (!item) {
        return props.mode === 'daily' ? 'expense' : 'bill'
    }

    if (item.type === 'refund') {
        return 'refund'
    }

    if (item.type === 'savings') {
        return item.direction === 'out' ? 'savings_out' : 'savings'
    }

    return props.mode === 'daily' ? 'expense' : 'bill'
}

const form = useForm({
    occurred_on: page.props.today,
    description: '',
    amount: '',
    direction: 'out',
    type: props.mode === 'daily' ? 'daily' : 'bill',
    category_id: defaultCategory() as number | null,
    notes: '',
    entry: props.mode === 'daily' ? 'expense' : 'bill',
})

const isSavings = computed(() => form.entry === 'savings' || form.entry === 'savings_out')
const showCategory = computed(() => !isSavings.value && props.mode === 'bills')

const kindHint = computed(() => {
    if (form.entry === 'savings') {
        return 'Leftover pay going into the pile. Comes out of this month’s cash left.'
    }

    if (form.entry === 'savings_out') {
        return 'Money leaving the pile. Adds back to this month’s cash left.'
    }

    if (form.entry === 'bill') {
        return 'A bill paid from paycheck, like Meralco or Cursor.'
    }

    return ''
})

function applyEntry(entry: string) {
    form.entry = entry

    if (entry === 'refund') {
        form.type = 'refund'
        form.direction = 'in'
    } else if (entry === 'bill') {
        form.type = 'bill'
        form.direction = 'out'
    } else if (entry === 'savings') {
        form.type = 'savings'
        form.direction = 'in'
    } else if (entry === 'savings_out') {
        form.type = 'savings'
        form.direction = 'out'
    } else {
        form.type = 'daily'
        form.direction = 'out'
    }
}

function fill(item: Editable | null | undefined) {
    if (!item) {
        form.reset()
        form.occurred_on = page.props.today
        form.category_id = defaultCategory()
        applyEntry(props.mode === 'daily' ? 'expense' : 'bill')
        form.clearErrors()

        return
    }

    form.occurred_on = item.occurred_on
    form.description = item.description
    form.amount = String(item.amount)
    form.category_id = item.category_id
    form.notes = item.notes ?? ''
    applyEntry(entryFrom(item))
}

watch(() => props.editing, (item) => fill(item), { immediate: true })

function submit() {
    applyEntry(form.entry)
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.editing) {
                fill(null)
            }

            emit('saved')
        },
    }

    if (form.type === 'savings') {
        const savingsPayload = () => ({
            occurred_on: form.occurred_on,
            amount: form.amount,
            direction: form.direction,
            notes: form.description,
        })
        const savingsOptions = {
            ...options,
            onFinish: () => form.transform((data) => data),
        }

        if (props.editing?.source === 'savings') {
            form.transform(savingsPayload).put(`/savings/${props.editing.id}`, savingsOptions)
        } else {
            form.transform(savingsPayload).post('/savings', savingsOptions)
        }

        return
    }

    if (props.editing) {
        form.put(`/transactions/${props.editing.id}`, options)
    } else {
        form.post('/transactions', options)
    }
}
</script>

<template>
    <form class="grid gap-3" @submit.prevent="submit">
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="grid gap-1">
                <span class="kicker">Date</span>
                <input v-model="form.occurred_on" type="date" required class="field">
            </label>
            <label class="grid gap-1">
                <span class="kicker">Kind</span>
                <select v-model="form.entry" class="field" @change="applyEntry(form.entry)">
                    <option v-for="choice in choices" :key="choice.value" :value="choice.value">{{ choice.label }}</option>
                </select>
                <span v-if="kindHint" class="text-sm text-moss">{{ kindHint }}</span>
            </label>
            <label class="grid gap-1 sm:col-span-2">
                <span class="kicker">Item</span>
                <input v-model="form.description" type="text" required maxlength="500" class="field" :placeholder="isSavings ? 'Starting pile, leftover pay' : 'Taho, Meralco, gas'">
            </label>
            <label class="grid gap-1">
                <span class="kicker">Amount</span>
                <input v-model="form.amount" type="number" required min="0.01" step="0.01" inputmode="decimal" class="field" placeholder="0.00">
            </label>
            <label v-if="showCategory" class="grid gap-1">
                <span class="kicker">Category</span>
                <select v-model="form.category_id" required class="field">
                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
            </label>
        </div>
        <div class="flex flex-wrap items-center gap-2 pt-1">
            <button type="submit" class="btn" :disabled="form.processing">Save</button>
            <button v-if="editing" type="button" class="btn-ghost" @click="emit('cancel')">Cancel</button>
            <p v-if="form.errors.amount || form.errors.description || form.errors.occurred_on || form.errors.category_id" class="text-sm text-clay">
                {{ form.errors.amount || form.errors.description || form.errors.occurred_on || form.errors.category_id }}
            </p>
        </div>
    </form>
</template>
