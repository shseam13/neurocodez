<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/** Money in — what the client has actually paid. */
class Payment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'project_id', 'amount', 'paid_at', 'method', 'reference', 'note', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'paid_at' => 'date',
            'method' => PaymentMethod::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** The currency lives on the parent project, not on each payment row. */
    public function getCurrencyAttribute(): string
    {
        return $this->project?->currency ?? config('neuro.currency', 'BDT');
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Audit scope is money records only — enough to reconstruct a disputed
        // figure, small enough for a 1 GB free-tier database.
        return LogOptions::defaults()
            ->logOnly(['amount', 'paid_at', 'method', 'reference', 'project_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('money');
    }
}
