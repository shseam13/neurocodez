<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every permission in the system, named up front.
 *
 * v1 grants ALL of these to the `admin` role, so staff currently have full
 * access. They are declared now anyway: the enforcement layer is what is
 * expensive to retrofit, not the rules. When you decide to restrict something,
 * it is a seeder change plus a checkbox — no controller edits.
 *
 * The rule that keeps that promise: every check goes through a Gate or Policy.
 * Never `if ($user->role === 'admin')` in a controller or Blade file.
 */
enum Permission: string
{
    // Commission terms are usually the most sensitive data here — your deal
    // with each partner. Expect this to be the first thing you lock down.
    case ViewCommissions = 'view_commissions';
    case ManageCommissions = 'manage_commissions';

    case ViewCompanyFinances = 'view_company_finances';

    case ManageClients = 'manage_clients';
    case ManagePartners = 'manage_partners';
    case ManageProjects = 'manage_projects';
    case ManagePayments = 'manage_payments';
    case ManageInvoices = 'manage_invoices';
    case ManageStageSets = 'manage_stage_sets';

    // Public-site content: blog posts, portfolio case studies, videos.
    case ManageContent = 'manage_content';

    // Website enquiries, and converting one into a client.
    case ManageLeads = 'manage_leads';

    case ManageUsers = 'manage_users';
    case ManageSettings = 'manage_settings';
    case DeleteRecords = 'delete_records';
    case ViewAuditLog = 'view_audit_log';
    case ImpersonateUsers = 'impersonate_users';

    public function label(): string
    {
        return match ($this) {
            self::ViewCommissions => 'View partner commissions',
            self::ManageCommissions => 'Record commission payouts',
            self::ViewCompanyFinances => 'View company-wide finances',
            self::ManageClients => 'Manage clients',
            self::ManagePartners => 'Manage partners',
            self::ManageProjects => 'Manage projects and files',
            self::ManagePayments => 'Record client payments',
            self::ManageInvoices => 'Create and send invoices',
            self::ManageStageSets => 'Manage stage sets',
            self::ManageContent => 'Write posts and manage the public site',
            self::ManageLeads => 'Handle website enquiries',
            self::ManageUsers => 'Invite and manage staff',
            self::ManageSettings => 'Change company settings',
            self::DeleteRecords => 'Delete records',
            self::ViewAuditLog => 'View the audit log',
            self::ImpersonateUsers => 'View the app as another user',
        };
    }

    /** @return array<int, string> */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Permissions reserved for the owner even once staff rules tighten.
     *
     * Super admin passes every check via Gate::before regardless; this list is
     * what the UI should refuse to hand to a plain admin.
     *
     * @return array<int, self>
     */
    public static function ownerOnly(): array
    {
        return [self::ManageUsers, self::ManageSettings, self::ImpersonateUsers, self::DeleteRecords];
    }
}
