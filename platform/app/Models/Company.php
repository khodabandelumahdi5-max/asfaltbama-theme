<?php

namespace App\Models;

use App\Models\Concerns\HasPersianSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'slug', 'city', 'phone', 'website', 'description', 'founded_year', 'is_verified'])]
class Company extends Model
{
    use HasPersianSlug;

    protected function casts(): array
    {
        return ['is_verified' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class);
    }
}
