<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 9)->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();

            // Markdown is the source of truth; body_html is rendered once on
            // save. Rendering per request would burn CPU on every visit for
            // output that only changes when the author edits.
            $table->longText('body_markdown')->nullable();
            $table->longText('body_html')->nullable();

            $table->string('cover_disk', 32)->nullable();
            $table->string('cover_path')->nullable();
            $table->string('cover_alt')->nullable();

            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->unsignedBigInteger('views')->default(0);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Drives the public listing query: published posts, newest first.
            $table->index(['status', 'published_at']);
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('tags');
    }
};
