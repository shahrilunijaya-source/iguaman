<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // TEST-01: the suite shares the dev MySQL DB and cleans up by tag/email prefix, so a
        // stray non-testing env (real mail/queue, wrong DB) must hard-fail rather than mutate
        // live data. phpunit.xml forces APP_ENV=testing; this catches a mis-invoked run.
        if (! app()->environment('testing')) {
            throw new \RuntimeException(
                'Refusing to run tests: APP_ENV is "'.app()->environment().'", expected "testing". '.
                'Run via "php artisan test" / phpunit so phpunit.xml forces the testing env.'
            );
        }

        // The array cache store persists for the whole test-run process. Phase 5 added
        // report/SLA/KPI caching, so flush between tests or one test's aggregates bleed
        // into the next (keys are data-independent, only filter/period-keyed).
        Cache::flush();
    }
}
