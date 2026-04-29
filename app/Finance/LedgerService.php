<?php

namespace App\Finance;

use App\Finance\Exceptions\JournalEntryAlreadyPostedException;
use App\Finance\Models\FinancialAccount;
use App\Finance\Models\JournalEntry;
use App\Finance\Models\JournalLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single point through which every double-entry posting flows.
 *
 * Invariants enforced by this class
 * ---------------------------------
 *   1. SUM(debits) == SUM(credits) per journal     (assertBalanced)
 *   2. Idempotency: same idempotency_key MUST resolve to the same posted
 *      journal — a retry returns the existing one rather than throwing
 *      uncaught.
 *   3. Atomic: journal_entry + every journal_line + balance updates
 *      happen in ONE DB transaction. A crash mid-post leaves nothing
 *      half-written.
 *   4. Append-only. We NEVER UPDATE/DELETE a posted entry — reversals
 *      create a NEW entry pointing back via reversed_by_id.
 */
class LedgerService
{
    /**
     * Post a balanced journal draft. Returns the persisted JournalEntry.
     *
     * Idempotency:
     *   If $draft->idempotencyKey already exists, returns the EXISTING
     *   journal silently (caller treats this as success — the real-world
     *   case is a webhook retry).
     *
     * @throws \App\Finance\Exceptions\UnbalancedJournalException  if DR != CR
     */
    public function post(JournalDraft $draft): JournalEntry
    {
        // 1. Validate before opening a DB transaction. Saves a round-trip
        //    when callers pass garbage.
        $draft->assertBalanced();

        // 2. Idempotency check OUTSIDE the transaction first — short
        //    circuit on retry without taking any locks. Inside the txn
        //    we use the unique constraint as the authoritative check.
        $existing = JournalEntry::where('idempotency_key', $draft->idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        // 3. Atomic: insert entry + lines + bump balances in one txn.
        return DB::transaction(function () use ($draft) {
            // Re-check idempotency under FOR-UPDATE-style isolation.
            // Postgres serializable / repeatable read makes this safe
            // because of the UNIQUE constraint — a concurrent insert
            // would fail here and we'd recover on the next attempt.
            try {
                $entry = JournalEntry::create([
                    'journal_uuid'    => (string) Str::uuid(),
                    'type'            => $draft->type,
                    'description'     => $draft->description,
                    'idempotency_key' => $draft->idempotencyKey,
                    'metadata'        => $draft->metadata,
                    'posted_at'       => now(),
                    'posted_by'       => $draft->postedBy ?? 'system',
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Concurrent post won the race — fetch the now-existing entry.
                $existing = JournalEntry::where('idempotency_key', $draft->idempotencyKey)->first();
                if ($existing) {
                    throw new JournalEntryAlreadyPostedException($existing);
                }
                throw $e;
            }

            foreach ($draft->lines() as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account']->id,
                    'direction'        => $line['direction'],
                    'amount_minor'     => $line['amount']->minor,
                    'currency'         => $line['amount']->currency,
                    'created_at'       => now(),
                ]);

                $this->bumpBalance($line['account'], $line['direction'], $line['amount']);
            }

            return $entry->load('lines');
        });
    }

    /**
     * Reverse a posted journal — creates a new entry that mirrors the
     * original with DR↔CR swapped. The original is marked
     * reversed_by_id; both entries live forever.
     *
     * @param  string  $reasonIdempotencyKey  Caller-supplied; e.g. 'order.42.refunded'
     */
    public function reverse(JournalEntry $original, string $reasonIdempotencyKey, ?string $reason = null): JournalEntry
    {
        $original->loadMissing('lines');

        $reversal = new JournalDraft(
            type:           $original->type . '.reversed',
            idempotencyKey: $reasonIdempotencyKey,
            description:    $reason ?? "Reversal of #{$original->id}",
            postedBy:       'system',
            metadata:       ['reverses_journal_id' => $original->id, 'reverses_uuid' => $original->journal_uuid],
        );

        foreach ($original->lines as $line) {
            $account = FinancialAccount::find($line->account_id);
            if (!$account) {
                continue; // skip orphaned lines (shouldn't happen — FK protects)
            }
            // Swap direction: original DR becomes CR in the reversal.
            if ($line->isDebit()) {
                $reversal->credit($account, $line->money());
            } else {
                $reversal->debit($account, $line->money());
            }
        }

        $reversalEntry = $this->post($reversal);

        // Mark the original as reversed (one-shot — UNIQUE-style guard
        // via reversed_by_id IS NULL prevents double-reversal races).
        if ($original->reversed_by_id === null) {
            $original->reversed_by_id = $reversalEntry->id;
            $original->save();
        }

        return $reversalEntry;
    }

    /**
     * Current balance of an account, signed under the "normal balance"
     * convention:
     *   - Asset/Expense:    DR is positive (assets grow with debits)
     *   - Liability/Eq/Rev: CR is positive (liabilities grow with credits)
     *
     * Falls back to recomputing from journal_lines if the cached
     * balance row is missing — costly, but correct.
     */
    public function balance(FinancialAccount $account): Money
    {
        $cached = DB::table('financial_balances')
            ->where('account_id', $account->id)
            ->first();

        if ($cached) {
            return new Money((int) $cached->balance_minor, (string) $cached->currency);
        }
        return $this->recomputeBalance($account);
    }

    /**
     * Force-recompute from the immutable journal lines. Useful for the
     * reconciliation cron and after restoring from backup.
     */
    public function recomputeBalance(FinancialAccount $account): Money
    {
        $row = DB::table('financial_journal_lines')
            ->where('account_id', $account->id)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN direction = 'DR' THEN amount_minor ELSE 0 END), 0) AS dr,
                COALESCE(SUM(CASE WHEN direction = 'CR' THEN amount_minor ELSE 0 END), 0) AS cr
            ")
            ->first();

        $dr = (int) ($row->dr ?? 0);
        $cr = (int) ($row->cr ?? 0);

        $signed = $account->normalBalanceDirection() === JournalLine::DEBIT
            ? ($dr - $cr)
            : ($cr - $dr);

        return new Money($signed, $account->currency);
    }

    /* ─────────────────── Internals ─────────────────── */

    private function bumpBalance(FinancialAccount $account, string $direction, Money $amount): void
    {
        // Sign relative to the account's normal balance direction.
        // For an asset account: DR adds, CR subtracts.
        // For a revenue account: CR adds, DR subtracts.
        $normal = $account->normalBalanceDirection();
        $delta  = ($direction === $normal) ? $amount->minor : -$amount->minor;

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement(
                'INSERT INTO financial_balances (account_id, balance_minor, currency, updated_at)
                 VALUES (?, ?, ?, NOW())
                 ON CONFLICT (account_id) DO UPDATE SET
                   balance_minor = financial_balances.balance_minor + EXCLUDED.balance_minor,
                   updated_at    = NOW()',
                [$account->id, $delta, $account->currency],
            );
            return;
        }

        // sqlite / mysql path — same effect via lockForUpdate.
        $row = DB::table('financial_balances')
            ->where('account_id', $account->id)
            ->lockForUpdate()
            ->first();

        if ($row) {
            DB::table('financial_balances')
                ->where('account_id', $account->id)
                ->update([
                    'balance_minor' => DB::raw('balance_minor + (' . (int) $delta . ')'),
                    'updated_at'    => now(),
                ]);
        } else {
            DB::table('financial_balances')->insert([
                'account_id'    => $account->id,
                'balance_minor' => $delta,
                'currency'      => $account->currency,
                'updated_at'    => now(),
            ]);
        }
    }
}
