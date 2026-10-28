<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '../../Components/AppLayout.vue'
import DashPanel from '../../Components/DashPanel.vue'
import Modal from '../../Components/Modal.vue'
import PageHeader from '../../Components/PageHeader.vue'
import PageHero from '../../Components/PageHero.vue'
import PeriodPicker from '../../Components/PeriodPicker.vue'
import type { PeriodValue } from '../../Components/PeriodPicker.vue'
import { confirmDelete } from '../../lib/confirm'
import { pesos } from '../../lib/money'

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

const viewingAll = computed(() => props.period.span === 'all')
const latest = computed(() => props.entries[0] ?? null)

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

function rowLabel(entry: Entry) {
    if (entry.notes) {
        return entry.notes
    }

    if (entry.opening) {
        return 'Starting balance'
    }

    return entry.direction === 'out' ? 'Took out' : 'Deposit'
}
</script>

<template>
    <Head title="Savings" />
    <AppLayout>
        <template #header>
            <PageHeader title="Savings" :subtitle="`Deposits and take-outs · ${period.label}`">
                <PeriodPicker :period="period" allow-all base="/savings" tone="toolbar" />
            </PageHeader>
        </template>

        <template #hero>
            <PageHero
                label="Saved"
                :value="pesos(balance)"
                :hint="latest ? `Last ${prettyDay(latest.occurred_on)}` : null"
            >
                <p v-if="!viewingAll">This period {{ pesos(month_saved) }}</p>
            </PageHero>
        </template>

        <DashPanel title="Log" icon="savings" icon-wrap="bg-[#E8F8EF] text-palm-deep">
            <template #actions>
                <button type="button" class="btn-ghost py-1 text-xs" @click="openAdd">Add</button>
            </template>
            <table class="data-table">
                <caption class="sr-only">Savings log</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Note</th>
                        <th scope="col" class="text-right">Amount</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="entries.length === 0">
                        <td colspan="4" class="py-8 text-center text-moss">No deposits in this view.</td>
                    </tr>
                    <tr v-for="entry in entries" :key="entry.id">
                        <td class="whitespace-nowrap">{{ prettyDay(entry.occurred_on) }}</td>
                        <td>{{ rowLabel(entry) }}</td>
                        <td class="num text-right" :class="entry.direction === 'out' ? 'text-clay' : 'text-palm-deep'">
                            {{ entry.direction === 'out' ? '−' : '' }}{{ pesos(entry.amount) }}
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <button type="button" class="row-edit" @click="openEdit(entry)">Edit</button>
                            <button type="button" class="row-del" @click="removeEntry(entry)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </DashPanel>

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
