<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderByDesc('is_verified')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('companies.index', compact('companies'));
    }

    public function show(Company $company)
    {
        return view('companies.show', [
            'company' => $company,
            'products' => $company->products()->active()->with('category')->latest()->paginate(12),
        ]);
    }
}
