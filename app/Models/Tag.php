<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'color'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::saving(function (self $tag) {
            if (blank($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public static function findOrCreateByName(string $name): self
    {
        return static::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => trim($name)],
        );
    }
}
