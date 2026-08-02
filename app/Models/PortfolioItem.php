<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A curated public case study.
 *
 * Separate from Project on purpose. Everything shown here is authored for
 * publication — `client_display_name` is what you chose to say, never
 * `clients.name`, and no money field exists on this model at all.
 */
class PortfolioItem extends Model
{
    use HasFactory, Publishable, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'summary', 'body_markdown', 'body_html',
        'client_display_name', 'project_id',
        'cover_disk', 'cover_path', 'cover_alt',
        'tech', 'live_url', 'year', 'position', 'is_featured',
        'status', 'published_at', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'tech' => 'array',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'year' => 'integer',
            'position' => 'integer',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(PortfolioImage::class)->orderBy('position');
    }

    /**
     * Internal convenience link back to the real project.
     *
     * NOTHING on the public side may traverse this. It exists so staff can jump
     * from a case study to the underlying job, and for no other reason.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function coverUrl(): ?string
    {
        if (blank($this->cover_path)) {
            return null;
        }

        return Storage::disk($this->cover_disk ?? config('filesystems.default'))
            ->url($this->cover_path);
    }

    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function seoDescription(): string
    {
        return $this->meta_description ?: (string) $this->summary;
    }
}
