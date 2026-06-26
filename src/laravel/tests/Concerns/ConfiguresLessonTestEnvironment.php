<?php

namespace Tests\Concerns;

trait ConfiguresLessonTestEnvironment
{
    use ConfiguresCatTestEnvironment;

    protected function configureLessonTestEnvironment(): void
    {
        $this->configureCatTestEnvironment();

        config([
            'kafka.lesson.async'          => false,
            'kafka.lesson.async_content'  => false,
            'kafka.lesson.async_chat'     => false,
            'kafka.lesson.async_review'   => false,
        ]);
    }
}
