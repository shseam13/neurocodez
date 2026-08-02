<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ChargeKind;
use App\Enums\ChargeStatus;
use App\Models\Project;
use App\Models\ProjectCharge;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateRetainerCharges extends Command
{
    protected $signature = 'retainers:generate
                            {--date= : Generate as if it were this date (YYYY-MM-DD)}
                            {--dry-run : Show what would be created without writing}';

    protected $description = 'Create this month\'s charge for every active retainer project';

    public function handle(): int
    {
        $today = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $dryRun = (bool) $this->option('dry-run');

        $periodStart = $today->copy()->startOfMonth();
        $periodEnd = $today->copy()->endOfMonth();

        $created = 0;
        $skipped = 0;

        foreach (Project::query()->retainers()->with('client')->get() as $project) {
            if (! $project->retainerIsActive($today)) {
                $skipped++;

                continue;
            }

            // Wait until the billing day has arrived this month.
            if ($today->day < $project->retainer_day) {
                $skipped++;

                continue;
            }

            /*
             * Idempotent by (project, kind, period_start), enforced by a unique
             * index as well as this check. Running the command twice — or the
             * scheduler firing twice after a restart — must never double-bill
             * a client.
             */
            $exists = ProjectCharge::query()
                ->withTrashed()
                ->where('project_id', $project->id)
                ->where('kind', ChargeKind::RetainerCycle)
                ->whereDate('period_start', $periodStart)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $label = $periodStart->format('F Y');

            if ($dryRun) {
                $this->line("  would create: {$project->title} ({$project->client->name}) — {$label} — ".
                    $project->retainer_amount->formatWithCurrency());
                $created++;

                continue;
            }

            ProjectCharge::create([
                'project_id' => $project->id,
                'title' => "Maintenance retainer — {$label}",
                'amount' => $project->retainer_amount,
                'kind' => ChargeKind::RetainerCycle,
                // Auto-generated charges are approved: the retainer agreement
                // is the approval. A quote would need chasing every month.
                'status' => ChargeStatus::Approved,
                'commission_applies' => true,
                'occurred_at' => $periodStart,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);

            $created++;
        }

        $verb = $dryRun ? 'would be created' : 'created';
        $this->components->info("Retainer charges: {$created} {$verb}, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
