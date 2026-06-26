export const COURSE_CARD_CONTENT_WIDTH_CLASS = "w-[58%] max-w-[58%] shrink-0";
export const COURSE_CARD_ART_WIDTH_CLASS = "w-[46%]";

/** Обёртка ячейки в grid (высота ряда = у самой высокой карточки). */
export const COURSE_GRID_CELL_CLASS = "h-full min-h-0";

/** Карточка в сетке: тень снаружи, без overflow-hidden (иначе box-shadow не виден). */
export const COURSE_CARD_ARTICLE_CLASS =
    "group relative flex h-full w-full min-h-[248px] rounded-2xl bg-white dark:bg-gray-900";

/** CSS-класс тени обычной карточки (см. app.css) */
export const COURSE_CARD_ELEVATION_CLASS = "course-card-elevated";

/** CSS-класс тени карточки Senior */
export const SENIOR_CARD_ELEVATION_CLASS = "course-card-elevated-senior";

/** Фиолетовая обводка карточки программы Senior */
export const SENIOR_CARD_BORDER_CLASS =
    "border-2 border-violet-400 dark:border-violet-500";

/** Внутренняя обрезка скруглений и watermark */
export const COURSE_CARD_CLIP_CLASS =
    "relative flex h-full w-full min-h-[248px] overflow-hidden rounded-2xl";

/** Оболочка контента карточки курса (совпадает с CourseCard / SeniorProgramCard). */
export const COURSE_CARD_SHELL_CLASS =
    "relative z-10 flex h-full min-h-[248px] w-full flex-col p-5 pr-3";

/** Высота блока «Прогресс» / описания в свёрнутой карточке. */
export const COURSE_CARD_MIDDLE_MIN_H_CLASS = "mb-4 min-h-[52px]";

/** Подвал: основная ссылка + ряд как у «Настройки / Удалить». */
export const COURSE_CARD_FOOTER_CLASS = "mt-auto space-y-3 border-t pt-4";

/** Заглушка под второй ряд кнопок в обычной карточке. */
export const COURSE_CARD_FOOTER_ACTIONS_PLACEHOLDER_CLASS = "min-h-[36px]";
