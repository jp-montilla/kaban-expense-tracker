<script setup lang="ts">
import { BarController, BarElement, CategoryScale, Chart, Legend, LinearScale, Tooltip } from 'chart.js'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { pesos } from '../lib/money'

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend)

interface Point {
    label: string
    full_label: string
    income: number | null
    actual: number
    cash_left: number | null
}

const props = defineProps<{
    points: Point[]
}>()

const canvas = ref<HTMLCanvasElement | null>(null)
let chart: Chart | null = null

function render() {
    if (!canvas.value) {
        return
    }

    chart?.destroy()
    chart = new Chart(canvas.value, {
        type: 'bar',
        data: {
            labels: props.points.map((point) => point.label),
            datasets: [
                {
                    label: 'Pay',
                    data: props.points.map((point) => point.income ?? 0),
                    backgroundColor: '#5b4ce8',
                    borderRadius: 3,
                    maxBarThickness: 18,
                },
                {
                    label: 'Expenses',
                    data: props.points.map((point) => point.actual),
                    backgroundColor: '#e15d5d',
                    borderRadius: 3,
                    maxBarThickness: 18,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        boxHeight: 12,
                        color: '#1b1833',
                        font: { family: 'Plus Jakarta Sans, sans-serif', size: 12 },
                    },
                },
                tooltip: {
                    callbacks: {
                        title: (items) => props.points[items[0]?.dataIndex ?? 0]?.full_label ?? '',
                        label: (item) => `${item.dataset.label}: ${pesos(Number(item.raw))}`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#6b6f86', font: { family: 'Plus Jakarta Sans, sans-serif' } },
                },
                y: {
                    grid: { color: '#e4e5ef' },
                    ticks: {
                        color: '#6b6f86',
                        font: { family: 'Plus Jakarta Sans, sans-serif' },
                        callback: (value) => pesos(Number(value)).replace('.00', ''),
                    },
                },
            },
        },
    })
}

onMounted(render)
watch(() => props.points, render, { deep: true })
onBeforeUnmount(() => chart?.destroy())
</script>

<template>
    <div class="h-72">
        <canvas ref="canvas" role="img" aria-label="Pay and money out for the last twelve months" />
    </div>
</template>
