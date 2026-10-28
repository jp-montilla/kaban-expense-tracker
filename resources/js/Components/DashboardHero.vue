<script setup lang="ts">
import { pesos } from '../lib/money'

defineProps<{
    cashLeft: number | null
    hint: string
    saved: number
    leftover: number
    first: { label: string; pay: number; saved: number; leftover: number } | null
    second: { label: string; pay: number; saved: number; leftover: number } | null
}>()
</script>

<template>
    <section class="hero relative overflow-hidden rounded-[1.5rem] px-5 py-6 md:px-8 md:py-7">
        <div class="pointer-events-none absolute -right-16 -top-20 size-64 rounded-full bg-white/10" />
        <div class="pointer-events-none absolute -bottom-24 right-1/3 size-52 rounded-full bg-indigo-300/20" />

        <div class="relative flex flex-wrap items-center gap-5">
            <div class="flex min-w-[12rem] flex-1 items-center gap-4">
                <div class="hidden size-[4.5rem] shrink-0 sm:grid sm:place-items-center">
                    <svg viewBox="0 0 72 72" class="size-[4.5rem]" aria-hidden="true">
                        <rect x="10" y="28" width="48" height="32" rx="8" fill="#3b2cc4" />
                        <rect x="14" y="18" width="36" height="14" rx="3" fill="#f8e38c" />
                        <rect x="18" y="12" width="32" height="12" rx="3" fill="#7dcea0" />
                        <rect x="10" y="34" width="48" height="26" rx="8" fill="#5b4ce8" />
                        <rect x="42" y="42" width="18" height="12" rx="4" fill="#c7bfff" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-white/80">Budget Left</p>
                    <p class="num mt-1 whitespace-nowrap text-[2rem] leading-none tracking-tight text-white sm:text-4xl lg:text-[2.75rem]">{{ pesos(cashLeft) }}</p>
                    <p class="mt-2 text-sm text-white/85">{{ hint }}</p>
                </div>
            </div>

            <div
                v-if="first || second"
                class="grid flex-1 gap-3"
                :class="first && second ? 'min-w-[18rem] grid-cols-2' : 'max-w-xs grid-cols-1'"
            >
                <div v-if="first" class="rounded-2xl bg-white/15 px-3 py-3 backdrop-blur-sm sm:px-4">
                    <p class="flex items-center gap-1.5 text-xs text-white/75">
                        <span class="inline-block size-1.5 rounded-full bg-white/80" />
                        {{ first.label }}
                    </p>
                    <p class="num mt-2 text-lg text-white">{{ pesos(first.pay) }}</p>
                    <p v-if="first.saved" class="mt-1 text-xs text-white/75">saved {{ pesos(first.saved) }}</p>
                    <p class="mt-0.5 text-xs text-white/75">left {{ pesos(first.leftover) }}</p>
                </div>
                <div v-if="second" class="rounded-2xl bg-white/15 px-3 py-3 backdrop-blur-sm sm:px-4">
                    <p class="flex items-center gap-1.5 text-xs text-white/75">
                        <span class="inline-block size-1.5 rounded-full bg-white/80" />
                        {{ second.label }}
                    </p>
                    <p class="num mt-2 text-lg text-white">{{ pesos(second.pay) }}</p>
                    <p v-if="second.saved" class="mt-1 text-xs text-white/75">saved {{ pesos(second.saved) }}</p>
                    <p class="mt-0.5 text-xs text-white/75">left {{ pesos(second.leftover) }}</p>
                </div>
            </div>
        </div>

        <p class="relative mt-6 text-sm text-white/90">
            Saved {{ pesos(saved) }}
            <span class="text-white/70"> · left {{ pesos(leftover) }}</span>
        </p>
    </section>
</template>
