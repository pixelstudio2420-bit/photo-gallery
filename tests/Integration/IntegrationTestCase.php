<?php

namespace Tests\Integration;

use Tests\TestCase;

/**
 * Base for integration tests that hit REAL external APIs.
 *
 * Why a separate base class?
 * --------------------------
 * Regular tests run on every CI commit and use mocks/fakes. Integration
 * tests hit the real Google / LINE / R2 endpoints — they're expensive,
 * flaky, and require live credentials. We DON'T run them on every
 * commit.
 *
 * Run model
 * ---------
 *   php vendor/bin/phpunit --testsuite=Integration
 *
 * The phpunit.xml `<testsuites>` config could expose this as a separate
 * suite, OR an operator can simply target this directory:
 *
 *   php vendor/bin/phpunit tests/Integration/
 *
 * Each test self-skips when its required ENV vars are missing — so
 * running the suite without credentials is harmless (everything is
 * SKIPPED, no failures). When credentials ARE present, the tests
 * exercise real API contracts and catch breakage that mocks miss
 * (auth flow changes, rate limits, response shape drift).
 *
 * Cadence recommendation
 * ----------------------
 * Run weekly via cron, or before every production release. Failed
 * runs alert the same email/LINE channels as the queue heartbeat.
 */
abstract class IntegrationTestCase extends TestCase
{
    /**
     * Skip the test if any of the required ENV keys is missing.
     * Centralised so each smoke test can declare its dependencies
     * declaratively at the top.
     */
    protected function requireEnv(array $keys): void
    {
        $missing = [];
        foreach ($keys as $k) {
            if (env($k) === null || env($k) === '') {
                $missing[] = $k;
            }
        }
        if ($missing) {
            $this->markTestSkipped(
                'Skipping integration test — missing env: ' . implode(', ', $missing)
            );
        }
    }
}
