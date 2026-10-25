<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '../../Components/AppLayout.vue'
import PageHero from '../../Components/PageHero.vue'
import DayLogForm from '../../Components/DayLogForm.vue'
import Modal from '../../Components/Modal.vue'
import BusyOverlay from '../../Components/BusyOverlay.vue'
import PeriodPicker from '../../Components/PeriodPicker.vue'
import type { PeriodValue } from '../../Components/PeriodPicker.vue'
import TransactionForm from '../../Components/TransactionForm.vue'
import { confirmDelete } from '../../lib/confirm'
import { pesos } from '../../lib/money'

interface Row {
    id: number
    occurred_on: string
    description: string
    notes: string | null
    amount: number
    direction: 'out' | 'in'
    type: string
    category_id: number | null
    category: string | null
    color: string | null
    via_credit?: boolean
    payment_method?: string | null
    credit_months?: number | null
    source?: 'transaction' | 'savings'
}

interface DayGroup {
    date: string
    items: Row[]
    total: number
}

const props = defineProps<{
    mode: 'daily' | 'bills'
    period: PeriodValue
    transactions: Row[]
    totals: { bills: number; loan: number; daily: number; actual: number }
    categories: Array<{ id: number; name: string; slug: string; kind: string; color: string }>
}>()

const adding = ref(false)
const editing = ref<Row | null>(null)
const editingDay = ref<DayGroup | null>(null)

const editOpen = computed({
    get: () => editing.value !== null,
    set: (open) => {
        if (!open) {
            editing.value = null
        }
    },
})

const editDayOpen = computed({
    get: () => editingDay.value !== null,
    set: (open) => {
        if (!open) {
            editingDay.value = null
        }
    },
})

const busy = ref(false)
const title = computed(() => props.mode === 'bills' ? 'Bills' : 'Daily')
const base = computed(() => props.mode === 'bills' ? '/bills' : '/log')

const days = computed((): DayGroup[] => {
    const groups = new Map<string, Row[]>()

    for (const row of props.transactions) {
        const items = groups.get(row.occurred_on) ?? []
        items.push(row)
        groups.set(row.occurred_on, items)
    }

    return [...groups.entries()].map(([date, items]) => ({
        date,
        items: [...items].sort((left, right) => left.id - right.id),
        total: items.reduce((sum, row) => {
            if (row.via_credit) {
                return sum
            }

            return sum + (row.direction === 'in' ? -row.amount : row.amount)
        }, 0),
    }))
})

function remove(row: Row) {
    confirmDelete({
        title: 'Delete this entry?',
        message: `${row.description} · ${pesos(row.amount)}`,
        run: () => {
            const path = row.source === 'savings' ? `/savings/${row.id}` : `/transactions/${row.id}`

            busy.value = true
            router.delete(path, {
                preserveScroll: true,
                onFinish: () => {
                    busy.value = false
                },
                onSuccess: () => {
                    if (editing.value?.id === row.id) {
                        editing.value = null
                    }
                },
            })
        },
    })
}

function removeDay(day: DayGroup) {
    confirmDelete({
        title: 'Delete this day?',
        message: `${prettyDate(day.date)} · ${pesos(day.total)}`,
        run: () => {
            busy.value = true
            router.delete(`/log/${day.date}`, {
                preserveScroll: true,
                onFinish: () => {
                    busy.value = false
                },
                onSuccess: () => {
                    if (editingDay.value?.date === day.date) {
                        editingDay.value = null
                    }
                },
            })
        },
    })
}

function prettyDate(iso: string) {
    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-PH', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    })
}

function amountClass(row: Row): string {
    if (row.type === 'savings') {
        return row.direction === 'in' ? 'text-palm-deep' : 'text-clay'
    }

    return row.direction === 'in' ? 'text-palm-deep' : ''
}

function amountLabel(row: Row): string {
    if (row.direction === 'in') {
        return `+${pesos(row.amount)}`
    }

    if (row.type === 'savings') {
        return `−${pesos(row.amount)}`
    }

    return pesos(row.amount)
}
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <template #hero>
            <PageHero
                :eyebrow="`${title} · ${period.label}`"
                :label="mode === 'bills' ? 'Bills and loans' : 'Spent'"
                :value="pesos(mode === 'bills' ? totals.bills + totals.loan : totals.daily)"
                :pill="`${transactions.length} ${transactions.length === 1 ? 'line' : 'lines'}`"
            />
        </template>
        <BusyOverlay :show="busy" label="Updating…" />

        <section class="card min-w-0 overflow-hidden">
            <div class="sheet-head flex-wrap">
                <h2>{{ title }}</h2>
                <div class="flex min-w-0 flex-wrap items-center justify-end gap-2">
                    <PeriodPicker :period="period" :base="base" :month-only="mode === 'daily'" />
                    <button type="button" class="btn-ghost py-1 text-xs" @click="editingDay = null; adding = true">Add</button>
                </div>
            </div>
            <p v-if="transactions.length === 0" class="px-3 py-3 text-sm text-moss">Nothing in {{ period.label }} yet.</p>
            <ul v-else-if="mode === 'daily'" class="divide-y divide-line">
                <li v-for="day in days" :key="day.date" class="px-3 py-2.5">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="min-w-0 text-sm text-moss">{{ prettyDate(day.date) }}</p>
                        <p class="num shrink-0 text-sm" :class="day.total < 0 ? 'text-palm-deep' : ''">{{ pesos(day.total) }}</p>
                    </div>
                    <ul class="mt-1 grid gap-1">
                        <li v-for="item in day.items" :key="item.id" class="flex items-baseline justify-between gap-3">
                            <span class="min-w-0 break-words">
                                {{ item.description }}
                                <span v-if="item.via_credit" class="text-moss"> · {{ item.payment_method }} {{ item.credit_months }} mo</span>
                                <span v-else-if="item.notes" class="text-moss"> · {{ item.notes }}</span>
                            </span>
                            <span class="num shrink-0 text-moss" :class="amountClass(item)">{{ amountLabel(item) }}</span>
                        </li>
                    </ul>
                    <div class="mt-1.5 text-right">
                        <button type="button" class="row-edit" @click="adding = false; editingDay = day">Edit</button>
                        <button type="button" class="row-del" @click="removeDay(day)">Delete</button>
                    </div>
                </li>
            </ul>
            <table v-else class="data-table">
                <caption class="sr-only">{{ title }} for {{ period.label }}</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Item</th>
                        <th scope="col">Category</th>
                        <th scope="col" class="text-right">Amount</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in transactions" :key="`${row.source ?? 'transaction'}-${row.id}`">
                        <td class="text-moss">{{ prettyDate(row.occurred_on) }}</td>
                        <td>
                            {{ row.description }}
                            <span v-if="row.notes" class="text-moss"> · {{ row.notes }}</span>
                        </td>
                        <td class="text-moss">
                            <template v-if="row.type === 'savings'">
                                {{ row.direction === 'in' ? 'Add to savings' : 'Take from savings' }}
                            </template>
                            <template v-else>
                                {{ row.category }}
                                <span v-if="row.direction === 'in'"> · money back</span>
                            </template>
                        </td>
                        <td class="num text-right" :class="amountClass(row)">
                            {{ amountLabel(row) }}
                        </td>
                        <td class="text-right">
                            <button type="button" class="row-edit" @click="editing = row">Edit</button>
                            <button type="button" class="row-del" @click="remove(row)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <Modal v-if="adding" v-model="adding" :title="mode === 'bills' ? 'Add' : 'Log'" size="lg">
            <DayLogForm v-if="mode === 'daily'" @saved="adding = false" />
            <TransactionForm v-else :categories="categories" :mode="mode" @saved="adding = false" />
        </Modal>

        <Modal v-if="editingDay" v-model="editDayOpen" :title="`Edit ${prettyDate(editingDay.date)}`" size="lg">
            <DayLogForm :occurred-on="editingDay.date" :items="editingDay.items" @cancel="editingDay = null" @saved="editingDay = null" />
        </Modal>

        <Modal v-if="editing" v-model="editOpen" title="Edit entry">
            <TransactionForm
                :categories="categories"
                :mode="mode"
                :editing="editing"
                @cancel="editing = null"
                @saved="editing = null"
            />
        </Modal>

    </AppLayout>
</template>
