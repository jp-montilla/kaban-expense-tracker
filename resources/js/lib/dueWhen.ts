export type DueMode = 'first' | 'second' | 'exact' | null

export function dueLabel(mode: DueMode, day: number | null): string | null {
    if (mode === 'first') {
        return '1–15'
    }

    if (mode === 'second') {
        return '16–end'
    }

    if (mode === 'exact' && day) {
        return ordinal(day)
    }

    return null
}

function ordinal(day: number) {
    const mod = day % 100

    if (mod >= 11 && mod <= 13) {
        return `${day}th`
    }

    return `${day}${['th', 'st', 'nd', 'rd'][day % 10] ?? 'th'}`
}
