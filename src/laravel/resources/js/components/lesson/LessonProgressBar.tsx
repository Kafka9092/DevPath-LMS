import React from "react";

interface Props {
    step: number;
    total: number;
    label: string;
}

export const LessonProgressBar: React.FC<Props> = ({ step, total, label }) => {
    const pct = total > 0 ? Math.round((step / total) * 100) : 0;

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between text-xs">
                <span className="font-medium text-slate-500">
                    Шаг {step} из {total}
                </span>
                <span className="text-violet-600 font-semibold truncate max-w-[55%] text-right">{label}</span>
            </div>
            <div className="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                <div
                    className="h-full bg-gradient-to-r from-violet-500 to-violet-400 rounded-full transition-all duration-500 ease-out"
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
};
