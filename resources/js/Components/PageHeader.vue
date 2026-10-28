<script setup lang="ts">
defineProps<{
    title: string
    subtitle?: string
}>()
</script>

<template>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 text-sm text-moss">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </div>
</template>
