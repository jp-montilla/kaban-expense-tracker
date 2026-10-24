<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, useTemplateRef, watch } from 'vue'

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as const

const props = withDefaults(defineProps<{
    year: number
    month: number
    tone?: 'page' | 'hero'
    id?: string
}>(), {
    tone: 'page',
})

const emit = defineEmits<{
    pick: [year: number, month: number]
}>()

const open = ref(false)
const viewYear = ref(props.year)
const top = ref(0)
const left = ref(0)
const trigger = useTemplateRef<HTMLButtonElement>('trigger')
const panel = useTemplateRef<HTMLElement>('panel')

const label = computed(() => new Date(props.year, props.month - 1, 1).toLocaleDateString('en-PH', {
    month: 'long',
    year: 'numeric',
}))

const now = new Date()
const thisYear = now.getFullYear()
const thisMonth = now.getMonth() + 1

const triggerClass = computed(() => props.tone === 'hero'
    ? 'inline-flex min-w-[10.5rem] items-center justify-between gap-2 rounded-full border-0 bg-white/15 px-3 py-1.5 text-sm font-medium text-white'
    : 'field !w-auto inline-flex min-w-[10.5rem] items-center justify-between gap-2 py-1.5 text-sm')

watch(() => [props.year, props.month], () => {
    if (! open.value) {
        viewYear.value = props.year
    }
})

watch(open, async (isOpen) => {
    if (isOpen) {
        viewYear.value = props.year
        await nextTick()
        place()
        requestAnimationFrame(place)
        window.addEventListener('resize', place)
        window.addEventListener('scroll', close, true)
        document.addEventListener('pointerdown', onDocClick)
        document.addEventListener('keydown', onKey)

        return
    }

    dropListeners()
})

onUnmounted(dropListeners)

function dropListeners() {
    window.removeEventListener('resize', place)
    window.removeEventListener('scroll', close, true)
    document.removeEventListener('pointerdown', onDocClick)
    document.removeEventListener('keydown', onKey)
}

function close() {
    open.value = false
}

function toggle() {
    open.value = ! open.value
}

function place() {
    const rect = trigger.value?.getBoundingClientRect()

    if (! rect) {
        return
    }

    const width = 224
    const height = panel.value?.offsetHeight ?? 220
    let nextLeft = rect.left
    let nextTop = rect.bottom + 6
    const spaceBelow = window.innerHeight - rect.bottom

    if (nextLeft + width > window.innerWidth - 8) {
        nextLeft = Math.max(8, rect.right - width)
    }

    if (spaceBelow < 80 && rect.top > height + 6) {
        nextTop = rect.top - height - 6
    }

    left.value = nextLeft
    top.value = nextTop
}

function onDocClick(event: MouseEvent) {
    const node = event.target as Node

    if (trigger.value?.contains(node) || panel.value?.contains(node)) {
        return
    }

    close()
}

function onKey(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        close()
        trigger.value?.focus()
    }
}

function shiftYear(delta: number) {
    viewYear.value = Math.min(2100, Math.max(2000, viewYear.value + delta))
}

function pick(month: number) {
    emit('pick', viewYear.value, month)
    close()
}

function isSelected(month: number) {
    return viewYear.value === props.year && month === props.month
}

function isCurrent(month: number) {
    return viewYear.value === thisYear && month === thisMonth
}
</script>

<template>
    <div class="relative">
        <button
            :id="id"
            ref="trigger"
            type="button"
            :class="triggerClass"
            :aria-expanded="open"
            :aria-label="label"
            aria-haspopup="dialog"
            @click="toggle"
        >
            <span>{{ label }}</span>
            <svg class="size-3.5 shrink-0 opacity-60" viewBox="0 0 12 12" aria-hidden="true">
                <path d="M2.4 4.2 6 7.8l3.6-3.6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
        <Teleport to="body">
            <div
                v-if="open"
                ref="panel"
                role="dialog"
                aria-label="Choose month"
                class="fixed z-50 w-56 rounded-xl border border-line bg-paper p-2 shadow-[0_12px_28px_rgb(70_60_180_/_0.16)]"
                :style="{ top: `${top}px`, left: `${left}px` }"
                @pointerdown.stop
            >
                <div class="mb-2 flex items-center justify-between gap-2">
                    <button type="button" class="btn-ghost px-2 py-1 text-sm" aria-label="Previous year" @click="shiftYear(-1)">‹</button>
                    <p class="text-sm font-semibold tabular-nums">{{ viewYear }}</p>
                    <button type="button" class="btn-ghost px-2 py-1 text-sm" aria-label="Next year" @click="shiftYear(1)">›</button>
                </div>
                <div class="grid grid-cols-3 gap-1" :aria-label="`Months in ${viewYear}`">
                    <button
                        v-for="(name, index) in MONTHS"
                        :key="name"
                        type="button"
                        class="rounded-lg px-1 py-1.5 text-sm font-medium"
                        :class="isSelected(index + 1)
                            ? 'bg-accent text-white'
                            : isCurrent(index + 1)
                                ? 'text-accent hover:bg-mist'
                                : 'text-ink hover:bg-mist'"
                        :aria-pressed="isSelected(index + 1)"
                        @click="pick(index + 1)"
                    >
                        {{ name }}
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
