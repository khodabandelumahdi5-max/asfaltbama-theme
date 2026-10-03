<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FitmentRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reminder;
use App\Models\StockAlert;
use App\Models\SupplierOffer;

class DashboardController extends Controller
{
    /** Work queue: every item here needs an owner today. */
    public function index()
    {
        return view('admin.dashboard', [
            'awaiting' => Order::with('customer')->where('status', 'awaiting_confirmation')->oldest()->get(),
            'toShip' => Order::with('customer')->where('status', 'paid')->orderBy('promised_at')->get(),
            'late' => Order::whereIn('status', ['confirmed', 'paid', 'shipped'])->whereNotNull('promised_at')->where('promised_at', '<', now())->count(),
            'fitments' => FitmentRequest::where('status', 'pending')->oldest()->get(),
            'followUps' => FitmentRequest::where('status', 'compatible')->whereNull('followed_up_at')->where('checked_at', '<', now()->subDay())->count(),
            'alertsReady' => StockAlert::whereNull('notified_at')->whereHas('product', fn ($q) => $q->where('stock_status', 'in_stock'))->count(),
            'expiredOffers' => SupplierOffer::whereNotNull('valid_until')->where('valid_until', '<', today())->count(),
            'stalePrices' => Product::active()->where(fn ($q) => $q->whereNull('price_checked_at')->orWhere('price_checked_at', '<', now()->subDays(7)))->count(),
            'reminders' => Reminder::with('customer')->whereNull('done_at')->where('due_on', '<=', today()->addDays(7))
                ->whereHas('customer', fn ($q) => $q->where('marketing_consent', true))->orderBy('due_on')->take(10)->get(),
        ]);
    }
}
