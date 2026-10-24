<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '../../Components/AppLayout.vue'
import PageHero from '../../Components/PageHero.vue'
import BusyOverlay from '../../Components/BusyOverlay.vue'
import CreditTerms from '../../Components/CreditTerms.vue'
import Modal from '../../Components/Modal.vue'
import PaySourceSelect from '../../Components/PaySourceSelect.vue'
import PeriodPicker from '../../Components/PeriodPicker.vue'
import type { PeriodValue } from '../../Components/PeriodPicker.vue'
import { pesos } from '../../lib/money'
import type { CreditOption, WalletRow } from '../../lib/wallets'

interface BillPayment {
    id: number
    occurred_on: string
    amount: number
    payment_method: string | null
    notes: string | null
    via_credit: boolean
    credit_months: number | null
    credit_monthly: number | null
}

interface BillRow {
    id: number
    name: string
    slug: string
    color: string
    plan: number
    spent: number
    paid: boolean
    payment: BillPayment | null
}

interface ComputedBillRow {
    id: string
    name: string
    slug: string
    category_id: number | null
    plan: number
    spent: number
    paid: boolean
    payment: BillPayment | null
}

interface LoanRow {
    id: number
    name: string
    creditor: string | null
    balance: number
    is_monthly: boolean
    installment_amount: number | null
    installments_remaining: number | null
    status: string
    due_this_period: boolean
    paid_this_period: boolean
    spent_this_period: number
}

interface CreditDueRow {
    id: string
    wallet_id: string
    wallet_name: string
    charge_id: string
    description: string
    n: number
    months: number
    due_on: string
    amount: number
    paid_on: string | null
    transaction_id: number | null
    overdue: boolean
    payable: boolean
}

interface CreditSheetRow {
    key: string
    name: string
    due: CreditDueRow | null
    bill: ComputedBillRow | null
    spent: number
    plan: number
    paid: boolean
}

interface PaymentRow {
    id: number
    occurred_on: string
    description: string
    amount: number
    direction: 'out' | 'in'
    type: string
    category: string | null
    category_id: number | null
    payment_method: string | null
    notes: string | null
    via_credit?: boolean
    credit_months?: number | null
    credit_monthly?: number | null
    due_id?: string | null
    source?: string
}

const props = defineProps<{
    period: PeriodValue
    pay_on: string
    bills: BillRow[]
    computed_bills: ComputedBillRow[]
    loans: LoanRow[]
    credit_dues: CreditDueRow[]
    wallets: WalletRow[]
    pay_sources: WalletRow[]
    payments: PaymentRow[]
    totals: { bills: number; loan: number; actual: number }
}>()

const credits = computed(() => props.wallets.filter((row): row is WalletRow & CreditOption => row.group === 'credit'))
const planBills = computed(() => props.bills.filter((bill) => bill.plan > 0))
const cashBills = computed(() => planBills.value.reduce((sum, bill) => sum + (bill.paid ? bill.spent : bill.plan), 0))
const creditDue = computed(() => props.computed_bills.reduce((sum, bill) => sum + bill.plan, 0))
const estimatedTotal = computed(() => Math.round((cashBills.value + creditDue.value + props.totals.loan) * 100) / 100)
const creditSheet = computed((): CreditSheetRow[] => {
    const used = new Set<string>()
    const rows: CreditSheetRow[] = []

    for (const bill of props.computed_bills) {
        const dues = props.credit_dues.filter((due) => due.wallet_id === bill.id)

        if (dues.length > 0) {
            for (const due of dues) {
                used.add(due.id)
                rows.push(creditDueRow(bill, due))
            }

            continue
        }

        if (bill.paid || bill.plan > 0) {
            rows.push({
                key: `computed-${bill.id}`,
                name: bill.name,
                due: null,
                bill,
                spent: bill.spent,
                plan: bill.plan,
                paid: bill.paid,
            })
        }
    }

    for (const due of props.credit_dues) {
        if (! used.has(due.id)) {
            rows.push(creditDueRow(null, due))
        }
    }

    return rows
})

function creditDueRow(bill: ComputedBillRow | null, due: CreditDueRow): CreditSheetRow {
    return {
        key: `due-${due.id}`,
        name: bill?.name ?? due.wallet_name,
        due,
        bill,
        spent: due.paid_on ? due.amount : 0,
        plan: due.amount,
        paid: due.paid_on !== null,
    }
}
const paying = ref<{
    kind: 'bill' | 'loan' | 'credit_due'
    id: number | string
    name: string
    amount: number
    paymentId: number | null
} | null>(null)
const page = useForm({
    kind: 'bill' as 'bill' | 'loan' | 'credit_due',
    target_id: '' as number | string,
    occurred_on: props.pay_on,
    amount: '',
    payment_method: props.pay_sources[0]?.slug ?? props.wallets[0]?.slug ?? '',
    notes: '',
    via_credit: false,
    credit_method: '',
    credit_months: 1,
    credit_monthly: '',
})

const busy = computed(() => page.processing)
const payWallets = computed(() => props.pay_sources)
const editingPay = computed(() => paying.value?.paymentId != null)
const payOpen = computed({
    get: () => paying.value !== null,
    set: (open) => {
        if (!open) {
            paying.value = null
        }
    },
})

function fillPay(
    kind: 'bill' | 'loan' | 'credit_due',
    id: number | string,
    name: string,
    amount: number,
    paymentId: number | null = null,
) {
    paying.value = { kind, id, name, amount, paymentId }
    page.kind = kind
    page.target_id = id
    page.occurred_on = props.pay_on
    page.amount = amount > 0 ? String(amount) : ''
    page.payment_method = props.pay_sources[0]?.slug ?? props.wallets[0]?.slug ?? ''
    page.notes = ''
    page.via_credit = false
    page.credit_method = credits.value[0]?.slug ?? ''
    page.credit_months = 1
    page.credit_monthly = amount > 0 ? String(amount) : ''
    page.clearErrors()
}

function openPay(kind: 'bill' | 'loan' | 'credit_due', id: number | string, name: string, amount: number) {
    fillPay(kind, id, name, amount)
}

function openBillEdit(bill: BillRow) {
    const payment = bill.payment

    if (payment === null) {
        return
    }

    fillPay('bill', bill.id, bill.name, payment.via_credit ? (payment.credit_monthly ?? payment.amount) : payment.amount, payment.id)
    page.occurred_on = payment.occurred_on
    page.payment_method = payment.via_credit
        ? (props.pay_sources[0]?.slug ?? '')
        : (payment.payment_method ?? props.pay_sources[0]?.slug ?? '')
    page.notes = payment.notes ?? ''
    page.via_credit = payment.via_credit
    page.credit_method = payment.via_credit ? (payment.payment_method ?? credits.value[0]?.slug ?? '') : (credits.value[0]?.slug ?? '')
    page.credit_months = payment.credit_months ?? 1
    page.credit_monthly = payment.credit_monthly == null ? String(payment.amount) : String(payment.credit_monthly)
}

function openComputed(bill: ComputedBillRow) {
    const dues = props.credit_dues.filter((due) => due.wallet_id === bill.id)
    const due = dues.find((row) => row.payable) ?? dues[0]

    if (due) {
        openDue(due)

        return
    }

    const payment = bill.payment

    if (payment === null || bill.category_id === null) {
        return
    }

    fillPay('bill', bill.category_id, bill.name, payment.via_credit ? (payment.credit_monthly ?? payment.amount) : payment.amount, payment.id)
    page.occurred_on = payment.occurred_on
    page.payment_method = payment.via_credit
        ? (props.pay_sources[0]?.slug ?? '')
        : (payment.payment_method ?? props.pay_sources[0]?.slug ?? '')
    page.notes = payment.notes ?? ''
    page.via_credit = payment.via_credit
    page.credit_method = payment.via_credit ? (payment.payment_method ?? credits.value[0]?.slug ?? '') : (credits.value[0]?.slug ?? '')
    page.credit_months = payment.credit_months ?? 1
    page.credit_monthly = payment.credit_monthly == null ? String(payment.amount) : String(payment.credit_monthly)
}

function openCredit(row: CreditSheetRow) {
    if (row.due) {
        openDue(row.due)

        return
    }

    if (row.bill) {
        openComputed(row.bill)
    }
}

function openDue(due: CreditDueRow) {
    fillPay(
        'credit_due',
        due.id,
        `${due.wallet_name} · ${due.description} ${due.n}/${due.months}`,
        due.amount,
        due.payable ? null : due.transaction_id,
    )

    if (due.paid_on !== null) {
        page.occurred_on = due.paid_on
        const row = props.payments.find((item) => item.id === due.transaction_id)

        if (row) {
            page.occurred_on = row.occurred_on
            page.payment_method = row.payment_method ?? page.payment_method
            page.notes = row.notes ?? ''
        }
    }
}

function openExisting(row: PaymentRow) {
    if (row.due_id) {
        const due = props.credit_dues.find((item) => item.id === row.due_id)

        if (due) {
            openDue(due)
        }

        return
    }

    if (row.type !== 'bill' || row.category_id === null) {
        return
    }

    const bill = props.bills.find((item) => item.id === row.category_id)

    fillPay('bill', row.category_id, bill?.name ?? row.description, row.via_credit ? (row.credit_monthly ?? row.amount) : row.amount, row.id)
    page.occurred_on = row.occurred_on
    page.payment_method = row.via_credit
        ? (props.pay_sources[0]?.slug ?? '')
        : (row.payment_method ?? props.pay_sources[0]?.slug ?? '')
    page.notes = row.notes ?? ''
    page.via_credit = Boolean(row.via_credit)
    page.credit_method = row.via_credit ? (row.payment_method ?? credits.value[0]?.slug ?? '') : (credits.value[0]?.slug ?? '')
    page.credit_months = row.credit_months ?? 1
    page.credit_monthly = row.credit_monthly == null ? String(row.amount) : String(row.credit_monthly)
}

function submitPay() {
    if (page.via_credit && paying.value?.kind === 'bill') {
        page.payment_method = page.credit_method
        page.amount = page.credit_monthly
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            paying.value = null
        },
    }

    if (paying.value?.paymentId != null) {
        page.put(`/pay/${paying.value.paymentId}`, options)

        return
    }

    page.post('/pay', options)
}

function walletName(slug: string | null) {
    return props.wallets.find((row) => row.slug === slug)?.name ?? slug ?? ''
}

function prettyDate(iso: string) {
    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
    })
}
</script>

<template>
    <Head title="Bills" />
    <AppLayout>
        <template #hero>
            <PageHero
                :eyebrow="`Bills · ${period.label}`"
                label="Paid this period"
                :value="pesos(totals.actual)"
            >
                <div class="hero-split">
                    <div>
                        <p class="text-xs text-white/70">Estimated total</p>
                        <p class="num mt-1 text-lg text-white">{{ pesos(estimatedTotal) }}</p>
                    </div>
                </div>
            </PageHero>
        </template>
        <BusyOverlay :show="busy" :label="editingPay ? 'Updating payment…' : 'Recording payment…'" />
        <dl class="card stat-grid stat-grid-3">
            <div>
                <dt>Bills</dt>
                <dd class="num">{{ pesos(cashBills) }}</dd>
            </div>
            <div>
                <dt>Credit</dt>
                <dd class="num">{{ pesos(creditDue) }}</dd>
            </div>
            <div>
                <dt>Loans</dt>
                <dd class="num">{{ pesos(totals.loan) }}</dd>
            </div>
        </dl>

        <section class="card mt-4 overflow-x-auto">
            <div class="sheet-head">
                <h2>Bills</h2>
                <PeriodPicker :period="period" base="/bills" />
            </div>
            <table class="data-table">
                <caption class="sr-only">Bills</caption>
                <thead class="border-y border-line text-moss">
                    <tr>
                        <th scope="col" class="px-3 py-1.5 font-medium">Name</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Paid</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Plan</th>
                        <th scope="col" class="px-3 py-1.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="planBills.length === 0">
                        <td colspan="4" class="px-3 py-3 text-moss">No bills in Plan yet.</td>
                    </tr>
                    <tr v-for="bill in planBills" :key="bill.id" class="border-t border-line hover:bg-mist/60">
                        <td class="px-3 py-1.5">
                            <span class="mr-2 inline-block size-2 rounded-full align-middle" :style="{ background: bill.color }" />
                            {{ bill.name }}
                            <span v-if="bill.paid" class="ml-2 text-xs text-palm-deep">Paid</span>
                        </td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">{{ pesos(bill.spent) }}</td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">{{ pesos(bill.plan) }}</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-right">
                            <button
                                v-if="bill.paid"
                                type="button"
                                class="text-moss hover:text-ink"
                                @click="openBillEdit(bill)"
                            >
                                Edit
                            </button>
                            <button
                                v-else
                                type="button"
                                class="text-accent hover:text-ink"
                                @click="openPay('bill', bill.id, bill.name, bill.plan)"
                            >
                                Pay
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card mt-4 overflow-x-auto">
            <div class="sheet-head">
                <h2>Credit</h2>
            </div>
            <table class="data-table">
                <caption class="sr-only">Credit</caption>
                <thead class="border-y border-line text-moss">
                    <tr>
                        <th scope="col" class="px-3 py-1.5 font-medium">Name</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 font-medium">Due</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Paid</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Plan</th>
                        <th scope="col" class="px-3 py-1.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="creditSheet.length === 0">
                        <td colspan="5" class="px-3 py-3 text-moss">No credit this period.</td>
                    </tr>
                    <tr v-for="row in creditSheet" :key="row.key" class="border-t border-line hover:bg-mist/60">
                        <td class="px-3 py-1.5">
                            {{ row.name }}
                            <span
                                v-if="row.due && row.due.description.toLowerCase() !== row.name.toLowerCase()"
                                class="ml-2 text-xs text-moss"
                            >{{ row.due.description }}</span>
                            <span v-if="row.paid" class="ml-2 text-xs text-palm-deep">Paid</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-1.5" :class="row.due?.overdue && row.due.payable ? 'text-clay' : 'text-moss'">
                            <template v-if="row.due">
                                {{ row.due.n }}/{{ row.due.months }} · {{ prettyDate(row.due.due_on) }}
                                <template v-if="row.due.overdue && row.due.payable"> · overdue</template>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">{{ pesos(row.spent) }}</td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">{{ pesos(row.plan) }}</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-right">
                            <button
                                v-if="row.paid"
                                type="button"
                                class="text-moss hover:text-ink"
                                @click="openCredit(row)"
                            >
                                Edit
                            </button>
                            <button
                                v-else-if="row.plan > 0"
                                type="button"
                                class="text-accent hover:text-ink"
                                @click="openCredit(row)"
                            >
                                Pay
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card mt-4 overflow-x-auto">
            <div class="sheet-head">
                <h2>Loans</h2>
            </div>
            <table class="data-table">
                <caption class="sr-only">Loans</caption>
                <thead class="border-y border-line text-moss">
                    <tr>
                        <th scope="col" class="px-3 py-1.5 font-medium">Name</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Paid</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Plan</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Left</th>
                        <th scope="col" class="px-3 py-1.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loans.length === 0">
                        <td colspan="5" class="px-3 py-3 text-moss">No open loans.</td>
                    </tr>
                    <tr v-for="loan in loans" :key="loan.id" class="border-t border-line hover:bg-mist/60">
                        <td class="px-3 py-1.5">
                            {{ loan.name }}
                            <span v-if="loan.installments_remaining != null" class="ml-2 text-xs text-moss">{{ loan.installments_remaining }} left</span>
                            <span v-if="loan.paid_this_period" class="ml-2 text-xs text-palm-deep">Paid</span>
                        </td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">{{ pesos(loan.spent_this_period) }}</td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">
                            {{ loan.installment_amount == null ? '—' : pesos(loan.installment_amount) }}
                        </td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">{{ pesos(loan.balance) }}</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-right">
                            <button
                                v-if="! loan.paid_this_period && loan.status !== 'paid'"
                                type="button"
                                class="text-accent hover:text-ink"
                                @click="openPay('loan', loan.id, loan.name, loan.installment_amount ?? 0)"
                            >
                                Pay
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card mt-4 overflow-x-auto">
            <div class="sheet-head">
                <h2>This period</h2>
            </div>
            <table class="data-table">
                <caption class="sr-only">This period</caption>
                <thead class="border-y border-line text-moss">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 font-medium">Date</th>
                        <th scope="col" class="px-3 py-1.5 font-medium">Paid</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Amount</th>
                        <th scope="col" class="px-3 py-1.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="payments.length === 0">
                        <td colspan="4" class="px-3 py-3 text-moss">Nothing paid in this view.</td>
                    </tr>
                    <tr v-for="row in payments" :key="`${row.source}-${row.id}`" class="border-t border-line hover:bg-mist/60">
                        <td class="whitespace-nowrap px-3 py-1.5 text-moss">{{ prettyDate(row.occurred_on) }}</td>
                        <td class="px-3 py-1.5">
                            {{ row.description }}
                            <span v-if="row.payment_method" class="text-moss"> · {{ walletName(row.payment_method) }}</span>
                        </td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">{{ pesos(row.amount) }}</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-right">
                            <button
                                v-if="row.type === 'bill'"
                                type="button"
                                class="text-moss hover:text-ink"
                                @click="openExisting(row)"
                            >
                                Edit
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <Modal v-if="paying" v-model="payOpen" :title="`${editingPay ? 'Edit' : 'Pay'} ${paying.name}`">
            <form class="grid gap-3" @submit.prevent="submitPay">
                <label class="grid gap-1">
                    <span class="kicker">Date</span>
                    <input v-model="page.occurred_on" type="date" required class="field">
                </label>
                <label v-if="! page.via_credit || paying.kind !== 'bill'" class="grid gap-1">
                    <span class="kicker">Amount</span>
                    <input v-model="page.amount" type="number" min="0.01" step="0.01" required class="field" :readonly="paying.kind === 'credit_due'">
                </label>
                <CreditTerms
                    v-if="paying.kind === 'bill'"
                    v-model:via-credit="page.via_credit"
                    v-model:credit-method="page.credit_method"
                    v-model:months="page.credit_months"
                    v-model:monthly="page.credit_monthly"
                    :credits="credits"
                />
                <label v-if="paying.kind !== 'bill' || ! page.via_credit" class="grid gap-1">
                    <span class="kicker">Payment option</span>
                    <PaySourceSelect v-model="page.payment_method" :wallets="payWallets" />
                </label>
                <label v-if="paying.kind !== 'credit_due'" class="grid gap-1">
                    <span class="kicker">Note</span>
                    <input v-model="page.notes" class="field">
                </label>
                <div class="flex gap-2">
                    <button class="btn" :disabled="page.processing || (page.via_credit ? !page.credit_method || !page.credit_monthly : !page.payment_method)">Save</button>
                    <button type="button" class="btn-ghost" @click="paying = null">Cancel</button>
                </div>
                <p v-if="page.errors.payment_method || page.errors.amount" class="text-sm text-clay">
                    {{ page.errors.payment_method || page.errors.amount }}
                </p>
            </form>
        </Modal>
    </AppLayout>
</template>
