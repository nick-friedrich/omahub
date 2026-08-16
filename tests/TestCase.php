<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The submission/admin controllers validate user input and CSRF is
        // covered separately by the framework. Skip it so feature tests can
        // POST directly without threading a token through every request.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }
}
