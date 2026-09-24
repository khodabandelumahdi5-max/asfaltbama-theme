<?php

namespace App\Http\Controllers\Panel;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends SupplierController
{
    public function edit()
    {
        return view('panel.company', ['company' => $this->company()]);
    }

    public function update(Request $request)
    {
        $company = $this->company();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:190'],
            'founded_year' => ['nullable', 'integer', 'between:1300,1500'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($data['name'] !== $company->name) {
            $data['slug'] = Company::uniqueSlug($data['name'], $company->id);
        }

        $company->update($data);

        return back()->with('status', 'پروفایل شرکت به‌روزرسانی شد.');
    }
}
