import React, { useState } from "react";
import { TraceStep } from "../../types/WorkspaceTypes";

interface Props {
    steps: TraceStep[];
    slideTitle?: string;
}

export const TheoryTracePanel: React.FC<Props> = ({ steps, slideTitle }) => {
    const [idx, setIdx] = useState(0);
    const step = steps[idx];

    if (!step) {
        return null;
    }

    return (
        <div className="h-full flex flex-col rounded-lg border border-violet-200 bg-violet-50/30 overflow-hidden">
            <div className="px-3 py-2 border-b border-violet-100">
                <p className="text-[10px] font-semibold text-violet-600 uppercase">Трассировка</p>
                {slideTitle && <p className="text-xs text-gray-600 truncate">{slideTitle}</p>}
            </div>
            <div className="flex-1 p-3 space-y-3 overflow-y-auto min-h-0">
                <div className="rounded-lg bg-gray-900 px-3 py-2 font-mono text-xs text-emerald-300">
                    {step.highlight}
                </div>
                {step.memory && step.memory.length > 0 && (
                    <div className="space-y-2">
                        {step.memory.map((m) => (
                            <div key={m.name} className="flex items-center gap-2 text-xs">
                                <span className="font-mono font-semibold text-violet-700">{m.name}</span>
                                <span className="text-gray-500">=</span>
                                <span className="font-mono text-gray-800">{m.value}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
            <div className="shrink-0 flex items-center justify-between gap-2 px-3 py-2 border-t border-violet-100">
                <button
                    type="button"
                    disabled={idx <= 0}
                    onClick={() => setIdx((i) => Math.max(0, i - 1))}
                    className="px-2 py-1 text-xs rounded border border-gray-200 disabled:opacity-40"
                >
                    ← Назад
                </button>
                <span className="text-[10px] text-gray-400">{idx + 1} / {steps.length}</span>
                <button
                    type="button"
                    disabled={idx >= steps.length - 1}
                    onClick={() => setIdx((i) => Math.min(steps.length - 1, i + 1))}
                    className="px-2 py-1 text-xs rounded border border-gray-200 disabled:opacity-40"
                >
                    Далее →
                </button>
            </div>
        </div>
    );
};
