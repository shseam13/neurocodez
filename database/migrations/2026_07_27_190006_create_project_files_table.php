<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // The disk is recorded per file, not assumed. Files uploaded on
            // Render live in R2 (the free-tier filesystem is ephemeral and
            // destroys uploads on redeploy); files uploaded after a move to
            // cPanel live on local disk. Both must keep resolving.
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime', 128)->nullable();

            // Deliverables the client is allowed to see. Internal working files
            // stay hidden from the portal.
            $table->boolean('client_visible')->default(false);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'client_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_files');
    }
};
