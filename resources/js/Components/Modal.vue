<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref, useTemplateRef, watch } from 'vue'
import BusyOverlay from './BusyOverlay.vue'
import Icon from './Icon.vue'

const open = defineModel<boolean>({ default: false })

const props = withDefaults(defineProps<{
    title: string
    size?: 'md' | 'lg'
    busyLabel?: string
}>(), {
    size: 'md',
})

const dialog = useTemplateRef<HTMLDialogElement>('dialog')
const visiting = ref(false)
const busy = computed(() => open.value && visiting.value)
const label = computed(() => {
    if (props.busyLabel) {
        return props.busyLabel
    }

    return props.title.startsWith('Edit') ? 'Updating…' : 'Saving…'
})

onMounted(() => {
    const stopStart = router.on('start', (event) => {
        if (event.detail.visit.prefetch) {
            return
        }

        visiting.value = true
    })
    const stopFinish = router.on('finish', () => {
        visiting.value = false
    })

    onUnmounted(() => {
        stopStart()
        stopFinish()
    })
})

watch(open, async (isOpen) => {
    await nextTick()
    const el = dialog.value

    if (!el) {
        return
    }

    if (isOpen && !el.open) {
        el.showModal()
    }

    if (!isOpen && el.open) {
        el.close()
    }
}, { immediate: true })

function close() {
    if (busy.value) {
        return
    }

    open.value = false
}

function onDialogClick(event: MouseEvent) {
    if (event.target === dialog.value) {
        close()
    }
}

function onCancel(event: Event) {
    if (busy.value) {
        event.preventDefault()
    }
}
</script>

<template>
    <dialog
        ref="dialog"
        class="modal"
        :class="size === 'lg' && 'modal-lg'"
        :aria-busy="busy"
        @close="open = false"
        @cancel="onCancel"
        @click="onDialogClick"
    >
        <div class="modal-panel relative">
            <BusyOverlay :show="busy" :label="label" local />
            <header class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ title }}</h2>
                <button
                    type="button"
                    class="grid size-8 shrink-0 place-items-center rounded-full text-moss hover:bg-mist hover:text-ink disabled:opacity-40"
                    aria-label="Close"
                    :disabled="busy"
                    @click="close"
                >
                    <Icon name="close" :size="16" />
                </button>
            </header>
            <slot />
        </div>
    </dialog>
</template>
