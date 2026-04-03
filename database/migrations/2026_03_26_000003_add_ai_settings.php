<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->string('ai_provider')->nullable()->default('minimax');
            $table->text('ai_api_key')->nullable();
            $table->string('ai_model')->nullable()->default('MiniMax-M2.7');
        });
    }

    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn(['ai_provider', 'ai_api_key', 'ai_model']);
        });
    }
};
