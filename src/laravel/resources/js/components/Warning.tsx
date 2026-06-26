import React from "react";

interface WarningProbs {
    message: string;
    onConfirm: () => void;
}

export const Warning: React.FC<WarningProbs> = ({message, onConfirm}) => {
    return (
        <div>
            <p>{message}</p>
        </div>
    );
};
