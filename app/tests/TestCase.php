<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Inertia pages render through the `app` Blade layout, which calls @vite().
        // Stubbing Vite keeps the suite independent of `npm run build` artifacts.
        $this->withoutVite();
    }
}
