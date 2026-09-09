<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_shares', function (Blueprint $table) {
            $table->id();

            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('platform');

            // pending | sent | failed | skipped
            //   skipped = deliberately never to be sent (e.g. the backlog that
            //   existed before auto-posting was switched on).
            $table->string('status')->default('pending');

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
