<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Rfq;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RfqController extends Controller
{
    public function index(Request $request)
    {
        $rfqs = Rfq::with('category')->withCount('quotes')
            ->when($request->input('status', 'open') !== 'all', fn ($q) => $q->where('status', 'open'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('rfqs.index', compact('rfqs'));
    }

    public function create(Request $request)
    {
        return view('rfqs.create', [
            'categories' => Category::orderBy('sort')->get(),
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', Rule::in(Product::UNITS)],
            'city' => ['nullable', 'string', 'max:60'],
            'details' => ['nullable', 'string', 'max:3000'],
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_phone' => ['required', 'regex:/^09\d{9}$/'],
        ], ['contact_phone.regex' => 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.']);

        $rfq = Rfq::create($data + ['buyer_id' => $request->user()?->id]);

        return redirect()->route('rfqs.show', $rfq)
            ->with('status', 'درخواست استعلام شما ثبت شد. تأمین‌کنندگان به‌زودی قیمت می‌دهند.');
    }

    public function show(Request $request, Rfq $rfq)
    {
        $rfq->load('category')->loadCount('quotes');
        $company = $request->user()?->company;

        return view('rfqs.show', [
            'rfq' => $rfq,
            'myQuote' => $company ? $rfq->quotes()->where('company_id', $company->id)->first() : null,
            'canQuote' => $company !== null && $rfq->isOpen(),
        ]);
    }
}
