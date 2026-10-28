<script setup lang="ts">
export type IconName =
    | 'home' | 'log' | 'plus' | 'plan' | 'more' | 'bills' | 'loan' | 'savings'
    | 'vehicle' | 'year' | 'people' | 'out' | 'income' | 'spend' | 'bell'
    | 'chevron-left' | 'chevron-right' | 'close' | 'trash' | 'warn' | 'check'
    | 'calendar' | 'chart' | 'clock' | 'house' | 'lock' | 'wallet'

const props = withDefaults(defineProps<{
    name: IconName
    size?: number
}>(), {
    size: 22,
})

const paths: Record<IconName, string> = {
    home: 'M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z',
    log: 'M7 4h10a2 2 0 0 1 2 2v14l-3.2-1.6L12 20l-3.8-1.6L5 20V6a2 2 0 0 1 2-2zm3 5h4M8 13h8M8 17h5',
    plus: 'M12 5v14M5 12h14',
    plan: 'M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18zm0 9 6 3.5A7.5 7.5 0 0 0 12 4.5z',
    more: 'M5 12h.01M12 12h.01M19 12h.01',
    bills: 'M6 4h12a1 1 0 0 1 1 1v16l-3-1.4L12 21l-4-1.4L5 21V5a1 1 0 0 1 1-1zm3 5h6M9 13h6',
    loan: 'M4 10h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-4h12l2 4H4z',
    savings: 'M4 10h16v9H4zm8-7 8 5H4zM9 14h2v4H9zm4 0h2v4h-2z',
    vehicle: 'M5 16 6.5 9h11L19 16M7 16.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm10 0a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zM8 9l1.2-3h5.6L16 9',
    year: 'M7 4v2M17 4v2M5 9h14M6 6h12a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1z',
    people: 'M12 12a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 12 12zM5.5 20a6.5 6.5 0 0 1 13 0',
    out: 'M9 7V5a1 1 0 0 1 1-1h9v16h-9a1 1 0 0 1-1-1v-2M13 12H4m0 0 3-3M4 12l3 3',
    income: 'M12 19V6m0 0 4 4M12 6 8 10',
    spend: 'M12 5v13m0 0 4-4m-4 4-4-4',
    bell: 'M15 18a3 3 0 0 1-6 0M6.5 10a5.5 5.5 0 1 1 11 0c0 4 1.5 5.5 1.5 5.5H5S6.5 14 6.5 10z',
    'chevron-left': 'M14.5 6 9 12l5.5 6',
    'chevron-right': 'M9.5 6 15 12l-5.5 6',
    close: 'M7 7l10 10M17 7 7 17',
    trash: 'M9 5h6l1 2h4v2H4V7h4zm1 5v8m4-8v8M7 9l1 11h8l1-11',
    warn: 'M12 4 21 19H3zm0 6v4m0 3h.01',
    check: 'M5 12.5 9.5 17 19 7',
    calendar: 'M7 4v2M17 4v2M5 9h14M6 6h12a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1z',
    chart: 'M5 19V10m7 9V5m7 14v-7',
    clock: 'M12 8v5l3 2m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
    house: 'M4 11 12 4l8 7v9H4zM10 20v-6h4v6',
    lock: 'M8 11V8a4 4 0 1 1 8 0v3M6 11h12v9H6z',
    wallet: 'M4 8h16v11H4zm0 0 2.2-3h11.6L20 8M15.5 13.5H19',
}
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path :d="paths[name]" />
    </svg>
</template>
