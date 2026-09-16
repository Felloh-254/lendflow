<?php

namespace Tests\Support;

use Symfony\Component\Process\Process;

/**
 * Thin wrapper around Symfony Process for spawning `php artisan ...`
 * child processes. Used by the concurrency and deadlock test suites to
 * get REAL OS-level concurrent access to the same database rows — a
 * single PHPUnit process, being single-threaded, cannot exercise actual
 * PostgreSQL lock contention on its own.
 *
 * Environment variables are inherited from the parent process by default
 * (Symfony Process's normal behavior), which is what lets each child
 * connect to the same `lendflow_testing` database phpunit.xml configures
 * for the parent test run.
 */
class ConcurrentProcess
{
    public static function artisan(array $arguments, int $timeoutSeconds = 15): Process
    {
        $process = new Process([
            PHP_BINARY,
            base_path('artisan'),
            ...$arguments,
        ]);

        $process->setTimeout($timeoutSeconds);

        return $process;
    }

    /**
     * Start every given process as close to simultaneously as this
     * process can manage, then block until all have finished.
     *
     * @param  Process[]  $processes
     * @return Process[] the same processes, now finished
     */
    public static function runConcurrently(array $processes): array
    {
        foreach ($processes as $process) {
            $process->start();
        }

        foreach ($processes as $process) {
            $process->wait();
        }

        return $processes;
    }

    /**
     * Parse the last JSON line a `repayment:simulate` / `deadlock:simulate`
     * process printed to stdout.
     */
    public static function lastJsonLine(Process $process): array
    {
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));

        $last = end($lines);

        $decoded = json_decode($last ?: '{}', true);

        return is_array($decoded) ? $decoded : [];
    }
}
