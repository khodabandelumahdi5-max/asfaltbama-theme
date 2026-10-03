<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'customer_vehicle_id', 'part_category_id', 'title', 'due_on', 'is_estimate', 'basis', 'done_at'])]
class Reminder extends Model
{
    protected function casts(): array
    {
        return ['due_on' => 'date', 'is_estimate' => 'boolean', 'done_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerVehicle(): BelongsTo
    {
        return $this->belongsTo(CustomerVehicle::class);
    }
}
