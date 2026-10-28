<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '../../Components/AppLayout.vue'
import DashPanel from '../../Components/DashPanel.vue'
import OverviewTiles from '../../Components/OverviewTiles.vue'
import type { OverviewTile } from '../../Components/OverviewTiles.vue'
import PageHeader from '../../Components/PageHeader.vue'
import PageHero from '../../Components/PageHero.vue'
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
    covers_on?: string | null
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
    due_label: string | null
    covers_on: string
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
    due_label: string | null
    covers_on: string
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
    advance?: boolean
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
    covers_on?: string | null
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
    advance_dues: CreditDueRow[]
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

const overview = computed<OverviewTile[]>(() => [
    { label: 'Bills', value: pesos(cashBills.value), icon: 'bills', wrap: 'bg-[#EEEBFF] text-accent' },
    { label: 'Credit', value: pesos(creditDue.value), icon: 'wallet', wrap: 'bg-[#FFF1E8] text-[#E67E22]' },
    { label: 'Loans', value: pesos(props.totals.loan), icon: 'lock', wrap: 'bg-[#E8F1FF] text-income' },
])
function statementKey(due: CreditDueRow) {
    return `${due.wallet_id}|${due.due_on}`
}

function mergeStatement(dues: CreditDueRow[]): CreditDueRow {
    const list = [...dues].sort((left, right) => left.id.localeCompare(right.id))
    const unpaid = list.filter((due) => due.payable)
    const amount = list.reduce((sum, due) => sum + due.amount, 0)
    const unpaidAmount = unpaid.reduce((sum, due) => sum + due.amount, 0)
    const paid = unpaid.length === 0
    const head = unpaid[0] ?? list[0]

    return {
        ...head,
        amount: paid ? amount : unpaidAmount,
        description: '',
        n: 1,
        months: 1,
        payable: ! paid,
        paid_on: paid ? (list.find((due) => due.paid_on)?.paid_on ?? null) : null,
        overdue: list.some((due) => due.overdue && due.payable),
        transaction_id: paid ? (list.find((due) => due.transaction_id)?.transaction_id ?? null) : null,
    }
}

function statementRows(dues: CreditDueRow[]): CreditDueRow[] {
    const groups = new Map<string, CreditDueRow[]>()

    for (const due of dues) {
        const key = statementKey(due)
        const list = groups.get(key) ?? []
        list.push(due)
        groups.set(key, list)
    }

    return [...groups.values()].map((list) => mergeStatement(list))
}

const creditSheet = computed((): CreditSheetRow[] => {
    const used = new Set<string>()
    const rows: CreditSheetRow[] = []

    for (const bill of props.computed_bills) {
        const dues = statementRows(props.credit_dues.filter((due) => due.wallet_id === bill.id))

        if (dues.length > 0) {
            for (const due of dues) {
                used.add(statementKey(due))
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

    for (const due of statementRows(props.credit_dues)) {
        const key = statementKey(due)

        if (! used.has(key)) {
            used.add(key)
            rows.push(creditDueRow(null, due))
        }
    }

    for (const due of statementRows(props.advance_dues ?? [])) {
        const key = statementKey(due)

        if (! used.has(key)) {
            used.add(key)
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
    covers_on: props.pay_on,
    amount: '',
    payment_method: props.pay_sources[0]?.slug ?? props.wallets[0]?.slug ?? '',
    notes: '',
    via_credit: false,
    credit_method: '',
    credit_months: 1,
    credit_monthly: '',
})

const payWallets = computed(() => props.pay_sources)
const editingPay = computed(() => paying.value?.paymentId != null)
const isAdvancePay = computed(() => page.covers_on !== '' && page.occurred_on < page.covers_on)
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
    coversOn: string | null = null,
) {
    paying.value = { kind, id, name, amount, paymentId }
    page.kind = kind
    page.target_id = id
    page.occurred_on = props.pay_on
    page.covers_on = coversOn ?? props.pay_on
    page.amount = amount > 0 ? String(amount) : ''
    page.payment_method = props.pay_sources[0]?.slug ?? props.wallets[0]?.slug ?? ''
    page.notes = ''
    page.via_credit = false
    page.credit_method = credits.value[0]?.slug ?? ''
    page.credit_months = 1
    page.credit_monthly = amount > 0 ? String(amount) : ''
    page.clearErrors()
}

function openPay(kind: 'bill' | 'loan' | 'credit_due', id: number | string, name: string, amount: number, coversOn?: string) {
    fillPay(kind, id, name, amount, null, coversOn ?? null)
}

function openBillEdit(bill: BillRow) {
    const payment = bill.payment

    if (payment === null) {
        return
    }

    fillPay('bill', bill.id, bill.name, payment.via_credit ? (payment.credit_monthly ?? payment.amount) : payment.amount, payment.id, payment.covers_on ?? bill.covers_on)
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
    const dues = statementRows(props.credit_dues.filter((due) => due.wallet_id === bill.id))
    const due = dues.find((row) => row.payable) ?? dues[0]

    if (due) {
        openDue(due)

        return
    }

    const payment = bill.payment

    if (payment === null || bill.category_id === null) {
        return
    }

    fillPay('bill', bill.category_id, bill.name, payment.via_credit ? (payment.credit_monthly ?? payment.amount) : payment.amount, payment.id, payment.covers_on ?? null)
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
        `${due.wallet_name} · ${prettyDate(due.due_on)}`,
        due.amount,
        due.payable ? null : due.transaction_id,
        due.due_on,
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
        const match = [...props.credit_dues, ...(props.advance_dues ?? [])].find((item) => item.id === row.due_id)

        if (match) {
            const group = statementRows(
                [...props.credit_dues, ...(props.advance_dues ?? [])].filter(
                    (item) => item.wallet_id === match.wallet_id && item.due_on === match.due_on,
                ),
            )[0]

            openDue(group ?? match)
        }

        return
    }

    if (row.type !== 'bill' || row.category_id === null) {
        return
    }

    const bill = props.bills.find((item) => item.id === row.category_id)

    fillPay('bill', row.category_id, bill?.name ?? row.description, row.via_credit ? (row.credit_monthly ?? row.amount) : row.amount, row.id, row.covers_on ?? bill?.covers_on ?? null)
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

function halfHref(half?: number) {
    const params = new URLSearchParams({
        span: props.period.span ?? 'month',
        year: String(props.period.year),
        month: String(props.period.month),
    })

    if (half) {
        params.set('half', String(half))
    }

    return `/bills?${params.toString()}`
}
</script>

<template>
    <Head title="Bills" />
    <AppLayout>
        <template #header>
            <PageHeader title="Bills" :subtitle="period.label">
                <PeriodPicker :period="period" base="/bills" tone="toolbar" />
            </PageHeader>
        </template>
        <template #hero>
            <PageHero
                label="Paid this period"
                :value="pesos(totals.actual)"
                :hint="`Estimated ${pesos(estimatedTotal)}`"
            />
        </template>
        <DashPanel title="Overview" icon="chart" icon-wrap="bg-[#EEEBFF] text-accent">
            <template #actions>
                <div class="flex gap-1" role="group" aria-label="Cutoff">
                    <Link :href="halfHref()" preserve-scroll class="btn-ghost py-1 text-xs" :class="period.half === null ? 'is-on' : ''">Month</Link>
                    <Link :href="halfHref(1)" preserve-scroll class="btn-ghost py-1 text-xs" :class="period.half === 1 ? 'is-on' : ''">1–15</Link>
                    <Link :href="halfHref(2)" preserve-scroll class="btn-ghost py-1 text-xs" :class="period.half === 2 ? 'is-on' : ''">16–end</Link>
                </div>
            </template>
            <div class="px-5 pb-5">
                <OverviewTiles :tiles="overview" />
            </div>
        </DashPanel>

        <DashPanel class="mt-4" title="Bills" icon="bills">
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
                            <span v-if="bill.due_label" class="ml-2 text-xs text-moss">{{ bill.due_label }}</span>
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
                                @click="openPay('bill', bill.id, bill.name, bill.plan, bill.covers_on)"
                            >
                                Pay
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </DashPanel>

        <DashPanel class="mt-4" title="Credit" icon="wallet" icon-wrap="bg-[#FFF1E8] text-[#E67E22]">
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
                            <span v-if="row.due?.advance" class="ml-2 text-xs text-accent">Advance</span>
                            <span v-if="row.paid" class="ml-2 text-xs text-palm-deep">Paid</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-1.5" :class="row.due?.overdue && row.due.payable ? 'text-clay' : 'text-moss'">
                            <template v-if="row.due">
                                {{ prettyDate(row.due.due_on) }}
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
        </DashPanel>

        <DashPanel class="mt-4" title="Loans" icon="lock" icon-wrap="bg-[#E8F1FF] text-income">
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
                            <span v-if="loan.due_label" class="ml-2 text-xs text-moss">{{ loan.due_label }}</span>
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
                                @click="openPay('loan', loan.id, loan.name, loan.installment_amount ?? 0, loan.covers_on)"
                            >
                                Pay
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </DashPanel>

        <DashPanel class="mt-4" title="This period" icon="clock" icon-wrap="bg-[#E8F1FF] text-income">
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
                            <span v-if="row.covers_on && row.covers_on !== row.occurred_on" class="text-moss"> · covers {{ prettyDate(row.covers_on) }}</span>
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
        </DashPanel>

        <Modal v-if="paying" v-model="payOpen" :title="`${editingPay ? 'Edit' : 'Pay'} ${paying.name}`">
            <form class="grid gap-3" @submit.prevent="submitPay">
                <label class="grid gap-1">
                    <span class="kicker">Paid on</span>
                    <input v-model="page.occurred_on" type="date" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Covers</span>
                    <input
                        v-model="page.covers_on"
                        type="date"
                        required
                        class="field"
                        :readonly="paying.kind === 'credit_due'"
                    >
                </label>
                <p v-if="isAdvancePay" class="text-sm text-moss">
                    Paying in advance for {{ prettyDate(page.covers_on) }}.
                </p>
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
                    :occurred-on="page.occurred_on"
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
