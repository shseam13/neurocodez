<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advance-request invoices.
 *
 * When set, the line items still describe the whole engagement — that is what
 * the client is agreeing to — but only this percentage of it is asked for now.
 * The remainder is invoiced on delivery.
 *
 * A percentage rather than an amount, so the figure survives an edit to the
 * line items: change the scope and the advance follows it, instead of quietly
 * becoming a different fraction of a different total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('advance_percent', 5, 2)->nullable()->after('tax');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('advance_percent');
        });
    }
};
