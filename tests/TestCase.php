<?php

namespace Tests;

use App\Models\Setting;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // BranchContext and the settings store memoize per request in static
        // state, cleared by middleware on each HTTP request. Tests call models
        // directly as well, and every test shares one PHP process, so reset
        // them here or one test's branch context leaks into the next.
        BranchContext::flush();
        Setting::forget();
    }

    protected function tearDown(): void
    {
        BranchContext::flush();
        Setting::forget();

        parent::tearDown();
    }
}
