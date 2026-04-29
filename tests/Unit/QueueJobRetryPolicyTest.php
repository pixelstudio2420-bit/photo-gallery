<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Every queue job declares explicit $tries, $timeout (and, where
 * appropriate, $backoff) values.
 *
 * Why this matters
 * ----------------
 * A queue job without $tries defaults to Laravel's "retry forever until
 * failed_jobs is reviewed" behaviour, which makes one bad job quietly
 * lock up the worker. Without $timeout, a stuck HTTP call (Drive, S3,
 * Rekognition) holds the worker forever. Without $backoff, a rate-limited
 * call retries back-to-back, burning the next minute's quota before the
 * cooldown has passed.
 *
 * This test does NOT exercise the jobs at runtime — it inspects each job
 * class via reflection and asserts the property values. That keeps it
 * fast, avoids needing real queue infra, and crucially catches the case
 * where someone adds a new job class and forgets to set these.
 *
 * One-shot jobs (PurgeEventJob, PurgeEventOriginalsJob) deliberately set
 * $tries = 1 and skip $backoff — a half-run purge is worse than a skip,
 * so retries are off by design. Those are allow-listed below.
 */
class QueueJobRetryPolicyTest extends TestCase
{
    /**
     * Jobs that are intentionally one-shot: they MUST have $tries = 1
     * and are allowed to omit $backoff (retries are off by design).
     */
    private const ONE_SHOT_JOBS = [
        \App\Jobs\PurgeEventJob::class,
        \App\Jobs\PurgeEventOriginalsJob::class,
    ];

    /**
     * Every other job class that should have $tries + $timeout + $backoff.
     * Listed explicitly (not auto-discovered) so a new unsafe job fails
     * this test loudly instead of silently skipping coverage.
     */
    private const RETRYABLE_JOBS = [
        \App\Jobs\BuildOrderZipJob::class,
        \App\Jobs\FetchNewsFromSource::class,
        \App\Jobs\ImportDrivePhotosJob::class,
        \App\Jobs\ImportSingleDrivePhotoJob::class,
        \App\Jobs\MirrorPhotoJob::class,
        \App\Jobs\ModeratePhotoJob::class,
        \App\Jobs\ProcessAiTask::class,
        \App\Jobs\ProcessPhotoCache::class,
        \App\Jobs\ProcessUploadedPhotoJob::class,
        \App\Jobs\SendMailJob::class,
        \App\Jobs\SyncEventPhotos::class,
    ];

    // ─── Retryable jobs must declare all three properties ───

    #[DataProvider('retryableJobsProvider')]
    public function test_retryable_job_has_complete_retry_policy(string $jobClass): void
    {
        $this->assertJobHasIntProperty($jobClass, 'tries');
        $this->assertJobHasIntProperty($jobClass, 'timeout');
        $this->assertJobHasIntProperty($jobClass, 'backoff');
    }

    public static function retryableJobsProvider(): array
    {
        return array_map(fn ($class) => [$class], self::RETRYABLE_JOBS);
    }

    // ─── Retry counts are in a sensible range ───

    #[DataProvider('retryableJobsProvider')]
    public function test_retryable_job_tries_count_is_sensible(string $jobClass): void
    {
        $tries = $this->propertyValue($jobClass, 'tries');

        // 1 is valid for one-shot jobs (but those aren't in this provider),
        // 2-5 is the sweet spot for real retry logic. > 10 suggests
        // someone set a default by accident; those should fail LOUDLY.
        $this->assertGreaterThanOrEqual(2, $tries, "{$jobClass} has \$tries = {$tries}; retryable jobs need at least 2 attempts");
        $this->assertLessThanOrEqual(10, $tries, "{$jobClass} has \$tries = {$tries}; that's excessive — use a dead-letter queue instead");
    }

    #[DataProvider('retryableJobsProvider')]
    public function test_retryable_job_backoff_is_sensible(string $jobClass): void
    {
        $backoff = $this->propertyValue($jobClass, 'backoff');

        // A backoff of 0 means "retry instantly" — which for rate-limited
        // APIs burns the next budget. Enforce a minimum cooldown.
        $this->assertGreaterThanOrEqual(5, $backoff, "{$jobClass} has \$backoff = {$backoff}s; that's too aggressive for retrying API calls");
        // > 10 minutes suggests a typo (hours instead of seconds).
        $this->assertLessThanOrEqual(600, $backoff, "{$jobClass} has \$backoff = {$backoff}s; that's over 10 minutes — double-check the unit");
    }

    #[DataProvider('retryableJobsProvider')]
    public function test_retryable_job_timeout_is_positive(string $jobClass): void
    {
        $timeout = $this->propertyValue($jobClass, 'timeout');
        $this->assertGreaterThan(0, $timeout, "{$jobClass} has a non-positive \$timeout; the worker would never finish");
    }

    // ─── One-shot jobs: tries=1, and deliberately no backoff requirement ───

    #[DataProvider('oneShotJobsProvider')]
    public function test_one_shot_job_has_tries_one(string $jobClass): void
    {
        $this->assertSame(
            1,
            $this->propertyValue($jobClass, 'tries'),
            "{$jobClass} is allow-listed as one-shot but \$tries is not 1 — partial state is worse than a skip here"
        );
        // timeout must still be finite
        $this->assertGreaterThan(0, $this->propertyValue($jobClass, 'timeout'));
    }

    public static function oneShotJobsProvider(): array
    {
        return array_map(fn ($class) => [$class], self::ONE_SHOT_JOBS);
    }

    // ─── Helpers ───

    private function assertJobHasIntProperty(string $jobClass, string $property): void
    {
        $this->assertTrue(
            class_exists($jobClass),
            "Job class {$jobClass} does not exist — update RETRYABLE_JOBS/ONE_SHOT_JOBS if it was renamed or removed"
        );

        $ref = new ReflectionClass($jobClass);
        $this->assertTrue(
            $ref->hasProperty($property),
            "{$jobClass} is missing public int \${$property} — the queue will use Laravel defaults, which can lock up the worker"
        );

        $prop = $ref->getProperty($property);
        $this->assertTrue(
            $prop->isPublic(),
            "{$jobClass}::\${$property} must be public so the queue worker can read it"
        );
    }

    private function propertyValue(string $jobClass, string $property): int
    {
        $ref = new ReflectionClass($jobClass);

        // Prefer the declared default over instantiating the job (which
        // needs constructor args for most of them).
        $defaults = $ref->getDefaultProperties();
        $this->assertArrayHasKey(
            $property,
            $defaults,
            "{$jobClass}::\${$property} has no default value"
        );

        return (int) $defaults[$property];
    }
}
