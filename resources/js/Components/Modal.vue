<script setup lang="ts">
import { nextTick, useTemplateRef, watch } from 'vue'
import Icon from './Icon.vue'

const open = defineModel<boolean>({ default: false })

withDefaults(defineProps<{
    title: string
    size?: 'md' | 'lg'
}>(), {
    size: 'md',
})

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
    <dialog ref="dialog" class="modal" :class="size === 'lg' && 'modal-lg'" @close="open = false" @click="onDialogClick">
        <div class="modal-panel">
            <header class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ title }}</h2>
                <button type="button" class="grid size-8 shrink-0 place-items-center rounded-full text-moss hover:bg-mist hover:text-ink" aria-label="Close" @click="open = false">
                    <Icon name="close" :size="16" />
                </button>
            </header>
            <slot />
        </div>
    </dialog>
</template>
