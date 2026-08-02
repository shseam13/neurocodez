<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // Human-facing sequential number, e.g. INV-2026-042. Unique because
            // a duplicated invoice number is a real accounting problem.
            $table->string('number', 40)->unique();

            $table->date('issued_at');
            $table->date('due_at')->nullable();
            $table->enum('status', ['draft', 'sent', 'paid', 'void'])->default('draft');

            // All minor units.
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('total')->default(0);

            $table->char('currency', 3)->default('BDT');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('qty', 10, 2)->default(1);
            $table->bigInteger('unit_price')->default(0);
            $table->bigInteger('line_total')->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
