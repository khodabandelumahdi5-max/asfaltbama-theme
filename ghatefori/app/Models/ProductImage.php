<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'path', 'kind', 'caption', 'sort'])]
class ProductImage extends Model
{
    public const KINDS = [
        'full' => 'نمای کامل',
        'back' => 'پشت قطعه',
        'connector' => 'سوکت / محل اتصال',
        'label' => 'کد و برچسب',
        'package' => 'بسته‌بندی',
        'installed' => 'نصب‌شده روی خودرو',
        'illustration' => 'تصویر توضیحی (عکس واقعی نیست)',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return str_starts_with($this->path, 'http') ? $this->path : asset('storage/'.$this->path);
    }
}
