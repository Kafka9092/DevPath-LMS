import { getAuthUserId } from "@/lib/authSession";
import { getLessonLearningHref } from "@/lib/lessonWorkspaceStorage";

export const ADAPTIVE_TEST_STORAGE_KEY = "devpath_adaptive_test";


export const ADAPTIVE_TEST_TTL_MS = 60 * 60 * 1000;

export type TestScreen = "question" | "task" | "results";

export interface StoredCodeFeedback {
    summary: string;
    strengths: string[];
    improvements: string[];
    score: number;
}

export interface StoredAdaptiveTest {
    userId?: number;
    sessionId: string;
    direction: string;
    selectedAnswers: number | number[] | null;
    questionId: number | null;
    showFeedback: boolean;
    screen?: TestScreen;
    finishStep?: "task" | "results" | null;
    codeFeedback?: StoredCodeFeedback | null;
    notifiedReady?: boolean;
    /** Ожидаем генерацию первого вопроса (для toast вне /test). */
    generatingFirstQuestion?: boolean;
    /** Закрытые toast-уведомления (не показывать снова для этой сессии). */
    dismissedNotices?: Partial<Record<"generating_first" | "ready_first" | "in_progress" | "results", boolean>>;
    savedAt?: number;
}

export const ADAPTIVE_TEST_STORAGE_EVENT = "devpath-adaptive-test-storage";

function emitStorageChange(): void {
    if (typeof window === "undefined") return;
    window.dispatchEvent(new CustomEvent(ADAPTIVE_TEST_STORAGE_EVENT));
}

function belongsToCurrentUser(data: StoredAdaptiveTest): boolean {
    const userId = getAuthUserId();
    if (!userId || !data.userId) {
        return false;
    }

    return data.userId === userId;
}

function parseStored(raw: string | null): StoredAdaptiveTest | null {
    if (!raw) return null;
    try {
        const data = JSON.parse(raw) as StoredAdaptiveTest;
        if (!data.sessionId || !data.direction) return null;
        return data;
    } catch {
        return null;
    }
}

export function isStoredTestExpired(data: StoredAdaptiveTest): boolean {
    if (!data.savedAt) return false;
    return Date.now() - data.savedAt > ADAPTIVE_TEST_TTL_MS;
}

export function clearStoredTest(): void {
    localStorage.removeItem(ADAPTIVE_TEST_STORAGE_KEY);
    emitStorageChange();
}

export function persistResultsScreen(
    sessionId: string,
    direction: string,
    codeFeedback?: StoredCodeFeedback | null,
): void {
    const prev = loadAnyStoredTest();
    const dismissedNotices = { ...prev?.dismissedNotices };
    delete dismissedNotices.results;
    saveStoredTest({
        sessionId,
        direction,
        selectedAnswers: null,
        questionId: null,
        showFeedback: false,
        screen: "results",
        finishStep: null,
        notifiedReady: true,
        generatingFirstQuestion: false,
        codeFeedback: codeFeedback ?? prev?.codeFeedback ?? null,
        dismissedNotices: Object.keys(dismissedNotices).length > 0 ? dismissedNotices : undefined,
    });
}


export function abandonStoredTest(): void {
    clearStoredTest();
}

export function loadAnyStoredTest(): StoredAdaptiveTest | null {
    try {
        const raw = localStorage.getItem(ADAPTIVE_TEST_STORAGE_KEY);
        if (!raw) return null;

        const data = parseStored(raw);
        if (!data) {
            clearStoredTest();
            return null;
        }

        if (!belongsToCurrentUser(data)) {
            if (getAuthUserId()) {
                clearStoredTest();
            }
            return null;
        }

        if (isStoredTestExpired(data)) {
            clearStoredTest();
            return null;
        }

        return data;
    } catch {
        return null;
    }
}

export function buildTestUrl(sessionId: string, direction: string): string {
    return `/test?direction=${encodeURIComponent(direction.toLowerCase())}&session=${encodeURIComponent(sessionId)}`;
}

export function buildFreshTestUrl(direction: string): string {
    return `/test?direction=${encodeURIComponent(direction.toLowerCase())}&fresh=1`;
}


export function getLearningHref(): string {
    return getLessonLearningHref();
}

export function isLearningPath(pathname: string): boolean {
    return pathname === "/main"
        || pathname === "/"
        || pathname.startsWith("/test")
        || pathname.startsWith("/workspace")
        || pathname.startsWith("/create-course")
        || pathname.startsWith("/plan-settings")
        || pathname.startsWith("/senior-program")
        || pathname.startsWith("/senior/")
        || pathname.startsWith("/onboarding");
}

export function loadStoredTest(direction: string): StoredAdaptiveTest | null {
    const data = loadAnyStoredTest();
    if (!data) return null;
    if (data.direction?.toLowerCase() !== direction.toLowerCase()) return null;
    return data;
}

function comparableTest(data: StoredAdaptiveTest | Omit<StoredAdaptiveTest, "savedAt">): string {
    const { savedAt: _savedAt, ...rest } = data as StoredAdaptiveTest;
    return JSON.stringify(rest);
}

export function saveStoredTest(data: Omit<StoredAdaptiveTest, "savedAt">): void {
    const userId = getAuthUserId();
    if (!userId) {
        return;
    }

    const payload = { ...data, userId };
    const prev = parseStored(localStorage.getItem(ADAPTIVE_TEST_STORAGE_KEY));
    if (prev && prev.userId === userId && comparableTest(prev) === comparableTest(payload)) {
        return;
    }

    localStorage.setItem(
        ADAPTIVE_TEST_STORAGE_KEY,
        JSON.stringify({ ...payload, savedAt: Date.now() }),
    );
    emitStorageChange();
}

export function patchStoredTest(patch: Partial<StoredAdaptiveTest>): void {
    try {
        const prev = loadAnyStoredTest();
        if (!prev) return;
        const next = { ...prev, ...patch };
        if (comparableTest(prev) === comparableTest(next)) {
            return;
        }
        localStorage.setItem(
            ADAPTIVE_TEST_STORAGE_KEY,
            JSON.stringify({ ...next, savedAt: Date.now() }),
        );
        emitStorageChange();
    } catch {
        /* ignore */
    }
}

export function markAwaitingFirstQuestion(): void {
    patchStoredTest({ generatingFirstQuestion: true, notifiedReady: false });
}

export function markFirstQuestionReadySeen(): void {
    patchStoredTest({ generatingFirstQuestion: false, notifiedReady: true });
}

export type TestBannerKind = "generating_first" | "ready_first" | "in_progress" | "results";

export function dismissTestBanner(kind: TestBannerKind): void {
    const prev = loadAnyStoredTest();
    patchStoredTest({
        dismissedNotices: {
            ...prev?.dismissedNotices,
            [kind]: true,
        },
        ...(kind === "ready_first"
            ? { generatingFirstQuestion: false, notifiedReady: true }
            : {}),
    });
}

export function isTestBannerDismissed(
    stored: StoredAdaptiveTest,
    kind: TestBannerKind,
): boolean {
    return Boolean(stored.dismissedNotices?.[kind]);
}

/** Нижняя плашка «Продолжить тест» на /main — только для незавершённой сессии. */
export function shouldShowMainTestContinueBar(
    stored: StoredAdaptiveTest,
    data: {
        error?: boolean;
        status?: string;
        is_finished?: boolean;
        generating_task?: boolean;
    },
): boolean {
    if (stored.screen === "results") {
        return false;
    }

    if (data.error && data.status === "missing") {
        return false;
    }

    const status = data.status ?? "";

    if (status === "generating_start" || status === "generating_next" || status === "generating_task") {
        return true;
    }

    if (data.is_finished) {
        if (data.generating_task || status === "generating_task") {
            return true;
        }
        if (stored.screen === "task") {
            return true;
        }
        return false;
    }

    return true;
}

export function getStoredSessionId(): string | null {
    return loadAnyStoredTest()?.sessionId ?? null;
}
