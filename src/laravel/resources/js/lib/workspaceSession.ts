import { LessonStatus } from "../components/lesson/LessonStudio";
import { ChatBlock, LessonPhase } from "../types/WorkspaceTypes";

export interface WorkspaceSession {
    subtopicId: number;
    themeTitle: string;
    phase: LessonPhase;
    blocks: ChatBlock[];
    nextSubtopic: { id: number; title: string } | null;
    currentCode: string;
}

function storageKey(courseId: number): string {
    return `devpath-workspace-session-${courseId}`;
}

export function loadWorkspaceSession(courseId: number): WorkspaceSession | null {
    try {
        const raw = sessionStorage.getItem(storageKey(courseId));
        if (!raw) return null;
        const data = JSON.parse(raw) as WorkspaceSession;
        if (!data?.subtopicId || !data.phase) return null;
        return data;
    } catch {
        return null;
    }
}

export function saveWorkspaceSession(courseId: number, session: WorkspaceSession): void {
    try {
        sessionStorage.setItem(storageKey(courseId), JSON.stringify(session));
    } catch {
        
    }
}

export function clearWorkspaceSession(courseId: number): void {
    try {
        sessionStorage.removeItem(storageKey(courseId));
    } catch {
    }
}

export const ACTIVE_LESSON_PHASES: LessonPhase[] = [
    "generating",
    "learning",
    "theory_review",
    "task",
    "evaluating",
    "completed",
    "lesson_review",
];

export function isActiveLessonPhase(phase: LessonPhase): boolean {
    return ACTIVE_LESSON_PHASES.includes(phase);
}

/** Не восстанавливать sessionStorage без подтверждения прогресса на сервере. */
export function canRestoreWorkspaceSession(
    status: LessonStatus | null | undefined,
    phase: LessonPhase,
): boolean {
    if (!isActiveLessonPhase(phase)) return false;
    if (!status) return false;
    if (status.status === "generating_content") return true;
    return Boolean(status.generated);
}
