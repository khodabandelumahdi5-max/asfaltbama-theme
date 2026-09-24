<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;

class CompanyController extends Controller
{
    public function index()
    {
        return view('panel.admin.companies', [
            'companies' => Company::with('owner')->withCount('products')->orderBy('is_verified')->latest()->paginate(30),
        ]);
    }

    public function toggleVerify(Company $company)
    {
        $company->update(['is_verified' => ! $company->is_verified]);

        return back()->with('status', $company->is_verified ? 'شرکت تأیید شد.' : 'تأیید شرکت لغو شد.');
    }
}
