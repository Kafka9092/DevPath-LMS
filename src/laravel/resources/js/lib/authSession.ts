const LESSON_WORKSPACE_STORAGE_KEY = 'devpath_lesson_workspace';
const ADAPTIVE_TEST_STORAGE_KEY = 'devpath_adaptive_test';
const LESSON_WORKSPACE_STORAGE_EVENT = 'devpath-lesson-workspace-storage';
const ADAPTIVE_TEST_STORAGE_EVENT = 'devpath-adaptive-test-storage';

let authUserId: number | null = null;

export function getAuthUserId(): number | null {
    return authUserId;
}

function emitStorageEvents(): void {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent(LESSON_WORKSPACE_STORAGE_EVENT));
    window.dispatchEvent(new CustomEvent(ADAPTIVE_TEST_STORAGE_EVENT));
}

export function clearUserScopedClientState(): void {
    if (typeof window === 'undefined') return;
    localStorage.removeItem(LESSON_WORKSPACE_STORAGE_KEY);
    localStorage.removeItem(ADAPTIVE_TEST_STORAGE_KEY);
    emitStorageEvents();
}

export function syncAuthUserId(nextUserId: number | null): void {
    if (authUserId !== null && nextUserId !== null && authUserId !== nextUserId) {
        clearUserScopedClientState();
    }

    if (authUserId !== null && nextUserId === null) {
        clearUserScopedClientState();
    }

    authUserId = nextUserId;
}
