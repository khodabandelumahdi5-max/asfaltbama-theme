@extends('layouts.app')
@section('title', $rfq->title)

@section('content')
<div class="container mt" style="max-width:900px">
    <div class="card">
        <div class="row between">
            <h1 style="margin:0">{{ $rfq->title }}</h1>
            @if($rfq->isOpen())<span class="badge badge-ok">باز</span>@else<span class="badge">بسته شده</span>@endif
        </div>
        <div class="grid g4 mt">
            <div class="stat"><span>مقدار</span><b>{{ fa_number($rfq->quantity, 2) }} {{ $rfq->unit }}</b></div>
            <div class="stat"><span>دسته</span><b style="font-size:1rem">{{ $rfq->category->name ?? '—' }}</b></div>
            <div class="stat"><span>محل تحویل</span><b style="font-size:1rem">{{ $rfq->city ?? '—' }}</b></div>
            <div class="stat"><span>پیشنهادها</span><b>{{ fa_number($rfq->quotes_count) }}</b></div>
        </div>
        <h3 class="mt">توضیحات</h3>
        <p style="white-space:pre-line">{{ $rfq->details ?: '—' }}</p>
        <p class="small muted">ثبت شده در {{ jdate($rfq->created_at) }} · اطلاعات تماس خریدار پس از ارسال پیشنهاد در CRM شما قرار می‌گیرد.</p>
    </div>

    @if($canQuote)
        <form method="POST" action="{{ route('panel.quotes.store', $rfq) }}" class="card mt">
            @csrf
            <h2>{{ $myQuote ? 'ویرایش پیشنهاد شما' : 'ارسال پیشنهاد قیمت' }}</h2>
            <div class="grid g2">
                <div class="field"><label>قیمت هر {{ $rfq->unit }} (تومان) *</label><input type="number" min="1" name="unit_price" value="{{ old('unit_price', $myQuote?->unit_price) }}" required></div>
                <div class="field"><label>زمان تحویل (روز)</label><input type="number" min="0" name="delivery_days" value="{{ old('delivery_days', $myQuote?->delivery_days) }}"></div>
            </div>
            <div class="field"><label>توضیحات پیشنهاد</label><textarea name="message" rows="3">{{ old('message', $myQuote?->message) }}</textarea></div>
            <button class="btn btn-amber">ارسال پیشنهاد</button>
        </form>
    @elseif($rfq->isOpen() && ! auth()->user()?->company)
        <div class="card mt row between">
            <span>تأمین‌کننده هستید؟ برای ارسال پیشنهاد قیمت وارد شوید.</span>
            <a href="{{ route('register', ['role' => 'supplier']) }}" class="btn btn-amber">ثبت‌نام تأمین‌کننده</a>
        </div>
    @endif
</div>
@endsection
