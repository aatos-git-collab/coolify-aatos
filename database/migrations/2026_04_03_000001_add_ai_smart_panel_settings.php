<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            // AI Build Pack settings
            $table->boolean('ai_buildpack_enabled')->default(true);
            $table->boolean('ai_buildpack_auto_detect_docker')->default(true);
            $table->boolean('ai_buildpack_fallback_nixpacks')->default(true);

            // AI Auto-Fix settings
            $table->boolean('ai_autofix_enabled')->default(true);
            $table->integer('ai_autofix_max_retries')->default(5);
            $table->integer('ai_autofix_retry_delay')->default(10);

            // IP Whitelist settings
            $table->boolean('ip_whitelist_enabled')->default(false);
            $table->string('ip_whitelist_sources')->nullable();
        });

        // Create ai_healing_logs table
        if (!Schema::hasTable('ai_healing_logs')) {
            Schema::create('ai_healing_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
                $table->string('container_name')->nullable();
                $table->text('issue_detected')->nullable();
                $table->longText('ai_analysis')->nullable();
                $table->string('remediation_tried')->nullable();
                $table->string('result')->default('analyzed'); // analyzed, success, failed
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ai_buildpack_enabled',
                'ai_buildpack_auto_detect_docker',
                'ai_buildpack_fallback_nixpacks',
                'ai_autofix_enabled',
                'ai_autofix_max_retries',
                'ai_autofix_retry_delay',
                'ip_whitelist_enabled',
                'ip_whitelist_sources',
            ]);
        });

        Schema::dropIfExists('ai_healing_logs');
    }
};
