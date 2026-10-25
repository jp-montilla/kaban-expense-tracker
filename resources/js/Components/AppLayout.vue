<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { loggingOpen } from '../lib/logModal'
import ConfirmDialog from './ConfirmDialog.vue'
import DayLogForm from './DayLogForm.vue'
import Icon from './Icon.vue'
import Modal from './Modal.vue'

const page = usePage<{
    auth?: { user?: { name: string; email: string; is_admin?: boolean; is_superadmin?: boolean } | null }
}>()

const user = computed(() => page.props.auth?.user ?? null)
const path = computed(() => (page.url.split('?')[0] || '/'))

const moreOpen = ref(false)

const primary = [
    { href: '/', label: 'Dashboard', icon: 'home' as const, match: (url: string) => url === '/' },
    { href: '/log', label: 'Daily', icon: 'log' as const, match: (url: string) => url.startsWith('/log') },
    { href: '/budget', label: 'Plan', icon: 'plan' as const, match: (url: string) => url.startsWith('/budget') },
]

const extra = computed(() => {
    const items = [
        { href: '/bills', label: 'Bills', icon: 'bills' as const, match: (url: string) => url.startsWith('/bills') },
        { href: '/loans', label: 'Loan', icon: 'loan' as const, match: (url: string) => url.startsWith('/loans') || url.startsWith('/debts') },
        { href: '/savings', label: 'Savings', icon: 'savings' as const, match: (url: string) => url.startsWith('/savings') },
        { href: '/vehicles', label: 'Vehicles', icon: 'vehicle' as const, match: (url: string) => url.startsWith('/bike') || url.startsWith('/vehicles') },
        { href: '/summary', label: 'Year', icon: 'year' as const, match: (url: string) => url.startsWith('/summary') },
    ]

    if (user.value?.is_admin) {
        items.push({ href: '/people', label: 'Users', icon: 'people' as const, match: (url: string) => url.startsWith('/people') })
    }

    return items
})

const rail = computed(() => [...primary, ...extra.value])
const moreOn = computed(() => extra.value.some((link) => link.match(path.value)))

function openLogModal() {
    loggingOpen.value = true
}

function logout() {
    moreOpen.value = false
    router.post('/logout')
}
</script>

<template>
    <div class="min-h-screen md:grid md:grid-cols-[232px_1fr]">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-xl focus:bg-paper focus:px-3 focus:py-2">
            Skip to content
        </a>

        <aside class="hidden border-r border-line bg-paper md:sticky md:top-0 md:flex md:h-svh md:flex-col md:self-start md:overflow-hidden md:px-4 md:py-6">
            <p class="shrink-0 px-2 text-lg font-semibold tracking-tight">Kaban</p>
            <nav class="mt-7 min-h-0 flex-1 overflow-y-auto [scrollbar-width:thin]" aria-label="Sections">
                <Link
                    v-for="link in rail"
                    :key="link.href"
                    :href="link.href"
                    class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors duration-150"
                    :class="link.match(path) ? 'bg-accent/10 text-accent' : 'text-moss hover:bg-mist hover:text-ink'"
                >
                    <Icon :name="link.icon" :size="18" />
                    {{ link.label }}
                </Link>
            </nav>
            <button type="button" class="btn mt-4 w-full shrink-0" @click="openLogModal">
                <Icon name="plus" :size="16" />
                Log
            </button>
            <div v-if="user" class="mt-4 shrink-0 border-t border-line px-2 pt-4 text-sm">
                <p class="truncate font-medium text-ink">{{ user.name }}</p>
                <p class="truncate text-moss">{{ user.email }}</p>
                <button type="button" class="mt-3 text-moss hover:text-ink" @click="logout">Log out</button>
            </div>
        </aside>

        <div class="min-w-0 overflow-x-hidden pb-[calc(5.75rem+env(safe-area-inset-bottom))] md:pb-0">
            <main id="main">
                <slot name="hero" />
                <div class="mx-auto w-full max-w-5xl px-4 py-5 md:px-8 md:py-8" :class="$slots.hero ? '-mt-10 md:-mt-12' : ''">
                    <slot />
                </div>
            </main>
        </div>

        <nav class="tab-bar fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 items-end px-2 pt-2 md:hidden" aria-label="Primary">
            <Link
                v-for="link in primary.slice(0, 2)"
                :key="link.href"
                :href="link.href"
                class="grid justify-items-center gap-0.5 pb-2 text-[11px] font-medium"
                :class="link.match(path) ? 'text-accent' : 'text-moss'"
            >
                <Icon :name="link.icon" />
                {{ link.label }}
            </Link>
            <button type="button" class="fab mx-auto -translate-y-4" aria-label="Log an expense" @click="openLogModal">
                <Icon name="plus" :size="26" />
            </button>
            <Link
                :href="primary[2].href"
                class="grid justify-items-center gap-0.5 pb-2 text-[11px] font-medium"
                :class="primary[2].match(path) ? 'text-accent' : 'text-moss'"
            >
                <Icon :name="primary[2].icon" />
                {{ primary[2].label }}
            </Link>
            <button
                type="button"
                class="grid justify-items-center gap-0.5 pb-2 text-[11px] font-medium"
                :class="moreOn ? 'text-accent' : 'text-moss'"
                aria-haspopup="dialog"
                :aria-expanded="moreOpen"
                @click="moreOpen = true"
            >
                <Icon name="more" />
                More
            </button>
        </nav>

        <div
            v-if="moreOpen"
            class="fixed inset-0 z-40 bg-ink/40 md:hidden"
            @click.self="moreOpen = false"
        >
            <div class="sheet-panel absolute inset-x-0 bottom-0" role="dialog" aria-label="More">
                <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-line" />
                <p class="text-sm font-semibold">More</p>
                <nav class="mt-3 grid gap-1" aria-label="More sections">
                    <Link
                        v-for="link in extra"
                        :key="link.href"
                        :href="link.href"
                        class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium"
                        :class="link.match(path) ? 'bg-accent/10 text-accent' : 'text-ink hover:bg-mist'"
                        @click="moreOpen = false"
                    >
                        <span class="grid size-9 place-items-center rounded-full bg-mist text-accent">
                            <Icon :name="link.icon" :size="18" />
                        </span>
                        {{ link.label }}
                    </Link>
                </nav>
                <div v-if="user" class="mt-4 border-t border-line pt-4">
                    <p class="text-sm font-medium">{{ user.name }}</p>
                    <p class="text-xs text-moss">{{ user.email }}</p>
                    <button type="button" class="mt-3 text-sm text-spend" @click="logout">Log out</button>
                </div>
            </div>
        </div>

        <Modal v-if="loggingOpen" v-model="loggingOpen" title="Log" size="lg">
            <DayLogForm @saved="loggingOpen = false" />
        </Modal>
        <ConfirmDialog />
    </div>
</template>
