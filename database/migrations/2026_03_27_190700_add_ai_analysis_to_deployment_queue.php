<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_deployment_queues', function (Blueprint $table) {
            $table->text('ai_analysis')->nullable()->after('logs');
        });
    }

    public function down(): void
    {
        Schema::table('application_deployment_queues', function (Blueprint $table) {
            $table->dropColumn('ai_analysis');
        });
    }
};