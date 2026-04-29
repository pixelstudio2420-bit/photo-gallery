<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reset all `promptpay_verified_name` + `promptpay_verified_at` values
 * because EVERYTHING in those columns to date came from the old mock
 * `PromptPayService::mockName()` (a deterministic hash → fake name). That
 * code path is gone (see PromptPayService header for the rationale), so
 * every stored value is a lie: a name that looks plausible but was never
 * confirmed by any bank.
 *
 * We copy the fake name into `bank_account_name` ONLY if the photographer
 * didn't type one themselves — that way they can at least see what the
 * old UI was showing them and correct it on their next visit to the
 * setup-bank page. Without this backfill we'd be erasing any record of
 * the payout target for existing profiles.
 *
 * The `omise_recipient_id` is also cleared so the next transfer rebuilds
 * the recipient at Omise with the photographer's (soon-to-be-corrected)
 * typed name — otherwise Omise would keep the stale fake name on file.
 *
 * Down migration is a no-op. We can't un-fake the data; the photographer
 * just has to re-enter their real name on setup-bank, and the first real
 * transfer will populate the verified columns honestly.
 */
return new class extends Migration {
    public function up(): void
    {
        // Copy fake verified_name into bank_account_name where the user
        // hasn't typed one yet. This gives them a starting value they can
        // eyeball and correct rather than an empty field on their next
        // visit to setup-bank.
        DB::statement("
            UPDATE photographer_profiles
            SET bank_account_name = promptpay_verified_name
            WHERE (bank_account_name IS NULL OR bank_account_name = '')
              AND promptpay_verified_name IS NOT NULL
              AND promptpay_verified_name != ''
        ");

        // Now wipe the verification flags + stale Omise recipient id.
        // Leave promptpay_number untouched — that's real user input.
        DB::table('photographer_profiles')
            ->whereNotNull('promptpay_verified_at')
            ->update([
                'promptpay_verified_name' => null,
                'promptpay_verified_at'   => null,
                'omise_recipient_id'      => null,
            ]);
    }

    public function down(): void
    {
        // Intentional no-op: the data we erased was fake, so there's
        // nothing to restore. A down migration that repopulates the
        // mock data would actively reintroduce the bug this migration
        // is fixing.
    }
};
