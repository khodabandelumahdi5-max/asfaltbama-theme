<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'user_id', 'name', 'phone', 'email', 'organization', 'source', 'stage', 'deal_value', 'notes'])]
class CrmContact extends Model
{
    /** Sales pipeline stages, in order. */
    public const STAGES = [
        'new' => 'سرنخ جدید',
        'contacted' => 'تماس گرفته شد',
        'negotiation' => 'در حال مذاکره',
        'won' => 'قرارداد بسته شد',
        'lost' => 'از دست رفته',
    ];

    public const SOURCES = ['manual' => 'دستی', 'inquiry' => 'پیام محصول', 'rfq' => 'استعلام قیمت'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class)->latest();
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    /**
     * Find the supplier's contact for this phone number, or create a new lead.
     */
    public static function captureLead(Company $company, array $attributes, string $source): self
    {
        $contact = $company->contacts()->firstOrNew(['phone' => $attributes['phone']]);

        if (! $contact->exists) {
            $contact->fill($attributes + ['source' => $source, 'stage' => 'new'])->save();
        }

        return $contact;
    }
}
