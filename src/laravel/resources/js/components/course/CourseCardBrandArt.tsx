import React from "react";
import { getLanguageVisual, languageLogoUrl } from "@/lib/courseLanguageVisual";
import { COURSE_CARD_ART_WIDTH_CLASS } from "./courseCardLayout";

export function CourseCardBrandArt({ direction }: { direction: string }) {
    const visual = getLanguageVisual(direction);
    const lightSrc = languageLogoUrl(visual.slug, false);
    const darkSrc = languageLogoUrl(visual.slug, true);

    /** Левая часть watermark плавно растворяется под текст */
    const logoMask =
        "linear-gradient(to right, transparent 0%, rgba(0,0,0,0.45) 10%, black 25%, black 100%)";

    return (
        <div
            className={`absolute inset-y-0 right-0 ${COURSE_CARD_ART_WIDTH_CLASS} overflow-hidden pointer-events-none`}
            aria-hidden
        >
            <div className="absolute top-1/2 -translate-y-1/2 right-[-7%] w-[112%] max-w-[270px] aspect-square">
                <div
                    className="absolute inset-0 z-10"
                    style={{
                        WebkitMaskImage: logoMask,
                        maskImage: logoMask,
                    }}
                >
                    {/* Светлая тема — серый svg, приглушённо */}
                    <img
                        src={lightSrc}
                        alt=""
                        draggable={false}
                        className="absolute inset-0 h-full w-full object-contain object-center select-none opacity-20 dark:hidden"
                    />
                    {/* Тёмная тема — белый *_edited.svg, как маленькая иконка */}
                    <img
                        src={darkSrc}
                        alt=""
                        draggable={false}
                        className="absolute inset-0 hidden h-full w-full object-contain object-center select-none dark:block"
                    />
                </div>

                <div
                    className="absolute inset-y-0 left-0 w-[28%] overflow-hidden dark:hidden"
                    style={{
                        WebkitMaskImage:
                            "linear-gradient(to right, black 0%, black 38%, transparent 100%)",
                        maskImage:
                            "linear-gradient(to right, black 0%, black 38%, transparent 100%)",
                    }}
                >
                    <img
                        src={lightSrc}
                        alt=""
                        draggable={false}
                        className="absolute inset-[-25%] h-[150%] w-[150%] object-contain blur-3xl select-none opacity-10"
                    />
                </div>
            </div>

            {/* В light — подложка под текст; в dark не нужна — перекрывала белую иконку */}
            <div
                className="absolute inset-y-0 left-0 z-0 w-[48%] bg-gradient-to-r from-white via-white/98 to-transparent dark:hidden"
            />
        </div>
    );
}
