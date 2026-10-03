<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with('customer', 'items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('customer', fn ($c) => $c->where('phone', 'like', '%'.$request->string('q').'%')->orWhere('name', 'like', '%'.$request->string('q').'%'))))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load('customer', 'customerVehicle.vehicle.make', 'items.product.offers.supplier', 'items.supplier', 'events.user', 'fitmentChecker', 'review');

        return view('admin.orders.show', ['order' => $order, 'staff' => User::orderBy('name')->get()]);
    }

    /** Edit line prices (never below the floor unless a manager), costs and assignment. */
    public function update(Request $request, Order $order)
    {
        $user = $request->user();
        $data = $request->validate([
            'items' => ['array'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'integer', 'min:0'],
            'items.*.supplier_id' => ['nullable', 'exists:suppliers,id'],
            'discount' => ['required', 'integer', 'min:0'],
            'shipping_charge' => ['required', 'integer', 'min:0'],
            'promised_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'fitment_note' => ['nullable', 'string', 'max:1000'],
            'fitment_confirmed' => ['nullable', 'boolean'],
            // Cost fields are manager-only.
            'shipping_cost' => ['sometimes', 'integer', 'min:0'],
            'inbound_cost' => ['sometimes', 'integer', 'min:0'],
            'packaging_cost' => ['sometimes', 'integer', 'min:0'],
            'payment_fee' => ['sometimes', 'integer', 'min:0'],
            'acquisition_cost' => ['sometimes', 'integer', 'min:0'],
            'return_reserve' => ['sometimes', 'integer', 'min:0'],
        ]);

        $items = $order->items()->with('product.offers')->get()->keyBy('id');
        $errors = [];
        foreach ($data['items'] ?? [] as $id => $line) {
            /** @var OrderItem|null $item */
            $item = $items->get($id);
            if (! $item) {
                continue;
            }
            $floor = $item->product->floorPrice();
            if (! $user->isManager() && $floor !== null && $line['unit_price'] < $floor) {
                $errors["items.$id.unit_price"] = 'قیمت «'.$item->product->name.'» کمتر از حداقل قیمت مجاز است. فقط مدیر می‌تواند تأیید کند.';
            }
        }

        $lineTotal = collect($data['items'] ?? [])->sum(fn ($l) => $l['unit_price'] * $l['qty']);
        if (! $user->isManager() && $data['discount'] > 0) {
            $minAllowed = $items->sum(fn ($i) => ($i->product->floorPrice() ?? 0) * ($data['items'][$i->id]['qty'] ?? $i->qty));
            if ($lineTotal - $data['discount'] < $minAllowed) {
                $errors['discount'] = 'این تخفیف سفارش را زیر حداقل قیمت مجاز می‌برد.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($data['items'] ?? [] as $id => $line) {
            $item = $items->get($id);
            if (! $item) {
                continue;
            }
            $fields = ['unit_price' => $line['unit_price'], 'qty' => $line['qty'], 'supplier_id' => $line['supplier_id'] ?? $item->supplier_id];
            if ($user->isManager()) {
                $fields['unit_cost'] = $line['unit_cost'] ?? null;
            }
            $item->update($fields);
        }

        $fields = collect($data)->only(['discount', 'shipping_charge', 'promised_at', 'assigned_to', 'fitment_note'])->all();
        if ($user->isManager()) {
            $fields += collect($data)->only(['shipping_cost', 'inbound_cost', 'packaging_cost', 'payment_fee', 'acquisition_cost', 'return_reserve'])->all();
        }
        if ($request->boolean('fitment_confirmed') && ! $order->fitment_checked_by) {
            $fields['fitment_checked_by'] = $user->id;
            $order->log('تطبیق قطعه با خودرو تأیید شد.', $user);
        } elseif (! $request->boolean('fitment_confirmed') && $order->fitment_checked_by && $order->status === 'awaiting_confirmation') {
            $fields['fitment_checked_by'] = null;
        }
        $order->update($fields);

        return back()->with('status', 'سفارش به‌روز شد.');
    }

    public function status(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'cancel_reason' => ['required_if:status,cancelled', 'nullable', Rule::in(array_keys(Order::CANCEL_REASONS))],
            'return_reason' => ['required_if:status,returned', 'nullable', Rule::in(array_keys(Order::RETURN_REASONS))],
            'tracking_code' => ['required_if:status,shipped', 'nullable', 'string', 'max:60'],
        ]);
        abort_unless($order->canMoveTo($data['status']), 422, 'این تغییر وضعیت مجاز نیست.');

        if ($data['status'] === 'confirmed') {
            $order->load('items.product');
            $needsCheck = $order->items->contains(fn ($i) => $i->product->requires_fitment_check);
            if ($needsCheck && ! $order->fitment_checked_by) {
                throw ValidationException::withMessages(['status' => 'این سفارش قطعه‌ای دارد که بررسی تطبیق لازم دارد. ابتدا تطبیق را تأیید کنید.']);
            }
            if (! $order->promised_at) {
                throw ValidationException::withMessages(['status' => 'پیش از تأیید، تاریخ ارسال قول‌داده‌شده را ثبت کنید.']);
            }
            if ($order->items->contains(fn ($i) => $i->product->stock_status === 'out_of_stock')) {
                throw ValidationException::withMessages(['status' => 'یکی از اقلام ناموجود است. موجودی را تأیید یا سفارش را اصلاح کنید.']);
            }
        }

        $stamp = ['confirmed' => 'confirmed_at', 'shipped' => 'shipped_at', 'delivered' => 'delivered_at'][$data['status']] ?? null;
        $order->update(array_filter([
            'status' => $data['status'],
            'cancel_reason' => $data['cancel_reason'] ?? null,
            'return_reason' => $data['return_reason'] ?? null,
            'tracking_code' => $data['tracking_code'] ?? null,
        ]) + ($stamp ? [$stamp => now()] : []));

        if ($data['status'] === 'paid') {
            foreach ($order->items()->with('product')->get() as $item) {
                if ($item->product->stock_status === 'in_stock') {
                    $item->product->decrement('stock_qty', min($item->qty, $item->product->stock_qty));
                }
            }
        }

        $reason = Order::CANCEL_REASONS[$data['cancel_reason'] ?? ''] ?? Order::RETURN_REASONS[$data['return_reason'] ?? ''] ?? null;
        $order->log('وضعیت: '.Order::STATUSES[$data['status']].($reason ? ' — علت: '.$reason : '').(! empty($data['tracking_code']) ? ' — کد رهگیری '.$data['tracking_code'] : ''), $request->user());

        return back()->with('status', 'وضعیت سفارش تغییر کرد.');
    }
}
