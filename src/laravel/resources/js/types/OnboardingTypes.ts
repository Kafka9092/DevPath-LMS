export type LearningPreference = 'practice_heavy' | 'balanced' | 'theory_heavy';
export type MentorPersona      = 'strict_lead' | 'colleague' | 'soft_mentor';
export type CareerGoal         = 'senior_interview' | 'mid_level_confidence' | 'startup_architecture';

export interface OnboardingAnswers {
    learning_preference: LearningPreference | null;
    domain_interest:     string | null;
    mentor_persona:      MentorPersona | null;
    career_goal:         CareerGoal | null;
}

export const DOMAINS_BY_DIRECTION: Record<string, { value: string; label: string; description: string }[]> = {
    php: [
        { value: 'ecommerce',  label: 'E-commerce',    description: 'Маркетплейсы, интернет-магазины, логистика' },
        { value: 'fintech',    label: 'FinTech',        description: 'Платёжные шлюзы, банкинг, безопасность' },
        { value: 'saas',       label: 'SaaS & CRM',     description: 'Бизнес-платформы, автоматизация процессов' },
        { value: 'highload',   label: 'Highload Web',   description: 'Соцсети, медиа-платформы, WebSocket' },
    ],
    python: [
        { value: 'ai_data',    label: 'AI & Data',      description: 'Нейросети, аналитика данных, ML-пайплайны' },
        { value: 'web',        label: 'Web Backend',    description: 'REST API, микросервисы, Django/FastAPI' },
        { value: 'automation', label: 'Automation',     description: 'Парсеры, боты, автоматизация процессов' },
        { value: 'fintech',    label: 'FinTech',        description: 'Алгоритмы расчётов, риск-модели' },
    ],
    javascript: [
        { value: 'frontend',   label: 'Frontend',       description: 'UI/UX, SPA, React/Vue-приложения' },
        { value: 'fullstack',  label: 'Fullstack',      description: 'Node.js, Express, Fastify, SSR' },
        { value: 'ecommerce',  label: 'E-commerce',     description: 'Маркетплейсы, корзины, платежи' },
        { value: 'realtime',   label: 'Realtime',       description: 'WebSocket, чаты, live-дашборды' },
    ],
    typescript: [
        { value: 'frontend',   label: 'Frontend',       description: 'Типизированные SPA, React + TS' },
        { value: 'fullstack',  label: 'Fullstack',       description: 'NestJS, типизированный API' },
        { value: 'saas',       label: 'SaaS & CRM',     description: 'Бизнес-логика, корпоративные системы' },
        { value: 'fintech',    label: 'FinTech',        description: 'Надёжные финансовые системы' },
    ],
    java: [
        { value: 'enterprise', label: 'Enterprise',     description: 'Spring Boot, микросервисы, корпоративный ПО' },
        { value: 'fintech',    label: 'FinTech',        description: 'Банкинг, платёжные системы' },
        { value: 'android',    label: 'Android',        description: 'Мобильные приложения под Android' },
        { value: 'highload',   label: 'Highload',       description: 'Kafka, высоконагруженные системы' },
    ],
    'c++': [
        { value: 'gamedev',    label: 'GameDev',        description: 'Игровые движки, физика, графика' },
        { value: 'embedded',   label: 'Embedded & IoT', description: 'Прошивки, умные устройства' },
        { value: 'systems',    label: 'Systems',        description: 'Операционные системы, компиляторы' },
        { value: 'fintech',    label: 'HFT / FinTech',  description: 'Высокочастотный трейдинг, latency' },
    ],
    'c#': [
        { value: 'gamedev',    label: 'GameDev (Unity)', description: 'Unity, игровая логика, графика' },
        { value: 'enterprise', label: 'Enterprise .NET', description: '.NET приложения, WPF, ASP.NET' },
        { value: 'saas',       label: 'SaaS',           description: 'Azure, облачные бизнес-решения' },
        { value: 'desktop',    label: 'Desktop Apps',   description: 'Windows-приложения' },
    ],
    go: [
        { value: 'highload',   label: 'Highload & API', description: 'Микросервисы, gRPC, REST' },
        { value: 'devops',     label: 'DevOps Tools',   description: 'CLI-утилиты, инфраструктурные сервисы' },
        { value: 'fintech',    label: 'FinTech',        description: 'Высоконагруженные финансовые системы' },
        { value: 'ecommerce',  label: 'E-commerce',     description: 'Backend маркетплейсов' },
    ],
    ruby: [
        { value: 'web',        label: 'Web (Rails)',    description: 'Ruby on Rails, RESTful API' },
        { value: 'ecommerce',  label: 'E-commerce',     description: 'Shopify-плагины, маркетплейсы' },
        { value: 'saas',       label: 'SaaS',           description: 'Стартап-MVP, продуктовые компании' },
        { value: 'automation', label: 'Automation',     description: 'Скрипты, DevOps, Rake-задачи' },
    ],
    default: [
        { value: 'web',        label: 'Web Backend',    description: 'REST API, сервисы, CRUD' },
        { value: 'ecommerce',  label: 'E-commerce',     description: 'Маркетплейсы, платёжные системы' },
        { value: 'fintech',    label: 'FinTech',        description: 'Финансовые приложения' },
        { value: 'saas',       label: 'SaaS',           description: 'Бизнес-платформы' },
    ],
};

export function getDomainsForDirection(direction: string) {
    const key = direction.toLowerCase();
    return DOMAINS_BY_DIRECTION[key] ?? DOMAINS_BY_DIRECTION['default'];
}
