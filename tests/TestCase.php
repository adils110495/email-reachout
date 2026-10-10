<?php

namespace Tests;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Freeze the clock at the same instant, but in the app timezone - as the real clock is.
     *
     * While a test clock is set, Carbon reads database strings in the frozen clock's zone. A
     * clock frozen in UTC would make every TIMESTAMP read back 5:30 off under IST, a failure
     * that cannot happen in production. Tests still write instants in whatever zone reads best.
     */
    public function travelTo($date, $callback = null)
    {
        if ($date instanceof DateTimeInterface) {
            $date = CarbonImmutable::instance($date)->setTimezone(config('app.timezone'));
        }

        return parent::travelTo($date, $callback);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Safety net: tests migrate and truncate tables. Refuse to run against anything that
        // is not an obviously separate test schema (e.g. if an env var overrode phpunit.xml).
        $database = (string) config('database.connections.'.config('database.default').'.database');
        if (! app()->environment('testing') || ! str_ends_with($database, '_test')) {
            $this->fail('Refusing to run: env=['.app()->environment()."] database=[$database]. Tests need APP_ENV=testing and a *_test database.");
        }
    }
}
