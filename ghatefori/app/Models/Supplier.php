<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'payment_terms', 'return_terms', 'ships_direct', 'notes'])]
class Supplier extends Model
{
    protected function casts(): array
    {
        return ['ships_direct' => 'boolean'];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(SupplierOffer::class);
    }
}
