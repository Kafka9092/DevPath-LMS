import React, { useCallback, useEffect, useState } from "react";
import { Link, router, usePage } from "@inertiajs/react";
import { AuthSessionSync } from "@/components/AuthSessionSync";
import { AppTopBar } from "@/components/AppTopBar";
import { AdaptiveTestNotifier } from "@/components/AdaptiveTestNotifier";
import { LessonGenerationNotifier } from "@/components/LessonGenerationNotifier";
import { getLearningHref, isLearningPath } from "@/lib/adaptiveTestStorage";
import { LESSON_WORKSPACE_STORAGE_EVENT } from "@/lib/lessonWorkspaceStorage";
import { activateSeniorPreviewFromSidebar } from "@/lib/seniorPreview";


const IconLearning = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
        <path d="M11.7 2.805a.75.75 0 0 1 .6 0A60.65 60.65 0 0 1 22.83 8.72a.75.75 0 0 1-.231 1.337 49.948 49.948 0 0 0-9.902 3.912l-.003.002c-.114.06-.227.119-.34.18a.75.75 0 0 1-.707 0A50.88 50.88 0 0 0 7.5 12.173v-.224c0-.131.067-.248.172-.311a54.615 54.615 0 0 1 4.653-2.52.75.75 0 0 0-.65-1.352 56.123 56.123 0 0 0-4.78 2.589 1.858 1.858 0 0 0-.859 1.228 49.803 49.803 0 0 0-4.634-1.527.75.75 0 0 1-.231-1.337A60.653 60.653 0 0 1 11.7 2.805Z" />
        <path d="M13.06 15.473a48.45 48.45 0 0 1 7.666-3.282c.134 1.414.22 2.843.255 4.284a.75.75 0 0 1-.46.71 47.87 47.87 0 0 0-8.105 4.342.75.75 0 0 1-.832 0 47.87 47.87 0 0 0-8.104-4.342.75.75 0 0 1-.461-.71c.035-1.442.121-2.87.255-4.286.921.304 1.83.634 2.726.99v1.27a1.5 1.5 0 0 0-.14 2.508c-.09.38-.222.753-.397 1.11.452.213.901.434 1.346.66a6.727 6.727 0 0 0 .551-1.607 1.5 1.5 0 0 0 .14-2.67v-.645a48.549 48.549 0 0 1 3.44 1.667 2.25 2.25 0 0 0 2.12 0Z" />
    </svg>
);

const IconAiHr = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
        <path fillRule="evenodd" d="M7.5 5.25a3 3 0 0 1 3-3h3a3 3 0 0 1 3 3v.205c.933.085 1.857.197 2.774.334 1.454.218 2.476 1.483 2.476 2.917v3.033c0 1.211-.734 2.352-1.936 2.752A24.726 24.726 0 0 1 12 15.75c-2.73 0-5.357-.442-7.814-1.259-1.202-.4-1.936-1.541-1.936-2.752V8.706c0-1.434 1.022-2.7 2.476-2.917A48.814 48.814 0 0 1 7.5 5.455V5.25Zm7.5 0v.09a49.488 49.488 0 0 0-6 0v-.09a1.5 1.5 0 0 1 1.5-1.5h3a1.5 1.5 0 0 1 1.5 1.5Zm-3 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clipRule="evenodd" />
        <path d="M3 18.4v-2.796a4.3 4.3 0 0 0 .713.31A26.226 26.226 0 0 0 12 17.25c2.892 0 5.68-.468 8.287-1.335.252-.084.49-.189.713-.311V18.4c0 1.452-1.047 2.728-2.523 2.923-2.12.282-4.282.427-6.477.427a49.19 49.19 0 0 1-6.477-.427C4.047 21.128 3 19.852 3 18.4Z" />
    </svg>
);

const IconCodeReview = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
        <path fillRule="evenodd" d="M2.25 6a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V6Zm3.97.97a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06l-2.25 2.25a.75.75 0 0 1-1.06-1.06l1.72-1.72-1.72-1.72a.75.75 0 0 1 0-1.06Zm4.28 4.28a.75.75 0 0 0 0 1.5h3a.75.75 0 0 0 0-1.5h-3Z" clipRule="evenodd" />
    </svg>
);

const IconProgress = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
        <path d="M18.375 2.25c-1.035 0-1.875.84-1.875 1.875v15.75c0 1.035.84 1.875 1.875 1.875h.75c1.035 0 1.875-.84 1.875-1.875V4.125c0-1.036-.84-1.875-1.875-1.875h-.75ZM9.75 8.625c0-1.036.84-1.875 1.875-1.875h.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-.75a1.875 1.875 0 0 1-1.875-1.875V8.625ZM3 13.125c0-1.036.84-1.875 1.875-1.875h.75c1.036 0 1.875.84 1.875 1.875v6.75c0 1.035-.84 1.875-1.875 1.875h-.75A1.875 1.875 0 0 1 3 19.875v-6.75Z" />
    </svg>
);

const LogoMark: React.FC<{ onSecretClick?: (e: React.MouseEvent) => void; pulsing?: boolean }> = ({
    onSecretClick,
    pulsing,
}) => (
    <button
        type="button"
        onClick={onSecretClick}
        className={`w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shadow-inner cursor-default border-0 p-0 outline-none focus:outline-none ${
            pulsing ? "ring-2 ring-white/40 ring-offset-2 ring-offset-violet-700" : ""
        }`}
        aria-label=""
        title=""
    >
        <svg viewBox="0 0 24 24" fill="none" className="w-5 h-5 text-white" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
        </svg>
    </button>
);

interface NavItem {
    label: string;
    href: string;
    icon: React.ReactNode;
    routeName?: string;
}

interface SidebarProps {
    useInertia?: boolean;
}

export const SIDEBAR_WIDTH = 100;

const NAV_ITEMS: NavItem[] = [
    { label: "Обучение",      href: "/main",        routeName: "main",        icon: <IconLearning /> },
    { label: "Собеседование", href: "/ai-hr",        routeName: "ai-hr",       icon: <IconAiHr /> },
    { label: "Анализ кода",   href: "/code-review",  routeName: "code-review", icon: <IconCodeReview /> },
    { label: "Прогресс",      href: "/progress",     routeName: "progress",    icon: <IconProgress /> },
];

interface NavButtonProps {
    item: NavItem;
    isActive: boolean;
    useInertia: boolean;
}

const NavButton: React.FC<NavButtonProps> = ({ item, isActive, useInertia }) => {
    const inner = (
        <span
            className={`
                group relative flex flex-col items-center justify-center gap-1.5
                w-[92px] h-16 rounded-2xl cursor-pointer
                transition-all duration-200 ease-out
                ${isActive
                    ? "bg-white/20 text-white shadow-lg shadow-black/20"
                    : "text-violet-200 hover:bg-white/10 hover:text-white"
                }
            `}
        >
            {isActive && (
                <span className="absolute -left-[19px] top-1/2 -translate-y-1/2 w-1 h-7 bg-white rounded-r-full" />
            )}

            {item.icon}

            <span className="max-w-full text-[10px] font-semibold leading-tight tracking-wide text-center px-0.5 whitespace-nowrap">
                {item.label}
            </span>

            {}
            <span className="
                pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2
                bg-gray-900 text-white text-xs font-medium px-2.5 py-1.5 rounded-lg
                whitespace-nowrap shadow-xl
                opacity-0 translate-x-1
                group-hover:opacity-100 group-hover:translate-x-0
                transition-all duration-150
                z-50
            ">
                {item.label}
                <span className="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900" />
            </span>
        </span>
    );

    if (useInertia) {
        return <Link href={item.href}>{inner}</Link>;
    }

    return <a href={item.href}>{inner}</a>;
};

export const Sidebar: React.FC<SidebarProps> = ({ useInertia = true }) => {
    const [seniorPreviewPulse, setSeniorPreviewPulse] = useState(false);

    const handleSecretStarClick = useCallback(async (e: React.MouseEvent) => {
        const ok = await activateSeniorPreviewFromSidebar((url) => router.visit(url));
        if (ok) {
            setSeniorPreviewPulse(true);
            window.setTimeout(() => setSeniorPreviewPulse(false), 1200);
        }
    }, []);

    let currentUrl = "/";
    try {
        if (useInertia) {
            const { url } = usePage();
            currentUrl = url.split("?")[0];
        } else {
            currentUrl = window.location.pathname;
        }
    } catch {
        currentUrl = typeof window !== "undefined" ? window.location.pathname : "/";
    }

    const [learningHref, setLearningHref] = useState("/main");

    useEffect(() => {
        const refresh = () => setLearningHref(getLearningHref());
        refresh();
        window.addEventListener(LESSON_WORKSPACE_STORAGE_EVENT, refresh);
        return () => window.removeEventListener(LESSON_WORKSPACE_STORAGE_EVENT, refresh);
    }, [currentUrl]);

    const navItems = NAV_ITEMS.map((item) =>
        item.routeName === "main" ? { ...item, href: learningHref } : item,
    );

    const isActive = (item: NavItem) => {
        if (item.routeName === "main") {
            return isLearningPath(currentUrl);
        }
        return currentUrl.startsWith(item.href);
    };

    return (
        <aside className="
            fixed left-0 top-0 h-screen z-40
            style={{ width: SIDEBAR_WIDTH }}
            bg-gradient-to-b from-violet-700 to-purple-800
            flex flex-col items-center
            py-5 gap-0
            shadow-2xl shadow-purple-900/40
        ">
            <div className="mb-5 mt-1">
                <LogoMark onSecretClick={handleSecretStarClick} pulsing={seniorPreviewPulse} />
            </div>
            <div className="w-10 h-px bg-white/15 mb-5" />
            <nav className="flex flex-col items-center gap-1 flex-1">
                {navItems.map((item) => (
                    <NavButton
                        key={item.href}
                        item={item}
                        isActive={isActive(item)}
                        useInertia={useInertia}
                    />
                ))}
            </nav>
        </aside>
    );
};

interface AppLayoutProps {
    children: React.ReactNode;
}

export const AppLayout: React.FC<AppLayoutProps> = ({ children }) => (
    <div className="flex min-h-screen bg-white dark:bg-gray-950">
        <AuthSessionSync />
        <Sidebar />
        <div className="flex min-h-screen flex-1 flex-col bg-white dark:bg-gray-950" style={{ marginLeft: SIDEBAR_WIDTH }}>
            <AppTopBar />
            <main className="flex flex-1 flex-col min-h-0 bg-white text-slate-900 dark:bg-gray-950 dark:text-gray-100">
                {children}
            </main>
        </div>
        <AdaptiveTestNotifier />
        <LessonGenerationNotifier />
    </div>
);

export default Sidebar;
