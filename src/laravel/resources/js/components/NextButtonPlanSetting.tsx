import React from 'react';

interface NextButtonPlanSettingProps {
    onClick: () => void;
    disabled?: boolean;
}

export const NextButtonPlanSetting: React.FC<NextButtonPlanSettingProps> = ({ onClick, disabled }) => {
    return (
        <div className="flex justify-center">
            <button
                onClick={onClick}
                disabled={disabled}
                className={`
                    px-8 py-4 rounded-xl font-semibold text-base
                    transition-all duration-150 active:scale-95
                    ${!disabled
                        ? "bg-violet-600 hover:bg-orange-500 text-white shadow-md shadow-violet-200 cursor-pointer"
                        : "bg-slate-200 text-slate-400 cursor-not-allowed"
                    }
                `}
            >
                Продолжить
            </button>
        </div>
    );
};
