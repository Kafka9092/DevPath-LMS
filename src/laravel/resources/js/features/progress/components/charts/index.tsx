import React from "react";
import { MODULE_CHART_COLORS } from "../../constants/moduleThemes";

export { StatCard } from "../ui/StatCard";
export { CHART_COLORS } from "./chartColors";
export {
    CompletionRing,
    VerdictStrip,
    RankedBarChart,
    StackedLangActivityBars,
    ScoreBucketBars,
    StarScoreBars,
    InlineBar,
} from "./visualizations";

export function DonutChart({
    segments,
    size = 160,
    centerLabel,
    centerValue,
}: {
    segments: { label: string; value: number; color: string }[];
    size?: number;
    centerLabel?: string;
    centerValue?: string;
}) {
    const total = segments.reduce((s, x) => s + x.value, 0) || 1;
    const r = 54;
    const c = 2 * Math.PI * r;
    let offset = 0;

    return (
        <div className="flex flex-col sm:flex-row items-center gap-6">
            <svg width={size} height={size} viewBox="0 0 128 128" className="shrink-0 -rotate-90">
                <circle cx="64" cy="64" r={r} fill="none" stroke="#e2e8f0" strokeWidth="14" className="dark:stroke-gray-700" />
                {segments.filter((s) => s.value > 0).map((seg) => {
                    const len = (seg.value / total) * c;
                    const el = (
                        <circle
                            key={seg.label}
                            cx="64"
                            cy="64"
                            r={r}
                            fill="none"
                            stroke={seg.color}
                            strokeWidth="14"
                            strokeDasharray={`${len} ${c - len}`}
                            strokeDashoffset={-offset}
                            strokeLinecap="round"
                        />
                    );
                    offset += len;
                    return el;
                })}
            </svg>
            <div className="flex-1 space-y-2 min-w-0">
                {(centerValue || centerLabel) && (
                    <div className="mb-3 text-center sm:text-left">
                        {centerValue && <p className="text-2xl font-bold text-slate-900 dark:text-gray-50">{centerValue}</p>}
                        {centerLabel && <p className="text-xs text-slate-500">{centerLabel}</p>}
                    </div>
                )}
                {segments.map((s) => (
                    <div key={s.label} className="flex items-center justify-between gap-2 text-sm">
                        <span className="flex items-center gap-2 min-w-0">
                            <span className="w-2.5 h-2.5 rounded-full shrink-0" style={{ background: s.color }} />
                            <span className="text-slate-600 dark:text-gray-300 truncate">{s.label}</span>
                        </span>
                        <span className="font-semibold text-slate-900 dark:text-gray-100 tabular-nums">{s.value}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

export function HorizontalBars({
    items,
    valueKey,
    labelKey,
    maxValue,
    color = MODULE_CHART_COLORS.learning,
}: {
    items: Record<string, unknown>[];
    valueKey: string;
    labelKey: string;
    maxValue?: number;
    color?: string;
}) {
    const max = maxValue ?? Math.max(1, ...items.map((i) => Number(i[valueKey]) || 0));

    return (
        <div className="space-y-3">
            {items.map((item, idx) => {
                const val = Number(item[valueKey]) || 0;
                const pct = Math.round((val / max) * 100);
                return (
                    <div key={idx}>
                        <div className="flex justify-between text-sm mb-1">
                            <span className="font-medium text-slate-700 dark:text-gray-200">{String(item[labelKey])}</span>
                            <span className="text-slate-500 tabular-nums">{val}</span>
                        </div>
                        <div className="h-2.5 rounded-full bg-slate-100 dark:bg-gray-800 overflow-hidden">
                            <div
                                className="h-full rounded-full transition-all duration-500"
                                style={{ width: `${pct}%`, background: color }}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export function StackedActivityChart({
    days,
}: {
    days: { label: string; learning: number; interviews: number; code_review: number }[];
}) {
    const max = Math.max(1, ...days.map((d) => d.learning + d.interviews + d.code_review));
    const { learning, interviews, codeReview } = MODULE_CHART_COLORS;

    return (
        <div className="flex items-end gap-1 h-44 pt-4">
            {days.map((d, i) => {
                const total = d.learning + d.interviews + d.code_review;
                const h = total > 0 ? Math.max(8, (total / max) * 100) : 4;
                const lPct = total > 0 ? (d.learning / total) * 100 : 0;
                const iPct = total > 0 ? (d.interviews / total) * 100 : 0;
                const cPct = total > 0 ? (d.code_review / total) * 100 : 0;

                return (
                    <div key={i} className="flex-1 flex flex-col items-center gap-1 min-w-0 group">
                        <div
                            className="w-full max-w-[14px] rounded-t-md overflow-hidden flex flex-col justify-end opacity-90 group-hover:opacity-100 transition-opacity"
                            style={{ height: `${h}%` }}
                            title={`${d.label}: ${total}`}
                        >
                            {total > 0 ? (
                                <>
                                    <div style={{ height: `${cPct}%`, background: codeReview }} />
                                    <div style={{ height: `${iPct}%`, background: interviews }} />
                                    <div style={{ height: `${lPct}%`, background: learning }} />
                                </>
                            ) : (
                                <div className="h-full bg-slate-200 dark:bg-gray-700 rounded-t-md" />
                            )}
                        </div>
                        {i % 5 === 0 && (
                            <span className="text-[9px] text-slate-400 truncate w-full text-center">{d.label}</span>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

export function BucketChart({
    buckets,
    color = MODULE_CHART_COLORS.learning,
}: {
    buckets: { label: string; count: number }[];
    color?: string;
}) {
    const max = Math.max(1, ...buckets.map((b) => b.count));

    return (
        <div className="flex items-end justify-between gap-2 h-36">
            {buckets.map((b) => (
                <div key={b.label} className="flex-1 flex flex-col items-center gap-2">
                    <span className="text-xs font-semibold text-slate-600 dark:text-gray-300 tabular-nums">{b.count}</span>
                    <div
                        className="w-full max-w-12 rounded-t-lg transition-all"
                        style={{
                            height: `${Math.max(6, (b.count / max) * 100)}%`,
                            backgroundColor: color,
                        }}
                    />
                    <span className="text-[10px] text-slate-500 text-center">{b.label}</span>
                </div>
            ))}
        </div>
    );
}

export function StarRadar({
    scores,
    strokeColor = MODULE_CHART_COLORS.interviews,
}: {
    scores: { label: string; avg: number }[];
    strokeColor?: string;
}) {
    const n = scores.length || 4;
    const cx = 80;
    const cy = 80;
    const maxR = 56;
    const maxVal = 5;

    const point = (i: number, val: number) => {
        const angle = (Math.PI * 2 * i) / n - Math.PI / 2;
        const r = (val / maxVal) * maxR;
        return { x: cx + r * Math.cos(angle), y: cy + r * Math.sin(angle) };
    };

    const gridLevels = [1, 2, 3, 4, 5];
    const dataPoints = scores.map((s, i) => point(i, s.avg));
    const pathD = dataPoints.map((p, i) => `${i === 0 ? "M" : "L"}${p.x},${p.y}`).join(" ") + " Z";

    return (
        <div className="flex flex-col items-center gap-4">
            <svg viewBox="0 0 160 160" className="w-44 h-44">
                {gridLevels.map((lvl) => {
                    const pts = scores.map((_, i) => point(i, lvl));
                    const d = pts.map((p, i) => `${i === 0 ? "M" : "L"}${p.x},${p.y}`).join(" ") + " Z";
                    return <path key={lvl} d={d} fill="none" stroke="#e2e8f0" strokeWidth="1" className="dark:stroke-gray-700" />;
                })}
                {scores.map((s, i) => {
                    const outer = point(i, maxVal);
                    const inner = point(i, 0);
                    return (
                        <g key={s.label}>
                            <line x1={inner.x} y1={inner.y} x2={outer.x} y2={outer.y} stroke="#e2e8f0" className="dark:stroke-gray-700" />
                            <text x={outer.x} y={outer.y} textAnchor="middle" dominantBaseline="middle" className="fill-slate-500 text-[9px] font-bold">
                                {s.label}
                            </text>
                        </g>
                    );
                })}
                <path d={pathD} fill={`${strokeColor}40`} stroke={strokeColor} strokeWidth="2" />
            </svg>
            <div className="flex flex-wrap justify-center gap-3 text-xs text-slate-600 dark:text-gray-400">
                {scores.map((s) => (
                    <span key={s.label}>
                        {s.label}: <strong style={{ color: strokeColor }}>{s.avg}/5</strong>
                    </span>
                ))}
            </div>
        </div>
    );
}

export function LineChart({
    points,
    startValue,
    color = MODULE_CHART_COLORS.learning,
    height = 200,
}: {
    points: { label: string; value: number }[];
    startValue: number;
    color?: string;
    height?: number;
}) {
    if (points.length === 0) return null;

    const values = points.map((p) => p.value);
    const dataMin = Math.min(startValue, ...values);
    const dataMax = Math.max(startValue, ...values);
    const padding = Math.max(3, (dataMax - dataMin) * 0.15);
    const min = Math.max(0, dataMin - padding);
    const max = Math.min(100, dataMax + padding);
    const range = max - min || 1;
    const w = 100;
    const h = 100;
    const pad = 8;

    const coords = points.map((p, i) => {
        const x = pad + (i / Math.max(1, points.length - 1)) * (w - pad * 2);
        const y = pad + (1 - (p.value - min) / range) * (h - pad * 2);
        return { x, y, ...p };
    });

    const lineD = coords.map((c, i) => `${i === 0 ? "M" : "L"}${c.x},${c.y}`).join(" ");
    const startY = pad + (1 - (startValue - min) / range) * (h - pad * 2);

    return (
        <div className="w-full">
            <svg viewBox={`0 0 ${w} ${h}`} className="w-full" style={{ height }}>
                <line x1={pad} y1={startY} x2={w - pad} y2={startY} stroke={color} strokeOpacity="0.35" strokeWidth="0.6" strokeDasharray="2 2" />
                <path d={lineD} fill="none" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                {coords.map((c, i) => (
                    <g key={i}>
                        <circle cx={c.x} cy={c.y} r="2.5" fill="white" stroke={color} strokeWidth="1.2" />
                        {(i === 0 || i === coords.length - 1) && (
                            <text x={c.x} y={c.y - 4} textAnchor="middle" fill={color} fontSize="5" fontWeight="bold">
                                {c.value}
                            </text>
                        )}
                    </g>
                ))}
            </svg>
            <div className="flex justify-between mt-2 text-[10px] text-slate-400 px-1">
                {points
                    .filter((_, i) => i === 0 || i === points.length - 1 || i % Math.ceil(points.length / 5) === 0)
                    .map((p) => (
                        <span key={p.label}>{p.label}</span>
                    ))}
            </div>
        </div>
    );
}
