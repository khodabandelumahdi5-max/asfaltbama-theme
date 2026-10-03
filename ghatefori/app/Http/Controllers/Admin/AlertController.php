<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockAlert;

class AlertController extends Controller
{
    public function index()
    {
        return view('admin.alerts', [
            'alerts' => StockAlert::with('product')->whereNull('notified_at')
                ->orderByRaw("(select stock_status from products where products.id = stock_alerts.product_id) = 'in_stock' desc")
                ->oldest()->paginate(50),
        ]);
    }

    public function notified(StockAlert $alert)
    {
        $alert->update(['notified_at' => now()]);

        return back()->with('status', 'تماس با '.$alert->name.' ثبت شد.');
    }
}
