export function formatPercent(value: number | null | undefined, fallback = "—"): string {
    if (value == null || Number.isNaN(value)) {
        return fallback;
    }
    return `${Math.round(value * 10) / 10}%`;
}

export function formatVsPlatform(delta: number | null | undefined): string | undefined {
    if (delta == null || Number.isNaN(delta)) {
        return undefined;
    }
    const sign = delta >= 0 ? "+" : "";
    return `${sign}${delta} к среднему по платформе`;
}
