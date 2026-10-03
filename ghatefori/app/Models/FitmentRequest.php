<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id', 'product_id', 'vehicle_id', 'name', 'phone', 'vehicle_text', 'year', 'engine', 'vin', 'part_number',
    'description', 'photo_path', 'status', 'result_note', 'quoted_price', 'quoted_lead_days', 'checked_by', 'checked_at', 'followed_up_at',
])]
class FitmentRequest extends Model
{
    public const STATUSES = [
        'pending' => 'در انتظار بررسی فنی',
        'needs_info' => 'نیاز به اطلاعات بیشتر',
        'compatible' => 'سازگار — قابل سفارش',
        'incompatible' => 'سازگار نیست',
        'unavailable' => 'فعلاً قابل تأمین نیست',
    ];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'followed_up_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
