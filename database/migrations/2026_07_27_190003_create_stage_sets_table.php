<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_set_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            // Optional friendlier name shown to clients, e.g. internal
            // "Code review" surfacing as "In progress".
            $table->string('client_label')->nullable();

            $table->string('slug');
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('color', 9)->nullable();

            // Terminal stages end the pipeline (Delivered, Cancelled).
            $table->boolean('is_terminal')->default(false);

            // Per-audience visibility. A stage hidden from an audience collapses
            // into the previous visible one for them, so outside viewers see
            // smooth progress instead of gaps or internal detail.
            $table->boolean('visible_to_client')->default(true);
            $table->boolean('visible_to_partner')->default(false);

            $table->timestamps();
            // Soft deletes only: a hard delete would orphan the stage history of
            // every project that ever sat on this stage.
            $table->softDeletes();

            $table->unique(['stage_set_id', 'slug']);
            $table->index(['stage_set_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
        Schema::dropIfExists('stage_sets');
    }
};
