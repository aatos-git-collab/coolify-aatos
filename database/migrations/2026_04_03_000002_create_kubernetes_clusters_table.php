<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kubernetes_clusters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('kubeconfig'); // encrypted
            $table->string('api_server_url');
            $table->string('ca_data')->nullable();
            $table->string('token')->nullable();
            $table->string('default_namespace')->default('default');
            $table->string('version')->nullable();
            $table->string('distribution')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreignId('team_id')->nullable()->constrained();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kubernetes_clusters');
    }
};
