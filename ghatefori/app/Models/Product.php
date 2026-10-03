<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'part_category_id', 'part_brand_id', 'name', 'slug', 'part_number', 'oem_number', 'authenticity', 'condition',
    'side', 'position', 'package_contents', 'description', 'incompatibility_notes', 'warranty', 'return_policy',
    'stock_status', 'stock_qty', 'lead_time_days', 'price', 'min_price', 'requires_fitment_check', 'is_active', 'price_checked_at',
])]
class Product extends Model
{
    /** Three distinct availability states; "on_request" is never shown as stock. */
    public const STOCK = [
        'in_stock' => 'موجود و آماده ارسال',
        'on_request' => 'قابل تأمین پس از تأیید',
        'out_of_stock' => 'ناموجود',
    ];

    /** Authenticity is declared per item, never for the whole store. */
    public const AUTHENTICITY = [
        'genuine' => 'اصلی (جنیون) — بسته‌بندی خودروساز',
        'oem_supplier' => 'سازنده تأمین‌کننده خط تولید (OEM)',
        'aftermarket' => 'یدکی برند (افترمارکت)',
    ];

    public const CONDITIONS = ['new' => 'نو', 'used' => 'کارکرده'];

    public const SIDES = ['left' => 'چپ (سمت راننده)', 'right' => 'راست (سمت شاگرد)'];

    public const POSITIONS = ['front' => 'جلو', 'rear' => 'عقب'];

    protected function casts(): array
    {
        return [
            'requires_fitment_check' => 'boolean',
            'is_active' => 'boolean',
            'price_checked_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class, 'part_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(PartBrand::class, 'part_brand_id');
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class)->withPivot('note');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(SupplierOffer::class)->orderBy('cost_price');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(StockAlert::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Matches name, part number or OEM number, ignoring spaces and dashes in numbers. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }
        $code = self::normalizeCode($term);
        $query->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhereRaw("replace(replace(upper(part_number), '-', ''), ' ', '') like ?", ["%{$code}%"])
            ->orWhereRaw("replace(replace(upper(oem_number), '-', ''), ' ', '') like ?", ["%{$code}%"]));
    }

    public function scopeForVehicle(Builder $query, ?int $vehicleId): void
    {
        if ($vehicleId) {
            $query->whereHas('vehicles', fn (Builder $q) => $q->whereKey($vehicleId));
        }
    }

    public static function normalizeCode(string $code): string
    {
        return str_replace(['-', ' ', '.'], '', Str::upper(strtr($code, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9'])));
    }

    public static function uniqueSlug(string $text, ?int $ignoreId = null): string
    {
        $base = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($text)), '-') ?: 'part';
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function isOrderable(): bool
    {
        return $this->is_active && $this->stock_status !== 'out_of_stock' && $this->price !== null;
    }

    /** Lowest acceptable unit price: explicit floor, else best current cost. */
    public function floorPrice(): ?int
    {
        return $this->min_price ?? $this->offers->min('cost_price');
    }

    public function schemaAvailability(): string
    {
        return [
            'in_stock' => 'https://schema.org/InStock',
            'on_request' => 'https://schema.org/BackOrder',
            'out_of_stock' => 'https://schema.org/OutOfStock',
        ][$this->stock_status];
    }
}
