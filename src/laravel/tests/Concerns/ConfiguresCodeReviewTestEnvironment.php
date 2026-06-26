<?php

namespace Tests\Concerns;

trait ConfiguresCodeReviewTestEnvironment
{
    use ConfiguresCatTestEnvironment;

    protected function configureCodeReviewTestEnvironment(): void
    {
        $this->configureCatTestEnvironment();

        config([
            'kafka.code_review.async'         => false,
            'kafka.code_review.async_analyze' => false,
        ]);
    }
}
