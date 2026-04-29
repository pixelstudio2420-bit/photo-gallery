<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke-tests the env:check artisan command.
 *
 * NOTE: phpunit.xml forces DB_CONNECTION=sqlite for fast in-memory tests.
 * env:check should correctly detect that this fork requires pgsql, so
 * --strict mode is expected to FAIL inside the test runner. That failure
 * is the feature working — not a bug.
 */
class EnvironmentCheckCommandTest extends TestCase
{
    public function test_env_check_runs_in_non_strict_mode(): void
    {
        // In non-strict mode, env:check always returns 0 even with errors,
        // so it can be used as an informational pre-deploy report.
        $this->artisan('env:check')->assertExitCode(0);
    }

    public function test_env_check_strict_flags_sqlite_driver_in_postgres_fork(): void
    {
        // The test runner uses sqlite. env:check --strict must reject this
        // because the codebase contains Postgres-only SQL (FILTER, ::interval).
        // Exit code 1 means the gate worked correctly.
        $this->artisan('env:check', ['--strict' => true])->assertExitCode(1);
    }

    public function test_env_check_quiet_success_flag_does_not_change_exit_code(): void
    {
        // --quiet-success only suppresses output; failures still bubble up.
        $this->artisan('env:check', ['--quiet-success' => true])->assertExitCode(0);
    }
}
