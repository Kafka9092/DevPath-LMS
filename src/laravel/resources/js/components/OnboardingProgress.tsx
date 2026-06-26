import React from "react";

interface OnboardingProgressProps {
    current: number;  
    total: number;
    labels: string[];
}

export const OnboardingProgress: React.FC<OnboardingProgressProps> = ({ current, total, labels }) => {
    return (
        <div className="w-full">
            {}
            <div className="flex items-center gap-0">
                {Array.from({ length: total }, (_, i) => {
                    const step     = i + 1;
                    const isDone   = step < current;
                    const isActive = step === current;

                    return (
                        <React.Fragment key={step}>
                            {}
                            <div className="flex flex-col items-center gap-1.5">
                                <div className={`
                                    w-8 h-8 rounded-full flex items-center justify-center
                                    text-xs font-bold border-2 transition-all duration-300
                                    ${isDone   ? "bg-violet-600 border-violet-600 text-white"
                                    : isActive ? "bg-white dark:bg-gray-900 border-violet-600 text-violet-700 dark:text-violet-300 shadow-md shadow-violet-200 dark:shadow-violet-900/30"
                                               : "bg-white dark:bg-gray-900 border-slate-200 dark:border-gray-700 text-slate-400 dark:text-gray-500"}
                                `}>
                                    {isDone ? (
                                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={3} stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    ) : step}
                                </div>
                                <span className={`
                                    text-[10px] font-semibold tracking-wide whitespace-nowrap
                                    ${isActive ? "text-violet-700 dark:text-violet-300" : isDone ? "text-violet-400" : "text-slate-300 dark:text-gray-600"}
                                `}>
                                    {labels[i]}
                                </span>
                            </div>

                            {}
                            {i < total - 1 && (
                                <div className={`
                                    flex-1 h-0.5 mb-5 mx-1 rounded-full transition-all duration-500
                                    ${isDone ? "bg-violet-500" : "bg-slate-200 dark:bg-gray-700"}
                                `} />
                            )}
                        </React.Fragment>
                    );
                })}
            </div>
        </div>
    );
};
