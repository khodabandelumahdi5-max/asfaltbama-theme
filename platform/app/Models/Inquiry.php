<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'product_id', 'user_id', 'crm_contact_id', 'name', 'phone', 'quantity', 'message', 'read_at'])]
class Inquiry extends Model
{
    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'quantity' => 'float'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }
}
