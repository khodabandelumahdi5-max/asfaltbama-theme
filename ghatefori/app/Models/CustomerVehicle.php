<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'vehicle_id', 'description', 'year', 'vin', 'last_service_date', 'last_service_km'])]
#[Hidden(['vin'])]
class CustomerVehicle extends Model
{
    protected function casts(): array
    {
        return ['last_service_date' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function label(): string
    {
        $label = $this->vehicle?->label() ?? $this->description ?? 'خودرو';

        return $this->year ? $label.' — مدل '.fa_digits($this->year) : $label;
    }
}
