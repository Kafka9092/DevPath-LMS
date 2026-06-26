import React from "react";
 
interface DirectionSelectorProps {
    directions: string[];
    selectedDirection: string | null;
    onSelect: (direction: string) => void;
}
 
export const DirectionSelector: React.FC<DirectionSelectorProps> = ({
    directions,
    selectedDirection,
    onSelect,
}) => {
    return (
        <div className="space-y-3">
            <p className="text-xs font-semibold text-slate-400 dark:text-gray-500 uppercase tracking-widest">
                Язык программирования
            </p>
 
            <div className="grid grid-cols-3 gap-2.5">
                {directions.map((dir) => {
                    const isSelected = selectedDirection === dir;
                    return (
                        <button
                            key={dir}
                            onClick={() => onSelect(dir)}
                            className={`
                                relative flex items-center justify-center
                                px-3 py-3 rounded-xl border font-semibold text-[13px]
                                transition-all duration-150 active:scale-95 select-none
                                ${isSelected
                                    ? "border-orange-400 bg-orange-50 text-orange-600 shadow-sm shadow-orange-100 dark:border-orange-500 dark:bg-orange-950/40 dark:text-orange-300 dark:shadow-none"
                                    : "border-slate-300 bg-white text-slate-600 surface-control hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-violet-600 dark:hover:bg-violet-950/30 dark:hover:text-violet-300"
                                }
                            `}
                        >
                            {isSelected && (
                                <span className="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-orange-400" />
                            )}
                            {dir}
                        </button>
                    );
                })}
            </div>
        </div>
    );
};
