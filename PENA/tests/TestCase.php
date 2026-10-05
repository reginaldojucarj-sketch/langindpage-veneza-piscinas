<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fail before fixtures/migrations if an environment or config cache overrides PHPUnit.
        if (! app()->environment('testing') ||
            config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.database') !== ':memory:' ||
            config('database.connections.sqlite.url')) {
            throw new \RuntimeException('Tests require testing + SQLite :memory: without DB_URL. No fixture was written.');
        }
    }
}
