<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_export_requests', function (Blueprint $t) {
            $t->id();
            // auth_users.id is unsignedInteger — match its type to satisfy FK
            $t->unsignedInteger('user_id');
            $t->foreign('user_id')->references('id')->on('auth_users')->cascadeOnDelete();
            $t->string('request_type', 20)->default('export'); // export | delete
            $t->string('status', 20)->default('pending');       // pending | processing | ready | rejected | cancelled
            $t->text('reason')->nullable();                     // user reason for request (optional)
            $t->text('admin_note')->nullable();                 // admin note when resolving
            $t->string('file_path', 512)->nullable();           // relative storage path of generated export
            $t->string('file_disk', 20)->default('local');
            $t->unsignedBigInteger('file_size_bytes')->nullable();
            $t->string('download_token', 64)->nullable()->unique(); // one-time download token for the user
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->unsignedBigInteger('processed_by')->nullable(); // admin id
            $t->timestamps();

            $t->index(['user_id', 'status']);
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_export_requests');
    }
};
