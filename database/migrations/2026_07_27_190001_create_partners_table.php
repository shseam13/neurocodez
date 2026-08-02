<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();

            // The DEFAULT rate for new projects only. Never read this to work
            // out what is owed — projects snapshot their own percentage so a
            // renegotiated rate cannot rewrite historical commissions.
            $table->decimal('default_commission_percent', 5, 2)->default(0);

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
