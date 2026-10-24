<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageHero from '../../Components/PageHero.vue'

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
    <main class="min-h-screen bg-cream">
        <PageHero eyebrow="Household money" label="Sign in" value="Kaban" />
        <form class="card relative -mt-8 mx-auto grid max-w-sm gap-3 px-4 py-4" @submit.prevent="submit">
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
            <button class="btn" :disabled="form.processing">Sign in</button>
            <p v-if="form.errors.email" class="text-sm text-clay">{{ form.errors.email }}</p>
        </form>
    </main>
</template>
