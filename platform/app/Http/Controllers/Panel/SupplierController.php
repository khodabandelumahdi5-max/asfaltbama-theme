<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

abstract class SupplierController extends Controller
{
    protected function company(): Company
    {
        $company = request()->user()->company;
        abort_if($company === null, 403, 'پروفایل شرکت یافت نشد.');

        return $company;
    }

    /** Abort unless the record belongs to the current supplier's company. */
    protected function authorizeOwned(Model $model, string $key = 'company_id'): void
    {
        abort_unless((int) $model->{$key} === $this->company()->id, 404);
    }
}
