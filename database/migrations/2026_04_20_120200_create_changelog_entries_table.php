<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_entries', function (Blueprint $t) {
            $t->id();
            $t->string('version', 30)->index();         // 1.2.0 / 2026-04 / whatever
            $t->date('released_on');
            $t->string('title', 200);
            $t->string('type', 20)->default('feature'); // feature | improvement | fix | security | deprecation
            $t->text('body')->nullable();               // markdown
            $t->string('audience', 20)->default('all'); // all | admin | photographer | public
            $t->boolean('is_published')->default(true)->index();
            $t->timestamps();

            $t->index(['is_published', 'released_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_entries');
    }
};
