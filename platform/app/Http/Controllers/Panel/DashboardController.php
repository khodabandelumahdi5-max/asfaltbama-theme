<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\Rfq;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isSupplier() && $company = $user->company) {
            $pipeline = $company->contacts()->selectRaw('stage, count(*) as total, sum(deal_value) as value')
                ->groupBy('stage')->get()->keyBy('stage');

            return view('panel.supplier-dashboard', [
                'company' => $company,
                'stats' => [
                    'products' => $company->products()->count(),
                    'unread' => $company->inquiries()->whereNull('read_at')->count(),
                    'quotes' => $company->quotes()->count(),
                    'won' => $company->contacts()->where('stage', 'won')->sum('deal_value'),
                ],
                'pipeline' => $pipeline,
                'stages' => CrmContact::STAGES,
                'inquiries' => $company->inquiries()->with('product')->latest()->take(5)->get(),
                'tasks' => CrmActivity::with('contact')
                    ->whereHas('contact', fn ($q) => $q->where('company_id', $company->id))
                    ->whereNull('done_at')->whereNotNull('due_at')
                    ->orderBy('due_at')->take(6)->get(),
                'openRfqs' => Rfq::where('status', 'open')
                    ->whereDoesntHave('quotes', fn ($q) => $q->where('company_id', $company->id))
                    ->latest()->take(5)->get(),
            ]);
        }

        if ($user->isAdmin()) {
            return view('panel.admin-dashboard', [
                'pending' => Company::where('is_verified', false)->count(),
                'companies' => Company::count(),
                'rfqs' => Rfq::count(),
            ]);
        }

        return view('panel.buyer-dashboard', [
            'rfqs' => $user->rfqs()->withCount('quotes')->latest()->take(10)->get(),
        ]);
    }
}
