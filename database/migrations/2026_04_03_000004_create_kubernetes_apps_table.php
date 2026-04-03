<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kubernetes_apps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('kubernetes_pipeline_id')->nullable()
                ->constrained('kubernetes_pipelines')->nullOnDelete();
            $table->string('name');
            $table->string('namespace')->default('default');
            $table->string('image_repository')->nullable();
            $table->string('image_tag')->default('latest');
            $table->integer('container_port')->default(80);
            $table->integer('replicas')->default(1);
            $table->string('pod_size')->default('small');
            $table->string('buildstrategy')->default('dockerfile');
            $table->string('dockerfile_path')->default('Dockerfile');
            $table->text('build_commands')->nullable();
            $table->boolean('autoscale_enabled')->default(false);
            $table->integer('autoscale_min')->default(1);
            $table->integer('autoscale_max')->default(5);
            $table->integer('autoscale_cpu_threshold')->default(70);
            $table->integer('autoscale_memory_threshold')->default(80);
            $table->boolean('healthcheck_enabled')->default(true);
            $table->string('healthcheck_path')->default('/');
            $table->integer('healthcheck_port')->default(80);
            $table->string('ingress_host')->nullable();
            $table->string('ingress_path')->default('/');
            $table->boolean('ingress_tls')->default(false);
            $table->json('env_vars')->nullable();
            $table->json('secrets')->nullable();
            $table->string('status')->default('pending');
            $table->string('kubernetes_resource_version')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kubernetes_apps');
    }
};
