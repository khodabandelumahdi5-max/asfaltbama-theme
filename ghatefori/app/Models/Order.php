<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'code', 'customer_id', 'customer_vehicle_id', 'status', 'city', 'address', 'shipping_charge', 'shipping_cost',
    'inbound_cost', 'packaging_cost', 'payment_fee', 'discount', 'acquisition_cost', 'return_reserve', 'source',
    'customer_note', 'fitment_checked_by', 'fitment_note', 'tracking_code', 'cancel_reason', 'return_reason',
    'assigned_to', 'confirmed_at', 'shipped_at', 'delivered_at', 'promised_at',
])]
class Order extends Model
{
    public const STATUSES = [
        'awaiting_confirmation' => 'در انتظار تأیید قیمت، موجودی و تطبیق',
        'confirmed' => 'تأیید شد — در انتظار پرداخت',
        'paid' => 'پرداخت شد — در حال آماده‌سازی',
        'shipped' => 'ارسال شد',
        'delivered' => 'تحویل شد',
        'cancelled' => 'لغو شد',
        'returned' => 'مرجوع شد',
    ];

    /** Allowed next statuses from each status. */
    public const TRANSITIONS = [
        'awaiting_confirmation' => ['confirmed', 'cancelled'],
        'confirmed' => ['paid', 'cancelled'],
        'paid' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'returned'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    public const CANCEL_REASONS = ['no_stock' => 'نبود موجودی', 'price_change' => 'تغییر قیمت', 'fitment' => 'عدم تطبیق', 'customer' => 'انصراف مشتری', 'other' => 'سایر'];

    public const RETURN_REASONS = ['fitment' => 'عدم تطبیق', 'defect' => 'خرابی', 'damaged' => 'آسیب در ارسال', 'customer' => 'انصراف مشتری', 'other' => 'سایر'];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'promised_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->code ??= 'GF-'.Str::upper(Str::random(6));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerVehicle(): BelongsTo
    {
        return $this->belongsTo(CustomerVehicle::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest();
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function fitmentChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fitment_checked_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function itemsTotal(): int
    {
        return (int) $this->items->sum(fn (OrderItem $i) => $i->qty * $i->unit_price);
    }

    /** What the customer pays. */
    public function total(): int
    {
        return max(0, $this->itemsTotal() - $this->discount + $this->shipping_charge);
    }

    public function goodsCost(): ?int
    {
        return $this->items->contains(fn (OrderItem $i) => $i->unit_cost === null)
            ? null
            : (int) $this->items->sum(fn (OrderItem $i) => $i->qty * $i->unit_cost);
    }

    public function variableCosts(): int
    {
        return $this->shipping_cost + $this->inbound_cost + $this->packaging_cost + $this->payment_fee
            + $this->acquisition_cost + $this->return_reserve;
    }

    /**
     * Sales − purchase cost − variable costs: what is left for fixed costs and profit.
     * Null while any item's purchase cost is unknown.
     */
    public function contribution(): ?int
    {
        $cost = $this->goodsCost();

        return $cost === null ? null : $this->total() - $cost - $this->variableCosts();
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function log(string $body, ?User $user = null): void
    {
        $this->events()->create(['body' => $body, 'user_id' => $user?->id]);
    }
}
