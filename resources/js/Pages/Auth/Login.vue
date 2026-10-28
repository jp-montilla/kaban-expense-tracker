<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import Icon from '../../Components/Icon.vue'

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post('/login')
}
</script>

<template>
    <Head title="Sign in" />
    <main class="grid min-h-svh place-items-center bg-cream px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-6 flex items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-xl bg-accent text-white">
                    <Icon name="chart" :size="18" />
                </span>
                <span class="text-lg font-semibold tracking-tight">Kaban</span>
            </div>
            <form class="card grid gap-4 px-6 py-6" @submit.prevent="submit">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Sign in</h1>
                    <p class="mt-1 text-sm text-moss">Household money, one place.</p>
                </div>
                <label class="grid gap-1">
                    <span class="kicker">Email</span>
                    <input v-model="form.email" type="email" autocomplete="username" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Password</span>
                    <input v-model="form.password" type="password" autocomplete="current-password" required class="field">
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.remember" type="checkbox" class="size-4 accent-accent">
                    Keep me signed in
                </label>
                <button class="btn rounded-2xl" :disabled="form.processing">Sign in</button>
                <p v-if="form.errors.email" class="text-sm text-clay">{{ form.errors.email }}</p>
            </form>
        </div>
    </main>
</template>
