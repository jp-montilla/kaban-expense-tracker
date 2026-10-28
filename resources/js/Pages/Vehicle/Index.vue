<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '../../Components/AppLayout.vue'
import DashPanel from '../../Components/DashPanel.vue'
import OverviewTiles from '../../Components/OverviewTiles.vue'
import type { OverviewTile } from '../../Components/OverviewTiles.vue'
import PageHeader from '../../Components/PageHeader.vue'
import PageHero from '../../Components/PageHero.vue'
import Modal from '../../Components/Modal.vue'
import { confirmDelete } from '../../lib/confirm'
import { pesos } from '../../lib/money'

interface VehicleCard {
    id: number
    name: string
    make: string | null
    model: string | null
    year: number | null
    plate: string | null
    current_odometer: number
    due_count: number
    spent: number
}

interface DocumentRow {
    id: string
    kind: string
    name: string
    issued_on: string | null
    expires_on: string | null
    renewal_months: number | null
    notes: string | null
    days_left: number | null
    status: string
}

const props = withDefaults(defineProps<{
    vehicles: VehicleCard[]
    documents?: DocumentRow[]
}>(), {
    documents: () => [],
})

const addingVehicle = ref(false)
const addingDocument = ref(false)

const vehicleForm = useForm({
    name: '',
    make: '',
    model: '',
    year: '',
    plate: '',
    current_odometer: '0',
    notes: '',
    seed_bike_parts: true,
})

const documentForm = useForm({
    kind: 'license',
    name: "Driver's license",
    issued_on: '',
    expires_on: '',
    renewal_months: '12',
    notes: '',
})

function addVehicle() {
    vehicleForm.post('/vehicles', {
        preserveScroll: true,
        onSuccess: () => {
            vehicleForm.reset()
            vehicleForm.seed_bike_parts = true
            addingVehicle.value = false
        },
    })
}

function addDocument() {
    documentForm.post('/documents', {
        preserveScroll: true,
        onSuccess: () => {
            documentForm.reset()
            documentForm.kind = 'license'
            documentForm.name = "Driver's license"
            documentForm.renewal_months = '12'
            addingDocument.value = false
        },
    })
}

function removeDocument(doc: DocumentRow) {
    confirmDelete({
        title: 'Delete this document?',
        message: doc.name,
        run: () => router.delete(`/documents/${doc.id}`, { preserveScroll: true }),
    })
}

function prettyDay(iso: string | null) {
    if (! iso) {
        return ''
    }

    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}

function km(value: number) {
    return `${value.toLocaleString('en-PH')} km`
}

const totalSpent = computed(() => props.vehicles.reduce((sum, row) => sum + row.spent, 0))
const totalDue = computed(() => props.vehicles.reduce((sum, row) => sum + row.due_count, 0))

const overview = computed<OverviewTile[]>(() => [
    { label: 'Spend', value: pesos(totalSpent.value), icon: 'spend', wrap: 'bg-[#FDECEC] text-spend' },
    { label: 'Due', value: String(totalDue.value), valueClass: totalDue.value > 0 ? 'text-clay' : '', icon: 'warn', wrap: 'bg-[#FFF4E5] text-gold' },
    { label: 'Vehicles', value: String(props.vehicles.length), icon: 'vehicle', wrap: 'bg-[#E8F1FF] text-income' },
    { label: 'Papers', value: String(props.documents.length), icon: 'bills', wrap: 'bg-[#EEEBFF] text-accent' },
])
</script>

<template>
    <Head title="Vehicles" />
    <AppLayout>
        <template #header>
            <PageHeader title="Vehicles" subtitle="Garage, papers, and service spend." />
        </template>
        <template #hero>
            <PageHero
                label="Service spend"
                :value="pesos(totalSpent)"
                :hint="totalDue > 0 ? `${totalDue} due` : `${vehicles.length} vehicles`"
            />
        </template>
        <DashPanel title="Overview" icon="chart" icon-wrap="bg-[#EEEBFF] text-accent">
            <div class="px-5 pb-5">
                <OverviewTiles :tiles="overview" />
            </div>
        </DashPanel>

        <DashPanel class="mt-4" title="Garage" icon="vehicle" icon-wrap="bg-[#E8F1FF] text-income">
            <template #actions>
                <button type="button" class="btn-ghost py-1 text-xs" @click="addingVehicle = true">Add</button>
            </template>
            <table class="data-table">
                <caption class="sr-only">Vehicles</caption>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Plate</th>
                        <th scope="col" class="text-right">Odometer</th>
                        <th scope="col" class="text-right">Spend</th>
                        <th scope="col"><span class="sr-only">Due</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="vehicles.length === 0">
                        <td colspan="5" class="text-moss">No vehicles yet.</td>
                    </tr>
                    <tr v-for="row in vehicles" :key="row.id">
                        <td>
                            <Link :href="`/vehicles/${row.id}`" class="font-medium hover:text-accent">
                                {{ row.make ? `${row.make} ${row.name}` : row.name }}
                            </Link>
                            <span v-if="row.year" class="text-moss"> · {{ row.year }}</span>
                        </td>
                        <td class="whitespace-nowrap text-moss">{{ row.plate || '—' }}</td>
                        <td class="num text-right">{{ km(row.current_odometer) }}</td>
                        <td class="num text-right">{{ pesos(row.spent) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <span v-if="row.due_count > 0" class="text-clay">{{ row.due_count }} due</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </DashPanel>

        <DashPanel class="mt-4" title="Papers" icon="bills" icon-wrap="bg-[#EEEBFF] text-accent">
            <template #actions>
                <button type="button" class="btn-ghost py-1 text-xs" @click="addingDocument = true">Add</button>
            </template>
            <table class="data-table">
                <caption class="sr-only">Licenses and papers</caption>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Expires</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="documents.length === 0">
                        <td colspan="4" class="text-moss">No papers yet.</td>
                    </tr>
                    <tr v-for="doc in documents" :key="doc.id">
                        <td>
                            {{ doc.name }}
                            <span class="text-moss"> · {{ doc.kind }}</span>
                        </td>
                        <td class="whitespace-nowrap text-moss">
                            {{ doc.expires_on ? prettyDay(doc.expires_on) : '—' }}
                            <template v-if="doc.renewal_months"> · {{ doc.renewal_months }} mo</template>
                        </td>
                        <td>
                            {{ doc.status === 'overdue' ? 'Expired' : doc.status === 'soon' ? 'Due soon' : doc.status === 'ok' ? 'Current' : 'Open' }}
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <button type="button" class="row-del ml-0" @click="removeDocument(doc)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </DashPanel>

        <Modal v-if="addingDocument" v-model="addingDocument" title="Add document">
            <form class="grid gap-3" @submit.prevent="addDocument">
                <label class="grid gap-1">
                    <span class="kicker">Kind</span>
                    <select v-model="documentForm.kind" class="field">
                        <option value="license">Driver’s license</option>
                        <option value="registration">Registration</option>
                        <option value="insurance">Insurance</option>
                        <option value="other">Other</option>
                    </select>
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Name</span>
                    <input v-model="documentForm.name" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Issued</span>
                    <input v-model="documentForm.issued_on" type="date" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Expires</span>
                    <input v-model="documentForm.expires_on" type="date" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Renew every (months)</span>
                    <input v-model="documentForm.renewal_months" type="number" min="1" max="120" class="field">
                </label>
                <button class="btn w-fit" :disabled="documentForm.processing">Save</button>
            </form>
        </Modal>

        <Modal v-if="addingVehicle" v-model="addingVehicle" title="Add vehicle">
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="addVehicle">
                <label class="grid gap-1">
                    <span class="kicker">Name</span>
                    <input v-model="vehicleForm.name" required class="field" placeholder="Z400, Civic, Mio">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Make</span>
                    <input v-model="vehicleForm.make" class="field" placeholder="Kawasaki">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Model</span>
                    <input v-model="vehicleForm.model" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Year</span>
                    <input v-model="vehicleForm.year" type="number" min="1950" max="2100" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Plate</span>
                    <input v-model="vehicleForm.plate" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Odometer</span>
                    <input v-model="vehicleForm.current_odometer" type="number" min="0" required class="field">
                </label>
                <label class="grid gap-1 sm:col-span-2">
                    <span class="kicker">Notes</span>
                    <input v-model="vehicleForm.notes" class="field">
                </label>
                <label class="flex items-center gap-2 text-sm sm:col-span-2">
                    <input v-model="vehicleForm.seed_bike_parts" type="checkbox" class="size-4 accent-palm">
                    Start with a common bike checklist (oil, filters, chain, brakes, tires)
                </label>
                <div class="sm:col-span-2">
                    <button class="btn" :disabled="vehicleForm.processing">Save</button>
                    <p v-if="vehicleForm.errors.name" class="mt-2 text-sm text-clay">{{ vehicleForm.errors.name }}</p>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
