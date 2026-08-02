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

/** Money out — what has actually been paid to a partner. */
class CommissionPayout extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'project_id', 'partner_id', 'amount', 'paid_at',
        'method', 'reference', 'note', 'recorded_by',
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

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getCurrencyAttribute(): string
    {
        return $this->project?->currency ?? config('neuro.currency', 'BDT');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'paid_at', 'method', 'reference', 'partner_id', 'project_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('money');
    }
}
