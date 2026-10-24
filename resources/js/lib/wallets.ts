export type WalletGroup = 'cash' | 'wallet' | 'credit' | 'bank'

export interface WalletPlan {
    id: string
    months: number
    interest: number
}

export interface CreditDue {
    id: string
    n: number
    due_on: string
    amount: number
    paid_on: string | null
    transaction_id?: number | null
}

export interface CreditCharge {
    id: string
    description: string
    amount: number
    plan_id: string
    months: number
    interest: number
    first_due_on: string
    dues: CreditDue[]
}

export interface WalletRow {
    id: string
    name: string
    slug: string
    group: WalletGroup
    locked: boolean
    due_day: number | null
    plans: WalletPlan[]
    charges: CreditCharge[]
}

export const WALLET_GROUPS: { key: WalletGroup; label: string; addable: boolean }[] = [
    { key: 'cash', label: 'Cash', addable: false },
    { key: 'wallet', label: 'Wallets', addable: true },
    { key: 'credit', label: 'Credit', addable: true },
    { key: 'bank', label: 'Debit / Bank', addable: true },
]

export function groupedWallets(wallets: WalletRow[]) {
    return WALLET_GROUPS.map((group) => ({
        ...group,
        items: wallets.filter((row) => (row.group ?? 'wallet') === group.key),
    }))
}

export const CREDIT_MONTHS = [1, 3, 6, 9, 12, 18, 24, 36] as const

export interface CreditOption {
    id: string
    name: string
    slug: string
    due_day: number | null
}

export function planLabel(plan: Pick<WalletPlan, 'months' | 'interest'> & { monthly?: number }) {
    if (plan.monthly != null && Number(plan.monthly) > 0) {
        return `${plan.months} mo · ${Number(plan.monthly).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}/mo`
    }

    const interest = Number(plan.interest) === 0 ? '0%' : `${plan.interest}%`

    return `${plan.months} mo · ${interest}`
}

export function isoDate(year: number, monthIndex: number, day: number) {
    const date = new Date(year, monthIndex, day)
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')

    return `${date.getFullYear()}-${month}-${d}`
}

export function nextDueOn(dueDay: number | null, today = new Date()) {
    const day = Math.min(Math.max(dueDay ?? 15, 1), 28)
    let year = today.getFullYear()
    let month = today.getMonth()
    const candidate = new Date(year, month, day)

    candidate.setHours(0, 0, 0, 0)
    const start = new Date(today)
    start.setHours(0, 0, 0, 0)

    if (candidate < start) {
        month += 1
        if (month > 11) {
            month = 0
            year += 1
        }
    }

    return isoDate(year, month, day)
}

export function emptyPlans(): WalletPlan[] {
    return [
        { id: 'new-1', months: 1, interest: 0 },
        { id: 'new-3', months: 3, interest: 0 },
    ]
}
