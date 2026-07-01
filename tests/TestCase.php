<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The array cache store persists for the whole test-run process. Phase 5 added
        // report/SLA/KPI caching, so flush between tests or one test's aggregates bleed
        // into the next (keys are data-independent, only filter/period-keyed).
        Cache::flush();
    }
}
