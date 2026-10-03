<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FitmentRequest;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** Weekly numbers (Saturday–Friday) to decide from, not guess. */
    public function weekly(Request $request)
    {
        $offset = max(0, $request->integer('week'));
        $start = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SATURDAY)->subWeeks($offset);
        $end = $start->endOfWeek(CarbonImmutable::FRIDAY);

        $inquiries = FitmentRequest::whereBetween('created_at', [$start, $end])->get();
        $orders = Order::with('items', 'customer')->whereBetween('created_at', [$start, $end])->get();
        $completed = $orders->whereIn('status', ['paid', 'shipped', 'delivered']);
        $checked = $inquiries->whereNotNull('checked_at');

        $responseMinutes = $checked->map(fn ($f) => $f->created_at->diffInMinutes($f->checked_at))->avg();
        $orderCustomers = $orders->pluck('customer_id')->unique();
        $repeat = $orderCustomers->filter(fn ($id) => Order::where('customer_id', $id)->where('created_at', '<', $start)->exists())->count();

        $contribution = $completed->map->contribution();

        $metrics = [
            'inquiries' => $inquiries->count(),
            'response_minutes' => $responseMinutes !== null ? (int) round($responseMinutes) : null,
            'sourceable_pct' => $checked->count() ? round(100 * $checked->whereIn('status', ['compatible'])->count() / $checked->count()) : null,
            'converted_pct' => $inquiries->count() ? round(100 * $inquiries->filter(fn ($f) => $f->customer_id && Order::where('customer_id', $f->customer_id)->where('created_at', '>=', $f->created_at)->exists())->count() / $inquiries->count()) : null,
            'orders' => $orders->count(),
            'revenue' => $completed->sum(fn ($o) => $o->total()),
            'contribution' => $contribution->contains(null) ? null : $contribution->sum(),
            'unknown_cost_orders' => $contribution->filter(fn ($c) => $c === null)->count(),
            'acquisition' => $orders->sum('acquisition_cost'),
            'cancel_stock_price' => $orders->where('status', 'cancelled')->whereIn('cancel_reason', ['no_stock', 'price_change'])->count(),
            'returns_fitment' => Order::where('status', 'returned')->where('return_reason', 'fitment')->whereBetween('updated_at', [$start, $end])->count(),
            'late' => Order::whereNotNull('promised_at')->whereBetween('promised_at', [$start, $end])
                ->where(fn ($q) => $q->whereNull('shipped_at')->orWhereColumn('shipped_at', '>', 'promised_at'))
                ->whereNotIn('status', ['cancelled'])->count(),
            'repeat_customers' => $repeat,
        ];

        return view('admin.report', compact('metrics', 'start', 'end', 'offset'));
    }
}
