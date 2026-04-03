<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->boolean('auto_scaling_enabled')->default(false)->nullable();
            $table->integer('auto_scaling_min_replicas')->default(1)->nullable();
            $table->integer('auto_scaling_max_replicas')->default(5)->nullable();
            $table->integer('auto_scaling_target_cpu')->default(70)->nullable();
            $table->integer('auto_scaling_target_memory')->default(80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'auto_scaling_enabled',
                'auto_scaling_min_replicas',
                'auto_scaling_max_replicas',
                'auto_scaling_target_cpu',
                'auto_scaling_target_memory',
            ]);
        });
    }
};