<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'name', 'phone', 'contact_consent', 'notified_at'])]
class StockAlert extends Model
{
    protected function casts(): array
    {
        return ['notified_at' => 'datetime', 'contact_consent' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
