<?php

namespace App\Http\Controllers\Panel;

use App\Models\CrmContact;
use App\Models\Rfq;
use Illuminate\Http\Request;

class QuoteController extends SupplierController
{
    public function index()
    {
        return view('panel.quotes.index', [
            'quotes' => $this->company()->quotes()->with('rfq')->latest()->paginate(20),
        ]);
    }

    /**
     * Supplier submits a price for an open RFQ; the requester becomes a CRM lead.
     */
    public function store(Request $request, Rfq $rfq)
    {
        abort_unless($rfq->isOpen(), 422, 'این استعلام بسته شده است.');
        $company = $this->company();

        $data = $request->validate([
            'unit_price' => ['required', 'integer', 'min:1'],
            'delivery_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $rfq->quotes()->updateOrCreate(['company_id' => $company->id], $data + ['status' => 'pending']);

        $contact = CrmContact::captureLead($company, [
            'name' => $rfq->contact_name,
            'phone' => $rfq->contact_phone,
            'user_id' => $rfq->buyer_id,
            'deal_value' => (int) round($data['unit_price'] * $rfq->quantity),
        ], 'rfq');

        $contact->activities()->create([
            'user_id' => $request->user()->id,
            'type' => 'note',
            'body' => 'پیشنهاد قیمت '.toman($data['unit_price']).' برای «'.$rfq->title.'» ارسال شد.',
        ]);

        return back()->with('status', 'پیشنهاد قیمت شما ثبت شد و خریدار به CRM اضافه شد.');
    }
}
