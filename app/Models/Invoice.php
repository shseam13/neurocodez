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
        'subtotal', 'tax', 'advance_percent', 'total', 'currency', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'due_at' => 'date',
            'status' => InvoiceStatus::class,
            'subtotal' => MoneyCast::class,
            'tax' => MoneyCast::class,
            'advance_percent' => 'decimal:2',
            'total' => MoneyCast::class,
        ];
    }

    /**
     * Is this invoice asking for part of the work up front?
     *
     * 100% is not an advance — it is the whole thing — so it is treated as an
     * ordinary invoice and the advance wording is left off the document.
     */
    public function isAdvanceRequest(): bool
    {
        return $this->advance_percent !== null
            && (float) $this->advance_percent > 0
            && (float) $this->advance_percent < 100;
    }

    /** The portion being billed now: the whole subtotal unless this is an advance. */
    public function billableSubtotal(): Money
    {
        $subtotal = $this->subtotal ?? Money::zero($this->currency);

        return $this->isAdvanceRequest()
            ? $subtotal->percent((float) $this->advance_percent)
            : $subtotal;
    }

    /** What is left to invoice later. Zero on an ordinary invoice. */
    public function deferredAmount(): Money
    {
        $subtotal = $this->subtotal ?? Money::zero($this->currency);

        return $subtotal->minus($this->billableSubtotal());
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
