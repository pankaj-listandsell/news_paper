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
            $table->boolean('ai_rewrite')->default(false)->after('fetch_full_content');
            $table->string('ai_provider')->nullable()->after('ai_rewrite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            $table->dropColumn(['ai_rewrite', 'ai_provider']);
        });
    }
};
