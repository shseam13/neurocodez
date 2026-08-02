<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Extra work billed on top of the original scope.
         *
         * Deliberately NOT done by editing projects.agreed_amount. That column
         * records what was originally agreed and must stay that way: if a
         * client later disputes the bill, "we agreed 50,000" needs to still be
         * visible alongside every addition, each with its own date and reason.
         * Mutating the figure would also silently re-base commission on work
         * the partner may have had nothing to do with.
         */
        Schema::create('project_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Minor units, like every other money column.
            $table->bigInteger('amount')->default(0);

            $table->enum('kind', ['extra', 'revision', 'maintenance', 'retainer_cycle'])->default('extra');
            $table->enum('status', ['quoted', 'approved', 'rejected', 'cancelled'])->default('quoted');

            /*
             * Whether this charge earns the partner commission.
             *
             * Defaults to true: most referral deals cover the whole client
             * relationship. Turned off for work the partner had no hand in.
             */
            $table->boolean('commission_applies')->default(true);

            $table->date('occurred_at');
            $table->timestamp('approved_at')->nullable();

            // Set only for retainer cycles, and unique per period so a re-run of
            // the generator cannot double-bill a client.
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->unique(['project_id', 'kind', 'period_start'], 'charges_retainer_period_unique');
        });

        Schema::table('projects', function (Blueprint $table) {
            /*
             * Follow-up work links back to the project it came from.
             *
             * A maintenance engagement six months later gets its own stages,
             * its own invoices and its own commission terms, while staying
             * traceable to the original build.
             */
            $table->foreignId('parent_id')->nullable()->after('client_id')
                ->constrained('projects')->nullOnDelete();

            // Recurring maintenance. Optional — most projects are not retainers.
            $table->boolean('is_retainer')->default(false)->after('commission_basis');
            $table->bigInteger('retainer_amount')->nullable()->after('is_retainer');
            // Capped at 28 so every month has the day, including February.
            $table->unsignedTinyInteger('retainer_day')->default(1)->after('retainer_amount');
            $table->date('retainer_starts_on')->nullable()->after('retainer_day');
            $table->date('retainer_ends_on')->nullable()->after('retainer_starts_on');

            $table->index(['is_retainer', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn([
                'parent_id', 'is_retainer', 'retainer_amount',
                'retainer_day', 'retainer_starts_on', 'retainer_ends_on',
            ]);
        });

        Schema::dropIfExists('project_charges');
    }
};
