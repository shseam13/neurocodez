<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_stage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();

            // The stage name AS IT WAS when the project entered it. Stage sets
            // get renamed and reordered after projects are already using them;
            // without this snapshot a rename silently rewrites history and a
            // removal leaves blanks in the timeline.
            $table->string('stage_name_snapshot');

            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'entered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_stage_logs');
    }
};
