<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A reusable named pipeline, e.g. "Web Development" or "Logo Design".
 *
 * Admin-defined rather than hardcoded, because a logo job and a web build have
 * nothing in common stage-wise.
 */
class StageSet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'description', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('position');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function firstStage(): ?Stage
    {
        return $this->stages()->first();
    }

    /**
     * Duplicate this set and its stages.
     *
     * The copy is fully independent — editing it must never reach back into the
     * original or the projects already running on it.
     */
    public function duplicate(?string $name = null): self
    {
        $copy = static::create([
            'name' => $name ?? "{$this->name} (copy)",
            'description' => $this->description,
            'is_default' => false,
            'is_active' => true,
        ]);

        foreach ($this->stages as $stage) {
            $copy->stages()->create([
                'name' => $stage->name,
                'client_label' => $stage->client_label,
                'slug' => $stage->slug,
                'position' => $stage->position,
                'color' => $stage->color,
                'is_terminal' => $stage->is_terminal,
                'visible_to_client' => $stage->visible_to_client,
                'visible_to_partner' => $stage->visible_to_partner,
            ]);
        }

        return $copy->load('stages');
    }

    /** Only one set may be the default. */
    public function makeDefault(): void
    {
        static::query()->where('is_default', true)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true])->save();
    }

    protected static function booted(): void
    {
        static::creating(function (self $set) {
            $set->name = trim($set->name) ?: 'Untitled set';
        });
    }

    public static function defaultSet(): ?self
    {
        return static::query()->where('is_default', true)->where('is_active', true)->first();
    }

    public function suggestSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'stage';
        $slug = $base;
        $i = 2;

        while ($this->stages()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
