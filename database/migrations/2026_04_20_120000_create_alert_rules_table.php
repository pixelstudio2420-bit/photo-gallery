<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('description', 255)->nullable();

            // Metric identifier (see AlertEvaluatorService::metrics())
            $t->string('metric', 60)->index();
            $t->string('operator', 4)->default('>');       // >, >=, <, <=, =
            $t->decimal('threshold', 16, 4);

            // Notification channels JSON: ['email','line','push','admin']
            $t->json('channels')->nullable();
            $t->string('severity', 16)->default('warn');   // info | warn | critical
            $t->unsignedSmallInteger('cooldown_minutes')->default(60);

            // Runtime state
            $t->timestamp('last_triggered_at')->nullable();
            $t->decimal('last_value', 16, 4)->nullable();
            $t->timestamp('last_checked_at')->nullable();

            $t->boolean('is_active')->default(true)->index();
            $t->timestamps();
        });

        Schema::create('alert_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rule_id')->constrained('alert_rules')->cascadeOnDelete();
            $t->timestamp('triggered_at')->useCurrent();
            $t->decimal('value', 16, 4);
            $t->string('severity', 16);
            $t->json('channels_sent')->nullable();   // which channels successfully notified
            $t->text('note')->nullable();
            $t->index(['rule_id', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_events');
        Schema::dropIfExists('alert_rules');
    }
};
