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
        Schema::table('news_sources', function (Blueprint $table) {
            $table->boolean('ai_image')->default(false)->after('ai_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            $table->dropColumn('ai_image');
        });
    }
};
