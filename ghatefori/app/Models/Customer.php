<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'type', 'city', 'source', 'marketing_consent', 'notes'])]
class Customer extends Model
{
    public const TYPES = ['owner' => 'مالک خودرو', 'garage' => 'تعمیرگاه', 'shop' => 'فروشگاه قطعه'];

    public const SOURCES = ['instagram' => 'اینستاگرام', 'google' => 'گوگل', 'referral' => 'معرفی دوستان', 'garage' => 'معرفی تعمیرگاه', 'other' => 'سایر'];

    protected function casts(): array
    {
        return ['marketing_consent' => 'boolean'];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(CustomerVehicle::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function fitmentRequests(): HasMany
    {
        return $this->hasMany(FitmentRequest::class)->latest();
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class)->orderBy('due_on');
    }

    /** Find by phone (the customer's identity) or create, filling only blanks on existing records. */
    public static function capture(array $attributes): self
    {
        $customer = static::firstOrNew(['phone' => $attributes['phone']]);
        foreach ($attributes as $key => $value) {
            if (blank($customer->{$key}) && filled($value)) {
                $customer->{$key} = $value;
            }
        }
        $customer->save();

        return $customer;
    }
}
