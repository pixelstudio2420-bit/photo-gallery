<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photographer team members.
 *
 * The owner photographer (the one paying for the subscription) can invite
 * other users to act on the same photographer profile — upload photos,
 * manage events, see orders. Cap is per-plan via SubscriptionPlan.max_team_seats:
 *
 *   Free / Starter / Pro = 1 (just the owner)
 *   Business             = 3
 *   Studio               = 10
 *
 * Member rows reference an existing User. Pending invites have user_id NULL
 * + invite_email + invite_token; once the invitee logs in / accepts the
 * invite, the row gets stamped with their user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photographer_team_members', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_user_id');     // photographer who owns the seat
            $t->unsignedBigInteger('user_id')->nullable(); // member's User row (NULL when pending)
            $t->string('invite_email')->nullable();
            $t->string('invite_token', 64)->unique()->nullable();
            $t->timestamp('invited_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->enum('role', ['admin', 'editor', 'viewer'])->default('editor');
            $t->enum('status', ['pending', 'active', 'revoked'])->default('pending');
            $t->timestamps();

            $t->index(['owner_user_id', 'status']);
            $t->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photographer_team_members');
    }
};
