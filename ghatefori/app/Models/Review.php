<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'rating', 'body', 'publish_consent', 'is_published'])]
class Review extends Model
{
    protected function casts(): array
    {
        return ['publish_consent' => 'boolean', 'is_published' => 'boolean'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
