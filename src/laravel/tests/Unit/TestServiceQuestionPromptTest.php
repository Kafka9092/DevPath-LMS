<?php

namespace Tests\Unit;

use App\Service\TestService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TestServiceQuestionPromptTest extends TestCase
{
    public function test_database_competence_prompt_requires_language_and_sql_intersection(): void
    {
        $prompt = $this->buildPrompt(
            dir: 'go',
            comp: 'Базы данных (SQL)',
            cat: 'database',
        );

        $this->assertStringContainsString('Язык программирования теста: Go', $prompt);
        $this->assertStringContainsString('на стыке «Go» и работы с СУБД', $prompt);
        $this->assertStringContainsString('Запрещено: чистый синтаксис SQL', $prompt);
        $this->assertStringContainsString('connection pool', $prompt);
        $this->assertStringContainsString('N+1', $prompt);
    }

    public function test_syntax_competence_prompt_still_requires_language_context(): void
    {
        $prompt = $this->buildPrompt(
            dir: 'php',
            comp: 'Синтаксис',
            cat: 'syntax',
        );

        $this->assertStringContainsString('Язык программирования теста: PHP', $prompt);
        $this->assertStringContainsString('экосистеме «PHP»', $prompt);
        $this->assertStringNotContainsString('чистый синтаксис SQL', $prompt);
    }

    private function buildPrompt(string $dir, string $comp, string $cat): string
    {
        $service = (new \ReflectionClass(TestService::class))->newInstanceWithoutConstructor();
        $method  = new ReflectionMethod(TestService::class, 'buildQuestionPrompt');

        return $method->invoke($service, [], $dir, $comp, $cat, 'middle', 'single');
    }
}
