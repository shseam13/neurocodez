<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Deliberately a SEPARATE table from `projects`, not a flag on it.
         *
         * `projects` carries client names, agreed amounts and partner
         * commission terms. An `is_public` boolean there would mean one missing
         * scope or one mis-clicked toggle publishes your financials. A curated
         * table with its own authored copy makes that leak structurally
         * impossible rather than a matter of discipline.
         */
        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            $table->longText('body_markdown')->nullable();
            $table->longText('body_html')->nullable();

            // What the public is told the client is called — which may be
            // nothing at all. Never rendered from clients.name.
            $table->string('client_display_name')->nullable();

            // Internal convenience link only. Nothing on the public side may
            // traverse this relation.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            $table->string('cover_disk', 32)->nullable();
            $table->string('cover_path')->nullable();
            $table->string('cover_alt')->nullable();

            $table->json('tech')->nullable();
            $table->string('live_url')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_featured')->default(false);

            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'position']);
        });

        Schema::create('portfolio_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_item_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['portfolio_item_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_images');
        Schema::dropIfExists('portfolio_items');
    }
};
