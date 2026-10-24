<script setup lang="ts">
import { nextTick, useTemplateRef, watch } from 'vue'

const open = defineModel<boolean>({ default: false })

defineProps<{
    title: string
}>()

const dialog = useTemplateRef<HTMLDialogElement>('dialog')

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

function onDialogClick(event: MouseEvent) {
    if (event.target === dialog.value) {
        open.value = false
    }
}
</script>

<template>
    <dialog ref="dialog" class="modal" @close="open = false" @click="onDialogClick">
        <div class="modal-panel">
            <header class="mb-4 flex items-start justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ title }}</h2>
                <button type="button" class="btn-ghost px-2 py-1 text-lg leading-none" aria-label="Close" @click="open = false">
                    ×
                </button>
            </header>
            <slot />
        </div>
    </dialog>
</template>
