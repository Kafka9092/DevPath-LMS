import React from "react";
import { Link } from "@inertiajs/react";
 
interface Crumb {
    label: string;
    path: string;
}
 
interface BreadcrumbsProps {
    crumbs: Crumb[];
}
 
const ChevronRight = () => (
    <svg className="w-3.5 h-3.5 text-slate-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
    </svg>
);
 
export const Breadcrumbs: React.FC<BreadcrumbsProps> = ({ crumbs }) => {
    return (
        <nav className="flex w-full items-center justify-start gap-1.5 pt-4 pb-1">
            {crumbs.map((crumb, index) => {
                const isLast = index === crumbs.length - 1;
                return (
                    <React.Fragment key={`${index}-${crumb.label}`}>
                        {isLast ? (
                            <span className="text-xs font-semibold text-violet-700 dark:text-violet-400 tracking-wide">
                                {crumb.label}
                            </span>
                        ) : (
                            <Link
                                href={crumb.path}
                                className="text-xs font-medium text-slate-400 hover:text-violet-500 dark:text-gray-500 dark:hover:text-violet-400 transition-colors duration-150 tracking-wide"
                            >
                                {crumb.label}
                            </Link>
                        )}
                        {!isLast && <ChevronRight />}
                    </React.Fragment>
                );
            })}
        </nav>
    );
};
