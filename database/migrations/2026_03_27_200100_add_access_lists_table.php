<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('ips'); // Array of IP addresses or CIDR blocks
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('application_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('access_list_id')->nullable()->after('is_ai_push_to_git_enabled');
            $table->foreign('access_list_id')->references('id')->on('access_lists')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('application_settings', function (Blueprint $table) {
            $table->dropForeign(['access_list_id']);
            $table->dropColumn('access_list_id');
        });

        Schema::dropIfExists('access_lists');
    }
};