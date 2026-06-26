export const SENIOR_PROGRAM_STORAGE_KEY = "devpath_senior_program";

export type SeniorModeId = "learning" | "interview";

export interface SeniorProgramState {
    direction: string;
    triedModes: SeniorModeId[];
    lastMode?: SeniorModeId | null;
    savedAt?: number;
}

export function loadSeniorProgram(): SeniorProgramState | null {
    try {
        const raw = localStorage.getItem(SENIOR_PROGRAM_STORAGE_KEY);
        if (!raw) return null;
        return JSON.parse(raw) as SeniorProgramState;
    } catch {
        return null;
    }
}

export function saveSeniorProgram(state: Omit<SeniorProgramState, "savedAt">): void {
    localStorage.setItem(
        SENIOR_PROGRAM_STORAGE_KEY,
        JSON.stringify({ ...state, savedAt: Date.now() }),
    );
}

export function markSeniorModeTried(direction: string, mode: SeniorModeId): void {
    const prev = loadSeniorProgram();
    const tried = new Set<SeniorModeId>(prev?.direction === direction ? prev.triedModes : []);
    tried.add(mode);
    saveSeniorProgram({
        direction,
        triedModes: [...tried],
        lastMode: mode,
    });
}

export function buildSeniorHubUrl(direction: string): string {
    return `/senior-program?direction=${encodeURIComponent(direction.toUpperCase())}`;
}

export function buildPlanSettingsUrl(direction: string): string {
    return `/plan-settings?direction=${encodeURIComponent(direction.toUpperCase())}`;
}

export function buildInterviewUrl(direction: string): string {
    return `/ai-hr?from_senior=1&direction=${encodeURIComponent(direction.toUpperCase())}&level=Senior`;
}
