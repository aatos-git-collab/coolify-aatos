<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kubernetes_addons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('kubernetes_cluster_id')->constrained()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('namespace')->default('kubero-addons');
            $table->string('version')->default('latest');
            $table->string('size')->default('small');
            $table->integer('storage_gb')->default(5);
            $table->boolean('high_availability')->default(false);
            $table->string('database_name')->nullable();
            $table->string('username')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kubernetes_addons');
    }
};
