<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kubernetes_pipelines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('environment_id')->constrained();
            $table->foreignId('kubernetes_cluster_id')->nullable()
                ->constrained('kubernetes_clusters')->nullOnDelete();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('git_provider')->nullable();
            $table->string('git_repository')->nullable();
            $table->string('git_branch')->default('main');
            $table->string('buildstrategy')->default('dockerfile');
            $table->boolean('reviewapps_enabled')->default(true);
            $table->json('phases')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kubernetes_pipelines');
    }
};
