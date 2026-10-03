<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'origin', 'sort'])]
class CarMake extends Model
{
    public $timestamps = false;

    public const ORIGINS = ['chinese' => 'چینی', 'korean' => 'کره‌ای', 'japanese' => 'ژاپنی'];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class)->orderBy('model')->orderBy('year_from');
    }
}
