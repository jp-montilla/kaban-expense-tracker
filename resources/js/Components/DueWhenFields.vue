<script setup lang="ts">
import type { DueMode } from '../lib/dueWhen'

const mode = defineModel<DueMode>('mode', { default: null })
const day = defineModel<number | null>('day', { default: null })

function pick(next: Exclude<DueMode, null>) {
    mode.value = next

    if (next !== 'exact') {
        day.value = null
    } else if (! day.value) {
        day.value = 15
    }
}
</script>

<template>
    <div class="grid gap-2">
        <span class="kicker">Due</span>
        <div class="flex flex-wrap gap-1" role="group" aria-label="When this is due">
            <button type="button" class="btn-ghost py-1 text-xs" :class="mode == null ? 'is-on' : ''" @click="mode = null; day = null">Anytime</button>
            <button type="button" class="btn-ghost py-1 text-xs" :class="mode === 'first' ? 'is-on' : ''" @click="pick('first')">1–15</button>
            <button type="button" class="btn-ghost py-1 text-xs" :class="mode === 'second' ? 'is-on' : ''" @click="pick('second')">16–end</button>
            <button type="button" class="btn-ghost py-1 text-xs" :class="mode === 'exact' ? 'is-on' : ''" @click="pick('exact')">Day</button>
        </div>
        <label v-if="mode === 'exact'" class="grid max-w-24 gap-1">
            <span class="kicker">Day</span>
            <input v-model.number="day" type="number" min="1" max="28" required class="field">
        </label>
    </div>
</template>
