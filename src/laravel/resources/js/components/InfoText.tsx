import React from 'react';
import { SurfaceCard } from '@/components/ui/SurfaceCard';

interface InfoTextProps {
    direction: string;
}

export const InfoText: React.FC<InfoTextProps> = ({ direction }) => {
    return (
        <SurfaceCard className="mb-6" innerClassName="border-2 border-violet-400 p-5 dark:border-violet-600">
            <p className="text-sm leading-relaxed text-slate-700 dark:text-gray-300">
                Вы выбрали{' '}
                <strong className="text-violet-700 dark:text-violet-400">{direction}</strong>.
                Дальше можно пройти тест — мы определим уровень и предложим план,
                или собрать программу обучения с нуля без теста
            </p>

            <div className="mt-4 rounded-lg border border-violet-200/80 bg-violet-50/60 px-4 py-3 dark:border-violet-800 dark:bg-violet-950/40">
                <p className="text-sm font-semibold text-slate-800 dark:text-gray-100">Пройти тест</p>
                <ul className="mt-2 space-y-1.5 text-sm leading-relaxed text-slate-600 dark:text-gray-400">
                    <li>Не более 25 вопросов по теории и 1 практическая задача в конце</li>
                    <li>
                        Обычно уходит <strong className="font-medium text-slate-700 dark:text-gray-300">25–40 минут</strong>
                        {' '}— тест может завершиться раньше, если уровень уже ясен
                    </li>
                </ul>
            </div>
        </SurfaceCard>
    );
};
