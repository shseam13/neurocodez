<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Single-row company profile. Drives app chrome and, more importantly, every
 * invoice that goes to a client.
 */
class CompanySetting extends Model
{
    protected $fillable = [
        'name', 'slogan', 'logo_path', 'address', 'phone', 'email', 'website',
        'invoice_prefix', 'invoice_next_number', 'currency',
        'default_commission_basis', 'payment_details',
    ];

    protected function casts(): array
    {
        return [
            'invoice_next_number' => 'integer',
            'default_commission_basis' => CommissionBasis::class,
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], []);
    }

    /**
     * Reserve the next invoice number, e.g. INV-2026-042.
     *
     * Wrapped in a transaction with a row lock: two invoices created at the
     * same moment must not collide on `invoices.number`, which is unique.
     */
    public function nextInvoiceNumber(): string
    {
        return DB::transaction(function () {
            $row = static::query()->lockForUpdate()->find($this->getKey());
            $seq = $row->invoice_next_number;
            $row->forceFill(['invoice_next_number' => $seq + 1])->save();

            return sprintf('%s-%s-%03d', $row->invoice_prefix, now()->year, $seq);
        });
    }
}
