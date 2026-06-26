<?php

namespace App\Service;

class LanguageCompetencePromptBuilder
{
    public function normalizeDirectionLabel(string $dir): string
    {
        return match (strtolower(trim($dir))) {
            'c++', 'cpp' => 'C++',
            'c#', 'csharp' => 'C#',
            'javascript', 'js' => 'JavaScript',
            'typescript', 'ts' => 'TypeScript',
            'php' => 'PHP',
            'python', 'py' => 'Python',
            'java' => 'Java',
            'go', 'golang' => 'Go',
            'ruby', 'rb' => 'Ruby',
            default => ucfirst(strtolower(trim($dir))),
        };
    }

    public function inferCategoryFromTopic(string $topic): string
    {
        $t = mb_strtolower(trim($topic));

        if ($this->isDatabaseTopic($t)) {
            return 'database';
        }

        if (str_contains($t, 'http') || str_contains($t, 'api') || str_contains($t, 'rest') || str_contains($t, 'grpc')) {
            return 'api';
        }

        if (str_contains($t, 'тест') || str_contains($t, 'test') || str_contains($t, 'jest') || str_contains($t, 'phpunit')) {
            return 'testing';
        }

        if (str_contains($t, 'безопас') || str_contains($t, 'security') || str_contains($t, 'xss') || str_contains($t, 'jwt')) {
            return 'security';
        }

        if (str_contains($t, 'git')) {
            return 'tools';
        }

        if (
            str_contains($t, 'класс')
            || str_contains($t, 'наслед')
            || str_contains($t, 'интерфейс')
            || str_contains($t, 'полиморф')
            || str_contains($t, 'абстракт')
            || str_contains($t, 'oop')
            || str_contains($t, 'ооп')
        ) {
            return 'oop';
        }

        if (str_contains($t, 'многопот') || str_contains($t, 'async') || str_contains($t, 'конкур') || str_contains($t, 'goroutine')) {
            return 'concurrency';
        }

        return 'general';
    }

    /**
     * @param  'test'|'lesson'  $context
     */
    public function buildRules(string $dir, string $comp, string $cat, string $context = 'test'): string
    {
        $lang      = $this->normalizeDirectionLabel($dir);
        $compLower = mb_strtolower($comp);
        $catLower  = strtolower($cat);

        if ($context === 'lesson') {
            $lines = [
                'КОНТЕКСТ ЯЗЫКА (обязательно):',
                "Курс и урок ведутся по языку «{$lang}».",
                "Теория, примеры в playground, практическая задача, test_cases и ответы ментора — только в экосистеме «{$lang}».",
                "Запрещено: примеры и API других языков; абстрактная теория без кода «{$lang}»; перенос синтаксиса PHP/Java в Go и наоборот.",
            ];
        } else {
            $lines = [
                'КОНТЕКСТ ЯЗЫКА (обязательно):',
                "Тест определяет уровень разработчика по языку «{$lang}».",
                "Каждый вопрос и каждый вариант ответа должны проверять знания в экосистеме «{$lang}».",
                "Запрещено: вопросы про другой язык; API/синтаксис других языков как правильные ответы; абстрактная теория без привязки к «{$lang}».",
            ];
        }

        $isDatabase = $catLower === 'database' || $this->isDatabaseTopic($compLower);

        if ($isDatabase) {
            $lines = array_merge($lines, $this->databaseRules($lang, $comp, $context));
        }

        if ($catLower === 'api' || str_contains($compLower, 'http')) {
            $lines[] = '';
            $lines[] = "КОМПЕТЕНЦИЯ «{$comp}» + язык «{$lang}»:";
            $lines[] = $context === 'lesson'
                ? "Объясняй HTTP-клиенты, серверные фреймворки, middleware, сериализацию, REST/gRPC на «{$lang}» с примерами кода."
                : "Спрашивай про HTTP-клиенты, серверные фреймворки, middleware, сериализацию, REST/gRPC в экосистеме «{$lang}», а не абстрактно.";
        }

        if ($catLower === 'testing') {
            $lines[] = '';
            $lines[] = "КОМПЕТЕНЦИЯ «{$comp}» + язык «{$lang}»:";
            $lines[] = "Используй принятые в «{$lang}» фреймворки и практики тестирования (unit/integration, mock/stub, assertions).";
        }

        if ($catLower === 'security') {
            $lines[] = '';
            $lines[] = "КОМПЕТЕНЦИЯ «{$comp}» + язык «{$lang}»:";
            $lines[] = "Фокус на уязвимостях и защите в приложениях на «{$lang}» (инъекции, XSS, CSRF, JWT, работа с секретами).";
        }

        if ($catLower === 'oop') {
            $lines[] = '';
            $lines[] = "ООП в контексте «{$lang}»:";
            $lines[] = 'Учитывай реальную объектную модель языка. Не переноси концепции одного языка в другой без оговорок (например, классическое наследование Java в Go).';
        }

        if ($catLower === 'tools' && str_contains($compLower, 'git')) {
            $lines[] = '';
            $lines[] = "Git в контексте проектов на «{$lang}»:";
            $lines[] = 'Можно использовать workflow, .gitignore для экосистемы языка, pre-commit hooks, CI для типичного стека «' . $lang . '».';
        }

        if ($catLower === 'concurrency' || str_contains($compLower, 'многопот') || str_contains($compLower, 'async')) {
            $lines[] = '';
            $lines[] = "Конкурентность в «{$lang}»:";
            $lines[] = "Используй принятые в «{$lang}» примитивы (goroutines/channels, async/await, threads, GIL и т.д.).";
        }

        return implode("\n", $lines);
    }

    public function buildLessonRulesFromContext(array $ctx): string
    {
        $dir   = (string) ($ctx['direction'] ?? 'PHP');
        $topic = trim((string) ($ctx['subtopic_title'] ?? ''));

        if ($topic === '') {
            return $this->buildRules($dir, 'программа курса', 'general', 'lesson');
        }

        return $this->buildRules(
            $dir,
            $topic,
            $this->inferCategoryFromTopic($topic),
            'lesson',
        );
    }

    public function functionSignatureExample(string $dir): string
    {
        return match (strtolower(trim($dir))) {
            'go', 'golang' => 'func Name(arg1 type, arg2 type) (returnType, error)',
            'python', 'py' => 'def name(arg1: type, arg2: type) -> type',
            'java' => 'public static ReturnType name(Type arg1, Type arg2)',
            'javascript', 'js' => 'function name(arg1, arg2)',
            'typescript', 'ts' => 'function name(arg1: Type, arg2: Type): ReturnType',
            'ruby', 'rb' => 'def name(arg1, arg2)',
            'c++', 'cpp' => 'ReturnType name(Type arg1, Type arg2)',
            'c#', 'csharp' => 'public static ReturnType Name(Type arg1, Type arg2)',
            default => 'function name(type $arg1, type $arg2): type',
        };
    }

    private function isDatabaseTopic(string $text): bool
    {
        return str_contains($text, 'sql')
            || str_contains($text, 'баз')
            || str_contains($text, 'pdo')
            || str_contains($text, 'orm')
            || str_contains($text, 'database')
            || str_contains($text, 'eloquent')
            || str_contains($text, 'миграц');
    }

    /** @return list<string> */
    private function databaseRules(string $lang, string $comp, string $context): array
    {
        $lines = [
            '',
            "КОМПЕТЕНЦИЯ «{$comp}» + язык «{$lang}»:",
        ];

        if ($context === 'lesson') {
            $lines[] = "Материал ОБЯЗАН быть на стыке «{$lang}» и работы с СУБД в коде этого языка.";
            $lines[] = 'Запрещено: урок только про чистый SQL-синтаксис (SELECT/JOIN/WHERE отдельно от языка), диалекты SQL без кода «' . $lang . '».';
            $lines[] = 'Фокус урока (теория + практика):';
        } else {
            $lines[] = "Вопрос ОБЯЗАН быть на стыке «{$lang}» и работы с СУБД в коде этого языка.";
            $lines[] = 'Запрещено: чистый синтаксис SQL (SELECT/JOIN/WHERE как отдельная дисциплина), диалекты SQL без кода «' . $lang . '», общая теория СУБД без драйвера/ORM/кода.';
            $lines[] = 'Фокус (выбери тему под уровень сложности):';
        }

        $lines[] = "- пул соединений (connection pool) и его настройка в «{$lang}»;";
        $lines[] = '- утечки соединений/памяти при работе с БД;';
        $lines[] = "- транзакции и уровни изоляции в коде «{$lang}»;";
        $lines[] = '- ORM/драйвер: N+1, lazy/eager loading, prepared statements, batch-операции;';
        $lines[] = '- безопасность: SQL-injection на уровне драйвера, параметризация, race conditions, конкурентный/async-доступ к БД;';
        $lines[] = $context === 'lesson'
            ? "Примеры кода в playground и starter_code — на «{$lang}» с реальными API (драйвер, ORM, stdlib)."
            : "В вопросе и вариантах используй реальные API «{$lang}» (драйвер, ORM, стандартная библиотека для БД).";

        return $lines;
    }
}
