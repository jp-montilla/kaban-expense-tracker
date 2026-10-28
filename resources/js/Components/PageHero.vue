<script setup lang="ts">
withDefaults(defineProps<{
    label: string
    value: string
    hint?: string | null
    pill?: string | null
    eyebrow?: string | null
}>(), {
    hint: null,
    pill: null,
    eyebrow: null,
})
</script>

<template>
    <section class="hero relative overflow-hidden rounded-[1.5rem] px-5 py-6 md:px-8 md:py-7">
        <div class="pointer-events-none absolute -right-16 -top-20 size-64 rounded-full bg-white/10" />
        <div class="pointer-events-none absolute -bottom-24 right-1/3 size-52 rounded-full bg-indigo-300/20" />

        <div class="relative flex flex-wrap items-center gap-5">
            <div class="flex min-w-[12rem] flex-1 items-center gap-4">
                <div v-if="$slots.mark" class="hidden shrink-0 sm:block">
                    <slot name="mark" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-white/80">{{ label }}</p>
                    <p class="num mt-1 whitespace-nowrap text-[2rem] leading-none tracking-tight text-white sm:text-4xl lg:text-[2.75rem]">{{ value }}</p>
                    <p v-if="hint || pill" class="mt-2 text-sm text-white/85">{{ hint || pill }}</p>
                </div>
            </div>
            <div v-if="$slots.side" class="min-w-[16rem] flex-1">
                <slot name="side" />
            </div>
        </div>

        <div v-if="$slots.default" class="relative mt-6 text-sm text-white/90">
            <slot />
        </div>
    </section>
</template>
