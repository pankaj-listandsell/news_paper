<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach the internet. Rendering any page asks Open-Meteo
        // for the weather, and a suite that depends on someone else's server
        // being up is a suite that fails for no reason.
        Http::preventStrayRequests();
    }
}
