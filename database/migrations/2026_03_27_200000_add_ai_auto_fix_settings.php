<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_settings', function (Blueprint $table) {
            $table->boolean('is_ai_auto_fix_enabled')->default(true)->after('is_git_shallow_clone_enabled');
            $table->boolean('is_ai_push_to_git_enabled')->default(false)->after('is_ai_auto_fix_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('application_settings', function (Blueprint $table) {
            $table->dropColumn(['is_ai_auto_fix_enabled', 'is_ai_push_to_git_enabled']);
        });
    }
};