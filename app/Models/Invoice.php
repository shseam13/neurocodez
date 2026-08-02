<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\InvoiceStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Invoice extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'project_id', 'number', 'issued_at', 'due_at', 'status',
        'subtotal', 'tax', 'total', 'currency', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'due_at' => 'date',
            'status' => InvoiceStatus::class,
            'subtotal' => MoneyCast::class,
            'tax' => MoneyCast::class,
            'total' => MoneyCast::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Sent
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    /** Recompute totals from the line items. */
    public function recalculate(): self
    {
        $subtotal = $this->items->reduce(
            fn (Money $carry, InvoiceItem $item) => $carry->plus($item->line_total),
            Money::zero($this->currency)
        );

        $this->subtotal = $subtotal;
        $this->total = $subtotal->plus($this->tax ?? Money::zero($this->currency));

        return $this;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'status', 'total', 'issued_at', 'due_at', 'project_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('money');
    }
}
