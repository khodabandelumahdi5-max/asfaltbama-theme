<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\Rfq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuyerRfqController extends Controller
{
    public function index(Request $request)
    {
        return view('panel.buyer.rfqs', [
            'rfqs' => $request->user()->rfqs()->withCount('quotes')->latest()->paginate(20),
        ]);
    }

    public function show(Request $request, Rfq $rfq)
    {
        abort_unless($rfq->buyer_id === $request->user()->id, 404);

        return view('panel.buyer.rfq-show', [
            'rfq' => $rfq,
            'quotes' => $rfq->quotes()->with('company')->orderBy('unit_price')->get(),
        ]);
    }

    /** Buyer picks a winning quote: it's accepted, the rest rejected, the RFQ closed. */
    public function accept(Request $request, Quote $quote)
    {
        $rfq = $quote->rfq;
        abort_unless($rfq->buyer_id === $request->user()->id, 404);
        abort_unless($rfq->isOpen(), 422, 'این استعلام قبلاً بسته شده است.');

        DB::transaction(function () use ($rfq, $quote) {
            $rfq->quotes()->whereKeyNot($quote->id)->update(['status' => 'rejected']);
            $quote->update(['status' => 'accepted']);
            $rfq->update(['status' => 'closed']);
            $quote->company->contacts()->where('phone', $rfq->contact_phone)
                ->update(['stage' => 'won', 'deal_value' => (int) round($quote->unit_price * $rfq->quantity)]);
        });

        return back()->with('status', 'پیشنهاد '.$quote->company->name.' پذیرفته شد. تأمین‌کننده با شما تماس می‌گیرد.');
    }
}
