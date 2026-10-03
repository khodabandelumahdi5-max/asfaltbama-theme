<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        return view('admin.suppliers', [
            'suppliers' => Supplier::withCount('offers')->orderBy('name')->get(),
            'editing' => request('edit') ? Supplier::find(request('edit')) : new Supplier,
        ]);
    }

    public function save(Request $request, ?Supplier $supplier = null)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'return_terms' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]) + ['ships_direct' => $request->boolean('ships_direct')];

        $supplier && $supplier->exists ? $supplier->update($data) : Supplier::create($data);

        return redirect()->route('admin.suppliers.index')->with('status', 'تأمین‌کننده ذخیره شد.');
    }
}
