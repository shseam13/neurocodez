<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    use HasFactory, Publishable, SoftDeletes;

    /**
     * Opts this model into reading-time calculation by Publishable.
     *
     * Posts have a `reading_minutes` column; portfolio items do not.
     */
    protected bool $tracksReadingTime = true;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body_markdown', 'body_html',
        'cover_disk', 'cover_path', 'cover_alt',
        'status', 'published_at', 'author_id',
        'meta_title', 'meta_description', 'reading_minutes', 'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'reading_minutes' => 'integer',
            'views' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function coverUrl(): ?string
    {
        if (blank($this->cover_path)) {
            return null;
        }

        return Storage::disk($this->cover_disk ?? config('filesystems.default'))
            ->url($this->cover_path);
    }

    /** Title for <title> and Open Graph, falling back to the post title. */
    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function seoDescription(): string
    {
        return $this->meta_description
            ?: $this->excerpt
            ?: app(\App\Services\MarkdownService::class)->excerpt($this->body_markdown);
    }

    /**
     * Increment the view counter without touching `updated_at`.
     *
     * A plain save() would rewrite updated_at on every page view, which would
     * make the sitemap claim every post changed constantly.
     */
    public function recordView(): void
    {
        static::withoutTimestamps(fn () => $this->increment('views'));
    }
}
