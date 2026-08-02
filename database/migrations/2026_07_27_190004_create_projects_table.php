<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Money is stored in MINOR UNITS (poisha) as a signed bigint.
            // Floats and decimals both invite rounding drift across repeated
            // partial payments; integers cannot drift.
            $table->bigInteger('agreed_amount')->default(0);
            $table->char('currency', 3)->default('BDT');

            // Snapshotted from the partner at creation, then owned by this
            // project. Renegotiating a partner's default rate must never
            // silently change what is owed on work already done.
            $table->decimal('commission_percent', 5, 2)->default(0);

            // 'collected' accrues commission only on money actually received,
            // which protects cash flow. 'agreed' accrues on the full value at
            // signing. Per-project because real deals differ.
            $table->enum('commission_basis', ['collected', 'agreed'])->default('collected');

            $table->foreignId('stage_set_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_stage_id')->nullable()->constrained('stages')->nullOnDelete();

            $table->enum('status', ['active', 'on_hold', 'delivered', 'cancelled'])->default('active');
            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deadline']);
            $table->index('client_id');
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
