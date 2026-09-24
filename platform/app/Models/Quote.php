<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rfq_id', 'company_id', 'unit_price', 'delivery_days', 'message', 'status'])]
class Quote extends Model
{
    public const STATUSES = ['pending' => 'در انتظار', 'accepted' => 'پذیرفته شد', 'rejected' => 'رد شد'];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
