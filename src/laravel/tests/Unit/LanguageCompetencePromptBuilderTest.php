<?php

namespace Tests\Unit;

use App\Service\LanguageCompetencePromptBuilder;
use App\Service\LessonContextBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LanguageCompetencePromptBuilderTest extends TestCase
{
    public function test_lesson_sql_topic_requires_language_and_database_intersection(): void
    {
        $builder = new LanguageCompetencePromptBuilder();

        $rules = $builder->buildLessonRulesFromContext([
            'direction' => 'Go',
            'subtopic_title' => 'Базы данных (SQL)',
        ]);

        $this->assertStringContainsString('урок ведутся по языку «Go»', $rules);
        $this->assertStringContainsString('на стыке «Go» и работы с СУБД', $rules);
        $this->assertStringContainsString('чистый SQL-синтаксис', $rules);
        $this->assertStringContainsString('connection pool', $rules);
    }

    public function test_lesson_context_builder_includes_language_rules_in_mentor_prompt(): void
    {
        $builder = new LanguageCompetencePromptBuilder();
        $contextBuilder = (new ReflectionClass(LessonContextBuilder::class))->newInstanceWithoutConstructor();

        $property = new ReflectionMethod(LessonContextBuilder::class, 'mentorStylePrompt');
        $property->setAccessible(true);

        $setter = new \ReflectionProperty(LessonContextBuilder::class, 'languagePrompts');
        $setter->setAccessible(true);
        $setter->setValue($contextBuilder, $builder);

        $prompt = $property->invoke($contextBuilder, [
            'learning_preference' => 'balanced',
            'domain_interest' => 'web',
            'direction' => 'PHP',
            'subtopic_title' => 'HTTP и API',
            'mentor_persona' => 'colleague',
        ]);

        $this->assertStringContainsString('Язык: PHP', $prompt);
        $this->assertStringContainsString('экосистеме «PHP»', $prompt);
        $this->assertStringContainsString('HTTP-клиенты', $prompt);
    }
}
