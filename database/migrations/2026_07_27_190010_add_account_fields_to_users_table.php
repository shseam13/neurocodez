<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // WHICH APPLICATION this account belongs to. Structural and
            // effectively permanent; it drives the route group, the layout and
            // the model global scopes.
            //
            // Deliberately separate from roles. Clients and partners are not
            // lesser admins — modelling them as low-privilege staff is how data
            // leaks between portals. Roles (super_admin/admin) apply only to
            // type = staff.
            $table->enum('type', ['staff', 'client', 'partner'])->default('staff')->after('email');

            // Exactly one of these is set for portal accounts, and neither for
            // staff. Enforced in the User model.
            $table->foreignId('client_id')->nullable()->after('type')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->after('client_id')->constrained()->cascadeOnDelete();

            $table->enum('theme_pref', ['light', 'dark'])->nullable()->after('remember_token');

            $table->timestamp('invited_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->softDeletes();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['partner_id']);
            $table->dropColumn([
                'type', 'client_id', 'partner_id', 'theme_pref',
                'invited_at', 'last_login_at', 'is_active', 'deleted_at',
            ]);
        });
    }
};
