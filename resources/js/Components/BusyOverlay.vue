<script setup lang="ts">
withDefaults(defineProps<{
    show: boolean
    label?: string
    local?: boolean
}>(), {
    local: false,
})
</script>

<template>
    <div
        v-if="show"
        class="grid place-items-center bg-ink/40"
        :class="local ? 'absolute inset-0 z-10 rounded-[1rem]' : 'fixed inset-0 z-50'"
        role="status"
        aria-live="polite"
    >
        <div class="grid justify-items-center gap-3 rounded-2xl bg-paper px-8 py-6 shadow-[0_8px_20px_rgb(70_60_180_/_0.12)]">
            <span class="size-8 animate-spin rounded-full border-2 border-accent border-t-transparent" />
            <p class="text-sm font-medium">{{ label ?? 'Saving…' }}</p>
        </div>
    </div>
</template>
