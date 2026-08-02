<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'default_commission_percent', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_commission_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** Projects explicitly referred by this person — the commission-bearing link. */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** Clients this person introduced. Only pre-fills new projects. */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(CommissionPayout::class);
    }

    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
