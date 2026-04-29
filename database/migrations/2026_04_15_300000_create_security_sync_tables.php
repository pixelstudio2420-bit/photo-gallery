<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Admin 2FA ──
        if (!Schema::hasTable('admin_2fa')) {
            Schema::create('admin_2fa', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('admin_id')->unique();
                $table->string('secret_key');
                $table->boolean('is_enabled')->default(false);
                $table->text('backup_codes')->nullable();
                $table->timestamps();

                $table->foreign('admin_id')->references('id')->on('auth_admins')->onDelete('cascade');
            });
        }

        // ── Security Logs ──
        if (!Schema::hasTable('security_logs')) {
            Schema::create('security_logs', function (Blueprint $table) {
                $table->id();
                $table->string('event_type', 100);          // login, logout, 2fa, password_change, etc.
                $table->string('severity', 20)->default('info'); // info, warning, critical
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent')->nullable();
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('event_type');
                $table->index('severity');
                $table->index('admin_id');
                $table->index('created_at');
            });
        }

        // ── Security IP Rules (blacklist / whitelist) ──
        if (!Schema::hasTable('security_ip_rules')) {
            Schema::create('security_ip_rules', function (Blueprint $table) {
                $table->id();
                $table->string('ip_address', 45);
                $table->string('rule_type', 20)->default('blacklist'); // blacklist, whitelist
                $table->string('reason')->nullable();
                $table->unsignedBigInteger('blocked_by')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index('ip_address');
                $table->index('rule_type');
            });
        }

        // ── Threat Incidents ──
        if (!Schema::hasTable('threat_incidents')) {
            Schema::create('threat_incidents', function (Blueprint $table) {
                $table->id();
                $table->string('threat_type', 100);       // brute_force, sql_injection, xss, etc.
                $table->string('severity', 20)->default('medium'); // low, medium, high, critical
                $table->string('ip_address', 45)->nullable();
                $table->string('target_url')->nullable();
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->string('status', 20)->default('detected'); // detected, blocked, resolved
                $table->timestamps();

                $table->index('threat_type');
                $table->index('severity');
                $table->index('created_at');
            });
        }

        // ── Sync Queue (Google Drive sync) ──
        if (!Schema::hasTable('sync_queue')) {
            Schema::create('sync_queue', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('event_id')->nullable();
                $table->string('status', 30)->default('pending'); // pending, processing, running, completed, failed
                $table->string('action', 50)->default('sync');    // sync, resync, delete
                $table->integer('total_files')->default(0);
                $table->integer('processed_files')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('event_id');

                $table->foreign('event_id')->references('id')->on('event_events')->onDelete('cascade');
            });
        }

        // ── Sessions (Laravel default) ──
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queue');
        Schema::dropIfExists('threat_incidents');
        Schema::dropIfExists('security_ip_rules');
        Schema::dropIfExists('security_logs');
        Schema::dropIfExists('admin_2fa');
        Schema::dropIfExists('sessions');
    }
};
