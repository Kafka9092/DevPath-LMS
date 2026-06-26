import React from "react";

interface PlanSettingButtonProbs {
    onClick: () => void;
    disabled?: boolean;
}

export const PlanSettingButton: React.FC<PlanSettingButtonProbs> = ({onClick, disabled}) => {
    return (
        <button onClick={onClick} disabled={disabled}>
            Продолжить
        </button>
    );
};
