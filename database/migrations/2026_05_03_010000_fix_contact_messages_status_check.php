<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix the contact_messages.status CHECK constraint.
 *
 * The original migration shipped a 3-state CHECK:
 *     CHECK (status IN ('new', 'read', 'replied'))
 *
 * but the ContactMessage model + admin UI evolved to 6 states:
 *     new / open / in_progress / waiting / resolved / closed
 *
 * Result: the controller's show() method calls changeStatus('open')
 * the first time admin opens a 'new' ticket → DB rejects → 500. The
 * user reported it on /admin/messages/3.
 *
 * This migration:
 *   1. Drops the old constraint
 *   2. Migrates legacy data:
 *        'read'    → 'open'        (admin saw, hasn't acted)
 *        'replied' → 'in_progress' (admin replied, ticket still active)
 *   3. Adds the new constraint covering all 6 model states
 *
 * Postgres-specific because Laravel's Schema doesn't have a portable
 * "drop check constraint" primitive — but contact_messages is pgsql-
 * only in this app per the rest of the codebase, so this is fine.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('contact_messages')) return;
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') return;

        // 1. Drop the old constraint (idempotent — IF EXISTS handles
        //    the case where someone already manually fixed it).
        DB::statement('ALTER TABLE contact_messages DROP CONSTRAINT IF EXISTS contact_messages_status_check');

        // 2. Migrate legacy values BEFORE re-adding the constraint —
        //    otherwise rows with status='read' would block the new
        //    constraint creation.
        DB::table('contact_messages')->where('status', 'read')->update(['status'    => 'open']);
        DB::table('contact_messages')->where('status', 'replied')->update(['status' => 'in_progress']);

        // 3. Add the constraint covering all 6 model states.
        DB::statement(<<<SQL
            ALTER TABLE contact_messages
            ADD CONSTRAINT contact_messages_status_check
            CHECK (status IN ('new', 'open', 'in_progress', 'waiting', 'resolved', 'closed'))
        SQL);
    }

    public function down(): void
    {
        if (!Schema::hasTable('contact_messages')) return;
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') return;

        // Map back to the 3-state vocabulary so the rollback constraint
        // accepts every existing row.
        DB::table('contact_messages')->where('status', 'open')->update(['status' => 'read']);
        DB::table('contact_messages')->whereIn('status', ['in_progress', 'waiting', 'resolved', 'closed'])->update(['status' => 'replied']);

        DB::statement('ALTER TABLE contact_messages DROP CONSTRAINT IF EXISTS contact_messages_status_check');
        DB::statement(<<<SQL
            ALTER TABLE contact_messages
            ADD CONSTRAINT contact_messages_status_check
            CHECK (status IN ('new', 'read', 'replied'))
        SQL);
    }
};
