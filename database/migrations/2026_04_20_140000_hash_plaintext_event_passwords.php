<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Historical event_password rows were saved as plaintext. Convert every
 * one to a bcrypt hash so a DB dump no longer exposes gallery passwords.
 *
 * We detect "already-hashed" rows via the `$2y$` / `$argon2` prefix and
 * skip them — safe to re-run (idempotent). There's no down-migration:
 * plaintext can't be recovered from a hash by design.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('event_events')) return;

        DB::table('event_events')
            ->whereNotNull('event_password')
            ->where('event_password', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $pw = (string) $row->event_password;
                    // Skip if already hashed (bcrypt or argon2)
                    if (preg_match('/^\$(2[aby]|argon2[id]?)\$/', $pw)) {
                        continue;
                    }
                    DB::table('event_events')
                        ->where('id', $row->id)
                        ->update(['event_password' => Hash::make($pw)]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally no-op — hashes can't be reversed.
    }
};
