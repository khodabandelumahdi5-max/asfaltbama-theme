<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarMake;
use App\Models\Customer;
use App\Models\PartCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.customers.index', [
            'customers' => Customer::withCount('orders')
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->string('q').'%')->orWhere('phone', 'like', '%'.$request->string('q').'%')))
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
                ->latest()->paginate(40)->withQueryString(),
        ]);
    }

    public function show(Customer $customer)
    {
        return view('admin.customers.show', [
            'customer' => $customer->load('vehicles.vehicle.make', 'orders.items.product', 'fitmentRequests.product', 'reminders.customerVehicle.vehicle.make'),
            'makes' => CarMake::with('vehicles')->orderBy('sort')->get(),
            'categories' => PartCategory::orderBy('sort')->get(),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(Customer::TYPES))],
            'city' => ['nullable', 'string', 'max:60'],
            'source' => ['nullable', Rule::in(array_keys(Customer::SOURCES))],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]) + ['marketing_consent' => $request->boolean('marketing_consent')]);

        return back()->with('status', 'پرونده مشتری ذخیره شد.');
    }

    public function addVehicle(Request $request, Customer $customer)
    {
        $customer->vehicles()->create($request->validate([
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'description' => ['required_without:vehicle_id', 'nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'between:1370,1410'],
            'vin' => ['nullable', 'string', 'max:20'],
            'last_service_date' => ['nullable', 'date'],
            'last_service_km' => ['nullable', 'integer', 'min:0'],
        ]));

        return back()->with('status', 'خودرو اضافه شد.');
    }

    /**
     * Reminders are labelled as estimates unless based on confirmed current mileage,
     * and only appear in the work queue for customers who agreed to be contacted.
     */
    public function addReminder(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'customer_vehicle_id' => ['nullable', Rule::exists('customer_vehicles', 'id')->where('customer_id', $customer->id)],
            'part_category_id' => ['nullable', 'exists:part_categories,id'],
            'title' => ['required', 'string', 'max:160'],
            'due_on' => ['required', 'date'],
            'basis' => ['required', 'string', 'max:255'],
        ], ['basis.required' => 'منبع بازه (دفترچه سرویس، توصیه سازنده، اعلام مشتری) را بنویسید.']);

        $customer->reminders()->create($data + ['is_estimate' => ! $request->boolean('confirmed_mileage')]);

        return back()->with('status', 'یادآوری ثبت شد.');
    }

    public function completeReminder(Customer $customer, int $reminder)
    {
        $customer->reminders()->whereKey($reminder)->update(['done_at' => now()]);

        return back();
    }
}
