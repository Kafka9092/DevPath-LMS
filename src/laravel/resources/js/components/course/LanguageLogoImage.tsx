import { languageLogoUrl } from '@/lib/courseLanguageVisual';
import { CSSProperties } from 'react';

interface Props {
    slug: string;
    className?: string;
    classNameLight?: string;
    classNameDark?: string;
    alt?: string;
    style?: CSSProperties;
}

/** Серый *.svg в light, белый *_edited.svg в dark (класс html.dark). */
export function LanguageLogoImage({
    slug,
    className = '',
    classNameLight = '',
    classNameDark = '',
    alt = '',
    style,
}: Props) {
    const lightSrc = languageLogoUrl(slug, false);
    const darkSrc = languageLogoUrl(slug, true);

    return (
        <>
            <img
                src={lightSrc}
                alt={alt}
                className={`${className} ${classNameLight} dark:hidden`.trim()}
                style={style}
                draggable={false}
            />
            <img
                src={darkSrc}
                alt={alt}
                className={`${className} ${classNameDark || classNameLight} hidden dark:block`.trim()}
                style={style}
                draggable={false}
            />
        </>
    );
}
