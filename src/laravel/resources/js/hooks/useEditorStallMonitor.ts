import { useCallback, useEffect, useRef } from "react";
import { hasMeaningfulCode, normalizeForCompare } from "../lib/meaningfulCode";

const STALL_MS = 6 * 60 * 1000;
const ACTIVE_GRACE_MS = 45_000;
const CHECK_INTERVAL_MS = 30_000;

export type StallKind = "stall_empty_screen" | "stall_rewrite_cycle" | "stall_idle_pause";

interface Options {
    enabled: boolean;
    code: string;
    starterCode?: string;
    submitLoading: boolean;
    mentorPromptPending: boolean;
    userDeclinedHelp: boolean;
    onStall: (kind: StallKind) => void;
    onActivity?: () => void;
}

export function useEditorStallMonitor({
    enabled,
    code,
    starterCode,
    submitLoading,
    mentorPromptPending,
    userDeclinedHelp,
    onStall,
    onActivity,
}: Options) {
    const lastEditAt = useRef(Date.now());
    const taskStartedAt = useRef(Date.now());
    const rewriteCycles = useRef(0);
    const lastNonEmptySnapshot = useRef("");
    const firedStall = useRef(false);

    const markEdit = useCallback((nextCode: string) => {
        const now = Date.now();
        lastEditAt.current = now;
        onActivity?.();

        const had = hasMeaningfulCode(lastNonEmptySnapshot.current, starterCode);
        const has = hasMeaningfulCode(nextCode, starterCode);

        if (had && !has && lastNonEmptySnapshot.current.length > 40) {
            rewriteCycles.current += 1;
        }
        if (has) {
            lastNonEmptySnapshot.current = nextCode;
        }

        if (normalizeForCompare(nextCode) !== normalizeForCompare(code)) {
            firedStall.current = false;
        }
    }, [code, starterCode, onActivity]);

    useEffect(() => {
        if (!enabled) return;
        taskStartedAt.current = Date.now();
        rewriteCycles.current = 0;
        firedStall.current = false;
        lastNonEmptySnapshot.current = "";
    }, [enabled, starterCode]);

    useEffect(() => {
        if (!enabled || userDeclinedHelp || mentorPromptPending) return;

        const tick = () => {
            if (submitLoading) return;
            if (firedStall.current) return;

            const now = Date.now();
            if (now - lastEditAt.current < ACTIVE_GRACE_MS) return;

            const meaningful = hasMeaningfulCode(code, starterCode);
            const idleMs = now - lastEditAt.current;
            const sinceTaskMs = now - taskStartedAt.current;

            if (rewriteCycles.current >= 2 && idleMs >= 60_000) {
                firedStall.current = true;
                onStall("stall_rewrite_cycle");
                return;
            }

            if (!meaningful && sinceTaskMs >= STALL_MS) {
                firedStall.current = true;
                onStall("stall_empty_screen");
                return;
            }

            if (meaningful && idleMs >= STALL_MS) {
                firedStall.current = true;
                onStall("stall_idle_pause");
            }
        };

        const id = window.setInterval(tick, CHECK_INTERVAL_MS);
        return () => window.clearInterval(id);
    }, [
        enabled,
        code,
        starterCode,
        submitLoading,
        mentorPromptPending,
        userDeclinedHelp,
        onStall,
    ]);

    return { markEdit };
}
