<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'youtube_id', 'title', 'description', 'published_at', 'thumbnail_url',
        'position', 'is_featured', 'is_published', 'is_manual', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'synced_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'is_manual' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function watchUrl(): string
    {
        return "https://www.youtube.com/watch?v={$this->youtube_id}";
    }

    /**
     * Privacy-enhanced embed host: youtube-nocookie.com does not set tracking
     * cookies until the visitor actually presses play.
     */
    public function embedUrl(): string
    {
        return "https://www.youtube-nocookie.com/embed/{$this->youtube_id}";
    }

    /**
     * Thumbnail straight from YouTube's image CDN.
     *
     * `hqdefault` always exists; `maxresdefault` 404s for videos that were
     * never uploaded at high resolution, which would leave broken images on
     * the grid.
     */
    public function thumbnail(string $quality = 'hqdefault'): string
    {
        return $this->thumbnail_url
            ?: "https://i.ytimg.com/vi/{$this->youtube_id}/{$quality}.jpg";
    }

    /** Accepts a full URL, a youtu.be link, or a bare id. */
    public static function extractId(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }

        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $input, $m)) {
            return $m[1];
        }

        return null;
    }
}
