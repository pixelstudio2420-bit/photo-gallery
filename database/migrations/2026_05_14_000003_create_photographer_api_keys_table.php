<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API keys for Studio-plan photographers.
 *
 * Each row is a Bearer token scoped to one photographer. Tokens are
 * displayed exactly once (on creation) — we store the bcrypt hash plus
 * a 6-char prefix so admins/owners can recognise which key is which
 * without ever recovering the secret. This mirrors the GitHub PAT UX.
 *
 * Scopes are a CSV of permissions (e.g. "events:read,photos:read").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photographer_api_keys', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('photographer_id');
            $t->string('label');
            $t->string('token_prefix', 8);    // first 6 chars of plain token, for display
            $t->string('token_hash');         // bcrypt of full token
            $t->string('scopes')->default('events:read,photos:read');
            $t->timestamp('last_used_at')->nullable();
            $t->ipAddress('last_used_ip')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();

            $t->index(['photographer_id', 'revoked_at']);
            $t->index('token_prefix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photographer_api_keys');
    }
};
