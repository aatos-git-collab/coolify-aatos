<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swarm_domain_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('path_prefix')->nullable()->default('/');
            $table->foreignId('application_id')->constrained('applications')->onDelete('cascade');
            $table->boolean('is_enabled')->default(true);
            $table->string('scheme')->default('http');
            $table->integer('port')->default(80);

            // Rate Limiting
            $table->integer('rate_limit_average')->nullable();
            $table->integer('rate_limit_burst')->nullable();
            $table->string('rate_limit_period')->default('1m');

            // Security Headers
            $table->boolean('enable_security_headers')->default(false);
            $table->boolean('header_xss_filter')->default(true);
            $table->boolean('header_content_type_nosniff')->default(true);
            $table->boolean('header_frame_deny')->default(true);
            $table->integer('header_sts_seconds')->nullable();
            $table->boolean('header_sts_include_subdomains')->default(false);

            // IP Whitelist
            $table->boolean('ip_whitelist_enabled')->default(false);
            $table->text('ip_whitelist_sources')->nullable();

            $table->timestamps();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('swarm_service_identifier')->nullable()->unique()->after('swarm_placement_constraints');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('swarm_service_identifier');
        });

        Schema::dropIfExists('swarm_domain_mappings');
    }
};