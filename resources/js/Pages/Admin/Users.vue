<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '../../Components/AppLayout.vue'
import PageHero from '../../Components/PageHero.vue'
import Modal from '../../Components/Modal.vue'
import { confirmDelete } from '../../lib/confirm'

interface Person {
    id: number
    name: string
    email: string
    role: 'superadmin' | 'admin' | 'member'
    is_active: boolean
    created_at: string | null
}

defineProps<{
    people: Person[]
}>()

const adding = ref(false)
const editing = ref<Person | null>(null)

const editOpen = computed({
    get: () => editing.value !== null,
    set: (open) => {
        if (!open) {
            editing.value = null
        }
    },
})

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'member',
})

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'member' as Person['role'],
    is_active: true,
})

function openEdit(person: Person) {
    editForm.name = person.name
    editForm.email = person.email
    editForm.password = ''
    editForm.password_confirmation = ''
    editForm.role = person.role
    editForm.is_active = person.is_active
    editing.value = person
}

function addPerson() {
    createForm.post('/people', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset()
            createForm.role = 'member'
            adding.value = false
        },
    })
}

function savePerson() {
    if (! editing.value) {
        return
    }

    editForm.put(`/people/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null
        },
    })
}

function removePerson(person: Person) {
    confirmDelete({
        title: 'Remove this account?',
        message: `${person.name} · ${person.email}. Their bills, log, loans, and vehicles go with it.`,
        confirmLabel: 'Remove',
        run: () => router.delete(`/people/${person.id}`, { preserveScroll: true }),
    })
}
</script>

<template>
    <Head title="Users" />
    <AppLayout>
        <template #hero>
            <PageHero
                eyebrow="Users"
                label="Accounts"
                :value="String(people.length)"
                :pill="`${people.filter((row) => row.is_active).length} on`"
            />
        </template>

        <section class="card overflow-x-auto">
            <div class="sheet-head">
                <h2>Household</h2>
                <button type="button" class="btn-ghost py-1 text-xs" @click="adding = true">Add</button>
            </div>
            <table class="data-table min-w-[36rem]">
                <caption class="sr-only">Accounts</caption>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="person in people" :key="person.id">
                        <td class="font-medium">{{ person.name }}</td>
                        <td class="text-moss">{{ person.email }}</td>
                        <td class="capitalize">{{ person.role }}</td>
                        <td>{{ person.is_active ? 'On' : 'Off' }}</td>
                        <td class="whitespace-nowrap text-right">
                            <button type="button" class="row-edit" @click="openEdit(person)">Edit</button>
                            <button v-if="person.role !== 'superadmin'" type="button" class="row-del" @click="removePerson(person)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <Modal v-if="adding" v-model="adding" title="Add user">
            <form class="grid gap-3" @submit.prevent="addPerson">
                <label class="grid gap-1">
                    <span class="kicker">Name</span>
                    <input v-model="createForm.name" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Email</span>
                    <input v-model="createForm.email" type="email" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Password</span>
                    <input v-model="createForm.password" type="password" required autocomplete="new-password" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Repeat</span>
                    <input v-model="createForm.password_confirmation" type="password" required autocomplete="new-password" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Role</span>
                    <select v-model="createForm.role" class="field">
                        <option value="member">Member</option>
                        <option value="admin">Admin</option>
                    </select>
                </label>
                <button class="btn w-fit" :disabled="createForm.processing">Create</button>
                <p v-if="createForm.errors.email || createForm.errors.password" class="text-sm text-clay">
                    {{ createForm.errors.email || createForm.errors.password }}
                </p>
            </form>
        </Modal>

        <Modal v-if="editing" v-model="editOpen" title="Edit user">
            <form class="grid gap-3" @submit.prevent="savePerson">
                <label class="grid gap-1">
                    <span class="kicker">Name</span>
                    <input v-model="editForm.name" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Email</span>
                    <input v-model="editForm.email" type="email" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">New password</span>
                    <input v-model="editForm.password" type="password" autocomplete="new-password" class="field" placeholder="Leave blank to keep">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Repeat</span>
                    <input v-model="editForm.password_confirmation" type="password" autocomplete="new-password" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Role</span>
                    <select v-model="editForm.role" class="field" :disabled="editForm.role === 'superadmin'">
                        <option v-if="editForm.role === 'superadmin'" value="superadmin">Superadmin</option>
                        <option value="member">Member</option>
                        <option value="admin">Admin</option>
                    </select>
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="editForm.is_active" type="checkbox" class="size-4 accent-palm" :disabled="editForm.role === 'superadmin'">
                    Account is on
                </label>
                <button class="btn w-fit" :disabled="editForm.processing">Save</button>
                <p v-if="editForm.errors.email || editForm.errors.role || editForm.errors.is_active || editForm.errors.password" class="text-sm text-clay">
                    {{ editForm.errors.email || editForm.errors.role || editForm.errors.is_active || editForm.errors.password }}
                </p>
            </form>
        </Modal>

    </AppLayout>
</template>
