<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A local mirror of the YouTube channel, filled by `youtube:sync` from
         * the channel's public RSS feed.
         *
         * Pages read this table and never call YouTube during a request, so the
         * site does not slow down or break when YouTube is unreachable — and it
         * still renders offline in the PWA.
         */
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('youtube_id', 32)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('thumbnail_url')->nullable();

            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);

            // Hand-added videos survive a sync. RSS only returns the latest ~15
            // uploads, so anything older has to be pinned manually and must not
            // be wiped when the feed no longer mentions it.
            $table->boolean('is_manual')->default(false);

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'published_at']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');

            $table->string('source', 40)->default('contact_form');
            $table->enum('status', ['new', 'contacted', 'converted', 'spam'])->default('new');

            // Set when a lead becomes a real client — closes the loop between
            // the website and the system.
            $table->foreignId('converted_client_id')->nullable()->constrained('clients')->nullOnDelete();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('videos');
    }
};
