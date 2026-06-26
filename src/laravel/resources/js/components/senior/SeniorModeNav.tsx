import React from "react";
import { Link } from "@inertiajs/react";
import { buildSeniorHubUrl } from "@/lib/seniorProgramStorage";

interface SeniorModeNavProps {
    direction: string;
    current?: string;
}

export const SeniorModeNav: React.FC<SeniorModeNavProps> = ({ direction, current }) => (
    <div className="flex flex-wrap items-center justify-between gap-3 mb-6 pb-4 border-b border-violet-100">
        <Link
            href={buildSeniorHubUrl(direction)}
            className="text-sm font-medium text-violet-600 hover:text-violet-800 transition-colors"
        >
            ← Все режимы Senior
        </Link>
        {current && (
            <span className="text-xs font-medium uppercase tracking-wider text-slate-400">
                {current}
            </span>
        )}
    </div>
);
