import React from "react";
import { LanguageLogoImage } from "@/components/course/LanguageLogoImage";
import { getLanguageVisual } from "@/lib/courseLanguageVisual";

export function LanguageLogoBadge({ direction, size = 40 }: { direction: string; size?: number }) {
    const visual = getLanguageVisual(direction);

    return (
        <div
            className="rounded-xl shrink-0 border border-slate-200/80 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden flex items-center justify-center"
            style={{ width: size, height: size }}
        >
            <LanguageLogoImage
                slug={visual.slug}
                className="w-[78%] h-[78%] object-contain"
            />
        </div>
    );
}
