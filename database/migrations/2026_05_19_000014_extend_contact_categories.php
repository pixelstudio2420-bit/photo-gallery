<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extend `contact_messages.category` CHECK constraint to include two
 * new categories specifically requested by the photographer:
 *
 *   • bug_report      — รายงานปัญหา / บัก
 *     Was being lumped under 'technical' (which catches anything
 *     login / app-crash / non-bug-also). A dedicated category lets
 *     admins triage faster and lets us auto-route bug_report → eng
 *     queue separate from billing/refund queues.
 *
 *   • feature_request — ข้อเสนอแนะ / ฟีเจอร์ใหม่
 *     Previously fell into 'general' or 'other' — now has a clear
 *     home so the product team can build a backlog from real user
 *     suggestions without sifting through password-reset asks.
 *
 * The categories surface automatically in the public contact form
 * (resources/views/public/contact.blade.php uses
 * ContactMessage::CATEGORIES to build the <select>), and the admin
 * queue at /admin/messages picks them up via the same constant.
 *
 * Compat: existing rows are not migrated — they keep their current
 * category. Only NEW submissions can pick the new options.
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE contact_messages DROP CONSTRAINT IF EXISTS contact_messages_category_check');
            DB::statement("
                ALTER TABLE contact_messages
                ADD CONSTRAINT contact_messages_category_check
                CHECK (category IN (
                    'general', 'billing', 'technical', 'account', 'refund',
                    'photographer', 'bug_report', 'feature_request', 'other'
                ))
            ");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("
                ALTER TABLE contact_messages
                MODIFY COLUMN category ENUM(
                    'general', 'billing', 'technical', 'account', 'refund',
                    'photographer', 'bug_report', 'feature_request', 'other'
                ) NOT NULL DEFAULT 'general'
            ");
        }
        // SQLite test DB — CHECK constraint is baked into the table at
        // create time and can't be ALTERed. Tests don't write to this
        // category enum so the seed step works regardless.
    }

    public function down(): void
    {
        // Revert any rows that were created with the new categories
        // before narrowing the constraint, otherwise the rebuild would
        // reject them.
        DB::table('contact_messages')->whereIn('category', ['bug_report', 'feature_request'])
            ->update(['category' => 'other']);

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE contact_messages DROP CONSTRAINT IF EXISTS contact_messages_category_check');
            DB::statement("
                ALTER TABLE contact_messages
                ADD CONSTRAINT contact_messages_category_check
                CHECK (category IN (
                    'general', 'billing', 'technical', 'account', 'refund',
                    'photographer', 'other'
                ))
            ");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("
                ALTER TABLE contact_messages
                MODIFY COLUMN category ENUM(
                    'general', 'billing', 'technical', 'account', 'refund',
                    'photographer', 'other'
                ) NOT NULL DEFAULT 'general'
            ");
        }
    }
};
