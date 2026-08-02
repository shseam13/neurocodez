<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Money IN — what the client has actually paid.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // Minor units. Signed, so a correction or refund can be recorded as
            // a negative row rather than by editing history.
            $table->bigInteger('amount');

            $table->date('paid_at');
            $table->enum('method', ['bkash', 'nagad', 'rocket', 'bank', 'cash', 'other'])->default('bkash');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'paid_at']);
        });

        // Money OUT — what has actually been paid to a partner.
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();

            $table->bigInteger('amount');
            $table->date('paid_at');
            $table->enum('method', ['bkash', 'nagad', 'rocket', 'bank', 'cash', 'other'])->default('bkash');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['partner_id', 'paid_at']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('payments');
    }
};
