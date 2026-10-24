import { computed, ref } from 'vue'

export interface ConfirmRequest {
    title: string
    message: string
    confirmLabel: string
    run: () => void
}

const request = ref<ConfirmRequest | null>(null)

export const confirmRequest = computed(() => request.value)

export const confirmOpen = computed({
    get: () => request.value !== null,
    set: (open: boolean) => {
        if (! open) {
            request.value = null
        }
    },
})

export function confirmDelete(options: {
    title: string
    message: string
    confirmLabel?: string
    run: () => void
}): void {
    request.value = {
        title: options.title,
        message: options.message,
        confirmLabel: options.confirmLabel ?? 'Delete',
        run: options.run,
    }
}

export function closeConfirm(): void {
    request.value = null
}

export function acceptConfirm(): void {
    const current = request.value

    request.value = null
    current?.run()
}
