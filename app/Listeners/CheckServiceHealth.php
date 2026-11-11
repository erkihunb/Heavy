<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The health check (`/up`, spec §16) also checks the services the system
 * cannot work without: the database, the cache (sessions, locks, rate
 * limits) and the queue connection. Any failure makes `/up` answer 500, so
 * a load balancer or uptime monitor sees it. The message names the service
 * only, never connection details.
 */
class CheckServiceHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        $this->check('database', fn () => DB::select('SELECT 1'));

        $this->check('cache', function (): void {
            $key = 'health:'.Str::random(8);
            Cache::put($key, 'ok', 10);
            $ok = Cache::pull($key) === 'ok';

            if (! $ok) {
                throw new RuntimeException('cache did not return the value');
            }
        });

        $this->check('queue', fn () => Queue::connection()->size());
    }

    private function check(string $service, callable $probe): void
    {
        try {
            $probe();
        } catch (\Throwable $exception) {
            throw new RuntimeException("Health check failed: {$service}.", 0, $exception);
        }
    }
}
