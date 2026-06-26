export interface LanguageVisual {
    slug: string;
    label: string;
    accent: string;
    accentMuted: string;
    tint: string;
}

const ACCENT = "#7c3aed";
const ACCENT_MUTED = "#a78bfa";
const TINT = "transparent";

const DEFAULT: LanguageVisual = {
    slug: "default",
    label: "Code",
    accent: ACCENT,
    accentMuted: ACCENT_MUTED,
    tint: TINT,
};

const MAP: Record<string, LanguageVisual> = {
    php:        { slug: "php",        label: "PHP",        accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    python:     { slug: "python",     label: "Python",     accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    javascript: { slug: "javascript", label: "JavaScript", accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    js:         { slug: "javascript", label: "JavaScript", accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    typescript: { slug: "typescript", label: "TypeScript", accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    java:       { slug: "java",       label: "Java",       accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    "c++":      { slug: "cpp",        label: "C++",        accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    cpp:        { slug: "cpp",        label: "C++",        accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    "c#":       { slug: "csharp",     label: "C#",         accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    csharp:     { slug: "csharp",     label: "C#",         accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    go:         { slug: "go",         label: "Go",         accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    ruby:       { slug: "ruby",       label: "Ruby",       accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    rust:       { slug: "default",    label: "Rust",       accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    kotlin:     { slug: "default",    label: "Kotlin",     accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    swift:      { slug: "default",    label: "Swift",      accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
    c:          { slug: "default",    label: "C",          accent: ACCENT, accentMuted: ACCENT_MUTED, tint: TINT },
};

export function getLanguageVisual(direction: string): LanguageVisual {
    const key = direction.trim().toLowerCase();
    return MAP[key] ?? DEFAULT;
}

const LOGO_SLUGS = new Set([
    "php", "python", "javascript", "typescript", "java", "cpp", "csharp", "go", "ruby",
]);

export function languageLogoUrl(slug: string, isDark = false): string {
    const file = LOGO_SLUGS.has(slug) ? slug : "default";

    if (isDark && LOGO_SLUGS.has(file)) {
        return `/lang-logos/${file}_edited.svg`;
    }

    if (file === "default") {
        return isDark ? `/lang-logos/javascript_edited.svg` : `/lang-logos/javascript.svg`;
    }

    return `/lang-logos/${file}.svg`;
}
