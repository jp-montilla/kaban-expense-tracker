<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '../../Components/AppLayout.vue'
import PageHero from '../../Components/PageHero.vue'
import BusyOverlay from '../../Components/BusyOverlay.vue'
import Modal from '../../Components/Modal.vue'
import PeriodPicker from '../../Components/PeriodPicker.vue'
import type { PeriodValue } from '../../Components/PeriodPicker.vue'
import { confirmDelete } from '../../lib/confirm'
import { pesos, tone } from '../../lib/money'

interface Entry {
    id: number
    occurred_on: string
    amount: number
    direction: 'in' | 'out'
    notes: string | null
    opening: boolean
    toward_budget: number
}

const props = defineProps<{
    period: PeriodValue
    entries: Entry[]
    month_saved: number
    cash_left: number | null
    balance: number
}>()

const page = usePage<{ today: string }>()

const adding = ref(false)

const form = useForm({
    occurred_on: page.props.today,
    amount: '',
    notes: '',
})

const editing = ref<Entry | null>(null)

const editOpen = computed({
    get: () => editing.value !== null,
    set: (open) => {
        if (!open) {
            editing.value = null
        }
    },
})

const editForm = useForm({
    occurred_on: page.props.today,
    amount: '',
    notes: '',
})

const busy = computed(() => form.processing || editForm.processing)
const viewingAll = computed(() => props.period.span === 'all')

function openAdd() {
    form.occurred_on = page.props.today
    form.amount = ''
    form.notes = ''
    form.clearErrors()
    adding.value = true
}

function addEntry() {
    form.post('/savings', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('amount', 'notes')
            form.occurred_on = page.props.today
            adding.value = false
        },
    })
}

function openEdit(entry: Entry) {
    editForm.occurred_on = entry.occurred_on
    editForm.amount = String(entry.amount)
    editForm.notes = entry.notes ?? ''
    editForm.clearErrors()
    editing.value = entry
}

function saveEntry() {
    if (! editing.value) {
        return
    }

    editForm.put(`/savings/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null
        },
    })
}

function removeEntry(entry: Entry) {
    confirmDelete({
        title: 'Delete this savings?',
        message: `${prettyDay(entry.occurred_on)} · ${pesos(entry.amount)}`,
        run: () => router.delete(`/savings/${entry.id}`, { preserveScroll: true }),
    })
}

function prettyDay(iso: string) {
    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

function kindLabel(entry: Entry) {
    if (entry.opening) {
        return 'Starting pile'
    }

    return entry.direction === 'in' ? 'Put in' : 'Took out'
}
</script>

<template>
    <Head title="Savings" />
    <AppLayout>
        <template #hero>
            <PageHero
                :eyebrow="`Savings · ${period.label}`"
                label="Pile"
                :value="pesos(balance)"
                :pill="viewingAll ? `${entries.length} entries` : `This period ${pesos(month_saved)}`"
            />
        </template>
        <BusyOverlay :show="busy" label="Saving…" />
        <dl class="card stat-grid">
            <div>
                <dt>{{ viewingAll ? 'Put in' : 'This period' }}</dt>
                <dd class="num">{{ pesos(month_saved) }}</dd>
            </div>
            <div>
                <dt>Pile</dt>
                <dd class="num">{{ pesos(balance) }}</dd>
            </div>
            <div>
                <dt>Budget left</dt>
                <dd class="num" :class="tone(cash_left)">{{ pesos(cash_left) }}</dd>
            </div>
            <div>
                <dt>Entries</dt>
                <dd class="num">{{ entries.length }}</dd>
            </div>
        </dl>

        <section class="card mt-4 overflow-x-auto">
            <div class="sheet-head">
                <h2>{{ viewingAll ? 'All savings' : 'This period' }}</h2>
                <div class="flex items-center gap-2">
                    <PeriodPicker :period="period" allow-all base="/savings" />
                    <button type="button" class="btn-ghost py-1 text-xs" @click="openAdd">Add</button>
                </div>
            </div>
            <table class="data-table">
                <caption class="sr-only">Savings</caption>
                <thead>
                    <tr>
                        <th scope="col">Kind</th>
                        <th scope="col">Date</th>
                        <th scope="col" class="text-right">Amount</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="entries.length === 0">
                        <td colspan="4" class="text-moss">Nothing saved in this view yet.</td>
                    </tr>
                    <tr v-for="entry in entries" :key="entry.id">
                        <td>
                            {{ kindLabel(entry) }}
                            <span v-if="entry.notes" class="text-moss"> · {{ entry.notes }}</span>
                        </td>
                        <td class="whitespace-nowrap text-moss">{{ prettyDay(entry.occurred_on) }}</td>
                        <td class="num text-right">{{ pesos(entry.amount) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <button type="button" class="row-edit" @click="openEdit(entry)">Edit</button>
                            <button type="button" class="row-del" @click="removeEntry(entry)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <Modal v-if="adding" v-model="adding" title="Add savings">
            <form class="grid gap-3" @submit.prevent="addEntry">
                <label class="grid gap-1">
                    <span class="kicker">Amount</span>
                    <input v-model="form.amount" type="number" min="0.01" step="0.01" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Date</span>
                    <input v-model="form.occurred_on" type="date" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Note</span>
                    <input v-model="form.notes" class="field" placeholder="October leftover">
                </label>
                <button class="btn w-fit" :disabled="form.processing">Save</button>
                <p v-if="form.errors.amount" class="text-sm text-clay">{{ form.errors.amount }}</p>
            </form>
        </Modal>

        <Modal v-if="editing" v-model="editOpen" title="Edit savings">
            <form class="grid gap-3" @submit.prevent="saveEntry">
                <label class="grid gap-1">
                    <span class="kicker">Amount</span>
                    <input v-model="editForm.amount" type="number" min="0.01" step="0.01" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Date</span>
                    <input v-model="editForm.occurred_on" type="date" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Note</span>
                    <input v-model="editForm.notes" class="field" placeholder="October leftover">
                </label>
                <p v-if="editing.opening" class="text-sm text-moss">
                    Keep the note starting with “Starting” if this is the pile that should not touch Budget left.
                </p>
                <button class="btn w-fit" :disabled="editForm.processing">Save</button>
                <p v-if="editForm.errors.amount || editForm.errors.occurred_on" class="text-sm text-clay">
                    {{ editForm.errors.amount || editForm.errors.occurred_on }}
                </p>
            </form>
        </Modal>
    </AppLayout>
</template>
