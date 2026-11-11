<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run when the suite is not pointed at a dedicated test database.
     * RefreshDatabase wipes whatever database is configured, so this must be
     * checked before any testing trait is booted.
     *
     * @return array<class-string, int>
     */
    protected function setUpTraits(): array
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! in_array($connection, ['mysql', 'mariadb'], true) || ! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Refusing to run tests against [{$connection}:{$database}]. ".
                'Tests must use the MySQL *_testing database configured in phpunit.xml.'
            );
        }

        return parent::setUpTraits();
    }

    protected function skipUnlessFortifyFeature(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
