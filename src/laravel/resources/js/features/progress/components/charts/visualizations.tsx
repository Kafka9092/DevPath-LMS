import React from "react";
import { LanguageLogoBadge } from "@/components/course/LanguageLogoBadge";
import { MODULE_CHART_COLORS } from "../../constants/moduleThemes";
import type { LanguageOverviewRow } from "../../lib/selectors";

export function CompletionRing({
    value,
    max = 100,
    label,
    color = MODULE_CHART_COLORS.learning,
    size = 120,
}: {
    value: number;
    max?: number;
    label: string;
    color?: string;
    size?: number;
}) {
    const pct = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;
    const r = 42;
    const c = 2 * Math.PI * r;
    const dash = (pct / 100) * c;

    return (
        <div className="flex flex-col items-center gap-3">
            <div className="relative" style={{ width: size, height: size }}>
                <svg width={size} height={size} viewBox="0 0 100 100" className="-rotate-90">
                    <circle cx="50" cy="50" r={r} fill="none" stroke="#e2e8f0" strokeWidth="8" className="dark:stroke-gray-700" />
                    <circle
                        cx="50"
                        cy="50"
                        r={r}
                        fill="none"
                        stroke={color}
                        strokeWidth="8"
                        strokeDasharray={`${dash} ${c - dash}`}
                        strokeLinecap="round"
                        className="transition-all duration-700"
                    />
                </svg>
                <div className="absolute inset-0 flex items-center justify-center">
                    <p className="text-2xl font-bold tabular-nums text-slate-900 dark:text-gray-50">{pct}%</p>
                </div>
            </div>
            <p className="text-xs text-slate-500 text-center max-w-[160px] leading-snug">{label}</p>
        </div>
    );
}

export function VerdictStrip({
    successful,
    rejected,
    other,
}: {
    successful: number;
    rejected: number;
    other: number;
}) {
    const total = successful + rejected + other || 1;
    const parts = [
        { label: "Принято", value: successful, color: "#10b981" },
        { label: "Отказ", value: rejected, color: "#ef4444" },
        { label: "Другое", value: other, color: "#94a3b8" },
    ].filter((p) => p.value > 0);

    return (
        <div className="space-y-3">
            <div className="flex h-4 rounded-full overflow-hidden bg-slate-100 dark:bg-gray-800">
                {parts.map((p) => (
                    <div
                        key={p.label}
                        className="h-full transition-all duration-500"
                        style={{ width: `${(p.value / total) * 100}%`, background: p.color }}
                        title={`${p.label}: ${p.value}`}
                    />
                ))}
            </div>
            <div className="flex flex-wrap gap-4 text-xs">
                {parts.map((p) => (
                    <span key={p.label} className="flex items-center gap-1.5 text-slate-600 dark:text-gray-400">
                        <span className="w-2.5 h-2.5 rounded-full" style={{ background: p.color }} />
                        {p.label}: <strong className="tabular-nums text-slate-800 dark:text-gray-200">{p.value}</strong>
                    </span>
                ))}
            </div>
        </div>
    );
}

export function RankedBarChart({
    items,
    color,
    valueSuffix = "",
    maxItems,
}: {
    items: { label: string; value: number; sublabel?: string }[];
    color: string;
    valueSuffix?: string;
    maxItems?: number;
}) {
    const active = items.filter((i) => i.value > 0);
    const shown = maxItems ? active.slice(0, maxItems) : active;
    const max = Math.max(1, ...shown.map((i) => i.value));

    if (shown.length === 0) {
        return <p className="text-sm text-slate-500 text-center py-6">Пока нет данных для графика</p>;
    }

    return (
        <div className="space-y-3.5">
            {shown.map((item, idx) => {
                const pct = Math.max(4, Math.round((item.value / max) * 100));
                return (
                    <div key={item.label} className="group">
                        <div className="flex items-center gap-2 mb-1.5">
                            <span className="text-[10px] font-bold tabular-nums w-5 text-slate-400">#{idx + 1}</span>
                            <span className="text-sm font-medium text-slate-800 dark:text-gray-200 flex-1 truncate">
                                {item.label}
                            </span>
                            <span className="text-sm font-semibold tabular-nums" style={{ color }}>
                                {item.value}{valueSuffix}
                            </span>
                        </div>
                        <div className="ml-7 h-2.5 rounded-full bg-slate-100 dark:bg-gray-800 overflow-hidden">
                            <div
                                className="h-full rounded-full transition-all duration-700 ease-out group-hover:opacity-90"
                                style={{
                                    width: `${pct}%`,
                                    backgroundColor: color,
                                }}
                            />
                        </div>
                        {item.sublabel && (
                            <p className="ml-7 text-[10px] text-slate-400 mt-0.5">{item.sublabel}</p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

export function StackedLangActivityBars({ rows }: { rows: LanguageOverviewRow[] }) {
    const active = rows.filter((r) => r.total > 0).sort((a, b) => b.total - a.total);
    const max = Math.max(1, ...active.map((r) => r.total));
    const { learning, interviews, codeReview } = MODULE_CHART_COLORS;

    if (active.length === 0) {
        return <p className="text-sm text-slate-500 text-center py-8">Пока нет активности</p>;
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-4 text-xs text-slate-500 mb-2">
                <span className="flex items-center gap-1.5">
                    <span className="w-2.5 h-2.5 rounded-sm" style={{ background: learning }} />
                    Уроки
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="w-2.5 h-2.5 rounded-sm" style={{ background: interviews }} />
                    Собеседования
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="w-2.5 h-2.5 rounded-sm" style={{ background: codeReview }} />
                    Code Review
                </span>
            </div>
            {active.map((row) => {
                const barW = Math.max(6, (row.total / max) * 100);
                const lPct = row.total > 0 ? (row.lessons / row.total) * 100 : 0;
                const iPct = row.total > 0 ? (row.interviews / row.total) * 100 : 0;
                const cPct = row.total > 0 ? (row.reviews / row.total) * 100 : 0;

                return (
                    <div key={row.language} className="flex items-center gap-3">
                        <div className="w-28 shrink-0 flex items-center gap-2 min-w-0">
                            <LanguageLogoBadge direction={row.language} size={22} />
                            <span className="text-sm font-medium text-slate-800 dark:text-gray-200 truncate">
                                {row.language}
                            </span>
                        </div>
                        <div className="flex-1 flex items-center gap-2 min-w-0">
                            <div
                                className="h-3 rounded-full overflow-hidden flex bg-slate-100 dark:bg-gray-800 transition-all"
                                style={{ width: `${barW}%`, minWidth: row.total > 0 ? "2rem" : 0 }}
                            >
                                {row.lessons > 0 && (
                                    <div style={{ width: `${lPct}%`, background: learning }} title={`Уроки: ${row.lessons}`} />
                                )}
                                {row.interviews > 0 && (
                                    <div style={{ width: `${iPct}%`, background: interviews }} title={`Собеседования: ${row.interviews}`} />
                                )}
                                {row.reviews > 0 && (
                                    <div style={{ width: `${cPct}%`, background: codeReview }} title={`Code Review: ${row.reviews}`} />
                                )}
                            </div>
                            <span className="text-xs font-semibold tabular-nums text-slate-500 w-6 text-right shrink-0">
                                {row.total}
                            </span>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export function ScoreBucketBars({
    buckets,
    color = MODULE_CHART_COLORS.learning,
}: {
    buckets: { label: string; count: number }[];
    color?: string;
}) {
    const max = Math.max(1, ...buckets.map((b) => b.count));
    const hasData = buckets.some((b) => b.count > 0);

    if (!hasData) {
        return <p className="text-sm text-slate-500">Пока нет оценённых уроков</p>;
    }

    return (
        <div className="flex items-end justify-between gap-2 h-32 pt-2">
            {buckets.map((b) => (
                <div key={b.label} className="flex-1 flex flex-col items-center gap-2 min-w-0">
                    <span className="text-xs font-bold tabular-nums text-slate-700 dark:text-gray-300">{b.count}</span>
                    <div
                        className="w-full max-w-14 rounded-t-lg transition-all duration-500"
                        style={{
                            height: `${Math.max(8, (b.count / max) * 100)}%`,
                            backgroundColor: color,
                        }}
                    />
                    <span className="text-[10px] text-slate-500 text-center leading-tight px-0.5">{b.label}</span>
                </div>
            ))}
        </div>
    );
}

export function StarScoreBars({
    scores,
    color = MODULE_CHART_COLORS.interviews,
}: {
    scores: { label: string; key: string; avg: number }[];
    color?: string;
}) {
    const active = scores.filter((s) => s.avg > 0);
    if (active.length === 0) return null;

    return (
        <div className="space-y-3">
            {active.map((s) => (
                <div key={s.key}>
                    <div className="flex justify-between text-xs mb-1">
                        <span className="text-slate-600 dark:text-gray-400">{s.label}</span>
                        <span className="font-bold tabular-nums" style={{ color }}>{s.avg}/5</span>
                    </div>
                    <div className="h-2 rounded-full bg-slate-100 dark:bg-gray-800 overflow-hidden">
                        <div
                            className="h-full rounded-full transition-all duration-500"
                            style={{ width: `${(s.avg / 5) * 100}%`, background: color }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

export function InlineBar({ value, max, color }: { value: number; max: number; color: string }) {
    const pct = max > 0 ? Math.max(0, Math.min(100, (value / max) * 100)) : 0;
    return (
        <div className="flex items-center gap-2">
            <div className="flex-1 h-1.5 rounded-full bg-slate-100 dark:bg-gray-800 overflow-hidden min-w-[48px]">
                <div className="h-full rounded-full" style={{ width: `${Math.max(value > 0 ? 6 : 0, pct)}%`, background: color }} />
            </div>
            <span className="text-sm tabular-nums text-slate-700 dark:text-gray-300 w-8 text-right">{value}</span>
        </div>
    );
}
