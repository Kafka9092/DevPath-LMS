import axios from "axios";
import { loadAnyStoredTest } from "@/lib/adaptiveTestStorage";

export const SENIOR_PREVIEW_EVENT = "devpath-senior-preview";

export interface SeniorPreviewResult {
    overall_level: string;
    description: string;
    recommendation: string;
    competencies: Record<string, number>;
    weak_topics: string[];
    strong_topics: string[];
    code_feedback?: string;
}

export function resolveTestDirection(): string {
    if (typeof window !== "undefined") {
        const params = new URLSearchParams(window.location.search);
        const fromUrl = params.get("direction");
        if (fromUrl) return fromUrl.toUpperCase();
    }
    const stored = loadAnyStoredTest();
    if (stored?.direction) return stored.direction.toUpperCase();
    return "PHP";
}

const PENDING_KEY = "devpath_apply_senior_preview";

export function dispatchSeniorPreview(result: SeniorPreviewResult, direction: string): void {
    if (typeof window === "undefined") return;
    window.dispatchEvent(
        new CustomEvent(SENIOR_PREVIEW_EVENT, {
            detail: { result, direction },
        }),
    );
}

export function isOnTestPage(): boolean {
    return typeof window !== "undefined" && window.location.pathname.startsWith("/test");
}

export function markSeniorPreviewPending(): void {
    sessionStorage.setItem(PENDING_KEY, "1");
}

export function consumeSeniorPreviewPending(): boolean {
    if (sessionStorage.getItem(PENDING_KEY) !== "1") return false;
    sessionStorage.removeItem(PENDING_KEY);
    return true;
}

export async function activateSeniorPreview(): Promise<boolean> {
    const direction = resolveTestDirection();
    try {
        const res = await axios.post("/test/force-senior-preview", { direction });
        if (res.data.error) return false;
        dispatchSeniorPreview(res.data.result as SeniorPreviewResult, res.data.direction ?? direction);
        return true;
    } catch {
        return false;
    }
}

export async function activateSeniorPreviewFromSidebar(
    navigate: (url: string) => void,
): Promise<boolean> {
    const direction = resolveTestDirection();
    const testUrl = `/test?direction=${encodeURIComponent(direction.toLowerCase())}`;

    if (!isOnTestPage()) {
        markSeniorPreviewPending();
        navigate(testUrl);
        return true;
    }

    return activateSeniorPreview();
}
