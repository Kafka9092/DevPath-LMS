import React from "react";

interface PlanActionsProbs {
    onStart: () => void;
    onRetakeTest: () => void;
}

export const PlanActions: React.FC<PlanActionsProbs> = ({ onStart, onRetakeTest}) => {
    return (
        <div>
            <button onClick={onStart}>Начать обучение</button>
            <button onClick={onRetakeTest}>Пройти тест</button>
        </div>
    )
}
