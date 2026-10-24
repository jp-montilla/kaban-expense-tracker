import { router } from '@inertiajs/vue3'

interface Toast {
    id: number
    kind: 'success' | 'error'
    message: string
}

const items: Toast[] = []
const timers = new Map<number, number>()
let seq = 0
let lastKey = ''
let lastAt = 0
let host: HTMLElement | null = null

function isBag(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object'
}

function icon(kind: Toast['kind']): string {
    if (kind === 'error') {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4 21 19H3zm0 6v4m0 3h.01"/></svg>'
    }

    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5 9.5 17 19 7"/></svg>'
}

function ensureHost(): HTMLElement | null {
    if (typeof document === 'undefined') {
        return null
    }

    if (host?.isConnected) {
        return host
    }

    host = document.createElement('div')
    host.id = 'kaban-toasts'
    host.className = 'pointer-events-none fixed inset-x-0 top-4 z-[80] flex flex-col items-center gap-2 px-4 md:top-6'
    host.addEventListener('click', (event) => {
        const button = (event.target as HTMLElement).closest('[data-toast-id]')

        if (button instanceof HTMLElement) {
            dismissToast(Number(button.dataset.toastId))
        }
    })
    document.body.appendChild(host)

    return host
}

function paint(): void {
    const root = ensureHost()

    if (! root) {
        return
    }

    root.innerHTML = items.map((toast) => `
        <div class="toast pointer-events-auto flex max-w-md items-center gap-3 rounded-2xl px-3 py-2.5 text-sm text-white shadow-[0_16px_40px_rgb(27_24_51_/_0.28)] ${toast.kind === 'error' ? 'bg-clay' : 'bg-ink/95'}" role="status" aria-live="polite">
            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-white/12">${icon(toast.kind)}</span>
            <p class="min-w-0 flex-1 pr-1"></p>
            <button type="button" class="grid size-8 shrink-0 place-items-center rounded-full text-white/70 hover:bg-white/10 hover:text-white" data-toast-id="${toast.id}" aria-label="Dismiss">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 7l10 10M17 7 7 17"/></svg>
            </button>
        </div>
    `).join('')

    root.querySelectorAll('p').forEach((paragraph, index) => {
        paragraph.textContent = items[index]?.message ?? ''
    })
}

export function dismissToast(id: number): void {
    const timer = timers.get(id)

    if (timer) {
        window.clearTimeout(timer)
        timers.delete(id)
    }

    const index = items.findIndex((toast) => toast.id === id)

    if (index >= 0) {
        items.splice(index, 1)
        paint()
    }
}

export function pushToast(kind: Toast['kind'], message: unknown): void {
    const text = typeof message === 'string' ? message.trim() : ''

    if (! text) {
        return
    }

    const now = Date.now()
    const key = `${kind}:${text}`

    if (key === lastKey && now - lastAt < 800) {
        return
    }

    lastKey = key
    lastAt = now

    const id = ++seq
    items.unshift({ id, kind, message: text })
    items.splice(3)
    timers.set(id, window.setTimeout(() => dismissToast(id), 3600))
    paint()
}

export function readFlash(flash: unknown): void {
    if (! isBag(flash)) {
        return
    }

    pushToast('success', flash.success)
    pushToast('error', flash.error)
}

function onFlash(event: Event): void {
    const detail = (event as CustomEvent<{ flash?: unknown }>).detail
    readFlash(detail?.flash)
}

function onSuccess(event: Event): void {
    const page = (event as CustomEvent<{ page?: { flash?: unknown; props?: { flash?: unknown } } }>).detail?.page
    readFlash(page?.flash)
    readFlash(page?.props?.flash)
}

if (typeof document !== 'undefined') {
    document.addEventListener('inertia:flash', onFlash)
    document.addEventListener('inertia:success', onSuccess)
}

router.on('flash', (event) => readFlash(event.detail.flash))
router.on('success', (event) => {
    readFlash(event.detail.page.flash)
    readFlash(event.detail.page.props.flash)
})
