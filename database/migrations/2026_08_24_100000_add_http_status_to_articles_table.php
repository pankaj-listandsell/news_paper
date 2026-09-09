<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // What the article's detail page should answer with.
            // 200 = normal, 301 = moved to redirect_url, 304 = frozen (empty body).
            $table->unsignedSmallInteger('http_status')->default(200)->after('status');
            $table->string('redirect_url')->nullable()->after('http_status');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['http_status', 'redirect_url']);
        });
    }
};
