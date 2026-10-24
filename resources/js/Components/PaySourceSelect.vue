<script setup lang="ts">
import { computed } from 'vue'
import { groupedWallets, type WalletRow } from '../lib/wallets'

const props = defineProps<{
    wallets: WalletRow[]
}>()

const model = defineModel<string>({ default: '' })
const groups = computed(() => groupedWallets(props.wallets).filter((group) => group.items.length > 0))
</script>

<template>
    <select v-model="model" required class="field">
        <option disabled value="">Choose one</option>
        <optgroup v-for="group in groups" :key="group.key" :label="group.label">
            <option v-for="wallet in group.items" :key="wallet.id" :value="wallet.slug">{{ wallet.name }}</option>
        </optgroup>
    </select>
</template>
