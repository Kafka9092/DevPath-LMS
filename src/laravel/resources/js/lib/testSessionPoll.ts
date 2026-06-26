import axios from "axios";

export const GENERATING_STATUSES = new Set([
    "generating_start",
    "generating_next",
    "generating_task",
]);

export interface SessionPollData {
    status?: string;
    error?: boolean;
    is_finished?: boolean;
    generating_task?: boolean;
    practical_task?: { title?: string };
    question?: unknown;
    [key: string]: unknown;
}


export function isSessionReturnable(data: SessionPollData): boolean {
    if (data.error && data.status === "missing") return false;
    if (GENERATING_STATUSES.has(data.status ?? "")) return true;
    if (data.is_finished) {
        if (data.status === "generating_task" || data.generating_task) return true;
        if (data.practical_task?.title) return true;
        return false;
    }
    return true;
}

export function shouldKeepPolling(data: SessionPollData): boolean {
    if (data.error && data.status === "missing") return false;
    if (GENERATING_STATUSES.has(data.status ?? "")) return true;
    if (data.generating_task && !data.practical_task?.title) return true;
    return false;
}

type PollHandle = { cancelled: boolean; inFlight: boolean };

export function stopTestSessionPoll(handleRef: { current: PollHandle | null }): void {
    if (handleRef.current) {
        handleRef.current.cancelled = true;
        handleRef.current = null;
    }
}


export function startTestSessionPoll(
    sessionId: string,
    handleRef: { current: PollHandle | null },
    handlers: {
        onTick: (data: SessionPollData) => void;
        onDone?: (data: SessionPollData) => void;
        onMissing?: () => void;
    },
    initialDelayMs = 2500,
): void {
    stopTestSessionPoll(handleRef);
    const handle: PollHandle = { cancelled: false, inFlight: false };
    handleRef.current = handle;

    let delayMs = initialDelayMs;
    let scheduled = false;

    const schedule = (ms: number) => {
        if (handle.cancelled || scheduled) return;
        scheduled = true;
        window.setTimeout(() => {
            scheduled = false;
            void run();
        }, ms);
    };

    const run = async () => {
        if (handle.cancelled || handle.inFlight) return;

        handle.inFlight = true;
        try {
            const res = await axios.get(`/test/session/${sessionId}`);
            const data = res.data as SessionPollData;

            if (handle.cancelled) return;

            if (data.error && data.status === "missing") {
                handlers.onMissing?.();
                stopTestSessionPoll(handleRef);
                return;
            }

            handlers.onTick(data);

            if (handle.cancelled) return;

            if (shouldKeepPolling(data)) {
                delayMs = Math.min(Math.round(delayMs * 1.2), 8000);
                schedule(delayMs);
                return;
            }

            handlers.onDone?.(data);
            stopTestSessionPoll(handleRef);
        } catch {
            if (!handle.cancelled) {
                delayMs = Math.min(Math.round(delayMs * 1.5), 10000);
                schedule(delayMs);
            }
        } finally {
            handle.inFlight = false;
        }
    };

    schedule(initialDelayMs);
}
