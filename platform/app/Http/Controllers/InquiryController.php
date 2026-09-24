<?php

namespace App\Http\Controllers;

use App\Models\CrmContact;
use App\Models\Product;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    /**
     * A buyer contacts a supplier about a product. The message lands in the
     * supplier's inbox and the buyer is captured as a lead in their CRM.
     */
    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'message' => ['required', 'string', 'max:2000'],
        ], ['phone.regex' => 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.']);

        $company = $product->company;
        $contact = CrmContact::captureLead($company, [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'user_id' => $request->user()?->id,
        ], 'inquiry');

        $company->inquiries()->create($data + [
            'product_id' => $product->id,
            'user_id' => $request->user()?->id,
            'crm_contact_id' => $contact->id,
        ]);

        return back()->with('status', 'پیام شما برای '.$company->name.' ارسال شد.');
    }
}
