import { useMemo, useState } from "react";
import type { ProgressPageProps } from "@/types/progress";
import { MODULE_THEMES, type ModuleId } from "../constants/moduleThemes";
import { buildLanguageOverview, buildModuleKpis, hasModuleData } from "../lib/selectors";

export function useProgressDashboard(props: ProgressPageProps) {
    const [activeModule, setActiveModule] = useState<ModuleId>("learning");
    const theme = MODULE_THEMES[activeModule];

    const kpis = useMemo(
        () => buildModuleKpis(activeModule, props.learning, props.interviews, props.codeReview),
        [activeModule, props.learning, props.interviews, props.codeReview],
    );

    const languageOverview = useMemo(() => buildLanguageOverview(props), [props]);

    const moduleHasData = hasModuleData(activeModule, props);

    return {
        activeModule,
        setActiveModule,
        theme,
        themes: MODULE_THEMES,
        kpis,
        languageOverview,
        moduleHasData,
    };
}
