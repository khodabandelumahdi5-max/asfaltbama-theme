<?php

namespace App\Models;

use App\Models\Concerns\HasPersianSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'category_id', 'name', 'slug', 'description', 'unit', 'min_order', 'price_min', 'price_max', 'image_url', 'is_active'])]
class Product extends Model
{
    use HasPersianSlug;

    public const UNITS = ['تن', 'بشکه', 'کیلوگرم', 'لیتر', 'متر مربع', 'متر مکعب', 'سرویس', 'دستگاه'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'min_order' => 'float'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (filled($term)) {
            $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"));
        }
    }
}
