<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PortfolioImage extends Model
{
    use HasFactory;

    protected $fillable = ['portfolio_item_id', 'disk', 'path', 'caption', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PortfolioItem::class, 'portfolio_item_id');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    protected static function booted(): void
    {
        static::deleted(function (self $image) {
            Storage::disk($image->disk)->delete($image->path);
        });
    }
}
