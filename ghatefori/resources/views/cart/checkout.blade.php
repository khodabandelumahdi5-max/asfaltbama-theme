@extends('layouts.shop')
@section('title', 'ثبت سفارش')

@section('content')
@php($items = $lines->sum('subtotal'))
@php($ship = \App\Support\Cart::shippingFor($city))
<div class="container mt">
    <h1>ثبت سفارش</h1>
    <div class="grid layout-side" style="grid-template-columns:1fr 340px;align-items:start">
        <form method="POST" action="{{ route('checkout.place') }}" class="card" id="checkout">
            @csrf
            <h3>اطلاعات تماس</h3>
            <div class="grid g3">
                <div class="field"><label>نام *</label><input name="name" value="{{ old('name') }}" required></div>
                <div class="field"><label>موبایل *</label><input name="phone" class="ltr" value="{{ old('phone') }}" placeholder="09123456789" required></div>
                <div class="field"><label>خرید برای</label>
                    <select name="type">@foreach(\App\Models\Customer::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $l }}</option>@endforeach</select>
                </div>
            </div>
            <h3 class="mt">خودرو (برای بررسی تطبیق)</h3>
            <div class="field">@include('partials.vehicle-select', ['name' => 'vehicle_id', 'selected' => old('vehicle_id', $vehicleId)])</div>
            <div class="grid g3">
                <div class="field"><label>یا بنویسید</label><input name="vehicle_text" value="{{ old('vehicle_text') }}"></div>
                <div class="field"><label>سال ساخت</label><input type="number" name="year" value="{{ old('year') }}"></div>
                <div class="field"><label>شماره شاسی (اختیاری)</label><input name="vin" class="ltr" value="{{ old('vin') }}" maxlength="20"></div>
            </div>
            <h3 class="mt">ارسال</h3>
            <div class="grid g3">
                <div class="field"><label>شهر *</label>
                    <select name="city" onchange="location.search='?city='+encodeURIComponent(this.value)">@foreach(config('shop.cities') as $c)<option @selected($city === $c)>{{ $c }}</option>@endforeach</select>
                </div>
                <div class="field" style="grid-column:span 2"><label>آدرس کامل *</label><input name="address" value="{{ old('address') }}" required></div>
            </div>
            <div class="grid g2">
                <div class="field"><label>از کجا با ما آشنا شدید؟</label>
                    <select name="source"><option value="">—</option>@foreach(\App\Models\Customer::SOURCES as $k => $l)<option value="{{ $k }}" @selected(old('source') === $k)>{{ $l }}</option>@endforeach</select>
                </div>
                <div class="field"><label>توضیحات</label><input name="note" value="{{ old('note') }}"></div>
            </div>
            <div class="field"><label class="row" style="font-weight:400"><input type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent'))> یادآوری سرویس و پیشنهادهای مرتبط با خودرویم را دریافت کنم (قابل لغو)</label></div>
        </form>
        <aside class="card">
            <h3>خلاصه</h3>
            @foreach($lines as $line)
                <div class="row between small" style="padding:4px 0"><span>{{ $line['product']->name }} × {{ fa_digits($line['qty']) }}</span><span>{{ fa_number($line['subtotal']) }}</span></div>
            @endforeach
            <hr style="border:0;border-top:1px solid var(--line)">
            <div class="row between"><span>کالاها</span><b>{{ toman($items) }}</b></div>
            <div class="row between"><span>ارسال به {{ $city }}</span><b>{{ toman($ship['charge']) }}</b></div>
            <div class="small muted">زمان رسیدن پس از ارسال: {{ $ship['eta'] }}</div>
            <div class="row between mt" style="font-size:1.1rem"><span>مبلغ قابل پرداخت</span><b>{{ toman($items + $ship['charge']) }}</b></div>
            <p class="small muted">پرداخت الان انجام نمی‌شود. پس از تأیید موجودی، قیمت، زمان ارسال و تطبیق قطعه، لینک پرداخت برایتان ارسال می‌شود.</p>
            <button form="checkout" class="btn btn-accent btn-block">ثبت سفارش</button>
        </aside>
    </div>
</div>
<style>@media(max-width:860px){.layout-side{grid-template-columns:1fr!important}}</style>
@endsection
