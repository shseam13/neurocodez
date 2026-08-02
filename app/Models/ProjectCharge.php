<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\ChargeKind;
use App\Enums\ChargeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Extra work billed on top of a project's original scope.
 *
 * The original `projects.agreed_amount` is never edited to absorb these — see
 * the migration for why.
 */
class ProjectCharge extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'project_id', 'title', 'description', 'amount',
        'kind', 'status', 'commission_applies',
        'occurred_at', 'approved_at', 'period_start', 'period_end', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'kind' => ChargeKind::class,
            'status' => ChargeStatus::class,
            'commission_applies' => 'boolean',
            'occurred_at' => 'date',
            'approved_at' => 'datetime',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Only approved charges move money — quotes must not inflate a balance. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ChargeStatus::Approved);
    }

    public function scopeCommissionable(Builder $query): Builder
    {
        return $query->approved()->where('commission_applies', true);
    }

    public function getCurrencyAttribute(): string
    {
        return $this->project?->currency ?? config('neuro.currency', 'BDT');
    }

    public function approve(): self
    {
        $this->forceFill([
            'status' => ChargeStatus::Approved,
            'approved_at' => $this->approved_at ?? now(),
        ])->save();

        return $this;
    }

    protected static function booted(): void
    {
        // A charge created already approved still needs its timestamp, or the
        // audit trail cannot say when it was authorised.
        static::saving(function (self $charge) {
            if ($charge->status === ChargeStatus::Approved && $charge->approved_at === null) {
                $charge->approved_at = now();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'amount', 'kind', 'status', 'commission_applies', 'occurred_at', 'project_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('money');
    }
}
