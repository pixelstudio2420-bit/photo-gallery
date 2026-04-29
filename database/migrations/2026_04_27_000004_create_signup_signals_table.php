<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anti-abuse signal store for Free-tier signups.
 *
 * Multi-account abuse is the single biggest cost-leak on a Free-tier
 * SaaS — one human creating 100 free accounts pays the same price
 * (₿0) but consumes 100× the storage + AI budget. We track signals
 * pre-signup (email, IP, device fingerprint) and the AntiAbuseGuard
 * middleware can either rate-limit or require email verification +
 * CAPTCHA when the score crosses a threshold.
 *
 * Hashed columns: we store sha256(value) not the raw email/IP so a
 * leaked DB doesn't deanonymize users. Rainbow-table attacks on emails
 * are still possible — production should add a per-environment salt
 * (env: SIGNUP_SIGNAL_SALT). Tests don't bother.
 *
 * Retention: rows are pruned after 90 days. The signal is only useful
 * during the burst window — once a fraud ring's accounts are flagged,
 * the dust settles.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('signup_signals', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->char('email_hash', 64)->nullable()->index();
            $t->char('ip_hash', 64)->nullable()->index();
            $t->char('device_fingerprint', 64)->nullable()->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedSmallInteger('risk_score')->default(0);
            $t->boolean('flagged')->default(false);
            $t->json('metadata')->nullable();
            $t->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_signals');
    }
};
