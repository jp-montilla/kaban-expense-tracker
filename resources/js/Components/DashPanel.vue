<script setup lang="ts">
import type { IconName } from './Icon.vue'
import Icon from './Icon.vue'

withDefaults(defineProps<{
    title: string
    icon?: IconName
    iconWrap?: string
}>(), {
    icon: undefined,
    iconWrap: 'bg-accent/10 text-accent',
})
</script>

<template>
    <section class="card overflow-hidden">
        <div class="sheet-head">
            <h2 class="flex items-center gap-2.5">
                <span
                    v-if="icon"
                    class="grid size-8 place-items-center rounded-xl"
                    :class="iconWrap"
                >
                    <Icon :name="icon" :size="16" />
                </span>
                {{ title }}
            </h2>
            <slot name="actions" />
        </div>
        <div class="min-w-0 overflow-x-auto">
            <slot />
        </div>
    </section>
</template>
