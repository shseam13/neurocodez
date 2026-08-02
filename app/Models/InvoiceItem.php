<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'description', 'qty', 'unit_price', 'line_total', 'position',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'position' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getCurrencyAttribute(): string
    {
        return $this->invoice?->currency ?? config('neuro.currency', 'BDT');
    }

    protected static function booted(): void
    {
        // Derive the line total rather than trusting whatever was posted.
        static::saving(function (self $item) {
            if ($item->unit_price !== null) {
                $item->line_total = $item->unit_price->percent((float) $item->qty * 100);
            }
        });
    }
}
