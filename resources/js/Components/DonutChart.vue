<script setup lang="ts">
import { ArcElement, Chart, DoughnutController, Tooltip } from 'chart.js'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { pesos } from '../lib/money'

Chart.register(DoughnutController, ArcElement, Tooltip)

interface Slice {
    name: string
    spent: number
    color: string
    share: number
}

const props = defineProps<{
    slices: Slice[]
    total: number
    label?: string
}>()

const canvas = ref<HTMLCanvasElement | null>(null)
let chart: Chart | null = null

const visible = computed(() => props.slices.filter((slice) => Math.abs(slice.spent) > 0.004).slice(0, 5))

function render() {
    if (! canvas.value) {
        return
    }

    chart?.destroy()

    const data = visible.value.length
        ? visible.value
        : [{ name: 'None', spent: 1, color: '#ececf4', share: 100 }]

    chart = new Chart(canvas.value, {
        type: 'doughnut',
        data: {
            labels: data.map((slice) => slice.name),
            datasets: [{
                data: data.map((slice) => Math.abs(slice.spent)),
                backgroundColor: data.map((slice) => slice.color),
                borderWidth: 0,
                spacing: 3,
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '74%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (item) => {
                            const slice = visible.value[item.dataIndex]

                            if (! slice) {
                                return 'Nothing logged'
                            }

                            return `${slice.name}: ${pesos(slice.spent)} (${slice.share}%)`
                        },
                    },
                },
            },
        },
    })
}

onMounted(render)
watch(() => props.slices, render, { deep: true })
onBeforeUnmount(() => chart?.destroy())
</script>

<template>
    <div class="relative mx-auto size-56">
        <canvas ref="canvas" role="img" :aria-label="label ?? 'Spend by category'" />
        <div class="pointer-events-none absolute inset-0 grid place-items-center text-center">
            <div>
                <p class="text-xs font-medium text-moss">{{ label ?? 'Total' }}</p>
                <p class="num mt-0.5 text-xl tracking-tight">{{ pesos(total) }}</p>
            </div>
        </div>
    </div>
</template>
