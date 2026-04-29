<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Enhance chat_messages
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('is_read');
            }
            if (!Schema::hasColumn('chat_messages', 'message_type')) {
                $table->enum('message_type', ['text', 'image', 'file', 'system'])->default('text')->after('message');
            }
            if (!Schema::hasColumn('chat_messages', 'attachment_url')) {
                $table->string('attachment_url', 500)->nullable()->after('message_type');
            }
            if (!Schema::hasColumn('chat_messages', 'attachment_name')) {
                $table->string('attachment_name', 255)->nullable()->after('attachment_url');
            }
            if (!Schema::hasColumn('chat_messages', 'attachment_size')) {
                $table->unsignedInteger('attachment_size')->nullable()->after('attachment_name');
            }
            if (!Schema::hasColumn('chat_messages', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('attachment_size');
            }
            if (!Schema::hasColumn('chat_messages', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('edited_at');
            }
        });

        // Enhance chat_conversations
        Schema::table('chat_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_conversations', 'subject')) {
                $table->string('subject', 255)->nullable()->after('event_id');
            }
            if (!Schema::hasColumn('chat_conversations', 'unread_count_user')) {
                $table->unsignedInteger('unread_count_user')->default(0)->after('status');
            }
            if (!Schema::hasColumn('chat_conversations', 'unread_count_photographer')) {
                $table->unsignedInteger('unread_count_photographer')->default(0)->after('unread_count_user');
            }
            if (!Schema::hasColumn('chat_conversations', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('unread_count_photographer');
            }
            if (!Schema::hasColumn('chat_conversations', 'archived_by')) {
                $table->enum('archived_by', ['user', 'photographer'])->nullable()->after('archived_at');
            }
        });

        // Add indexes
        try {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->index(['conversation_id', 'created_at'], 'cm_conv_created_idx');
                $table->index(['conversation_id', 'is_read'], 'cm_conv_read_idx');
            });
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            foreach (['read_at', 'message_type', 'attachment_url', 'attachment_name', 'attachment_size', 'edited_at', 'deleted_at'] as $col) {
                if (Schema::hasColumn('chat_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            foreach (['subject', 'unread_count_user', 'unread_count_photographer', 'archived_at', 'archived_by'] as $col) {
                if (Schema::hasColumn('chat_conversations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
