<?php

namespace Tests;

use App\Support\DocumentVersioning;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DocumentVersioning::flush();   // settings are remembered per process, and each test starts with an empty database
    }
}
