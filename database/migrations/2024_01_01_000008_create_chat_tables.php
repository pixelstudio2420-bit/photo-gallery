<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('photographer_id');
            $table->unsignedInteger('event_id')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->enum('status', ['active','closed'])->default('active');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'photographer_id']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('conversation_id')->index();
            $table->enum('sender_type', ['user','photographer']);
            $table->unsignedInteger('sender_id');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
