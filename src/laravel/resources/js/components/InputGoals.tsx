import React from "react";

interface InputGoalsProbs {
    goal: string;
    onGoalChange: (goal: string) => void;
}


export const InputGoals: React.FC<InputGoalsProbs> = ({goal, onGoalChange}) => {
    return (
        <div>
            <p>Напиши цель своего обучения?</p>
            <textarea
                rows={3}
                cols={40}
                value={goal}
                onChange={(e) => onGoalChange(e.target.value)}
                placeholder= "цель"
            />
        </div>
    );
};
