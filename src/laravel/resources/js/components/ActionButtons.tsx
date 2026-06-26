import React from 'react';

interface ActionButtonsProps {
    onStartTest: () => void;
    onCreatePlan: () => void;
}

export const ActionButtons: React.FC<ActionButtonsProps> = ({ onStartTest, onCreatePlan }) => {
    return (
        <div className="flex gap-3">
            <button
                onClick={onStartTest}
                className="
                    flex items-center gap-2 px-5 py-3 rounded-xl
                    bg-violet-600 hover:bg-orange-500 active:scale-95
                    text-white text-sm font-semibold
                    shadow-md shadow-violet-200 dark:shadow-none
                    transition-all duration-150
                "
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" strokeWidth={2.2} strokeLinecap="round" strokeLinejoin="round"
                    className="w-4 h-4"
                >
                    <polygon points="5 3 19 12 5 21 5 3" />
                </svg>
                Пройти тест
            </button>

            <button
                onClick={onCreatePlan}
                className="
                    flex items-center gap-2 px-5 py-3 rounded-xl
                    border border-violet-400 bg-white hover:bg-orange-50 hover:border-orange-500
                    text-violet-600 dark:border-violet-600 dark:bg-gray-900 dark:hover:bg-orange-950/30 dark:hover:text-orange-400 dark:hover:border-orange-500 text-sm font-semibold
                    transition-all duration-150 active:scale-95
                "
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" strokeWidth={2.2} strokeLinecap="round" strokeLinejoin="round"
                    className="w-4 h-4 text-violet-500"
                >
                    <path d="M12 5v14M5 12h14" />
                </svg>
                Создать с нуля
            </button>
        </div>
    );
};
