<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\BillingTarget;
use App\Enums\CommissionBasis;
use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id', 'parent_id', 'partner_id', 'title', 'description',
        'agreed_amount', 'currency', 'commission_percent', 'commission_basis', 'billed_to',
        'stage_set_id', 'current_stage_id',
        'status', 'start_date', 'deadline', 'delivered_at',
        'is_retainer', 'retainer_amount', 'retainer_day',
        'retainer_starts_on', 'retainer_ends_on',
    ];

    protected function casts(): array
    {
        return [
            /*
             * Foreign keys cast to integer.
             *
             * A form posts "1", the database returns 1, and until the model is
             * reloaded the attribute keeps whatever type it was assigned. Any
             * strict comparison between the two then fails — StageService's
             * "belongs to a different stage set" guard threw on every project
             * created through the form, while every test passed because tests
             * assign $set->id, which is already an int.
             */
            'client_id' => 'integer',
            'partner_id' => 'integer',
            'stage_set_id' => 'integer',
            'current_stage_id' => 'integer',
            'parent_id' => 'integer',

            'agreed_amount' => MoneyCast::class,
            'commission_percent' => 'decimal:2',
            'commission_basis' => CommissionBasis::class,
            'billed_to' => BillingTarget::class,
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'deadline' => 'date',
            'delivered_at' => 'datetime',
            'is_retainer' => 'boolean',
            'retainer_amount' => MoneyCast::class,
            'retainer_day' => 'integer',
            'retainer_starts_on' => 'date',
            'retainer_ends_on' => 'date',
        ];
    }

    // ------------------------------------------------------------ relations

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function stageSet(): BelongsTo
    {
        return $this->belongsTo(StageSet::class);
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'current_stage_id');
    }

    public function stageLogs(): HasMany
    {
        return $this->hasMany(ProjectStageLog::class)->orderBy('entered_at');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function commissionPayouts(): HasMany
    {
        return $this->hasMany(CommissionPayout::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(ProjectCharge::class)->orderByDesc('occurred_at');
    }

    /** The project this one followed on from, e.g. the original build. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Maintenance engagements and later phases spun off this project. */
    public function followUps(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->latest();
    }

    // --------------------------------------------------------------- scopes

    /*
     * Every column below is table-qualified on purpose.
     *
     * These scopes are used inside joins — `project_charges` and `payments`
     * both carry a `status` column of their own, and an unqualified one throws
     * "Column 'status' in where clause is ambiguous" at runtime. Qualifying
     * costs nothing when there is no join and prevents the failure when there is.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('projects.status', [ProjectStatus::Active, ProjectStatus::OnHold]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('projects.deadline')
            ->whereDate('projects.deadline', '<', now());
    }

    public function scopeReferred(Builder $query): Builder
    {
        return $query->whereNotNull('projects.partner_id');
    }

    // --------------------------------------------------------------- booted

    protected static function booted(): void
    {
        /*
         * Snapshot the commission rate at creation.
         *
         * This is the single most important rule in the money model. The rate
         * lives on the PROJECT, copied from the partner once. If a partner
         * later renegotiates their default, every project already agreed keeps
         * the terms it was agreed under — and the same partner can hold a
         * different deal on a different project, which is how real referral
         * arrangements actually work.
         */
        static::creating(function (self $project) {
            if ($project->partner_id && blank($project->commission_percent)) {
                $project->commission_percent = Partner::find($project->partner_id)
                    ?->default_commission_percent ?? 0;
            }

            if (blank($project->commission_percent)) {
                $project->commission_percent = 0;
            }
        });
    }

    /** Is there a partner attached at all, whatever the billing arrangement? */
    public function hasPartner(): bool
    {
        return $this->partner_id !== null;
    }

    /**
     * Does this project generate commission?
     *
     * Requires a partner, a non-zero rate, AND that the CLIENT is the one being
     * billed. When the partner pays you a net amount their cut never enters
     * your books, so charging commission on top would be paying it twice.
     */
    public function earnsCommission(): bool
    {
        return $this->partner_id !== null
            && (float) $this->commission_percent > 0
            && ($this->billed_to ?? BillingTarget::Client)->earnsCommission();
    }

    public function isBilledToPartner(): bool
    {
        return ($this->billed_to ?? BillingTarget::Client) === BillingTarget::Partner;
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->deadline !== null
            && $this->deadline->isPast();
    }

    public function scopeRetainers(Builder $query): Builder
    {
        return $query->where('projects.is_retainer', true);
    }

    /**
     * Is this retainer currently billing?
     *
     * Checks the window as well as the flag — a retainer that has ended must
     * stop generating charges without anyone having to remember to switch it
     * off.
     */
    public function retainerIsActive(?\DateTimeInterface $on = null): bool
    {
        if (! $this->is_retainer || $this->retainer_amount === null) {
            return false;
        }

        if ($this->status === ProjectStatus::Cancelled) {
            return false;
        }

        $date = \Illuminate\Support\Carbon::instance($on ? \Illuminate\Support\Carbon::parse($on) : now())->startOfDay();

        if ($this->retainer_starts_on && $date->lt($this->retainer_starts_on->startOfDay())) {
            return false;
        }

        if ($this->retainer_ends_on && $date->gt($this->retainer_ends_on->endOfDay())) {
            return false;
        }

        return true;
    }
}
