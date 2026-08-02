<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: the development database already gained this column via an
        // earlier migration that has since been folded away, while a fresh
        // install has not. Both must end up in the same place.
        if (Schema::hasColumn('projects', 'billed_to')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            /*
             * Who receives the invoice for this project.
             *
             * `client` — you bill the end client and the partner earns
             *   commission on what you collect.
             *
             * `partner` — the partner owns the client relationship and pays you
             *   an agreed net. `agreed_amount` is then YOUR share, so there is
             *   no commission to calculate: their cut never passes through your
             *   books at all.
             *
             * Per project, because one partner may work both ways.
             */
            $table->enum('billed_to', ['client', 'partner'])->default('client')->after('partner_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('projects', 'billed_to')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('billed_to');
        });
    }
};
