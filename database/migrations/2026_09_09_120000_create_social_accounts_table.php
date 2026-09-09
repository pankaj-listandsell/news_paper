<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();

            // facebook | linkedin | x — one row per connected account.
            $table->string('platform')->unique();

            // What to call it in the admin panel, e.g. "Hauptstadt Report".
            $table->string('name')->nullable();

            // Tokens, page ids, organisation urns. Encrypted at rest: this
            // column holds enough to post as the site, so it never sits in
            // the database in the clear.
            $table->text('credentials')->nullable();

            // Facebook and LinkedIn tokens expire. Knowing when lets the admin
            // warn before posting quietly stops working.
            $table->timestamp('token_expires_at')->nullable();

            $table->boolean('is_active')->default(false);

            // Whether the scheduled run may post to this account on its own.
            $table->boolean('auto_post')->default(false);

            // Why the last attempt failed, so the admin can show it.
            $table->text('last_error')->nullable();
            $table->timestamp('last_posted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
