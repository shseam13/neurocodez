<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'stage_set_id', 'name', 'client_label', 'slug', 'position', 'color',
        'is_terminal', 'visible_to_client', 'visible_to_partner',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_terminal' => 'boolean',
            'visible_to_client' => 'boolean',
            'visible_to_partner' => 'boolean',
        ];
    }

    public function stageSet(): BelongsTo
    {
        return $this->belongsTo(StageSet::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProjectStageLog::class);
    }

    /** Is this stage currently the live stage of any project? */
    public function isInUse(): bool
    {
        return Project::query()->where('current_stage_id', $this->getKey())->exists()
            || $this->logs()->exists();
    }

    public function isVisibleTo(AccountType $audience): bool
    {
        return match ($audience) {
            AccountType::Staff => true,
            AccountType::Client => $this->visible_to_client,
            AccountType::Partner => $this->visible_to_partner,
        };
    }

    /** The name a given audience should see — never the internal one. */
    public function labelFor(AccountType $audience): string
    {
        if ($audience === AccountType::Staff) {
            return $this->name;
        }

        return $this->client_label ?: $this->name;
    }
}
