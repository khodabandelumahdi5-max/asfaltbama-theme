<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class OrderTrackingController extends Controller
{
    public function form()
    {
        return view('orders.track');
    }

    public function find(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string'], 'phone' => ['required', 'string']]);
        $order = Order::where('code', strtoupper(trim($data['code'])))
            ->whereHas('customer', fn ($q) => $q->where('phone', $data['phone']))
            ->first();

        if (! $order) {
            return back()->withErrors(['code' => 'سفارشی با این کد و شماره موبایل پیدا نشد.'])->withInput();
        }

        return redirect()->to(URL::signedRoute('orders.show', $order));
    }

    public function show(Order $order)
    {
        return view('orders.show', [
            'order' => $order->load('items.product.brand', 'customerVehicle.vehicle.make', 'review'),
            'reviewUrl' => URL::signedRoute('orders.review', $order),
        ]);
    }

    /** Only a customer whose order was delivered can leave a review. */
    public function review(Request $request, Order $order)
    {
        abort_unless($order->status === 'delivered' && ! $order->review, 403);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:1000'],
            'publish_consent' => ['nullable', 'boolean'],
        ]);
        $order->review()->create($data + ['publish_consent' => (bool) ($data['publish_consent'] ?? false)]);

        return back()->with('status', 'از نظر شما ممنونیم.');
    }
}
