import React from "react";

interface PlanHeaderProbs {
    title: string;
}

export const PlanHeader: React.FC<PlanHeaderProbs> = ({title}) => {
    return <h2>{title}</h2>;
}
