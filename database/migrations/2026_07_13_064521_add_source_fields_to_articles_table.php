<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->after('user_id')->index();
            $table->string('source_name')->nullable()->after('source_id');
            $table->string('source_url', 500)->nullable()->after('source_name');
            $table->unique('source_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique(['source_url']);
            $table->dropColumn(['source_id', 'source_name', 'source_url']);
        });
    }
};
