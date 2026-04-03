<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->boolean('ai_monitor_enabled')->default(false);
            $table->integer('ai_monitor_interval')->default(5);
            $table->boolean('ai_auto_heal_enabled')->default(false);
            $table->integer('ai_monitor_log_lines')->default(500);
        });

        Schema::create('ai_healing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained('servers')->onDelete('cascade');
            $table->string('container_id')->nullable();
            $table->string('container_name')->nullable();
            $table->text('issue_detected')->nullable();
            $table->text('remediation_tried')->nullable();
            $table->string('result')->nullable(); // success, failed, skipped
            $table->text('ai_analysis')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_healing_logs');

        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ai_monitor_enabled',
                'ai_monitor_interval',
                'ai_auto_heal_enabled',
                'ai_monitor_log_lines',
            ]);
        });
    }
};
