<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Cache;

trait ConfiguresCatTestEnvironment
{
    protected function configureCatTestEnvironment(): void
    {
        config([
            'cache.default'               => 'array',
            'kafka.cat_test.async'        => false,
            'kafka.cat_test.async_start'  => false,
            'kafka.cat_test.async_next'   => false,
            'kafka.cat_test.async_finish' => false,
        ]);

        Cache::clearResolvedInstances();
        Cache::flush();
    }
}
