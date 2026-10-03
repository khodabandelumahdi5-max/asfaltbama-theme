<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Support\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    public function index()
    {
        return view('cart.index', ['lines' => Cart::lines()]);
    }

    public function add(Request $request, Product $product)
    {
        abort_unless($product->isOrderable(), 422, 'این کالا در حال حاضر قابل سفارش نیست.');
        Cart::add($product, $request->integer('qty', 1));

        return redirect()->route('cart.index')->with('status', '«'.$product->name.'» به سبد اضافه شد.');
    }

    public function update(Request $request, Product $product)
    {
        Cart::set($product, $request->integer('qty'));

        return back();
    }

    public function checkout(Request $request)
    {
        $lines = Cart::lines();
        if ($lines->isEmpty()) {
            return redirect()->route('cart.index');
        }

        return view('cart.checkout', [
            'lines' => $lines,
            'city' => old('city', $request->query('city', 'تهران')),
            'vehicleId' => session('vehicle_id'),
        ]);
    }

    /**
     * Places the order as "awaiting confirmation": price, stock, lead time and fitment
     * are verified by staff before the customer is asked to pay.
     */
    public function place(Request $request)
    {
        $lines = Cart::lines()->filter(fn ($l) => $l['product']->isOrderable());
        if ($lines->isEmpty()) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'کالاهای سبد دیگر قابل سفارش نیستند.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'type' => ['required', Rule::in(array_keys(Customer::TYPES))],
            'city' => ['required', Rule::in(config('shop.cities'))],
            'address' => ['required', 'string', 'max:500'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'vehicle_text' => ['nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'between:1370,1410'],
            'vin' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', Rule::in(array_keys(Customer::SOURCES))],
            'note' => ['nullable', 'string', 'max:1000'],
            'marketing_consent' => ['nullable', 'boolean'],
        ], ['phone.regex' => 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.']);

        $order = DB::transaction(function () use ($data, $lines) {
            $customer = Customer::capture([
                'name' => $data['name'], 'phone' => $data['phone'], 'type' => $data['type'], 'city' => $data['city'],
                'source' => $data['source'] ?? null, 'marketing_consent' => (bool) ($data['marketing_consent'] ?? false),
            ]);

            $vehicle = null;
            if (! empty($data['vehicle_id']) || ! empty($data['vehicle_text'])) {
                $vehicle = $customer->vehicles()->firstOrCreate(
                    ['vehicle_id' => $data['vehicle_id'] ?? null, 'description' => $data['vehicle_text'] ?? null, 'year' => $data['year'] ?? null],
                    ['vin' => $data['vin'] ?? null],
                );
            }

            $itemsTotal = $lines->sum('subtotal');
            $defaults = config('shop.defaults');
            $shipping = Cart::shippingFor($data['city']);

            $order = Order::create([
                'customer_id' => $customer->id,
                'customer_vehicle_id' => $vehicle?->id,
                'city' => $data['city'],
                'address' => $data['address'],
                'shipping_charge' => $shipping['charge'],
                'shipping_cost' => $shipping['charge'],
                'packaging_cost' => $defaults['packaging_cost'],
                'payment_fee' => (int) round($itemsTotal * $defaults['payment_fee_percent'] / 100),
                'return_reserve' => (int) round($itemsTotal * $defaults['return_reserve_percent'] / 100),
                'source' => $data['source'] ?? null,
                'customer_note' => $data['note'] ?? null,
            ]);

            foreach ($lines as $line) {
                $best = $line['product']->offers()->first();
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'qty' => $line['qty'],
                    'unit_price' => $line['product']->price,
                    'unit_cost' => $best?->cost_price,
                    'supplier_id' => $best?->supplier_id,
                ]);
            }
            $order->log('سفارش توسط مشتری ثبت شد.');

            return $order;
        });

        Cart::clear();

        return redirect()->to(URL::signedRoute('orders.show', $order))
            ->with('status', 'سفارش ثبت شد. پیش از پرداخت، قیمت، موجودی، زمان ارسال و تطبیق قطعه را تأیید و به شما اعلام می‌کنیم.');
    }
}
