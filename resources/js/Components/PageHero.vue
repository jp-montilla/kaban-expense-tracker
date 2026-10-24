<script setup lang="ts">
withDefaults(defineProps<{
    eyebrow: string
    label: string
    value: string
    pill?: string | null
}>(), {
    pill: null,
})
</script>

<template>
    <section class="hero px-4 pb-16 pt-5 md:mx-auto md:mt-8 md:max-w-5xl md:rounded-[1.25rem] md:px-8 md:pt-7 md:pb-20">
        <p class="hero-eyebrow">{{ eyebrow }}</p>
        <p class="mt-6 text-center text-sm font-medium text-white/90">{{ label }}</p>
        <p class="num mt-1 text-center text-[2.5rem] leading-none tracking-tight text-white md:text-5xl">{{ value }}</p>
        <p v-if="pill" class="mx-auto mt-4 w-fit rounded-full bg-white/15 px-3 py-1 text-sm text-white">{{ pill }}</p>
        <slot />
    </section>
</template>
