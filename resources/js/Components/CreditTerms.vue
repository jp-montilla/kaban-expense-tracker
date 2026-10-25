<script setup lang="ts">
import { computed } from 'vue'
import { CREDIT_MONTHS, prettyDue, statementDueOn, type CreditOption } from '../lib/wallets'

const props = defineProps<{
    credits: CreditOption[]
    occurredOn?: string
    disabled?: boolean
}>()

const viaCredit = defineModel<boolean>('viaCredit', { default: false })
const creditMethod = defineModel<string>('creditMethod', { default: '' })
const months = defineModel<number>('months', { default: 1 })
const monthly = defineModel<string>('monthly', { default: '' })

const credit = computed(() => props.credits.find((row) => row.slug === creditMethod.value) ?? null)
const landingOn = computed(() => {
    if (! credit.value || ! props.occurredOn) {
        return null
    }

    return statementDueOn(credit.value, props.occurredOn)
})
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
        <div v-if="viaCredit" class="grid grid-cols-2 gap-2" :class="Number(months) === 1 ? 'md:grid-cols-2' : 'md:grid-cols-3'">
            <label class="grid gap-1">
                <span class="kicker">Credit</span>
                <select v-model="creditMethod" required class="field">
                    <option disabled value="">Choose one</option>
                    <option v-for="row in credits" :key="row.id" :value="row.slug">{{ row.name }}</option>
                </select>
            </label>
            <label class="grid gap-1">
                <span class="kicker">Months</span>
                <select v-model.number="months" required class="field">
                    <option v-for="count in CREDIT_MONTHS" :key="count" :value="count">{{ count }} mo</option>
                </select>
            </label>
            <label v-if="Number(months) !== 1" class="col-span-2 grid gap-1 md:col-span-1">
                <span class="kicker">Monthly pay</span>
                <input v-model="monthly" type="number" min="0.01" step="0.01" required inputmode="decimal" class="field">
            </label>
        </div>
        <p v-if="viaCredit && landingOn" class="text-xs text-moss">
            {{ Number(months) === 1 ? 'Lands on' : '1st due' }} {{ prettyDue(landingOn) }}
        </p>
    </div>
</template>
