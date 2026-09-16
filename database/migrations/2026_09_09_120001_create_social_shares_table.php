<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A first run of this migration on a MyISAM database died after
        // CREATE TABLE but before its indexes, leaving a half-built table
        // behind and the migration unrecorded, so a second run could only
        // fail with "table already exists". Clear that leftover away. It is
        // dropped only while empty: a table holding real rows is left alone,
        // and the create below then fails loudly instead.
        if (Schema::hasTable('social_shares') && DB::table('social_shares')->doesntExist()) {
            Schema::drop('social_shares');
        }

        Schema::create('social_shares', function (Blueprint $table) {
            $table->id();

            $table->foreignId('article_id')->constrained()->cascadeOnDelete();

            // Short on purpose. Both columns only ever hold a few fixed words,
            // and the indexes below must fit MyISAM's 1000-byte key limit —
            // two default VARCHAR(255) columns come to 1,530 bytes in utf8
            // and 2,040 in utf8mb4.
            $table->string('platform', 20);

            // pending | sent | failed | skipped
            //   skipped = deliberately never to be sent (e.g. the backlog that
            //   existed before auto-posting was switched on).
            $table->string('status', 20)->default('pending');

            // What the platform gave back, so the admin can link to the post.
            $table->string('remote_id')->nullable();
            $table->string('remote_url')->nullable();

            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            // The double-post lock. One row per article per platform means a
            // command that runs twice, or a button clicked twice, still only
            // ever results in a single post going out.
            $table->unique(['article_id', 'platform']);

            // The scheduled run asks "what is still waiting for this platform?"
            $table->index(['platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_shares');
    }
};
