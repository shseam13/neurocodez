<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\MarkdownService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Shared publishing behaviour for Markdown-authored public content.
 *
 * Slugs are generated once and then frozen — changing a slug after publication
 * breaks every existing link and any search ranking the page has earned.
 */
trait Publishable
{
    public static function bootPublishable(): void
    {
        static::saving(function ($model) {
            if (blank($model->slug)) {
                $model->slug = static::uniqueSlug($model->title ?? '');
            }

            if ($model->isDirty('body_markdown')) {
                $markdown = app(MarkdownService::class);
                $model->body_html = $markdown->toHtml($model->body_markdown);

                /*
                 * Opt in explicitly, via a property on the model.
                 *
                 * This used to test isFillable('reading_minutes') as a stand-in
                 * for "does this model have that column". That is wrong twice
                 * over: fillability describes mass assignment, not schema — and
                 * `db:seed` runs inside Model::unguarded(), where isFillable()
                 * returns true for every key. Portfolio items then tried to
                 * write a column that only exists on posts.
                 */
                if (property_exists($model, 'tracksReadingTime')) {
                    $model->reading_minutes = $markdown->readingMinutes($model->body_markdown);
                }
            }

            // Publishing without an explicit date should mean "now", not null —
            // a null date would drop the item out of every ordered listing.
            if ($model->status === 'published' && blank($model->published_at)) {
                $model->published_at = now();
            }
        });
    }

    protected static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: Str::random(8);
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * The only query the public side may use.
     *
     * Excludes drafts AND future-dated items, so scheduling a post simply works
     * rather than needing a publishing job.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
