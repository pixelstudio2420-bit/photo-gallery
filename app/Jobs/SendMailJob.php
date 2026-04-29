<?php

namespace App\Jobs;

use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued wrapper around any MailService method.
 *
 * Dispatching email via a queue moves the 100-500ms SMTP roundtrip out of the
 * request/response cycle. At 50k+ concurrent users, sending mail inline would
 * pin workers and stall the site; queued mail keeps the web tier at ~10ms per
 * request regardless of how slow the SMTP server is.
 *
 * Usage:
 *     SendMailJob::dispatch('welcome', ['user@example.com', 'John']);
 *     SendMailJob::dispatch('orderConfirmation', [$orderArray, $itemsArray]);
 *
 * Falls back to synchronous sending if the queue driver is `sync`.
 */
class SendMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;
    public int $backoff = 30;

    /** Use a lower-priority queue so transactional emails don't block image jobs. */
    public function __construct(
        public string $method,
        public array  $arguments = []
    ) {
        $this->onQueue('mail');
    }

    public function handle(MailService $mail): void
    {
        if (!method_exists($mail, $this->method)) {
            Log::warning("SendMailJob: unknown method {$this->method}");
            return;
        }

        try {
            $mail->{$this->method}(...$this->arguments);
        } catch (\Throwable $e) {
            Log::error("SendMailJob::{$this->method} failed: " . $e->getMessage());
            // Let Laravel's retry mechanism handle transient SMTP errors
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SendMailJob permanently failed: {$this->method} — " . $e->getMessage());
    }
}
