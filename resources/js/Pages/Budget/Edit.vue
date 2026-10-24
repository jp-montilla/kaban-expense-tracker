<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '../../Components/AppLayout.vue'
import PageHero from '../../Components/PageHero.vue'
import BusyOverlay from '../../Components/BusyOverlay.vue'
import Modal from '../../Components/Modal.vue'
import { confirmDelete } from '../../lib/confirm'
import { pesos } from '../../lib/money'
import { emptyPlans, groupedWallets, type WalletGroup, type WalletPlan, type WalletRow } from '../../lib/wallets'

interface SalaryRow {
    id: number
    effective_on: string
    amount: number
    pay_cycle?: 'cutoff' | 'month'
    notes: string | null
}


interface BonusRow {
    id: number
    received_on: string
    amount: number
    name: string
    notes: string | null
}

interface BillRow {
    id: number
    name: string
    slug: string
    monthly_plan: number
    color: string
}

interface ComputedBillRow {
    id: string
    name: string
    slug: string
    monthly_plan: number
}

interface LoanRow {
    id: number
    name: string
    creditor: string | null
    balance: number
    is_monthly: boolean
    installment_amount: number | null
    installment_count: number | null
    installments_paid: number
    installments_remaining: number | null
}

const props = defineProps<{
    budget_month: string
    previous_pay_label: string
    current_pay_label: string
    first_cutoff: number
    second_cutoff: number
    month_pay: number
    pay_mode: 'cutoff' | 'lagged' | 'month'
    budget_cycle: 'month' | 'cutoff'
    wallets: WalletRow[]
    living_plan: number
    bill_plan: number
    loan_plan: number
    spending_plan: number
    left_if_followed: number
    salaries: SalaryRow[]
    bonuses: BonusRow[]
    bills: BillRow[]
    computed_bills: ComputedBillRow[]
    loans: LoanRow[]
}>()

const page = usePage<{ today: string }>()

const livingForm = useForm({
    living_plan: props.living_plan,
    budget_cycle: props.budget_cycle,
})

watch(() => [props.living_plan, props.budget_cycle], () => {
    livingForm.living_plan = props.living_plan
    livingForm.budget_cycle = props.budget_cycle
})

const salaryForm = useForm({
    effective_on: page.props.today,
    amount: '',
    pay_cycle: 'cutoff' as 'cutoff' | 'month',
    notes: '',
})

const walletForm = useForm({
    name: '',
    group: 'wallet' as WalletGroup,
    due_day: 15 as number | null,
    plans: emptyPlans() as WalletPlan[],
})

const bonusForm = useForm({
    received_on: page.props.today,
    amount: '',
    name: 'Christmas bonus',
    notes: '',
})

const billForm = useForm({
    name: '',
    monthly_plan: '',
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const showPay = ref(false)
const showBonus = ref(false)
const showBill = ref(false)
const showWallet = ref(false)
const showPassword = ref(false)
const editingSalary = ref<SalaryRow | null>(null)
const editingBonus = ref<BonusRow | null>(null)
const editingBill = ref<BillRow | null>(null)
const editingWallet = ref<WalletRow | null>(null)
const removing = ref(false)
const optionGroups = computed(() => groupedWallets(props.wallets))

const busy = computed(() => (
    livingForm.processing
    || salaryForm.processing
    || bonusForm.processing
    || billForm.processing
    || walletForm.processing
    || passwordForm.processing
    || removing.value
))

const payNote = computed(() => {
    if (props.pay_mode === 'month') {
        return `${props.budget_month} budget is ${pesos(props.month_pay)} for the month.`
    }

    return `${props.previous_pay_label} pay ${pesos(props.first_cutoff)} plus ${props.current_pay_label} pay ${pesos(props.second_cutoff)} is the ${props.budget_month} budget.`
})

const payOpen = computed({
    get: () => showPay.value,
    set: (open) => {
        showPay.value = open
        if (! open) {
            editingSalary.value = null
        }
    },
})

const bonusOpen = computed({
    get: () => showBonus.value,
    set: (open) => {
        showBonus.value = open
        if (! open) {
            editingBonus.value = null
        }
    },
})

const billOpen = computed({
    get: () => showBill.value,
    set: (open) => {
        showBill.value = open
        if (! open) {
            editingBill.value = null
        }
    },
})

const walletOpen = computed({
    get: () => showWallet.value,
    set: (open) => {
        showWallet.value = open
        if (! open) {
            editingWallet.value = null
        }
    },
})

function prettyDay(iso: string | null) {
    if (! iso) {
        return ''
    }

    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}

function saveLiving() {
    livingForm.put('/budget', { preserveScroll: true })
}

function openAddPay() {
    editingSalary.value = null
    salaryForm.reset()
    salaryForm.effective_on = page.props.today
    salaryForm.pay_cycle = 'cutoff'
    salaryForm.clearErrors()
    showPay.value = true
}

function openEditPay(row: SalaryRow) {
    editingSalary.value = row
    salaryForm.effective_on = row.effective_on
    salaryForm.amount = String(row.amount)
    salaryForm.pay_cycle = row.pay_cycle ?? 'cutoff'
    salaryForm.notes = row.notes ?? ''
    salaryForm.clearErrors()
    showPay.value = true
}

function savePay() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            salaryForm.reset()
            salaryForm.pay_cycle = 'cutoff'
            showPay.value = false
            editingSalary.value = null
        },
    }

    if (editingSalary.value) {
        salaryForm.put(`/salaries/${editingSalary.value.id}`, options)

        return
    }

    salaryForm.post('/salaries', options)
}

function removeSalary(salary: SalaryRow) {
    confirmDelete({
        title: 'Delete this pay?',
        message: `${prettyDay(salary.effective_on)} · ${pesos(salary.amount)}`,
        run: () => {
            removing.value = true
            router.delete(`/salaries/${salary.id}`, {
                preserveScroll: true,
                onFinish: () => {
                    removing.value = false
                },
            })
        },
    })
}

function openAddBonus() {
    editingBonus.value = null
    bonusForm.reset()
    bonusForm.received_on = page.props.today
    bonusForm.name = 'Christmas bonus'
    bonusForm.clearErrors()
    showBonus.value = true
}

function openEditBonus(row: BonusRow) {
    editingBonus.value = row
    bonusForm.received_on = row.received_on
    bonusForm.amount = String(row.amount)
    bonusForm.name = row.name
    bonusForm.notes = row.notes ?? ''
    bonusForm.clearErrors()
    showBonus.value = true
}

function saveBonus() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            bonusForm.reset()
            bonusForm.name = 'Christmas bonus'
            showBonus.value = false
            editingBonus.value = null
        },
    }

    if (editingBonus.value) {
        bonusForm.put(`/bonuses/${editingBonus.value.id}`, options)

        return
    }

    bonusForm.post('/bonuses', options)
}

function removeBonus(bonus: BonusRow) {
    confirmDelete({
        title: 'Delete this bonus?',
        message: `${bonus.name} · ${prettyDay(bonus.received_on)} · ${pesos(bonus.amount)}`,
        run: () => {
            removing.value = true
            router.delete(`/bonuses/${bonus.id}`, {
                preserveScroll: true,
                onFinish: () => {
                    removing.value = false
                },
            })
        },
    })
}

function openAddBill() {
    editingBill.value = null
    billForm.reset()
    billForm.clearErrors()
    showBill.value = true
}

function openEditBill(row: BillRow) {
    editingBill.value = row
    billForm.name = row.name
    billForm.monthly_plan = String(row.monthly_plan)
    billForm.clearErrors()
    showBill.value = true
}

function saveBill() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            billForm.reset()
            showBill.value = false
            editingBill.value = null
        },
    }

    if (editingBill.value) {
        billForm.put(`/bills/${editingBill.value.id}`, options)

        return
    }

    billForm.post('/bills', options)
}

function removeBill(bill: BillRow) {
    confirmDelete({
        title: 'Delete this bill?',
        message: `${bill.name} · ${pesos(bill.monthly_plan)} a month`,
        run: () => {
            removing.value = true
            router.delete(`/bills/${bill.id}`, {
                preserveScroll: true,
                onFinish: () => {
                    removing.value = false
                },
            })
        },
    })
}

function openAddWallet(group: WalletGroup) {
    editingWallet.value = null
    walletForm.reset()
    walletForm.group = group
    walletForm.due_day = group === 'credit' ? 15 : null
    walletForm.plans = group === 'credit' ? emptyPlans() : []
    walletForm.clearErrors()
    showWallet.value = true
}

function openEditWallet(row: WalletRow) {
    if (row.locked) {
        return
    }

    editingWallet.value = row
    walletForm.name = row.name
    walletForm.group = row.group
    walletForm.due_day = row.due_day
    walletForm.plans = row.group === 'credit'
        ? row.plans.map((plan) => ({ ...plan }))
        : []
    walletForm.clearErrors()
    showWallet.value = true
}

function saveWallet() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            walletForm.reset()
            walletForm.plans = emptyPlans()
            showWallet.value = false
            editingWallet.value = null
        },
    }

    if (editingWallet.value) {
        walletForm.put(`/wallets/${editingWallet.value.id}`, options)

        return
    }

    walletForm.post('/wallets', options)
}

function removeWallet(wallet: WalletRow) {
    if (wallet.locked) {
        return
    }

    confirmDelete({
        title: 'Delete this payment option?',
        message: wallet.name,
        run: () => {
            removing.value = true
            router.delete(`/wallets/${wallet.id}`, {
                preserveScroll: true,
                onFinish: () => {
                    removing.value = false
                },
            })
        },
    })
}

function optionTitle(group: WalletGroup) {
    if (group === 'credit') {
        return 'Edit credit'
    }

    if (group === 'bank') {
        return 'Edit bank'
    }

    return 'Edit wallet'
}

function savePassword() {
    passwordForm.put('/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset()
            showPassword.value = false
        },
    })
}
</script>

<template>
    <Head title="Plan" />
    <AppLayout>
        <template #hero>
            <PageHero
                :eyebrow="`Plan · ${budget_month}`"
                label="Left if followed"
                :value="pesos(left_if_followed)"
                :pill="`Month budget ${pesos(month_pay)}`"
            >
                <div v-if="pay_mode !== 'month'" class="hero-split">
                    <div>
                        <p class="text-xs text-white/70">{{ previous_pay_label }}</p>
                        <p class="num mt-1 text-lg text-white">{{ pesos(first_cutoff) }}</p>
                    </div>
                    <p class="self-center text-white/40">+</p>
                    <div>
                        <p class="text-xs text-white/70">{{ current_pay_label }}</p>
                        <p class="num mt-1 text-lg text-white">{{ pesos(second_cutoff) }}</p>
                    </div>
                </div>
                <p v-else class="mx-auto mt-4 max-w-md text-center text-sm text-white/90">{{ payNote }}</p>
            </PageHero>
        </template>

        <BusyOverlay :show="busy" label="Updating…" />

        <dl class="card stat-grid">
            <div>
                <dt>Bills</dt>
                <dd class="num">{{ pesos(bill_plan) }}</dd>
            </div>
            <div>
                <dt>Loans</dt>
                <dd class="num">{{ pesos(loan_plan) }}</dd>
            </div>
            <div>
                <dt>Living</dt>
                <dd class="num">{{ pesos(living_plan) }}</dd>
            </div>
            <div>
                <dt>Left</dt>
                <dd class="num" :class="left_if_followed < 0 ? 'text-clay' : 'text-palm-deep'">{{ pesos(left_if_followed) }}</dd>
            </div>
        </dl>

        <section class="card mt-4 overflow-x-auto">
            <div class="sheet-head">
                <h2>Pay per cutoff</h2>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost py-1 text-xs" @click="openAddPay">Add pay</button>
                    <button type="button" class="btn-ghost py-1 text-xs" @click="openAddBonus">Add bonus</button>
                </div>
            </div>
            <table class="data-table">
                <caption class="sr-only">Pay per cutoff</caption>
                <thead class="border-y border-line text-moss">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 font-medium">Starts</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Amount</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 font-medium">Paid</th>
                        <th scope="col" class="px-3 py-1.5 font-medium">Note</th>
                        <th scope="col" class="px-3 py-1.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="salaries.length === 0">
                        <td colspan="5" class="px-3 py-3 text-moss">No pay yet.</td>
                    </tr>
                    <tr v-for="salary in salaries" :key="salary.id" class="border-t border-line hover:bg-mist/60">
                        <td class="whitespace-nowrap px-3 py-1.5 text-moss">{{ prettyDay(salary.effective_on) }}</td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">{{ pesos(salary.amount) }}</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-moss">{{ salary.pay_cycle === 'month' ? 'Monthly' : 'Per cutoff' }}</td>
                        <td class="max-w-[14rem] truncate px-3 py-1.5" :title="salary.notes ?? ''">{{ salary.notes }}</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-right">
                            <button type="button" class="text-moss hover:text-ink" @click="openEditPay(salary)">Edit</button>
                            <button type="button" class="ml-3 text-clay" @click="removeSalary(salary)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="bonuses.length > 0" class="border-t border-line">
                <table class="data-table">
                    <caption class="sr-only">Bonuses</caption>
                    <thead class="text-moss">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-3 py-1.5 font-medium">Bonus</th>
                            <th scope="col" class="whitespace-nowrap px-3 py-1.5 font-medium">Date</th>
                            <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Amount</th>
                            <th scope="col" class="px-3 py-1.5"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="bonus in bonuses" :key="bonus.id" class="border-t border-line hover:bg-mist/60">
                            <td class="px-3 py-1.5">
                                {{ bonus.name }}
                                <span v-if="bonus.notes" class="text-moss"> · {{ bonus.notes }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-1.5 text-moss">{{ prettyDay(bonus.received_on) }}</td>
                            <td class="num whitespace-nowrap px-3 py-1.5 text-right">{{ pesos(bonus.amount) }}</td>
                            <td class="whitespace-nowrap px-3 py-1.5 text-right">
                                <button type="button" class="text-moss hover:text-ink" @click="openEditBonus(bonus)">Edit</button>
                                <button type="button" class="ml-3 text-clay" @click="removeBonus(bonus)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card mt-4 min-w-0 overflow-x-auto">
            <div class="sheet-head">
                <h2>Bills</h2>
                <button type="button" class="btn-ghost py-1 text-xs" @click="openAddBill">Add bill</button>
            </div>
            <table class="data-table">
                <caption class="sr-only">Bills</caption>
                <thead class="border-y border-line text-moss">
                    <tr>
                        <th scope="col" class="px-3 py-1.5 font-medium">Name</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Budget</th>
                        <th scope="col" class="px-3 py-1.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="bills.length === 0 && computed_bills.length === 0">
                        <td colspan="3" class="px-3 py-3 text-moss">No bills yet.</td>
                    </tr>
                    <tr v-for="bill in bills" :key="bill.id" class="border-t border-line hover:bg-mist/60">
                        <td class="px-3 py-1.5">
                            <span class="mr-2 inline-block size-2 rounded-full align-middle" :style="{ background: bill.color }" />
                            {{ bill.name }}
                        </td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">{{ pesos(bill.monthly_plan) }}</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-right">
                            <button type="button" class="text-moss hover:text-ink" @click="openEditBill(bill)">Edit</button>
                            <button type="button" class="ml-3 text-clay" @click="removeBill(bill)">Delete</button>
                        </td>
                    </tr>
                    <tr v-for="bill in computed_bills" :key="'computed-'+bill.id" class="border-t border-line">
                        <td class="px-3 py-1.5">
                            {{ bill.name }}
                            <span class="ml-2 text-xs text-moss">Computed</span>
                        </td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">{{ pesos(bill.monthly_plan) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card mt-4 overflow-hidden">
            <div class="sheet-head">
                <h2>Payment options</h2>
            </div>
            <div v-for="group in optionGroups" :key="group.key" class="border-t border-line">
                <div class="flex items-center justify-between gap-3 bg-mist/50 px-3 py-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-moss">{{ group.label }}</h3>
                    <button v-if="group.addable" type="button" class="btn-ghost py-1 text-xs" @click="openAddWallet(group.key)">Add</button>
                </div>
                <ul>
                    <li v-for="wallet in group.items" :key="wallet.id" class="border-t border-line px-3 py-2">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p>
                                <span class="font-medium">{{ wallet.name }}</span>
                                <span v-if="wallet.group === 'credit'" class="ml-2 text-xs text-moss">
                                    Due day {{ wallet.due_day }}
                                </span>
                                <span v-else-if="wallet.locked" class="ml-2 text-xs text-moss">Always available</span>
                            </p>
                            <p v-if="! wallet.locked" class="whitespace-nowrap text-sm">
                                <button type="button" class="text-moss hover:text-ink" @click="openEditWallet(wallet)">Edit</button>
                                <button type="button" class="ml-3 text-clay" @click="removeWallet(wallet)">Delete</button>
                            </p>
                        </div>
                    </li>
                    <li v-if="group.items.length === 0" class="px-3 py-2 text-sm text-moss">None yet.</li>
                </ul>
            </div>
        </section>

        <form class="card mt-4 flex flex-wrap items-end gap-3 px-3 py-3" @submit.prevent="saveLiving">
            <label class="grid min-w-40 flex-1 gap-1">
                <span class="kicker">Living budget</span>
                <input v-model="livingForm.living_plan" type="number" min="0" step="0.01" required class="field py-1.5">
            </label>
            <label class="grid gap-1">
                <span class="kicker">Budget by</span>
                <select v-model="livingForm.budget_cycle" class="field py-1.5">
                    <option value="month">Month</option>
                    <option value="cutoff">Cutoff</option>
                </select>
            </label>
            <button class="btn py-1.5" :disabled="livingForm.processing">Save</button>
        </form>

        <section class="card mt-4 overflow-x-auto">
            <div class="sheet-head">
                <h2>Loans</h2>
                <Link href="/loans" class="text-sm text-moss hover:text-ink">Open</Link>
            </div>
            <table class="data-table">
                <caption class="sr-only">Loans</caption>
                <thead class="border-y border-line text-moss">
                    <tr>
                        <th scope="col" class="px-3 py-1.5 font-medium">Name</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 font-medium">Type</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Monthly</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-1.5 text-right font-medium">Left</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="loan in loans" :key="loan.id" class="border-t border-line hover:bg-mist/60">
                        <td class="px-3 py-1.5">
                            {{ loan.name }}
                            <span v-if="loan.creditor" class="text-moss"> · {{ loan.creditor }}</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-moss">
                            <template v-if="loan.is_monthly && loan.installment_count">
                                {{ loan.installments_paid }}/{{ loan.installment_count }}
                            </template>
                            <template v-else>Personal</template>
                        </td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">
                            {{ loan.is_monthly ? pesos(loan.installment_amount) : '—' }}
                        </td>
                        <td class="num whitespace-nowrap px-3 py-1.5 text-right">{{ pesos(loan.balance) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <p class="mt-4">
            <button type="button" class="text-sm text-moss hover:text-ink" @click="showPassword = true">Change password</button>
        </p>

        <Modal v-if="showPay" v-model="payOpen" :title="editingSalary ? 'Edit pay' : 'Add pay'">
            <form class="grid gap-3" @submit.prevent="savePay">
                <label class="grid gap-1">
                    <span class="kicker">Starts on</span>
                    <input v-model="salaryForm.effective_on" type="date" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Amount</span>
                    <input v-model="salaryForm.amount" type="number" min="0.01" step="0.01" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Paid</span>
                    <select v-model="salaryForm.pay_cycle" class="field">
                        <option value="cutoff">Per cutoff</option>
                        <option value="month">Per month</option>
                    </select>
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Note</span>
                    <input v-model="salaryForm.notes" class="field" placeholder="Raise">
                </label>
                <button class="btn w-fit" :disabled="salaryForm.processing">Save</button>
                <p v-if="salaryForm.errors.effective_on || salaryForm.errors.amount" class="text-sm text-clay">
                    {{ salaryForm.errors.effective_on || salaryForm.errors.amount }}
                </p>
            </form>
        </Modal>

        <Modal v-if="showBonus" v-model="bonusOpen" :title="editingBonus ? 'Edit bonus' : 'Add bonus'">
            <form class="grid gap-3" @submit.prevent="saveBonus">
                <label class="grid gap-1">
                    <span class="kicker">Date</span>
                    <input v-model="bonusForm.received_on" type="date" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Amount</span>
                    <input v-model="bonusForm.amount" type="number" min="0.01" step="0.01" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Name</span>
                    <input v-model="bonusForm.name" required class="field" placeholder="Christmas bonus">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Note</span>
                    <input v-model="bonusForm.notes" class="field">
                </label>
                <button class="btn w-fit" :disabled="bonusForm.processing">Save</button>
                <p v-if="bonusForm.errors.amount || bonusForm.errors.name" class="text-sm text-clay">
                    {{ bonusForm.errors.amount || bonusForm.errors.name }}
                </p>
            </form>
        </Modal>

        <Modal v-if="showBill" v-model="billOpen" :title="editingBill ? 'Edit bill' : 'Add bill'">
            <form class="grid gap-3" @submit.prevent="saveBill">
                <label class="grid gap-1">
                    <span class="kicker">Name</span>
                    <input v-model="billForm.name" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Set budget</span>
                    <input v-model="billForm.monthly_plan" type="number" min="0" step="0.01" required class="field">
                </label>
                <button class="btn w-fit" :disabled="billForm.processing">Save</button>
                <p v-if="billForm.errors.name || billForm.errors.monthly_plan" class="text-sm text-clay">
                    {{ billForm.errors.name || billForm.errors.monthly_plan }}
                </p>
            </form>
        </Modal>

        <Modal v-if="showWallet" v-model="walletOpen" :title="editingWallet ? optionTitle(editingWallet.group) : optionTitle(walletForm.group).replace('Edit', 'Add')">
            <form class="grid gap-3" @submit.prevent="saveWallet">
                <label class="grid gap-1">
                    <span class="kicker">Name</span>
                    <input v-model="walletForm.name" required class="field" :placeholder="walletForm.group === 'credit' ? 'SPay, LazPay, BDO CC' : walletForm.group === 'bank' ? 'UnionBank, Debit' : 'GCash, Gotyme, Maya'">
                </label>
                <label v-if="walletForm.group === 'credit'" class="grid gap-1">
                    <span class="kicker">Due day</span>
                    <input v-model.number="walletForm.due_day" type="number" min="1" max="28" required class="field">
                </label>
                <button class="btn w-fit" :disabled="walletForm.processing">Save</button>
            </form>
        </Modal>

        <Modal v-if="showPassword" v-model="showPassword" title="Change password">
            <form class="grid gap-3" @submit.prevent="savePassword">
                <label class="grid gap-1">
                    <span class="kicker">Current</span>
                    <input v-model="passwordForm.current_password" type="password" autocomplete="current-password" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">New</span>
                    <input v-model="passwordForm.password" type="password" autocomplete="new-password" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Repeat</span>
                    <input v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" required class="field">
                </label>
                <button class="btn w-fit" :disabled="passwordForm.processing">Save</button>
                <p v-if="passwordForm.errors.current_password || passwordForm.errors.password" class="text-sm text-clay">
                    {{ passwordForm.errors.current_password || passwordForm.errors.password }}
                </p>
            </form>
        </Modal>
    </AppLayout>
</template>
