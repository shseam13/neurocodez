<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'name', 'email', 'password', 'type',
        'client_id', 'partner_id', 'theme_pref',
        'invited_at', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'type' => AccountType::class,
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // ------------------------------------------------------------ relations

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    // ------------------------------------------------------- account type

    public function isStaff(): bool
    {
        return $this->type === AccountType::Staff;
    }

    public function isClient(): bool
    {
        return $this->type === AccountType::Client;
    }

    public function isPartner(): bool
    {
        return $this->type === AccountType::Partner;
    }

    /**
     * Roles only ever apply to staff. Asking whether a client is a super admin
     * should be false by construction, not by hoping no role was assigned.
     */
    public function isSuperAdmin(): bool
    {
        return $this->isStaff() && $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    /**
     * Invited but has not chosen a password yet.
     *
     * `invited_at` is cleared the moment they set one, which is what makes an
     * invitation link single-use even though the signed URL itself stays valid
     * until it expires.
     */
    public function hasPendingInvitation(): bool
    {
        return $this->invited_at !== null;
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('type', AccountType::Staff);
    }

    public function scopeOfType(Builder $query, AccountType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopePendingInvitation(Builder $query): Builder
    {
        return $query->whereNotNull('invited_at');
    }

    /**
     * Is this the last super admin standing?
     *
     * Guards deletion and demotion. Without it you can lock yourself out of
     * your own company with no way back in short of editing the database.
     */
    public function isLastSuperAdmin(): bool
    {
        if (! $this->isSuperAdmin()) {
            return false;
        }

        return static::query()
            ->staff()
            ->where('is_active', true)
            ->whereKeyNot($this->getKey())
            ->whereHas('roles', fn (Builder $q) => $q->where('name', self::ROLE_SUPER_ADMIN))
            ->doesntExist();
    }

    /**
     * Queue the reset email instead of sending it inside the request.
     *
     * Laravel's default is inline, which stalls the response on the SMTP
     * handshake and can time the request out at the web server. See
     * [QueuedResetPassword].
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\QueuedResetPassword($token));
    }
}
