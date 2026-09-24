<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['crm_contact_id', 'user_id', 'type', 'body', 'due_at', 'done_at'])]
class CrmActivity extends Model
{
    public const TYPES = ['note' => 'یادداشت', 'call' => 'تماس تلفنی', 'meeting' => 'جلسه', 'task' => 'پیگیری'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'done_at' => 'datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
