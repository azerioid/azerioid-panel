<?php

namespace Tests;

use App\Services\Broker\FakeBroker;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        if ($this->app->bound(FakeBroker::class)) {
            $this->app->make(FakeBroker::class)->reset();
        }
    }
}
