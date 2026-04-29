<?php

namespace App\Finance\Exceptions;

use App\Finance\Models\JournalEntry;
use RuntimeException;

/**
 * Thrown when a caller tries to post a journal whose idempotency_key
 * already exists. The pre-existing entry is attached so the caller
 * can decide whether to short-circuit (treat as success) or surface
 * an error.
 */
class JournalEntryAlreadyPostedException extends RuntimeException
{
    public function __construct(
        public readonly JournalEntry $existing,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? "Journal already posted for idempotency_key '{$existing->idempotency_key}'"
        );
    }
}
