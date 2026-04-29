<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_admins', function (Blueprint $table) {
            $table->string('role', 20)->default('admin')->after('last_name');
            $table->json('permissions')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('permissions');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        // Upgrade existing admin(s) to superadmin
        DB::table('auth_admins')->update(['role' => 'superadmin', 'permissions' => null]);
    }

    public function down(): void
    {
        Schema::table('auth_admins', function (Blueprint $table) {
            $table->dropColumn(['role', 'permissions', 'is_active', 'last_login_at']);
        });
    }
};
