import { ref } from 'vue'

export const loggingOpen = ref(false)

export function openLog() {
    loggingOpen.value = true
}
