<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row table. Company identity used across the app and, more
        // importantly, printed onto every invoice.
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Neuro Codez');
            $table->string('slogan')->nullable()->default('Connect . Create . Serve');
            $table->string('logo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            $table->string('invoice_prefix', 20)->default('INV');
            $table->unsignedInteger('invoice_next_number')->default(1);
            $table->char('currency', 3)->default('BDT');

            // Default only — each project still owns its own basis.
            $table->enum('default_commission_basis', ['collected', 'agreed'])->default('collected');

            $table->text('payment_details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
