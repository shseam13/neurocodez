<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An enquiry from the public site.
 *
 * This is what closes the loop: enquiries land in the system rather than
 * getting lost in an inbox, and convert directly into a client.
 */
class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'subject', 'message',
        'source', 'status', 'converted_client_id',
        'ip', 'user_agent', 'handled_at', 'handled_by',
    ];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', 'new');
    }

    public function scopeActionable(Builder $query): Builder
    {
        return $query->whereIn('status', ['new', 'contacted']);
    }

    /**
     * Turn this enquiry into a real client.
     *
     * Idempotent — converting twice returns the client already created rather
     * than duplicating it.
     */
    public function convertToClient(?User $by = null): Client
    {
        if ($this->converted_client_id) {
            return $this->convertedClient;
        }

        $client = Client::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'notes' => "Converted from website enquiry #{$this->id}:\n\n{$this->message}",
        ]);

        $this->update([
            'converted_client_id' => $client->id,
            'status' => 'converted',
            'handled_at' => now(),
            'handled_by' => $by?->getKey(),
        ]);

        return $client;
    }
}
