<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['car_make_id', 'model', 'year_from', 'year_to', 'engine', 'gearbox', 'trim'])]
class Vehicle extends Model
{
    public function make(): BelongsTo
    {
        return $this->belongsTo(CarMake::class, 'car_make_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('note');
    }

    /** e.g. «جک S5 ۱.۵ توربو اتومات (۱۳۹۸–۱۴۰۲)» */
    public function label(): string
    {
        $years = $this->year_from ? ' ('.fa_digits($this->year_from).($this->year_to && $this->year_to !== $this->year_from ? '–'.fa_digits($this->year_to) : '').')' : '';

        return trim(collect([$this->make?->name, $this->model, $this->trim, $this->engine ? fa_digits($this->engine) : null, $this->gearbox])->filter()->implode(' ')).$years;
    }
}
