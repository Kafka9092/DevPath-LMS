import React, { useEffect } from "react";
import { AppLayout } from "@/components/Sidebar";
import { Breadcrumbs } from "@/components/Breadcrumbs";
import { SurfaceCard } from "@/components/ui/SurfaceCard";
import { SeniorModesGrid } from "@/components/senior/SeniorModesGrid";
import { saveSeniorProgram } from "@/lib/seniorProgramStorage";
import { seniorHubBreadcrumbs } from "@/lib/courseFlowBreadcrumbs";

interface SeniorHubProps {
    direction: string;
    realLevel: string;
}

export default function SeniorHub({ direction }: SeniorHubProps) {
    useEffect(() => {
        saveSeniorProgram({ direction, triedModes: [], lastMode: null });
    }, [direction]);

    return (
        <AppLayout>
            <div className="w-full px-6 lg:px-10">
                <Breadcrumbs crumbs={seniorHubBreadcrumbs(direction)} />
            </div>
            <div className="flex flex-1 w-full flex-col items-center justify-center px-6 py-16">
                <div className="w-full max-w-2xl">
                    <p className="text-sm font-medium text-slate-400 uppercase tracking-wider mb-8 text-center">
                        SENIOR · {direction}
                    </p>

                    <div className="text-center mb-10">
                        <h1 className="text-4xl font-extrabold text-slate-800 dark:text-gray-100 leading-tight mb-3">
                            Программа для{" "}
                            <span className="text-violet-600">Senior</span>
                        </h1>
                        <p className="text-base text-slate-500 dark:text-gray-400 max-w-lg mx-auto">
                            Специализированные режимы по результатам теста. Вернуться к выбору можно
                            с главной — карточка «Программа Senior».
                        </p>
                    </div>

                    <SurfaceCard className="mb-6" innerClassName="border-2 border-violet-500 p-5 text-center">
                        <p className="text-base text-slate-700">
                            Ваш уровень по тесту:{" "}
                            <strong className="text-violet-700">Senior</strong>
                        </p>
                    </SurfaceCard>

                    <p className="text-xs font-bold text-violet-600 uppercase tracking-wider text-center mb-4">
                        Выберите режим
                    </p>

                    <SeniorModesGrid direction={direction} />
                </div>
            </div>
        </AppLayout>
    );
}
