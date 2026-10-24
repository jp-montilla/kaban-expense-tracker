<script setup lang="ts">
import { CREDIT_MONTHS, type CreditOption } from '../lib/wallets'

defineProps<{
    credits: CreditOption[]
    disabled?: boolean
}>()

const viaCredit = defineModel<boolean>('viaCredit', { default: false })
const creditMethod = defineModel<string>('creditMethod', { default: '' })
const months = defineModel<number>('months', { default: 1 })
const monthly = defineModel<string>('monthly', { default: '' })
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center justify-between gap-3">
            <span class="kicker">Paid via credit</span>
            <button
                type="button"
                role="switch"
                class="switch"
                :class="viaCredit && 'is-on'"
                :aria-checked="viaCredit"
                :disabled="disabled"
                @click="viaCredit = ! viaCredit"
            >
                <span class="sr-only">Paid via credit</span>
            </button>
        </div>
        <div v-if="viaCredit" class="grid gap-2 sm:grid-cols-3">
            <label class="grid gap-1">
                <span class="kicker">Credit</span>
                <select v-model="creditMethod" required class="field">
                    <option disabled value="">Choose one</option>
                    <option v-for="credit in credits" :key="credit.id" :value="credit.slug">{{ credit.name }}</option>
                </select>
            </label>
            <label class="grid gap-1">
                <span class="kicker">Months</span>
                <select v-model.number="months" required class="field">
                    <option v-for="count in CREDIT_MONTHS" :key="count" :value="count">{{ count }} mo</option>
                </select>
            </label>
            <label class="grid gap-1">
                <span class="kicker">Monthly pay</span>
                <input v-model="monthly" type="number" min="0.01" step="0.01" required inputmode="decimal" class="field">
            </label>
        </div>
    </div>
</template>
