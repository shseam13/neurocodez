<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'company', 'email', 'phone', 'address', 'notes', 'partner_id', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * The partner who introduced this client, if any.
     *
     * A "referred client" is an ordinary client with this link set — not a
     * third account type. What differs is the relationship, not the login.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function wasReferred(): bool
    {
        return $this->partner_id !== null;
    }
}
