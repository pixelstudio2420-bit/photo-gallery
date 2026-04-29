<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Upgrade contact_messages to full ticket system
        Schema::table('contact_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_messages', 'ticket_number')) {
                $table->string('ticket_number', 20)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('contact_messages', 'category')) {
                $table->enum('category', ['general', 'billing', 'technical', 'account', 'refund', 'photographer', 'other'])->default('general')->after('subject');
            }
            if (!Schema::hasColumn('contact_messages', 'priority')) {
                $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->after('category');
            }
            if (!Schema::hasColumn('contact_messages', 'assigned_to_admin_id')) {
                $table->unsignedBigInteger('assigned_to_admin_id')->nullable()->after('priority');
            }
            if (!Schema::hasColumn('contact_messages', 'first_response_at')) {
                $table->timestamp('first_response_at')->nullable()->after('admin_reply');
            }
            if (!Schema::hasColumn('contact_messages', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('first_response_at');
            }
            if (!Schema::hasColumn('contact_messages', 'resolved_by_admin_id')) {
                $table->unsignedBigInteger('resolved_by_admin_id')->nullable()->after('resolved_at');
            }
            if (!Schema::hasColumn('contact_messages', 'sla_deadline')) {
                $table->timestamp('sla_deadline')->nullable()->after('resolved_by_admin_id');
            }
            if (!Schema::hasColumn('contact_messages', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('sla_deadline');
            }
            if (!Schema::hasColumn('contact_messages', 'reply_count')) {
                $table->unsignedInteger('reply_count')->default(0)->after('last_activity_at');
            }
            if (!Schema::hasColumn('contact_messages', 'satisfaction_rating')) {
                $table->unsignedTinyInteger('satisfaction_rating')->nullable()->after('reply_count');
            }
            if (!Schema::hasColumn('contact_messages', 'satisfaction_comment')) {
                $table->string('satisfaction_comment', 500)->nullable()->after('satisfaction_rating');
            }
        });

        // Add indexes for performance
        try {
            Schema::table('contact_messages', function (Blueprint $table) {
                $table->index(['status', 'priority'], 'cm_status_priority_idx');
                $table->index('category', 'cm_category_idx');
                $table->index('assigned_to_admin_id', 'cm_assigned_idx');
                $table->index('sla_deadline', 'cm_sla_idx');
            });
        } catch (\Throwable $e) {
            // Indexes may already exist
        }

        // Thread replies (each reply in the conversation)
        Schema::create('contact_replies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ticket_id'); // → contact_messages.id
            $table->enum('sender_type', ['user', 'admin', 'system']);
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('sender_name', 255);
            $table->string('sender_email', 255)->nullable();
            $table->longText('message');
            $table->boolean('is_internal_note')->default(false); // Admin-only note
            $table->json('attachments')->nullable(); // array of file paths
            $table->timestamp('read_at')->nullable(); // when opposite party read it
            $table->timestamp('created_at')->useCurrent();

            $table->index('ticket_id');
            $table->index(['ticket_id', 'is_internal_note']);
        });

        // Ticket activity log (status changes, assignments, etc.)
        Schema::create('contact_activities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ticket_id');
            $table->enum('type', [
                'created', 'replied', 'status_changed', 'priority_changed',
                'assigned', 'unassigned', 'category_changed', 'resolved',
                'reopened', 'closed', 'merged', 'note_added',
            ]);
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_name', 255)->nullable();
            $table->json('meta')->nullable(); // old_value, new_value, etc.
            $table->string('description', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('ticket_id');
            $table->index(['ticket_id', 'created_at']);
        });

        // Fill ticket numbers for existing records (driver-agnostic)
        $driver = \DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            \DB::statement("
                UPDATE contact_messages
                SET ticket_number = 'TKT-' || substr('000000' || id, -6, 6)
                WHERE ticket_number IS NULL
            ");
        } elseif ($driver === 'pgsql') {
            // Postgres: LPAD requires text — cast id::text first.
            \DB::statement("
                UPDATE contact_messages
                SET ticket_number = 'TKT-' || LPAD(id::text, 6, '0')
                WHERE ticket_number IS NULL
            ");
        } else {
            \DB::statement("
                UPDATE contact_messages
                SET ticket_number = CONCAT('TKT-', LPAD(id, 6, '0'))
                WHERE ticket_number IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_activities');
        Schema::dropIfExists('contact_replies');

        Schema::table('contact_messages', function (Blueprint $table) {
            foreach (['ticket_number', 'category', 'priority', 'assigned_to_admin_id',
                     'first_response_at', 'resolved_at', 'resolved_by_admin_id',
                     'sla_deadline', 'last_activity_at', 'reply_count',
                     'satisfaction_rating', 'satisfaction_comment'] as $col) {
                if (Schema::hasColumn('contact_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
