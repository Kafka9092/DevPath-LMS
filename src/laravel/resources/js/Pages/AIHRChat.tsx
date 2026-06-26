import React, { useMemo } from "react";
import { AppLayout } from "@/components/Sidebar";
import { Breadcrumbs } from "@/components/Breadcrumbs";
import AIHRChatPanel from "@/components/AIHRChat";
import { SeniorModeNav } from "@/components/senior/SeniorModeNav";

export default function AIHRChat() {
    const fromSenior = useMemo(() => {
        if (typeof window === "undefined") return false;
        return new URLSearchParams(window.location.search).get("from_senior") === "1";
    }, []);

    const direction = useMemo(() => {
        if (typeof window === "undefined") return "PHP";
        return new URLSearchParams(window.location.search).get("direction") ?? "PHP";
    }, []);

    return (
        <AppLayout>
            <div className="flex h-[calc(100vh-88px)] flex-col overflow-hidden">
                <div className="px-6 lg:px-10 shrink-0">
                    <Breadcrumbs
                        crumbs={[
                            { label: "DevPath", path: "/main" },
                            { label: "Собеседование", path: "/ai-hr" },
                        ]}
                    />
                </div>
                {fromSenior && (
                    <div className="shrink-0 max-w-5xl mx-auto w-full px-[100px] pt-2">
                        <SeniorModeNav direction={direction} current="Собеседование" />
                    </div>
                )}
                <div className="flex-1 min-h-0 overflow-hidden">
                    <AIHRChatPanel />
                </div>
            </div>
        </AppLayout>
    );
}
