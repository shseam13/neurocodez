<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();

            // Who introduced this client. This is the CLIENT-level link and it
            // only pre-fills new projects; projects.partner_id is what actually
            // decides commission. Keeping both levels is what makes "commission
            // on the first project only" and "commission on everything" both
            // expressible as data rather than a code branch.
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
