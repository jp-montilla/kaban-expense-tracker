<script setup lang="ts">
import { nextTick, useTemplateRef, watch } from 'vue'
import { acceptConfirm, closeConfirm, confirmOpen, confirmRequest } from '../lib/confirm'
import Icon from './Icon.vue'

const dialog = useTemplateRef<HTMLDialogElement>('dialog')

watch(confirmOpen, async (isOpen) => {
    await nextTick()
    const el = dialog.value

    if (! el) {
        return
    }

    if (isOpen && ! el.open) {
        el.showModal()
    }

    if (! isOpen && el.open) {
        el.close()
    }
}, { immediate: true, flush: 'post' })

function onDialogClick(event: MouseEvent) {
    if (event.target === dialog.value) {
        closeConfirm()
    }
}
</script>

<template>
    <dialog
        v-if="confirmRequest"
        ref="dialog"
        class="confirm-dialog"
        @close="closeConfirm"
        @click="onDialogClick"
    >
        <div class="confirm-panel">
            <span class="grid size-12 place-items-center rounded-full bg-clay/10 text-clay">
                <Icon name="trash" :size="22" />
            </span>
            <h2 class="mt-4 text-lg font-semibold tracking-tight">{{ confirmRequest.title }}</h2>
            <p class="mt-2 text-sm leading-6 text-moss">{{ confirmRequest.message }}</p>
            <div class="mt-6 grid gap-2">
                <button type="button" class="btn bg-clay hover:bg-[#8f1c14]" @click="acceptConfirm">
                    {{ confirmRequest.confirmLabel }}
                </button>
                <button type="button" class="btn-ghost" @click="closeConfirm">Cancel</button>
            </div>
        </div>
    </dialog>
</template>
