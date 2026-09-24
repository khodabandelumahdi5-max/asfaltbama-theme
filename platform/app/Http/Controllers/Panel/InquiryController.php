<?php

namespace App\Http\Controllers\Panel;

use App\Models\Inquiry;

class InquiryController extends SupplierController
{
    public function index()
    {
        return view('panel.inquiries.index', [
            'inquiries' => $this->company()->inquiries()->with('product', 'contact')->latest()->paginate(20),
        ]);
    }

    public function show(Inquiry $inquiry)
    {
        $this->authorizeOwned($inquiry);
        $inquiry->read_at ??= now();
        $inquiry->save();

        return view('panel.inquiries.show', ['inquiry' => $inquiry->load('product', 'contact')]);
    }
}
