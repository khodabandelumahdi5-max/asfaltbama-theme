<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FitmentRequest;
use App\Models\Product;
use Illuminate\Http\Request;

class FitmentController extends Controller
{
    public function create(Request $request)
    {
        return view('fitment.create', [
            'product' => $request->filled('product') ? Product::where('slug', $request->string('product'))->first() : null,
            'vehicleId' => session('vehicle_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'product_id' => ['nullable', 'exists:products,id'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'vehicle_text' => ['required_without:vehicle_id', 'nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'between:1370,1410'],
            'engine' => ['nullable', 'string', 'max:60'],
            'vin' => ['nullable', 'string', 'max:20'],
            'part_number' => ['nullable', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'marketing_consent' => ['nullable', 'boolean'],
        ], ['phone.regex' => 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.', 'vehicle_text.required_without' => 'خودرو را از فهرست انتخاب کنید یا بنویسید.']);

        $customer = Customer::capture([
            'name' => $data['name'], 'phone' => $data['phone'],
            'marketing_consent' => (bool) ($data['marketing_consent'] ?? false),
        ]);

        $path = $request->file('photo')?->store('fitment', 'public');
        unset($data['photo'], $data['marketing_consent']);

        $fitment = FitmentRequest::create($data + ['customer_id' => $customer->id, 'photo_path' => $path]);

        return redirect()->route('fitment.thanks')->with('fitment_id', $fitment->id);
    }

    public function thanks()
    {
        abort_unless(session('fitment_id'), 404);

        return view('fitment.thanks');
    }

    /** «خبرم کن موجود شد» */
    public function alert(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
        ], ['phone.regex' => 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.']);

        $product->alerts()->firstOrCreate(
            ['phone' => $data['phone'], 'notified_at' => null],
            ['name' => $data['name'], 'contact_consent' => true],
        );

        return back()->with('status', 'ثبت شد. وقتی «'.$product->name.'» واقعاً موجود شد با شما تماس می‌گیریم.');
    }
}
