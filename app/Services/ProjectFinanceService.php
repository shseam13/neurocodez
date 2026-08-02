<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Support\Money;

/**
 * The single source of truth for what a project has earned and what it is owed.
 *
 * Every figure here is DERIVED, never stored. Caching a running total in a
 * column means a missed update leaves the dashboard disagreeing with the
 * invoice — and on money the user is chasing, that is worse than a slow query.
 */
class ProjectFinanceService
{
    /** What was agreed at the start. Never changes as extra work is added. */
    public function originalScope(Project $project): Money
    {
        return $project->agreed_amount;
    }

    /**
     * Approved extra work only.
     *
     * Quoted charges are excluded on purpose: a proposal sitting in the system
     * must never make a client appear to owe money they have not agreed to.
     */
    public function approvedExtras(Project $project): Money
    {
        $minor = (int) $project->charges()->approved()->sum('amount');

        return Money::ofMinor($minor, $project->currency);
    }

    /** Original scope plus everything since agreed — the real contract value. */
    public function contractValue(Project $project): Money
    {
        return $project->agreed_amount->plus($this->approvedExtras($project));
    }

    /** Total actually received. Negative rows (refunds/corrections) net off. */
    public function totalPaid(Project $project): Money
    {
        $minor = (int) $project->payments()->sum('amount');

        return Money::ofMinor($minor, $project->currency);
    }

    /** Everything owed, including approved extras, minus what has come in. */
    public function amountDue(Project $project): Money
    {
        return $this->contractValue($project)->minus($this->totalPaid($project));
    }

    public function isFullyPaid(Project $project): bool
    {
        return ! $this->amountDue($project)->isPositive();
    }

    /** How much of the contract value has been collected, 0-100. */
    public function paidPercent(Project $project): float
    {
        $value = $this->contractValue($project)->minor;

        if ($value <= 0) {
            return 0.0;
        }

        return round(min(100, max(0, $this->totalPaid($project)->minor / $value * 100)), 1);
    }

    /**
     * Everything the UI needs for one project, in a single pass.
     *
     * @return array{
     *     agreed: Money, extras: Money, contract: Money, paid: Money, due: Money,
     *     paid_percent: float, fully_paid: bool, overdue: bool
     * }
     */
    public function summarise(Project $project): array
    {
        $extras = $this->approvedExtras($project);
        $contract = $project->agreed_amount->plus($extras);
        $paid = $this->totalPaid($project);
        $due = $contract->minus($paid);

        return [
            'agreed' => $project->agreed_amount,
            'extras' => $extras,
            'contract' => $contract,
            'paid' => $paid,
            'due' => $due,
            'paid_percent' => $this->paidPercent($project),
            'fully_paid' => ! $due->isPositive(),
            'overdue' => $project->isOverdue(),
        ];
    }

    /**
     * Company-wide totals for the dashboard.
     *
     * @return array{receivable: Money, collected: Money, outstanding: Money, overdue_count: int}
     */
    public function companyTotals(string $currency = 'BDT'): array
    {
        $agreed = (int) Project::query()->open()->sum('agreed_amount');

        $extras = (int) Project::query()
            ->open()
            ->join('project_charges', 'project_charges.project_id', '=', 'projects.id')
            ->whereNull('project_charges.deleted_at')
            ->where('project_charges.status', 'approved')
            ->sum('project_charges.amount');

        $collected = (int) Project::query()
            ->open()
            ->join('payments', 'payments.project_id', '=', 'projects.id')
            ->whereNull('payments.deleted_at')
            ->sum('payments.amount');

        $receivable = $agreed + $extras;

        return [
            'receivable' => Money::ofMinor($receivable, $currency),
            'collected' => Money::ofMinor($collected, $currency),
            'outstanding' => Money::ofMinor(max(0, $receivable - $collected), $currency),
            'overdue_count' => Project::query()->overdue()->count(),
        ];
    }
}
