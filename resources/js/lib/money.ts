export function pesos(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }

    const sign = value < 0 ? '−' : '';
    const formatted = Math.abs(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    return `${sign}₱${formatted}`;
}

export function tone(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return 'text-ink';
    }

    return value < 0 ? 'text-clay' : 'text-palm-deep';
}
