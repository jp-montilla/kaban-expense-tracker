<script setup lang="ts">
import { computed } from 'vue'
import type { IconName } from './Icon.vue'
import Icon from './Icon.vue'

export interface OverviewTile {
    label: string
    value: string
    hint?: string
    valueClass?: string
    icon: IconName
    wrap: string
}

const props = defineProps<{
    tiles: OverviewTile[]
}>()

const gridClass = computed(() => {
    const count = props.tiles.length

    if (count <= 3) {
        return 'grid-cols-2 sm:grid-cols-3'
    }

    if (count === 4) {
        return 'grid-cols-2 sm:grid-cols-4'
    }

    if (count === 7) {
        return 'grid-cols-2 sm:grid-cols-4 xl:grid-cols-7'
    }

    return 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6'
})
</script>

<template>
    <div class="grid gap-x-4 gap-y-5" :class="gridClass">
        <div v-for="tile in tiles" :key="tile.label" class="min-w-0">
            <span class="grid size-10 place-items-center rounded-full" :class="tile.wrap">
                <Icon :name="tile.icon" :size="18" />
            </span>
            <p class="mt-3 text-sm text-moss">{{ tile.label }}</p>
            <p class="num mt-1 truncate text-lg" :class="tile.valueClass">{{ tile.value }}</p>
            <p v-if="tile.hint" class="mt-1 truncate text-xs text-moss">{{ tile.hint }}</p>
        </div>
    </div>
</template>
