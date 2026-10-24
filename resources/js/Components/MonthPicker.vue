<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import Icon from './Icon.vue'

const props = withDefaults(defineProps<{
    year: number
    month: number
    half?: number | null
    base: string
    tone?: 'page' | 'hero'
}>(), {
    tone: 'page',
})

const label = computed(() => new Date(props.year, props.month - 1, 1).toLocaleDateString('en-PH', {
    month: 'long',
    year: 'numeric',
}))

function href(year: number, month: number, half?: number | null) {
    const params = new URLSearchParams({
        year: String(year),
        month: String(month),
    })

    if (half === 1 || half === 2) {
        params.set('half', String(half))
    }

    return `${props.base}?${params.toString()}`
}

const previous = computed(() => {
    const date = new Date(props.year, props.month - 2, 1)

    return href(date.getFullYear(), date.getMonth() + 1, props.half)
})

const next = computed(() => {
    const date = new Date(props.year, props.month, 1)

    return href(date.getFullYear(), date.getMonth() + 1, props.half)
})
</script>

<template>
    <div class="flex items-center gap-1">
        <Link
            :href="previous"
            preserve-scroll
            class="grid size-9 place-items-center rounded-full"
            :class="tone === 'hero' ? 'text-white hover:bg-white/15' : 'text-ink hover:bg-mist'"
            aria-label="Previous month"
        >
            <Icon name="chevron-left" :size="18" />
        </Link>
        <h1
            class="min-w-36 text-center text-sm font-semibold md:text-base"
            :class="tone === 'hero' ? 'text-white' : 'text-ink'"
        >
            {{ label }}
        </h1>
        <Link
            :href="next"
            preserve-scroll
            class="grid size-9 place-items-center rounded-full"
            :class="tone === 'hero' ? 'text-white hover:bg-white/15' : 'text-ink hover:bg-mist'"
            aria-label="Next month"
        >
            <Icon name="chevron-right" :size="18" />
        </Link>
    </div>
</template>
