<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommissionBasis;
use App\Models\Project;
use App\Models\Partner;
use App\Support\Money;

/**
 * What is owed to partners, and what has actually been paid out.
 *
 * The rate always comes from `projects.commission_percent` — the value
 * snapshotted when the project was created — never from the partner's current
 * default. Reading the live default would silently rewrite what is owed on
 * historical work the moment a rate is renegotiated.
 */
class CommissionService
{
    public function __construct(
        private readonly ProjectFinanceService $finance,
    ) {}

    /** Original scope plus approved extras that carry commission. */
    public function commissionableValue(Project $project): Money
    {
        $extras = (int) $project->charges()->commissionable()->sum('amount');

        return $project->agreed_amount->plus(Money::ofMinor($extras, $project->currency));
    }

    /**
     * The amount the percentage applies to.
     *
     * `agreed` — the commissionable contract value, owed from signing.
     *
     * `collected` — money actually received, apportioned pro-rata to the
     * commissionable share of the contract. Payments are not tagged to
     * individual line items, so when some extras are excluded from commission
     * there is no way to know which part of a payment settled which line.
     * Pro-rata is the defensible answer: if 80% of the contract earns
     * commission, 80% of each payment counts toward it. When every charge is
     * commissionable (the default) the ratio is exactly 1 and nothing is
     * apportioned at all.
     */
    public function base(Project $project): Money
    {
        $commissionable = $this->commissionableValue($project);

        if ($project->commission_basis === CommissionBasis::Agreed) {
            return $commissionable;
        }

        $contract = $this->finance->contractValue($project);
        $paid = $this->finance->totalPaid($project);

        if ($contract->minor <= 0 || $paid->minor <= 0) {
            return Money::zero($project->currency);
        }

        // Fast path — and the common one. Avoids any rounding at all.
        if ($commissionable->minor === $contract->minor) {
            return $paid;
        }

        /*
         * Ratio held as a float deliberately. Multiplying two minor-unit
         * integers would overflow 64 bits on large contracts; the ratio is
         * between 0 and 1, so float64 carries it with far more precision than a
         * poisha, and the single round() below bounds the error at 1 poisha.
         */
        $ratio = $commissionable->minor / $contract->minor;

        return Money::ofMinor(
            (int) round($paid->minor * $ratio, 0, PHP_ROUND_HALF_UP),
            $project->currency,
        );
    }

    public function owed(Project $project): Money
    {
        // Covers both "no partner" and "the partner bills the client and pays
        // me a net" — in the latter their cut never passes through our books.
        if (! $project->earnsCommission()) {
            return Money::zero($project->currency);
        }

        return $this->base($project)->percent((float) $project->commission_percent);
    }

    public function paidOut(Project $project): Money
    {
        $minor = (int) $project->commissionPayouts()->sum('amount');

        return Money::ofMinor($minor, $project->currency);
    }

    /** Never negative: over-paying a partner does not create a debt back to you. */
    public function due(Project $project): Money
    {
        return $this->owed($project)->minus($this->paidOut($project))->floorAtZero();
    }

    /**
     * @return array{
     *     applies: bool, percent: float, basis: CommissionBasis,
     *     base: Money, owed: Money, paid: Money, due: Money,
     *     excluded: Money
     * }
     */
    public function summarise(Project $project): array
    {
        $owed = $this->owed($project);
        $paid = $this->paidOut($project);

        $contract = $this->finance->contractValue($project);
        $commissionable = $this->commissionableValue($project);

        return [
            'applies' => $project->earnsCommission(),
            'billed_to' => $project->billed_to ?? \App\Enums\BillingTarget::Client,
            'percent' => (float) $project->commission_percent,
            'basis' => $project->commission_basis,
            'base' => $this->base($project),
            'owed' => $owed,
            'paid' => $paid,
            'due' => $owed->minus($paid)->floorAtZero(),
            // Extra work explicitly excluded from commission — shown so the
            // figure can be explained to a partner who queries it.
            'excluded' => $contract->minus($commissionable),
        ];
    }

    /**
     * Everything outstanding for one partner, project by project.
     *
     * @return array{total_owed: Money, total_paid: Money, total_due: Money, projects: array<int, array>}
     */
    public function summarisePartner(Partner $partner, string $currency = 'BDT'): array
    {
        $owed = Money::zero($currency);
        $paid = Money::zero($currency);
        $rows = [];

        $projects = $partner->projects()->with('client')->get();

        foreach ($projects as $project) {
            $line = $this->summarise($project);
            $owed = $owed->plus($line['owed']);
            $paid = $paid->plus($line['paid']);

            $rows[] = ['project' => $project] + $line;
        }

        return [
            'total_owed' => $owed,
            'total_paid' => $paid,
            'total_due' => $owed->minus($paid)->floorAtZero(),
            'projects' => $rows,
        ];
    }

    /**
     * Total commission still payable across every partner — the
     * "Commissions payable" figure on the dashboard.
     */
    public function totalPayable(string $currency = 'BDT'): Money
    {
        $total = Money::zero($currency);

        Project::query()
            ->referred()
            ->with('commissionPayouts')
            ->chunk(200, function ($projects) use (&$total) {
                foreach ($projects as $project) {
                    $total = $total->plus($this->due($project));
                }
            });

        return $total;
    }
}
