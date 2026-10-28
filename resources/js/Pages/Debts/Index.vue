<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '../../Components/AppLayout.vue'
import DashPanel from '../../Components/DashPanel.vue'
import Modal from '../../Components/Modal.vue'
import OverviewTiles from '../../Components/OverviewTiles.vue'
import type { OverviewTile } from '../../Components/OverviewTiles.vue'
import PageHeader from '../../Components/PageHeader.vue'
import PageHero from '../../Components/PageHero.vue'
import PaySourceSelect from '../../Components/PaySourceSelect.vue'
import { confirmDelete } from '../../lib/confirm'
import DueWhenFields from '../../Components/DueWhenFields.vue'
import { pesos } from '../../lib/money'
import type { DueMode } from '../../lib/dueWhen'
import type { WalletRow } from '../../lib/wallets'

interface Payment {
    id: number
    occurred_on: string
    covers_on: string | null
    amount: number
    installment_no: number | null
    notes: string | null
    counts_in_spending: boolean
}

interface DebtItem {
    id: number
    name: string
    creditor: string | null
    item: string | null
    status: string
    principal: number
    paid: number
    balance: number
    progress: number
    is_monthly: boolean
    installment_amount: number | null
    installment_count: number | null
    installments_logged: number
    installments_paid: number
    installments_remaining: number | null
    notes: string | null
    due_mode: DueMode
    due_day: number | null
    due_label: string | null
    covers_on: string
    payments: Payment[]
}

const props = defineProps<{
    debts: DebtItem[]
    wallets: WalletRow[]
}>()

const page = usePage<{ today: string }>()
const adding = ref(false)
const editing = ref<DebtItem | null>(null)
const paying = ref<DebtItem | null>(null)
const viewingId = ref<number | null>(null)

const editOpen = computed({
    get: () => editing.value !== null,
    set: (open) => {
        if (!open) {
            editing.value = null
        }
    },
})

const payOpen = computed({
    get: () => paying.value !== null,
    set: (open) => {
        if (!open) {
            paying.value = null
        }
    },
})

const viewing = computed(() => props.debts.find((debt) => debt.id === viewingId.value) ?? null)

const viewingOpen = computed({
    get: () => viewing.value !== null,
    set: (open) => {
        if (!open) {
            viewingId.value = null
        }
    },
})


function prettyDay(iso: string | null) {
    if (!iso) {
        return ''
    }

    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}

function paymentForm(debt: DebtItem) {
    return useForm({
        kind: 'loan',
        target_id: debt.id,
        occurred_on: page.props.today,
        covers_on: debt.covers_on,
        amount: debt.installment_amount === null ? '' : String(debt.installment_amount),
        payment_method: props.wallets[0]?.slug ?? '',
        notes: '',
    })
}

const payments = ref<Record<number, ReturnType<typeof paymentForm>>>({})

function formFor(debt: DebtItem) {
    if (!payments.value[debt.id]) {
        payments.value[debt.id] = paymentForm(debt)
    }

    return payments.value[debt.id]
}

function pay(debt: DebtItem) {
    const form = formFor(debt)
    form.target_id = debt.id
    form.post('/pay', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('notes')
            form.amount = debt.installment_amount === null ? '' : String(debt.installment_amount)
            form.covers_on = debt.covers_on
            form.payment_method = props.wallets[0]?.slug ?? ''
            paying.value = null
        },
    })
}

function removePayment(debt: DebtItem, payment: Payment) {
    confirmDelete({
        title: 'Delete this payment?',
        message: `${prettyDay(payment.occurred_on)} · ${pesos(payment.amount)}`,
        run: () => router.delete(`/debts/${debt.id}/payments/${payment.id}`, { preserveScroll: true }),
    })
}

const createForm = useForm({
    name: '',
    creditor: '',
    item: '',
    principal: '',
    is_monthly: false,
    installment_amount: '',
    installment_count: '',
    notes: '',
    due_mode: null as DueMode,
    due_day: null as number | null,
})

function setCreateMonthly(monthly: boolean) {
    createForm.is_monthly = monthly
    if (!monthly) {
        createForm.installment_amount = ''
        createForm.installment_count = ''
    }
}

function createDebt() {
    createForm.post('/debts', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset()
            adding.value = false
        },
    })
}

function editForm(debt: DebtItem) {
    return useForm({
        name: debt.name,
        creditor: debt.creditor ?? '',
        item: debt.item ?? '',
        principal: String(debt.principal),
        is_monthly: debt.is_monthly,
        installment_amount: debt.installment_amount === null ? '' : String(debt.installment_amount),
        installment_count: debt.installment_count === null ? '' : String(debt.installment_count),
        notes: debt.notes ?? '',
        due_mode: debt.due_mode,
        due_day: debt.due_day,
    })
}

const editors = ref<Record<number, ReturnType<typeof editForm>>>({})

function editorFor(debt: DebtItem) {
    if (!editors.value[debt.id]) {
        editors.value[debt.id] = editForm(debt)
    }

    return editors.value[debt.id]
}

function setEditorMonthly(debt: DebtItem, monthly: boolean) {
    const form = editorFor(debt)
    form.is_monthly = monthly
    if (!monthly) {
        form.installment_amount = ''
        form.installment_count = ''
    }
}

function saveDebt(debt: DebtItem) {
    editorFor(debt).put(`/debts/${debt.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null
        },
    })
}

function removeDebt(debt: DebtItem) {
    confirmDelete({
        title: 'Delete this loan?',
        message: `${debt.name} and its payment history will be removed.`,
        run: () => router.delete(`/debts/${debt.id}`, { preserveScroll: true }),
    })
}

const remaining = computed(() => props.debts.reduce((sum, debt) => sum + debt.balance, 0))
const paidTotal = computed(() => props.debts.reduce((sum, debt) => sum + debt.paid, 0))
const principalTotal = computed(() => props.debts.reduce((sum, debt) => sum + debt.principal, 0))
const openCount = computed(() => props.debts.filter((debt) => debt.status !== 'paid').length)

const overview = computed<OverviewTile[]>(() => [
    { label: 'Left', value: pesos(remaining.value), valueClass: remaining.value > 0 ? 'text-clay' : 'text-palm-deep', icon: 'lock', wrap: 'bg-[#E8F1FF] text-income' },
    { label: 'Paid', value: pesos(paidTotal.value), icon: 'check', wrap: 'bg-[#E8F8EF] text-palm-deep' },
    { label: 'Principal', value: pesos(principalTotal.value), icon: 'loan', wrap: 'bg-[#EEEBFF] text-accent' },
    { label: 'Open', value: String(openCount.value), icon: 'bills', wrap: 'bg-[#FFF4E5] text-gold' },
])

function terms(debt: DebtItem) {
    if (debt.status === 'paid') {
        return 'Paid'
    }

    if (debt.is_monthly && debt.installment_count) {
        return `${debt.installments_paid}/${debt.installment_count}${debt.installment_amount ? ` · ${pesos(debt.installment_amount)}` : ''}`
    }

    return 'Personal'
}
</script>

<template>
    <Head title="Loan" />
    <AppLayout>
        <template #header>
            <PageHeader title="Loan" subtitle="Balances still open." />
        </template>
        <template #hero>
            <PageHero
                label="Left"
                :value="pesos(remaining)"
                :hint="`${openCount} open`"
            />
        </template>
        <DashPanel title="Overview" icon="chart" icon-wrap="bg-[#EEEBFF] text-accent">
            <div class="px-5 pb-5">
                <OverviewTiles :tiles="overview" />
            </div>
        </DashPanel>

        <DashPanel class="mt-4" title="Loans" icon="lock" icon-wrap="bg-[#E8F1FF] text-income">
            <template #actions>
                <button type="button" class="btn-ghost py-1 text-xs" @click="adding = true">Add</button>
            </template>
            <table class="data-table min-w-[40rem]">
                <caption class="sr-only">Loans</caption>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Terms</th>
                        <th scope="col" class="text-right">Paid</th>
                        <th scope="col" class="text-right">Left</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="debts.length === 0">
                        <td colspan="5" class="text-moss">No loans yet.</td>
                    </tr>
                    <tr v-for="debt in debts" :key="debt.id">
                        <td>
                            {{ debt.name }}
                            <span v-if="debt.creditor" class="text-moss"> · {{ debt.creditor }}</span>
                            <span v-if="debt.due_label" class="ml-2 text-xs text-moss">{{ debt.due_label }}</span>
                        </td>
                        <td class="whitespace-nowrap text-moss">{{ terms(debt) }}</td>
                        <td class="num text-right">{{ pesos(debt.paid) }}</td>
                        <td class="num text-right" :class="debt.balance > 0 ? 'text-clay' : 'text-palm-deep'">{{ pesos(debt.balance) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <span class="inline-flex gap-3">
                                <button v-if="debt.status !== 'paid'" type="button" class="row-pay" @click="paying = debt">Pay</button>
                                <button type="button" class="row-edit" @click="viewingId = debt.id">Logs</button>
                                <button type="button" class="row-edit" @click="editing = debt">Edit</button>
                                <button type="button" class="row-del ml-0" @click="removeDebt(debt)">Delete</button>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </DashPanel>

        <Modal v-model="adding" title="Add loan">
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="createDebt">
                <label class="grid gap-1">
                    <span class="kicker">Name</span>
                    <input v-model="createForm.name" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Loan from</span>
                    <input v-model="createForm.creditor" class="field" placeholder="A friend, Security Bank">
                </label>
                <label class="grid gap-1 sm:col-span-2">
                    <span class="kicker">Item</span>
                    <input v-model="createForm.item" class="field" placeholder="iPhone 17, credit card">
                </label>
                <div class="grid gap-1 sm:col-span-2">
                    <span class="kicker">Terms</span>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Terms of payment">
                        <button type="button" class="btn-ghost" :class="createForm.is_monthly ? 'is-on' : ''" @click="setCreateMonthly(true)">Monthly</button>
                        <button type="button" class="btn-ghost" :class="!createForm.is_monthly ? 'is-on' : ''" @click="setCreateMonthly(false)">Personal</button>
                    </div>
                </div>
                <template v-if="createForm.is_monthly">
                    <label class="grid gap-1">
                        <span class="kicker">Fixed payment</span>
                        <input v-model="createForm.installment_amount" type="number" min="0.01" step="0.01" required class="field">
                    </label>
                    <label class="grid gap-1">
                        <span class="kicker">Months</span>
                        <input v-model="createForm.installment_count" type="number" min="1" step="1" required class="field">
                    </label>
                    <p v-if="Number(createForm.installment_amount) > 0 && Number(createForm.installment_count) > 0" class="text-sm text-moss sm:col-span-2">
                        {{ createForm.installment_count }} × {{ pesos(Number(createForm.installment_amount)) }} = {{ pesos(Number(createForm.installment_amount) * Number(createForm.installment_count)) }}
                    </p>
                </template>
                <label v-else class="grid gap-1 sm:col-span-2">
                    <span class="kicker">How much</span>
                    <input v-model="createForm.principal" required type="number" min="0.01" step="0.01" class="field">
                </label>
                <div class="sm:col-span-2">
                    <DueWhenFields v-model:mode="createForm.due_mode" v-model:day="createForm.due_day" />
                </div>
                <label class="grid gap-1 sm:col-span-2">
                    <span class="kicker">Notes</span>
                    <textarea v-model="createForm.notes" rows="2" class="field" />
                </label>
                <div class="sm:col-span-2">
                    <button class="btn" :disabled="createForm.processing">Save</button>
                    <p v-if="createForm.errors.principal || createForm.errors.name || createForm.errors.installment_amount" class="mt-2 text-sm text-clay">
                        {{ createForm.errors.principal || createForm.errors.name || createForm.errors.installment_amount }}
                    </p>
                </div>
            </form>
        </Modal>

        <Modal v-if="editing" v-model="editOpen" :title="`Edit ${editing.name}`">
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="saveDebt(editing)">
                <label class="grid gap-1">
                    <span class="kicker">Name</span>
                    <input v-model="editorFor(editing).name" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Loan from</span>
                    <input v-model="editorFor(editing).creditor" class="field" placeholder="A friend, Security Bank">
                </label>
                <label class="grid gap-1 sm:col-span-2">
                    <span class="kicker">Item</span>
                    <input v-model="editorFor(editing).item" class="field">
                </label>
                <div class="grid gap-1 sm:col-span-2">
                    <span class="kicker">Terms</span>
                    <div class="flex flex-wrap gap-2" role="group" :aria-label="`Terms for ${editing.name}`">
                        <button type="button" class="btn-ghost" :class="editorFor(editing).is_monthly ? 'is-on' : ''" @click="setEditorMonthly(editing, true)">Monthly</button>
                        <button type="button" class="btn-ghost" :class="!editorFor(editing).is_monthly ? 'is-on' : ''" @click="setEditorMonthly(editing, false)">Personal</button>
                    </div>
                </div>
                <template v-if="editorFor(editing).is_monthly">
                    <label class="grid gap-1">
                        <span class="kicker">Fixed payment</span>
                        <input v-model="editorFor(editing).installment_amount" type="number" min="0.01" step="0.01" required class="field">
                    </label>
                    <label class="grid gap-1">
                        <span class="kicker">Months</span>
                        <input v-model="editorFor(editing).installment_count" type="number" min="1" step="1" required class="field">
                    </label>
                </template>
                <label v-else class="grid gap-1 sm:col-span-2">
                    <span class="kicker">How much</span>
                    <input v-model="editorFor(editing).principal" required type="number" min="0.01" step="0.01" class="field">
                </label>
                <div class="sm:col-span-2">
                    <DueWhenFields v-model:mode="editorFor(editing).due_mode" v-model:day="editorFor(editing).due_day" />
                </div>
                <label class="grid gap-1 sm:col-span-2">
                    <span class="kicker">Notes</span>
                    <textarea v-model="editorFor(editing).notes" rows="2" class="field" />
                </label>
                <div class="sm:col-span-2">
                    <button class="btn" :disabled="editorFor(editing).processing">Save</button>
                </div>
            </form>
        </Modal>

        <Modal v-if="viewing" v-model="viewingOpen" :title="`${viewing.name} payments`">
            <table class="data-table">
                <caption class="sr-only">Payments for {{ viewing.name }}</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col" class="text-right">Amount</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="viewing.payments.length === 0">
                        <td colspan="3" class="text-moss">No payments yet.</td>
                    </tr>
                    <tr v-for="payment in viewing.payments" :key="payment.id">
                        <td>
                            <template v-if="payment.installment_no">#{{ payment.installment_no }} · </template>
                            {{ prettyDay(payment.occurred_on) }}
                            <span v-if="payment.covers_on && payment.covers_on !== payment.occurred_on" class="text-moss"> · covers {{ prettyDay(payment.covers_on) }}</span>
                            <span v-if="payment.notes" class="text-moss"> · {{ payment.notes }}</span>
                        </td>
                        <td class="num text-right">{{ pesos(payment.amount) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <button type="button" class="row-del ml-0" @click="removePayment(viewing, payment)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </Modal>

        <Modal v-if="paying" v-model="payOpen" :title="`Pay ${paying.name}`">
            <form class="grid gap-3" @submit.prevent="pay(paying)">
                <label class="grid gap-1">
                    <span class="kicker">Paid on</span>
                    <input v-model="formFor(paying).occurred_on" type="date" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Covers</span>
                    <input v-model="formFor(paying).covers_on" type="date" required class="field">
                </label>
                <p v-if="formFor(paying).covers_on && formFor(paying).occurred_on < formFor(paying).covers_on" class="text-sm text-moss">
                    Paying in advance for {{ prettyDay(formFor(paying).covers_on) }}.
                </p>
                <label class="grid gap-1">
                    <span class="kicker">Amount</span>
                    <input v-model="formFor(paying).amount" type="number" required min="0.01" step="0.01" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Payment option</span>
                    <PaySourceSelect v-model="formFor(paying).payment_method" :wallets="wallets" />
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Note</span>
                    <input v-model="formFor(paying).notes" class="field">
                </label>
                <button class="btn w-fit" :disabled="formFor(paying).processing || !formFor(paying).payment_method">Log payment</button>
            </form>
        </Modal>

    </AppLayout>
</template>
