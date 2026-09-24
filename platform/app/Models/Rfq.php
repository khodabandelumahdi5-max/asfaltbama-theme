<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['buyer_id', 'category_id', 'title', 'quantity', 'unit', 'city', 'details', 'contact_name', 'contact_phone', 'status'])]
class Rfq extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'float'];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
