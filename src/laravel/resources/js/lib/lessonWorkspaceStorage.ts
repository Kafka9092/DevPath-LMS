import { getAuthUserId } from '@/lib/authSession';

/** Состояние workspace — переживает переход в другие разделы (localStorage). */

export const LESSON_WORKSPACE_STORAGE_KEY = "devpath_lesson_workspace";
export const LESSON_WORKSPACE_STORAGE_EVENT = "devpath-lesson-workspace-storage";
export const LESSON_WORKSPACE_TTL_MS = 24 * 60 * 60 * 1000;

export interface StoredLessonWorkspace {
    userId?: number;
    courseId: number;
    subtopicId: number;
    subtopicTitle: string;
    themeTitle?: string;
    /** Идёт генерация контента урока (Kafka). */
    generating: boolean;
    jobId?: string | null;
    /** Урок сгенерирован, но пользователь ещё не открыл слайды. */
    lessonReady?: boolean;
    /** Уведомление «урок готов» уже показано. */
    notifiedReady?: boolean;
    /** Есть незавершённый урок (теория/практика). */
    inProgress?: boolean;
    /** Закрытые toast-уведомления для текущего урока. */
    dismissedNotices?: Partial<Record<LessonNoticeKind, boolean>>;
    savedAt: number;
}

export type LessonNoticeKind = "generating" | "ready" | "in_progress";

function emitChange(): void {
    if (typeof window === "undefined") return;
    window.dispatchEvent(new CustomEvent(LESSON_WORKSPACE_STORAGE_EVENT));
}

function belongsToCurrentUser(data: StoredLessonWorkspace): boolean {
    const userId = getAuthUserId();
    if (!userId) {
        return false;
    }

    if (!data.userId) {
        return false;
    }

    return data.userId === userId;
}

function parseStored(raw: string | null): StoredLessonWorkspace | null {
    if (!raw) return null;
    try {
        const data = JSON.parse(raw) as StoredLessonWorkspace;
        if (!data?.courseId || !data?.subtopicId) return null;
        if (Date.now() - (data.savedAt ?? 0) > LESSON_WORKSPACE_TTL_MS) return null;
        return data;
    } catch {
        return null;
    }
}

function parse(raw: string | null): StoredLessonWorkspace | null {
    const data = parseStored(raw);
    if (!data) return null;
    if (!belongsToCurrentUser(data)) return null;
    return data;
}

export function loadLessonWorkspace(): StoredLessonWorkspace | null {
    if (typeof window === "undefined") return null;

    const raw = localStorage.getItem(LESSON_WORKSPACE_STORAGE_KEY);
    const data = parse(raw);

    if (raw && !data) {
        const stored = parseStored(raw);
        const userId = getAuthUserId();
        if (userId && stored && (!stored.userId || stored.userId !== userId)) {
            clearLessonWorkspace();
        }
    }

    return data;
}

function comparableWorkspace(data: StoredLessonWorkspace | Omit<StoredLessonWorkspace, "savedAt">): string {
    const { savedAt: _savedAt, ...rest } = data as StoredLessonWorkspace;
    return JSON.stringify(rest);
}

export function saveLessonWorkspace(data: Omit<StoredLessonWorkspace, "savedAt">): void {
    const userId = getAuthUserId();
    if (!userId) {
        return;
    }

    const payload = { ...data, userId };
    const prev = loadLessonWorkspace();
    if (prev && comparableWorkspace(prev) === comparableWorkspace(payload)) {
        return;
    }

    localStorage.setItem(
        LESSON_WORKSPACE_STORAGE_KEY,
        JSON.stringify({ ...payload, savedAt: Date.now() }),
    );
    emitChange();
}

export function patchLessonWorkspace(patch: Partial<StoredLessonWorkspace>): void {
    const prev = loadLessonWorkspace();
    if (!prev) return;
    saveLessonWorkspace({ ...prev, ...patch });
}

export function isLessonNoticeDismissed(
    stored: StoredLessonWorkspace,
    kind: LessonNoticeKind,
): boolean {
    return Boolean(stored.dismissedNotices?.[kind]);
}

export function dismissLessonNotice(kind: LessonNoticeKind): void {
    const prev = loadLessonWorkspace();
    if (!prev) return;

    const patch: Partial<StoredLessonWorkspace> = {
        dismissedNotices: { ...prev.dismissedNotices, [kind]: true },
    };

    if (kind === "ready") {
        patch.notifiedReady = true;
        patch.lessonReady = false;
    }

    patchLessonWorkspace(patch);
}

export function clearLessonWorkspace(): void {
    localStorage.removeItem(LESSON_WORKSPACE_STORAGE_KEY);
    emitChange();
}

/** Сброс localStorage урока при удалении курса или устаревшей сессии. */
export function clearLessonWorkspaceForCourse(courseId: number): void {
    const stored = loadLessonWorkspace();
    if (stored?.courseId === courseId) {
        clearLessonWorkspace();
    }
}

export function rememberWorkspaceContext(
    courseId: number,
    subtopicId: number,
    subtopicTitle: string,
    themeTitle?: string,
): void {
    const prev = loadLessonWorkspace();
    const sameLesson = prev?.courseId === courseId && prev?.subtopicId === subtopicId;

    saveLessonWorkspace({
        courseId,
        subtopicId,
        subtopicTitle,
        themeTitle: themeTitle ?? prev?.themeTitle,
        generating: sameLesson ? (prev?.generating ?? false) : false,
        jobId: sameLesson ? prev?.jobId : null,
        lessonReady: sameLesson ? prev?.lessonReady : false,
        notifiedReady: sameLesson ? prev?.notifiedReady : false,
        inProgress: sameLesson ? prev?.inProgress : false,
        dismissedNotices: sameLesson ? prev?.dismissedNotices : undefined,
    });
}

export function workspaceHref(courseId: number): string {
    return `/workspace/${courseId}`;
}

/** Куда вести пункт «Обучение» в сайдбаре. */
export function getLessonLearningHref(): string {
    const stored = loadLessonWorkspace();
    if (!stored) return "/main";
    if (stored.generating || stored.lessonReady || stored.inProgress) {
        return workspaceHref(stored.courseId);
    }
    return "/main";
}

export function isOnWorkspacePath(pathname: string, courseId?: number): boolean {
    if (!pathname.startsWith("/workspace")) return false;
    if (courseId == null) return true;
    return pathname === workspaceHref(courseId) || pathname.startsWith(`${workspaceHref(courseId)}`);
}
