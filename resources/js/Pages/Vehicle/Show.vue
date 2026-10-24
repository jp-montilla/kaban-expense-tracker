<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '../../Components/AppLayout.vue'
import PageHero from '../../Components/PageHero.vue'
import Modal from '../../Components/Modal.vue'
import { confirmDelete } from '../../lib/confirm'
import { pesos } from '../../lib/money'

interface PartOption {
    id: number
    name: string
    slug: string
    interval_km: number | null
    interval_months: number | null
}

interface Card {
    id: number
    name: string
    slug: string
    interval_km: number | null
    interval_months: number | null
    last_on: string | null
    last_odo: number | null
    last_cost: number | null
    next_due_odo: number | null
    next_due_on: string | null
    km_left: number | null
    days_left: number | null
    status: 'ok' | 'soon' | 'overdue' | 'never'
}

interface LogRow {
    id: number
    part_id: number
    part: string | null
    serviced_on: string
    odometer: number | null
    cost: number | null
    shop: string | null
    next_due_odo: number | null
    next_due_on: string | null
    notes: string | null
    has_expense: boolean
}

interface VehicleRow {
    id: number
    name: string
    make: string | null
    model: string | null
    year: number | null
    plate: string | null
    current_odometer: number
    notes: string | null
}

const props = defineProps<{
    vehicles: Array<{ id: number; name: string }>
    vehicle: VehicleRow
    parts: PartOption[]
    cards: Card[]
    logs: LogRow[]
    spent: number
    due_count: number
}>()

const page = usePage<{ today: string }>()
const editingVehicle = ref(false)
const addingPart = ref(false)
const editingPart = ref<PartOption | null>(null)
const logging = ref(false)

const editPartOpen = computed({
    get: () => editingPart.value !== null,
    set: (open) => {
        if (!open) {
            editingPart.value = null
        }
    },
})


const editVehicleForm = useForm({
    name: '',
    make: '',
    model: '',
    year: '',
    plate: '',
    current_odometer: 0,
    notes: '',
})

const partForm = useForm({
    name: '',
    interval_km: '',
    interval_months: '',
})

const editPartForm = useForm({
    name: '',
    interval_km: '',
    interval_months: '',
})

const odoForm = useForm({
    current_odometer: props.vehicle?.current_odometer ?? 0,
    notes: props.vehicle?.notes ?? '',
})

const logForm = useForm({
    maintenance_part_id: props.parts[0]?.id ?? null,
    serviced_on: page.props.today,
    odometer: '',
    cost: '',
    shop: '',
    notes: '',
    log_as_expense: true,
})

const selectedPart = computed(() => props.parts.find((part) => part.id === Number(logForm.maintenance_part_id)))
const title = computed(() => props.vehicle?.name ?? 'Vehicle')

const history = computed(() => {
    const groups: Array<{
        key: string
        ids: number[]
        title: string
        serviced_on: string
        parts: string[]
        odometer: number | null
        shop: string | null
        cost: number | null
        has_expense: boolean
    }> = []
    const index = new Map<string, number>()

    for (const row of props.logs) {
        const groupTitle = row.notes || row.part || 'Service'
        const key = `${row.serviced_on}|${groupTitle}`
        const existing = index.get(key)

        if (existing === undefined) {
            index.set(key, groups.length)
            groups.push({
                key,
                ids: [row.id],
                title: groupTitle,
                serviced_on: row.serviced_on,
                parts: row.part ? [row.part] : [],
                odometer: row.odometer,
                shop: row.shop,
                cost: row.cost,
                has_expense: row.has_expense,
            })
            continue
        }

        const group = groups[existing]
        group.ids.push(row.id)
        if (row.part && !group.parts.includes(row.part)) {
            group.parts.push(row.part)
        }
        if (group.cost === null) {
            group.cost = row.cost
        } else if (row.cost !== null) {
            group.cost += row.cost
        }
        group.has_expense = group.has_expense || row.has_expense
        if (group.odometer === null) {
            group.odometer = row.odometer
        }
        if (!group.shop) {
            group.shop = row.shop
        }
    }

    return groups
})

watch(() => props.vehicle, (value) => {
    odoForm.current_odometer = value?.current_odometer ?? 0
    odoForm.notes = value?.notes ?? ''
    logForm.maintenance_part_id = props.parts[0]?.id ?? null
}, { deep: true })

function km(value: number | null | undefined) {
    if (value === null || value === undefined) {
        return '—'
    }

    return `${value.toLocaleString('en-PH')} km`
}

function prettyDay(iso: string | null) {
    if (!iso) {
        return '—'
    }

    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}

function statusLabel(status: Card['status']) {
    return {
        ok: 'On time',
        soon: 'Due soon',
        overdue: 'Overdue',
        never: 'Not logged',
    }[status]
}

function saveOdo() {
    if (! props.vehicle) {
        return
    }

    odoForm.put(`/vehicles/${props.vehicle.id}`, { preserveScroll: true })
}

function openEditVehicle() {
    if (! props.vehicle) {
        return
    }

    editVehicleForm.name = props.vehicle.name
    editVehicleForm.make = props.vehicle.make ?? ''
    editVehicleForm.model = props.vehicle.model ?? ''
    editVehicleForm.year = props.vehicle.year ? String(props.vehicle.year) : ''
    editVehicleForm.plate = props.vehicle.plate ?? ''
    editVehicleForm.current_odometer = props.vehicle.current_odometer
    editVehicleForm.notes = props.vehicle.notes ?? ''
    editingVehicle.value = true
}

function saveVehicle() {
    if (! props.vehicle) {
        return
    }

    editVehicleForm.put(`/vehicles/${props.vehicle.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingVehicle.value = false
        },
    })
}

function removeVehicle() {
    if (! props.vehicle) {
        return
    }

    confirmDelete({
        title: 'Remove this vehicle?',
        message: `${props.vehicle.name} and its service history will be deleted.`,
        confirmLabel: 'Remove',
        run: () => router.delete(`/vehicles/${props.vehicle.id}`, { preserveScroll: true }),
    })
}

function addPart() {
    if (! props.vehicle) {
        return
    }

    partForm.post(`/vehicles/${props.vehicle.id}/parts`, {
        preserveScroll: true,
        onSuccess: () => {
            partForm.reset()
            addingPart.value = false
        },
    })
}

function openEditPart(part: PartOption) {
    editPartForm.name = part.name
    editPartForm.interval_km = part.interval_km ? String(part.interval_km) : ''
    editPartForm.interval_months = part.interval_months ? String(part.interval_months) : ''
    editingPart.value = part
}

function savePart() {
    if (! props.vehicle || ! editingPart.value) {
        return
    }

    editPartForm.put(`/vehicles/${props.vehicle.id}/parts/${editingPart.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingPart.value = null
        },
    })
}

function removePart(part: { id: number; name: string }) {
    if (! props.vehicle) {
        return
    }

    confirmDelete({
        title: 'Remove this part?',
        message: `${part.name}. Service logs for it will go too.`,
        confirmLabel: 'Remove',
        run: () => router.delete(`/vehicles/${props.vehicle.id}/parts/${part.id}`, { preserveScroll: true }),
    })
}

function submitLog() {
    if (! props.vehicle) {
        return
    }

    logForm.post(`/vehicles/${props.vehicle.id}/logs`, {
        preserveScroll: true,
        onSuccess: () => {
            logForm.reset('cost', 'shop', 'notes', 'odometer')
            logForm.serviced_on = page.props.today
            logForm.log_as_expense = true
            logging.value = false
        },
    })
}

function removeLog(row: { ids: number[]; title: string }) {
    if (! props.vehicle) {
        return
    }

    confirmDelete({
        title: 'Remove this service?',
        message: row.title,
        confirmLabel: 'Remove',
        run: () => {
            row.ids.forEach((id) => {
                router.delete(`/vehicles/${props.vehicle!.id}/logs/${id}`, { preserveScroll: true })
            })
        },
    })
}
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <template #hero>
            <PageHero
                :eyebrow="`Vehicles · ${vehicle.name}`"
                label="Service spend"
                :value="pesos(spent)"
                :pill="`${due_count} due`"
            />
        </template>

        <nav v-if="vehicles.length > 1" class="card flex flex-wrap gap-2 px-3 py-2.5" aria-label="Vehicles">
            <Link
                v-for="row in vehicles"
                :key="row.id"
                :href="`/vehicles/${row.id}`"
                class="btn-ghost py-1 text-xs"
                :class="vehicle.id === row.id ? 'is-on' : ''"
            >
                {{ row.name }}
            </Link>
        </nav>

        <form class="card flex flex-wrap items-end gap-3 px-3 py-3" :class="vehicles.length > 1 ? 'mt-4' : ''" @submit.prevent="saveOdo">
            <label class="grid min-w-40 flex-1 gap-1">
                <span class="kicker">Odometer</span>
                <input v-model="odoForm.current_odometer" type="number" min="0" step="1" required class="field py-1.5">
            </label>
            <button class="btn py-1.5 text-xs" :disabled="odoForm.processing">Save</button>
            <button type="button" class="btn-ghost py-1 text-xs" @click="openEditVehicle">Edit</button>
            <button type="button" class="btn-ghost py-1 text-xs text-clay" @click="removeVehicle">Delete</button>
            <p class="w-full text-xs text-moss">{{ due_count }} {{ due_count === 1 ? 'item due' : 'items due' }}</p>
        </form>

        <section class="card mt-4 overflow-x-auto">
            <div class="sheet-head">
                <h2>Maintenance</h2>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost py-1 text-xs" :disabled="parts.length === 0" @click="logging = true">Log</button>
                    <button type="button" class="btn-ghost py-1 text-xs" @click="addingPart = true">Add part</button>
                </div>
            </div>
            <table class="data-table">
                <caption class="sr-only">Maintenance</caption>
                <thead>
                    <tr>
                        <th scope="col">Part</th>
                        <th scope="col">Last</th>
                        <th scope="col">Next</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="cards.length === 0">
                        <td colspan="5" class="text-moss">Add oil, tires, filters.</td>
                    </tr>
                    <tr v-for="card in cards" :key="card.id">
                        <td>
                            {{ card.name }}
                            <span v-if="card.interval_km || card.interval_months" class="text-moss">
                                ·
                                <template v-if="card.interval_km">{{ card.interval_km.toLocaleString('en-PH') }} km</template>
                                <template v-if="card.interval_km && card.interval_months"> / </template>
                                <template v-if="card.interval_months">{{ card.interval_months }} mo</template>
                            </span>
                        </td>
                        <td class="whitespace-nowrap text-moss">
                            <template v-if="card.last_on">{{ prettyDay(card.last_on) }}<template v-if="card.last_odo !== null"> · {{ km(card.last_odo) }}</template></template>
                            <template v-else>Never</template>
                        </td>
                        <td class="whitespace-nowrap text-moss">
                            <template v-if="card.next_due_odo">{{ km(card.next_due_odo) }}</template>
                            <template v-if="card.next_due_odo && card.next_due_on"> · </template>
                            <template v-if="card.next_due_on">{{ prettyDay(card.next_due_on) }}</template>
                            <template v-if="!card.next_due_odo && !card.next_due_on">—</template>
                        </td>
                        <td :class="card.status === 'overdue' ? 'text-clay' : card.status === 'soon' ? 'text-gold' : 'text-moss'">
                            {{ statusLabel(card.status) }}
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <button type="button" class="row-edit" @click="openEditPart(card)">Edit</button>
                            <button type="button" class="row-del" @click="removePart(card)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card mt-4 overflow-x-auto">
            <div class="sheet-head">
                <h2>History</h2>
            </div>
            <table class="data-table">
                <caption class="sr-only">Service history</caption>
                <thead>
                    <tr>
                        <th scope="col">Service</th>
                        <th scope="col">Date</th>
                        <th scope="col" class="text-right">Cost</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="history.length === 0">
                        <td colspan="4" class="text-moss">No services yet.</td>
                    </tr>
                    <tr v-for="row in history" :key="row.key">
                        <td>
                            {{ row.title }}
                            <span class="text-moss">
                                <template v-if="row.parts.length"> · {{ row.parts.join(', ') }}</template>
                                <template v-if="row.odometer !== null"> · {{ km(row.odometer) }}</template>
                                <template v-if="row.shop"> · {{ row.shop }}</template>
                            </span>
                        </td>
                        <td class="whitespace-nowrap text-moss">{{ prettyDay(row.serviced_on) }}</td>
                        <td class="num text-right">{{ row.cost === null ? '—' : pesos(row.cost) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <button type="button" class="row-del ml-0" @click="removeLog(row)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <Modal v-if="editingVehicle && vehicle" v-model="editingVehicle" title="Edit vehicle">
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="saveVehicle">
                <label class="grid gap-1">
                    <span class="kicker">Name</span>
                    <input v-model="editVehicleForm.name" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Make</span>
                    <input v-model="editVehicleForm.make" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Model</span>
                    <input v-model="editVehicleForm.model" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Year</span>
                    <input v-model="editVehicleForm.year" type="number" min="1950" max="2100" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Plate</span>
                    <input v-model="editVehicleForm.plate" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Odometer</span>
                    <input v-model="editVehicleForm.current_odometer" type="number" min="0" required class="field">
                </label>
                <label class="grid gap-1 sm:col-span-2">
                    <span class="kicker">Notes</span>
                    <input v-model="editVehicleForm.notes" class="field">
                </label>
                <div class="sm:col-span-2">
                    <button class="btn" :disabled="editVehicleForm.processing">Save</button>
                </div>
            </form>
        </Modal>

        <Modal v-if="addingPart" v-model="addingPart" title="Add part">
            <form class="grid gap-3" @submit.prevent="addPart">
                <label class="grid gap-1">
                    <span class="kicker">Name</span>
                    <input v-model="partForm.name" required class="field" placeholder="Engine oil">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Every km</span>
                    <input v-model="partForm.interval_km" type="number" min="1" class="field" placeholder="6000">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Every months</span>
                    <input v-model="partForm.interval_months" type="number" min="1" class="field" placeholder="6">
                </label>
                <button class="btn w-fit" :disabled="partForm.processing">Save</button>
            </form>
        </Modal>

        <Modal v-if="editingPart" v-model="editPartOpen" title="Edit part">
            <form class="grid gap-3" @submit.prevent="savePart">
                <label class="grid gap-1">
                    <span class="kicker">Name</span>
                    <input v-model="editPartForm.name" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Every km</span>
                    <input v-model="editPartForm.interval_km" type="number" min="1" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Every months</span>
                    <input v-model="editPartForm.interval_months" type="number" min="1" class="field">
                </label>
                <button class="btn w-fit" :disabled="editPartForm.processing">Save</button>
            </form>
        </Modal>

        <Modal v-if="logging && vehicle" v-model="logging" title="Log service">
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="submitLog">
                <label class="grid gap-1 sm:col-span-2">
                    <span class="kicker">What</span>
                    <select v-model="logForm.maintenance_part_id" required class="field">
                        <option v-for="part in parts" :key="part.id" :value="part.id">{{ part.name }}</option>
                    </select>
                    <p v-if="selectedPart" class="text-sm text-moss">
                        Interval
                        <template v-if="selectedPart.interval_km">{{ selectedPart.interval_km.toLocaleString('en-PH') }} km</template>
                        <template v-if="selectedPart.interval_km && selectedPart.interval_months"> / </template>
                        <template v-if="selectedPart.interval_months">{{ selectedPart.interval_months }} mo</template>
                    </p>
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Date</span>
                    <input v-model="logForm.serviced_on" type="date" required class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Odo</span>
                    <input v-model="logForm.odometer" type="number" min="0" step="1" class="field" placeholder="km">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Cost</span>
                    <input v-model="logForm.cost" type="number" min="0" step="0.01" class="field">
                </label>
                <label class="grid gap-1">
                    <span class="kicker">Shop</span>
                    <input v-model="logForm.shop" class="field">
                </label>
                <label class="grid gap-1 sm:col-span-2">
                    <span class="kicker">Notes</span>
                    <input v-model="logForm.notes" class="field">
                </label>
                <label class="flex items-center gap-2 text-sm sm:col-span-2">
                    <input v-model="logForm.log_as_expense" type="checkbox" class="size-4 accent-palm">
                    Count as a spend
                </label>
                <div class="sm:col-span-2">
                    <button class="btn" :disabled="logForm.processing">Save</button>
                    <p v-if="logForm.errors.maintenance_part_id || logForm.errors.serviced_on" class="mt-2 text-sm text-clay">
                        {{ logForm.errors.maintenance_part_id || logForm.errors.serviced_on }}
                    </p>
                </div>
            </form>
        </Modal>

    </AppLayout>
</template>
