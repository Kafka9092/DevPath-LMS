<?php

namespace Tests\Concerns;

trait ConfiguresHrTestEnvironment
{
    use ConfiguresCatTestEnvironment;

    protected function configureHrTestEnvironment(): void
    {
        $this->configureCatTestEnvironment();

        config([
            'kafka.hr_interview.async'        => false,
            'kafka.hr_interview.async_reply'  => false,
            'kafka.hr_interview.async_code'   => false,
        ]);
    }
}
