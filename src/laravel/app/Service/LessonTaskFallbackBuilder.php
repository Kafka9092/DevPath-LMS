<?php

namespace App\Service;

/** Готовые структурированные задачи, если LLM не вернул валидный JSON. */
class LessonTaskFallbackBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(array $ctx, string $subtopicTitle): array
    {
        $lower  = mb_strtolower($subtopicTitle);
        $domain = (string) ($ctx['domain_interest'] ?? 'general');

        if (str_contains($lower, 'переменн') || str_contains($lower, 'тип')) {
            return $this->variablesTask($domain);
        }

        if (str_contains($lower, 'услов') || str_contains($lower, 'if')) {
            return $this->conditionsTask($domain);
        }

        if (str_contains($lower, 'цикл') || str_contains($lower, 'loop')) {
            return $this->loopsTask($domain);
        }

        if (str_contains($lower, 'массив') || str_contains($lower, 'array')) {
            return $this->arraysTask($domain);
        }

        return $this->genericTask($subtopicTitle, $domain);
    }

    /** @return array<string, mixed> */
    private function variablesTask(string $domain): array
    {
        return match ($domain) {
            'fintech' => [
                'title'              => 'Сумма с комиссией',
                'action'             => 'Напишите функцию applyFee(int $amountCents, int $feeBps): int',
                'function_signature' => 'function applyFee(int $amountCents, int $feeBps): int',
                'description'        => '',
                'constraints'        => [
                    'Аргументы: $amountCents (int ≥ 0), $feeBps (int ≥ 0) — комиссия в базисных пунктах (1 bps = 0.01%)',
                    'Возвращаемое значение: int — итоговая сумма в копейках после добавления комиссии',
                    'Округление вниз (floor). Если $amountCents = 0 — вернуть 0',
                ],
                'test_cases' => [
                    ['label' => 'Без комиссии', 'input' => '1000 0', 'output' => '1000'],
                    ['label' => 'Комиссия 2.5%', 'input' => '1000 250', 'output' => '1025'],
                    ['label' => 'Нулевая сумма', 'input' => '0 100', 'output' => '0'],
                ],
                'starter_code' => <<<'PHP'
function applyFee(int $amountCents, int $feeBps): int
{
    // TODO
}
PHP,
            ],
            'ecommerce' => [
                'title'              => 'Итог по позиции',
                'action'             => 'Напишите функцию lineTotal(int $priceCents, int $qty): int',
                'function_signature' => 'function lineTotal(int $priceCents, int $qty): int',
                'description'        => '',
                'constraints'        => [
                    'Аргументы: $priceCents (int ≥ 0), $qty (int ≥ 0)',
                    'Возвращаемое значение: int — стоимость позиции в копейках',
                    'Если $qty = 0 — вернуть 0',
                ],
                'test_cases' => [
                    ['label' => 'Одна единица', 'input' => '199 1', 'output' => '199'],
                    ['label' => 'Несколько', 'input' => '250 4', 'output' => '1000'],
                    ['label' => 'Нулевое количество', 'input' => '500 0', 'output' => '0'],
                ],
                'starter_code' => <<<'PHP'
function lineTotal(int $priceCents, int $qty): int
{
    // TODO
}
PHP,
            ],
            default => [
                'title'              => 'Компактный формат числа',
                'action'             => 'Напишите функцию formatCompactCount(int $count): string',
                'function_signature' => 'function formatCompactCount(int $count): string',
                'description'        => '',
                'constraints'        => [
                    'Аргумент $count: int (может быть 0 или положительным)',
                    'Возвращаемое значение: string',
                    'Если $count < 1000 — вернуть (string) $count. Если ≥ 1000 — формат «X.XK» (один знак после точки)',
                ],
                'test_cases' => [
                    ['label' => 'Меньше тысячи', 'input' => '999', 'output' => '999'],
                    ['label' => 'Тысячи', 'input' => '1200', 'output' => '1.2K'],
                    ['label' => 'Ноль', 'input' => '0', 'output' => '0'],
                ],
                'starter_code' => <<<'PHP'
function formatCompactCount(int $count): string
{
    // TODO
}
PHP,
            ],
        };
    }

    /** @return array<string, mixed> */
    private function conditionsTask(string $domain): array
    {
        return [
            'title'              => 'Проверка лимита',
            'action'             => 'Напишите функцию isWithinLimit(int $value, int $max): bool',
            'function_signature' => 'function isWithinLimit(int $value, int $max): bool',
            'description'        => '',
            'constraints'        => [
                'Аргументы: $value (int), $max (int ≥ 0)',
                'Возвращаемое значение: bool — true, если 0 ≤ $value ≤ $max',
                'Если $value < 0 — вернуть false',
            ],
            'test_cases' => [
                ['label' => 'В пределах', 'input' => '5 10', 'output' => 'true'],
                ['label' => 'Превышение', 'input' => '15 10', 'output' => 'false'],
                ['label' => 'Отрицательное', 'input' => '-1 10', 'output' => 'false'],
            ],
            'starter_code' => <<<'PHP'
function isWithinLimit(int $value, int $max): bool
{
    // TODO
}
PHP,
        ];
    }

    /** @return array<string, mixed> */
    private function loopsTask(string $domain): array
    {
        return [
            'title'              => 'Сумма диапазона',
            'action'             => 'Напишите функцию sumRange(int $from, int $to): int',
            'function_signature' => 'function sumRange(int $from, int $to): int',
            'description'        => '',
            'constraints'        => [
                'Аргументы: $from, $to (int). Суммировать все целые от $from до $to включительно',
                'Если $from > $to — вернуть 0',
                'Возвращаемое значение: int',
            ],
            'test_cases' => [
                ['label' => 'Диапазон 1-3', 'input' => '1 3', 'output' => '6'],
                ['label' => 'Один элемент', 'input' => '5 5', 'output' => '5'],
                ['label' => 'Пустой диапазон', 'input' => '5 2', 'output' => '0'],
            ],
            'starter_code' => <<<'PHP'
function sumRange(int $from, int $to): int
{
    // TODO
}
PHP,
        ];
    }

    /** @return array<string, mixed> */
    private function arraysTask(string $domain): array
    {
        return [
            'title'              => 'Срез массива',
            'action'             => 'Напишите функцию takeFirst(array $items, int $limit): array',
            'function_signature' => 'function takeFirst(array $items, int $limit): array',
            'description'        => '',
            'constraints'        => [
                'Аргументы: $items (array), $limit (int ≥ 0)',
                'Вернуть первые $limit элементов массива (ключи сохранять не нужно — только значения по порядку)',
                'Если $limit ≥ count($items) — вернуть весь массив',
            ],
            'test_cases' => [
                ['label' => 'Два из трёх', 'input' => "a\nb\nc\n2", 'output' => '["a","b"]'],
                ['label' => 'Лимит больше длины', 'input' => "x\ny\n5", 'output' => '["x","y"]'],
                ['label' => 'Нулевой лимит', 'input' => "a\nb\n0", 'output' => '[]'],
            ],
            'starter_code' => <<<'PHP'
function takeFirst(array $items, int $limit): array
{
    // TODO
}
PHP,
        ];
    }

    /** @return array<string, mixed> */
    private function genericTask(string $subtopicTitle, string $domain): array
    {
        return [
            'title'              => mb_substr($subtopicTitle, 0, 60),
            'action'             => 'Напишите функцию solve(int $input): int',
            'function_signature' => 'function solve(int $input): int',
            'description'        => '',
            'constraints'        => [
                'Аргумент $input: int',
                'Возвращаемое значение: int — удвоенное значение $input',
                'Если $input < 0 — вернуть 0',
            ],
            'test_cases' => [
                ['label' => 'Положительное', 'input' => '4', 'output' => '8'],
                ['label' => 'Ноль', 'input' => '0', 'output' => '0'],
                ['label' => 'Отрицательное', 'input' => '-3', 'output' => '0'],
            ],
            'starter_code' => <<<'PHP'
function solve(int $input): int
{
    // TODO
}
PHP,
        ];
    }
}
