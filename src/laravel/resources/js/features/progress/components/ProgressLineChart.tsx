import React, { useState } from "react";
import type { LanguageTimelinePoint } from "@/types/progress";

const Y_TICKS = [0, 25, 50, 75, 100];

export function ProgressLineChart({
    points,
    color = "#2563eb",
    height = 240,
}: {
    points: LanguageTimelinePoint[];
    color?: string;
    height?: number;
}) {
    const [hovered, setHovered] = useState<number | null>(null);

    if (points.length === 0) return null;

    const min = 0;
    const max = 100;
    const range = max - min;
    const w = 100;
    const h = 100;
    const padLeft = 14;
    const padRight = 4;
    const padTop = 6;
    const padBottom = 4;
    const chartW = w - padLeft - padRight;
    const chartH = h - padTop - padBottom;

    const coords = points.map((p, i) => {
        const x = padLeft + (i / Math.max(1, points.length - 1)) * chartW;
        const y = padTop + (1 - (p.value - min) / range) * chartH;
        return { x, y, ...p, index: i };
    });

    const lineD = coords.map((c, i) => `${i === 0 ? "M" : "L"}${c.x},${c.y}`).join(" ");

    const active = hovered != null ? coords[hovered] : null;

    return (
        <div className="relative w-full">
            <svg viewBox={`0 0 ${w} ${h}`} className="w-full select-none" style={{ height }}>
                {Y_TICKS.map((tick) => {
                    const y = padTop + (1 - (tick - min) / range) * chartH;
                    return (
                        <g key={tick}>
                            <line
                                x1={padLeft}
                                y1={y}
                                x2={w - padRight}
                                y2={y}
                                stroke="currentColor"
                                className="text-slate-200 dark:text-gray-700"
                                strokeWidth="0.35"
                            />
                            <text
                                x={padLeft - 1.5}
                                y={y + 1.2}
                                textAnchor="end"
                                fill="currentColor"
                                className="text-slate-400 dark:text-gray-500"
                                fontSize="3.2"
                            >
                                {tick}
                            </text>
                        </g>
                    );
                })}

                <path d={lineD} fill="none" stroke={color} strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />

                {coords.map((c) => (
                    <g key={c.index}>
                        <circle
                            cx={c.x}
                            cy={c.y}
                            r="4"
                            fill="transparent"
                            className="cursor-pointer"
                            onMouseEnter={() => setHovered(c.index)}
                            onMouseLeave={() => setHovered(null)}
                        />
                        <circle
                            cx={c.x}
                            cy={c.y}
                            r={hovered === c.index ? 3.2 : 2.2}
                            fill="white"
                            stroke={color}
                            strokeWidth={hovered === c.index ? 1.6 : 1.2}
                            className="pointer-events-none transition-all"
                        />
                    </g>
                ))}

                {active && (
                    <line
                        x1={active.x}
                        y1={padTop}
                        x2={active.x}
                        y2={padTop + chartH}
                        stroke={color}
                        strokeOpacity="0.35"
                        strokeWidth="0.5"
                        strokeDasharray="1.5 1.5"
                    />
                )}
            </svg>

            {active && (
                <div
                    className="pointer-events-none absolute z-10 max-w-[240px] rounded-xl border border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2.5 shadow-lg text-left"
                    style={{
                        left: `${(active.x / w) * 100}%`,
                        top: `${(active.y / h) * 100 - 12}%`,
                        transform: "translate(-50%, -100%)",
                    }}
                >
                    <p className="text-[11px] font-semibold text-slate-900 dark:text-gray-100 leading-snug">
                        {active.tooltip ?? `${active.label} — ${active.value}`}
                    </p>
                    {active.module && active.kind !== "start" && active.kind !== "now" && (
                        <p className="text-[10px] text-slate-500 dark:text-gray-400 mt-1">
                            {active.module} · {active.result}
                        </p>
                    )}
                </div>
            )}

            <div className="flex justify-between mt-2 text-[10px] text-slate-400 px-1">
                {points
                    .filter((_, i) => i === 0 || i === points.length - 1 || i % Math.ceil(points.length / 4) === 0)
                    .map((p) => (
                        <span key={`${p.date}-${p.label}`}>{p.label}</span>
                    ))}
            </div>
        </div>
    );
}
