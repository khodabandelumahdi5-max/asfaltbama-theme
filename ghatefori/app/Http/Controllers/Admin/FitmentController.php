<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FitmentRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FitmentController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.fitment.index', [
            'requests' => FitmentRequest::with('product', 'vehicle.make', 'checker')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')), fn ($q) => $q->orderByRaw("status = 'pending' desc"))
                ->latest()->paginate(30)->withQueryString(),
        ]);
    }

    public function show(FitmentRequest $fitment)
    {
        return view('admin.fitment.show', [
            'fitment' => $fitment->load('product.vehicles', 'vehicle.make', 'customer.orders', 'checker'),
            'products' => Product::active()->orderBy('name')->get(['id', 'name', 'part_number']),
        ]);
    }

    public function update(Request $request, FitmentRequest $fitment)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(FitmentRequest::STATUSES))],
            'product_id' => ['nullable', 'exists:products,id'],
            'result_note' => ['required_unless:status,pending', 'nullable', 'string', 'max:2000'],
            'quoted_price' => ['nullable', 'integer', 'min:0'],
            'quoted_lead_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'followed_up' => ['nullable', 'boolean'],
        ], ['result_note.required_unless' => 'نتیجه بررسی و دلیل آن را بنویسید.']);

        $fields = collect($data)->except('followed_up')->all();
        if ($data['status'] !== 'pending' && $fitment->status === 'pending') {
            $fields += ['checked_by' => $request->user()->id, 'checked_at' => now()];
        }
        if ($request->boolean('followed_up') && ! $fitment->followed_up_at) {
            $fields['followed_up_at'] = now();
        }
        $fitment->update($fields);

        return back()->with('status', 'نتیجه بررسی ثبت شد.');
    }
}
