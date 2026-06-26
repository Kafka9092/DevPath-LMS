export interface BreadcrumbCrumb {
    label: string;
    path: string;
}

const base: BreadcrumbCrumb[] = [
    { label: "DevPath", path: "/main" },
    { label: "Мои курсы", path: "/main" },
];

function languageCrumb(direction: string): BreadcrumbCrumb {
    return { label: `Язык · ${direction}`, path: "/create-course" };
}

function planCrumb(direction: string): BreadcrumbCrumb {
    return {
        label: `План · ${direction}`,
        path: `/plan-settings?direction=${encodeURIComponent(direction)}`,
    };
}

export function createCourseBreadcrumbs(): BreadcrumbCrumb[] {
    return [...base, { label: "Создать курс", path: "/create-course" }];
}

export function testBreadcrumbs(direction: string): BreadcrumbCrumb[] {
    return [
        ...base,
        languageCrumb(direction),
        {
            label: `Тест · ${direction}`,
            path: `/test?direction=${encodeURIComponent(direction.toLowerCase())}`,
        },
    ];
}

export function planSettingsBreadcrumbs(direction: string): BreadcrumbCrumb[] {
    return [...base, languageCrumb(direction), planCrumb(direction)];
}

export function onboardingBreadcrumbs(direction: string): BreadcrumbCrumb[] {
    return [...base, languageCrumb(direction), planCrumb(direction), { label: "Персонализация", path: "/onboarding" }];
}

export function seniorHubBreadcrumbs(direction: string): BreadcrumbCrumb[] {
    return [
        ...base,
        languageCrumb(direction),
        {
            label: `Senior · ${direction}`,
            path: `/senior-program?direction=${encodeURIComponent(direction)}`,
        },
    ];
}
